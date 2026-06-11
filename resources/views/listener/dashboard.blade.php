<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | MannMitra Listener</title>

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    {{-- Custom CSS (no Bootstrap — custom only) --}}
    <link rel="stylesheet" href="{{ asset('css/listener_dashboard.css') }}">
</head>
<body>

    {{-- ── NAVBAR ──────────────────────────────────────────── --}}
    <nav class="navbar-custom">
        <a class="brand-text" href="#">
            <i class="fas fa-hands-helping"></i>
            MannMitra Listener
        </a>

        <div class="navbar-right">
            <span class="online-dot" title="You are online"></span>
            <span class="navbar-user">{{ Auth::user()->name }}</span>

            {{-- Theme Toggle --}}
            <button class="btn-theme-toggle" id="themeToggle" title="Toggle theme" aria-label="Toggle dark/light mode">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            {{-- Logout --}}
            <form action="{{ route('admin.logout') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn-logout">Sign out</button>
            </form>
        </div>
    </nav>

    {{-- ── MAIN ────────────────────────────────────────────── --}}
    <div class="page-container">

        {{-- Page header --}}
        <div class="page-header">
            <h1>Good {{ \Carbon\Carbon::now()->format('G') < 12 ? 'morning' : (\Carbon\Carbon::now()->format('G') < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', Auth::user()->name)[0] }}</h1>
            <p>You are online.
                @if($poolTickets->count() > 0)
                    {{ $poolTickets->count() }} {{ Str::plural('person', $poolTickets->count()) }} waiting for support.
                @else
                    All caught up — no one waiting right now.
                @endif
            </p>
        </div>

        {{-- Stats row --}}
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Waiting</div>
                <div class="stat-value">{{ $poolTickets->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active sessions</div>
                <div class="stat-value">{{ $myTickets->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Closed today</div>
                <div class="stat-value">{{ $closedTodayCount ?? 0 }}</div>
            </div>
        </div>

        {{-- Two column grid --}}
        <div class="main-grid">

            {{-- ── LEFT: Open Requests Pool ─────────────────── --}}
            <div class="mm-card">
                <div class="mm-card-header">
                    <h2 class="mm-card-title">
                        <i class="fas fa-inbox"></i>
                        Open requests
                    </h2>
                    <span class="badge-blue">{{ $poolTickets->count() }} waiting</span>
                </div>

                @if($poolTickets->isEmpty())
                    <div class="empty-state">
                        <i class="fas fa-mug-hot"></i>
                        <h5>All quiet</h5>
                        <p>No one is waiting right now. Check back soon.</p>
                    </div>
                @else
                    @foreach($poolTickets as $ticket)
                    <div class="request-item">
                        <div class="user-avatar">
                            {{ strtoupper(substr($ticket->user->name ?? 'G', 0, 1)) }}
                        </div>

                        <div class="request-info">
                            <h6>{{ $ticket->subject }}</h6>
                            <div class="request-meta">
                                <i class="far fa-user"></i>
                                {{ $ticket->user->name ?? 'Guest User' }}
                                <span class="meta-sep">·</span>
                                <i class="far fa-clock"></i>
                                {{ $ticket->created_at->diffForHumans() }}
                            </div>
                        </div>

                        <form action="{{ route('listener.ticket.accept', $ticket->id) }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn-accept">
                                Accept <i class="fas fa-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                    @endforeach
                @endif
            </div>

            {{-- ── RIGHT: Active Sessions ────────────────────── --}}
            <div class="mm-card">
                <div class="mm-card-header">
                    <h2 class="mm-card-title">
                        <i class="fas fa-comments"></i>
                        Active sessions
                    </h2>
                    <span class="badge-green">{{ $myTickets->count() }} live</span>
                </div>

                @if($myTickets->isEmpty())
                    <div class="empty-state">
                        <i class="far fa-comment-dots"></i>
                        <h5>No active chats</h5>
                        <p>Accept a request from the pool to start a session.</p>
                    </div>
                @else
                    @foreach($myTickets as $ticket)
                    <div class="session-item">
                        <div class="user-avatar green">
                            {{ strtoupper(substr($ticket->user->name ?? 'G', 0, 1)) }}
                        </div>

                        <div class="session-info">
                            <h6>{{ $ticket->user->name ?? 'Guest' }}</h6>
                            <span class="session-preview">"{{ Str::limit($ticket->subject, 42) }}"</span>
                        </div>

                        <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                            <span class="badge-live">Live</span>
                            <a href="{{ route('chat', $ticket->ticket_id) }}" class="btn-continue">
                                Open <i class="fas fa-arrow-right" style="font-size:11px;"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>

        </div>{{-- end .main-grid --}}

    </div>{{-- end .page-container --}}

    {{-- ── THEME TOGGLE SCRIPT ──────────────────────────────── --}}
    <script>
        (function () {
            const html     = document.documentElement;
            const btn      = document.getElementById('themeToggle');
            const icon     = document.getElementById('themeIcon');
            const STORAGE  = 'mm_theme';

            // Apply saved theme on load
            const saved = localStorage.getItem(STORAGE) || 'light';
            html.setAttribute('data-theme', saved);
            updateIcon(saved);

            btn.addEventListener('click', function () {
                const current = html.getAttribute('data-theme');
                const next    = current === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', next);
                localStorage.setItem(STORAGE, next);
                updateIcon(next);
            });

            function updateIcon(theme) {
                if (theme === 'dark') {
                    icon.className = 'fas fa-sun';
                    btn.title = 'Switch to light mode';
                } else {
                    icon.className = 'fas fa-moon';
                    btn.title = 'Switch to dark mode';
                }
            }
        })();
    </script>

</body>
</html>