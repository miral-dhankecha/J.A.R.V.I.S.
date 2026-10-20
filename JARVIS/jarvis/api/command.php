<?php
// ============================================
// api/command.php
// Receives a command (typed or from speech), decides what to do,
// calls python/system_control.py for OS-level actions, saves to history,
// and returns a JSON response for the JS front-end to display + speak.
// ============================================
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "fail", "lines" => ["Session expired. Please login again."]]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// >>> venv ka python.exe use ho raha hai (psutil isi venv mein installed hai).
// Path is computed RELATIVE to this project folder (not hardcoded to a
// specific folder name like "jarvis-assistant") so it keeps working no
// matter what you rename/extract the project folder as - e.g.
// "jarvis-assistant-fixed". Tries several fallbacks in order and picks
// the FIRST ONE THAT ACTUALLY RUNS (not just "exists"), since a bare
// "python" can silently resolve to the Windows Store stub (which does
// nothing and returns no output) even when it looks fine on paper.
function resolve_python_path(): string {
    $candidates = [];

    // 1) This project's own venv (preferred - matches requirements.txt)
    $venvPython = __DIR__ . "/../venv/Scripts/python.exe";
    $real = realpath($venvPython);
    if ($real !== false) {
        $candidates[] = '"' . $real . '"';
    }

    // 2) Common launcher / PATH names, in order of preference
    $candidates[] = "py -3";
    $candidates[] = "python";
    $candidates[] = "python3";

    foreach ($candidates as $candidate) {
        $test = @shell_exec($candidate . " --version 2>&1");
        // A real Python prints "Python 3.x.y" here. The Windows Store
        // stub / a missing command prints nothing or an error instead.
        if ($test !== null && stripos($test, "Python") !== false) {
            return $candidate;
        }
    }

    // Nothing worked - fall back to "python" anyway so the error message
    // further down (raw output logged) at least shows WHY it failed,
    // instead of silently returning "python" that we already know is bad.
    return "python";
}
$PYTHON_PATH = resolve_python_path();
$PY_SCRIPT = escapeshellarg(__DIR__ . "/../python/system_control.py");

$rawInput = isset($_POST['command']) ? trim($_POST['command']) : "";

if ($rawInput === "") {
    echo json_encode(["status" => "fail", "lines" => ["Empty command."]]);
    exit();
}

// Keep the ORIGINAL text for the history log, but match intent against a
// cleaned-up version so natural phrasing works too, e.g.
// "can you please open chrome" / "jarvis could you open chrome" -> "open chrome"
$raw = normalize_command($rawInput);
$cmd = strtolower($raw);

$lines = [];
$status = "success";
$extra = []; // extra fields merged into the JSON response (e.g. tab_action)

// Calls python/system_control.py with given args, returns decoded JSON (or null).
// Logs to error_log.txt whenever the call fails to produce valid JSON, OR
// produces valid JSON with ok=false - so a real Python-reported error (a
// path not found, a Windows exception, a refusal) is never silently
// hidden behind a generic "operation fail ho gaya" with no way to tell why.
function call_python(string $pythonPath, string $script, string $args) {
    $cmdStr = $pythonPath . " " . $script . " " . $args . " 2>&1";
    $output = shell_exec($cmdStr);
    $decoded = json_decode($output, true);
    if ($decoded === null) {
        jarvis_log_error(
            "call_python failed to get valid JSON.\n" .
            "  Command run: $cmdStr\n" .
            "  Raw output : " . var_export($output, true)
        );
    } elseif (($decoded['ok'] ?? null) === false) {
        jarvis_log_error(
            "call_python ran fine but reported ok=false.\n" .
            "  Command run: $cmdStr\n" .
            "  Error      : " . ($decoded['error'] ?? '(no error field)')
        );
    }
    return $decoded;
}

// ------------------------------------------------------------------
// Web apps that just open as a browser TAB. Handled fully in the
// browser (assets/js/main.js opens/closes an actual window handle),
// NOT via taskkill - that used to kill the whole Chrome process
// (including the Jarvis dashboard tab itself) when you said
// "close whatsapp". Now only that one tab closes.
// >>> apna Marwadi University portal ka exact URL yaha daalo
// ------------------------------------------------------------------
$WEB_APPS = [
    "gmail" => "https://mail.google.com",
    "whatsapp" => "https://web.whatsapp.com",
    "youtube" => "https://www.youtube.com",
    "drive" => "https://drive.google.com",
    "google drive" => "https://drive.google.com",
    "classroom" => "https://classroom.google.com",
    "google classroom" => "https://classroom.google.com",
    "jiohotstar" => "https://www.hotstar.com",
    "instagram" => "https://www.instagram.com",
    "marwadi university" => "https://portal.marwadiuniversity.ac.in",
    "marwadi" => "https://portal.marwadiuniversity.ac.in",
    "marwadi university portal" => "https://portal.marwadiuniversity.ac.in",
];

// Loose match so "marwadiuniversity portal" still matches "marwadi university"
function fuzzy_match_key(string $target, array $mapping) {
    $target = strtolower(trim(preg_replace('/\s+/', ' ', $target)));
    if (isset($mapping[$target])) return $target;

    $targetNoSpace = str_replace(' ', '', $target);
    // Guard against short targets accidentally substring-matching into an
    // unrelated key (e.g. "cdrive" contains "drive", "a" is inside "gmail").
    if (strlen($targetNoSpace) < 4) return null;
    foreach (array_keys($mapping) as $key) {
        $keyNoSpace = str_replace(' ', '', $key);
        if (strpos($targetNoSpace, $keyNoSpace) !== false || strpos($keyNoSpace, $targetNoSpace) !== false) {
            return $key;
        }
    }
    return null;
}

// ---------- WAKE UP -> greeting sequence ----------
if (strpos($cmd, "wake up jarvis") !== false) {
    $weather = get_weather("Rajkot");
    $weatherLine = "Weather info abhi available nahi hai";
    if ($weather) {
        $desc = $weather['weather'][0]['description'];
        $temp = round($weather['main']['temp'], 2);
        $weatherLine = "Current weather in Rajkot is $desc with temperature $temp degree Celsius ☁️";
    }
    $hour = (int)date("H");
    $greet = "Good Morning";
    if ($hour >= 12 && $hour < 17) $greet = "Good Afternoon";
    elseif ($hour >= 17) $greet = "Good Evening";

    $lines = [
        "Hello $user_name 👋",
        $greet,
        "Today is " . date("l") . " 📅",
        "Current time is " . date("h:i A") . " ⏰",
        $weatherLine,
        "System is now online 🚀",
        "What would you like to do today"
    ];

// ---------- EXIT ----------
} elseif ($cmd === "exit") {
    $weather = get_weather("Rajkot");
    $weatherLine = "Weather info abhi available nahi hai";
    if ($weather) {
        $desc = $weather['weather'][0]['description'];
        $weatherLine = "Current weather in Rajkot is $desc ☁️";
    }
    $lines = [
        "Today is " . date("l") . " 📅",
        "Current date is " . date("d-M-Y"),
        "Current time is " . date("h:i A") . " ⏰",
        $weatherLine,
        "You did a great job today 🌟",
        "Now it is time to take some rest 😴",
        "Goodbye $user_name 👋",
        "Take care of yourself ❤️"
    ];
    $extra['session_end'] = true;

// ---------- WEATHER ----------
} elseif (preg_match('/weather(?:\s+(?:of|in|for))?\s+(.+)/i', $raw, $m) || $cmd === "weather") {
    $city = isset($m[1]) ? trim($m[1]) : "Rajkot";
    $weather = get_weather($city);
    if ($weather) {
        $desc = $weather['weather'][0]['description'];
        $temp = round($weather['main']['temp'], 2);
        $feels = round($weather['main']['feels_like'], 2);
        $humidity = $weather['main']['humidity'];
        $wind = $weather['wind']['speed'];
        $cityFull = $weather['name'] . ", " . $weather['sys']['country'];
        $isNight = (time() < $weather['sys']['sunrise'] || time() > $weather['sys']['sunset']);
        $lines = [
            "Current weather in $cityFull is $desc ☁️",
            "🌡️ Temperature : {$temp}°C",
            "🤒 Feels Like : {$feels}°C",
            "💧 Humidity : {$humidity}%",
            "🌬️ Wind Speed : {$wind} m/s",
            "📅 Day : " . date("l"),
            "🗓️ Date : " . date("d-m-Y"),
            "⏰ Local Time : " . date("h:i:s A"),
            $isNight ? "🕓 Currently it is Night 🌙" : "🕓 Currently it is Day ☀️"
        ];
    } else {
        $lines = ["Sorry $user_name, weather ke liye city nahi mili ya API key set nahi hai."];
        $status = "fail";
    }

// ---------- OPEN / CLOSE a LOCAL DRIVE ("open c drive", "open d drive") ----------
// Checked BEFORE the web-app match below, because "c drive" / "d drive"
// used to fuzzy-match the web app "drive" (Google Drive) since "cdrive"
// contains the substring "drive" - so it wrongly opened Chrome instead of
// This PC / File Explorer. Plain "open drive" (no letter) still means
// Google Drive and falls through to the web-app block further down.
} elseif (preg_match('/^(open|close)\s+([a-z])\s*:?\s*drive$/i', $cmd, $m)) {
    $action = strtolower($m[1]);
    $driveLetter = strtoupper($m[2]);

    if ($action === 'close') {
        // Windows runs ALL File Explorer windows under one shared
        // explorer.exe process (which is also the Desktop/taskbar), so
        // force-closing it to close just one drive window would kill the
        // whole desktop shell. Safer to be honest than to do that.
        $lines = ["Sorry $user_name, main sirf $driveLetter drive ki window band nahi kar sakta bina puri File Explorer/taskbar band kiye. Use manually close kar dijiye."];
        $status = "fail";
    } else {
        $result = call_python($PYTHON_PATH, $PY_SCRIPT, "app open " . escapeshellarg("$driveLetter drive"));
        if ($result && $result['ok']) {
            $lines = ["Yes $user_name, $driveLetter drive File Explorer mein open kar diya hai"];
        } else {
            $lines = ["Sorry $user_name, $driveLetter drive open nahi ho paya. Check karo ki wo drive exist karti hai."];
            $status = "fail";
        }
    }

// ---------- OPEN / CLOSE a WEB APP (tab) — handled by the browser ----------
} elseif (preg_match('/^(open|close)\s+(.+)/i', $cmd, $m) && fuzzy_match_key(trim($m[2]), $WEB_APPS) !== null) {
    $action = strtolower($m[1]);
    $targetRaw = trim($m[2]);
    $key = fuzzy_match_key($targetRaw, $WEB_APPS);
    $url = $WEB_APPS[$key];

    $lines = ["Yes $user_name, $key " . ($action === 'open' ? 'open' : 'close') . " kar diya hai"];
    $extra['tab_action'] = $action;   // "open" or "close"
    $extra['tab_name'] = $key;
    $extra['tab_url'] = $url;

// ---------- OPEN / CLOSE desktop app or folder shortcut ----------
} elseif (preg_match('/^(open|close)\s+(.+)/i', $cmd, $m)) {
    $action = strtolower($m[1]);
    $target = trim($m[2]);
    $result = call_python($PYTHON_PATH, $PY_SCRIPT, "app $action " . escapeshellarg($target));

    if ($result && $result['ok']) {
        $lines = ["Yes $user_name, $target " . ($action === 'open' ? 'open' : 'close') . " kar diya hai"];
    } elseif ($action === 'close' && $result && ($result['error'] ?? '') === 'not_supported') {
        // Explicitly-unclosable apps (Settings, Microsoft Store) - honest
        // message instead of the generic "app available nahi hai".
        $lines = ["Sorry $user_name, $target close support nahi karta"];
        $status = "fail";
    } elseif ($action === 'close' && $result && ($result['error'] ?? '') === 'process not running or could not be closed') {
        $lines = ["Sorry $user_name, $target abhi open hi nahi hai ya close nahi ho paya"];
        $status = "fail";
    } else {
        $lines = ["Sorry $user_name, app available nahi hai (check error_log.txt for details)"];
        $status = "fail";
    }

// ---------- CREATE / DELETE FILE OR FOLDER (path given) ----------
} elseif (preg_match('/^(create|delete)\s+(.+)/i', $raw, $m)) {
    $action = strtolower($m[1]);
    $path = trim($m[2]);
    $result = call_python($PYTHON_PATH, $PY_SCRIPT, "path $action " . escapeshellarg($path));

    if ($result && $result['ok']) {
        $kind = $result['kind'] ?? 'file/folder'; // "file" or "folder"
        $lines = ["Yes $user_name, $kind " . ($action === 'create' ? 'create' : 'delete') . " kar diya hai"];
    } elseif ($result && str_starts_with($result['error'] ?? '', 'refused:')) {
        // Safety refusal (e.g. trying to delete Desktop/Documents/home
        // wholesale) - show the REAL reason, not a generic failure.
        $reason = substr($result['error'], strlen('refused: '));
        $lines = ["Sorry $user_name, $reason"];
        $status = "fail";
    } else {
        $detail = $result['error'] ?? 'unknown error';
        $lines = ["Sorry $user_name, path operation fail ho gaya: $detail"];
        $status = "fail";
    }

// ---------- SEARCH ----------
} elseif (preg_match('/^search\s+(.+)/i', $raw, $m)) {
    $query = trim($m[1]);
    $result = call_python($PYTHON_PATH, $PY_SCRIPT, "search " . escapeshellarg($query));
    if ($result && $result['ok']) {
        $lines = ["Yes $user_name, \"$query\" search kar diya hai"];
    } else {
        $lines = ["Sorry $user_name, search nahi ho paya (check error_log.txt for details)."];
        $status = "fail";
    }

// ---------- PLAY SONG ----------
} elseif (preg_match('/^play\s+(.+)/i', $raw, $m)) {
    $song = trim($m[1]);
    $result = call_python($PYTHON_PATH, $PY_SCRIPT, "play " . escapeshellarg($song));
    if ($result && $result['ok']) {
        $lines = ["Yes $user_name, \"$song\" YouTube par play kar diya hai"];
    } else {
        $lines = ["Sorry $user_name, song play nahi ho paya (check error_log.txt for details)."];
        $status = "fail";
    }

// ---------- CALCULATOR (with optional "calculate"/"calc" prefix) ----------
} elseif (preg_match('/^(?:calculate|calc)\s+([\d\.\s\+\-\*\/\%\(\)]+)$/i', $raw, $m)
        || (preg_match('/^[\d\.\s\+\-\*\/\%\(\)]+$/', $raw) && preg_match('/[\d]/', $raw))) {

    $expr = isset($m[1]) ? $m[1] : $raw;
    $safeExpr = preg_replace('/[^0-9\.\+\-\*\/\%\(\)\s]/', '', $expr);
    try {
        $result = eval("return ($safeExpr);");
        if (!is_numeric($result)) {
            throw new Exception("not numeric");
        }
        $lines = ["The answer is $result"];
    } catch (Throwable $e) {
        $lines = ["Sorry $user_name, ye calculation samajh nahi aaya."];
        $status = "fail";
    }

// ---------- MEMORY: remember X is Y ----------
} elseif (preg_match('/remember\s+(?:my\s+)?(.+?)\s+is\s+(.+)/i', $raw, $m)) {
    $key = trim($m[1]);
    $value = trim($m[2]);
    save_memory($conn, $user_id, $key, $value);
    $lines = ["Yes $user_name, i remembered your $key is $value"];

// ---------- MEMORY: generic "remember ..." (anything that isn't the
// "X is Y" shape above) - e.g. "remember i am human", "remember to call
// mom tomorrow". Stored as its own free-form note instead of being
// rejected, so it still shows up in the Memory panel and can be
// recalled later. Each note gets its own unique key so multiple general
// remembers don't overwrite each other (save_memory UPDATEs on a
// matching key, INSERTs on a new one). ----------
} elseif (preg_match('/^remember\s+(?:that\s+)?(.+)/i', $raw, $m)) {
    $fact = trim($m[1]);
    $key = "note_" . substr(md5($fact . microtime()), 0, 8);
    save_memory($conn, $user_id, $key, $fact);
    $lines = ["Okay $user_name, I'll remember that $fact"];

// ---------- WHAT / TELL ME MY NAME (checked before generic memory lookup) ----------
} elseif (strpos($cmd, "my name") !== false) {
    $lines = ["Your name is $user_name 😊"];

// ---------- MEMORY: what is my X ----------
} elseif (preg_match('/what\s+is\s+my\s+(.+?)\s*\??$/i', $raw, $m)) {
    $key = trim($m[1]);
    $value = get_memory($conn, $user_id, $key);
    if ($value !== null) {
        $lines = ["Yes $user_name, your $key is $value"];
    } else {
        $lines = ["Sorry $user_name, mujhe aapka $key yaad nahi hai. Pehle 'remember my $key is ...' bolke batao."];
        $status = "fail";
    }

// ---------- FULL FORM ----------
} elseif (strpos($cmd, "full form") !== false) {
    $lines = ["My full form is Just A Rather Very Intelligent System"];

// ---------- WHO CREATED YOU ----------
} elseif (strpos($cmd, "who create") !== false || strpos($cmd, "who made") !== false) {
    // >>> Change OWNER_NAME below to your own name
    $OWNER_NAME = "Min";
    $lines = ["$OWNER_NAME created me"];

// ---------- WHO AM I FOR YOU ----------
} elseif (strpos($cmd, "who i am") !== false || strpos($cmd, "who am i") !== false) {
    $lines = ["I'm your personal assistant $user_name"];

// ---------- UNKNOWN -> last resort, search stored memory/notes for a
// natural-language match before giving up (so "remember I am human"
// followed later by "am I human" or "what do you know about me" has a
// chance of actually answering instead of always failing) ----------
} else {
    $found = search_memory($conn, $user_id, $raw);
    if ($found) {
        $lines = ["Yes $user_name, i remember: " . $found['mem_value']];
    } else {
        $lines = ["Sorry $user_name, mujhe ye command samajh nahi aayi."];
        $status = "fail";
    }
}

// Save to history (join multi-line response with " | " for storage).
// Store the ORIGINAL text the user typed/spoke, not the cleaned-up one.
save_history($conn, $user_id, $rawInput, implode(" | ", $lines), $status);

echo json_encode(array_merge(["status" => $status, "lines" => $lines], $extra));
?>
