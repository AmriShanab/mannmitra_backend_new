<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Video Session | MannMitra</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <link rel="icon" type="image/png" href="{{ asset('images/app_icon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --sky: #56b4ea; --indigo: #5b5fe6;
            --grad: linear-gradient(135deg, #56b4ea 0%, #5b5fe6 100%);
            --bg: #090b1e; --surface: rgba(21, 24, 51, .78); --line: rgba(255, 255, 255, .1);
            --text: #f3f4ff; --muted: #a4a9d0; --danger: #e5484d; --ok: #3fd5a6;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; width: 100%; height: 100dvh; overflow: hidden; color: var(--text); font-family: "Plus Jakarta Sans", system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        body {
            display: flex; flex-direction: column;
            background:
                radial-gradient(900px 500px at 85% -10%, rgba(86, 180, 234, .22), transparent 60%),
                radial-gradient(800px 500px at -10% 110%, rgba(91, 95, 230, .26), transparent 60%),
                var(--bg);
        }

        /* Top bar */
        .top-bar { flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 22px; }
        .brand { display: flex; align-items: center; gap: 11px; font-weight: 800; }
        .brand img { width: 34px; height: 34px; border-radius: 10px; box-shadow: 0 4px 14px rgba(91, 95, 230, .5); }
        .session-badge { display: flex; align-items: center; gap: 10px; padding: 8px 16px; border-radius: 999px; background: var(--surface); border: 1px solid var(--line); backdrop-filter: blur(12px); font-size: .85rem; font-weight: 700; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 10px var(--ok); animation: blink 1.6s infinite; }
        @keyframes blink { 50% { opacity: .4; } }
        .session-badge .sep { width: 1px; height: 14px; background: var(--line); }
        .session-badge .id { color: var(--muted); font-weight: 600; }
        #callTimer { font-variant-numeric: tabular-nums; min-width: 42px; }

        /* Stage */
        .main-stage { flex: 1; min-height: 0; display: flex; justify-content: center; align-items: center; padding: 6px 22px 14px; position: relative; }
        .remote-video-container { position: relative; width: 100%; max-width: 1180px; max-height: 100%; aspect-ratio: 16 / 9; border-radius: 26px; overflow: hidden; background: #000; border: 1px solid var(--line); box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .8), 0 0 0 6px rgba(255, 255, 255, .03); }
        #remoteVideo { width: 100%; height: 100%; object-fit: cover; }
        @media (orientation: portrait) { .remote-video-container { aspect-ratio: 3 / 4; } }

        .local-video-container { position: absolute; right: 40px; bottom: 32px; width: 150px; height: 112px; border-radius: 16px; overflow: hidden; border: 2px solid rgba(255, 255, 255, .35); background: #1a1d3a; box-shadow: 0 12px 30px rgba(0, 0, 0, .55); z-index: 10; }
        #localVideo { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        .you-tag { position: absolute; left: 8px; bottom: 6px; font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; background: rgba(0, 0, 0, .5); }
        @media (min-width: 768px) { .local-video-container { width: 230px; height: 130px; right: 48px; bottom: 40px; } }

        /* Waiting overlay */
        #waitingOverlay { position: absolute; inset: 0; z-index: 2; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px;
            background: radial-gradient(600px 360px at 50% 40%, rgba(91, 95, 230, .35), transparent 70%), #0a0c22; }
        .pulse-wrap { position: relative; width: 96px; height: 96px; margin-bottom: 22px; display: grid; place-items: center; }
        .pulse-wrap::before, .pulse-wrap::after { content: ""; position: absolute; inset: 0; border-radius: 50%; background: var(--grad); opacity: .45; animation: ring 2.4s infinite; }
        .pulse-wrap::after { animation-delay: 1.2s; }
        @keyframes ring { 0% { transform: scale(.7); opacity: .55; } 100% { transform: scale(1.7); opacity: 0; } }
        .pulse-wrap img { position: relative; z-index: 1; width: 72px; height: 72px; border-radius: 20px; box-shadow: 0 10px 30px rgba(91, 95, 230, .6); }
        #waitingOverlay h5 { margin: 0 0 6px; font-size: 1.15rem; font-weight: 800; }
        #waitingOverlay p { margin: 0; color: var(--muted); font-size: .9rem; }

        /* Controls */
        .controls-wrap { flex: 0 0 auto; display: flex; justify-content: center; padding: 0 16px calc(22px + env(safe-area-inset-bottom)); }
        .controls-bar { display: flex; align-items: center; gap: 16px; padding: 12px 20px; border-radius: 999px; background: var(--surface); border: 1px solid var(--line); backdrop-filter: blur(16px); box-shadow: 0 20px 50px -15px rgba(0, 0, 0, .7); }
        .control-btn { width: 54px; height: 54px; border-radius: 50%; border: 1px solid var(--line); background: rgba(255, 255, 255, .08); color: #fff; font-size: 1.1rem; display: flex; justify-content: center; align-items: center; cursor: pointer; transition: .15s; -webkit-tap-highlight-color: transparent; }
        .control-btn:hover { background: rgba(255, 255, 255, .16); transform: translateY(-2px); }
        .control-btn:active { transform: scale(.95); }
        .control-btn.active { background: #fff; color: var(--danger); }
        .btn-hangup { width: 78px; border-radius: 27px; background: var(--danger); border-color: transparent; box-shadow: 0 10px 24px -6px rgba(229, 72, 77, .7); }
        .btn-hangup:hover { background: #f05a5f; }

        @media (max-width: 600px) {
            .top-bar { padding: 10px 14px; }
            .brand span { display: none; }
            .main-stage { padding: 4px 10px 10px; }
            .remote-video-container { border-radius: 20px; }
            .local-video-container { right: 20px; bottom: 18px; width: 108px; height: 82px; }
            .controls-bar { gap: 12px; padding: 10px 16px; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="brand"><img src="{{ asset('images/app_icon.png') }}" alt="MannMitra"><span>MannMitra</span></div>
        <div class="session-badge">
            <div class="status-dot"></div>
            <span id="callStatus">Live</span>
            <span class="sep"></span>
            <span id="callTimer">00:00</span>
            <span class="sep"></span>
            <span class="id">#{{ $appointment->appointment_id }}</span>
        </div>
        <div style="width:34px"></div>
    </div>

    <div class="main-stage">
        <div class="remote-video-container">
            <div id="waitingOverlay">
                <div class="pulse-wrap"><img src="{{ asset('images/app_icon.png') }}" alt=""></div>
                <h5>Waiting for the patient…</h5>
                <p>The session starts automatically when they join.</p>
            </div>
            <video id="remoteVideo" autoplay playsinline></video>
        </div>

        <div class="local-video-container">
            <video id="localVideo" autoplay playsinline muted></video>
            <span class="you-tag">You</span>
        </div>
    </div>

    <div class="controls-wrap">
        <div class="controls-bar">
            <button class="control-btn" id="btnMic" onclick="toggleMute()" title="Mute / unmute">
                <i class="fas fa-microphone"></i>
            </button>
            <button class="control-btn" id="btnCam" onclick="toggleVideo()" title="Camera on / off">
                <i class="fas fa-video"></i>
            </button>
            <button class="control-btn btn-hangup" onclick="endCall()" title="End session">
                <i class="fas fa-phone-slash"></i>
            </button>
        </div>
    </div>

    <script>
        // --- CONFIGURATION ---
        const ROOM_ID = @json($appointment->meeting_link);
        const SIGNALING_URL = @json($rt['socket_url']);
        const SOCKET_TOKEN = @json($rt['token']);
        const API_CLOSE_URL = "/api/v1/appointments/close";
        
        // ICE servers (incl. time-limited TURN login) are issued by the server per session.
        const rtcConfig = { iceServers: @json($rt['ice_servers']) };

        let pc;
        let localStream;
        let candidateQueue = [];
        const socket = io(SIGNALING_URL, { transports: ['websocket'], auth: { token: SOCKET_TOKEN } });
        socket.on('connect_error', (err) => {
            console.error('Signaling connection failed:', err.message);
            if (err.message === 'unauthorized') alert('Session expired. Please reopen this page.');
        });

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
            console.log("Peer Joined.");
            setTimeout(async () => {
                try {
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    socket.emit('offer', { room: ROOM_ID, sdp: offer });
                } catch (e) { console.error(e); }
            }, 300);
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
    <script>
        // Call timer + status (starts when the patient's video arrives)
        (function () {
            const timerEl = document.getElementById('callTimer');
            const statusEl = document.getElementById('callStatus');
            const rv = document.getElementById('remoteVideo');
            let started = null;
            rv.addEventListener('loadedmetadata', () => {
                if (started) return;
                started = Date.now();
                statusEl.textContent = 'Connected';
                setInterval(() => {
                    const s = Math.floor((Date.now() - started) / 1000);
                    const mm = String(Math.floor(s / 60)).padStart(2, '0');
                    const ss = String(s % 60).padStart(2, '0');
                    timerEl.textContent = mm + ':' + ss;
                }, 1000);
            });
        })();
    </script>
</body>
</html>
