<?php
// ============================================
// Common helper functions used across the project
// ============================================

// >>> IMPORTANT: without this, PHP's date()/time() default to UTC on
// most servers (Laragon included), so "wake up jarvis" could say
// "Good Afternoon" when it's actually night in India. Fix the server
// timezone once, here, since this file is included almost everywhere.
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Kolkata');
}

// Log any PHP-side error to a custom error log file (in addition to Apache's)
function jarvis_log_error(string $message) {
    $logFile = __DIR__ . "/../error_log.txt";
    $time = date("Y-m-d H:i:s");
    error_log("[$time] $message" . PHP_EOL, 3, $logFile);
}

// Save a command + response into history table
function save_history(mysqli $conn, int $user_id, string $command, string $response, string $status = 'success') {
    $command = mysqli_real_escape_string($conn, $command);
    $response = mysqli_real_escape_string($conn, $response);
    $date = date("Y-m-d");
    $day = date("l");
    $time = date("h:i A");

    $sql = "INSERT INTO history (user_id, command_text, response_text, status, cmd_date, cmd_day, cmd_time)
            VALUES ('$user_id', '$command', '$response', '$status', '$date', '$day', '$time')";

    if (!mysqli_query($conn, $sql)) {
        jarvis_log_error("save_history failed: " . mysqli_error($conn));
    }
}

// Save/update a memory key-value pair for the logged in user
function save_memory(mysqli $conn, int $user_id, string $key, string $value) {
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    $date = date("Y-m-d");
    $day = date("l");
    $time = date("h:i A");

    // check if key already exists for this user -> update, else insert
    $check = mysqli_query($conn, "SELECT id FROM memory WHERE user_id='$user_id' AND mem_key='$key'");
    if (mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_assoc($check);
        mysqli_query($conn, "UPDATE memory SET mem_value='$value', cmd_date='$date', cmd_day='$day', cmd_time='$time' WHERE id='{$row['id']}'");
    } else {
        mysqli_query($conn, "INSERT INTO memory (user_id, mem_key, mem_value, cmd_date, cmd_day, cmd_time)
                              VALUES ('$user_id', '$key', '$value', '$date', '$day', '$time')");
    }
}

// Fetch a memory value for a user (returns null if not found)
function get_memory(mysqli $conn, int $user_id, string $key) {
    $key = mysqli_real_escape_string($conn, $key);
    $res = mysqli_query($conn, "SELECT mem_value FROM memory WHERE user_id='$user_id' AND mem_key='$key'");
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res)['mem_value'];
    }
    return null;
}

// Loosely search this user's stored memory (both key names and free-form
// notes saved via generic "remember ...") for a natural-language query,
// e.g. "am i human" -> matches a note "i am human". Returns the best
// (most recent) row, or null if nothing matches at all.
function search_memory(mysqli $conn, int $user_id, string $query) {
    $stopwords = ['the','and','what','you','your','yes','remember','about',
                  'tell','me','is','my','am','are','do','does','know','who',
                  'jarvis','please'];
    $clean = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $query));
    $words = array_filter(
        preg_split('/\s+/', $clean),
        fn($w) => strlen($w) > 2 && !in_array($w, $stopwords, true)
    );
    if (empty($words)) return null;

    $conds = [];
    foreach ($words as $w) {
        $w = mysqli_real_escape_string($conn, $w);
        $conds[] = "(mem_key LIKE '%$w%' OR mem_value LIKE '%$w%')";
    }
    $sql = "SELECT mem_key, mem_value FROM memory
            WHERE user_id='$user_id' AND (" . implode(' OR ', $conds) . ")
            ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res);
    }
    return null;
}

// Strip common polite/filler phrases so natural language works too,
// e.g. "can you please open chrome" / "jarvis could you open chrome"
// all become "open chrome" before we try to match an intent.
function normalize_command(string $raw): string {
    $text = trim($raw);
    $fillers = [
        '/^hey\s+jarvis[,]?\s*/i',
        '/^jarvis[,]?\s*/i',
        '/^(please|kindly)\s+/i',
        '/^could\s+you\s+(please\s+)?/i',
        '/^can\s+you\s+(please\s+)?/i',
        '/^would\s+you\s+(please\s+)?/i',
        '/^will\s+you\s+(please\s+)?/i',
        '/\s+please\s*[\?\.]?$/i',
    ];
    $prevLen = -1;
    // repeat until nothing more changes (handles "jarvis please open chrome")
    while ($prevLen !== strlen($text)) {
        $prevLen = strlen($text);
        foreach ($fillers as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        $text = trim($text);
    }
    return $text === '' ? trim($raw) : $text;
}

// Get current weather from OpenWeatherMap (default city: Rajkot)
function get_weather(string $city = "Rajkot") {
    $apiKey = "f1aca0280c083ca259e825e6082eb158";
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=$apiKey&units=metric";

    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        jarvis_log_error("Weather API call failed for city: $city");
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['main'])) {
        return null;
    }
    return $data;
}
?>