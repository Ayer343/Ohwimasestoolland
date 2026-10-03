{{-- resources/views/sms/log-detail.blade.php --}}
@extends('layouts.app')

@section('title', 'SMS Log Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i>
            SMS Log Details
            <span class="text-sm ml-2 px-2 py-0.5 rounded-full {{ $log->status_badge }}">
                <i class="fas {{ $log->status_icon }} mr-1"></i>
                {{ ucfirst($log->status) }}
            </span>
        </h1>
        <div class="flex gap-2">
            <a href="{{ route('sms.logs') }}" class="px-4 py-2 rounded-lg font-medium transition-all hover:scale-105"
               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                <i class="fas fa-arrow-left mr-2"></i> Back to Logs
            </a>
            <button onclick="deleteLog({{ $log->id }})" class="px-4 py-2 rounded-lg font-medium transition-all hover:scale-105"
                    style="background-color: var(--danger); color: white;">
                <i class="fas fa-trash-alt mr-2"></i> Delete
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg" style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e;">
            <p style="color: #22c55e;">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
            <p style="color: #ef4444;">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2">
            <div class="rounded-lg p-6" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Message Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Phone Number</label>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $log->phone_number ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Provider</label>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">{{ $log->provider_display ?? $log->provider ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Message</label>
                        <div class="p-3 rounded-lg mt-1" style="background-color: var(--bg-primary); border: 1px solid var(--border-color);">
                            <p class="text-sm whitespace-pre-wrap" style="color: var(--text-primary);">{{ $log->message ?? 'No message content' }}</p>
                        </div>
                        @if(isset($log->message_length))
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                Length: {{ $log->message_length }} characters | {{ $log->message_parts ?? 1 }} SMS part(s)
                            </div>
                        @endif
                    </div>
                    
                    @if($log->error_message)
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Error</label>
                            <div class="p-3 rounded-lg mt-1" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                                <p class="text-sm" style="color: #ef4444;">{{ $log->error_message }}</p>
                                @if($log->error_code)
                                    <span class="text-xs mt-1 inline-block px-2 py-0.5 rounded-full" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                        Code: {{ $log->error_code }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Response Details -->
            @if($log->response)
                <div class="rounded-lg p-6 mt-6" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Provider Response</h3>
                    <pre class="text-sm p-3 rounded-lg overflow-x-auto" style="background-color: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); max-height: 300px; overflow-y: auto;">
{{ json_encode($log->response, JSON_PRETTY_PRINT) }}
                    </pre>
                </div>
            @endif

            <!-- Additional Details -->
            @if($log->details)
                <div class="rounded-lg p-6 mt-6" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Additional Details</h3>
                    <pre class="text-sm p-3 rounded-lg overflow-x-auto" style="background-color: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); max-height: 200px; overflow-y: auto;">
{{ json_encode($log->details, JSON_PRETTY_PRINT) }}
                    </pre>
                </div>
            @endif
        </div>
        
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="rounded-lg p-6" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Metadata</h3>
                
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-0.5 rounded-full text-xs {{ $log->status_badge }}">
                                <i class="fas {{ $log->status_icon }} mr-1"></i>
                                {{ ucfirst($log->status) }}
                            </span>
                        </dd>
                    </div>
                    
                    @if($log->message_id)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Message ID</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">{{ $log->message_id }}</dd>
                        </div>
                    @endif
                    
                    @if(isset($log->external_id) && $log->external_id)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">External ID</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">{{ $log->external_id }}</dd>
                        </div>
                    @endif
                    
                    @if($log->sent_at)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Sent At</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ $log->sent_at->format('Y-m-d H:i:s') }}
                            </dd>
                        </div>
                    @endif
                    
                    @if($log->delivered_at)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Delivered At</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ $log->delivered_at->format('Y-m-d H:i:s') }}
                                @if($log->sent_at)
                                    <span class="text-xs block" style="color: var(--text-secondary);">
                                        ({{ $log->formatted_delivery_time ?? 'N/A' }})
                                    </span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    
                    @if($log->failed_at)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Failed At</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ $log->failed_at->format('Y-m-d H:i:s') }}
                            </dd>
                        </div>
                    @endif
                    
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Created At</dt>
                        <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                            <span class="text-xs block" style="color: var(--text-secondary);">
                                {{ $log->created_at->diffForHumans() }}
                            </span>
                        </dd>
                    </div>
                    
                    @if($log->user)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Sent By</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ $log->user->name }}
                                <span class="text-xs block" style="color: var(--text-secondary);">
                                    {{ $log->user->email }}
                                </span>
                            </dd>
                        </div>
                    @endif
                    
                    @if($log->is_test)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Type</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-flask mr-0.5"></i> Test Message
                                </span>
                            </dd>
                        </div>
                    @endif
                    
                    @if($log->execution_time)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Execution Time</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ number_format($log->execution_time, 2) }} ms
                            </dd>
                        </div>
                    @endif
                    
                    @if($log->status_code)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">HTTP Status Code</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ $log->status_code }}
                            </dd>
                        </div>
                    @endif
                    
                    @if(isset($log->retry_count) && $log->retry_count > 0)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Retry Count</dt>
                            <dd class="mt-1 text-sm" style="color: var(--text-primary);">
                                {{ $log->retry_count }}
                            </dd>
                        </div>
                    @endif
                    
                    @if(isset($log->cost) && $log->cost !== null)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Cost</dt>
                            <dd class="mt-1 text-sm font-semibold" style="color: var(--text-primary);">
                                {{ $log->currency ?? 'GHS' }} {{ number_format($log->cost, 4) }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

<script>
function deleteLog(id) {
    if (!confirm('Are you sure you want to delete this log?')) return;
    
    fetch(`{{ url('sms/logs') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = '{{ route('sms.logs') }}';
        } else {
            alert(data.message || 'Failed to delete log.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>

<style>
/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
    pre {
        background-color: #1a1a2e !important;
        color: #e2e8f0 !important;
    }
}
</style>
@endsection