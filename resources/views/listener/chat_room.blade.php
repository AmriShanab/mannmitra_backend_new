<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with {{ $ticket->user->name }} | MannMitra</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/chat_room.css') }}">

    <style>

    </style>
</head>

<body>

    <header class="chat-header">
        <div class="user-info">
            <a href="{{ route('listener.dashboard') }}" class="text-secondary me-3">
                <i class="fas fa-chevron-left fa-lg"></i>
            </a>
            <div class="avatar-circle">
                {{ substr($ticket->user->name ?? 'G', 0, 1) }}
            </div>
            <div>
                <h6 class="m-0 fw-bold">{{ $ticket->user->name ?? 'Guest User' }}</h6>
                <small class="text-success"><i class="fas fa-circle" style="font-size: 7px;"></i> Active Session</small>
            </div>
        </div>
        <div>
            <span class="badge rounded-pill bg-light text-dark border px-3 py-2">
                ID: {{ $ticket->ticket_id }}
            </span>
            <button class="btn btn-danger btn-sm ms-3 rounded-pill px-3">End Session</button>
        </div>
    </header>

    <main class="chat-container" id="chat-messages">
        <div class="text-center my-4">
            <span class="text-muted small px-3 py-1 bg-white border rounded-pill">
                Conversation started at {{ $ticket->created_at->format('h:i A') }}
            </span>
        </div>

        <div class="message-wrapper theirs">
            <div class="message-bubble">
                <b>Initial Request:</b><br>
                {{ $ticket->subject }}
            </div>
            <span class="message-time">System Note</span>
        </div>

    </main>

    <footer class="chat-footer">
        <form id="chat-form" class="w-100">
            <div class="input-group-custom">
                <input type="text" id="message-input" class="chat-input" placeholder="Write a supportive message..."
                    autocomplete="off">
                <button type="submit" class="btn-send shadow-sm">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </footer>

    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script>
        // 1. Data Setup
        const TICKET_ID = "{{ $ticket->ticket_id }}";
        const USER_NAME = "{{ Auth::user()->name }}";
        const USER_ID = "{{ Auth::id() }}";

        // 2. Elements Setup
        const messagesContainer = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const endBtn = document.querySelector('.btn-danger');

        // 3. Socket Connection (Using your Server IP)
        const socket = io("http://31.97.232.145:3000");
        socket.emit('join_room', TICKET_ID);

        // 4. Initial Load: Fetch History from Database
        async function loadChatHistory() {
            try {
                // Updated path to match routes/api.php
                const response = await fetch(`/api/v1/listener/history/${TICKET_ID}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include'
                });
                const result = await response.json();

                if (result.status && result.data) {
                    result.data.forEach(msg => {
                        const type = (msg.sender_id == USER_ID) ? 'mine' : 'theirs';
                        const time = new Date(msg.created_at).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        addMessageToUI(msg.message, type, time);
                    });
                }
            } catch (error) {
                console.error("Error loading chat history:", error);
            }
        }

        // 5. Handle Sending Messages
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const messageText = messageInput.value.trim();

            if (messageText) {
                const timestamp = new Date().toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // A. Update local UI immediately for better UX
                addMessageToUI(messageText, 'mine', timestamp);

                // B. Emit to Socket Server for real-time delivery
                socket.emit('send_message', {
                    room: TICKET_ID,
                    message: messageText,
                    sender: USER_NAME,
                    sender_id: USER_ID,
                    timestamp: timestamp
                });

                // C. AJAX Call to Laravel to store the message
                try {
                    await fetch('/api/v1/listener/messages', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            ticket_id: TICKET_ID,
                            message: messageText,
                            sender_id: USER_ID
                        })
                    });
                } catch (err) {
                    console.error("Database save failed:", err);
                }

                messageInput.value = '';
            }
        });

        endBtn.addEventListener('click', async() => {
            if(!confirm("Are you sure you want to end this session?")) {
                return;
            }

            try {
                const response = await fetch('/api/v1/listener/end-session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'    
                    }, 
                    credentials: 'include',
                    body: JSON.stringify({
                        ticket_id:TICKET_ID
                    })
                });

                const result = await response.json();
                if(result.status) {
                    alert("Session ended successfully.");
                    window.location.href = "{{ route('listener.dashboard') }}";
                }else {
                    alert("Failed to end session: " + result.message);
                }
            } catch (error) {
                console.error("Error ending session:", error);
                alert("An error occurred while ending the session.");
            }
        });

        socket.on('session_ended', (data) => {
            messageInput.disabled = true;
            messageInput.placeholder = "This session has been ended.";
            document.querySelector('.btn-send').disabled = true;
            alert("This session has been ended by the listener.");
        })

        // 6. Listen for Incoming Messages (Real-time)
        socket.on('receive_message', (data) => {
            if (data.sender_id != USER_ID) {
                addMessageToUI(data.message, 'theirs', data.timestamp);
            }
        });

        // 7. UI Helpers
        function addMessageToUI(text, type, time = 'Just Now') {
            const wrapper = document.createElement('div');
            wrapper.className = `message-wrapper ${type} animate__animated animate__fadeInUp animate__faster`;

            wrapper.innerHTML = `
            <div class="message-bubble">${text}</div>
            <span class="message-time">${time}</span>
        `;

            messagesContainer.appendChild(wrapper);
            scrollToBottom();
        }

        function scrollToBottom() {
            messagesContainer.scrollTo({
                top: messagesContainer.scrollHeight,
                behavior: 'smooth'
            });
        }

        // Load history when page opens
        window.onload = () => {
            loadChatHistory();
        };
    </script>
</body>

</html>
