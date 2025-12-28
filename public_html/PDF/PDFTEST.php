<?php
require 'dompdf/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$config = require '/home/u394535132/domains/lightgray-vulture-703201.hostingersite.com/config.php';
// === Database connection ===

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

processPendingOrders_($conn);

// Close connection when done
$conn->close();

/*
// ======= Load and Decode JSON =======
$jsonData = '{"id":11985328570699,"admin_graphql_api_id":"gid:\/\/shopify\/Order\/11985328570699","app_id":580111,"browser_ip":"2a00:23c8:f935:5a01:351b:6849:de79:53e2","buyer_accepts_marketing":false,"cancel_reason":null,"cancelled_at":null,"cart_token":"hWN2K7e126TDdtPmZneALOWh","checkout_id":51543508091211,"checkout_token":"626350fbd52de2217b76a8ce43736b82","client_details":{"accept_language":"en-IE","browser_height":null,"browser_ip":"2a00:23c8:f935:5a01:351b:6849:de79:53e2","browser_width":null,"session_hash":null,"user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit\/605.1.15 (KHTML, like Gecko) Version\/26.0 Safari\/605.1.15"},"closed_at":null,"company":null,"confirmation_number":"AMWN50N5H","confirmed":true,"contact_email":"adam.brown@winespark.com","created_at":"2025-10-28T15:17:49+00:00","currency":"EUR","current_shipping_price_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"current_subtotal_price":"162.04","current_subtotal_price_set":{"shop_money":{"amount":"162.04","currency_code":"EUR"},"presentment_money":{"amount":"142.32","currency_code":"GBP"}},"current_total_additional_fees_set":null,"current_total_discounts":"0.00","current_total_discounts_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"current_total_duties_set":null,"current_total_price":"162.04","current_total_price_set":{"shop_money":{"amount":"162.04","currency_code":"EUR"},"presentment_money":{"amount":"142.32","currency_code":"GBP"}},"current_total_tax":"0.00","current_total_tax_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"customer_locale":"en-IE","device_id":null,"discount_codes":[],"duties_included":false,"email":"adam.brown@winespark.com","estimated_taxes":false,"financial_status":"paid","fulfillment_status":null,"landing_site":"\/account\/login?return_url=\/account","landing_site_ref":null,"location_id":null,"merchant_business_entity_id":"MTUxMjA2NDU1NDgz","merchant_of_record_app_id":null,"name":"#29448","note":"To Adam,\n\nThanks for everything,\n\nEamon","note_attributes":[{"name":"_barId","value":"49d36127-1f1a-4e54-8130-d1511863588c"},{"name":"_source","value":"Rebuy"},{"name":"_attribution","value":"Smart Cart 2.0"},{"name":"Terms and Conditions","value":"Accepted"}],"number":28448,"order_number":29448,"order_status_url":"https:\/\/winespark.com\/51206455483\/orders\/d775f8261c0b944ec2e934e2905b05d0\/authenticate?key=b4ef6e177ea896c31df398e2540263c6","original_total_additional_fees_set":null,"original_total_duties_set":null,"payment_gateway_names":["gift_card"],"phone":"+447504415315","po_number":null,"presentment_currency":"GBP","processed_at":"2025-10-28T15:17:46+00:00","reference":null,"referring_site":null,"source_identifier":null,"source_name":"web","source_url":null,"subtotal_price":"162.04","subtotal_price_set":{"shop_money":{"amount":"162.04","currency_code":"EUR"},"presentment_money":{"amount":"142.32","currency_code":"GBP"}},"tags":"","tax_exempt":false,"tax_lines":[],"taxes_included":true,"test":false,"token":"d775f8261c0b944ec2e934e2905b05d0","total_cash_rounding_payment_adjustment_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"total_cash_rounding_refund_adjustment_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"total_discounts":"0.00","total_discounts_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"total_line_items_price":"162.04","total_line_items_price_set":{"shop_money":{"amount":"162.04","currency_code":"EUR"},"presentment_money":{"amount":"142.32","currency_code":"GBP"}},"total_outstanding":"0.00","total_price":"162.04","total_price_set":{"shop_money":{"amount":"162.04","currency_code":"EUR"},"presentment_money":{"amount":"142.32","currency_code":"GBP"}},"total_shipping_price_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"total_tax":"0.00","total_tax_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"total_tip_received":"0.00","total_weight":8400,"updated_at":"2025-10-28T15:17:50+00:00","user_id":null,"billing_address":{"first_name":"Adam","address1":"2 REVEL BURROUGHS WAY","phone":"07504 415315","city":"NORWICH","zip":"NR14 7UX","province":"England","country":"United Kingdom","last_name":"Brown","address2":null,"company":null,"latitude":52.572429,"longitude":1.1233518,"name":"Adam Brown","country_code":"GB","province_code":"ENG"},"customer":{"id":10592223560011,"created_at":"2025-08-28T14:49:54+01:00","updated_at":"2025-10-28T15:17:50+00:00","first_name":"Adam","last_name":"Brown","state":"enabled","note":"","verified_email":true,"multipass_identifier":null,"tax_exempt":false,"email":"adam.brown@winespark.com","phone":"+447504415315","currency":"GBP","tax_exemptions":[],"admin_graphql_api_id":"gid:\/\/shopify\/Customer\/10592223560011","default_address":{"id":35649653014859,"customer_id":10592223560011,"first_name":"Adam","last_name":"Brown","company":null,"address1":"2 REVEL BURROUGHS WAY","address2":null,"city":"NORWICH","province":"England","country":"United Kingdom","zip":"NR14 7UX","phone":"07504 415315","name":"Adam Brown","province_code":"ENG","country_code":"GB","country_name":"United Kingdom","default":true}},"discount_applications":[],"fulfillments":[],"line_items":[{"id":35405307838795,"admin_graphql_api_id":"gid:\/\/shopify\/LineItem\/35405307838795","current_quantity":1,"fulfillable_quantity":1,"fulfillment_service":"manual","fulfillment_status":null,"gift_card":false,"grams":8400,"name":"This Is WineSpark 6-pack","price":"162.04","price_set":{"shop_money":{"amount":"162.04","currency_code":"EUR"},"presentment_money":{"amount":"142.32","currency_code":"GBP"}},"product_exists":true,"product_id":14887237878091,"properties":[],"quantity":1,"requires_shipping":true,"sales_line_item_group_id":null,"sku":"WS6P25","taxable":true,"title":"This Is WineSpark 6-pack","total_discount":"0.00","total_discount_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"variant_id":53689413796171,"variant_inventory_management":"shopify","variant_title":null,"vendor":"WineSpark","tax_lines":[],"duties":[],"discount_allocations":[]}],"payment_terms":null,"refunds":[],"shipping_address":{"first_name":"Adam","address1":"2 REVEL BURROUGHS WAY","phone":"07504 415315","city":"NORWICH","zip":"NR14 7UX","province":"England","country":"United Kingdom","last_name":"Brown","address2":null,"company":null,"latitude":52.572429,"longitude":1.1233518,"name":"Adam Brown","country_code":"GB","province_code":"ENG"},"shipping_lines":[{"id":11081361981771,"carrier_identifier":null,"code":"UK Delivery","current_discounted_price_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"discounted_price":"0.00","discounted_price_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"is_removed":false,"phone":null,"price":"0.00","price_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"GBP"}},"requested_fulfillment_service_id":null,"source":"shopify","title":"UK Delivery","tax_lines":[],"discount_allocations":[]}],"returns":[],"line_item_groups":[]}
';
$order = json_decode($jsonData, true);

$bundles = getAllBundles_($conn);

createPDF($order, $bundles);
*/

function createPDF($order, $bundles) {
    // ======= Extract Order Data =======
    $orderNumber = $order['name'] ?? $order['order_number'] ?? '';
    $orderDate   = date('d M Y', strtotime($order['created_at'] ?? ''));
    $ship        = $order['shipping_address'] ?? [];
    $bill        = $order['billing_address'] ?? [];
    $items       = $order['line_items'] ?? [];
    $giftNotes   = $order['note'] ?? '';
    $defaultBundle = 'defaultBundle';
    $defaultBottle = 'defaultBottle';

    
    
    // ======= Build HTML =======
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Order ' . htmlspecialchars($orderNumber) . '</title>
    <style>
      @page { margin: 40px; size: A4 portrait; }
      body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
      .header { width: 100%; margin-bottom: 20px; }
      .header .left { float: left; width: 60%; }
      .header .right { float: right; width: 35%; text-align: right; }
      .header .center { float: center; width: 35%; text-align: center; }
      
      .clear { clear: both; }
      .section-title { font-weight: bold; margin-top: 10px; margin-bottom: 5px; }
      .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
      .table th, .table td { border-bottom: 1px solid #ddd; padding: 6px 4px; vertical-align: middle; }
      .table th { background: #f5f5f5; text-align: left; }
      .product-img { width: 40px; height: auto; }
      .gift { margin-top: 20px; font-size: 15px; text-align: center; color: #666; }
        
     footer {
          position: fixed;
          bottom: 0;
          left: 0;
          right: 0;
          margin-top: 20px;
          font-size: 15px;
          text-align: center;
          color: #666;
        }
        
        
    </style>
    </head>
    <body>
    
    
    <!-- HEADER -->
    <div class="header">
        <div class="logo" align="center"">
          <img src="https://lightgray-vulture-703201.hostingersite.com/logo.png" alt="Logo">
        </div>
     </div>
    
      <div class="clear"></div>
    </div>
    
    <div>
      <table class="table">
        <tr>
        
        <td width="40%" align="left">
            <strong>ORDER DETAILS</strong><br>'
            . htmlspecialchars('Order Number: ') . htmlspecialchars($orderNumber) . '<br>'
            . htmlspecialchars('Order Date: ') . htmlspecialchars($orderDate) . '<br><span style="color: white;">'
             . htmlspecialchars('Order Number: ') . htmlspecialchars($orderNumber) . '<br>'
            . htmlspecialchars('Order Date: ') . htmlspecialchars($orderDate) . '</span><br>
          </td>
    
    
        
    
          <td width="40%" align="left">
            <strong>BILL TO</strong><br>'
            . htmlspecialchars($bill['first_name'] ?? '') . ' ' . htmlspecialchars($ship['last_name'] ?? '') . '<br>'
            . htmlspecialchars($bill['address1'] ?? '') . '<br>' 
            . htmlspecialchars($ship['city'] ?? '') . '<br>' 
            . htmlspecialchars($bill['zip'] ?? '') . '<br>' 
            . htmlspecialchars($bill['country'] ?? '') . '
          </td>
          
        <td width="20%" align="left">
            <strong>SHIP TO</strong><br>'
            . htmlspecialchars($ship['first_name'] ?? '') . ' ' . htmlspecialchars($ship['last_name'] ?? '') . '<br>'
            . htmlspecialchars($ship['address1'] ?? '') . '<br>' 
            . htmlspecialchars($ship['city'] ?? '') . '<br>' 
            . htmlspecialchars($ship['zip'] ?? '') . '<br>' 
            . htmlspecialchars($ship['country'] ?? '') . '
            
          </td>
          
         
        </tr>
      </table>
    </div>
    
    <br>
    
    
    
    
    <!-- PRODUCT TABLE -->
    <table class="table">
      <thead>
        <tr>
          <th style="width: 10%;"><center>ITEMS</center></th>
          <th style="width: 10%;"></th>
          <th style="width: 60%;"></th>
          <th style="width: 20%;"></center>QUANTITY</center></th>
        </tr>
      </thead>
      <tbody>';
    
    // ======= Loop Through Line Items =======
    foreach ($items as $p) {
        
        $sku = $p['sku'];
        
        if ($sku === 'WSAM150' || $sku === 'WSMM15') {
            continue; // Skip this iteration
        }
        
        $image = null;
        
        if (isset($bundles[$sku])) {
            // ✅ SKU is a bundle
            $bundle = $bundles[$sku];
            //echo "<h3>Bundle: {$bundle->bundle_name} ({$bundle->bundle_sku})</h3>";
            if (file_exists(__DIR__ . '/Images/' . $bundle->bundle_sku. ".png")) {
                $image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $bundle->bundle_sku . ".png"; 
            } else {
                $image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $defaultBundle. ".png"; 
            }
            
            //$image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $bundle->bundle_sku. ".png"; 
            $html .= '
                <tr>
                  <td><center><img src="' . $image . '" class="product-img" alt="" style="transform: scale(1.2);"></center></td>
                  
                  <td colspan="2" style="font-size: 120%;"><strong>' . htmlspecialchars($bundle->bundle_name) . '</strong><br>' 
                        . htmlspecialchars($bundle->bundle_sku ?? '') . '<br></td>
             
                  <td>BUNDLE</td>
                </tr>';
            
            foreach ($bundle->items as $bundleItem) {
                echo "- {$bundleItem->product_name} ({$bundleItem->product_sku}) × {$bundleItem->quantity}<br>";
                
                if (file_exists(__DIR__ . '/Images/' . $bundleItem->product_sku . ".png")) {
                    $image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $bundleItem->product_sku . ".png"; 
                } else {
                    $image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $defaultBottle. ".png"; 
                }
                
                
                $html .= '
                <tr>
                  <td></td> 
                  <td><center><img src="' . $image . '" class="product-img" alt=""></center></td>
                  <td> <strong>' . htmlspecialchars($bundleItem->product_name) . '</strong><br>' 
                        . htmlspecialchars($bundleItem->product_sku ?? '') . '</td>
                  
                  <td>' . htmlspecialchars($bundleItem->quantity) . ' of ' . htmlspecialchars($bundleItem->quantity) . '</td>
                </tr>';
            }
        } else {
            
            echo 'here';
            if (file_exists(__DIR__ . '/Images/' . $p['sku'] . ".png")) {
                $image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $p['sku'] . ".png"; 
            } else {
                $image =  'https://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $defaultBottle . ".png"; 
            }
            
            
            $html .= '
            <tr>
              <td><center><img src="' . $image . '" class="product-img" alt=""></center></td>
              <td colspan="2"><strong>' . htmlspecialchars($p['title']) . '</strong><br>' .
                  htmlspecialchars($p['sku'] ?? '') . '</td>
              <td>' . htmlspecialchars($p['quantity']) . ' of ' . htmlspecialchars($p['quantity']) . '</td>
            </tr>';
        }
         
        //$image = 'https://www.citypng.com/public/uploads/preview/hd-realistic-red-glass-wine-bottle-png-704081694865763wkub0y4i3g.png'; // Replace if product image URL available
    }
    
    $html .= '
      </tbody>
    </table>
    
    <p class="gift">
    <strong>' . htmlspecialchars($giftNotes) . '</strong><br><br>
    </p>
    
    <footer>
      
      <strong>Thank you for supporting WineSpark!</strong>
    </footer>
    
    </body>
    </html>';
    
    
    
    echo $html;
    
    // ======= Generate PDF =======
    
    $options = new Options();
    $options->set('isRemoteEnabled', true); // ✅ allow remote images (HTTP/HTTPS)
    $options->set('isHtml5ParserEnabled', true); 
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // ======= Save PDF to /OrderConfirmations/ =======
    $savePath = __DIR__ . "/Test/";
    if (!file_exists($savePath)) {
        mkdir($savePath, 0777, true);
    }
    
    $fileName = "Order_" . preg_replace('/[^A-Za-z0-9_\-]/', '', $orderNumber) . ".pdf";
    file_put_contents($savePath . $fileName, $dompdf->output());
    
    //echo "✅ PDF saved successfully to: " . $savePath . $fileName;
    return $fileName;
    
}

function getAllBundles_($conn) {
    $sql = "
        SELECT 
            b.id               AS bundle_id,
            b.sku              AS bundle_sku,
            b.name             AS bundle_name,
            b.created_at       AS bundle_created,
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

function getPendingOrders_($conn) {
    //$sql = "SELECT DISTINCT * FROM shopify_orders WHERE status = 'NEW'";
    $sql = "SELECT DISTINCT * FROM shopify_orders WHERE order_number = 30130";
    
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

function updateOrderStatus_($conn, $orderNumber, $status) {
    $stmt = $conn->prepare("UPDATE shopify_orders SET status = ? WHERE order_number = ?");
    $stmt->bind_param("si", $status, $orderNumber); // status = string, order_number = int
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function updateOrderStatusPDF($conn, $orderNumber, $status, $pdfUrl) {
    $stmt = $conn->prepare("UPDATE shopify_orders SET status = ?, PDF_URL = ? WHERE order_number = ?");
    $stmt->bind_param("ssi", $status, $pdfUrl, $orderNumber); // status = string, PDF_URL = string, order_number = int
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function processPendingOrders_($conn) {
    
    // Get rows as an array (ensure your getPendingOrders____ returns array, not JSON)
    $orders = getPendingOrders_($conn);
    if (empty($orders)) {
        echo "No pending orders found.\n";
        return 0;
    }
    
    $bundles = getAllBundles_($conn);
    
    $processed = 0;

    foreach ($orders as $row) {
        // Fetch the JSON payload from the row
        $requestData = $row['payload'] ?? '';
        $order_number = $row['order_number'] ?? '';

        if ($requestData === '' || $requestData === null) {
            // no payload — skip safely
            continue;
        }

        // Decode to OBJECT (not assoc array), as requested
        $orderData = json_decode($requestData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // invalid JSON — skip (you can log json_last_error_msg() if needed)
            // error_log("Invalid JSON for row id {$row['id']}: " . json_last_error_msg());
            continue;
        }

        // Call your external function
        if (function_exists('createPDF')) {
            
            //0) Prepare XML format based on JSON
            $path = createPDF($orderData, $bundles);
            
            /*
            if (!empty($path)) {
                // $path is not empty
                //updateOrderStatusPDF($conn, $order_number, 'PDF', $path);
                //echo "Path exists: " . $path;
            } else {
                // $path is empty
                //updateOrderStatus_($conn, $order_number, 'FAILED_PDF');
                //echo "Path is empty or not set.";
            }
            
            //sendOrderPackEmail($path, $order_number);
            */
        } 
    }
}

function sendEmailWithPDF($to, $subject, $message, $pdfPath, $fromName, $fromEmail, $cc = '', $bcc = '') {
    // Read the PDF file
    if (!file_exists($pdfPath)) {
        return "Error: PDF file not found.";
    }
    $fileContent = file_get_contents($pdfPath);
    $fileContent = chunk_split(base64_encode($fileContent));

    // Create unique boundary
    $separator = md5(time());

    // Email headers
    $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
    if (!empty($cc))  $headers .= "Cc: {$cc}\r\n";
    if (!empty($bcc)) $headers .= "Bcc: {$bcc}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$separator}\"\r\n";

    // Email body
    $body  = "--{$separator}\r\n";
    $body .= "Content-Type: text/html; charset=\"utf-8\"\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= "{$message}\r\n\r\n";

    // Attachment
    $filename = basename($pdfPath);
    $body .= "--{$separator}\r\n";
    $body .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
    $body .= "{$fileContent}\r\n";
    $body .= "--{$separator}--";

    // Send the email
    if (mail($to, $subject, $body, $headers)) {
        return "Email sent successfully to {$to}" . (!empty($cc) ? " (CC: {$cc})" : "") . ".";
    } else {
        return "Error: Failed to send email.";
    }
}

function sendOrderPackEmail($path, $orderNumber) {
    //$toEmail = 'kusnierz.sebastian@gmail.com';
    $toEmail = 'shcustomerservice@lcb.co.uk';
    
    $basePath = __DIR__;  
    $pdfFile = $basePath . '/SummaryDocuments/' . $path;
    $subject = "PDF Packsheet for WineSpark Order WS-{$orderNumber}";
    $message = "<p>Hello, please find the PDF file attached. Do not answer to this email as it was generated automatically..</p>";
    $fromText = 'WineSpark Support Team';
    $fromEmail = 'support@winespark.com';
    $cc = 'adam.brown@winespark.com';
    //$cc = 'kusnierz.sebastian@gmail.com';

    // Call the main function
    return sendEmailWithPDF(
        $toEmail, // To
        $subject,
        $message,
        $pdfFile,
        $fromText,        // From Name
        $fromEmail,     // From Email
        $cc// Optional CC/BCC:
        // ,'manager@example.com'
        // ,'admin@example.com'
    );
}

