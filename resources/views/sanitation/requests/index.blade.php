{{-- resources/views/sanitation/requests/index.blade.php --}}

@php
    $user = auth()->user();
    $currentPersonnel = $user->sanitationPersonnel;
    $currentPersonnelId = $currentPersonnel?->id;

    $isAdmin             = $user->isAdmin() || $user->isSuperAdmin();
    $isRootSupervisor    = $currentPersonnel
        && $currentPersonnel->isSupervisor()
        && is_null($currentPersonnel->supervisor_id);
    $isSubSupervisor     = $currentPersonnel
        && $currentPersonnel->isSupervisor()
        && !is_null($currentPersonnel->supervisor_id);

    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';

    // ✅ Fallback guards — protects the blade from callers that
    //    forget to pass the lookup arrays (e.g. showRequest()'s
    //    fallback path rendering this view with minimal context).
    $requests         = $requests         ?? collect();
    $statuses         = $statuses         ?? \App\Models\WasteCollectionRequest::getStatuses();
    $priorities       = $priorities       ?? \App\Models\WasteCollectionRequest::getPriorities();
    $wasteTypes       = $wasteTypes       ?? \App\Models\WasteCollectionRequest::getWasteTypes();
    $approvalStatuses = $approvalStatuses ?? \App\Models\WasteCollectionRequest::getApprovalStatuses();

    // If `$requests` is a plain Collection (not a paginator), wrap it so
    // `->hasPages()` / `->appends()` / `->links()` calls don't crash.
    if (!($requests instanceof \Illuminate\Pagination\LengthAwarePaginator)
        && !($requests instanceof \Illuminate\Pagination\Paginator)) {
        $requests = new \Illuminate\Pagination\LengthAwarePaginator(
            $requests,
            $requests->count(),
            20,
            1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
@endphp

@extends($layout)

@section('title', 'Collection Requests')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        {{-- Flash --}}
        @if(session('success'))
            <div class="card p-3 mb-4" style="background-color: rgba(34,197,94,0.1); border-color: #22c55e;">
                <div class="flex items-center gap-2" style="color: #16a34a;">
                    <i class="fas fa-check-circle"></i>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="card p-3 mb-4" style="background-color: rgba(239,68,68,0.1); border-color: #ef4444;">
                <div class="flex items-center gap-2" style="color: #dc2626;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                    Collection Requests
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    @if($isRootSupervisor)
                        All requests across your team hierarchy
                    @elseif($isSubSupervisor)
                        Requests for your team
                    @elseif($isAdmin)
                        All waste collection requests across the system
                    @else
                        Your waste collection requests
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('sanitation.requests.pending') }}" class="btn-warning btn-sm">
                    <i class="fas fa-clock mr-2"></i> Pending
                </a>
                @if($isAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="btn-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Dashboard
                    </a>
                @else
                    <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Dashboard
                    </a>
                @endif
            </div>
        </div>

        <!-- Scope banner for supervisors -->
        @if($isRootSupervisor)
            <div class="card p-3 mb-4"
                 style="background-color: rgba(245, 158, 11, 0.08);
                        border-left: 4px solid #f59e0b;">
                <div class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                    <i class="fas fa-crown" style="color: #f59e0b;"></i>
                    <span>
                        <strong style="color: var(--text-primary);">Root Supervisor View</strong> —
                        you're seeing every request across your team's hierarchy.
                    </span>
                </div>
            </div>
        @endif

        <!-- Filters -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Priority</label>
                    <select name="priority" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Priorities</option>
                        @foreach($priorities as $key => $label)
                            <option value="{{ $key }}" {{ request('priority') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Waste Type</label>
                    <select name="waste_type" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Types</option>
                        @foreach($wasteTypes as $key => $label)
                            <option value="{{ $key }}" {{ request('waste_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Approval status filter --}}
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Approval</label>
                    <select name="approval_status" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="">All Approvals</option>
                        @foreach($approvalStatuses as $key => $label)
                            <option value="{{ $key }}" {{ request('approval_status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="Property or address...">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary btn-sm flex-1">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="{{ route('sanitation.requests.index') }}" class="btn-secondary btn-sm">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Waste Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Priority</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Assigned To</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @forelse($requests as $request)
                            @php
                                $isUnassigned = empty($request->assigned_to);
                                $isAssignedToMe = $currentPersonnelId && (int) $request->assigned_to === (int) $currentPersonnelId;
                                $canAssign = in_array($request->status, ['pending', 'assigned'])
                                    && $request->approval_status !== \App\Models\WasteCollectionRequest::APPROVAL_PENDING;
                            @endphp
                            <tr class="hover:bg-opacity-5" style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3 text-sm font-medium" style="color: var(--text-primary);">
                                    #{{ $request->id }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                        {{ $request->property->property_name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->property->digital_address ?? 'No address' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--text-primary);">
                                        {{ $wasteTypes[$request->waste_type] ?? $request->waste_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($request->priority == 'emergency') bg-red-100 text-red-600
                                        @elseif($request->priority == 'high') bg-orange-100 text-orange-600
                                        @elseif($request->priority == 'medium') bg-yellow-100 text-yellow-600
                                        @else bg-blue-100 text-blue-600 @endif">
                                        {{ ucfirst($request->priority) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($request->status == 'completed') bg-green-100 text-green-600
                                        @elseif($request->status == 'pending') bg-yellow-100 text-yellow-600
                                        @elseif($request->status == 'cancelled') bg-red-100 text-red-600
                                        @elseif($request->status == 'en_route') bg-blue-100 text-blue-600
                                        @elseif($request->status == 'arrived') bg-purple-100 text-purple-600
                                        @else bg-gray-100 text-gray-600 @endif">
                                        {{ $statuses[$request->status] ?? ucfirst($request->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($isUnassigned)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold"
                                              style="background-color: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                                            <i class="fas fa-user-slash"></i> Unassigned
                                        </span>
                                    @elseif($isAssignedToMe)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold"
                                              style="background-color: rgba(34, 197, 94, 0.15); color: #22c55e;">
                                            <i class="fas fa-user-check"></i> You
                                        </span>
                                    @else
                                        <span class="text-sm" style="color: var(--text-primary);">
                                            {{ $request->assignedTo?->full_name ?? 'Unknown' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm" style="color: var(--text-secondary);">
                                    <div>{{ $request->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs">{{ $request->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2 items-center">
                                        <a href="{{ route('sanitation.requests.show', $request) }}"
                                           class="text-sm hover:underline"
                                           style="color: var(--primary);"
                                           title="View details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if($canAssign && ($isRootSupervisor || $isSubSupervisor || $isAdmin))
                                            <a href="{{ route('sanitation.requests.show', $request) }}#assign"
                                               class="text-sm hover:underline"
                                               style="color: var(--warning);"
                                               title="Open request to assign">
                                                <i class="fas fa-user-plus"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-inbox text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No collection requests found</p>
                                    @if(request()->hasAny(['status', 'priority', 'waste_type', 'search', 'approval_status']))
                                        <a href="{{ route('sanitation.requests.index') }}"
                                           class="text-xs mt-2 inline-block"
                                           style="color: var(--primary);">
                                            <i class="fas fa-undo mr-1"></i> Clear filters
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $requests->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
    }

    .btn-primary, .btn-secondary, .btn-warning {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(to right, var(--primary), var(--info));
        color: white;
    }
    .btn-primary:hover { opacity: 0.9; color: white; text-decoration: none; }

    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border-color: var(--border-color);
    }
    .btn-secondary:hover {
        background-color: var(--bg-tertiary, var(--bg-secondary));
        color: var(--text-primary);
        text-decoration: none;
    }

    .btn-warning {
        background-color: var(--warning);
        color: white;
    }
    .btn-warning:hover { opacity: 0.9; color: white; text-decoration: none; }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }
</style>
@endpush