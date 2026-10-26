{{-- resources/views/admin/inquiries/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Unit Inquiries — Admin')

@php
    use App\Models\UnitInquiry;

    $inquiries  = $inquiries ?? collect();
    $stats      = $stats     ?? [];
    $landlords  = $landlords ?? collect();
    $statuses   = $statuses  ?? [
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
    $filterLandlord = request('landlord_id', '');
    $filterUnit     = request('property_unit_id', '');
    $searchTerm     = request('search', '');
    $fromDate       = request('from_date', '');
    $toDate         = request('to_date', '');
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- HEADER --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 mr-4"
                     style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary);">
                    <i class="fas fa-envelope-open-text text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-envelope-open-text mr-2" style="color: var(--primary);"></i>
                        Unit Inquiries
                    </h2>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-globe mr-2"></i>
                        Every inquiry submitted across the platform
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.inquiries.export', request()->all()) }}"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-file-export mr-2"></i> Export CSV
                </a>
            </div>
        </div>
    </div>

    {{-- FLASH --}}
    @if(session('success'))
        <div class="card">
            <div class="p-4 rounded-lg flex items-center"
                 style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="card">
            <div class="p-4 rounded-lg flex items-center"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <i class="fas fa-times-circle mr-3 text-xl" style="color: var(--danger);"></i>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif
    @if($errors->any())
        <div class="card">
            <div class="p-4 rounded-lg"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <p class="font-semibold mb-2" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the following:
                </p>
                <ul class="text-sm list-disc pl-6" style="color: var(--text-primary);">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- STATS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        @php
            $statCards = [
                ['key' => 'total',     'label' => 'Total',     'icon' => 'fa-inbox',            'color' => 'primary'],
                ['key' => 'new',       'label' => 'New',       'icon' => 'fa-envelope',         'color' => 'primary'],
                ['key' => 'read',      'label' => 'Read',      'icon' => 'fa-envelope-open',    'color' => 'info'],
                ['key' => 'replied',   'label' => 'Replied',   'icon' => 'fa-reply',            'color' => 'success'],
                ['key' => 'spam',      'label' => 'Spam',      'icon' => 'fa-ban',              'color' => 'danger'],
                ['key' => 'today',     'label' => 'Today',     'icon' => 'fa-sun',              'color' => 'primary'],
                ['key' => 'this_week', 'label' => 'This Week', 'icon' => 'fa-calendar-week',    'color' => 'primary'],
            ];
        @endphp
        @foreach($statCards as $card)
            <div class="card p-3">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                         style="background: rgba(var(--{{ $card['color'] }}-rgb), 0.1); color: var(--{{ $card['color'] }});">
                        <i class="fas {{ $card['icon'] }} text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wide" style="color: var(--text-secondary);">
                            {{ $card['label'] }}
                        </p>
                        <p class="text-lg font-bold truncate" style="color: var(--text-primary);">
                            {{ number_format($stats[$card['key']] ?? 0) }}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- FILTERS --}}
    <div class="card">
        <div class="p-5">
            <form method="GET" action="{{ route('admin.inquiries.index') }}"
                  class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                        Search (name, phone, email, message, UUID)
                    </label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm"
                           style="color: var(--text-secondary);"></i>
                        <input type="text" name="search" value="{{ $searchTerm }}"
                               class="form-input w-full pl-9 pr-3 py-2 rounded-lg"
                               placeholder="e.g. Kwame, +233…, kwame@example.com">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="form-input w-full px-3 py-2 rounded-lg">
                        <option value="">All statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($filterStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Landlord</label>
                    <select name="landlord_id" class="form-input w-full px-3 py-2 rounded-lg">
                        <option value="">All landlords</option>
                        @foreach($landlords as $landlord)
                            <option value="{{ $landlord->id }}" @selected((string) $filterLandlord === (string) $landlord->id)>
                                {{ $landlord->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">From date</label>
                    <input type="date" name="from_date" value="{{ $fromDate }}"
                           class="form-input w-full px-3 py-2 rounded-lg">
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">To date</label>
                    <input type="date" name="to_date" value="{{ $toDate }}"
                           class="form-input w-full px-3 py-2 rounded-lg">
                </div>

                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit"
                            class="btn-primary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center flex-1">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.inquiries.index') }}"
                       class="btn-secondary px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- LIST --}}
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2"></i> Inquiries
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"
                          style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        {{ $inquiries->total() }}
                    </span>
                </h3>
            </div>

            @if($inquiries->isEmpty())
                <div class="text-center py-16 rounded-lg"
                     style="background-color: rgba(var(--secondary-rgb), 0.03);
                            border: 1px dashed var(--border-color);">
                    <i class="fas fa-inbox text-5xl mb-4"
                       style="color: var(--text-secondary); opacity: 0.35;"></i>
                    <p class="text-base font-medium mb-1" style="color: var(--text-primary);">No inquiries found</p>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Try clearing your filters, or wait for guests to start inquiring.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Guest</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Unit / Landlord</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Message</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Received</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color: var(--text-primary);">Status</th>
                                <th class="text-right py-3 px-2 font-semibold" style="color: var(--text-primary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inquiries as $inquiry)
                                @php
                                    $statusColor = $statusColors[$inquiry->status] ?? 'secondary';
                                    $statusLabel = $statuses[$inquiry->status] ?? ucfirst($inquiry->status);
                                    $isUnread    = is_null($inquiry->landlord_viewed_at);
                                    $unit        = $inquiry->unit;
                                    $property    = $unit?->property;
                                    $landlord    = $inquiry->landlord;
                                @endphp
                                <tr style="border-bottom: 1px solid var(--border-color); vertical-align: top;"
                                    class="{{ $isUnread ? '' : '' }}">
                                    {{-- Guest --}}
                                    <td class="py-3 px-2">
                                        <div class="font-medium flex items-center gap-1" style="color: var(--text-primary);">
                                            @if($isUnread)
                                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                                      style="background: var(--primary);" title="Unread"></span>
                                            @endif
                                            {{ $inquiry->name ?? '—' }}
                                        </div>
                                        @if($inquiry->phone)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-phone mr-1"></i>{{ $inquiry->phone }}
                                            </div>
                                        @endif
                                        @if($inquiry->email)
                                            <div class="text-xs" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-1"></i>{{ $inquiry->email }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Unit / Landlord --}}
                                    <td class="py-3 px-2">
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $unit?->unit_name ?? $unit?->unit_number ?? 'Unit #' . $inquiry->property_unit_id }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-building mr-1"></i>{{ $property?->property_name ?? 'Unknown property' }}
                                        </div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                            <i class="fas fa-user mr-1"></i>{{ $landlord?->name ?? 'Unknown landlord' }}
                                        </div>
                                    </td>

                                    {{-- Message excerpt --}}
                                    <td class="py-3 px-2 max-w-xs">
                                        <p class="text-xs line-clamp-3" style="color: var(--text-secondary);">
                                            {{ \Illuminate\Support\Str::limit((string) $inquiry->message, 120) }}
                                        </p>
                                    </td>

                                    {{-- Received --}}
                                    <td class="py-3 px-2">
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ optional($inquiry->created_at)->format('M d, Y') }}
                                        </div>
                                        <div class="text-[10px]" style="color: var(--text-secondary);">
                                            {{ optional($inquiry->created_at)->diffForHumans() }}
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="py-3 px-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              style="background: rgba(var(--{{ $statusColor }}-rgb), 0.15);
                                                     color: var(--{{ $statusColor }});">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="py-3 px-2 text-right">
                                        <a href="{{ route('admin.inquiries.show', $inquiry->uuid) }}"
                                           class="px-2.5 py-1 rounded text-xs font-medium inline-flex items-center"
                                           style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center flex-wrap gap-3">
                    <div class="text-xs" style="color: var(--text-secondary);">
                        Showing {{ $inquiries->firstItem() }}–{{ $inquiries->lastItem() }}
                        of {{ $inquiries->total() }}
                    </div>
                    <div>{{ $inquiries->links() }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection