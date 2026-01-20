<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MannMitra Video Session</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Main Video Area */
        .main-stage {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .remote-video-container {
            width: 100%;
            height: 100%;
            max-width: 1280px;
            max-height: 720px;
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Local Video (Floating PIP) */
        .local-video-container {
            position: absolute;
            bottom: 30px;
            right: 30px;
            width: 280px;
            height: 160px;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: #1e1e1e;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
            z-index: 10;
        }

        .local-video-container:hover {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }

        #localVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Mirror effect */
        }

        /* Top Bar */
        .top-bar {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 5;
            pointer-events: none; /* Let clicks pass through */
        }

        .session-badge {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            pointer-events: auto;
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
            height: 80px;
            background: var(--surface-color);
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            z-index: 20;
        }

        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 18px;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .control-btn.active {
            background: var(--danger-color);
            color: white;
        }

        .btn-hangup {
            width: 70px;
            height: 50px;
            border-radius: 25px;
            background: var(--danger-color);
        }

        .btn-hangup:hover {
            background: #dc2626;
        }

        /* Waiting Screen Overlay */
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
            display: block;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: pulse 2s infinite;
            margin-bottom: 20px;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 30px rgba(14, 165, 233, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(14, 165, 233, 0); }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .local-video-container {
                width: 120px;
                height: 160px; /* Portrait for mobile */
                top: 20px;
                right: 20px;
                bottom: auto;
            }
            .controls-bar {
                gap: 15px;
            }
        }
    </style>
</head>
<body>

    <div class="main-stage">
        <div class="top-bar">
            <div class="session-badge">
                <div class="status-dot"></div>
                <span class="fw-bold">Live Session</span>
                <span class="text-white-50 mx-2">|</span>
                <span class="small text-white-50">#{{ $appointment->appointment_id }}</span>
            </div>
        </div>

        <div class="remote-video-container">
            <div id="waitingOverlay">
                <div class="pulse-ring"></div>
                <h4 class="fw-light">Waiting for patient...</h4>
            </div>
            <video id="remoteVideo" autoplay playsinline></video>
        </div>

        <div class="local-video-container">
            <video id="localVideo" autoplay playsinline muted></video>
        </div>
    </div>

    <div class="controls-bar">
        <button class="control-btn" id="btnMic" onclick="toggleMute()" title="Toggle Microphone">
            <i class="fas fa-microphone"></i>
        </button>
        <button class="control-btn" id="btnCam" onclick="toggleVideo()" title="Toggle Camera">
            <i class="fas fa-video"></i>
        </button>
        
        <button class="control-btn btn-hangup" onclick="endCall()" title="End Call">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>

    <script>
        // --- CONFIGURATION ---
        const ROOM_ID = "{{ $appointment->meeting_link }}";
        const SIGNALING_URL = "http://31.97.232.145:3000";
        const API_CLOSE_URL = "/api/appointment/close";
        
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

        // --- VARIABLES ---
        let pc;
        let localStream;
        let candidateQueue = [];
        const socket = io(SIGNALING_URL, { transports: ['websocket'] });

        // --- UI ELEMENTS ---
        const remoteVideo = document.getElementById('remoteVideo');
        const waitingOverlay = document.getElementById('waitingOverlay');
        const btnMic = document.getElementById('btnMic');
        const btnCam = document.getElementById('btnCam');

        // --- INITIALIZATION ---
        async function startCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                document.getElementById('localVideo').srcObject = localStream;
            } catch (e) {
                alert("Could not access Camera/Microphone. Please allow permissions.");
                console.error(e);
                return;
            }

            // Init PeerConnection
            pc = new RTCPeerConnection(rtcConfig);

            // Add Local Tracks
            localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

            // Handle Remote Tracks
            pc.ontrack = (event) => {
                if(remoteVideo.srcObject !== event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    waitingOverlay.style.display = 'none'; // Hide waiting screen
                    console.log("Video Connected!");
                }
            };

            // Handle ICE Candidates
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    socket.emit('ice_candidate', { room: ROOM_ID, candidate: event.candidate });
                }
            };

            // Join Room
            socket.emit('join_call', ROOM_ID);
        }

        // --- SIGNALING HANDLERS ---
        socket.on('peer_joined', async () => {
            console.log("Peer Joined. Waiting 1s for stability...");
            setTimeout(async () => {
                try {
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    socket.emit('offer', { room: ROOM_ID, sdp: offer });
                } catch (e) { console.error(e); }
            }, 1000);
        });

        socket.on('receive_offer', async (sdp) => {
            console.log("Received Offer");
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(sdp));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                socket.emit('answer', { room: ROOM_ID, sdp: answer });
                await processCandidateQueue();
            } catch (e) { console.error(e); }
        });

        socket.on('receive_answer', async (sdp) => {
            console.log("Received Answer");
            try {
                await pc.setRemoteDescription(new RTCSessionDescription(sdp));
                await processCandidateQueue();
            } catch (e) { console.error(e); }
        });

        socket.on('receive_ice_candidate', async (candidate) => {
            if (pc.remoteDescription) {
                try { await pc.addIceCandidate(new RTCIceCandidate(candidate)); } catch (e) {}
            } else {
                console.log("Buffering Candidate...");
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

        // --- HANGUP & CLOSING LOGIC ---
        socket.on('peer_hangup', () => {
            alert("The patient has left the session.");
            closeVideoCall();
        });

        async function endCall() {
            if (confirm("End this session for everyone?")) {
                // 1. Notify Socket
                socket.emit('hangup', { room: ROOM_ID });
                
                // 2. Notify DB to close appointment
                try {
                    await fetch(API_CLOSE_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ meeting_link: ROOM_ID })
                    });
                } catch (e) { console.error("DB Update Failed", e); }

                // 3. Close Local
                closeVideoCall();
            }
        }

        function closeVideoCall() {
            if (pc) { pc.close(); pc = null; }
            if (localStream) { localStream.getTracks().forEach(t => t.stop()); }
            
            // Redirect to dashboard
            window.location.href = "/psychiatrist/dashboard";
        }

        // --- CONTROL TOGGLES ---
        function toggleMute() {
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                btnMic.classList.toggle('active');
                btnMic.innerHTML = audioTrack.enabled ? '<i class="fas fa-microphone"></i>' : '<i class="fas fa-microphone-slash"></i>';
            }
        }

        function toggleVideo() {
            const videoTrack = localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                btnCam.classList.toggle('active');
                btnCam.innerHTML = videoTrack.enabled ? '<i class="fas fa-video"></i>' : '<i class="fas fa-video-slash"></i>';
            }
        }

        // Start
        startCall();

    </script>
</body>
</html>