<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MannMitra Video Session</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-color: #0f172a;
            --surface-color: #1e293b;
            --primary-color: #0ea5e9;
            --danger-color: #ef4444;
            --text-color: #f8fafc;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100dvh; /* FIX: Adapts to mobile browser bars */
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow: hidden; /* Prevent scrolling */
            display: flex;
            flex-direction: column;
        }

        /* Main Video Area - Takes available space */
        .main-stage {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px;
            position: relative;
            min-height: 0; /* FIX: Allows flex child to shrink properly */
            width: 100%;
        }

        .remote-video-container {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9; /* Maintain video shape */
            max-height: 100%; /* Never taller than the stage */
            max-width: 1280px;
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }

        /* On very tall screens (mobile), allow video to fill height if needed */
        @media (orientation: portrait) {
            .remote-video-container {
                width: 100%;
                height: auto;
                aspect-ratio: 3/4; /* Better for mobile portrait */
                object-fit: cover;
            }
        }

        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Local Video (Floating PIP) */
        .local-video-container {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 160px; /* Smaller default for responsiveness */
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: #1e1e1e;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            z-index: 10;
        }

        #localVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }

        /* Top Bar */
        .top-bar {
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 5;
            width: 100%;
        }

        .session-badge {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(8px);
            padding: 8px 16px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 10px #22c55e;
        }

        /* Bottom Controls Bar */
        .controls-bar {
            height: 80px; /* Fixed height */
            min-height: 80px;
            background: var(--surface-color);
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding-bottom: env(safe-area-inset-bottom); /* iPhone Home Bar fix */
            z-index: 20;
            width: 100%;
        }

        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 18px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .control-btn:active { transform: scale(0.95); }
        .control-btn.active { background: var(--danger-color); }

        .btn-hangup {
            width: 70px;
            border-radius: 25px;
            background: var(--danger-color);
        }

        /* Waiting Screen */
        #waitingOverlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 2;
        }
        
        .pulse-ring {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: pulse 2s infinite;
            margin-bottom: 15px;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.7; }
            70% { transform: scale(1); opacity: 0; }
            100% { transform: scale(0.95); opacity: 0; }
        }

        /* Mobile specific tweaks */
        @media (min-width: 768px) {
            .local-video-container {
                width: 240px;
                height: 135px;
                bottom: 30px;
                right: 30px;
            }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="session-badge">
            <div class="status-dot"></div>
            <span class="fw-bold">Live</span>
            <span class="text-white-50 mx-2">|</span>
            <span class="small text-white-50">#{{ $appointment->appointment_id }}</span>
        </div>
    </div>

    <div class="main-stage">
        <div class="remote-video-container">
            <div id="waitingOverlay">
                <div class="pulse-ring"></div>
                <h5 class="fw-light text-white-50">Waiting for patient...</h5>
            </div>
            <video id="remoteVideo" autoplay playsinline></video>
        </div>

        <div class="local-video-container">
            <video id="localVideo" autoplay playsinline muted></video>
        </div>
    </div>

    <div class="controls-bar">
        <button class="control-btn" id="btnMic" onclick="toggleMute()">
            <i class="fas fa-microphone"></i>
        </button>
        <button class="control-btn" id="btnCam" onclick="toggleVideo()">
            <i class="fas fa-video"></i>
        </button>
        <button class="control-btn btn-hangup" onclick="endCall()">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>

    <script>
        // --- CONFIGURATION ---
        const ROOM_ID = "{{ $appointment->meeting_link }}";
        const SIGNALING_URL = "http://31.97.232.145:3000";
        const API_CLOSE_URL = "/api/v1/appointments/close";
        
        const rtcConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { 
                    urls: 'turn:31.97.232.145:3478', 
                    username: 'mannmitra', 
                    credential: 'secure_video_password123' 
                }
            ]
        };

        let pc;
        let localStream;
        let candidateQueue = [];
        const socket = io(SIGNALING_URL, { transports: ['websocket'] });

        const remoteVideo = document.getElementById('remoteVideo');
        const waitingOverlay = document.getElementById('waitingOverlay');
        const btnMic = document.getElementById('btnMic');
        const btnCam = document.getElementById('btnCam');

        async function startCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('localVideo').srcObject = localStream;
            } catch (e) {
                alert("Camera access failed. Check permissions.");
                return;
            }

            pc = new RTCPeerConnection(rtcConfig);
            localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

            pc.ontrack = (event) => {
                if(remoteVideo.srcObject !== event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    waitingOverlay.style.display = 'none'; 
                }
            };

            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    socket.emit('ice_candidate', { room: ROOM_ID, candidate: event.candidate });
                }
            };

            socket.emit('join_call', ROOM_ID);
        }

        socket.on('peer_joined', async () => {
            console.log("Peer Joined. Waiting 1s...");
            setTimeout(async () => {
                try {
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    socket.emit('offer', { room: ROOM_ID, sdp: offer });
                } catch (e) { console.error(e); }
            }, 1000);
        });

        socket.on('receive_offer', async (sdp) => {
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(sdp));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                socket.emit('answer', { room: ROOM_ID, sdp: answer });
                await processCandidateQueue();
            } catch (e) { console.error(e); }
        });

        socket.on('receive_answer', async (sdp) => {
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(sdp));
                await processCandidateQueue();
            } catch (e) { console.error(e); }
        });

        socket.on('receive_ice_candidate', async (candidate) => {
            if (pc.remoteDescription) {
                try { await pc.addIceCandidate(new RTCIceCandidate(candidate)); } catch (e) {}
            } else {
                candidateQueue.push(candidate);
            }
        });

        async function processCandidateQueue() {
            if (candidateQueue.length > 0) {
                for (let c of candidateQueue) {
                    try { await pc.addIceCandidate(new RTCIceCandidate(c)); } catch (e) {}
                }
                candidateQueue = [];
            }
        }

        socket.on('peer_hangup', () => {
            alert("Call ended by patient.");
            closeVideoCall();
        });

        async function endCall() {
            if (confirm("End session?")) {
                socket.emit('hangup', { room: ROOM_ID });
                try {
                    await fetch(API_CLOSE_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ meeting_link: ROOM_ID })
                    });
                } catch (e) { console.error(e); }
                closeVideoCall();
            }
        }

        function closeVideoCall() {
            if (pc) pc.close();
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            window.location.href = "/psychiatrist/dashboard";
        }

        function toggleMute() {
            const track = localStream.getAudioTracks()[0];
            if (track) {
                track.enabled = !track.enabled;
                btnMic.classList.toggle('active');
                btnMic.innerHTML = track.enabled ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';
            }
        }

        function toggleVideo() {
            const track = localStream.getVideoTracks()[0];
            if (track) {
                track.enabled = !track.enabled;
                btnCam.classList.toggle('active');
                btnCam.innerHTML = track.enabled ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';
            }
        }

        startCall();
    </script>
</body>
</html>