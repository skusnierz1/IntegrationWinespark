<?php
// Enable error reporting (for debugging, remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$config = require '/home/u394535132/domains/lightgray-vulture-703201.hostingersite.com/config.php';
// === Database connection ===

ini_set('display_errors', 0); // hide on screen
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');  // save to file

$host = $config['DB_HOST'];
$user = $config['DB_USER'];
$pass = $config['DB_PASS'];
$dbname = $config['DB_NAME'];

//$clientID = 'gt5PGyAXmPbutEWD';
//$clientSecret = '$T7eF3C#wa0ONQgvyAyivXL%N';

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("❌ DB connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');


// === Example usage (uncomment for testing) ===
//printPendingOrders($conn);
//updateOrderStatus($conn, 1234, "COMPLETED");

$count = processPendingOrders($conn, $config);
//echo "\nProcessed $count orders.\n";

// Close connection when done
$conn->close();

// === Function 1: Update order status ===
function updateOrderStatus($conn, $orderNumber, $status) {
    $stmt = $conn->prepare("UPDATE shopify_orders SET status = ? WHERE order_number = ?");
    $stmt->bind_param("si", $status, $orderNumber); // status = string, order_number = int
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function updateOrderStatus_API($conn, $orderNumber, $status, $apiStatus, $apiResponseText) {
    $stmt = $conn->prepare("UPDATE shopify_orders SET status = ?, OrderStatusAPI = ?, OrderResponseAPI = ? WHERE order_number = ?");
    $stmt->bind_param("sssi", $status, $apiStatus, $apiResponseText, $orderNumber,); // status = string, order_number = int
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}


// === Function 2: Get all orders not completed ===
function getPendingOrders($conn) {
    $sql = "SELECT DISTINCT * FROM shopify_orders WHERE status = 'PDF'";
    $result = $conn->query($sql);

    $orders = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    $result->free();

    return $orders; // ✅ return array
}


function getAllBundles($conn) {
    $sql = "
        SELECT 
            b.id               AS bundle_id,
            b.sku              AS bundle_sku,
            b.name             AS bundle_name,
            b.created_at       AS bundle_created,
            b.preset           AS bundle_preset,
            i.id               AS item_id,
            i.product_sku,
            i.product_name,
            i.quantity,
            i.price
        FROM bundles AS b
        LEFT JOIN bundle_items AS i
            ON b.id = i.bundle_id
        ORDER BY b.id DESC, i.id ASC
    ";

    $result = $conn->query($sql);
    $bundles = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_object()) {
            $sku = $row->bundle_sku;

            // Initialize bundle if not set yet
            if (!isset($bundles[$sku])) {
                $bundles[$sku] = (object)[
                    'bundle_id'      => $row->bundle_id,
                    'bundle_sku'     => $row->bundle_sku,
                    'bundle_name'    => $row->bundle_name,
                    'bundle_created' => $row->bundle_created,
                    'bundle_preset' => $row->bundle_preset,
                    'items'          => []
                ];
            }

            // Add bundle item (if exists)
            if (!empty($row->item_id)) {
                $bundles[$sku]->items[] = (object)[
                    'item_id'      => $row->item_id,
                    'product_sku'  => $row->product_sku,
                    'product_name' => $row->product_name,
                    'quantity'     => $row->quantity,
                    'price'        => $row->price
                ];
            }
        }
    }

    $result->free();

    return $bundles; // associative array indexed by bundle_sku
}


function printPendingOrders($conn) {
    $orders = getPendingOrders($conn);

    if (empty($orders)) {
        echo "No pending orders found.\n";
        return;
    }

    foreach ($orders as $order) {
        echo json_encode($order, JSON_PRETTY_PRINT) . "\n\n";
    }
}

/**
 * Process all non-COMPLETED orders:
 * - read payload JSON from DB
 * - decode to object
 * - call custom_create_order_xml_new($orderData)
 *
 * Returns the number of successfully processed orders.
 */
function processPendingOrders($conn, $config) {
    // === LCB connection ===
    $clientID     = $config['LCB_CLIENTID'];
    $clientSecret = $config['LCB_CLIENTSECRET'];
    // Get rows as an array (ensure your getPendingOrders returns array, not JSON)
    $orders = getPendingOrders($conn);
    if (empty($orders)) {
        echo "No pending orders found.\n";
        return 0;
    }
   
    $bundles = getAllBundles($conn);
    
    $processed = 0;

    foreach ($orders as $row) {
        // Fetch the JSON payload from the row
        $requestData = $row['payload'] ?? '';
        $order_number = $row['order_number'] ?? '';
        $pdfName = $row['PDF_URL'] ?? '';

        if ($requestData === '' || $requestData === null) {
            // no payload — skip safely
            continue;
        }

        // Decode to OBJECT (not assoc array), as requested
        $orderData = json_decode($requestData);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // invalid JSON — skip (you can log json_last_error_msg() if needed)
            // error_log("Invalid JSON for row id {$row['id']}: " . json_last_error_msg());
            continue;
        }
        
        updateOrderStatus($conn, $order_number, 'PROCESSING');

        // Call your external function
        if (function_exists('custom_create_order_xml_new')) {
            
            //0) Prepare XML format based on JSON
            $xml_string = custom_create_order_xml_new($orderData, $config, $bundles, $pdfName);
            
            // 1) Get token
            $tok = getAccessToken($clientID, $clientSecret);
            
            if (!$tok['ok']) {
                // Log and decide whether to retry later
                error_log("Token error [HTTP {$tok['http_code']}]: {$tok['error']}");
                // e.g., mark DB row FAILED, increment retry_count, email on 3rd fail, etc.
                updateOrderStatus($conn, $order_number, 'FAILED');
                return;
            }
            
            $accessToken = $tok['token'];
            echo 'Test: ' . $accessToken;
            
            $res = postOrder($xml_string, $accessToken);

            if ($res['ok']) {
                // Success — parse $res['body'] if needed
                updateOrderStatus_API($conn, $order_number, 'CREATED', $res['http_code'], $res['body']);
                
                echo "✅ Order created successfully<br>";
                echo "HTTP Code: " . ($res['http_code'] ?? 'N/A') . "<br>";
                echo "Response Body:<br><pre>" . htmlspecialchars($res['body'] ?? '') . "</pre>";
                echo 'Response from Order ' . $res; 
            } else {
                // Inspect $res['http_code'] to decide retry/backoff
                error_log("Order error [HTTP {$res['http_code']}]: {$res['error']} | Body: " . substr((string)$res['body'], 0, 2000));
                // For 5xx/timeouts: retry with backoff. For 4xx: fix data/auth first.
                //updateOrderStatus($conn, $order_number, 'FAILED');
                updateOrderStatus_API($conn, $order_number, 'FAILED', $res['http_code'], $res['body']);
            }
            
            //updateOrderStatus($conn, $order_number, "CREATED");
            //echo $order_number;
            //echo $xml_string;

            $processed++;
        } 
        //else {
            // Function missing; avoid fatal errors
            // error_log("custom_create_order_xml_new function is not defined");
        //}

        // (Optional) If you want to mark as PROCESSING/COMPLETED here:
        // updateOrderStatus($conn, (int)($row['order_number'] ?? 0), 'PROCESSING');
        // ... do work ...
        // updateOrderStatus($conn, (int)($row['order_number'] ?? 0), 'COMPLETED');
    }

    return $processed;
}


function custom_create_order_xml_new($orderData, $config, $bundles, $pdfName) {

    $CUSTOMER_CODE   =  $config['LCB_CUSTOMER_CODE'];                      // your code
    $SITE_CODE       =  $config['LCB_SITE_CODE'];                          // your site
    $DOCREF_PREFIX   =  $config['LCB_ORDER_PREFIX'];                       // how you build DocRef, e.g. HC-<order_number>
    $PDF_URL_PATH      =  $config['PDF_URL'];                              // PATH TO SHARE PDF
    
    // Get the order object
    $order = $orderData->order_number;
    $shipping_address = $orderData->shipping_address;
    $billing_address = $orderData->billing_address;
    $FKUnderBondType = "P";
    
    
    if (!empty($orderData->billing_address->province) && stripos($orderData->billing_address->province, 'Northern Ireland') !== false) {
        // Billing province is set and contains "Northern Ireland"
        $FKUnderBondType = "N";
    } 
    elseif ( stripos($orderData->shipping_address->province ?? '', 'Northern Ireland') !== false) {
    // Billing did not match, but shipping province contains "Northern Ireland"
        $FKUnderBondType = "N";
    }
    
    // Create a SimpleXML object
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ORDERS></ORDERS>');
    
    $header = $xml->addChild('SO');
    
    //HD ELEMENT
    $secondParentHeader = $header->addChild('HD');
   
    $secondParentHeader->addChild('FKCustomer', $CUSTOMER_CODE);
    $secondParentHeader->addChild('FKUnderBondType', $FKUnderBondType);
    
    $secondParentHeader->addChild('DocumentRef', $DOCREF_PREFIX . $orderData->order_number);
     
    $DPAccountRef = $shipping_address->zip ?? $billing_address->zip ?? 'NA';
    $secondParentHeader->addChild('DPAccountRef', $DPAccountRef);
    
    //$secondParentHeader->addChild('OrderDate', $orderData->created_at->format('d/m/Y')); //FIX
    $secondParentHeader->addChild('OrderDate', date('d/m/Y')); //FIX
   
    // Get the shipping address
    $countryCheck = "";
    // Check if the shipping address is not empty
    if (!empty($shipping_address)) {
        
        $countryCheck = $shipping_address->country;
    
        // Shipping address is not empty
        // Output shipping address or perform further actions
        $firstName = $shipping_address->first_name;
        $surname = $shipping_address->last_name;
        
        $secondParentHeader->addChild('DeliveryName', $firstName . ' ' . $surname );
        
        $secondParentHeader->addChild('DeliveryAddress1', $shipping_address->address1 );
        $secondParentHeader->addChild('DeliveryAddress2', '');
        $secondParentHeader->addChild('DeliveryAddress3', '');
        
        
        $secondParentHeader->addChild('DeliveryTownOrCounty', $shipping_address->city);
        $secondParentHeader->addChild('DeliveryPostCode', $shipping_address->zip);
        $secondParentHeader->addChild('DeliveryCountry', 'GB');
        
        $secondParentHeader->addChild('ContactName', $firstName . ' ' . $surname );
        $phone = !empty($shipping_address->phone) ? preg_replace('/\s+/', '', $shipping_address->phone) : null;
        $secondParentHeader->addChild('ContactTelephoneNo', $phone);
        
    }  else {
         
        $countryCheck = $billing_address->country;
    
        // Shipping address is empty and we get billing address
       //Order Delivery Details
        $firstName = $billing_address->first_name;
        $surname = $billing_address->last_name;
        $secondParentHeader->addChild('DeliveryName', $firstName . ' ' . $surname );
        
        $secondParentHeader->addChild('DeliveryAddress1', $billing_address->address1);
        $secondParentHeader->addChild('DeliveryAddress2', '');
        $secondParentHeader->addChild('DeliveryAddress3', '');
        
        
        $secondParentHeader->addChild('DeliveryTownOrCounty', $billing_address->city);
        $secondParentHeader->addChild('DeliveryPostCode', $billing_address->zip);
        $secondParentHeader->addChild('DeliveryCountry', 'GB');
        
        $secondParentHeader->addChild('ContactName', $firstName . ' ' . $surname );
        $phone = !empty($billing_address->phone) ? preg_replace('/\s+/', '', $billing_address->phone) : null;
        $secondParentHeader->addChild('ContactTelephoneNo', preg_replace('/\s+/', '', $billing_address->phone));
   }
   
   /*
   $countryCheck = "United Kingdom";
    if (strcasecmp(trim($countryCheck), 'United Kingdom') !== 0) {
        return; // Exit function if not United Kingdom (case-insensitive)
    }
    */
  
  
    //HO ELEMENT
    $hoElement = $header->addChild('HO');
    
    $hoElement->addChild('FKSalesOrderType', '9');
    $hoElement->addChild('SiteCode', $SITE_CODE);
     
    $hoElement->addChild('DeliveryInstructions1', '');
    $hoElement->addChild('PickInstructions', '');
     
   
    //Create Collection Date
    //$inputDate = new DateTime('now', new DateTimeZone('Europe/London'));
    //$inputDate = $inputDate->format('Y-m-d H:i:s');
    //$collectionDate = createCollectDate($inputDate, '14:00:00');

    $collectionDate = isBetweenThursdayAndFriday($orderData->created_at);
    
    $hoElement->addChild('BookedOrCollectionDate', $collectionDate);
    //Next day delivery
    $hoElement->addChild('CollectionDeliveryArea', 'PC113');
    $hoElement->addChild('CollectionBy', '');
    $hoElement->addChild('VehicleRegistration', '');
    $hoElement->addChild('CollectionTime', '');
    
    $hoElement->addChild('DeliveryIssueDPCustEmailAddress', $orderData->customer->email);
    $hoElement->addChild('AWRS', 'PRIVATE');
    $hoElement->addChild('ParcelCarrierAccountNo', '3028153');
    
    if (empty($collectionDate)) {
        $hoElement->addChild('ParcelCarrierServiceCode', '');
    }
    else {
        $hoElement->addChild('ParcelCarrierServiceCode', '71'); //Saturday Delivery
    }

    $hoElement->addChild('SpecialMessageText1', '');
     
    //HU ELEMENT
    $huElement = $header->addChild('HU');
    
    $huElement->addChild('ExciseDefermentNumber', $CUSTOMER_CODE);
    $huElement->addChild('CustomsDefermentNumber', $CUSTOMER_CODE);
    $huElement->addChild('PayCustomsDuty', 'Y');
   
   /*
    $firstName = $billing_address->first_name;
    $surname = $billing_address->last_name;
    $huElement->addChild('ConsigneeName', $firstName . ' ' . $surname );
        
    $huElement->addChild('ConsigneeAddress1', $billing_address->address1);
    $huElement->addChild('ConsigneeAddress2', '');
    $huElement->addChild('ConsigneeAddress3', '');
        
        
    $huElement->addChild('ConsigneeAddress4', $billing_address->city);
    $huElement->addChild('ConsigneePostcode', $billing_address->zip);
    $huElement->addChild('ConsigneeCountry', 'GB');
    */
    
    if (!empty($billing_address)) {
        $firstName = $billing_address->first_name;
        $surname = $billing_address->last_name;
        $huElement->addChild('ConsigneeName', $firstName . ' ' . $surname );
            
        $huElement->addChild('ConsigneeAddress1', $billing_address->address1);
        $huElement->addChild('ConsigneeAddress2', '');
        $huElement->addChild('ConsigneeAddress3', '');
            
            
        $huElement->addChild('ConsigneeAddress4', $billing_address->city);
        $huElement->addChild('ConsigneePostcode', $billing_address->zip);
        $huElement->addChild('ConsigneeCountry', 'GB');
    }
    else if(!empty($shipping_address))
    {
        $firstName = $shipping_address->first_name;
        $surname = $shipping_address->last_name;
        $huElement->addChild('ConsigneeName', $firstName . ' ' . $surname );
            
        $huElement->addChild('ConsigneeAddress1', $shipping_address->address1);
        $huElement->addChild('ConsigneeAddress2', '');
        $huElement->addChild('ConsigneeAddress3', '');
            
            
        $huElement->addChild('ConsigneeAddress4', $shipping_address->city);
        $huElement->addChild('ConsigneePostcode', $shipping_address->zip);
        $huElement->addChild('ConsigneeCountry', 'GB');
    }
    
    //URL
    $pdfPath = $PDF_URL_PATH . $pdfName;
    $doElement = $header->addChild('DO');
    $doElement->addChild('DAD1', $pdfPath);
    
    //Line Items
    //$items = $order->get_items();
    $lineItems =  $orderData->line_items;
    $line_number = 1;
    
    //TEMP FIX FOR BUNDLES
    //$bundlesCases = $bundles;
    //$bundles = null;
         
    foreach ($lineItems as $item) {
        
         $sku = $item->sku;
         
        if ($sku === 'WSAM150' || $sku === 'WSMM15') {
            continue; // Skip this iteration
        }
        
        $bundleRow = $bundles[$sku] ?? null;
        
        //This is not LCB --> It should translate to singles
        if ($bundleRow !== null && !$bundleRow->bundle_preset) {
        //if (isset($bundles[$sku])) {
            // ✅ SKU is a bundle
            $bundle = $bundleRow;
            //echo "<h3>Bundle: {$bundle->bundle_name} ({$bundle->bundle_sku})</h3>";
    
            foreach ($bundle->items as $bundleItem) {
                //echo "- {$bundleItem->product_name} ({$bundleItem->product_sku}) × {$bundleItem->quantity}<br>";
                $lineItemElement = $header->addChild('LD');
        
                // Get individual item details
                $sub_total = $bundleItem->price;
                $formatted_subtotal = number_format($sub_total, 2);
                
                if ($formatted_subtotal <= 0) {
                    $formatted_subtotal = null;
                }
                
                //$lineItemElement->addChild('FKProduct', $item->get_product_id());
                $lineItemElement->addChild('FKProduct', $bundleItem->product_sku);
                $lineItemElement->addChild('SinglesOrdered', $bundleItem->quantity * $item->quantity);
                $lineItemElement->addChild('SinglePrice',$formatted_subtotal);
                //$lineItemElement->addChild('SinglePrice','0.00'); //Add items with zero price as requested
                $lineItemElement->addChild('PODLineNumber', $line_number);
                $line_number++;
            }
        } else {
             $lineItemElement = $header->addChild('LD');
        
            // Get individual item details
            $sub_total = $item->price;
            $formatted_subtotal = number_format($sub_total, 2);;
            
            //$lineItemElement->addChild('FKProduct', $item->get_product_id());
            $lineItemElement->addChild('FKProduct', $item->sku);
            
            if (isset($bundles[$sku])){
                $lineItemElement->addChild('UnitsOrdered', $item->quantity);
            }
            else {
                $lineItemElement->addChild('SinglesOrdered', $item->quantity);
            }
            
            $lineItemElement->addChild('SinglePrice',$formatted_subtotal);
            //$lineItemElement->addChild('SinglePrice','0.00'); //Add items with zero price as requested
            $lineItemElement->addChild('PODLineNumber', $line_number);
            $line_number++;
        }
       
        //$line_number++;
        
    }
    
     // Convert SimpleXML object to a formatted XML string
    $xml_string = $xml->asXML();
    
    //SO20240101_765.SIPPAW
    //$fileName = __DIR__ . '/Orders/'. $order;
    //$fileName = 'Orders/'. $order;
    // Save the XML to a file
    //$file_path = get_template_directory() . '/OrdersXML' . 'order_' . $order_id . '.xml';
   //$file_path = $fileName . '.xml';
   
   //if (!file_exists($file_path)) {
        //postOrder($xml_string);
    //}
    
    //echo $file_path;
    //file_put_contents($file_path, $xml_string);
    return $xml_string;
    // You can also send the XML via email, API, or perform any other custom actions here
   
}


function checkDateForPublicHoliday($orderDateTimeStore, $year, $country) {
    $countryCode = $country;

    // Get the current date
    $currentDate = DateTime::createFromFormat('Y-m-d H:i:s', $orderDateTimeStore)->format('Y-m-d');
    
    //echo 'Holiday date: ' . $currentDate . "\n";
    
    // API endpoint for UK public holidays
    $apiEndpoint = "https://date.nager.at/api/v3/publicholidays/$year/$country";
    
    // Fetch data from the API
    $response = file_get_contents($apiEndpoint);
    
    // Check if the request was successful
    if ($response !== false) {
        // Decode JSON response
        $holidays = json_decode($response, true);
    
        // Check if the current date is a public holiday
        $isPublicHoliday = "false";
        
        foreach ($holidays as $holiday) {
            //echo 'Holiday date: ' . $holiday['date'] . "\n";
            if ($holiday['date'] === $currentDate) {
                $isPublicHoliday = "true";
                break;
            }
        }
    
        // Output the result
        
        if ($isPublicHoliday === "true") {
            //echo "Today is a public holiday in the UK.\n";
        } else {
            //echo "Today is not a public holiday in the UK.\n";
        }
        
    } else {
        echo "Error fetching data from the API.\n";
    }
    
    return $isPublicHoliday;
}

function createCollectDate($orderDateTimeStore, $cutOffTime) {
    
    $dateString = $orderDateTimeStore;
    
    $targetTimeOrder = DateTime::createFromFormat('H:i:s', $cutOffTime);
    $targetTimeOrder = $targetTimeOrder->format('H:i:s');
    
    $orderDate = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
    $collectionDate = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
    $orderTime = $orderDate->format('H:i:s');

    //Create Time
    $timestamp = strtotime($dateString);
    $weekDay = date('l', $timestamp);
    
    echo 'Order Date: ' . $orderDate->format('d/m/Y H:i:s') . "\n";
    //echo 'Current Date: ' . $dateString . "\n";
    //echo 'Next Monday: ' . $nextMonday->format('Y-m-d') . "\n";
    //echo 'Order Time: ' . $orderTime . "\n";
    //echo 'Target Cut-off Time: ' . $targetTimeOrder . "\n";
    //echo 'Order Week Day: ' . date('l', $timestamp) . "\n";
    
    // Compare the current time with the target time and set collection date
    $collectionDateFound = false;
    
    while (!$collectionDateFound) {
        
        $orderTime = $collectionDate->format('H:i:s');

        //Create Time
        $timestamp = strtotime($collectionDate->format('Y-m-d H:i:s'));
        $weekDay = date('l', $timestamp);
    
        if ( $orderTime < $targetTimeOrder ) {
            
            if ( $weekDay === "Saturday" OR $weekDay === "Sunday")
            {
                $currentDayOfWeek = $collectionDate->format('N');
                $daysToAdd = $currentDayOfWeek <= 1 ? 1 : (8 - $currentDayOfWeek);
                $collectionDate = $collectionDate->modify("+$daysToAdd days");
                //echo 'Collection Date: ' . $collectionDate->format('d/m/Y') . "\n";
            }
            else
            {
                //$collectionDate = $currentDate->modify('+1 day');
                $collectionDate = $collectionDate;
                //echo 'Collection Date: ' . $collectionDate->format('d/m/Y') . "\n";
            }
        } 
        else {
            if ($weekDay === "Monday" OR $weekDay === "Tuesday" OR $weekDay === "Wednesday" OR $weekDay === "Thursday")
            {
                $collectionDate = $collectionDate->modify('+1 day');
                //echo 'Collection Date: ' . $orderDate->format('d/m/Y') . "\n";
            }
            else
            {
                $currentDayOfWeek = $collectionDate->format('N');
                $daysToAdd = $currentDayOfWeek <= 1 ? 1 : (8 - $currentDayOfWeek);
                $collectionDate = $collectionDate->modify("+$daysToAdd days");
                //echo 'Collection Date: ' . $collectionDate->format('d/m/Y') . "\n";
            }
        }
        
        //Get List of Public holidays
        $currentYearString = date('Y'); //TODO: Get param from collection date. 
        $publicHoliday = checkDateForPublicHoliday($collectionDate->format('Y-m-d H:i:s') , $currentYearString, 'GB');
        //If collection Date is public holiday + 1 day and continue
        if ($publicHoliday === "true"){
            $collectionDate = $collectionDate->modify('+1 day');
            $collectionDate->setTime(12, 00, 0);
            //echo 'Loop Collection Date True: ' . $collectionDate->format('d/m/Y') . "\n";
            $collectionDateFound = false;
        }
        else
        {
            //echo 'Loop Collection Date False: ';
            $collectionDateFound = true;
            continue;
        }
    }
    return $collectionDate->format('d/m/Y');
}

/*
 * Get OAuth access token.
 *
 * @param string $clientIdRaw     Client ID (optionally base64-encoded)
 * @param string $clientSecretRaw Client Secret (optionally base64-encoded)
 * @param string $tokenUrl        Token endpoint
 * @return array {
 *   ok: bool,
 *   http_code: int|null,
 *   token: string|null,
 *   body: string|null,      // raw token response
 *   error: string|null,     // cURL/HTTP/parse error
 * }
 */
function getAccessToken(string $clientIdRaw, string $clientSecretRaw, string $tokenUrl = 'https://kingmoor.lcb.co.uk/token'): array
{
    // If given base64 strings, decode; otherwise they pass through unchanged.

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

    if ($err) {
        return ['ok' => false, 'http_code' => $code, 'token' => null, 'body' => $resp, 'error' => "cURL error: $err"];
    }
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'http_code' => $code, 'token' => null, 'body' => $resp, 'error' => 'Non-2xx token HTTP response'];
    }

    $json = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($json['access_token'])) {
        return ['ok' => false, 'http_code' => $code, 'token' => null, 'body' => $resp, 'error' => 'Token JSON parse error or access_token missing: ' . json_last_error_msg()];
    }
    
    return ['ok' => true, 'http_code' => $code, 'token' => $json['access_token'], 'body' => $resp, 'error' => null];
}

function postOrder(string $xml, string $accessToken, string $apiUrl = 'https://kingmoor.lcb.co.uk/service/Order'): array
{
    $ch = curl_init($apiUrl);
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
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/xml',
            'Accept: application/json, application/xml;q=0.9, */*;q=0.1',
        ],
        CURLOPT_POSTFIELDS     => $xml,
    ];
    curl_setopt_array($ch, $opts);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: null;
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'http_code' => $code, 'body' => $resp, 'error' => "cURL error: $err"];
    }

    $ok = ($code >= 200 && $code < 300);
    return ['ok' => $ok, 'http_code' => $code, 'body' => $resp, 'error' => $ok ? null : 'Non-2xx order HTTP response'];
}

function isBetweenThursdayAndFriday($dateString, $thursdayCutoff = '15:00:00', $fridayCutoff = '14:55:00')
{
    $date = new DateTime($dateString);
    $dayOfWeek = (int)$date->format('N'); // 1 = Monday, ... 7 = Sunday
    $timeInSeconds = strtotime($date->format('H:i:s')) - strtotime('00:00:00');

    // Convert cutoffs to seconds
    $thursdayParts = explode(':', $thursdayCutoff);
    $fridayParts   = explode(':', $fridayCutoff);

    $thursdayStart = $thursdayParts[0] * 3600 + $thursdayParts[1] * 60 + ($thursdayParts[2] ?? 0);
    $fridayEnd     = $fridayParts[0] * 3600 + $fridayParts[1] * 60 + ($fridayParts[2] ?? 0);

    // Check if datetime is within the defined window (Thursday → Friday)
    if (
        ($dayOfWeek === 4 && $timeInSeconds >= $thursdayStart) || // Thursday after cutoff
        ($dayOfWeek === 5 && $timeInSeconds <= $fridayEnd)        // Friday before cutoff
    ) {
        // Return Friday's date of that same week in dd/mm/yyyy format
        $friday = clone $date;
        $friday->modify('friday this week');
        return $friday->format('d/m/Y'); // e.g. 07/11/2025
    }

    // Otherwise, return empty
    return '';
}

?>