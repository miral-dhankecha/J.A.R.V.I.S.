<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../includes/functions.php";

$city = isset($_GET['city']) ? $_GET['city'] : "Rajkot";
$weather = get_weather($city);

if ($weather) {
    echo json_encode([
        "ok" => true,
        "desc" => $weather['weather'][0]['description'],
        "temp" => round($weather['main']['temp'], 2)
    ]);
} else {
    echo json_encode(["ok" => false]);
}
?>
