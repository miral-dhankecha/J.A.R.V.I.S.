<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["ok" => false, "rows" => []]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Optional ?date=YYYY-MM-DD filter (used by the search box in the History
// window). When a valid date is given we fetch ALL rows for that date
// (no LIMIT), so nothing from that day gets cut off. Without a date we
// keep the original default behaviour: latest 100 rows, newest first.
$dateFilter = isset($_GET['date']) ? trim($_GET['date']) : "";

if ($dateFilter !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $safeDate = mysqli_real_escape_string($conn, $dateFilter);
    // Only this logged-in user's own history is fetched (privacy requirement)
    $res = mysqli_query($conn, "SELECT command_text, response_text, status, cmd_day, cmd_date, cmd_time
                                 FROM history WHERE user_id='$user_id' AND cmd_date='$safeDate' ORDER BY id DESC");
} else {
    // Only this logged-in user's own history is fetched (privacy requirement)
    $res = mysqli_query($conn, "SELECT command_text, response_text, status, cmd_day, cmd_date, cmd_time
                                 FROM history WHERE user_id='$user_id' ORDER BY id DESC LIMIT 100");
}

$rows = [];
while ($row = mysqli_fetch_assoc($res)) {
    $rows[] = $row;
}

echo json_encode(["ok" => true, "user_name" => $_SESSION['user_name'], "rows" => $rows, "filtered_date" => $dateFilter]);
?>
