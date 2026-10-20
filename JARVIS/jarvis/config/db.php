<?php
// ============================================
// Database connection (Laragon default: user=root, pass="")
// ============================================
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "jarvis_new";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    error_log("DB Connection failed: " . mysqli_connect_error());
    die("Database connection failed. Check config/db.php and error_log.");
}
?>