<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. {{ $user->name }} | MannMitra Pro</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <style>
        :root {
            /* NEW THEME: Clinical Teal (Calming & Professional) */
            --primary: #0D9488;
            /* Teal 600 */
            --primary-dark: #0F766E;
            /* Teal 700 */
            --primary-light: #F0FDFA;
            /* Teal 50 */

            --bg-body: #F8FAFC;
            /* Slate 50 */
            --text-main: #0F172A;
            /* Slate 900 */
            --text-muted: #64748B;
            /* Slate 500 */
            --white: #FFFFFF;

            --sidebar-width: 280px;
            --radius: 16px;
            --shadow-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--white);
            height: 100vh;
            position: fixed;
            border-right: 1px solid #E2E8F0;
            padding: 32px 24px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .brand-logo {
            font-size: 1.5rem;
            color: var(--primary-dark);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
        }

        .nav-item {
            padding: 14px 20px;
            margin-bottom: 8px;
            border-radius: 12px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            background-color: var(--bg-body);
            color: var(--primary);
        }

        .nav-item.active {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .nav-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 40px;
        }

        /* Stats Cards */
        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 28px;
            box-shadow: var(--shadow-card);
            border: 1px solid #F1F5F9;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 8px;
            color: var(--text-main);
        }

        /* Appointment List Cards */
        .apt-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #F1F5F9;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .apt-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-card);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        /* Buttons */
        .btn-accept {
            background-color: var(--white);
            color: var(--primary);
            border: 2px solid var(--primary-light);
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-accept:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-join {
            background: var(--primary);
            color: white;
            padding: 8px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.3);
            transition: all 0.2s;
        }

        .btn-join:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* Calendar Styling */
        #calendar {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-card);
            border: none;
        }

        .fc-toolbar-title {
            font-weight: 800 !important;
            color: var(--text-main);
        }

        .fc-button-primary {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
            font-weight: 600;
        }

        .fc-day-today {
            background-color: var(--primary-light) !important;
        }

        .fc-event {
            border-radius: 6px;
            padding: 2px 4px;
            border: none;
        }

        /* Helpers */
        .text-primary {
            color: var(--primary) !important;
        }

        .bg-primary {
            background-color: var(--primary) !important;
        }

        .d-none {
            display: none !important;
        }

        /* Logout Button */
        .btn-logout {
            margin-top: auto;
            color: #EF4444;
            background: #FEF2F2;
        }

        .btn-logout:hover {
            background: #FEE2E2;
            color: #DC2626;
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="brand-logo fw-bold">
            <i class="fas fa-brain me-2"></i> MannMitra
        </div>

        <nav class="d-flex flex-column h-100">
            <div class="nav-item active" id="tab-dashboard" onclick="switchTab('dashboard')">
                <i class="fas fa-grid-2 me-2"></i> Dashboard
            </div>
            <div class="nav-item" id="tab-schedule" onclick="switchTab('schedule')">
                <i class="fas fa-calendar-check me-2"></i> Full Schedule
            </div>

            <form action="{{ route('admin.logout') }}" method="POST" class="mt-auto">
                @csrf
                <button type="submit" class="nav-item btn-logout border-0 w-100">
                    <i class="fas fa-sign-out-alt me-2"></i> Sign Out
                </button>
            </form>
        </nav>
    </div>

    <div class="main-content">

        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Good Morning, Dr. {{ $user->name }}</h2>
                <p class="text-muted mb-0" id="page-subtitle">Here is your daily overview.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white p-2 rounded-circle shadow-sm border">
                    <img src="https://ui-avatars.com/api/?name={{ $user->name }}&background=0D9488&color=fff&font-size=0.5"
                        class="rounded-circle" width="48">
                </div>
            </div>
        </div>

        <div id="view-dashboard">
            <div class="row mb-5 g-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-label">Pending Requests</div>
                                <div class="stat-value" id="stat-pending">0</div>
                            </div>
                            <div class="text-warning opacity-25"><i class="fas fa-clock fa-3x"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="stat-label text-primary">Today's Sessions</div>
                                <div class="stat-value text-primary" id="stat-today">0</div>
                            </div>
                            <div class="text-primary opacity-25"><i class="fas fa-video fa-3x"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5">
                <div class="col-md-7">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0">Incoming Requests</h5>
                        <span class="badge bg-white text-muted border" id="badge-pending-count">0 New</span>
                    </div>
                    <div id="pending-container">
                        <div class="text-center py-5 text-muted">Checking for updates...</div>
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold m-0">Today's Agenda</h5>
                    </div>
                    <div id="agenda-container" class="mb-4">
                    </div>
                    <button class="btn btn-light w-100 py-3 text-primary fw-bold" onclick="switchTab('schedule')">
                        Open Full Calendar
                    </button>
                </div>
            </div>
        </div>

        <div id="view-schedule" class="d-none">
            <div id='calendar'></div>
        </div>

    </div>

    <script>
        const API_BASE = '/api/v1';
        const HEADERS = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        };

        document.addEventListener('DOMContentLoaded', () => {
            loadPendingRequests();
            loadAgenda();
        });

        // TABS
        let calendarInitialized = false;

        function switchTab(tab) {
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            if (tab === 'dashboard' || tab === 'schedule') {
                document.getElementById(`tab-${tab}`).classList.add('active');
            }

            if (tab === 'dashboard') {
                document.getElementById('view-dashboard').classList.remove('d-none');
                document.getElementById('view-schedule').classList.add('d-none');
                loadPendingRequests();
            } else {
                document.getElementById('view-dashboard').classList.add('d-none');
                document.getElementById('view-schedule').classList.remove('d-none');
                if (!calendarInitialized) {
                    initCalendar();
                    calendarInitialized = true;
                }
            }
        }

        // CALENDAR
        function initCalendar() {
            var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                themeSystem: 'bootstrap5',
                height: 'auto',
                events: async (info, success, failure) => {
                    try {
                        const res = await fetch(`${API_BASE}/appointments/my-schedule`, {
                            headers: HEADERS
                        });
                        const json = await res.json();
                        if (json.status) {
                            success(json.data.map(apt => ({
                                title: apt.user.name,
                                start: apt.scheduled_at,
                                // NEW (Correct - Points to Web Page)
                                url: `/meet/${apt.appointment_id}`,
                                color: '#0D9488', // Teal
                                extendedProps: {
                                    notes: apt.notes
                                }
                            })));
                        }
                    } catch (e) {
                        failure(e);
                    }
                },
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    if (confirm(`Join session with ${info.event.title}?`)) window.open(info.event.url,
                        '_blank');
                }
            });
            calendar.render();
        }

        // DATA FETCH
        async function loadPendingRequests() {
            const container = document.getElementById('pending-container');
            try {
                const res = await fetch(`${API_BASE}/appointments/pending`, {
                    headers: HEADERS
                });
                const json = await res.json();

                if (json.status && json.data.length > 0) {
                    document.getElementById('stat-pending').innerText = json.data.length;
                    document.getElementById('badge-pending-count').innerText = json.data.length + " New";

                    container.innerHTML = json.data.map(apt => `
                        <div class="apt-card">
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">${apt.user.name.charAt(0)}</div>
                                <div>
                                    <h6 class="fw-bold m-0 text-dark">${apt.user.name}</h6>
                                    <small class="text-muted">${new Date(apt.scheduled_at).toLocaleString('en-US', {weekday:'short', month:'short', day:'numeric', hour:'numeric', minute:'2-digit'})}</small>
                                </div>
                            </div>
                            <button onclick="acceptAppointment('${apt.appointment_id}')" class="btn-accept">Accept Request</button>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML =
                        `<div class="text-center py-5 bg-white rounded border border-dashed"><p class="text-muted m-0">No new patient requests.</p></div>`;
                    document.getElementById('stat-pending').innerText = "0";
                    document.getElementById('badge-pending-count').innerText = "0 New";
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function loadAgenda() {
            const container = document.getElementById('agenda-container');
            try {
                const res = await fetch(`${API_BASE}/appointments/my-schedule`, {
                    headers: HEADERS
                });
                const json = await res.json();

                if (json.status && json.data.length > 0) {
                    const today = new Date().toISOString().split('T')[0];
                    const todaysAppts = json.data.filter(apt => apt.scheduled_at.startsWith(today));

                    document.getElementById('stat-today').innerText = todaysAppts.length;

                    if (todaysAppts.length > 0) {
                        container.innerHTML = todaysAppts.map(apt => `
                            <div class="apt-card" style="border-left: 4px solid var(--primary);">
                                <div>
                                    <h6 class="fw-bold m-0 text-dark">${apt.user.name}</h6>
                                    <span class="text-primary fw-bold small">
                                        <i class="fas fa-video me-1"></i>
                                        ${new Date(apt.scheduled_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}
                                    </span>
                                </div>
                                <a href="/api/v1/appointments/${apt.appointment_id}/join" target="_blank" class="btn-join">Join</a>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML =
                            '<p class="text-muted text-center py-3">No sessions scheduled for today.</p>';
                    }
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function acceptAppointment(id) {
            if (!confirm("Are you sure you want to accept this patient?")) return;
            try {
                const res = await fetch(`${API_BASE}/appointments/${id}/accept`, {
                    method: 'POST',
                    headers: HEADERS
                });
                const json = await res.json();
                if (json.status) {
                    loadPendingRequests();
                    loadAgenda();
                } else {
                    alert(json.message);
                }
            } catch (e) {
                alert("Network Error");
            }
        }
    </script>
</body>

</html>
