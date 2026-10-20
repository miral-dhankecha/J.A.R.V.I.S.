<?php
require_once __DIR__ . "/includes/auth_check.php";
$userName = htmlspecialchars($_SESSION['user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>J.A.R.V.I.S - Dashboard</title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="jarvis-container">

    <div class="header-panel">
        <h1 class="logo-text">J.A.R.V.I.S</h1>
        <div class="datetime-row">
            <span id="cur-day"></span> | <span id="cur-date"></span> | <span id="cur-time"></span>
        </div>
        <div class="stats-row">
            <span>CPU : <b id="stat-cpu">--%</b></span> |
            <span>RAM : <b id="stat-ram">--%</b></span> |
            <span>Battery : <b id="stat-battery">--%</b></span> |
            <span>Internet : <b id="stat-internet">--</b></span> |
            <span>Bluetooth : <b id="stat-bluetooth">--</b></span>
        </div>
        <div class="stats-row">
            <span>Weather : <b id="stat-weather">Loading...</b></span>
        </div>
        <div id="system-status" class="system-status offline">SYSTEM OFFLINE</div>
    </div>

    <!-- ================= HUD: gauges | animated orb | clock + calendar ================= -->
    <div class="hud-grid">

        <!-- LEFT: circular % gauges -->
        <div class="hud-col">
            <div class="panel-card gauge-card gauge-cpu">
                <svg class="gauge-svg" viewBox="0 0 100 100">
                    <circle class="gauge-track" cx="50" cy="50" r="42"></circle>
                    <circle class="gauge-fill" id="gauge-cpu-fill" cx="50" cy="50" r="42"
                        stroke-dasharray="264" stroke-dashoffset="264"></circle>
                    <text class="gauge-percent" id="gauge-cpu-text" x="50" y="56" text-anchor="middle">0%</text>
                </svg>
                <div class="gauge-label">CPU LOAD</div>
            </div>
            <div class="panel-card gauge-card gauge-ram">
                <svg class="gauge-svg" viewBox="0 0 100 100">
                    <circle class="gauge-track" cx="50" cy="50" r="42"></circle>
                    <circle class="gauge-fill" id="gauge-ram-fill" cx="50" cy="50" r="42"
                        stroke-dasharray="264" stroke-dashoffset="264"></circle>
                    <text class="gauge-percent" id="gauge-ram-text" x="50" y="56" text-anchor="middle">0%</text>
                </svg>
                <div class="gauge-label">MEMORY</div>
            </div>
            <div class="panel-card gauge-card gauge-battery" id="gauge-battery-card">
                <svg class="gauge-svg" viewBox="0 0 100 100">
                    <circle class="gauge-track" cx="50" cy="50" r="42"></circle>
                    <circle class="gauge-fill" id="gauge-battery-fill" cx="50" cy="50" r="42"
                        stroke-dasharray="264" stroke-dashoffset="264"></circle>
                    <text class="gauge-percent" id="gauge-battery-text" x="50" y="56" text-anchor="middle">0%</text>
                </svg>
                <div class="gauge-label">BATTERY</div>
            </div>
        </div>

        <!-- CENTER: animated Jarvis orb -->
        <div class="panel-card orb-panel">
            <div class="orb-wrap state-idle" id="orb-wrap">
                <div class="orb-ring ring-dashed"></div>
                <div class="orb-ring ring-ticks"></div>
                <div class="orb-sweep"></div>
                <div class="orb-ripple"></div>
                <div class="orb-ripple"></div>
                <div class="orb-ripple"></div>
                <div class="orb-core"></div>
                <div class="orb-eq">
                    <span></span><span></span><span></span><span></span><span></span>
                </div>
            </div>
            <div class="orb-caption" id="orb-caption">STANDING BY</div>
        </div>

        <!-- RIGHT: clock + calendar -->
        <div class="hud-col">
            <div class="panel-card clock-card">
                <svg class="clock-svg" viewBox="0 0 100 100" id="clock-svg">
                    <circle class="clock-face" cx="50" cy="50" r="46"></circle>
                    <!-- ticks injected by JS -->
                    <g id="clock-ticks"></g>
                    <line id="clock-hour" class="clock-hand-hour" x1="50" y1="50" x2="50" y2="30"></line>
                    <line id="clock-minute" class="clock-hand-minute" x1="50" y1="50" x2="50" y2="20"></line>
                    <circle class="clock-hub" cx="50" cy="50" r="3"></circle>
                </svg>
                <div class="clock-digital" id="clock-digital">--:--:--</div>
                <div class="clock-date" id="clock-date-label">--</div>
            </div>

            <div class="panel-card calendar-card">
                <div class="calendar-head">
                    <button type="button" class="cal-nav-btn" id="cal-prev">&#9664;</button>
                    <span class="cal-title" id="cal-title">--</span>
                    <button type="button" class="cal-nav-btn" id="cal-next">&#9654;</button>
                </div>
                <div class="cal-grid" id="cal-grid"></div>
            </div>
        </div>

    </div>

    <div class="output-panel" id="jarvis-output">
        <p>Jarvis: GUI System Ready <?php echo $userName; ?> 👋</p>
    </div>

    <div class="input-row">
        <input type="text" id="command-input" placeholder="Type your command...">
        <button id="mic-btn" title="Speak command">🎤</button>
    </div>

    <div class="button-row">
        <button id="btn-start" class="btn btn-start">START</button>
        <button id="btn-history" class="btn btn-history">HISTORY</button>
        <button id="btn-memory" class="btn btn-memory">MEMORY</button>
        <button id="btn-charts" class="btn btn-charts">CHARTS</button>
        <a href="auth/logout.php" class="btn btn-logout">LOGOUT</a>
        <button id="btn-exit" class="btn btn-exit">EXIT</button>
    </div>

    <!-- History Modal -->
    <div id="history-modal" class="modal hidden">
        <div class="modal-box">
            <div class="modal-titlebar">
                <h2>Command History</h2>
                <div class="window-controls">
                    <button type="button" class="win-btn win-min" data-target="history-modal" title="Minimize">&#9472;</button>
                    <button type="button" class="win-btn win-max" data-target="history-modal" title="Maximize">&#9633;</button>
                    <button type="button" class="win-btn win-close" data-target="history-modal" title="Close">&times;</button>
                </div>
            </div>
            <div class="modal-search-row">
                <input type="date" id="history-search-date" class="modal-search-input">
                <button type="button" id="history-search-btn" class="btn btn-search">Search</button>
                <button type="button" id="history-search-clear" class="btn btn-search-clear">Show All</button>
            </div>
            <div id="history-content" class="modal-content"></div>
        </div>
    </div>

    <!-- Memory Modal -->
    <div id="memory-modal" class="modal hidden">
        <div class="modal-box">
            <div class="modal-titlebar">
                <h2>Jarvis Memory</h2>
                <div class="window-controls">
                    <button type="button" class="win-btn win-min" data-target="memory-modal" title="Minimize">&#9472;</button>
                    <button type="button" class="win-btn win-max" data-target="memory-modal" title="Maximize">&#9633;</button>
                    <button type="button" class="win-btn win-close" data-target="memory-modal" title="Close">&times;</button>
                </div>
            </div>
            <div class="modal-search-row">
                <input type="date" id="memory-search-date" class="modal-search-input">
                <button type="button" id="memory-search-btn" class="btn btn-search">Search</button>
                <button type="button" id="memory-search-clear" class="btn btn-search-clear">Show All</button>
            </div>
            <div id="memory-content" class="modal-content"></div>
        </div>
    </div>

    <!-- Charts Modal -->
    <div id="charts-modal" class="modal hidden">
        <div class="modal-box">
            <div class="modal-titlebar">
                <h2>Statistics</h2>
                <div class="window-controls">
                    <button type="button" class="win-btn win-min" data-target="charts-modal" title="Minimize">&#9472;</button>
                    <button type="button" class="win-btn win-max" data-target="charts-modal" title="Maximize">&#9633;</button>
                    <button type="button" class="win-btn win-close" data-target="charts-modal" title="Close">&times;</button>
                </div>
            </div>
            <div class="modal-content">
                <canvas id="pieChart" width="350" height="300"></canvas>
                <canvas id="barChart" width="350" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Minimized windows dock -->
    <div id="taskbar-dock" class="taskbar-dock"></div>

</div>

<script>
    const USER_NAME = <?php echo json_encode($_SESSION['user_name']); ?>;
</script>
<script src="assets/js/main.js"></script>
<script src="assets/js/speech.js"></script>
<script src="assets/js/charts.js"></script>
</body>
</html>
