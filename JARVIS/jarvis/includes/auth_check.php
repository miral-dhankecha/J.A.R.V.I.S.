<?php
// Protects pages - only logged in user can access
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>
