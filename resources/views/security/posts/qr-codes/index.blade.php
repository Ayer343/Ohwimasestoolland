@extends('layouts.secu')

@section('title', 'QR Codes - ' . $post->name)

@php
    // Resolve route URLs once so we don't sprinkle route() calls throughout
    // the template (and so future route renames are one-line changes).
    // NOTE: The trash view route is named `…trash.index` (Laravel's convention
    // for a nested `index` inside a `trash.` prefix group).
    $urlIndex       = route('security.posts.qr-codes.index', ['securityPost' => $post->id]);
    $urlCreate      = route('security.posts.qr-codes.create', ['securityPost' => $post->id]);
    $urlExport      = route('security.posts.qr-codes.export', ['securityPost' => $post->id]);
    $urlTrash       = route('security.posts.qr-codes.trash.index', ['securityPost' => $post->id]);
    $urlPostShow    = \Illuminate\Support\Facades\Route::has('security.posts.show')
                          ? route('security.posts.show', ['securityPost' => $post->id]) : null;
    $urlPostIndex   = \Illuminate\Support\Facades\Route::has('security.posts.index')
                          ? route('security.posts.index') : null;

    // Prefer the count the controller already computed; fall back to a
    // cheap query only if it's missing (e.g. legacy controllers).
    $trashedCount   = $stats['trashed'] ?? \App\Models\PostQrCode::where('post_id', $post->id)->onlyTrashed()->count();

    // Base URL for JS fetch calls (avoids hardcoding /admin/... in <script>).
    $urlQrBase      = url("security/posts/{$post->id}/qr-codes");
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    {{-- Header Card --}}
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div class="flex items-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mr-4"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-qrcode text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-qrcode mr-2" style="color: var(--primary);"></i>
                            QR Codes - {{ $post->name }}
                        </h2>
                        <div class="flex items-center mt-1 space-x-3 text-sm" style="color: var(--text-secondary);">
                            <span><i class="fas fa-map-marker-alt mr-1" style="color: var(--info);"></i> {{ $post->code }} | {{ $post->location }}</span>
                            <span>•</span>
                            <span><i class="fas fa-user-shield mr-1" style="color: var(--success);"></i> {{ auth()->user()->name }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="{{ $urlTrash }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-trash-alt mr-2"></i> Trash
                        @if($trashedCount > 0)
                            <span class="ml-1 px-2 py-0.5 text-xs rounded-full" style="background-color: var(--danger); color: white;">
                                {{ $trashedCount }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ $urlCreate }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-plus mr-2"></i> Generate QR Code
                    </a>
                    <a href="{{ $urlExport }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </a>
                    @if($urlPostShow)
                    <a href="{{ $urlPostShow }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Breadcrumb --}}
    <div class="card p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                @if($urlPostIndex)
                <a href="{{ $urlPostIndex }}"
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Security Posts
                </a>
                <span style="color: var(--text-secondary);">/</span>
                @endif
                @if($urlPostShow)
                <a href="{{ $urlPostShow }}"
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    {{ $post->name }}
                </a>
                <span style="color: var(--text-secondary);">/</span>
                @endif
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-qrcode mr-1"></i> QR Codes
                </span>
                <a href="{{ $urlTrash }}"
                   class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium hover:underline"
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-1"></i> Trash
                    @if($trashedCount > 0)
                        <span class="ml-1">({{ $trashedCount }})</span>
                    @endif
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1" style="color: var(--info);"></i>
                {{ now()->format('l, F j, Y') }}
            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-qrcode" style="color: var(--primary);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? $qrCodes->total() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total QR Codes</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active'] ?? $qrCodes->where('is_active', true)->count() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Active</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-infinity" style="color: var(--info);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $qrCodes->where('code_type', 'static')->count() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Permanent</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $qrCodes->where('code_type', 'one_time')->count() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">One-Time</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-hourglass-half" style="color: var(--danger);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $qrCodes->where('code_type', 'time_based')->count() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Time-Based</div>
                </div>
            </div>
        </div>
        <div class="card p-4 cursor-pointer hover:shadow-lg transition-all duration-300"
             onclick="window.location.href='{{ $urlTrash }}'">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-trash-alt" style="color: var(--danger);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $trashedCount }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">In Trash</div>
                </div>
            </div>
        </div>
    </div>

    {{-- QR Codes Table --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                    Generated QR Codes
                </h3>
                <div class="flex items-center space-x-4">
                    @if($trashedCount > 0)
                        <a href="{{ $urlTrash }}"
                           class="text-sm inline-flex items-center hover:underline"
                           style="color: var(--danger);">
                            <i class="fas fa-trash-alt mr-1"></i>
                            {{ $trashedCount }} item(s) in trash
                        </a>
                    @endif
                    <div class="text-sm" style="color: var(--text-secondary);">
                        Showing {{ $qrCodes->firstItem() }} to {{ $qrCodes->lastItem() }} of {{ $qrCodes->total() }} codes
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">QR Code</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Name</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Type</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Usage</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Expiration</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Created</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($qrCodes as $qrCode)
                            @php
                                $isPermanent = $qrCode->code_type === 'static' && $qrCode->expires_at === null;
                            @endphp
                            <tr class="border-b transition-all duration-200 hover:bg-opacity-50"
                                style="border-color: var(--border-color); background-color: var(--card-bg);"
                                onmouseenter="this.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)'"
                                onmouseleave="this.style.backgroundColor = 'var(--card-bg)'">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="qr-preview mr-3">
                                            @if($qrCode->image_path)
                                                <img src="{{ asset('storage/' . $qrCode->image_path) }}"
                                                     alt="QR Code"
                                                     class="w-10 h-10 rounded-lg border-2"
                                                     style="border-color: var(--border-color);">
                                            @else
                                                <div class="w-10 h-10 rounded-lg border-2 flex items-center justify-center"
                                                     style="background-color: rgba(var(--primary-rgb), 0.1); border-color: var(--border-color);">
                                                    <i class="fas fa-qrcode" style="color: var(--primary);"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-xs font-mono" style="color: var(--text-secondary);">
                                            {{ substr($qrCode->code, 0, 12) }}...
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $qrCode->name }}</div>
                                    @if($qrCode->description)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ Str::limit($qrCode->description, 30) }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $typeStyles = [
                                            'static'     => ['bg' => 'rgba(var(--info-rgb), 0.1)',    'text' => 'var(--info)',    'icon' => 'fa-infinity',       'label' => 'Permanent'],
                                            'one_time'   => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-clock',           'label' => 'One-Time'],
                                            'time_based' => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)',  'icon' => 'fa-hourglass-half',  'label' => 'Time-Based'],
                                        ];
                                        $style = $typeStyles[$qrCode->code_type]
                                            ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'icon' => 'fa-qrcode', 'label' => ucfirst(str_replace('_', ' ', $qrCode->code_type))];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: {{ $style['bg'] }}; color: {{ $style['text'] }};">
                                        <i class="fas {{ $style['icon'] }} mr-1"></i>
                                        {{ $style['label'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="relative">
                                        <button onclick="toggleStatus({{ $qrCode->id }}, {{ $qrCode->is_active ? 'false' : 'true' }})"
                                                class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center transition-all duration-200 hover:scale-105 status-toggle"
                                                style="background-color: {{ $qrCode->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }};
                                                       color: {{ $qrCode->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                            <i class="fas {{ $qrCode->is_active ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                            {{ $qrCode->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                        <div class="status-tooltip hidden absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 text-xs rounded whitespace-nowrap"
                                             style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
                                            Click to {{ $qrCode->is_active ? 'deactivate' : 'activate' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium" style="color: var(--text-primary);">{{ $qrCode->uses_count }}</div>
                                            @if($qrCode->max_uses)
                                                <div class="text-xs" style="color: var(--text-secondary);">max {{ $qrCode->max_uses }}</div>
                                            @else
                                                <div class="text-xs" style="color: var(--text-secondary);">unlimited</div>
                                            @endif
                                        </div>
                                        @if($qrCode->max_uses)
                                            @php
                                                $percentage = min(100, ($qrCode->uses_count / $qrCode->max_uses) * 100);
                                                $barColor = $percentage >= 100 ? 'var(--danger)' : ($percentage >= 75 ? 'var(--warning)' : 'var(--success)');
                                            @endphp
                                            <div class="w-16 h-1.5 rounded-full" style="background-color: var(--border-color);">
                                                <div class="h-1.5 rounded-full transition-all duration-300"
                                                     style="width: {{ $percentage }}%; background-color: {{ $barColor }};"></div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @if($isPermanent)
                                        <div class="flex items-center" style="color: var(--info);">
                                            <i class="fas fa-infinity mr-1 text-sm"></i>
                                            <span class="text-sm font-medium">Never Expires</span>
                                        </div>
                                        <span class="text-xs mt-1 inline-block" style="color: var(--info);">Permanent</span>
                                    @elseif($qrCode->expires_at)
                                        @php
                                            $expiresAt = Carbon\Carbon::parse($qrCode->expires_at);
                                            $isExpired = $expiresAt->isPast();
                                            $expiresColor = $isExpired ? 'var(--danger)' : ($expiresAt->diffInDays(now()) <= 7 ? 'var(--warning)' : 'var(--success)');
                                        @endphp
                                        <div class="flex items-center" style="color: {{ $expiresColor }};">
                                            <i class="fas fa-clock mr-1 text-xs"></i>
                                            <span class="text-sm">{{ $expiresAt->format('M d, Y') }}</span>
                                        </div>
                                        @if($isExpired)
                                            <span class="text-xs" style="color: var(--danger);">Expired</span>
                                        @elseif($expiresAt->diffInDays(now()) <= 7)
                                            <span class="text-xs" style="color: var(--warning);">Expiring soon</span>
                                        @else
                                            <span class="text-xs" style="color: var(--text-secondary);">{{ $expiresAt->diffForHumans() }}</span>
                                        @endif
                                    @else
                                        <div class="flex items-center" style="color: var(--text-secondary);">
                                            <i class="fas fa-minus-circle mr-1 text-xs"></i>
                                            <span class="text-sm">No Expiration</span>
                                        </div>
                                        <span class="text-xs" style="color: var(--text-secondary);">Never</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $qrCode->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $qrCode->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('security.posts.qr-codes.show', ['securityPost' => $post->id, 'qrCode' => $qrCode->id]) }}"
                                           class="action-btn" title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('security.posts.qr-codes.download', ['securityPost' => $post->id, 'qrCode' => $qrCode->id]) }}"
                                           class="action-btn" title="Download QR Code"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button onclick="showRegenerateModal({{ $qrCode->id }}, @js($qrCode->name))"
                                                class="action-btn" title="Regenerate"
                                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button onclick="showDeleteModal({{ $qrCode->id }}, @js($qrCode->name))"
                                                class="action-btn" title="Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 px-4 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                                            <i class="fas fa-qrcode text-4xl" style="color: var(--primary);"></i>
                                        </div>
                                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No QR Codes Found</h4>
                                        <p class="mb-4" style="color: var(--text-secondary);">Generate your first QR code for this security post.</p>
                                        <div class="flex space-x-3">
                                            <a href="{{ $urlCreate }}"
                                               class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                                                <i class="fas fa-plus mr-2"></i> Generate QR Code
                                            </a>
                                            @if($trashedCount > 0)
                                                <a href="{{ $urlTrash }}"
                                                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                    <i class="fas fa-trash-alt mr-2"></i> View Trash ({{ $trashedCount }})
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($qrCodes->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $qrCodes->firstItem() }} to {{ $qrCodes->lastItem() }} of {{ $qrCodes->total() }} results
                        </div>
                        <div class="flex space-x-2">{{ $qrCodes->links() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div id="deleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Delete QR Code</h3>
            <button onclick="closeModal('deleteModal')" class="modal-close">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
        </div>
        <div class="modal-body p-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-trash text-2xl" style="color: var(--danger);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="deleteQrName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to delete this QR code? It will be moved to trash.
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <div class="flex items-center text-sm" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        You can restore it later from the trash.
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer px-6 py-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('deleteModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background-color: var(--danger); border: 1px solid var(--danger);">
                        <i class="fas fa-trash mr-2"></i> Move to Trash
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Regenerate Modal --}}
<div id="regenerateModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('regenerateModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Regenerate QR Code</h3>
            <button onclick="closeModal('regenerateModal')" class="modal-close">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
        </div>
        <div class="modal-body p-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-sync-alt text-2xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="regenerateQrName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Regenerating will create a new unique code. The old code will be invalidated.
                </p>
                <div class="text-left">
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Reason (Optional)</label>
                    <input type="text"
                           id="regenerateReason"
                           class="w-full p-3 rounded-lg border"
                           style="background-color: var(--card-bg); color: var(--text-primary); border-color: var(--border-color);"
                           placeholder="e.g., Security rotation, Code compromised">
                </div>
            </div>
        </div>
        <div class="modal-footer px-6 py-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('regenerateModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <form id="regenerateForm" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background-color: var(--warning); border: 1px solid var(--warning);">
                        <i class="fas fa-sync-alt mr-2"></i> Regenerate
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

{{-- Loading Overlay --}}
<div id="loadingOverlay" class="loading-overlay hidden">
    <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-transparent mb-4"
             style="border-color: var(--primary); border-top-color: transparent;"></div>
        <div class="text-white font-medium" id="loadingMessage">Processing...</div>
    </div>
</div>

<style>
:root {
    --primary: #4f46e5; --primary-rgb: 79, 70, 229;
    --secondary: #8b5cf6; --secondary-rgb: 139, 92, 246;
    --success: #10b981; --success-rgb: 16, 185, 129;
    --danger: #ef4444; --danger-rgb: 239, 68, 68;
    --warning: #f59e0b; --warning-rgb: 245, 158, 11;
    --info: #3b82f6; --info-rgb: 59, 130, 246;
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --card-bg: #ffffff; --bg-secondary: #f9fafb; --border-color: #e5e7eb;
}
[data-theme="dark"] {
    --text-primary: #f3f4f6; --text-secondary: #9ca3af;
    --card-bg: #1f2937; --bg-secondary: #111827; --border-color: #374151;
}
.card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s ease; }
.card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); transition: all 0.3s ease; border: none; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(var(--primary-rgb), 0.3); }
.qr-preview { transition: transform 0.2s ease; }
.qr-preview:hover { transform: scale(1.1); }
.action-btn { width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease; cursor: pointer; border: none; }
.action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.status-toggle { transition: all 0.2s ease; cursor: pointer; }
.status-toggle:hover { filter: brightness(1.1); }
.status-tooltip { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translate(-50%, 10px); } to { opacity: 1; transform: translate(-50%, 0); } }
.modal { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; }
.modal.hidden { display: none; }
.modal-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); animation: fadeIn 0.2s ease; }
.modal-container { position: relative; background-color: var(--card-bg); border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; z-index: 10000; animation: slideUp 0.3s ease; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-close { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; cursor: pointer; transition: all 0.2s ease; }
.modal-close:hover { background-color: rgba(var(--danger-rgb), 0.1); }
.modal-close:hover i { color: var(--danger) !important; }
.loading-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.75); display: flex; align-items: center; justify-content: center; z-index: 10001; animation: fadeIn 0.2s ease; }
.loading-overlay.hidden { display: none; }
#toast-container { z-index: 10002; }
#toast-container > div { animation: slideInRight 0.3s ease; }
@keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
table { border-collapse: collapse; width: 100%; }
th { font-weight: 500; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
td { font-size: 0.875rem; }
.pagination { display: flex; list-style: none; padding: 0; margin: 0; }
.pagination li { margin: 0 2px; }
.pagination li a, .pagination li span { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 8px; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s ease; }
.pagination li a { background-color: var(--bg-secondary); color: var(--text-primary); text-decoration: none; border: 1px solid var(--border-color); }
.pagination li a:hover { background-color: var(--primary); color: white; border-color: var(--primary); }
.pagination li.active span { background-color: var(--primary); color: white; border: 1px solid var(--primary); }
.pagination li.disabled span { background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color); opacity: 0.5; cursor: not-allowed; }
@media (max-width: 1024px) { table { font-size: 0.875rem; } th, td { padding: 0.75rem 0.5rem; } }
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-6 { grid-template-columns: repeat(2, 1fr); }
    .action-btn { width: 32px; height: 32px; }
    .action-btn i { font-size: 0.875rem; }
}
@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-6 { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .card .p-6 { padding: 1rem; }
    table { display: block; overflow-x: auto; white-space: nowrap; }
    .action-btn { width: 28px; height: 28px; }
}
.hover-scale { transition: transform 0.2s ease; }
.hover-scale:hover { transform: scale(1.05); }
button:focus, a:focus, input:focus { outline: none; box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.5); }
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }
</style>
@endsection

@push('scripts')
<script>
// Pre-resolved URLs from Blade — no hardcoded /admin/... anywhere.
window.postData = {
    id: {{ $post->id }},
    name: @js($post->name),
    code: @js($post->code),
};
const QR_BASE = @js($urlQrBase); // e.g. /security/posts/1/qr-codes
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || @js(csrf_token());

let currentQrId = null;

async function toggleStatus(qrId, newStatus) {
    if (!confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this QR code?`)) return;
    showLoading('Updating QR code status...');
    try {
        const response = await fetch(`${QR_BASE}/${qrId}/update-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                is_active: newStatus,
                reason: `Manual ${newStatus ? 'activation' : 'deactivation'}`,
            }),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        hideLoading();
        if (data.success) {
            showToast(data.message || 'Status updated', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (error) {
        hideLoading();
        console.error('Toggle status:', error);
        showToast('Network error while updating status', 'error');
    }
}

function showDeleteModal(qrId, qrName) {
    currentQrId = qrId;
    document.getElementById('deleteQrName').textContent = qrName;
    document.getElementById('deleteForm').action = `${QR_BASE}/${qrId}`;
    openModal('deleteModal');
}

function showRegenerateModal(qrId, qrName) {
    currentQrId = qrId;
    document.getElementById('regenerateQrName').textContent = qrName;
    document.getElementById('regenerateReason').value = '';
    document.getElementById('regenerateForm').action = `${QR_BASE}/${qrId}/regenerate`;
    openModal('regenerateModal');
}

function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
}

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        const m = e.target.closest('.modal');
        if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => {
            if (!m.classList.contains('hidden')) {
                m.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

document.querySelectorAll('.status-toggle').forEach(button => {
    let t;
    button.addEventListener('mouseenter', function () {
        t = setTimeout(() => this.nextElementSibling?.classList.remove('hidden'), 300);
    });
    button.addEventListener('mouseleave', function () {
        clearTimeout(t);
        this.nextElementSibling?.classList.add('hidden');
    });
});

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const colors = {
        success: { bg: 'rgba(var(--success-rgb), 0.1)', text: 'var(--success)', border: 'rgba(var(--success-rgb), 0.3)', icon: 'fa-check-circle' },
        error:   { bg: 'rgba(var(--danger-rgb), 0.1)',  text: 'var(--danger)',  border: 'rgba(var(--danger-rgb), 0.3)',  icon: 'fa-exclamation-circle' },
        warning: { bg: 'rgba(var(--warning-rgb), 0.1)', text: 'var(--warning)', border: 'rgba(var(--warning-rgb), 0.3)', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'rgba(var(--info-rgb), 0.1)',    text: 'var(--info)',    border: 'rgba(var(--info-rgb), 0.3)',    icon: 'fa-info-circle' },
    };
    const c = colors[type] || colors.info;
    const t = document.createElement('div');
    t.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transition-all duration-300 transform translate-x-full';
    t.style.cssText = `background-color:${c.bg};color:${c.text};border:1px solid ${c.border};margin-bottom:.5rem;`;
    t.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${c.icon} mr-2"></i>
            <span class="text-sm font-medium"></span>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>`;
    t.querySelector('.text-sm').textContent = message;
    container.appendChild(t);
    setTimeout(() => t.classList.remove('translate-x-full'), 10);
    setTimeout(() => { t.classList.add('translate-x-full'); setTimeout(() => t.remove(), 300); }, 5000);
}

function showLoading(message = 'Processing...') {
    const o = document.getElementById('loadingOverlay');
    const m = document.getElementById('loadingMessage');
    if (m) m.textContent = message;
    if (o) o.classList.remove('hidden');
}
function hideLoading() {
    document.getElementById('loadingOverlay')?.classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
        });
        btn.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });

    window.copyToClipboard = function (text) {
        navigator.clipboard.writeText(text)
            .then(() => showToast('Code copied to clipboard!', 'success'))
            .catch(() => showToast('Failed to copy code', 'error'));
    };

    // Regenerate
    const regenForm = document.getElementById('regenerateForm');
    if (regenForm) {
        regenForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const reason = document.getElementById('regenerateReason').value;
            const fd = new FormData(this);
            if (reason) fd.append('reason', reason);
            showLoading('Regenerating QR code...');
            fetch(this.action, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(async r => {
                const ct = r.headers.get('content-type') || '';
                if (!r.ok) {
                    if (ct.includes('application/json')) {
                        const err = await r.json();
                        throw new Error(err.message || `Server error ${r.status}`);
                    }
                    throw new Error(`Server error ${r.status}`);
                }
                if (ct.includes('application/json')) return r.json();
                const text = await r.text();
                const m = text.match(/url=([^"]+)/) || text.match(/location\.href\s*=\s*['"]([^'"]+)['"]/);
                if (m) { window.location.href = m[1]; return { success: true }; }
                throw new Error('Unexpected response format');
            })
            .then(data => {
                hideLoading();
                closeModal('regenerateModal');
                if (data.success) {
                    showToast(data.message || 'QR code regenerated!', 'success');
                    if (data.redirect) {
                        setTimeout(() => location.href = data.redirect, 1500);
                    } else {
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    showToast(data.message || 'Failed to regenerate', 'error');
                }
            })
            .catch(err => {
                hideLoading();
                console.error('Regenerate:', err);
                showToast(err.message || 'Network error', 'error');
            });
        });
    }

    // Delete
    const delForm = document.getElementById('deleteForm');
    if (delForm) {
        delForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showLoading('Deleting QR code...');
            fetch(this.action, {
                method: 'POST',
                body: new FormData(this),
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                hideLoading();
                closeModal('deleteModal');
                if (data.success) {
                    showToast(data.message || 'QR code deleted', 'success');
                    setTimeout(() => location.href = @js($urlIndex), 1500);
                } else {
                    showToast(data.message || 'Failed to delete', 'error');
                }
            })
            .catch(err => {
                hideLoading();
                console.error('Delete:', err);
                showToast('Network error while deleting', 'error');
            });
        });
    }
});
</script>
@endpush