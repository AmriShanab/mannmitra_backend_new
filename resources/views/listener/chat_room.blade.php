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
        const TICKET_ID = "{{ $ticket->ticket_id }}"; 
        const USER_NAME = "{{ Auth::user()->name }}"; 

        const messagesContainer = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');

        const socket = io("http://localhost:3000");
        socket.emit('join_room', TICKET_ID);

        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const messageText = messageInput.value.trim();

            if (messageText) {
                addMessageToUI(messageText, 'mine');
                socket.emit('send_message', {
                    room: TICKET_ID,
                    message: messageText,
                    sender: USER_NAME,
                    timestamp: new Date().toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                });

                messageInput.value = '';
            }
        });

        socket.on('receive_message', (data) => {
            addMessageToUI(data.message, 'theirs', data.timestamp);
        });

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

        window.onload = scrollToBottom;
    </script>
</body>

</html>
