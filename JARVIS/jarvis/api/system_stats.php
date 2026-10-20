<?php
header('Content-Type: application/json');

// >>> venv ka python.exe use ho raha hai (psutil isi venv mein installed hai).
// Path is computed RELATIVE to this project folder (not hardcoded to a
// specific folder name) so it keeps working no matter what the project
// folder is called/renamed to. Tries several fallbacks in order and picks
// the FIRST ONE THAT ACTUALLY RUNS (not just "exists") - a bare "python"
// can silently resolve to the Windows Store stub (no output at all) even
// when the file path looks fine on paper. This mirrors the identical fix
// in api/command.php - keep both in sync if you change this again.
function resolve_python_path(): string {
    $candidates = [];

    $venvPython = __DIR__ . "/../venv/Scripts/python.exe";
    $real = realpath($venvPython);
    if ($real !== false) {
        $candidates[] = '"' . $real . '"';
    }

    $candidates[] = "py -3";
    $candidates[] = "python";
    $candidates[] = "python3";

    foreach ($candidates as $candidate) {
        $test = @shell_exec($candidate . " --version 2>&1");
        if ($test !== null && stripos($test, "Python") !== false) {
            return $candidate;
        }
    }

    return "python";
}
$PYTHON_PATH = resolve_python_path();
$PY_SCRIPT = escapeshellarg(__DIR__ . "/../python/system_control.py");

$output = shell_exec($PYTHON_PATH . " " . $PY_SCRIPT . " stats 2>&1");
$decoded = json_decode($output, true);

if ($decoded) {
    echo json_encode($decoded);
} else {
    // Fallback if python call fails, so GUI doesn't break. Logs the RAW
    // output (not just "failed") so this is diagnosable from error_log.txt
    // instead of just always showing 0%/Unknown with no clue why.
    error_log("system_stats.php: python call failed. Command tried: $PYTHON_PATH. Raw output: " . var_export($output, true));
    echo json_encode([
        "ok" => false,
        "cpu" => 0, "ram" => 0, "battery" => 0,
        "internet" => "Unknown", "bluetooth" => "Unknown"
    ]);
}
?>