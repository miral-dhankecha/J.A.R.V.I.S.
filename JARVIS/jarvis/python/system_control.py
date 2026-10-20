"""
============================================
system_control.py
Called by PHP (shell_exec) with command-line args.
Always prints a single JSON line to stdout and exits.

Usage:
    python system_control.py stats
    python system_control.py app open "chrome"
    python system_control.py app close "chrome"
    python system_control.py path create "C:\\Users\\min18\\Downloads\\B.C.A\\MIN"
    python system_control.py path delete "C:\\Users\\min18\\Downloads\\B.C.A\\MIN"
    python system_control.py search "what is ai"
    python system_control.py play "believer imagine dragons"

NOTE: Web apps that just open inside a browser TAB (gmail, whatsapp,
youtube, drive, classroom, jiohotstar, instagram, marwadi university...)
are handled entirely in the browser now (assets/js/main.js), NOT here.
That's what lets Jarvis close only that ONE tab instead of killing the
whole Chrome/Edge process (which used to close the Jarvis dashboard too).
This script only launches/kills real Windows desktop programs and does
file/folder/search/play actions.
============================================
"""

import sys
import os
import json
import glob
import shutil
import socket
import subprocess
import time
import webbrowser

try:
    import psutil
except ImportError:
    psutil = None


# ------------------------------------------------------------------
# Real Windows "Known Folder" lookup for Desktop/Documents/Downloads/
# Pictures/Music/Videos - this is the ONLY fully correct way to find
# these paths. Guessing "home/X" vs "home/OneDrive/X" is unreliable
# because OneDrive "Backup your folders" can redirect these to all
# kinds of locations (and the exact behaviour has changed across
# OneDrive versions) - asking Windows itself via SHGetKnownFolderPath
# always returns wherever the folder ACTUALLY is right now, matching
# exactly what File Explorer itself would show you.
# ------------------------------------------------------------------
KNOWN_FOLDER_GUIDS = {
    "desktop": "{B4BFCC3A-DB2C-424C-B029-7FE99A87C641}",
    "documents": "{FDD39AD0-238F-46AF-ADB4-6C85480369C7}",
    "downloads": "{374DE290-123F-4565-9164-39C4925E467B}",
    "pictures": "{33E28130-4E1E-4676-835A-98395C3BC3BB}",
    "music": "{4BD8D571-6D19-48D3-BE97-422220080E43}",
    "videos": "{18989B1D-99B5-455B-841C-AB7C74E4DDFC}",
}


def _known_folder_path(name: str):
    """Returns the real current path of a Windows known folder, or None
    if the lookup isn't available (non-Windows, or API call failed)."""
    guid = KNOWN_FOLDER_GUIDS.get(name)
    if not guid or sys.platform != "win32":
        return None
    try:
        import ctypes
        from ctypes import wintypes

        class GUID(ctypes.Structure):
            _fields_ = [
                ("Data1", ctypes.c_ulong), ("Data2", ctypes.c_ushort),
                ("Data3", ctypes.c_ushort), ("Data4", ctypes.c_ubyte * 8),
            ]

        def guid_from_str(s):
            s = s.strip("{}")
            parts = s.split("-")
            data1 = int(parts[0], 16)
            data2 = int(parts[1], 16)
            data3 = int(parts[2], 16)
            rest = parts[3] + parts[4]
            data4 = (ctypes.c_ubyte * 8)(*[int(rest[i:i + 2], 16) for i in range(0, 16, 2)])
            return GUID(data1, data2, data3, data4)

        SHGetKnownFolderPath = ctypes.windll.shell32.SHGetKnownFolderPath
        path_ptr = ctypes.c_wchar_p()
        rc = SHGetKnownFolderPath(ctypes.byref(guid_from_str(guid)), 0, 0, ctypes.byref(path_ptr))
        if rc == 0 and path_ptr.value:
            return path_ptr.value
    except Exception:
        return None
    return None


# ------------------------------------------------------------------
# >>> CUSTOMIZE THIS: map spoken app names -> Windows executable name
# ------------------------------------------------------------------
DESKTOP_APPS = {
    "edge": "msedge",
    "microsoft edge": "msedge",
    "store": "ms-windows-store:",
    "microsoft store": "ms-windows-store:",
    "settings": "ms-settings:",
    "file explorer": "explorer",
    "explorer": "explorer",
    "chrome": "chrome",
    "google chrome": "chrome",
    "snipping tool": "SnippingTool",
    "snipping tools": "SnippingTool",
    "word": "winword",
    "ms word": "winword",
    "microsoft word": "winword",
    "excel": "excel",
    "ms excel": "excel",
    "microsoft excel": "excel",
    "access": "msaccess",
    "ms access": "msaccess",
    "paint": "mspaint",
    "notepad": "notepad",
    "powerpoint": "powerpnt",
    "power point": "powerpnt",
    "ms powerpoint": "powerpnt",
    "calculator": "calc",
}

# Candidate process names used for "close" (taskkill /IM name). A list
# because Windows 10/11 sometimes runs a different real process name
# than the exe you launch with (e.g. calc.exe -> CalculatorApp.exe).
CLOSE_PROCESS_MAP = {
    "edge": ["msedge.exe"],
    "microsoft edge": ["msedge.exe"],
    "file explorer": ["explorer.exe"],
    "explorer": ["explorer.exe"],
    "chrome": ["chrome.exe"],
    "google chrome": ["chrome.exe"],
    "snipping tool": ["SnippingTool.exe", "ScreenClippingHost.exe"],
    "snipping tools": ["SnippingTool.exe", "ScreenClippingHost.exe"],
    "word": ["WINWORD.EXE"],
    "ms word": ["WINWORD.EXE"],
    "microsoft word": ["WINWORD.EXE"],
    "excel": ["EXCEL.EXE"],
    "ms excel": ["EXCEL.EXE"],
    "microsoft excel": ["EXCEL.EXE"],
    "access": ["MSACCESS.EXE"],
    "ms access": ["MSACCESS.EXE"],
    "paint": ["mspaint.exe"],
    "notepad": ["notepad.exe"],
    "powerpoint": ["POWERPNT.EXE"],
    "power point": ["POWERPNT.EXE"],
    "ms powerpoint": ["POWERPNT.EXE"],
    # Modern Windows 10/11 calculator actually runs as CalculatorApp.exe,
    # NOT calculator.exe - that's why "close calculator" used to fail.
    "calculator": ["CalculatorApp.exe", "Calculator.exe", "calculator.exe"],
}

# Apps that CAN be opened but genuinely can't be safely closed by name:
# - "settings" / "microsoft store" are UWP apps hosted inside a SHARED
#   ApplicationFrameHost.exe process along with other UWP apps (Photos,
#   Calculator's modern UI, etc). taskkill-ing that process name would
#   close whichever UWP apps happen to be open, not just Settings/Store.
# Instead of silently failing with a generic "app not found", we detect
# these explicitly and tell the user honestly that close isn't supported.
NOT_CLOSABLE_APPS = ["settings", "store", "microsoft store", "windows settings"]

# ------------------------------------------------------------------
# Fallback install locations, tried only if os.startfile()/Popen() by
# bare name fails (covers PCs where the exe isn't on PATH / App Paths).
# ------------------------------------------------------------------
COMMON_PATHS = {
    "chrome": [
        r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    ],
    "msedge": [
        r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
        r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
    ],
    "winword": [
        r"C:\Program Files\Microsoft Office\root\Office16\WINWORD.EXE",
        r"C:\Program Files (x86)\Microsoft Office\root\Office16\WINWORD.EXE",
        r"C:\Program Files\Microsoft Office\Office16\WINWORD.EXE",
    ],
    "excel": [
        r"C:\Program Files\Microsoft Office\root\Office16\EXCEL.EXE",
        r"C:\Program Files (x86)\Microsoft Office\root\Office16\EXCEL.EXE",
        r"C:\Program Files\Microsoft Office\Office16\EXCEL.EXE",
    ],
    "powerpnt": [
        r"C:\Program Files\Microsoft Office\root\Office16\POWERPNT.EXE",
        r"C:\Program Files (x86)\Microsoft Office\root\Office16\POWERPNT.EXE",
        r"C:\Program Files\Microsoft Office\Office16\POWERPNT.EXE",
    ],
    "msaccess": [
        r"C:\Program Files\Microsoft Office\root\Office16\MSACCESS.EXE",
        r"C:\Program Files (x86)\Microsoft Office\root\Office16\MSACCESS.EXE",
        r"C:\Program Files\Microsoft Office\Office16\MSACCESS.EXE",
    ],
}


# ------------------------------------------------------------------
# Known-fixed paths for system binaries that are prone to being
# misresolved by os.startfile()'s registry-based ("App Paths") lookup -
# e.g. Notepad opening some other program instead. Trying the real,
# guaranteed-correct Windows path FIRST removes that ambiguity entirely.
# ------------------------------------------------------------------
SYSTEM_BINARY_PATHS = {
    "notepad": [
        r"C:\Windows\System32\notepad.exe",
        r"C:\Windows\notepad.exe",
    ],
}


# ------------------------------------------------------------------
# Gracefully close Chrome windows WITHOUT touching the window the Jarvis
# dashboard itself is running in. Uses WM_CLOSE (the same signal Chrome's
# own [x] button sends) instead of taskkill /F, which kills the entire
# chrome.exe process tree in one shot - no way to spare one tab/window.
# ------------------------------------------------------------------
def close_other_chrome_windows(jarvis_title_marker="J.A.R.V.I.S"):
    import ctypes
    from ctypes import wintypes

    user32 = ctypes.windll.user32
    WM_CLOSE = 0x0010
    closed_titles = []

    @ctypes.WINFUNCTYPE(wintypes.BOOL, wintypes.HWND, wintypes.LPARAM)
    def enum_handler(hwnd, lparam):
        if not user32.IsWindowVisible(hwnd):
            return True

        length = user32.GetWindowTextLengthW(hwnd)
        if length == 0:
            return True
        buf = ctypes.create_unicode_buffer(length + 1)
        user32.GetWindowTextW(hwnd, buf, length + 1)
        title = buf.value

        cls_buf = ctypes.create_unicode_buffer(256)
        user32.GetClassNameW(hwnd, cls_buf, 256)
        cls_name = cls_buf.value

        # Chrome's top-level window class. Skip anything that isn't a
        # Chrome window, and skip the Jarvis dashboard window itself.
        if cls_name == "Chrome_WidgetWin_1" and jarvis_title_marker not in title:
            user32.PostMessageW(hwnd, WM_CLOSE, 0, 0)
            closed_titles.append(title)
        return True

    try:
        user32.EnumWindows(enum_handler, 0)
    except Exception:
        pass

    return closed_titles


# ------------------------------------------------------------------
# >>> CUSTOMIZE THIS: common folder shortcuts
# ------------------------------------------------------------------
def folder_shortcuts():
    home = os.path.expanduser("~")
    onedrive = os.environ.get("OneDrive") or os.environ.get("OneDriveConsumer") or os.path.join(home, "OneDrive")

    def resolve(name):
        # 1) Ask Windows directly (correct even for any custom redirection,
        #    not just the common "moved into OneDrive" case).
        kf = _known_folder_path(name.lower())
        if kf and os.path.isdir(kf):
            return kf
        # 2) Guess: OneDrive backup usually moves it to OneDrive\<Folder>.
        onedrive_path = os.path.join(onedrive, name)
        if os.path.isdir(onedrive_path):
            return onedrive_path
        # 3) Plain, non-redirected default location.
        return os.path.join(home, name)

    shortcuts = {
        "desktop": resolve("Desktop"),
        "downloads": resolve("Downloads"),
        "documents": resolve("Documents"),
        "pictures": resolve("Pictures"),
        "music": resolve("Music"),
        "videos": resolve("Videos"),
    }
    # Any drive letter ("c drive", "d drive", "e drive"...) opens that
    # drive in File Explorer / This PC - not just C and D, so USB drives
    # and other partitions work too.
    for letter in "abcdefghijklmnopqrstuvwxyz":
        shortcuts[f"{letter} drive"] = f"{letter.upper()}:\\"
    return shortcuts


def out(ok, **extra):
    result = {"ok": ok}
    result.update(extra)
    print(json.dumps(result))
    sys.exit(0)


def normalize(text):
    """lowercase + collapse spaces, used for fuzzy app-name matching"""
    return " ".join(text.strip().lower().split())


def fuzzy_lookup(target, mapping):
    """
    Try exact match first, then a loose 'contains / no-spaces' match so
    things like 'marwadiuniversity portal' still match 'marwadi university'.
    """
    target = normalize(target)
    if target in mapping:
        return target, mapping[target]

    target_nospace = target.replace(" ", "")
    for key, val in mapping.items():
        key_nospace = key.replace(" ", "")
        if key_nospace in target_nospace or target_nospace in key_nospace:
            return key, val
    return None, None


# ---------------- STATS ----------------
def get_stats():
    cpu = ram = 0
    battery = 100
    internet = "Disconnected"
    bluetooth = "Unknown"

    if psutil:
        try:
            cpu = psutil.cpu_percent(interval=0.5)
            ram = psutil.virtual_memory().percent
            batt = psutil.sensors_battery()
            battery = round(batt.percent) if batt else 100
        except Exception:
            pass
    else:
        # psutil not installed in this venv -> fall back to Windows'
        # own counters so CPU/RAM don't just sit at 0%.
        try:
            res = subprocess.run(
                ["powershell", "-Command",
                 "(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average"],
                capture_output=True, text=True, timeout=4
            )
            cpu = float(res.stdout.strip() or 0)
        except Exception:
            cpu = 0
        try:
            res = subprocess.run(
                ["powershell", "-Command",
                 "$os=Get-CimInstance Win32_OperatingSystem; "
                 "[math]::Round((($os.TotalVisibleMemorySize-$os.FreePhysicalMemory)/$os.TotalVisibleMemorySize)*100,1)"],
                capture_output=True, text=True, timeout=4
            )
            ram = float(res.stdout.strip() or 0)
        except Exception:
            ram = 0
        try:
            res = subprocess.run(
                ["powershell", "-Command",
                 "(Get-CimInstance -Class Win32_Battery | Select-Object -First 1 -ExpandProperty EstimatedChargeRemaining)"],
                capture_output=True, text=True, timeout=4
            )
            val = res.stdout.strip()
            battery = int(val) if val else 100
        except Exception:
            battery = 100

    # Internet check
    try:
        socket.create_connection(("8.8.8.8", 53), timeout=2)
        internet = "Connected"
    except OSError:
        internet = "Disconnected"

    # Bluetooth: checking the "bthserv" Windows SERVICE only tells you the
    # background service is running (almost always true) - it does NOT
    # tell you if the Bluetooth radio itself is switched on. Query the
    # actual radio state via the WinRT Radios API instead.
    try:
        ps_script = (
            "Add-Type -AssemblyName System.Runtime.WindowsRuntime; "
            "[Windows.Devices.Radios.Radio,Windows.System.Devices,ContentType=WindowsRuntime] | Out-Null; "
            "$radios = [Windows.Devices.Radios.Radio]::GetRadiosAsync().GetAwaiter().GetResult(); "
            "$bt = $radios | Where-Object { $_.Kind -eq 'Bluetooth' } | Select-Object -First 1; "
            "if ($bt) { $bt.State } else { 'NoAdapter' }"
        )
        res = subprocess.run(
            ["powershell", "-Command", ps_script],
            capture_output=True, text=True, timeout=4
        )
        state = res.stdout.strip()
        if state == "On":
            bluetooth = "On"
        elif state == "Off":
            bluetooth = "Off"
        elif state == "NoAdapter":
            bluetooth = "Not Available"
        else:
            bluetooth = "Unknown"
    except Exception:
        bluetooth = "Unknown"

    out(True, cpu=round(cpu, 1), ram=round(ram, 1), battery=battery,
        internet=internet, bluetooth=bluetooth)


# ---------------- APP OPEN/CLOSE ----------------
def _launch(exe):
    """Try several strategies to actually start a desktop program."""
    # 0) known-fixed system binary path, if we have one for this app.
    #    Bypasses os.startfile()'s registry lookup entirely, so nothing
    #    else registered under the same App Paths key can hijack it
    #    (this is what fixes "open notepad" launching the wrong program).
    for candidate in SYSTEM_BINARY_PATHS.get(exe.lower(), []):
        if os.path.exists(candidate):
            try:
                subprocess.Popen([candidate])
                return True
            except Exception:
                continue

    # 1) os.startfile - consults the Windows "App Paths" registry, which
    #    is how "chrome" / "msedge" resolve even when NOT on PATH. This
    #    is usually the fix for edge/chrome refusing to open.
    try:
        os.startfile(exe)
        return True
    except Exception:
        pass

    # 2) plain Popen - works if exe is on PATH (notepad, calc, mspaint...)
    try:
        subprocess.Popen(exe)
        return True
    except Exception:
        pass

    # 3) known install locations (Office / Chrome / Edge)
    for candidate in COMMON_PATHS.get(exe.lower(), []):
        if os.path.exists(candidate):
            try:
                subprocess.Popen([candidate])
                return True
            except Exception:
                continue

    # 4) last resort: glob-search Program Files for the exe (handles
    #    Office version folders like Office15/Office16/Office365)
    exe_name = exe if exe.lower().endswith(".exe") else exe + ".exe"
    for base in [r"C:\Program Files", r"C:\Program Files (x86)"]:
        matches = glob.glob(os.path.join(base, "**", exe_name), recursive=True)
        if matches:
            try:
                subprocess.Popen([matches[0]])
                return True
            except Exception:
                continue

    return False


def app_action(action, target):
    folders = folder_shortcuts()

    if action == "open":
        # 1) folder shortcut (desktop, downloads, c drive...)
        key, path = fuzzy_lookup(target, folders)
        if path:
            try:
                os.startfile(path)
                return out(True, matched=key)
            except Exception as e:
                return out(False, error=str(e))

        # 2) desktop app
        key, exe = fuzzy_lookup(target, DESKTOP_APPS)
        if exe:
            if exe.endswith(":"):
                try:
                    os.startfile(exe)
                    return out(True, matched=key)
                except Exception as e:
                    return out(False, error=str(e))
            if _launch(exe):
                return out(True, matched=key)
            return out(False, error=f"could not launch {exe}")

        return out(False, error="app not found")

    elif action == "close":
        # 1) Explicitly-unclosable apps (Settings, Microsoft Store) -> honest
        #    "not supported" answer instead of a generic failure.
        target_norm = normalize(target)
        target_nospace = target_norm.replace(" ", "")
        for bad in NOT_CLOSABLE_APPS:
            if bad.replace(" ", "") in target_nospace or target_nospace in bad.replace(" ", ""):
                return out(False, error="not_supported")

        # 2) Chrome is a SPECIAL case: this Jarvis dashboard itself runs
        #    inside a Chrome tab, so taskkill /IM chrome.exe /F would kill
        #    the whole browser process tree - including the Jarvis tab -
        #    and force-killing Chrome's GPU/compositor process mid-frame is
        #    also what was causing the black-screen / 3-finger-swipe /
        #    volume-key glitches (it briefly destabilizes the Windows
        #    DWM compositor, which owns those same overlays). So instead of
        #    taskkill, gracefully close only OTHER Chrome windows and leave
        #    the Jarvis window alone.
        key, proc_names = fuzzy_lookup(target, CLOSE_PROCESS_MAP)
        if key in ("chrome", "google chrome"):
            closed_titles = close_other_chrome_windows()
            if closed_titles:
                return out(True, matched=key, closed=closed_titles)
            return out(False, error="process not running or could not be closed")

        if not proc_names:
            return out(False, error="app not found")

        closed_any = False
        for proc_name in proc_names:
            try:
                res = subprocess.run(["taskkill", "/IM", proc_name, "/F"],
                                      capture_output=True, text=True, timeout=5)
                if res.returncode == 0:
                    closed_any = True
            except Exception:
                continue

        if closed_any:
            return out(True, matched=key)
        return out(False, error="process not running or could not be closed")

    return out(False, error="unknown action")


# Personal folders that should never be wiped out by a voice/typed
# command, however it's phrased - protects against "delete desktop"
# (which the user tried while testing) accidentally nuking everything
# on their actual Desktop. Compared as normalized, case-insensitive
# absolute paths.
def _protected_paths():
    home = os.path.normcase(os.path.normpath(os.path.expanduser("~")))
    protected = {home}
    for name in ("desktop", "documents", "downloads", "pictures", "music", "videos"):
        for path in folder_shortcuts_paths_for(name):
            if path:
                protected.add(os.path.normcase(os.path.normpath(path)))
    return protected


def folder_shortcuts_paths_for(name):
    """All plausible locations for one known folder (Known-Folder API
    result + the OneDrive guess + the plain default), so the protection
    list covers wherever it might actually be - not just one guess."""
    home = os.path.expanduser("~")
    onedrive = os.environ.get("OneDrive") or os.environ.get("OneDriveConsumer") or os.path.join(home, "OneDrive")
    title = name.capitalize()
    return [
        _known_folder_path(name.lower()),
        os.path.join(onedrive, title),
        os.path.join(home, title),
    ]


# ---------------- PATH CREATE/DELETE ----------------
def path_action(action, path):
    try:
        if action == "create":
            # If path has a file extension -> create file, else -> folder
            _, ext = os.path.splitext(path)
            if ext:
                parent = os.path.dirname(path)
                if parent:
                    os.makedirs(parent, exist_ok=True)
                with open(path, "w") as f:
                    f.write("")
                os.startfile(parent if parent else ".")
                return out(True, kind="file")
            else:
                os.makedirs(path, exist_ok=True)
                os.startfile(path)
                return out(True, kind="folder")

        elif action == "delete":
            norm_path = os.path.normcase(os.path.normpath(path.rstrip("\\/")))
            if norm_path in _protected_paths():
                return out(
                    False,
                    error="refused: this is one of your main personal folders (or your home "
                          "folder) - Jarvis won't delete it wholesale. Delete a specific file "
                          "or subfolder inside it instead.",
                )

            if os.path.isfile(path):
                parent = os.path.dirname(path)
                _remove_with_retry(lambda: os.remove(path))
                if parent:
                    os.startfile(parent)
                return out(True, kind="file")
            elif os.path.isdir(path):
                if os.path.islink(path) or os.path.ismount(path):
                    # A junction/reparse point (e.g. a redirected known
                    # folder) - shutil.rmtree() on some Python versions
                    # would recurse INTO the real target and delete its
                    # contents instead of just removing the link. Only
                    # ever remove the link itself here, never recurse.
                    os.rmdir(path)
                    return out(True, kind="folder")
                parent = os.path.dirname(path.rstrip("\\/"))
                _remove_with_retry(lambda: shutil.rmtree(path))
                if parent:
                    os.startfile(parent)
                return out(True, kind="folder")
            else:
                return out(False, error="path not found")

    except Exception as e:
        return out(False, error=f"{type(e).__name__}: {e}")

    return out(False, error="unknown action")


def _remove_with_retry(fn, attempts=4, delay=0.35):
    """A file/folder just created or shown in Explorer can briefly be
    locked by Explorer's own preview/indexing or antivirus real-time
    scanning - retrying a few times with a short pause clears most of
    these transient Windows file locks instead of failing immediately."""
    last_err = None
    for i in range(attempts):
        try:
            fn()
            return
        except PermissionError as e:
            last_err = e
            time.sleep(delay)
    raise last_err


# ---------------- SEARCH ----------------
def search_action(query):
    try:
        url = "https://www.google.com/search?q=" + query.replace(" ", "+")
        try:
            webbrowser.get("chrome").open_new_tab(url)
        except Exception:
            webbrowser.open_new_tab(url)
        return out(True)
    except Exception as e:
        return out(False, error=str(e))


# ---------------- PLAY SONG (YouTube) ----------------
def play_action(song):
    try:
        url = "https://www.youtube.com/results?search_query=" + song.replace(" ", "+")
        try:
            webbrowser.get("chrome").open_new_tab(url)
        except Exception:
            webbrowser.open_new_tab(url)
        return out(True)
    except Exception as e:
        return out(False, error=str(e))


# ---------------- MAIN ----------------
if __name__ == "__main__":
    args = sys.argv[1:]
    if not args:
        out(False, error="no action given")

    action_type = args[0]

    if action_type == "stats":
        get_stats()

    elif action_type == "app" and len(args) >= 3:
        app_action(args[1], " ".join(args[2:]))

    elif action_type == "path" and len(args) >= 3:
        path_action(args[1], " ".join(args[2:]))

    elif action_type == "search" and len(args) >= 2:
        search_action(" ".join(args[1:]))

    elif action_type == "play" and len(args) >= 2:
        play_action(" ".join(args[1:]))

    else:
        out(False, error="invalid arguments")
