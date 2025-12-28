<?php
// CONFIGURATION
                 // latest stable version
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

$config = require '/home/u394535132/domains/lightgray-vulture-703201.hostingersite.com/config.php';

// folder to save images
$save_dir       = getcwd() . "/Images";             

// Shopify
$SHOP_DOMAIN  = $config['SHOPIFY_URL'];
$API_VERSION  = $config['SHOPIFY_API_VERSION'];
$ADMIN_TOKEN  = $config['SHOPIFY_ADMIN_TOKEN'];

// =========================================
// INITIAL SETUP
// =========================================
if (!is_dir($save_dir)) mkdir($save_dir, 0755, true);

// =========================================
// TIMEOUT SETTINGS
// =========================================
$TIMEOUT_SECONDS = 600; // 10 minutes

// =========================================
// Shopify API Request Helper
// =========================================
function shopify_request($url, $token, &$next_page_info = null, $timeout = 600) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: $token",
            "Content-Type: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 30,  // connection timeout (sec)
        CURLOPT_TIMEOUT => $timeout,   // total timeout (sec)
    ]);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_text = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);

    $next_page_info = null;

    // Parse Link header to get next page_info if exists
    if (preg_match('/<([^>]+)>;\s*rel="next"/i', $header_text, $matches)) {
        $next_url = $matches[1];
        $query = parse_url($next_url, PHP_URL_QUERY);
        parse_str($query, $query_params);
        if (isset($query_params['page_info'])) {
            $next_page_info = $query_params['page_info'];
        }
    }

    return json_decode($body, true);
}

// =========================================
// Safe image downloader with timeout
// =========================================
function download_image($url, $timeout = 600) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]
    ]);
    return @file_get_contents($url, false, $context);
}

// =========================================
// Fetch all products, following page_info
// =========================================
$base_url = "https://$SHOP_DOMAIN/admin/api/$API_VERSION/products.json?limit=250";
$page_info = null;
$total_count = 0;

do {
    $url = $page_info
        ? "https://$SHOP_DOMAIN/admin/api/$API_VERSION/products.json?limit=250&page_info=" . urlencode($page_info)
        : $base_url;

    $data = shopify_request($url, $ADMIN_TOKEN, $next_page_info, $TIMEOUT_SECONDS);
    if (empty($data['products'])) {
        echo "No more products.\n";
        break;
    }

    foreach ($data['products'] as $product) {
        $total_count++;
        $sku = !empty($product['variants'][0]['sku']) ? trim($product['variants'][0]['sku']) : $product['handle'];
        if (empty($sku)) continue;

        $img_url = null;
        if (!empty($product['image']['src'])) {
            $img_url = $product['image']['src'];
        } elseif (!empty($product['images'][0]['src'])) {
            $img_url = $product['images'][0]['src'];
        }

        if (!$img_url) {
            //echo "No image for {$product['title']}\n";
            continue;
        }

        $save_path = "$save_dir/$sku.png";
        $img_data = download_image($img_url, $TIMEOUT_SECONDS);
        if ($img_data === false) {
            echo "Failed to download: $img_url\n";
            continue;
        }

        file_put_contents($save_path, $img_data);
        echo "✅ Saved ($total_count): $save_path\n";
    }

    $page_info = $next_page_info;
    sleep(1); // respect Shopify rate limits
} while ($page_info);

echo "🎉 Completed. Downloaded $total_count product images.\n";
?>

?>
/*
// Ensure the folder exists
if (!is_dir($save_dir)) {
    mkdir($save_dir, 0755, true);
}

// Helper function to call Shopify API
function shopify_request($url, $ADMIN_TOKEN) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $ADMIN_TOKEN",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status != 200) {
        echo "Error fetching $url (HTTP $status)\n";
        return false;
    }
    return json_decode($response, true);
}

$page_info = null;
do {
    $url = "https://$SHOP_DOMAIN/admin/api/$API_VERSION/products.json?limit=250";
    if ($page_info) $url .= "&page_info=" . urlencode($page_info);

    $response = shopify_request($url, $ADMIN_TOKEN);
    if (!$response || empty($response['products'])) break;

    foreach ($response['products'] as $product) {
        $sku = null;
        // Try to get SKU from first variant
        if (!empty($product['variants'][0]['sku'])) {
            $sku = trim($product['variants'][0]['sku']);
        } else {
            // fallback to product handle if no SKU
            $sku = $product['handle'];
        }

        // Use main image (or first image)
        if (!empty($product['image']['src'])) {
            $img_url = $product['image']['src'];
        } elseif (!empty($product['images'][0]['src'])) {
            $img_url = $product['images'][0]['src'];
        } else {
            echo "No image found for {$product['title']}\n";
            continue;
        }

        $save_path = "$save_dir/$sku.png";

        // Download and save the image
        $img_data = file_get_contents($img_url);
        if ($img_data === false) {
            echo "Failed to download $img_url\n";
            continue;
        }
        file_put_contents($save_path, $img_data);
        echo "Saved: $save_path\n";
    }

    // Pagination (Link header)
    $headers = $http_response_header ?? [];
    $next_link = null;
    foreach ($headers as $header) {
        if (stripos($header, 'link:') !== false && preg_match('/<([^>]+)>; rel="next"/i', $header, $matches)) {
            $next_link = $matches[1];
        }
    }
    $page_info = $next_link ? parse_url($next_link, PHP_URL_QUERY) : null;
} while ($page_info);

echo "✅ Done fetching all product images.\n";
?>
*/
