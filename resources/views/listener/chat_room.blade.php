<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with {{ $ticket->user->name ?? 'User' }} | MannMitra</title>

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    {{-- Custom CSS --}}
    <link rel="stylesheet" href="{{ asset('css/chat_room.css') }}">
</head>
<body>

<div class="chat-layout">

    {{-- ── HEADER ────────────────────────────────────────────── --}}
    <header class="chat-header">
        <div class="chat-header-left">
            <a href="{{ route('listener.dashboard') }}" class="btn-back" title="Back to dashboard">
                <i class="fas fa-chevron-left"></i>
            </a>

            <div class="header-avatar">
                {{ strtoupper(substr($ticket->user->name ?? 'G', 0, 1)) }}
            </div>

            <div>
                <div class="header-user-name">{{ $ticket->user->name ?? 'Guest User' }}</div>
                <div class="header-user-status">Active session</div>
            </div>
        </div>

        <div class="chat-header-right">
            <span class="ticket-badge">ID: {{ $ticket->ticket_id }}</span>

            {{-- Theme Toggle --}}
            <button class="btn-theme-toggle" id="themeToggle" title="Toggle theme" aria-label="Toggle dark/light mode">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <button class="btn-end-session" id="endSessionBtn">End session</button>
        </div>
    </header>

    {{-- ── BODY: Messages + AI Sidebar ──────────────────────── --}}
    <div class="chat-body">

        {{-- Messages Column --}}
        <div class="chat-messages" id="chat-messages">

            <div class="system-note">
                <span>Conversation started at {{ $ticket->created_at->format('h:i A') }}</span>
            </div>

            {{-- Initial request bubble --}}
            <div class="message-wrapper theirs">
                <div class="message-bubble">
                    <strong>Initial request:</strong><br>
                    {{ $ticket->subject }}
                </div>
                <span class="message-time">System note</span>
            </div>

            {{-- Dynamically loaded history will appear here via JS --}}

        </div>

        {{-- AI Suggestions Sidebar --}}
        {{-- <aside class="ai-sidebar" id="ai-sidebar" aria-label="AI response suggestions">

            <div class="ai-sidebar-header">
                <i class="fas fa-wand-magic-sparkles" style="font-size:13px; color: var(--clr-brand);" aria-hidden="true"></i>
                <span>Suggestions</span>
                <span class="ai-label">AI</span>
            </div>

            <div class="ai-sidebar-body" id="ai-sidebar-body">

                
                <div class="ai-loading" id="ai-loading">
                    <div class="ai-spinner"></div>
                    <span>Generating suggestions…</span>
                </div>

                
                <div id="ai-suggestions-list" style="display:none; display:flex; flex-direction:column; gap:8px;">
                    
                </div>

            </div>

            <div class="ai-disclaimer">
                <p>Suggestions are AI-generated. Always respond in your own voice.</p>
            </div>

        </aside> --}}

    </div>{{-- end .chat-body --}}

    {{-- ── FOOTER / INPUT ────────────────────────────────────── --}}
    <footer class="chat-footer">
        <div class="chat-input-row">
            <input
                type="text"
                class="chat-input"
                id="message-input"
                placeholder="Write a supportive message…"
                autocomplete="off"
                aria-label="Type your message"
            >
            <button type="button" class="btn-send" id="send-btn" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="input-hint" id="input-hint">Suggestions update after each message</div>
    </footer>

</div>{{-- end .chat-layout --}}

{{-- Toast --}}
<div class="toast-msg" id="toast" aria-live="polite"></div>


{{-- ── SCRIPTS ─────────────────────────────────────────────── --}}
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
// ── Config ──────────────────────────────────────────────────
const TICKET_ID   = @json($ticket->ticket_id);
const USER_NAME   = @json(Auth::user()->name);
const USER_ID     = @json((string) Auth::id());
const SOCKET_URL  = @json($rt['socket_url']);
const SOCKET_TOKEN = @json($rt['token']);
const CSRF_TOKEN  = "{{ csrf_token() }}";

// ── DOM Refs ─────────────────────────────────────────────────
const messagesEl   = document.getElementById('chat-messages');
const messageInput = document.getElementById('message-input');
const sendBtn      = document.getElementById('send-btn');
const endBtn       = document.getElementById('endSessionBtn');
const inputHint    = document.getElementById('input-hint');
const aiLoading    = document.getElementById('ai-loading');
const aiList       = document.getElementById('ai-suggestions-list');
const toast        = document.getElementById('toast');

// ── Socket Setup ─────────────────────────────────────────────
const socket = io(SOCKET_URL, { auth: { token: SOCKET_TOKEN } });
// (Re)join on every connect so a dropped connection recovers by itself.
socket.on('connect', () => socket.emit('join_room', TICKET_ID));
socket.on('connect_error', (err) => {
    if (err.message === 'unauthorized') showToast("Session expired. Please reload this page.");
});

// Incoming messages from user (real-time)
socket.on('receive_message', (data) => {
    if (String(data.sender_id) !== String(USER_ID)) {
        addMessage(data.message, 'theirs', data.timestamp || 'Just now');
        // Fetch fresh AI suggestions when user sends a new message
        fetchAiSuggestions(data.message);
    }
});

// Session ended by user side
socket.on('session_ended', () => {
    messageInput.disabled = true;
    messageInput.placeholder = "This session has ended.";
    sendBtn.disabled = true;
    setHint("Session ended.", false);
    showToast("The user has left the session.");
});

// ── Load Chat History ─────────────────────────────────────────
async function loadChatHistory() {
    try {
        const res = await fetch(`/api/v1/listener/history/${TICKET_ID}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        });
        const result = await res.json();

        if (result.status && result.data && result.data.length > 0) {
            result.data.forEach(msg => {
                const type = (String(msg.sender_id) === String(USER_ID)) ? 'mine' : 'theirs';
                const time = new Date(msg.created_at).toLocaleTimeString([], {
                    hour: '2-digit', minute: '2-digit'
                });
                addMessage(msg.message, type, time);
            });

            // Generate suggestions based on last user message
            const lastUserMsg = result.data
                .filter(m => String(m.sender_id) !== String(USER_ID))
                .pop();
            if (lastUserMsg) {
                fetchAiSuggestions(lastUserMsg.message);
            } else {
                fetchAiSuggestions("Hello, I need someone to talk to.");
            }
        } else {
            // No history — show default suggestions
            fetchAiSuggestions("Hello, I need someone to talk to.");
        }
    } catch (err) {
        console.error("History load error:", err);
        fetchAiSuggestions("Hello, I need someone to talk to.");
    }
}

// ── Send Message ──────────────────────────────────────────────
async function sendMessage() {
    const text = messageInput.value.trim();
    if (!text || messageInput.disabled) return;

    const timestamp = new Date().toLocaleTimeString([], {
        hour: '2-digit', minute: '2-digit'
    });

    // 1. Show in UI immediately
    addMessage(text, 'mine', timestamp);
    messageInput.value = '';
    setHint("Suggestions update after each message", false);

    // 2. Persist first; only broadcast once the server accepted the message.
    try {
        const res = await fetch('/api/v1/listener/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            credentials: 'include',
            body: JSON.stringify({ ticket_id: TICKET_ID, message: text })
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        socket.emit('send_message', {
            room: TICKET_ID,
            message: text,
            sender: USER_NAME,
            sender_id: USER_ID,
            timestamp: timestamp
        });
    } catch (err) {
        console.error("Send message error:", err);
        showToast("Message could not be sent. Please try again.");
    }
}

// ── End Session ───────────────────────────────────────────────
endBtn.addEventListener('click', async () => {
    if (!confirm("End this session? The user will be notified.")) return;

    try {
        const res = await fetch('/api/v1/listener/end-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            credentials: 'include',
            body: JSON.stringify({ ticket_id: TICKET_ID })
        });
        const result = await res.json();

        if (result.status) {
            socket.emit('end_session', { room: TICKET_ID });
            showToast("Session ended.");
            setTimeout(() => {
                window.location.href = "{{ route('listener.dashboard') }}";
            }, 1200);
        } else {
            alert("Could not end session: " + (result.message || "Unknown error"));
        }
    } catch (err) {
        console.error("End session error:", err);
        alert("An error occurred. Please try again.");
    }
});

// ── Send on Enter / Button ────────────────────────────────────
messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

sendBtn.addEventListener('click', sendMessage);

// ── Add Message to UI ─────────────────────────────────────────
function addMessage(text, type, time = 'Just now') {
    const wrapper = document.createElement('div');
    wrapper.className = `message-wrapper ${type}`;
    wrapper.innerHTML = `
        <div class="message-bubble">${escapeHtml(text)}</div>
        <span class="message-time">${time}</span>
    `;
    messagesEl.appendChild(wrapper);
    scrollBottom();
}

function scrollBottom() {
    messagesEl.scrollTo({ top: messagesEl.scrollHeight, behavior: 'smooth' });
}

function escapeHtml(str) {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

// ── AI Suggestions ────────────────────────────────────────────
// Debounce: only fetch if no new message arrives within 400ms
let aiDebounceTimer = null;

function fetchAiSuggestions(lastUserMessage) {
    clearTimeout(aiDebounceTimer);

    // Show loading state
    aiLoading.style.display = 'flex';
    aiList.style.display = 'none';
    aiList.innerHTML = '';
    setHint("Generating suggestions…", true);

    aiDebounceTimer = setTimeout(async () => {
        try {
            const response = await fetch('/api/v1/listener/ai-suggestions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                credentials: 'include',
                body: JSON.stringify({
                    ticket_id: TICKET_ID,
                    last_user_message: lastUserMessage
                })
            });

            const result = await response.json();

            if (result.status && result.suggestions && result.suggestions.length > 0) {
                renderSuggestions(result.suggestions);
            } else {
                renderSuggestions([
                    "I hear you. Thank you for sharing that with me.",
                    "That sounds really difficult. Can you tell me more?",
                    "You're not alone in this. I'm here."
                ]);
            }
        } catch (err) {
            console.error("AI suggestion error:", err);
            renderSuggestions([
                "I hear you. Thank you for sharing that with me.",
                "That sounds really difficult. Can you tell me more?",
                "You're not alone in this. I'm here."
            ]);
        }
    }, 400);
}

function renderSuggestions(suggestions) {
    aiLoading.style.display = 'none';
    aiList.innerHTML = '';
    aiList.style.display = 'flex';

    suggestions.forEach((text) => {
        const card = document.createElement('div');
        card.className = 'suggestion-card';
        card.innerHTML = `
            <p class="suggestion-text">${escapeHtml(text)}</p>
            <div class="suggestion-use">
                <i class="fas fa-level-down-alt"></i> Use this
            </div>
        `;
        card.addEventListener('click', () => {
            messageInput.value = text;
            messageInput.focus();
            setHint("Suggestion loaded — edit freely before sending", true);
            showToast("Suggestion loaded into the input");
        });
        aiList.appendChild(card);
    });

    setHint("Suggestions update after each message", false);
}

// ── Hint text helper ──────────────────────────────────────────
function setHint(text, active) {
    inputHint.textContent = text;
    inputHint.classList.toggle('active', active);
}

// ── Toast helper ──────────────────────────────────────────────
let toastTimer;
function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
}

// ── Theme Toggle ──────────────────────────────────────────────
(function () {
    const html    = document.documentElement;
    const btn     = document.getElementById('themeToggle');
    const icon    = document.getElementById('themeIcon');
    const STORAGE = 'mm_theme';

    const saved = localStorage.getItem(STORAGE) || 'light';
    html.setAttribute('data-theme', saved);
    updateIcon(saved);

    btn.addEventListener('click', function () {
        const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem(STORAGE, next);
        updateIcon(next);
    });

    function updateIcon(theme) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
})();

// ── Bootstrap ─────────────────────────────────────────────────
window.addEventListener('load', loadChatHistory);
</script>

</body>
</html>