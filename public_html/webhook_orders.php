<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$config = require '/home/u394535132/domains/lightgray-vulture-703201.hostingersite.com/config.php';
// Your shared secret from Shopify
$sharedSecret = $config['SHOPIFY_WEBHOOKID']; // Replace with your actual shared secret
$headers = getallheaders();

// Function to verify Shopify webhook
function verifyShopifyWebhook($data, $hmacHeader, $sharedSecret) {
    $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $sharedSecret, true));
    return hash_equals($hmacHeader, $calculatedHmac);
}


// Check if the HMAC header exists
if (isset($headers['X-Shopify-Hmac-Sha256'])) {
    $hmacHeader = $headers['X-Shopify-Hmac-Sha256'];
    
    // Read the POST data from Shopify webhook
    $requestData = file_get_contents('php://input');
    
    // Log the headers and the request data for debugging
    $headers = getallheaders();
    $logData = "Headers:\n" . print_r($headers, true) . "\n\nRequest Data:\n" . $requestData;
    file_put_contents( getcwd() . '/'. 'logfileNew.log', $logData, FILE_APPEND);
        
    // Verify the request
    if (!verifyShopifyWebhook($requestData, $hmacHeader, $sharedSecret)) {
        http_response_code(401);
        die('Unauthorized access to the Order WebService');
    }

    $host = $config['DB_HOST'];
    $user = $config['DB_USER'];
    $pass = $config['DB_PASS'];
    $dbname = $config['DB_NAME'];

    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        http_response_code(500);
        die("DB Connection failed");
    }

    // === Get Request Payload ===
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    // === Shopify Headers ===
    $webhookId  = $_SERVER['HTTP_X_SHOPIFY_WEBHOOK_ID'] ?? '';
    $topic      = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';
    $shopDomain = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';
    $orderId = $data['id'] ?? null;   // Shopify order_id
    $orderNumber  = $data['order_number'] ?? null;      // human-friendly order number
    $shipCountryCode = strtoupper(trim($data['shipping_address']['country_code'] ?? ''));
    $shipProvince = strtoupper(trim($data['shipping_address']['province'] ?? ''));
    $currency = $data['presentment_currency'] ?? null; 
    
    
    //ENTRY CONDITIONS FOR ORDERS: Only UK, currency GBP and not NI
    if ($shipCountryCode !== 'GB' || $currency !== 'GBP' || strtolower(trim($shipProvince)) === 'northern ireland') {
        http_response_code(200); // Always acknowledge to Shopify
        echo "Ignored order: country $shipCountryCode";
        exit;
    }
    
    //REJECT MEMEBRSHIP PRODUCTS
    $onlyMembership = true; // assume all are TEST1/TEST2 until proven otherwise

    foreach ($data['line_items'] as $item) {
        $title = strtoupper(trim($item['sku'] ?? ''));
    
        if ($title !== 'WSAM150' && $title !== 'WSMM15') {
            $onlyMembership = false;
            break; // found a non-test item — stop checking
        }
    }
    
    if ($onlyMembership) {
        http_response_code(200); // Always acknowledge to Shopify
        echo "Ignored order: only membership items";
        exit;
    }
    
    
    // === Deduplicate ===
    $stmt = $conn->prepare("SELECT id FROM shopify_orders WHERE order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        http_response_code(200);
        echo "Duplicate ignored";
        exit;
    }

    // === Extract useful fields ===
    
    $jsonPayload = json_encode($data);

    // === Insert into DB with status NEW ===
    $stmt = $conn->prepare("
    INSERT INTO shopify_orders 
        (webhook_id, topic, shop_domain, order_id, order_number, payload, status) 
    VALUES (?, ?, ?, ?, ?, ?, 'NEW')
    ");
    
    $stmt->bind_param("sssiis", $webhookId, $topic, $shopDomain, $orderId, $orderNumber, $jsonPayload);


    if ($stmt->execute()) {
        http_response_code(200); // ✅ Respond fast
        echo "OK";
    } else {
        http_response_code(500);
        echo "DB insert failed";
    }

    $conn->close();

    // Respond to Shopify with a 200 status code
    http_response_code(200);
    

} else {
    // HMAC header not found
    http_response_code(400);
    echo "HMAC header not found\n";
}
?>