<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Video Session | {{ $appointment->appointment_id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #0F172A;
            color: white;
            height: 100vh;
            overflow: hidden;
        }

        .video-container {
            position: relative;
            height: 85vh;
            width: 100%;
            display: flex;
            justify-content: center;
        }

        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #1E293B;
            border-radius: 12px;
        }

        #localVideo {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #0D9488;
            background: #000;
        }

        .controls {
            height: 15vh;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .btn-control {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            font-size: 20px;
        }

        .btn-end {
            background: #EF4444;
            color: white;
        }
    </style>
</head>

<body>

    <div class="container-fluid p-3">
        <div class="video-container">
            <video id="remoteVideo" autoplay playsinline></video>
            <video id="localVideo" autoplay playsinline muted></video>

            <div class="position-absolute top-0 start-0 p-3 bg-dark bg-opacity-50 rounded m-3">
                <h5 class="m-0">Patient Session</h5>
                <small class="text-light">ID: {{ $appointment->appointment_id }}</small>
            </div>
        </div>

        <div class="controls">
            <button class="btn-control bg-light" onclick="toggleMute()"><i class="fas fa-microphone"></i> 🎤</button>
            <button class="btn-control bg-light" onclick="toggleVideo()"><i class="fas fa-video"></i> 📹</button>
            <button class="btn-control btn-end" onclick="endCall()">❌</button>
        </div>
    </div>

    <script>
        const ROOM_ID = "{{ $appointment->meeting_link }}"; // The Room ID
        const SIGNALING_URL = "http://31.97.232.145:3000"; // Your IP-based Signaling Server

        // YOUR COTURN CONFIG
        const rtcConfig = {
            iceServers: [{
                    urls: 'stun:stun.l.google.com:19302'
                },
                {
                    urls: 'turn:31.97.232.145:3478',
                    username: 'mannmitra',
                    credential: 'secure_video_password123'
                }
            ]
        };

        let pc;
        let localStream;
        const socket = io(SIGNALING_URL, {
            transports: ['websocket']
        });

        async function startCall() {
            // 1. Get Camera
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });
                document.getElementById('localVideo').srcObject = localStream;
            } catch (e) {
                alert("Camera/Mic access failed. Please check permissions.");
                return;
            }

            // 2. Setup PeerConnection
            pc = new RTCPeerConnection(rtcConfig);

            // Add Tracks
            localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

            // Handle Remote Stream
            pc.ontrack = (event) => {
                document.getElementById('remoteVideo').srcObject = event.streams[0];
            };

            // Handle ICE Candidates
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    socket.emit('ice_candidate', {
                        room: ROOM_ID,
                        candidate: event.candidate
                    });
                }
            };

            // 3. Join Room
            socket.emit('join_call', ROOM_ID);
        }

        // --- SIGNALING LOGIC ---
        socket.on('peer_joined', async () => {
            console.log("Peer Joined. Waiting 1 second for stability...");

            // ⏳ WAIT 1000ms (1 second) before calling
            setTimeout(async () => {
                console.log("Sending Offer now...");
                try {
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    socket.emit('offer', {
                        room: ROOM_ID,
                        sdp: offer
                    });
                } catch (e) {
                    console.error("Error sending offer:", e);
                }
            }, 1000);
        });

        socket.on('receive_offer', async (sdp) => {
            console.log("Received Offer");
            await pc.setRemoteDescription(new RTCSessionDescription(sdp));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            socket.emit('answer', {
                room: ROOM_ID,
                sdp: answer
            });
        });

        socket.on('receive_answer', async (sdp) => {
            console.log("Received Answer");
            await pc.setRemoteDescription(new RTCSessionDescription(sdp));
        });

        socket.on('receive_ice_candidate', (candidate) => {
            pc.addIceCandidate(new RTCIceCandidate(candidate));
        });

        socket.on('peer_hangup', () => {
            alert("The patient has ended the call.");
            closeVideoCall();
        });

        function endCall() {
            if (confirm("Are you sure you want to end the session?")) {
                socket.emit('hangup', {
                    room: ROOM_ID
                });
                closeVideoCall();
            }
        }

        function closeVideoCall() {
            if (pc) {
                pc.close();
                pc = null;
            }
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            window.close();
            window.location.href = "/dashboard";
        }

        // --- UTILS ---
        function toggleMute() {
            const audioTrack = localStream.getAudioTracks()[0];
            if (audioTrack) audioTrack.enabled = !audioTrack.enabled;
        }

        function toggleVideo() {
            const videoTrack = localStream.getVideoTracks()[0];
            if (videoTrack) videoTrack.enabled = !videoTrack.enabled;
        }


        startCall();
    </script>
</body>

</html>
