<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listener Dashboard | MannMitra</title>
    
    {{-- 1. Modern Fonts & Icons --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    {{-- 2. Bootstrap 5 (Latest) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    {{-- 3. Custom CSS --}}
    <link rel="stylesheet" href="{{ asset('css/listener_dashboard.css') }}">
</head>
<body>

    {{-- NAVBAR --}}
    <nav class="navbar navbar-custom sticky-top">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand brand-text" href="#">
                <i class="fas fa-hands-helping me-2"></i> MannMitra Listener
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted d-none d-md-block">Welcome, {{ Auth::user()->name }}</span>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    {{-- MAIN CONTENT --}}
    <div class="container py-5">
        
        {{-- Header Section --}}
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold text-dark">Dashboard</h2>
                <p class="text-muted">Manage your sessions and accept new requests.</p>
            </div>
        </div>

        <div class="row g-4">
            
            {{-- LEFT COLUMN: NEW REQUESTS POOL --}}
            <div class="col-lg-7 col-md-12">
                <div class="custom-card">
                    <div class="card-header-custom">
                        <h5 class="card-title text-primary">
                            <i class="fas fa-inbox"></i> Open Requests (Pool)
                        </h5>
                        <span class="badge bg-primary rounded-pill">{{ $poolTickets->count() }} New</span>
                    </div>
                    
                    <div class="card-body p-0">
                        @if($poolTickets->isEmpty())
                            <div class="empty-state">
                                <i class="fas fa-coffee empty-icon"></i>
                                <h5>All quiet for now</h5>
                                <p>Waiting for new users to request support...</p>
                            </div>
                        @else
                            <div class="d-flex flex-column">
                                @foreach($poolTickets as $ticket)
                                <div class="request-item">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar">
                                            {{ substr($ticket->user->name ?? 'G', 0, 1) }}
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold text-dark">{{ $ticket->subject }}</h6>
                                            <small class="text-muted">
                                                <i class="far fa-user me-1"></i> {{ $ticket->user->name ?? 'Guest User' }}
                                                <span class="mx-2">•</span>
                                                <i class="far fa-clock me-1"></i> {{ $ticket->created_at->diffForHumans() }}
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <form action="{{ route('listener.ticket.accept', $ticket->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-accept shadow-sm">
                                            Accept <i class="fas fa-arrow-right ms-2"></i>
                                        </button>
                                    </form>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- RIGHT COLUMN: ACTIVE CHATS --}}
            <div class="col-lg-5 col-md-12">
                <div class="custom-card">
                    <div class="card-header-custom">
                        <h5 class="card-title text-success">
                            <i class="fas fa-comments"></i> Active Sessions
                        </h5>
                    </div>
                    
                    <div class="card-body p-0">
                        @if($myTickets->isEmpty())
                            <div class="empty-state">
                                <i class="far fa-comment-dots empty-icon"></i>
                                <h5>No active chats</h5>
                                <p>Accept a request from the pool to start chatting.</p>
                            </div>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($myTickets as $ticket)
                                <div class="list-group-item p-4 border-0 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar bg-success bg-opacity-10 text-success">
                                                {{ substr($ticket->user->name ?? 'G', 0, 1) }}
                                            </div>
                                            <span class="fw-bold">{{ $ticket->user->name ?? 'Guest' }}</span>
                                        </div>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Live</span>
                                    </div>
                                    
                                    <p class="text-muted small mb-3 ps-5">
                                        "{{ Str::limit($ticket->subject, 50) }}"
                                    </p>
                                    
                                    <div class="text-end">
                                        <a href="{{ route('chat', $ticket->ticket_id) }}" class="btn btn-continue w-100">
                                            Continue Chatting <i class="fas fa-comment-alt ms-2"></i>
                                        </a>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div> {{-- End Row --}}
    </div> {{-- End Container --}}

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>