<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. {{ $user->name }} | MannMitra Pro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #2563EB;        /* Trustworthy Blue */
            --primary-dark: #1E40AF;
            --bg-light: #F3F4F6;
            --text-dark: #1F2937;
            --text-muted: #6B7280;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --radius: 12px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 260px;
            background: var(--white);
            height: 100vh;
            position: fixed;
            border-right: 1px solid #E5E7EB;
            padding: 24px;
        }
        
        .nav-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            color: var(--text-muted);
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-item:hover, .nav-item.active {
            background-color: #EFF6FF;
            color: var(--primary);
        }

        .nav-item i { width: 24px; margin-right: 10px; }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 32px;
        }

        /* Cards */
        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid #E5E7EB;
        }
        
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-dark); }
        .stat-label { color: var(--text-muted); font-size: 14px; font-weight: 500; }

        /* Appointment Cards */
        .apt-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s;
        }
        
        .apt-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: #DBEAFE;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 18px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-confirmed { background: #D1FAE5; color: #059669; }

        .btn-primary-custom {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: background 0.2s;
        }
        .btn-primary-custom:hover { background: var(--primary-dark); }

    </style>
</head>
<body>

    <div class="sidebar">
        <div class="mb-5 px-2">
            <h4 class="fw-bold text-primary"><i class="fas fa-brain me-2"></i>MannMitra</h4>
            <span class="badge bg-light text-dark border">Professional Portal</span>
        </div>
        
        <nav>
            <a href="#" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#" class="nav-item"><i class="fas fa-calendar-check"></i> My Schedule</a>
            <a href="#" class="nav-item"><i class="fas fa-users"></i> Patient Requests</a>
            <a href="#" class="nav-item"><i class="fas fa-wallet"></i> Earnings</a>
            <a href="#" class="nav-item text-danger mt-5"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold m-0">Welcome, Dr. {{ $user->name }}</h2>
                <p class="text-muted">Here is your schedule for today.</p>
            </div>
            <div class="d-flex align-items-center">
                <button class="btn btn-white border me-3"><i class="fas fa-bell"></i></button>
                <img src="https://ui-avatars.com/api/?name={{ $user->name }}&background=2563EB&color=fff" class="rounded-circle" width="40">
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-value text-warning" id="stat-pending">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Upcoming Sessions</div>
                    <div class="stat-value text-primary" id="stat-scheduled">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value text-success" id="stat-completed">12</div>
                </div>
            </div>
        </div>

        <div class="row">
            
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold">Patient Requests</h5>
                    <button class="btn btn-sm btn-light" onclick="loadPendingRequests()"><i class="fas fa-sync"></i> Refresh</button>
                </div>
                <div id="pending-container">
                    <div class="text-center py-5 text-muted">Loading...</div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold">My Appointments</h5>
                </div>
                <div id="schedule-container">
                    </div>
            </div>

        </div>
    </div>

<script>
        const API_BASE = '/api/v1';

        document.addEventListener('DOMContentLoaded', () => {
            loadPendingRequests();
            loadMySchedule();
        });

        async function loadPendingRequests() {
            const container = document.getElementById('pending-container');
            container.innerHTML = '<div class="text-center py-3">Loading...</div>';

            try {
                // FIX: Removed 'Authorization' header. 
                // Added 'credentials: include' to send the Login Cookie.
                const response = await fetch(`${API_BASE}/appointments/pending`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest', // Tells Laravel it's AJAX
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'  // Security Token
                    }
                });
                
                // If the API returns 401 (Unauthenticated), it means API routes 
                // aren't accepting Cookies. We might need a quick route change (see Step 3 below).
                if(response.status === 401) {
                    console.error("Auth failed. API requires Token.");
                    container.innerHTML = '<p class="text-danger text-center">Auth Error: Session not accepted by API.</p>';
                    return;
                }

                const result = await response.json();

                if(result.status && result.data.length > 0) {
                    container.innerHTML = '';
                    document.getElementById('stat-pending').innerText = result.data.length;

                    result.data.forEach(apt => {
                        const dateObj = new Date(apt.scheduled_at);
                        const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                        const card = `
                            <div class="apt-card">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">${apt.user.name.charAt(0)}</div>
                                    <div>
                                        <h6 class="fw-bold m-0">${apt.user.name}</h6>
                                        <small class="text-muted"><i class="far fa-clock me-1"></i> ${dateStr} at ${timeStr}</small>
                                        <div class="text-muted small mt-1 text-truncate" style="max-width: 200px;">
                                            "${apt.notes || 'No notes provided'}"
                                        </div>
                                    </div>
                                </div>
                                <button onclick="acceptAppointment('${apt.appointment_id}')" class="btn btn-sm btn-outline-primary">
                                    Accept
                                </button>
                            </div>
                        `;
                        container.innerHTML += card;
                    });
                } else {
                    container.innerHTML = '<div class="text-center py-5"><p class="text-muted">No pending requests.</p></div>';
                    document.getElementById('stat-pending').innerText = "0";
                }

            } catch (error) {
                console.error(error);
                container.innerHTML = '<p class="text-danger text-center">Failed to load data.</p>';
            }
        }

        async function acceptAppointment(id) {
            if(!confirm("Are you sure you want to accept this patient?")) return;

            try {
                const response = await fetch(`${API_BASE}/appointments/${id}/accept`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const result = await response.json();
                
                if(result.status) {
                    alert("Appointment Confirmed!");
                    loadPendingRequests();
                    loadMySchedule();
                } else {
                    alert("Error: " + result.message);
                }
            } catch (err) {
                alert("Something went wrong.");
            }
        }

        async function loadMySchedule() {
            const container = document.getElementById('schedule-container');
            // Don't clear immediately to avoid flickering, just update content when ready
            
            try {
                const response = await fetch(`${API_BASE}/appointments/my-schedule`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const result = await response.json();

                if(result.status && result.data.length > 0) {
                    container.innerHTML = ''; // Clear previous
                    document.getElementById('stat-scheduled').innerText = result.data.length;

                    result.data.forEach(apt => {
                        const dateObj = new Date(apt.scheduled_at);
                        const dateStr = dateObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                        const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

                        // Logic: Enable "Join" button if within 15 mins of start time
                        // For testing, we enable it always.
                        
                        const card = `
                            <div class="apt-card border-primary">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3 bg-primary text-white">${apt.user.name.charAt(0)}</div>
                                    <div>
                                        <h6 class="fw-bold m-0">${apt.user.name}</h6>
                                        <span class="badge bg-light text-primary border mb-1">Video Call</span>
                                        <div class="small text-muted"><i class="fas fa-calendar-alt me-1"></i> ${dateStr}</div>
                                        <div class="fw-bold text-dark"><i class="far fa-clock me-1"></i> ${timeStr}</div>
                                    </div>
                                </div>
                                
                                <a href="/api/v1/appointments/${apt.appointment_id}/join" target="_blank" class="btn btn-sm btn-primary-custom">
                                    <i class="fas fa-video me-1"></i> Join
                                </a>
                            </div>
                        `;
                        container.innerHTML += card;
                    });
                } else {
                    container.innerHTML = `
                        <div class="text-center py-5 border rounded bg-white">
                            <p class="text-muted m-0">No upcoming appointments.</p>
                        </div>
                    `;
                    document.getElementById('stat-scheduled').innerText = "0";
                }

            } catch (error) {
                console.error(error);
            }
        }
    </script>
</body>
</html>