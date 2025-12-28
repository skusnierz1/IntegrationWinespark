<?php
// auth_check.php — include on protected pages
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php?msg=Please+log+in');
    exit;
}
?>
