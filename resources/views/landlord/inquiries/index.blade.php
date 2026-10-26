{{-- resources/views/landlord/inquiries/index.blade.php --}}
@extends('layouts.landlord')

@section('title', 'Unit Inquiries')

@php
    use App\Models\UnitInquiry;

    // Defensive defaults so the blade renders even if the controller
    // hasn't been updated to pass these variables.
    $inquiries   = $inquiries   ?? collect();
    $unreadCount = $unreadCount ?? 0;
    $stats       = $stats       ?? [
        'total'   => method_exists($inquiries, 'total') ? $inquiries->total() : $inquiries->count(),
        'new'     => 0,
        'replied' => 0,
        'unread'  => $unreadCount,
    ];
    $statuses    = $statuses    ?? [
        UnitInquiry::STATUS_NEW     => 'New',
        UnitInquiry::STATUS_READ    => 'Read',
        UnitInquiry::STATUS_REPLIED => 'Replied',
        UnitInquiry::STATUS_CLOSED  => 'Closed',
        UnitInquiry::STATUS_SPAM    => 'Spam',
    ];

    $statusColors = [
        UnitInquiry::STATUS_NEW     => 'primary',
        UnitInquiry::STATUS_READ    => 'info',
        UnitInquiry::STATUS_REPLIED => 'success',
        UnitInquiry::STATUS_CLOSED  => 'secondary',
        UnitInquiry::STATUS_SPAM    => 'danger',
    ];

    $filterStatus   = request('status', '');
    $searchTerm     = request('search', '');
    $fromDate       = request('from_date', '');
    $toDate         = request('to_date', '');
    $hasFilters     = $filterStatus || $searchTerm || $fromDate || $toDate;

    $statCards = [
        ['key' => 'total',   'label' => 'Total',   'icon' => 'fa-inbox',       'color' => 'primary'],
        ['key' => 'new',     'label' => 'New',     'icon' => 'fa-envelope',    'color' => 'warning'],
        ['key' => 'replied', 'label' => 'Replied', 'icon' => 'fa-reply',       'color' => 'success'],
        ['key' => 'unread',  'label' => 'Unread',  'icon' => 'fa-circle-dot',  'color' => 'info'],
    ];
@endphp

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4 space-y-4">

    {{-- ==================== HEADER ==================== --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-2xl font-bold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-envelope-open-text mr-2" style="color: var(--primary);"></i>
                Unit Inquiries
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                Messages and questions from prospective tenants about your units
            </p>
        </div>

        @if($inquiries->total() > 0)
            <span class="text-sm px-3 py-1 rounded-full"
                  style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                <i class="fas fa-list mr-1"></i>
                {{ $inquiries->total() }} total
            </span>
        @endif
    </div>

    {{-- ==================== FLASH ==================== --}}
    @if(session('success'))
        <div class="p-4 rounded-lg flex items-start"
             style="background: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
            <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
            <span class="text-sm" style="color: var(--text-primary);">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-lg flex items-start"
             style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <i class="fas fa-exclamation-circle mr-2 mt-0.5" style="color: var(--danger);"></i>
            <span class="text-sm" style="color: var(--text-primary);">{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-lg"
             style="background: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
            <p class="font-semibold text-sm mb-1" style="color: var(--danger);">
                <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the following:
            </p>
            <ul class="list-disc list-inside text-sm" style="color: var(--text-primary);">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ==================== STATS ==================== --}}
    @if($inquiries->total() > 0 || $hasFilters)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($statCards as $card)
                <div class="card p-3">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                             style="background: rgba(var(--{{ $card['color'] }}-rgb), 0.1);
                                    color: var(--{{ $card['color'] }});">
                            <i class="fas {{ $card['icon'] }} text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wide"
                               style="color: var(--text-secondary);">
                                {{ $card['label'] }}
                            </p>
                            <p class="text-lg font-bold truncate"
                               style="color: var(--text-primary);">
                                {{ number_format($stats[$card['key']] ?? 0) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ==================== FILTERS ==================== --}}
    @if($inquiries->total() > 0 || $hasFilters)
        <div class="card p-4">
            <form method="GET" action="{{ route('landlord.inquiries.index') }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-3">

                {{-- Search --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1"
                           style="color: var(--text-secondary);">
                        <i class="fas fa-search mr-1"></i> Search
                    </label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm"
                           style="color: var(--text-secondary);"></i>
                        <input type="text"
                               name="search"
                               value="{{ $searchTerm }}"
                               placeholder="Guest name, phone, email, message…"
                               class="w-full pl-9 pr-3 py-2 rounded-lg border"
                               style="background: var(--bg-primary);
                                      color: var(--text-primary);
                                      border-color: var(--border-color);">
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-medium mb-1"
                           style="color: var(--text-secondary);">
                        <i class="fas fa-tag mr-1"></i> Status
                    </label>
                    <select name="status"
                            class="w-full px-3 py-2 rounded-lg border"
                            style="background: var(--bg-primary);
                                   color: var(--text-primary);
                                   border-color: var(--border-color);">
                        <option value="">All statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($filterStatus === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Submit / Clear --}}
                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center"
                            style="background: var(--primary); color: white;">
                        <i class="fas fa-filter mr-2"></i> Filter
                    </button>
                    <a href="{{ route('landlord.inquiries.index') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center"
                       style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);"
                       title="Clear all filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>

                {{-- Date range (full-width row) --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1"
                           style="color: var(--text-secondary);">
                        <i class="fas fa-calendar mr-1"></i> From date
                    </label>
                    <input type="date" name="from_date" value="{{ $fromDate }}"
                           class="w-full px-3 py-2 rounded-lg border"
                           style="background: var(--bg-primary);
                                  color: var(--text-primary);
                                  border-color: var(--border-color);">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1"
                           style="color: var(--text-secondary);">
                        <i class="fas fa-calendar mr-1"></i> To date
                    </label>
                    <input type="date" name="to_date" value="{{ $toDate }}"
                           class="w-full px-3 py-2 rounded-lg border"
                           style="background: var(--bg-primary);
                                  color: var(--text-primary);
                                  border-color: var(--border-color);">
                </div>
            </form>

            @if($hasFilters)
                <div class="mt-3 flex items-center gap-2 flex-wrap">
                    <span class="text-xs" style="color: var(--text-secondary);">Active filters:</span>

                    @if($searchTerm)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-search"></i> "{{ $searchTerm }}"
                        </span>
                    @endif

                    @if($filterStatus)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-tag"></i>
                            {{ $statuses[$filterStatus] ?? $filterStatus }}
                        </span>
                    @endif

                    @if($fromDate)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-calendar"></i> From {{ $fromDate }}
                        </span>
                    @endif

                    @if($toDate)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                              style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                            <i class="fas fa-calendar"></i> To {{ $toDate }}
                        </span>
                    @endif

                    <a href="{{ route('landlord.inquiries.index') }}"
                       class="text-xs underline ml-2" style="color: var(--text-secondary);">
                        Clear
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- ==================== EMPTY STATES ==================== --}}
    @if($inquiries->isEmpty())
        <div class="card p-8 text-center">
            <i class="fas fa-envelope-open-text text-4xl mb-3"
               style="color: var(--text-secondary); opacity: 0.4;"></i>
            <p class="font-medium mb-2" style="color: var(--text-primary);">
                @if($hasFilters)
                    No inquiries match your filters.
                @else
                    No inquiries yet.
                @endif
            </p>
            <p class="text-sm" style="color: var(--text-secondary);">
                @if($hasFilters)
                    Try clearing the filters to see all inquiries.
                @else
                    When tenants inquire about your units, they'll show up here.
                @endif
            </p>
            @if($hasFilters)
                <a href="{{ route('landlord.inquiries.index') }}"
                   class="inline-block mt-4 px-4 py-2 rounded-lg text-sm font-medium"
                   style="background: var(--primary); color: white;">
                    <i class="fas fa-redo mr-2"></i> Show all inquiries
                </a>
            @endif
        </div>
    @else
        {{-- ==================== LIST ==================== --}}
        <div class="space-y-3">
            @foreach($inquiries as $inquiry)
                @php
                    $isUnread    = is_null($inquiry->landlord_viewed_at);
                    $statusColor = $statusColors[$inquiry->status] ?? 'secondary';
                    $statusLabel = $statuses[$inquiry->status]
                        ?? ucfirst(str_replace('_', ' ', $inquiry->status ?? 'new'));
                    $unit        = $inquiry->unit;
                    $property    = $unit?->property;
                    $guestName   = $inquiry->user->name ?? $inquiry->name ?? 'Anonymous';
                @endphp

                <a href="{{ route('landlord.inquiries.show', $inquiry->uuid) }}"
                   class="block card p-4 hover:shadow-md transition-all"
                   style="border-left: 4px solid {{ $isUnread ? 'var(--primary)' : 'var(--border-color)' }};">
                    <div class="flex items-start justify-between gap-4">

                        {{-- Left: content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                @if($isUnread)
                                    <span class="w-2 h-2 rounded-full flex-shrink-0"
                                          style="background: var(--primary);"
                                          title="Unread"></span>
                                @endif

                                <span class="font-semibold truncate"
                                      style="color: var(--text-primary);">
                                    {{ $guestName }}
                                </span>

                                <span class="px-2 py-0.5 rounded text-xs font-medium"
                                      style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15);
                                             color: var(--{{ $statusColor }});">
                                    {{ $statusLabel }}
                                </span>

                                @if($inquiry->email)
                                    <span class="text-[10px]" style="color: var(--text-secondary);">
                                        <i class="fas fa-envelope mr-1"></i>{{ $inquiry->email }}
                                    </span>
                                @endif

                                @if($inquiry->phone)
                                    <span class="text-[10px]" style="color: var(--text-secondary);">
                                        <i class="fas fa-phone mr-1"></i>{{ $inquiry->phone }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm truncate mb-1" style="color: var(--text-secondary);">
                                <i class="fas fa-home mr-1" style="color: var(--primary);"></i>
                                {{ $property?->property_name ?? 'Property' }}
                                @if($unit)
                                    — {{ $unit->unit_name ?? $unit->unit_number }}
                                @endif
                            </p>

                            @if(!empty($inquiry->message))
                                <p class="text-sm line-clamp-2" style="color: var(--text-secondary);">
                                    {{ \Illuminate\Support\Str::limit($inquiry->message, 140) }}
                                </p>
                            @endif

                            @if($inquiry->reply_message)
                                <p class="text-xs mt-2 pl-3"
                                   style="color: var(--text-secondary);
                                          border-left: 2px solid var(--success);">
                                    <i class="fas fa-reply mr-1" style="color: var(--success);"></i>
                                    {{ \Illuminate\Support\Str::limit($inquiry->reply_message, 100) }}
                                </p>
                            @endif
                        </div>

                        {{-- Right: timestamp + chevron --}}
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ optional($inquiry->created_at)->diffForHumans() }}
                            </p>
                            @if($inquiry->created_at)
                                <p class="text-[10px] mt-0.5" style="color: var(--text-secondary);">
                                    {{ $inquiry->created_at->format('M d, Y') }}
                                </p>
                            @endif
                            <i class="fas fa-chevron-right mt-2 text-xs"
                               style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- ==================== PAGINATION ==================== --}}
        @if(method_exists($inquiries, 'links'))
            <div class="mt-6 flex justify-between items-center flex-wrap gap-3">
                <div class="text-xs" style="color: var(--text-secondary);">
                    Showing {{ $inquiries->firstItem() }}–{{ $inquiries->lastItem() }}
                    of {{ $inquiries->total() }}
                </div>
                <div>{{ $inquiries->links() }}</div>
            </div>
        @endif
    @endif
</div>
@endsection