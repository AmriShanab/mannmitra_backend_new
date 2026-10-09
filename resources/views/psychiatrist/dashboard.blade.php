<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. {{ $user->name }} | MannMitra</title>
    @include('partials.portal-head')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <style>
        /* Notifications */
        .notif-wrap { position: relative; }
        .notif-badge { position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px; background: var(--danger); color: #fff; font-size: 11px; font-weight: 800; display: none; align-items: center; justify-content: center; border: 2px solid var(--bg); }
        .notif-panel { position: absolute; right: 0; top: 54px; width: 370px; max-width: 92vw; max-height: 460px; overflow-y: auto; background: var(--card); border: 1px solid var(--line); border-radius: 18px; box-shadow: var(--shadow); display: none; z-index: 80; }
        .notif-panel.open { display: block; }
        .notif-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--line); font-weight: 800; position: sticky; top: 0; background: var(--card); }
        .notif-head button { border: 0; background: none; color: var(--indigo); font-size: .78rem; font-weight: 700; cursor: pointer; }
        html[data-theme="dark"] .notif-head button { color: var(--sky); }
        .notif-item { padding: 13px 18px; border-bottom: 1px solid var(--line); cursor: pointer; position: relative; }
        .notif-item:hover { background: var(--soft); }
        .notif-item.unread { background: color-mix(in srgb, var(--sky) 10%, transparent); padding-left: 28px; }
        .notif-item.unread::before { content: ""; position: absolute; left: 12px; top: 20px; width: 8px; height: 8px; border-radius: 50%; background: var(--grad); }
        .notif-item .t { font-weight: 700; font-size: .88rem; }
        .notif-item .b { font-size: .82rem; color: var(--muted); margin-top: 2px; }
        .notif-item .w { font-size: .72rem; color: var(--faint); margin-top: 4px; font-weight: 600; }
        .notif-empty { padding: 34px 16px; text-align: center; color: var(--faint); font-size: .9rem; }

        /* Agenda */
        .agenda-time { color: var(--indigo); font-weight: 800; font-size: .82rem; }
        html[data-theme="dark"] .agenda-time { color: var(--sky); }
        .section-title { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .section-title h3 { font-size: 1.08rem; font-weight: 800; }
        .loading { text-align: center; padding: 34px; color: var(--faint); }
        .mode-chip { display: inline-flex; gap: 6px; align-items: center; padding: 2px 10px; border-radius: 999px; background: var(--soft); font-size: .72rem; font-weight: 700; color: var(--muted); }

        /* Schedule view */
        .legend { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
        .legend .lg { display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px; border-radius: 999px; background: var(--card); border: 1px solid var(--line); font-size: .78rem; font-weight: 700; color: var(--muted); }
        .legend .lg i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
        #calendar { background: var(--card); border: 1px solid var(--line); border-radius: var(--r); box-shadow: var(--shadow); padding: 24px; }

        /* FullCalendar skin */
        .fc { --fc-border-color: var(--line); --fc-page-bg-color: transparent; --fc-neutral-bg-color: var(--soft); --fc-today-bg-color: color-mix(in srgb, var(--sky) 14%, transparent); --fc-list-event-hover-bg-color: var(--soft); font-family: var(--font); }
        .fc .fc-toolbar-title { font-weight: 800; font-size: 1.25rem; letter-spacing: -.01em; }
        .fc .fc-button { background: var(--soft); border: 1px solid var(--line); color: var(--muted); font-weight: 700; border-radius: 10px; text-transform: capitalize; box-shadow: none !important; padding: 7px 14px; }
        .fc .fc-button:hover { background: var(--card); color: var(--indigo); border-color: var(--sky); }
        .fc .fc-button-primary:not(:disabled).fc-button-active, .fc .fc-button-primary:not(:disabled):active { background: var(--grad); color: #fff; border-color: transparent; }
        .fc .fc-button-primary:disabled { opacity: .5; }
        .fc .fc-col-header-cell-cushion, .fc .fc-daygrid-day-number { color: var(--muted); font-weight: 700; font-size: .82rem; text-decoration: none; }
        .fc .fc-day-today .fc-daygrid-day-number { color: var(--indigo); }
        .fc .fc-event { border-radius: 8px; padding: 2px 6px; border: 0; font-weight: 600; font-size: .78rem; cursor: pointer; }
        .fc-theme-standard .fc-scrollgrid { border-radius: 14px; overflow: hidden; }
        .fc a { color: inherit; }

        @media (max-width: 600px) {
            #calendar { padding: 12px; }
            .fc .fc-toolbar { flex-direction: column; gap: 10px; }
            .notif-panel { position: fixed; left: 12px; right: 12px; top: 70px; width: auto; max-width: none; }
        }
    </style>
</head>
<body>

@php
    $hour = (int) \Carbon\Carbon::now()->format('G');
    $greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
@endphp

{{-- ── TOP BAR ─────────────────────────────────────────────── --}}
<header class="pt-topbar">
    <div class="pt-topbar-in">
        <a class="pt-brand" href="{{ route('psychiatrist.dashboard') }}">
            <img src="{{ asset('images/app_icon.png') }}" alt="MannMitra">
            <span class="bn">MannMitra</span>
            <span class="role-pill">Doctor</span>
        </a>

        <nav class="pt-nav">
            <button type="button" class="on" id="tab-dashboard" onclick="switchTab('dashboard')"><i class="fas fa-table-columns"></i> Dashboard</button>
            <button type="button" id="tab-schedule" onclick="switchTab('schedule')"><i class="fas fa-calendar-check"></i> Full schedule</button>
        </nav>

        <div class="pt-right">
            <div class="notif-wrap">
                <button type="button" class="icon-btn" id="notifBtn" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="notifBadge">0</span>
                </button>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-head">
                        <span>Notifications</span>
                        <button type="button" id="notifReadAll">Mark all read</button>
                    </div>
                    <div id="notifList"><div class="notif-empty">Loading…</div></div>
                </div>
            </div>

            <div class="user-chip">
                <div class="avatar sm">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <span class="nm">Dr. {{ $user->name }}</span>
            </div>

            <button class="icon-btn" id="themeToggle" aria-label="Toggle dark/light mode"><i class="fas fa-moon" id="themeIcon"></i></button>

            <form action="{{ route('admin.logout') }}" method="POST" style="margin:0">
                @csrf
                <button type="submit" class="icon-btn" title="Sign out" aria-label="Sign out"><i class="fas fa-right-from-bracket"></i></button>
            </form>
        </div>
    </div>
</header>

<main class="pt-page">

    {{-- ── DASHBOARD VIEW ── --}}
    <div id="view-dashboard">

        <section class="hero-card">
            <div class="hero-top">
                <div>
                    <h1>Good {{ $greeting }}, Dr. {{ $user->name }}</h1>
                    <p id="page-subtitle">Here is your daily overview: new requests and today's sessions at a glance.</p>
                </div>
                <span class="hero-date"><i class="far fa-calendar"></i>&nbsp; {{ \Carbon\Carbon::now()->format('D, d M Y') }}</span>
            </div>
        </section>

        <section class="stats">
            <div class="stat">
                <div class="stat-ic warn"><i class="fas fa-clock"></i></div>
                <div><div class="stat-label">Pending requests</div><div class="stat-value" id="stat-pending">0</div></div>
            </div>
            <div class="stat">
                <div class="stat-ic"><i class="fas fa-video"></i></div>
                <div><div class="stat-label">Today's sessions</div><div class="stat-value" id="stat-today">0</div></div>
            </div>
        </section>

        <div class="grid-7-5">
            <section class="card">
                <div class="card-head">
                    <h2 class="card-title"><i class="fas fa-user-clock"></i> Incoming requests</h2>
                    <span class="badge warn" id="badge-pending-count">0 New</span>
                </div>
                <div id="pending-container"><div class="loading">Checking for updates…</div></div>
            </section>

            <section class="card">
                <div class="card-head">
                    <h2 class="card-title"><i class="fas fa-list-check"></i> Today's agenda</h2>
                </div>
                <div id="agenda-container" style="margin-bottom:16px"></div>
                <button class="btn btn-soft btn-block" onclick="switchTab('schedule')"><i class="far fa-calendar-days"></i> Open full calendar</button>
            </section>
        </div>
    </div>

    {{-- ── SCHEDULE VIEW ── --}}
    <div id="view-schedule" class="hidden">
        <div class="section-title">
            <h3>Full schedule</h3>
        </div>
        <div class="legend">
            <span class="lg"><i style="background:#5b5fe6"></i>Confirmed</span>
            <span class="lg"><i style="background:#12a37a"></i>Completed</span>
            <span class="lg"><i style="background:#64748b"></i>Closed</span>
            <span class="lg"><i style="background:#d6455d"></i>Cancelled</span>
            <span class="lg"><i style="background:#e08a00"></i>Pending</span>
        </div>
        <div id="calendar"></div>
    </div>

</main>

<script>
    const API_BASE = '/api/v1';
    const HEADERS = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
    };

    const escHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    document.addEventListener('DOMContentLoaded', () => {
        loadPendingRequests();
        loadAgenda();
    });

    // TABS
    let calendarInitialized = false;

    function switchTab(tab) {
        document.querySelectorAll('.pt-nav button').forEach(el => el.classList.remove('on'));
        document.getElementById(`tab-${tab}`).classList.add('on');

        if (tab === 'dashboard') {
            document.getElementById('view-dashboard').classList.remove('hidden');
            document.getElementById('view-schedule').classList.add('hidden');
            loadPendingRequests();
        } else {
            document.getElementById('view-dashboard').classList.add('hidden');
            document.getElementById('view-schedule').classList.remove('hidden');
            if (!calendarInitialized) {
                initCalendar();
                calendarInitialized = true;
            }
        }
    }

    // HELPER: Get Color based on Status
    function getStatusColor(status) {
        switch (status.toLowerCase()) {
            case 'confirmed': return '#5b5fe6';   // Indigo – upcoming
            case 'completed': return '#12a37a';   // Green – done
            case 'closed': return '#64748b';      // Slate – archived
            case 'cancelled':
            case 'expired': return '#d6455d';     // Red
            case 'pending': return '#e08a00';     // Amber – waiting for approval
            default: return '#56b4ea';            // Sky
        }
    }

    // CALENDAR INITIALIZATION
    function initCalendar() {
        var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            height: 'auto',

            // 1. Fetch Events
            events: async (info, success, failure) => {
                try {
                    const res = await fetch(`${API_BASE}/appointments/my-schedule`, {
                        headers: HEADERS
                    });
                    const json = await res.json();

                    if (json.status) {
                        success(json.data.map(apt => ({
                            title: `${apt.user.name} (${apt.mode})`, // Show Name + Mode
                            start: apt.scheduled_at,
                            url: `/meet/${apt.appointment_id}`,
                            color: getStatusColor(apt.status),
                            extendedProps: {
                                status: apt.status,
                                notes: apt.notes
                            }
                        })));
                    }
                } catch (e) {
                    failure(e);
                }
            },

            // 2. Handle Click (Prevent joining if cancelled/closed)
            eventClick: (info) => {
                info.jsEvent.preventDefault(); // Stop default browser navigation

                const status = info.event.extendedProps.status;
                const url = info.event.url;

                if (['cancelled', 'expired', 'closed', 'completed'].includes(status)) {
                    alert(`This appointment is ${status.toUpperCase()} and cannot be joined.`);
                } else {
                    if (confirm(`Join video session with ${info.event.title}?`)) {
                        window.open(url, '_blank');
                    }
                }
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
                    <div class="row-item">
                        <div class="avatar">${escHtml(apt.user.name.charAt(0).toUpperCase())}</div>
                        <div class="row-main">
                            <div class="row-title">${escHtml(apt.user.name)}</div>
                            <div class="row-meta">
                                <span><i class="far fa-clock"></i>${escHtml(new Date(apt.scheduled_at).toLocaleString('en-US', {weekday:'short', month:'short', day:'numeric', hour:'numeric', minute:'2-digit'}))}</span>
                                ${apt.mode ? `<span class="mode-chip"><i class="fas fa-${apt.mode === 'audio' ? 'phone' : 'video'}"></i>${escHtml(apt.mode)}</span>` : ''}
                            </div>
                        </div>
                        <div class="row-actions">
                            <button onclick="acceptAppointment('${escHtml(apt.appointment_id)}')" class="btn btn-primary">Accept <i class="fas fa-check"></i></button>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `<div class="empty"><i class="fas fa-inbox"></i><h5>No new patient requests</h5><p>New requests will appear here.</p></div>`;
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
                        <div class="row-item agenda">
                            <div class="row-main">
                                <div class="row-title">${escHtml(apt.user.name)}</div>
                                <div class="agenda-time">
                                    <i class="fas fa-${apt.mode === 'audio' ? 'phone' : 'video'}" style="margin-right:6px"></i>${escHtml(new Date(apt.scheduled_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}))}
                                </div>
                            </div>
                            <div class="row-actions"><a href="/meet/${encodeURIComponent(apt.appointment_id)}" class="btn btn-primary">Join</a></div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="empty"><i class="far fa-calendar-check"></i><h5>Nothing scheduled today</h5><p>Enjoy the breathing room.</p></div>';
                }
            } else {
                container.innerHTML = '<div class="empty"><i class="far fa-calendar-check"></i><h5>Nothing scheduled today</h5><p>Enjoy the breathing room.</p></div>';
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

<script>
    // ---- Notification bell (appointment reminders etc.) ----
    (function () {
        const API = '/api/v1';
        const H = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        };
        const btn = document.getElementById('notifBtn');
        const panel = document.getElementById('notifPanel');
        const badge = document.getElementById('notifBadge');
        const list = document.getElementById('notifList');
        let items = [];

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        function ago(iso) {
            const mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
            if (mins < 1) return 'Just now';
            if (mins < 60) return mins + ' min ago';
            const h = Math.round(mins / 60);
            if (h < 24) return h + ' hr ago';
            return Math.round(h / 24) + ' day(s) ago';
        }

        function render() {
            const unread = items.filter((n) => !n.isRead).length;
            badge.style.display = unread ? 'flex' : 'none';
            badge.textContent = unread > 99 ? '99+' : unread;

            if (!items.length) {
                list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
                return;
            }
            list.innerHTML = items.map((n) => `
                <div class="notif-item ${n.isRead ? '' : 'unread'}" data-id="${esc(n.id)}">
                    <div class="t">${esc(n.title)}</div>
                    <div class="b">${esc(n.body)}</div>
                    <div class="w">${esc(ago(n.timestamp))}</div>
                </div>`).join('');
        }

        async function load() {
            try {
                const res = await fetch(`${API}/notifications?limit=30`, { headers: H, credentials: 'same-origin' });
                if (!res.ok) return;
                const json = await res.json();
                if (json.success) { items = json.data; render(); }
            } catch (e) { /* offline: keep what we have */ }
        }

        async function markRead(id) {
            const n = items.find((x) => x.id === id);
            if (!n || n.isRead) return;
            n.isRead = true; render();
            try { await fetch(`${API}/notifications/${encodeURIComponent(id)}/read`, { method: 'POST', headers: H, credentials: 'same-origin' }); } catch (e) {}
        }

        btn.addEventListener('click', (e) => { e.stopPropagation(); panel.classList.toggle('open'); if (panel.classList.contains('open')) load(); });
        document.addEventListener('click', (e) => { if (!panel.contains(e.target)) panel.classList.remove('open'); });
        list.addEventListener('click', (e) => { const el = e.target.closest('.notif-item'); if (el) markRead(el.dataset.id); });
        document.getElementById('notifReadAll').addEventListener('click', async () => {
            items.forEach((n) => (n.isRead = true)); render();
            try { await fetch(`${API}/notifications/read-all`, { method: 'POST', headers: H, credentials: 'same-origin' }); } catch (e) {}
        });

        load();
        setInterval(load, 60000); // pick up new reminders without a page refresh
    })();
</script>
<script src="{{ asset('js/mm-portal.js') }}"></script>
</body>
</html>
