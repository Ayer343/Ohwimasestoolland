@extends('layouts.secu')

@section('title', 'Trashed QR Codes - ' . $post->name)

@php
    // Resolve routes once; used in multiple places across the blade and JS.
    $urlIndex       = route('security.posts.qr-codes.index', ['securityPost' => $post->id]);
    $urlEmptyTrash  = route('security.posts.qr-codes.trash.empty', ['securityPost' => $post->id]);
    $urlPostShow    = \Illuminate\Support\Facades\Route::has('security.posts.show')
                          ? route('security.posts.show', ['securityPost' => $post->id]) : null;
    $urlPostIndex   = \Illuminate\Support\Facades\Route::has('security.posts.index')
                          ? route('security.posts.index') : null;

    // Base URL for JS fetch calls — keeps `/admin/...` out of <script> blocks.
    $urlQrBase      = url("security/posts/{$post->id}/qr-codes");
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    {{-- Header --}}
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div class="flex items-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mr-4"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%);">
                        <i class="fas fa-trash-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i>
                            Trashed QR Codes - {{ $post->name }}
                        </h2>
                        <div class="flex items-center mt-1 space-x-3 text-sm" style="color: var(--text-secondary);">
                            <span><i class="fas fa-map-marker-alt mr-1" style="color: var(--info);"></i> {{ $post->code }} | {{ $post->location }}</span>
                            <span>•</span>
                            <span><i class="fas fa-user-shield mr-1" style="color: var(--success);"></i> {{ auth()->user()->name }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="showBulkRestoreModal()"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center bulk-restore-btn"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-undo-alt mr-2"></i> Bulk Restore
                    </button>

                    @if($trashedQrCodes->total() > 0)
                        <button onclick="showEmptyTrashModal()"
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                        </button>
                    @endif

                    <a href="{{ $urlIndex }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to QR Codes
                    </a>
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
                <a href="{{ $urlIndex }}"
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    QR Codes
                </a>
                <span style="color: var(--text-secondary);">/</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium"
                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-1"></i> Trash
                </span>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1" style="color: var(--info);"></i>
                {{ now()->format('l, F j, Y') }}
            </div>
        </div>
    </div>

    {{-- Warning --}}
    <div class="card p-4" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-xl" style="color: var(--warning);"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium" style="color: var(--warning);">Trash Information</h3>
                <div class="mt-1 text-sm" style="color: var(--text-secondary);">
                    <p>QR codes in trash are soft-deleted and can be restored. After {{ config('app.trash_retention_days', 30) }} days, they will be automatically permanently deleted.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-trash-alt" style="color: var(--warning);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $trashedQrCodes->total() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total in Trash</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-undo-alt" style="color: var(--success);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $trashedQrCodes->count() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Restorable</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
                <div>
                    @php
                        $expiringCount = $trashedQrCodes->filter(function ($qr) {
                            return $qr->deleted_at && $qr->deleted_at->diffInDays(now()) >= (config('app.trash_retention_days', 30) - 7);
                        })->count();
                    @endphp
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $expiringCount }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Expiring Soon</div>
                </div>
            </div>
        </div>
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-database" style="color: var(--danger);"></i>
                </div>
                <div>
                    @php
                        $totalSize = 0;
                        foreach ($trashedQrCodes as $qr) {
                            if ($qr->image_path && file_exists(storage_path('app/public/' . $qr->image_path))) {
                                $totalSize += filesize(storage_path('app/public/' . $qr->image_path));
                            }
                        }
                        $formattedSize = $totalSize > 1048576
                            ? round($totalSize / 1048576, 2) . ' MB'
                            : ($totalSize > 1024 ? round($totalSize / 1024, 2) . ' KB' : $totalSize . ' B');
                    @endphp
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $formattedSize }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Storage Used</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk bar --}}
    @if($trashedQrCodes->count() > 0)
    <div class="card p-4" id="bulkActionsBar" style="display: none;">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-sm font-medium" style="color: var(--text-primary);">
                    <span id="selectedCount">0</span> item(s) selected
                </span>
                <button onclick="selectAll()" class="text-sm hover:underline" style="color: var(--info);">Select All</button>
                <button onclick="deselectAll()" class="text-sm hover:underline" style="color: var(--text-secondary);">Deselect All</button>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="bulkRestore()"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-undo-alt mr-1"></i> Restore Selected
                </button>
                <button onclick="bulkForceDelete()"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center"
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-alt mr-1"></i> Delete Permanently
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Table --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i>
                    Trashed QR Codes
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $trashedQrCodes->firstItem() }} to {{ $trashedQrCodes->lastItem() }} of {{ $trashedQrCodes->total() }} trashed codes
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            @if($trashedQrCodes->count() > 0)
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary); width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                            </th>
                            @endif
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">QR Code</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Name</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Type</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Original Status</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Usage</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Deleted</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Auto-Delete</th>
                            <th class="text-left py-3 px-4 text-sm font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trashedQrCodes as $qrCode)
                            @php
                                $deletedAt = Carbon\Carbon::parse($qrCode->deleted_at);
                                $autoDeleteDate = $deletedAt->copy()->addDays(config('app.trash_retention_days', 30));
                                $daysLeft = now()->diffInDays($autoDeleteDate, false);
                                $isExpiring = $daysLeft <= 7;

                                $metadata = is_array($qrCode->metadata) ? $qrCode->metadata : (is_string($qrCode->metadata) ? (json_decode($qrCode->metadata, true) ?? []) : []);
                                $deletions = $metadata['deletions'] ?? [];
                                $lastDeletion = !empty($deletions) ? end($deletions) : null;
                                $deletedBy = $lastDeletion['deleted_by_name']
                                    ?? ($metadata['last_deletion']['deleted_by_name']
                                    ?? ($metadata['deleted_by_name'] ?? 'Unknown'));
                                $deletionCount = $metadata['deletion_count'] ?? count($deletions) ?? 1;
                            @endphp
                            <tr class="border-b transition-all duration-200 hover:bg-opacity-50"
                                style="border-color: var(--border-color); background-color: var(--card-bg);"
                                onmouseenter="this.style.backgroundColor = 'rgba(var(--warning-rgb), 0.05)'"
                                onmouseleave="this.style.backgroundColor = 'var(--card-bg)'">
                                <td class="py-3 px-4">
                                    <input type="checkbox" class="qr-checkbox" value="{{ $qrCode->id }}" onchange="updateBulkActions()">
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="qr-preview mr-3 opacity-50">
                                            @if($qrCode->image_path)
                                                <img src="{{ asset('storage/' . $qrCode->image_path) }}"
                                                     alt="QR Code"
                                                     class="w-10 h-10 rounded-lg border-2 grayscale"
                                                     style="border-color: var(--border-color);">
                                            @else
                                                <div class="w-10 h-10 rounded-lg border-2 flex items-center justify-center grayscale"
                                                     style="background-color: rgba(var(--warning-rgb), 0.1); border-color: var(--border-color);">
                                                    <i class="fas fa-qrcode" style="color: var(--warning);"></i>
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
                                            'static'     => ['bg' => 'rgba(var(--info-rgb), 0.1)',    'text' => 'var(--info)',    'icon' => 'fa-infinity'],
                                            'one_time'   => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-clock'],
                                            'time_based' => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)',  'icon' => 'fa-hourglass-half'],
                                        ];
                                        $style = $typeStyles[$qrCode->code_type] ?? ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'icon' => 'fa-qrcode'];
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center opacity-75"
                                          style="background-color: {{ $style['bg'] }}; color: {{ $style['text'] }};">
                                        <i class="fas {{ $style['icon'] }} mr-1"></i>
                                        {{ ucfirst(str_replace('_', ' ', $qrCode->code_type)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: {{ $qrCode->is_active ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)' }};
                                                 color: {{ $qrCode->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                        <i class="fas {{ $qrCode->is_active ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                        {{ $qrCode->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">{{ $qrCode->uses_count }}</div>
                                        @if($qrCode->max_uses)
                                            <div class="text-xs" style="color: var(--text-secondary);">/ {{ $qrCode->max_uses }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $deletedAt->format('M d, Y') }}</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">{{ $deletedAt->diffForHumans() }}</div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i> {{ $deletedBy }}
                                        @if($deletionCount > 1)
                                            <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full"
                                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                x{{ $deletionCount }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-col">
                                        <div class="text-sm" style="color: {{ $isExpiring ? 'var(--warning)' : 'var(--text-primary)' }};">
                                            {{ $autoDeleteDate->format('M d, Y') }}
                                        </div>
                                        <div class="text-xs mt-1">
                                            @if($daysLeft > 0)
                                                <span style="color: {{ $isExpiring ? 'var(--warning)' : 'var(--success)' }};">{{ $daysLeft }} days left</span>
                                            @else
                                                <span style="color: var(--danger);">Expired</span>
                                            @endif
                                        </div>
                                        @php
                                            $totalDays = config('app.trash_retention_days', 30);
                                            $percentage = min(100, (($totalDays - max(0, $daysLeft)) / $totalDays) * 100);
                                        @endphp
                                        <div class="w-full h-1 mt-2 rounded-full" style="background-color: var(--border-color);">
                                            <div class="h-1 rounded-full transition-all duration-300"
                                                 style="width: {{ $percentage }}%; background-color: {{ $percentage >= 90 ? 'var(--danger)' : ($percentage >= 70 ? 'var(--warning)' : 'var(--success)') }};"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="showRestoreModal({{ $qrCode->id }}, @js($qrCode->name))"
                                                class="action-btn" title="Restore QR Code"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        <button onclick="showForceDeleteModal({{ $qrCode->id }}, @js($qrCode->name))"
                                                class="action-btn" title="Permanently Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 px-4 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                                            <i class="fas fa-trash-alt text-4xl" style="color: var(--warning);"></i>
                                        </div>
                                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Trash is Empty</h4>
                                        <p class="mb-4" style="color: var(--text-secondary);">No QR codes have been moved to trash yet.</p>
                                        <a href="{{ $urlIndex }}"
                                           class="px-4 py-2 rounded-lg font-medium inline-flex items-center btn-primary text-white">
                                            <i class="fas fa-arrow-left mr-2"></i> Back to QR Codes
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($trashedQrCodes->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $trashedQrCodes->firstItem() }} to {{ $trashedQrCodes->lastItem() }} of {{ $trashedQrCodes->total() }} results
                        </div>
                        <div class="flex space-x-2">{{ $trashedQrCodes->links() }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Restore Modal --}}
<div id="restoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('restoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Restore QR Code</h3>
            <button onclick="closeModal('restoreModal')" class="modal-close"><i class="fas fa-times" style="color: var(--text-secondary);"></i></button>
        </div>
        <div class="modal-body p-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-undo-alt text-2xl" style="color: var(--success);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="restoreQrName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to restore this QR code? It will be moved back to active QR codes.
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <div class="flex items-center text-sm" style="color: var(--info);">
                        <i class="fas fa-info-circle mr-2"></i>
                        The QR code will be restored with all its original settings and usage history.
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer px-6 py-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('restoreModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <form id="restoreForm" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background-color: var(--success); border: 1px solid var(--success);">
                        <i class="fas fa-undo-alt mr-2"></i> Restore QR Code
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Force Delete Modal --}}
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Permanently Delete QR Code</h3>
            <button onclick="closeModal('forceDeleteModal')" class="modal-close"><i class="fas fa-times" style="color: var(--text-secondary);"></i></button>
        </div>
        <div class="modal-body p-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--danger);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="forceDeleteQrName"></h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action cannot be undone. The QR code and its image will be permanently deleted from the system.
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-center text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        All associated verification history will be preserved but the QR code will be removed.
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer px-6 py-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('forceDeleteModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <form id="forceDeleteForm" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background-color: var(--danger); border: 1px solid var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Bulk Restore Modal --}}
<div id="bulkRestoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkRestoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Restore QR Codes</h3>
            <button onclick="closeModal('bulkRestoreModal')" class="modal-close"><i class="fas fa-times" style="color: var(--text-secondary);"></i></button>
        </div>
        <div class="modal-body p-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-undo-alt text-2xl" style="color: var(--success);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Confirm Bulk Restore</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to restore all selected QR codes? They will be moved back to active QR codes.
                </p>
                <div id="selectedQrCodesList" class="mt-3 max-h-40 overflow-y-auto p-3 rounded-lg"
                     style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"></div>
            </div>
        </div>
        <div class="modal-footer px-6 py-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('bulkRestoreModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <button onclick="executeBulkRestore()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success); border: 1px solid var(--success);">
                    <i class="fas fa-undo-alt mr-2"></i> Restore Selected
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Empty Trash Modal --}}
<div id="emptyTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('emptyTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Empty Trash</h3>
            <button onclick="closeModal('emptyTrashModal')" class="modal-close"><i class="fas fa-times" style="color: var(--text-secondary);"></i></button>
        </div>
        <div class="modal-body p-6">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--danger);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Empty Trash Confirmation</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    You are about to permanently delete all {{ $trashedQrCodes->total() }} QR codes in trash. This action cannot be undone.
                </p>
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-center text-sm" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        All QR code images will be permanently deleted from the server.
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer px-6 py-4 border-t" style="border-color: var(--border-color);">
            <div class="flex justify-end space-x-3">
                <button onclick="closeModal('emptyTrashModal')"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    Cancel
                </button>
                <form action="{{ $urlEmptyTrash }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                            style="background-color: var(--danger); border: 1px solid var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Empty Trash
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
             style="border-color: var(--warning); border-top-color: transparent;"></div>
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
input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }
#bulkActionsBar { animation: slideDown 0.3s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
.pagination { display: flex; list-style: none; padding: 0; margin: 0; }
.pagination li { margin: 0 2px; }
.pagination li a, .pagination li span { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 8px; border-radius: 6px; font-size: 0.875rem; transition: all 0.2s ease; }
.pagination li a { background-color: var(--bg-secondary); color: var(--text-primary); text-decoration: none; border: 1px solid var(--border-color); }
.pagination li a:hover { background-color: var(--primary); color: white; border-color: var(--primary); }
.pagination li.active span { background-color: var(--primary); color: white; border: 1px solid var(--primary); }
.pagination li.disabled span { background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color); opacity: 0.5; cursor: not-allowed; }
@media (max-width: 1024px) { table { font-size: 0.875rem; } th, td { padding: 0.75rem 0.5rem; } }
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
    .action-btn { width: 32px; height: 32px; }
    .action-btn i { font-size: 0.875rem; }
}
@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .card .p-6 { padding: 1rem; }
    table { display: block; overflow-x: auto; white-space: nowrap; }
    .action-btn { width: 28px; height: 28px; }
}
button:focus, a:focus, input:focus { outline: none; box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.5); }
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg-secondary); border-radius: 4px; }
::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }
.grayscale { filter: grayscale(100%); }
.opacity-50 { opacity: 0.5; }
.opacity-75 { opacity: 0.75; }
</style>
@endsection

@push('scripts')
<script>
window.postData = { id: {{ $post->id }}, name: @js($post->name) };
const QR_BASE = @js($urlQrBase); // e.g. /security/posts/1/qr-codes
const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content || @js(csrf_token());

let currentQrId = null;
let selectedQrCodes = new Set();

function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
}

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        const m = e.target.closest('.modal');
        if (m) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => {
            if (!m.classList.contains('hidden')) { m.classList.add('hidden'); document.body.style.overflow = 'auto'; }
        });
    }
});

function showRestoreModal(qrId, qrName) {
    currentQrId = qrId;
    document.getElementById('restoreQrName').textContent = qrName;
    document.getElementById('restoreForm').action = `${QR_BASE}/trash/${qrId}/restore`;
    openModal('restoreModal');
}

function showForceDeleteModal(qrId, qrName) {
    currentQrId = qrId;
    document.getElementById('forceDeleteQrName').textContent = qrName;
    document.getElementById('forceDeleteForm').action = `${QR_BASE}/trash/${qrId}/force-delete`;
    openModal('forceDeleteModal');
}

function showEmptyTrashModal() { openModal('emptyTrashModal'); }

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.qr-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
        checkbox.checked ? selectedQrCodes.add(cb.value) : selectedQrCodes.delete(cb.value);
    });
    updateBulkActions();
}

function selectAll() {
    document.querySelectorAll('.qr-checkbox').forEach(cb => { cb.checked = true; selectedQrCodes.add(cb.value); });
    document.getElementById('selectAllCheckbox').checked = true;
    updateBulkActions();
}

function deselectAll() {
    document.querySelectorAll('.qr-checkbox').forEach(cb => { cb.checked = false; selectedQrCodes.delete(cb.value); });
    document.getElementById('selectAllCheckbox').checked = false;
    updateBulkActions();
}

function updateBulkActions() {
    selectedQrCodes.clear();
    document.querySelectorAll('.qr-checkbox:checked').forEach(cb => {
        if (cb.value && !isNaN(cb.value) && cb.value.trim() !== '') selectedQrCodes.add(cb.value);
    });

    const bar = document.getElementById('bulkActionsBar');
    const span = document.getElementById('selectedCount');
    if (bar) bar.style.display = selectedQrCodes.size > 0 ? 'block' : 'none';
    if (span) span.textContent = selectedQrCodes.size;
}

function showBulkRestoreModal() {
    if (selectedQrCodes.size === 0) { showToast('Please select at least one QR code to restore', 'warning'); return; }

    const list = document.getElementById('selectedQrCodesList');
    list.innerHTML = '';
    const names = [];
    document.querySelectorAll('.qr-checkbox:checked').forEach(cb => {
        if (cb.value && !isNaN(cb.value) && cb.value.trim() !== '') {
            const row = cb.closest('tr');
            const el = row?.querySelector('td:nth-child(3) .font-medium');
            if (el) names.push(`<div class="text-sm py-1" style="color: var(--text-primary);">• ${el.textContent}</div>`);
        }
    });

    if (names.length === 0) { showToast('No valid QR codes selected', 'error'); return; }
    list.innerHTML = names.join('');
    openModal('bulkRestoreModal');
}

function bulkRestore() { showBulkRestoreModal(); }

function executeBulkRestore() {
    closeModal('bulkRestoreModal');
    const validIds = Array.from(selectedQrCodes).filter(id => !isNaN(id) && id.trim() !== '');
    if (validIds.length === 0) { showToast('No valid QR codes to restore', 'error'); return; }

    showLoading('Restoring QR codes...');

    fetch(`${QR_BASE}/trash/bulk-restore`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': CSRF,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ qr_code_ids: validIds }),
    })
    .then(async r => {
        if (!r.ok) throw new Error(`Server error: ${r.status}`);
        const ct = r.headers.get('content-type');
        if (ct && ct.includes('application/json')) return r.json();
        throw new Error('Invalid response format');
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore', 'error');
        }
    })
    .catch(err => { hideLoading(); console.error(err); showToast(err.message || 'Network error', 'error'); });
}

function bulkForceDelete() {
    if (selectedQrCodes.size === 0) { showToast('Please select at least one QR code to delete', 'warning'); return; }

    const validIds = Array.from(selectedQrCodes).filter(id => !isNaN(id) && id.trim() !== '');
    if (validIds.length === 0) { showToast('No valid QR codes to delete', 'error'); return; }

    if (!confirm(`Are you sure you want to permanently delete ${validIds.length} QR code(s)? This action cannot be undone.`)) return;

    showLoading('Deleting QR codes...');

    const promises = validIds.map(id =>
        fetch(`${QR_BASE}/trash/${id}/force-delete`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
    );

    Promise.all(promises)
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(results => {
            hideLoading();
            if (results.every(r => r.success)) {
                showToast('Selected QR codes have been permanently deleted', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                const failed = results.filter(r => !r.success).length;
                showToast(`${failed} QR code(s) could not be deleted`, 'error');
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(err => { hideLoading(); console.error(err); showToast(err.message || 'Network error', 'error'); });
}

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
    t.style.cssText = `background-color:${c.bg};color:${c.text};border:1px solid ${c.border};margin-bottom:.5rem;z-index:10002;`;
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
    setTimeout(() => {
        if (t.parentNode) { t.classList.add('translate-x-full'); setTimeout(() => { if (t.parentNode) t.remove(); }, 300); }
    }, 5000);
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
        btn.addEventListener('mouseenter', function () { this.style.transform = 'translateY(-2px)'; this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)'; });
        btn.addEventListener('mouseleave', function () { this.style.transform = 'translateY(0)'; this.style.boxShadow = 'none'; });
    });
    document.querySelectorAll('.qr-checkbox').forEach(cb => cb.addEventListener('change', updateBulkActions));
});

window.addEventListener('unhandledrejection', function (event) {
    console.error('Unhandled promise rejection:', event.reason);
    hideLoading();
    showToast('An unexpected error occurred', 'error');
});
</script>
@endpush