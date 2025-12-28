<?php
// ship_created.php — find CREATED orders, get tracking, fulfill in Shopify, mark SHIPPED

// ===== DEBUG (disable in production) =====
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =====================
// CONFIG — EDIT THE PATH
// =====================
$config = require '/home/u394535132/domains/lightgray-vulture-703201.hostingersite.com/config.php';

// DB
$DB_HOST = $config['DB_HOST'];
$DB_USER = $config['DB_USER'];
$DB_PASS = $config['DB_PASS'];
$DB_NAME = $config['DB_NAME'];

// Shopify
$SHOP_DOMAIN  = $config['SHOPIFY_URL'];
$API_VERSION  = $config['SHOPIFY_API_VERSION'];
$ADMIN_TOKEN  = $config['SHOPIFY_ADMIN_TOKEN'];

// Vision (OrdPckd) — tracking source
$VISION_BASE_URL =  $config['LCB_API_URL'];                            // no trailing slash
$CUSTOMER_CODE   =  $config['LCB_CUSTOMER_CODE'];                      // your code
$SITE_CODE       =  $config['LCB_SITE_CODE'];                          // your site
$DOCREF_PREFIX   =  $config['LCB_ORDER_PREFIX'];                       // how you build DocRef, e.g. HC-<order_number>

//LCB Credentials
$VISION_CLIENT_ID      = $config['LCB_CLIENTID'];
$VISION_CLIENT_SECRET = $config['LCB_CLIENTSECRET'];
// =====================
// DB CONNECTION
// =====================
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$conn->set_charset('utf8mb4');

// =====================
// HELPERS
// =====================

// Get all orders by status (CREATED)
function getOrdersByStatus(mysqli $conn, string $status = 'CREATED'): array {
    $rows = [];
    $stmt = $conn->prepare("SELECT id, order_number, payload FROM shopify_orders WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

// Update row to SHIPPED with tracking + carrier
function markShipped(mysqli $conn, int $orderNumber, string $trackingNumber, string $carrier): void {
    $sql = "
        UPDATE shopify_orders
           SET status = 'SHIPPED',
               tracking_number = ?,
               carrier = ?,
               payload = NULL
         WHERE order_number = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $trackingNumber, $carrier, $orderNumber);
    $stmt->execute();
    $stmt->close();
}

// ---- Vision token (optional) ----
function getAccessToken(string $clientIdRaw, string $clientSecretRaw, string $tokenUrl = 'https://kingmoor.lcb.co.uk/token'): array {
    $ch = curl_init($tokenUrl);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientIdRaw,
            'client_secret' => $clientSecretRaw,
        ]),
    ];
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
    curl_close($ch);

    if ($err) return ['ok' => false, 'token' => null];
    $json = json_decode($resp, true);
    return ['ok' => !empty($json['access_token']), 'token' => $json['access_token'] ?? null];
}

// ---- Vision GET OrdPckd ----
function visionGetOrdPckdRaw(string $baseUrl, string $accessToken, string $customerCode, string $siteCode, ?string $docRef = null): array
{
    $url = rtrim($baseUrl, '/')
        . '/service/OrdPckd'
        . '/CustomerCode/' . rawurlencode($customerCode)
        . '/SiteCode/'     . rawurlencode($siteCode);

    if (!empty($docRef)) {
        $url .= '/DocRef/' . rawurlencode($docRef);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/xml, text/xml;q=0.9, application/json;q=0.8, */*;q=0.1',
        ],
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'http_code' => $code, 'url' => $url, 'body' => $resp, 'error' => "cURL error: $err"];
    }
    if ($code === 204) {
        return ['ok' => false, 'http_code' => 204, 'url' => $url, 'body' => $resp, 'error' => 'No record found (204)'];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'http_code' => $code, 'url' => $url, 'body' => $resp, 'error' => 'Non-2xx HTTP response'];
    }

    return ['ok' => true, 'http_code' => $code, 'url' => $url, 'body' => $resp, 'error' => null];
}

// Parse tracking/carrier from OrdPckd XML
function parseTrackingFromOrdPckd(string $xmlBody): array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlBody);
    if ($xml === false) {
        return ['tracking' => null, 'carrier' => null];
    }
    $pick = $xml->PICK ?? $xml;
    $hd   = $pick->HD ?? null;
    $pcon = $hd ? ($hd->PCon ?? null) : null;

    $tracking = $pcon ? trim((string)($pcon->PConNo ?? '')) : '';
    $carrier  = $pcon ? trim((string)($pcon->PConNm ?? '')) : '';

    return [
        'tracking' => $tracking !== '' ? $tracking : null,
        'carrier'  => $carrier !== '' ? $carrier : 'DPD Local',
    ];
}

// ---- Shopify: fulfill with tracking ----
function fulfillShopifyOrder(
    string $shop,
    string $adminToken,
    string $apiVersion,
    string $carrierName,
    string $trackingNo,
    int $orderId,
    ?string $trackingUrl = null,
    bool $notifyCustomer = false
): array {
    $shopifyRequest = function (string $method, string $url, ?array $json = null) use ($adminToken): array {
        $ch = curl_init($url);
        $headers = [
            'X-Shopify-Access-Token: ' . $adminToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];
        if ($json !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($json);
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
        curl_close($ch);

        return [
            'ok'        => !$err && $code >= 200 && $code < 300,
            'http_code' => $code,
            'error'     => $err,
            'body'      => $resp,
            'json'      => $resp ? json_decode($resp, true) : null,
        ];
    };

    // 1) Get fulfillment orders
    $foUrl = "https://{$shop}/admin/api/{$apiVersion}/orders/{$orderId}/fulfillment_orders.json";
    $foRes = $shopifyRequest('GET', $foUrl);
    if (!$foRes['ok'] || empty($foRes['json']['fulfillment_orders'])) {
        return ['success' => false, 'error' => 'No fulfillment orders found'];
    }

    $fulfillmentOrderId = $foRes['json']['fulfillment_orders'][0]['id'];

    // 2) Create fulfillment
    $createUrl = "https://{$shop}/admin/api/{$apiVersion}/fulfillments.json";
    $payload = [
        'fulfillment' => [
            'line_items_by_fulfillment_order' => [
                ['fulfillment_order_id' => $fulfillmentOrderId],
            ],
            'tracking_info' => array_filter([
                'number'  => $trackingNo,
                'company' => $carrierName,
                'url'     => $trackingUrl,
            ]),
            'notify_customer' => $notifyCustomer,
        ],
    ];

    $fulRes = $shopifyRequest('POST', $createUrl, $payload);
    if (!$fulRes['ok']) {
        return ['success' => false, 'error' => 'Fulfillment failed: ' . ($fulRes['body'] ?? '')];
    }

    return ['success' => true, 'data' => $fulRes['json']];
}

// =====================
// MAIN
// =====================
header('Content-Type: text/html; charset=utf-8');

// 1) Fetch CREATED orders
$orders = getOrdersByStatus($conn, 'CREATED');
if (empty($orders)) {
    echo "No CREATED orders to process.\n";
    exit;
}

// 2) Optional Vision token
$visionBearer = null;
if ($VISION_CLIENT_ID && $VISION_CLIENT_SECRET) {
    $tok = getAccessToken($VISION_CLIENT_ID, $VISION_CLIENT_SECRET);
    if ($tok['ok']) $visionBearer = $tok['token'];
}

// 3) Process each
foreach ($orders as $row) {
    $orderNumber = (int)$row['order_number'];
    $data = json_decode($row['payload'] ?? '', true);
    $orderId = $data['id'] ?? null;

    if (!$orderId || !$orderNumber) {
        echo "Skipping invalid row.\n";
        continue;
    }

    // Get tracking from Vision API
    $docRef = $DOCREF_PREFIX . $orderNumber;
    
    $ordRes = visionGetOrdPckdRaw($VISION_BASE_URL, $visionBearer, $CUSTOMER_CODE, $SITE_CODE, $docRef);
    
    /*
        // Show request URL
    echo "<h2>OrdPckd request</h2>";
    echo "URL: <code>" . htmlspecialchars($ordRes['url'] ?? '') . "</code><br>";
    echo "HTTP Code: " . htmlspecialchars((string)($ordRes['http_code'] ?? 'N/A')) . "<br>";
    
    if ($ordRes['ok']) {
    echo "<p>✅ Response OK</p>";
    } else {
        echo "<p>❌ " . htmlspecialchars((string)($ordRes['error'] ?? 'Error')) . "</p>";
    }
    echo "<h3>Full Raw Response</h3>";
    echo "<pre style='white-space:pre-wrap;word-wrap:break-word;max-width:100%;overflow:auto;'>"
        . htmlspecialchars((string)($ordRes['body'] ?? ''))
        . "</pre>";
    
    */

    if (!$ordRes['ok']) {
        echo "Order {$docRef}: no tracking found ({$ordRes['error']}).\n";
        echo '<br>';
        continue;
    }

    $parsed = parseTrackingFromOrdPckd($ordRes['body']);
    
    if (empty($parsed['tracking'])) {
        echo "Order {$orderNumber}: tracking missing.<br>";
        continue;
    }

    $tracking = $parsed['tracking'];
    $carrier  = $parsed['carrier'];
    
    // Fulfill in Shopify
    $ful = fulfillShopifyOrder($SHOP_DOMAIN, $ADMIN_TOKEN, $API_VERSION, $carrier, $tracking, $orderId, null, false);

    if ($ful['success']) {
        markShipped($conn, $orderNumber, $tracking, $carrier);
        echo "✅ Order {$orderNumber} (ID {$orderId}) shipped with {$carrier}, tracking {$tracking}\n";
        echo '<br>';
        
    } else {
        echo "❌ Order {$orderId}: Shopify fulfillment failed ({$ful['error']})\n";
        echo '<br>';
    }
    
}

//echo "Done.\n";
