@extends('adminlte::page')

@section('title', 'MannMitra Dashboard')

@section('content_header')
    <h1>Crisis Command Center</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['total_users'] }}</h3>
                    <p>Total Users</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['sessions_today'] }}</h3>
                    <p>Sessions Today</p>
                </div>
                <div class="icon">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $stats['pending_alerts'] }}</h3>
                    <p>Critical Alerts</p>
                </div>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-danger">
        <div class="card-header">
            <h3 class="card-title">🚨 Active Crisis Alerts</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Trigger Word</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alerts as $alert)
                        <tr>
                            <td>{{ $alert->session->user->id ?? 'Unknown' }}</td>
                            <td><span class="badge badge-danger">{{ $alert->trigger_keyword }}</span></td>
                            <td>{{ $alert->created_at->diffForHumans() }}</td>
                            <td>
                                <button class="btn btn-sm btn-primary view-chat-btn"
                                    data-session-id="{{ $alert->session_id }}">
                                    View Chat
                                </button>

                                <button class="btn btn-sm btn-success resolve-btn" data-alert-id="{{ $alert->id }}">
                                    Resolve
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No active alerts. Good job!</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-warning">
        <div class="card-header">
            <h3 class="card-title">⏳ Pending Professionals</h3>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Approve</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($approvals as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ ucfirst($user->role) }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <form action="{{ route('admin.approve', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No pending approvals.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="chatModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">Crisis Context</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="height: 400px; overflow-y: auto; background: #f4f6f9;">
                    <div id="chatContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
    @csrf
</form>
@stop

@section('css')
    {{-- Add custom CSS here --}}
@stop

@section('js')
<script>
    $(document).ready(function() {
        
        // 1. Handle "View Chat" Click
        $('.view-chat-btn').click(function() {
            var sessionId = $(this).data('session-id');
            var modalBody = $('#chatContent');
            
            // Clear previous chat and show loader
            modalBody.html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
            $('#chatModal').modal('show');

            // Fetch Data via AJAX
            $.ajax({
                url: '/admin/chat-history/' + sessionId,
                type: 'GET',
                success: function(response) {
                    // --- FIX FOR "forEach is not a function" ---
                    // Detect if response is array or object-wrapped
                    var messages = Array.isArray(response) ? response : response.data;
                    
                    // Fallback to empty array if still invalid
                    if(!Array.isArray(messages)) messages = [];

                    var html = '';
                    
                    if (messages.length === 0) {
                         html = '<div class="text-center text-muted p-3">No messages found for this session.</div>';
                    } else {
                        messages.forEach(function(msg) {
                            var isUser = (msg.sender === 'user');
                            var align = isUser ? 'text-right' : 'text-left';
                            var color = isUser ? 'bg-primary' : 'bg-gray';
                            var senderName = isUser ? 'User' : 'MannMitra AI';

                            // Format Time
                            var time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                            html += `
                                <div class="${align} mb-3">
                                    <small class="text-muted">${senderName} • ${time}</small>
                                    <div class="d-inline-block p-2 rounded ${color} ${isUser ? 'text-white' : ''}" style="max-width: 75%;">
                                        ${msg.content}
                                    </div>
                                </div>
                            `;
                        });
                    }

                    modalBody.html(html);
                    
                    // Scroll to bottom
                    var container = $('.modal-body');
                    container.scrollTop(container[0].scrollHeight);
                },
                error: function(xhr) {
                    console.error(xhr);
                    modalBody.html('<div class="text-center text-danger p-3">Error loading chat history.</div>');
                }
            });
        });

        // 2. Handle "Resolve" Click
        $('.resolve-btn').click(function() {
            if(!confirm('Are you sure you want to mark this alert as resolved?')) return;

            var btn = $(this);
            var alertId = btn.data('alert-id');

            $.post('/admin/alert/resolve/' + alertId, {
                _token: '{{ csrf_token() }}' // Laravel CSRF security
            }, function(response) {
                if(response.success) {
                    // Remove the row from the table smoothly
                    btn.closest('tr').fadeOut();
                    
                    // Optional: Update the "Critical Alerts" counter at the top
                    var countBox = $('.small-box.bg-danger h3');
                    var currentCount = parseInt(countBox.text());
                    if(!isNaN(currentCount)) {
                         countBox.text(Math.max(0, currentCount - 1));
                    }
                }
            });
        });

    });
</script>
@stop