// ============================================
// speech.js - Web Speech API (browser built-in)
// Voice input (SpeechRecognition) + Voice output (SpeechSynthesis)
// Works best in Chrome. No extra Python/server setup needed.
// ============================================

const micBtn = document.getElementById("mic-btn");
let recognition = null;
let listening = false;
// When true, mic keeps listening for the NEXT command automatically
// after each result, instead of switching off after a single command.
// User toggles this by clicking the mic button on/off.
let continuousMode = false;

const SpeechRecognitionAPI = window.SpeechRecognition || window.webkitSpeechRecognition;

if (SpeechRecognitionAPI) {
    recognition = new SpeechRecognitionAPI();
    recognition.lang = "en-IN";
    recognition.continuous = false;
    recognition.interimResults = false;

    recognition.onstart = () => {
        listening = true;
        micBtn.classList.add("recording");
        if (window.setOrbState) window.setOrbState("listening");
    };

    recognition.onend = () => {
        listening = false;
        micBtn.classList.remove("recording");
        if (window.setOrbState) window.setOrbState("idle");
        // Auto-restart so the person can keep giving commands without
        // pressing the mic again each time, until they turn it off.
        if (continuousMode) {
            setTimeout(() => {
                if (continuousMode) {
                    try { recognition.start(); } catch (e) {}
                }
            }, 400);
        }
    };

    recognition.onerror = (event) => {
        listening = false;
        micBtn.classList.remove("recording");
        // "no-speech" / "aborted" just means silence - keep listening in
        // continuous mode instead of giving up.
        if (continuousMode && (event.error === "no-speech" || event.error === "aborted")) {
            setTimeout(() => {
                if (continuousMode) {
                    try { recognition.start(); } catch (e) {}
                }
            }, 400);
        } else if (event.error === "not-allowed" || event.error === "service-not-allowed") {
            continuousMode = false;
            micBtn.classList.remove("always-on");
        }
    };

    recognition.onresult = (event) => {
        const text = event.results[0][0].transcript;
        document.getElementById("command-input").value = text;
        // auto-send the recognized command
        if (typeof sendCommand === "function") {
            sendCommand(text);
        }
    };

    micBtn.addEventListener("click", () => {
        continuousMode = !continuousMode;
        micBtn.classList.toggle("always-on", continuousMode);
        if (continuousMode) {
            if (!listening) {
                try { recognition.start(); } catch (e) {}
            }
        } else {
            if (listening) recognition.stop();
        }
    });
} else {
    micBtn.disabled = true;
    micBtn.title = "Speech recognition not supported in this browser (use Chrome)";
}

// ---------- Text to Speech (Jarvis voice) ----------
// Strips emoji before speaking (screen text keeps them) so Jarvis
// doesn't read out things like "waving hand" / "party popper" etc.
// Accepts an optional onDone callback so callers can chain lines
// (write line -> speak line -> once finished, move to next line).
const EMOJI_REGEX = /[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{2190}-\u{21FF}\u{2B00}-\u{2BFF}\u{FE0F}]/gu;

window.jarvisSpeak = function (text, onDone) {
    const cleanText = text.replace(EMOJI_REGEX, "").replace(/\s{2,}/g, " ").trim();

    if (!window.speechSynthesis || !cleanText) {
        if (typeof onDone === "function") onDone();
        return;
    }

    // Pause mic auto-restart while Jarvis is talking, so it doesn't try
    // to "hear" its own voice as a command.
    const wasListening = listening;
    if (wasListening) recognition.stop();

    const utterance = new SpeechSynthesisUtterance(cleanText);
    utterance.lang = "en-IN";
    utterance.rate = 1;
    utterance.pitch = 1;

    if (window.setOrbState) window.setOrbState("speaking");

    utterance.onend = () => {
        if (window.setOrbState) window.setOrbState("idle");
        if (continuousMode) {
            setTimeout(() => {
                if (continuousMode && !listening) {
                    try { recognition.start(); } catch (e) {}
                }
            }, 300);
        }
        if (typeof onDone === "function") onDone();
    };
    utterance.onerror = () => {
        if (window.setOrbState) window.setOrbState("idle");
        if (typeof onDone === "function") onDone();
    };

    window.speechSynthesis.speak(utterance);
};
