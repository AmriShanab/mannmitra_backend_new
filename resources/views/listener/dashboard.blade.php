<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | MannMitra Listener</title>
    @include('partials.portal-head')
</head>
<body>

@php
    $hour = (int) \Carbon\Carbon::now()->format('G');
    $greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
    $firstName = explode(' ', trim(Auth::user()->name))[0];
@endphp

{{-- ── TOP BAR ─────────────────────────────────────────────── --}}
<header class="pt-topbar">
    <div class="pt-topbar-in">
        <a class="pt-brand" href="{{ route('listener.dashboard') }}">
            <img src="{{ asset('images/app_icon.png') }}" alt="MannMitra">
            <span class="bn">MannMitra</span>
            <span class="role-pill">Listener</span>
        </a>

        <div class="pt-right">
            <div class="user-chip">
                <div class="avatar sm">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                <span class="online-dot" title="You are online"></span>
                <span class="nm">{{ Auth::user()->name }}</span>
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

    {{-- Hero --}}
    <section class="hero-card">
        <div class="hero-top">
            <div>
                <h1>Good {{ $greeting }}, {{ $firstName }} 👋</h1>
                <p>
                    @if($poolTickets->count() > 0)
                        {{ $poolTickets->count() }} {{ Str::plural('person', $poolTickets->count()) }} waiting for support. Your listening makes a real difference.
                    @else
                        All caught up. No one is waiting right now. Take a breath.
                    @endif
                </p>
            </div>
            <span class="hero-date"><i class="far fa-calendar"></i>&nbsp; {{ \Carbon\Carbon::now()->format('D, d M Y') }}</span>
        </div>
    </section>

    {{-- Stats --}}
    <section class="stats">
        <div class="stat">
            <div class="stat-ic warn"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-label">Waiting</div><div class="stat-value">{{ $poolTickets->count() }}</div></div>
        </div>
        <div class="stat">
            <div class="stat-ic"><i class="fas fa-comments"></i></div>
            <div><div class="stat-label">Active sessions</div><div class="stat-value">{{ $myTickets->count() }}</div></div>
        </div>
        <div class="stat">
            <div class="stat-ic ok"><i class="fas fa-circle-check"></i></div>
            <div><div class="stat-label">Closed today</div><div class="stat-value">{{ $closedTodayCount ?? 0 }}</div></div>
        </div>
    </section>

    <div class="grid-2">

        {{-- Open requests --}}
        <section class="card">
            <div class="card-head">
                <h2 class="card-title"><i class="fas fa-inbox"></i> Open requests</h2>
                <span class="badge warn">{{ $poolTickets->count() }} waiting</span>
            </div>

            @if($poolTickets->isEmpty())
                <div class="empty">
                    <i class="fas fa-mug-hot"></i>
                    <h5>All quiet</h5>
                    <p>No one is waiting right now. Check back soon.</p>
                </div>
            @else
                @foreach($poolTickets as $ticket)
                    <div class="row-item">
                        <div class="avatar">{{ strtoupper(substr($ticket->user->name ?? 'G', 0, 1)) }}</div>
                        <div class="row-main">
                            <div class="row-title">{{ $ticket->subject }}</div>
                            <div class="row-meta">
                                <span><i class="far fa-user"></i>{{ $ticket->user->name ?? 'Guest User' }}</span>
                                <span><i class="far fa-clock"></i>{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <form class="row-actions" action="{{ route('listener.ticket.accept', $ticket->id) }}" method="POST" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-primary">Accept <i class="fas fa-arrow-right"></i></button>
                        </form>
                    </div>
                @endforeach
            @endif
        </section>

        {{-- Active sessions --}}
        <section class="card">
            <div class="card-head">
                <h2 class="card-title"><i class="fas fa-comments"></i> Active sessions</h2>
                <span class="badge ok live">{{ $myTickets->count() }} live</span>
            </div>

            @if($myTickets->isEmpty())
                <div class="empty">
                    <i class="far fa-comment-dots"></i>
                    <h5>No active chats</h5>
                    <p>Accept a request from the pool to start a session.</p>
                </div>
            @else
                @foreach($myTickets as $ticket)
                    <div class="row-item">
                        <div class="avatar ok">{{ strtoupper(substr($ticket->user->name ?? 'G', 0, 1)) }}</div>
                        <div class="row-main">
                            <div class="row-title">{{ $ticket->user->name ?? 'Guest' }}</div>
                            <div class="row-meta"><span>“{{ Str::limit($ticket->subject, 42) }}”</span></div>
                        </div>
                        <div class="row-actions">
                            <span class="badge ok live">Live</span>
                            <a href="{{ route('chat', $ticket->ticket_id) }}" class="btn btn-soft">Open <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                @endforeach
            @endif
        </section>

    </div>
</main>

<script src="{{ asset('js/mm-portal.js') }}"></script>
</body>
</html>
