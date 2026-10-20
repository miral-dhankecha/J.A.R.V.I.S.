// ============================================
// main.js - dashboard logic, AJAX calls, UI control
// ============================================

const outputPanel = document.getElementById("jarvis-output");
const commandInput = document.getElementById("command-input");
const systemStatusEl = document.getElementById("system-status");

const STORAGE_KEY = "jarvis_session_" + USER_NAME;

// Session state persisted in sessionStorage so STOP -> START (or even a
// page refresh) resumes the SAME conversation instead of demanding
// "wake up Jarvis" again. Only EXIT / LOGOUT clears it.
let state = loadState();
let systemOnline = state.online;

function loadState() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw) return JSON.parse(raw);
    } catch (e) {}
    return { online: false, hasWoken: false, outputHTML: "" };
}

function saveState() {
    state.online = systemOnline;
    state.outputHTML = outputPanel.innerHTML;
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (e) {}
}

function clearState() {
    try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
    state = { online: false, hasWoken: false, outputHTML: "" };
}

// Restore previous conversation on load
if (state.outputHTML) {
    outputPanel.innerHTML = state.outputHTML;
    outputPanel.scrollTop = outputPanel.scrollHeight;
}
if (systemOnline) {
    systemStatusEl.textContent = "SYSTEM ONLINE";
    systemStatusEl.classList.remove("offline");
    systemStatusEl.classList.add("online");
}

// ---------- Clock (header text + analog/digital widget) ----------
function updateClock() {
    const now = new Date();
    document.getElementById("cur-day").textContent = now.toLocaleDateString('en-US', { weekday: 'long' });
    document.getElementById("cur-date").textContent = now.toLocaleDateString('en-GB');
    document.getElementById("cur-time").textContent = now.toLocaleTimeString();

    document.getElementById("clock-digital").textContent = now.toLocaleTimeString();
    document.getElementById("clock-date-label").textContent =
        now.toLocaleDateString('en-US', { weekday: 'short', day: '2-digit', month: 'short' });

    updateAnalogClock(now);
}
setInterval(updateClock, 1000);
updateClock();

// ---------- System stats (CPU/RAM/Battery/Internet/Bluetooth) ----------
function refreshStats() {
    fetch("api/system_stats.php")
        .then(r => r.json())
        .then(data => {
            document.getElementById("stat-cpu").textContent = data.cpu + "%";
            document.getElementById("stat-ram").textContent = data.ram + "%";
            document.getElementById("stat-battery").textContent = data.battery + "%";
            document.getElementById("stat-internet").textContent = data.internet;
            document.getElementById("stat-bluetooth").textContent = data.bluetooth;

            setGauge("cpu", data.cpu);
            setGauge("ram", data.ram);
            setGauge("battery", data.battery);

            const batteryCard = document.getElementById("gauge-battery-card");
            batteryCard.classList.toggle("critical", data.battery <= 15);
            batteryCard.classList.toggle("low", data.battery > 15 && data.battery <= 30);
        })
        .catch(() => {});
}
setInterval(refreshStats, 15000);
refreshStats();

// ---------- Circular gauge rings (CPU / RAM / BATTERY) ----------
const GAUGE_CIRCUMFERENCE = 2 * Math.PI * 42; // r=42 in the SVG viewBox
function setGauge(name, percent) {
    percent = Math.max(0, Math.min(100, Number(percent) || 0));
    const fill = document.getElementById(`gauge-${name}-fill`);
    const text = document.getElementById(`gauge-${name}-text`);
    if (!fill || !text) return;
    const offset = GAUGE_CIRCUMFERENCE - (percent / 100) * GAUGE_CIRCUMFERENCE;
    fill.style.strokeDasharray = GAUGE_CIRCUMFERENCE;
    fill.style.strokeDashoffset = offset;
    text.textContent = Math.round(percent) + "%";
}

// ---------- Analog + digital clock ----------
const clockTicksGroup = document.getElementById("clock-ticks");
(function buildClockTicks() {
    for (let i = 0; i < 60; i++) {
        const angle = (i / 60) * 360;
        const isMajor = i % 5 === 0;
        const rOuter = 46;
        const rInner = isMajor ? 40 : 43;
        const rad = (angle - 90) * (Math.PI / 180);
        const x1 = 50 + rOuter * Math.cos(rad);
        const y1 = 50 + rOuter * Math.sin(rad);
        const x2 = 50 + rInner * Math.cos(rad);
        const y2 = 50 + rInner * Math.sin(rad);
        const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
        line.setAttribute("x1", x1); line.setAttribute("y1", y1);
        line.setAttribute("x2", x2); line.setAttribute("y2", y2);
        line.setAttribute("class", "clock-tick " + (isMajor ? "major" : "minor"));
        clockTicksGroup.appendChild(line);
    }
})();

function updateAnalogClock(now) {
    const hours = now.getHours() % 12;
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();

    const minuteAngle = (minutes + seconds / 60) * 6; // 360/60
    const hourAngle = (hours + minutes / 60) * 30;     // 360/12

    setHand("clock-hour", hourAngle, 22);
    setHand("clock-minute", minuteAngle, 32);
}
function setHand(id, angleDeg, length) {
    const rad = (angleDeg - 90) * (Math.PI / 180);
    const x2 = 50 + length * Math.cos(rad);
    const y2 = 50 + length * Math.sin(rad);
    const line = document.getElementById(id);
    line.setAttribute("x2", x2);
    line.setAttribute("y2", y2);
}

// ---------- Calendar (with month navigation) ----------
let calViewYear, calViewMonth; // 0-indexed month
const CAL_MONTH_NAMES = ["January","February","March","April","May","June",
    "July","August","September","October","November","December"];

function renderCalendar(year, month) {
    calViewYear = year;
    calViewMonth = month;

    const today = new Date();
    const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;

    document.getElementById("cal-title").textContent = `${CAL_MONTH_NAMES[month]} ${year}`;

    const grid = document.getElementById("cal-grid");
    grid.innerHTML = "";
    ["Su","Mo","Tu","We","Th","Fr","Sa"].forEach(d => {
        const el = document.createElement("div");
        el.className = "cal-dow";
        el.textContent = d;
        grid.appendChild(el);
    });

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    // leading days from previous month
    for (let i = firstDay - 1; i >= 0; i--) {
        grid.appendChild(makeDayCell(daysInPrevMonth - i, true, false));
    }
    // this month's days
    for (let d = 1; d <= daysInMonth; d++) {
        grid.appendChild(makeDayCell(d, false, isCurrentMonth && d === today.getDate()));
    }
    // trailing days to fill the last row to 7 columns
    const totalCells = firstDay + daysInMonth;
    const trailing = (7 - (totalCells % 7)) % 7;
    for (let d = 1; d <= trailing; d++) {
        grid.appendChild(makeDayCell(d, true, false));
    }
}
function makeDayCell(num, otherMonth, isToday) {
    const el = document.createElement("div");
    el.className = "cal-day" + (otherMonth ? " other-month" : "") + (isToday ? " today" : "");
    el.textContent = num;
    return el;
}
document.getElementById("cal-prev").addEventListener("click", () => {
    let m = calViewMonth - 1, y = calViewYear;
    if (m < 0) { m = 11; y--; }
    renderCalendar(y, m);
});
document.getElementById("cal-next").addEventListener("click", () => {
    let m = calViewMonth + 1, y = calViewYear;
    if (m > 11) { m = 0; y++; }
    renderCalendar(y, m);
});
(function initCalendar() {
    const now = new Date();
    renderCalendar(now.getFullYear(), now.getMonth());
})();

// ---------- Jarvis orb state ("talking"/"listening" indicator) ----------
const orbWrap = document.getElementById("orb-wrap");
const orbCaption = document.getElementById("orb-caption");
window.setOrbState = function (mode) {
    orbWrap.classList.remove("state-idle", "state-listening", "state-speaking");
    orbWrap.classList.add("state-" + mode);
    orbCaption.textContent = mode === "listening" ? "LISTENING..."
        : mode === "speaking" ? "SPEAKING..."
        : "STANDING BY";
};

// ---------- Weather (stats bar, Rajkot default) ----------
function refreshWeather() {
    fetch("api/weather.php?city=Rajkot")
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById("stat-weather").textContent =
                    `Rajkot - ${data.desc}, ${data.temp}°C`;
            } else {
                document.getElementById("stat-weather").textContent = "Weather unavailable";
            }
        })
        .catch(() => {
            document.getElementById("stat-weather").textContent = "Weather unavailable";
        });
}
refreshWeather();

// ---------- Output helpers ----------
function printUserLine(text) {
    const p = document.createElement("p");
    p.className = "user-line";
    p.textContent = "Me: " + text;
    outputPanel.appendChild(p);
    outputPanel.scrollTop = outputPanel.scrollHeight;
    saveState();
}

// Show + speak lines ONE AT A TIME: line 1 is written, then spoken; only
// once that speech finishes does line 2 get written + spoken, and so on.
// (Previously ALL lines were written at once, then spoken together at
// the end - out of sync with what the person could actually read.)
function printJarvisLines(lines, isFail) {
    let i = 0;
    function next() {
        if (i >= lines.length) { saveState(); return; }
        const line = lines[i++];
        const p = document.createElement("p");
        p.className = isFail ? "fail-line" : "jarvis-line";
        p.textContent = "Jarvis: " + line;
        outputPanel.appendChild(p);
        outputPanel.scrollTop = outputPanel.scrollHeight;

        if (window.jarvisSpeak) {
            window.jarvisSpeak(line, next);
        } else {
            next();
        }
    }
    next();
}

// ---------- Browser-tab web apps (gmail, whatsapp, youtube, ...) ----------
// Opened/closed as real window handles from JS, so "close whatsapp" only
// closes that ONE tab instead of killing the whole browser (which used
// to also close the Jarvis dashboard tab itself).
window.jarvisTabs = window.jarvisTabs || {};

// Returns true/false = whether the open/close ACTUALLY worked, so
// sendCommand() can tell the user the truth instead of a canned
// "kar diya hai" that may not have happened.
function handleTabAction(data) {
    if (!data.tab_action) return null;
    const name = data.tab_name;
    if (data.tab_action === "open") {
        const existing = window.jarvisTabs[name];
        if (existing && !existing.closed) {
            existing.focus();
            return true;
        }
        const win = window.open(data.tab_url, "jarvis_tab_" + name.replace(/\s+/g, "_"));
        window.jarvisTabs[name] = win;
        // win comes back null/undefined if the browser's popup blocker ate it
        return !!win;
    } else if (data.tab_action === "close") {
        const existing = window.jarvisTabs[name];
        delete window.jarvisTabs[name];
        if (existing && !existing.closed) {
            existing.close();
            // .close() can silently do NOTHING if the site itself severed the
            // opener link (many sites do this on purpose, e.g. after login
            // redirects) - browsers don't throw an error for that, they just
            // ignore the call. Checking .closed afterwards is the only way
            // to know whether it actually worked.
            return existing.closed;
        }
        // No tracked handle - e.g. the dashboard page was reloaded since
        // that tab was opened, so the browser gave us no way to reach it.
        return false;
    }
    return null;
}

// ---------- Send command to server ----------
function sendCommand(text) {
    if (!text.trim()) return;
    printUserLine(text);

    fetch("api/command.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "command=" + encodeURIComponent(text)
    })
        .then(r => r.json())
        .then(data => {
            let lines = data.lines;
            let isFail = data.status === "fail";

            // For "open/close <web app tab>" commands, the server can't know
            // whether it really worked - only the browser does, since it's
            // the one holding (or losing) the window handle. So build the
            // line the user sees/hears from the REAL outcome, not a canned
            // success message.
            if (data.tab_action) {
                const worked = handleTabAction(data);
                if (data.tab_action === "open") {
                    lines = worked
                        ? [`Yes ${USER_NAME}, ${data.tab_name} open kar diya hai`]
                        : [`Sorry ${USER_NAME}, ${data.tab_name} open nahi ho paya. Popup blocked ho sakta hai - browser ki address bar me popup icon check karo.`];
                } else {
                    lines = worked
                        ? [`Yes ${USER_NAME}, ${data.tab_name} close kar diya hai`]
                        : [`Sorry ${USER_NAME}, ${data.tab_name} ka tab close nahi kar paaya. Ho sakta hai page reload hone ke baad tab handle kho gaya ho, ya site khud opener link tod deti hai - use manually close (Ctrl+W) karna padega.`];
                }
                isFail = !worked;
            }

            // Cancel any speech left over from a previous response before
            // starting this one, so lines don't overlap/queue up.
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            printJarvisLines(lines, isFail);
            if (/wake up jarvis/i.test(text)) {
                state.hasWoken = true;
                saveState();
            }
            if (data.session_end) {
                systemOnline = false;
                state.hasWoken = false;
                systemStatusEl.textContent = "SYSTEM OFFLINE";
                systemStatusEl.classList.remove("online");
                systemStatusEl.classList.add("offline");
                saveState();
            }
        })
        .catch(() => {
            printJarvisLines(["Server se connect nahi ho paya. Apache/Laragon check karo."], true);
        });

    commandInput.value = "";
}

// ---------- Start ----------
// (STOP button removed - it never worked reliably and EXIT already takes
// the system offline cleanly, so there was no real need for it.)
document.getElementById("btn-start").addEventListener("click", () => {
    systemOnline = true;
    systemStatusEl.textContent = "SYSTEM ONLINE";
    systemStatusEl.classList.remove("offline");
    systemStatusEl.classList.add("online");

    // Only prompt "say wake up Jarvis" the FIRST time. After a refresh,
    // resuming shouldn't force the wake-up phrase again.
    if (!state.hasWoken) {
        const p = document.createElement("p");
        p.className = "jarvis-line";
        p.textContent = "Jarvis: Jarvis ready. Say wake up Jarvis";
        outputPanel.appendChild(p);
        outputPanel.scrollTop = outputPanel.scrollHeight;
        if (window.jarvisSpeak) window.jarvisSpeak("Jarvis ready. Say wake up Jarvis");
    }
    saveState();
});

// ---------- Exit ----------
document.getElementById("btn-exit").addEventListener("click", () => {
    sendCommand("exit");
    systemOnline = false;
    systemStatusEl.textContent = "SYSTEM OFFLINE";
    systemStatusEl.classList.remove("online");
    systemStatusEl.classList.add("offline");
    clearState();
});

// ---------- Text input (Enter key) ----------
commandInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        sendCommand(commandInput.value);
    }
});

// ============================================
// Window controls (minimize / maximize / close) for History, Memory
// and Charts modals + a taskbar dock for minimized windows.
// ============================================
const taskbarDock = document.getElementById("taskbar-dock");
const modalTitles = {
    "history-modal": "Command History",
    "memory-modal": "Jarvis Memory",
    "charts-modal": "Statistics"
};

function openModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove("hidden");
    modal.classList.remove("minimized");
    removeTaskbarChip(id);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.add("hidden");
    modal.classList.remove("minimized", "maximized-parent");
    const box = modal.querySelector(".modal-box");
    if (box) box.classList.remove("maximized");
    removeTaskbarChip(id);
}

function minimizeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.add("minimized");
    addTaskbarChip(id);
}

function toggleMaximize(id) {
    const modal = document.getElementById(id);
    const box = modal.querySelector(".modal-box");
    if (box) box.classList.toggle("maximized");
}

function addTaskbarChip(id) {
    if (document.getElementById("chip-" + id)) return;
    const chip = document.createElement("div");
    chip.className = "taskbar-chip";
    chip.id = "chip-" + id;
    chip.textContent = modalTitles[id] || "Window";
    chip.addEventListener("click", () => openModal(id));
    taskbarDock.appendChild(chip);
}

function removeTaskbarChip(id) {
    const chip = document.getElementById("chip-" + id);
    if (chip) chip.remove();
}

document.querySelectorAll(".win-close").forEach(btn => {
    btn.addEventListener("click", () => closeModal(btn.dataset.target));
});
document.querySelectorAll(".win-min").forEach(btn => {
    btn.addEventListener("click", () => minimizeModal(btn.dataset.target));
});
document.querySelectorAll(".win-max").forEach(btn => {
    btn.addEventListener("click", () => toggleMaximize(btn.dataset.target));
});

// ---------- History modal (with date search) ----------
function loadHistory(date) {
    const url = date ? "api/history.php?date=" + encodeURIComponent(date) : "api/history.php";
    fetch(url)
        .then(r => r.json())
        .then(data => {
            let html = "";
            if (data.filtered_date) {
                html += `<div class="search-info">Showing history for ${data.filtered_date} (${data.rows.length} command${data.rows.length === 1 ? "" : "s"})</div>`;
            }
            html += "<table><tr><th>User</th><th>Date</th><th>Day</th><th>Time</th><th>Command</th><th>Response</th><th>Status</th></tr>";
            data.rows.forEach(row => {
                const statusMark = row.status === "success"
                    ? '<span class="status-ok">✔</span>'
                    : '<span class="status-fail">✘</span>';
                html += `<tr>
                    <td>${data.user_name}</td>
                    <td>${row.cmd_date}</td>
                    <td>${row.cmd_day}</td>
                    <td>${row.cmd_time}</td>
                    <td>${row.command_text}</td>
                    <td>${row.response_text}</td>
                    <td>${statusMark}</td>
                </tr>`;
            });
            html += "</table>";
            document.getElementById("history-content").innerHTML =
                data.rows.length ? html : (data.filtered_date ? `No history found for ${data.filtered_date}.` : "No history yet.");
        });
}

document.getElementById("btn-history").addEventListener("click", () => {
    document.getElementById("history-search-date").value = "";
    loadHistory();
    openModal("history-modal");
});
document.getElementById("history-search-btn").addEventListener("click", () => {
    loadHistory(document.getElementById("history-search-date").value);
});
document.getElementById("history-search-clear").addEventListener("click", () => {
    document.getElementById("history-search-date").value = "";
    loadHistory();
});

// ---------- Memory modal (with date search) ----------
function loadMemory(date) {
    const url = date ? "api/memory.php?date=" + encodeURIComponent(date) : "api/memory.php";
    fetch(url)
        .then(r => r.json())
        .then(data => {
            let html = "";
            if (data.filtered_date) {
                html += `<div class="search-info">Showing memory saved on ${data.filtered_date} (${data.rows.length} item${data.rows.length === 1 ? "" : "s"})</div>`;
            }
            html += "<table><tr><th>User</th><th>Date</th><th>Day</th><th>Time</th><th>Key</th><th>Value</th></tr>";
            data.rows.forEach(row => {
                html += `<tr>
                    <td>${data.user_name}</td>
                    <td>${row.cmd_date}</td>
                    <td>${row.cmd_day}</td>
                    <td>${row.cmd_time}</td>
                    <td>${row.mem_key}</td>
                    <td>${row.mem_value}</td>
                </tr>`;
            });
            html += "</table>";
            document.getElementById("memory-content").innerHTML =
                data.rows.length ? html : (data.filtered_date ? `No memory found for ${data.filtered_date}.` : "Nothing remembered yet.");
        });
}

document.getElementById("btn-memory").addEventListener("click", () => {
    document.getElementById("memory-search-date").value = "";
    loadMemory();
    openModal("memory-modal");
});
document.getElementById("memory-search-btn").addEventListener("click", () => {
    loadMemory(document.getElementById("memory-search-date").value);
});
document.getElementById("memory-search-clear").addEventListener("click", () => {
    document.getElementById("memory-search-date").value = "";
    loadMemory();
});
