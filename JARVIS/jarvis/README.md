# J.A.R.V.I.S — Setup Guide (Hinglish)

## ⚠️ Is update me kya-kya fix hua + tumhe kya karna hai (zaroor padho)

| Problem jo bataya tha | Fix |
|---|---|
| CPU/RAM/Battery hamesha 0% | `psutil` venv me installed hi nahi tha → neeche **step A** follow karo |
| Bluetooth hamesha "On" dikhata tha | Ab actual radio state check hota hai (Windows Radios API se), sirf background service nahi |
| "close whatsapp" pura Chrome hi band kar deta tha | Ab gmail/whatsapp/youtube/drive/classroom/jiohotstar/instagram/marwadi jaise web-apps **browser tab ke through** khulte-band hote hai (python/taskkill se nahi), toh sirf wahi tab band hoga, pura browser nahi. Chrome me localhost ke liye popups allow karna padega — **step B** dekho |
| Chrome/Edge/Word/Excel/PowerPoint/Access/Calculator open-close na hona | Launch logic robust kiya (App-Paths + common install paths + Office folder search). Calculator close bhi fix hua (Windows 10/11 me asli process `CalculatorApp.exe` hota hai, `calculator.exe` nahi) |
| "Good Afternoon" raat ko bhi aata tha | Server timezone UTC pe default tha → ab `Asia/Kolkata` set kar diya |
| Charts button kuch nahi karta tha | Modal open karna hi bhool gaya tha code me — fix ho gaya |
| History/Memory modal chhota tha, scroll karne pe close (×) gayab ho jaata tha | Modal ab bada hai, title-bar (with **minimize / maximize / close**) hamesha upar fixed rehta hai, sirf table scroll hoti hai |
| Minimize/maximize/close chahiye tha | History, Memory, Charts — teeno modals me ab window-style controls hai; minimize karne pe niche ek chip me dikhta hai, click karke wapas khol sakte ho |
| Mic sirf ek command ke baad band ho jaata tha | Mic button ab **toggle** hai — ek baar dabao to continuous sunta rahega jab tak khud dobara na dabao |
| Emoji bhi bola jaata tha (jaise "waving hand") | Ab sirf text bola jaata hai, emoji sirf screen pe dikhte hai |
| Saari lines ek saath likhkar baad me bolta tha | Ab ek line likhta hai → wahi line bolta hai → khatam hone pe agli line — sync me |
| STOP → START karne pe dobara "wake up jarvis" bolna padta tha | Ab conversation state save rehta hai (sessionStorage me), sirf EXIT/LOGOUT pe reset hota hai |
| "tell me my name" bola to naam nahi dikhta tha | Ab seedha "Your name is Miral" bolta hai |
| Memory store karke "what is my X" pucho to samajhta nahi tha | Ab memory se seedha value nikal ke batata hai |
| "calculate 544 - 45" kaam nahi karta tha | "calculate"/"calc" prefix wale commands ab handle hote hai |
| File banane pe "file/folder create kar diya" bolta tha (chahe file ho ya folder) | Ab exactly "file create kar diya hai" ya "folder create kar diya hai" bolta hai, jo bhi actually bana ho |
| "can you open chrome" jaisi natural language samajh nahi aati thi | Please/can you/could you/kindly jaise filler words ab automatically hat jaate hai command match karne se pehle |
| "open marwadiuniversity portal" match nahi hota tha | Ab loose/fuzzy matching hai (spaces ignore karke bhi match kar leta hai) |

### Step A — psutil install karo (CPU/RAM/Battery ke liye zaroori)
```
cd C:\laragon\www\jarvis-assistant
venv\Scripts\pip install psutil
```

### Step B — Chrome me localhost ke liye popups allow karo
Chrome address bar → lock/tune icon → **Site settings** → **Pop-ups and redirects** →
**Allow**. Isse "open gmail/whatsapp/youtube..." wale tabs bina block hue khulenge.

### Step C — Database name check karo
`config/db.php` me `$DB_NAME = "jarvis_new"` hai. `database.sql` bhi ab isi naam se
database banata hai. Agar tumhara phpMyAdmin me pehle se `jarvis_db` naam ka
database hai, to ya to usko rename karo `jarvis_new` me, ya `config/db.php` me
naam apne existing database jaisa kar do.


## 1. Laragon setup
1. Laragon install karo (agar nahi hai) → https://laragon.org/download/
2. Laragon start karo → **Apache** aur **MySQL** dono ON karo.
3. Ye poora `jarvis-assistant` folder copy karke daalo:
   `C:\laragon\www\jarvis-assistant`
4. Browser me kholo: `http://localhost/jarvis-assistant`

## 2. Database import
1. Laragon me "Database" button click karo → phpMyAdmin khulega.
2. Left side "Import" ya naya query window me `database.sql` ka content paste karke run karo.
3. Ye `jarvis_db` database + `users`, `history`, `memory` tables bana dega.

## 3. Python setup
1. Python already installed hona chahiye. Check karo cmd me: `python --version`
2. Terminal kholo `jarvis-assistant/python` folder me aur run karo:
   ```
   pip install -r requirements.txt
   ```
3. `api/command.php` aur `api/system_stats.php` dono me ye line dhundo:
   ```php
   $PYTHON_PATH = "python";
   ```
   Agar `python` command directly kaam na kare to `where python` chalao aur uska
   full path yaha daal do, jaise:
   ```php
   $PYTHON_PATH = '"C:\\Users\\min18\\AppData\\Local\\Programs\\Python\\Python312\\python.exe"';
   ```

## 4. Weather API key
1. Free account banao: https://openweathermap.org/api
2. API key copy karo.
3. `includes/functions.php` file me ye line dhundo aur apni key daalo:
   ```php
   $apiKey = "f1aca0280c083ca259e825e6082eb15";
   ```

## 5. Customize app/folder paths
- `python/system_control.py` file ke top pe `DESKTOP_APPS`, `CLOSE_PROCESS_MAP`,
  `folder_shortcuts()` dictionaries hai — real Windows programs (word, excel,
  notepad, chrome.exe khud, etc.) yahi se control hote hai.
- **Web apps jo browser tab me khulte hai** (gmail, whatsapp, youtube, drive,
  classroom, jiohotstar, instagram, Marwadi University portal) ab
  `api/command.php` ke `$WEB_APPS` array me hai — Marwadi University ka
  asli URL yahi pe update karna (abhi placeholder hai).

## 6. Kaise chalayein
1. `http://localhost/jarvis-assistant` kholo → Register karo (name, username, password).
2. Login hote hi Dashboard khulega.
3. **START** button dabao → "Jarvis ready. Say wake up Jarvis" aayega.
4. Type karo ya 🎤 dabakar bolo: `wake up jarvis` → greeting sequence chalu hoga.
5. Commands try karo:
   - `open chrome` / `close chrome`
   - `open gmail`, `open youtube`, `open whatsapp`
   - `open downloads`, `create C:\Users\min18\Downloads\B.C.A\MIN`
   - `weather norway`
   - `search what is ai`
   - `play believer imagine dragons`
   - `remember my favorite color is black and white`
   - `what is your full form`
   - `who created you`
   - `exit`
6. **HISTORY**, **MEMORY**, **CHARTS** buttons se apna data dekho.
7. **LOGOUT** se logout karo (data safe rehta hai, next login pe wahi history/memory milega).

## 7. Errors aaye to
- PHP errors: `error_log.txt` (project root me auto-create hoga) aur Laragon ka
  `C:\laragon\bin\apache\...\logs\error.log` check karo.
- Python errors: cmd me manually chalake dekho, jaise:
  `python python/system_control.py stats`
- Jo bhi error aaye, mujhe wahi paste kar dena — main us file ka **poora updated
  code** replace karke wapas de dunga.

## Folder Structure
```
jarvis-assistant/
├── index.php
├── dashboard.php
├── database.sql
├── config/db.php
├── includes/auth_check.php, functions.php
├── auth/register.php, login.php, logout.php
├── api/command.php, weather.php, history.php, memory.php, system_stats.php
├── python/system_control.py, requirements.txt
└── assets/css/style.css, assets/js/main.js, speech.js, charts.js
```
