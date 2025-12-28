<?php

$bundle= '6x-domaine-habrard-crozes-hermitage-sylv222ain-2019';
// Assuming $bundle->bundle_sku contains your SKU string
$filePath = 'Images/' . $bundle . '.png';
$defaultBundle = 'Images/defaultBundle.png';
$defaultBottle = 'Images/defaultBottle.png';

// Check if the image exists
if (file_exists($filePath)) {
    echo "<p>Exists: $filePath</p>";
    echo "<img src='$filePath' alt='Bundle Image' style='max-width:300px; height:auto;'>";
} else {
    echo "<p>Using default: $defaultBottle</p>";
    echo "<img src='$defaultBundle' alt='Default Bundle Image' style='max-width:300px; height:auto;'>";
    echo "<img src='$defaultBottle' alt='Default Bundle Image' style='max-width:300px; height:auto;'>";
}
