<?php
/*
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

function sendOrderPackEmail($orderNumber) {
    $pdfFile = "SummaryDocuments/Order_{$orderNumber}.pdf";
    $subject = "PDF Packsheet for WineSpark Order WS-{$orderNumber}";
    $message = "<p>Hello, please find the attached PDF file.</p>";

    // Call the main function
    return sendEmailWithPDF(
        'kusnierz.sebastian@gmail.com', // To
        $subject,
        $message,
        $pdfFile,
        'WineSpark Support Team',        // From Name
        'support@winespark.com'          // From Email
        // Optional CC/BCC:
        // ,'manager@example.com'
        // ,'admin@example.com'
    );
}

*/

// Example usage

$sku = 'defaultBottsle';
$bundle = 'defaultBundle';
$image = null;
if (file_exists('Images/' . $sku . ".png")) {
                $image =  'http://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $sku . ".png"; 
            } else {
                $image =  'http://lightgray-vulture-703201.hostingersite.com/PDF/Images/' . $bundle . ".png"; 
            }
            
            
            $html .= '
            <tr>
              <td><center><img src="' . $image . '" class="product-img" alt=""></center></td>
              <td colspan="2"><strong>' . 'Test ' . '</strong><br>' .
                  htmlspecialchars($sku ?? '') . '</td>
              <td>' . htmlspecialchars($bundle) . ' of ' . htmlspecialchars('1') . '</td>
            </tr>';
            

echo $image;


//$pdfFile = $basePath . "/SummaryDocuments/Order_29835.pdf";
//echo $pdfFile;
/*
echo sendEmailWithPDF(
    //'adam.brown@winespark.com',
    'shcustomerservice@lcb.co.uk',
    'PDF Packsheet for WineSpark Order WS-29778',
    '<p>Hello, please find the attached PDF file.</p>',
    'SummaryDocuments/Order_29778.pdf',
    'WineSpark Support Team',
    'support@winespark.com'
    ,'kusnierz.sebastian@gmail.com'
);
*/
?>
