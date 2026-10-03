@extends('layouts.app')

@section('title', 'Trashed QR Codes - ' . $post->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
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
                    <!-- Bulk Restore Button -->
                    <button onclick="showBulkRestoreModal()"
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center bulk-restore-btn"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-undo-alt mr-2"></i> Bulk Restore
                    </button>
                    
                    <!-- Empty Trash Button -->
                    @if($trashedQrCodes->total() > 0)
                        <button onclick="showEmptyTrashModal()"
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                        </button>
                    @endif
                    
                    <!-- Back to QR Codes -->
                    <a href="{{ route('admin.security-posts.qr-codes.index', ['securityPost' => $post->id]) }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to QR Codes
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="card p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.security-posts.index') }}" 
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Security Posts
                </a>
                <span style="color: var(--text-secondary);">/</span>
                <a href="{{ route('admin.security-posts.show', ['securityPost' => $post->id]) }}" 
                   class="inline-flex items-center text-sm font-medium hover:underline"
                   style="color: var(--primary);">
                    {{ $post->name }}
                </a>
                <span style="color: var(--text-secondary);">/</span>
                <a href="{{ route('admin.security-posts.qr-codes.index', ['securityPost' => $post->id]) }}" 
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

    <!-- Warning Alert -->
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

    <!-- Trash Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total Trashed -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-trash-alt" style="color: var(--warning);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $trashedQrCodes->total() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total in Trash</div>
                </div>
            </div>
        </div>

        <!-- Restorable -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-undo-alt" style="color: var(--success);"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $trashedQrCodes->count() }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Restorable</div>
                </div>
            </div>
        </div>

        <!-- Expiring Soon -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                </div>
                <div>
                    @php
                        $expiringCount = $trashedQrCodes->filter(function($qr) {
                            return $qr->deleted_at && $qr->deleted_at->diffInDays(now()) >= (config('app.trash_retention_days', 30) - 7);
                        })->count();
                    @endphp
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $expiringCount }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Expiring Soon</div>
                </div>
            </div>
        </div>

        <!-- Storage Used -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                     style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-database" style="color: var(--danger);"></i>
                </div>
                <div>
                    @php
                        $totalSize = 0;
                        foreach($trashedQrCodes as $qr) {
                            if($qr->image_path && file_exists(storage_path('app/public/' . $qr->image_path))) {
                                $totalSize += filesize(storage_path('app/public/' . $qr->image_path));
                            }
                        }
                        $formattedSize = $totalSize > 1048576 ? round($totalSize / 1048576, 2) . ' MB' : 
                                        ($totalSize > 1024 ? round($totalSize / 1024, 2) . ' KB' : $totalSize . ' B');
                    @endphp
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $formattedSize }}</div>
                    <div class="text-sm" style="color: var(--text-secondary);">Storage Used</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    @if($trashedQrCodes->count() > 0)
    <div class="card p-4" id="bulkActionsBar" style="display: none;">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-sm font-medium" style="color: var(--text-primary);">
                    <span id="selectedCount">0</span> item(s) selected
                </span>
                <button onclick="selectAll()" class="text-sm hover:underline" style="color: var(--info);">
                    Select All
                </button>
                <button onclick="deselectAll()" class="text-sm hover:underline" style="color: var(--text-secondary);">
                    Deselect All
                </button>
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

    <!-- Trashed QR Codes Table -->
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
                                
                                // FIXED: Safely get metadata - no json_decode needed!
                                $metadata = $qrCode->metadata ?? [];
                                if (!is_array($metadata)) {
                                    if (is_string($metadata)) {
                                        $metadata = json_decode($metadata, true) ?? [];
                                    } else {
                                        $metadata = [];
                                    }
                                }
                                
                                // Safely access deletion info
                                $deletions = $metadata['deletions'] ?? [];
                                $lastDeletion = !empty($deletions) ? end($deletions) : null;
                                $deletedBy = $lastDeletion['deleted_by_name'] ?? 
                                            ($metadata['last_deletion']['deleted_by_name'] ?? 
                                            ($metadata['deleted_by_name'] ?? 'Unknown'));
                                
                                // Safely get deletion count
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
                                            'static' => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)', 'icon' => 'fa-infinity'],
                                            'one_time' => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-clock'],
                                            'time_based' => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'icon' => 'fa-hourglass-half'],
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
                                                <span style="color: {{ $isExpiring ? 'var(--warning)' : 'var(--success)' }};">
                                                    {{ $daysLeft }} days left
                                                </span>
                                            @else
                                                <span style="color: var(--danger);">Expired</span>
                                            @endif
                                        </div>
                                        <div class="w-full h-1 mt-2 rounded-full" style="background-color: var(--border-color);">
                                            @php
                                                $totalDays = config('app.trash_retention_days', 30);
                                                $percentage = min(100, (($totalDays - max(0, $daysLeft)) / $totalDays) * 100);
                                            @endphp
                                            <div class="h-1 rounded-full transition-all duration-300" 
                                                 style="width: {{ $percentage }}%; background-color: {{ $percentage >= 90 ? 'var(--danger)' : ($percentage >= 70 ? 'var(--warning)' : 'var(--success)') }};"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2">
                                        <!-- Restore Button -->
                                        <button onclick="showRestoreModal({{ $qrCode->id }}, '{{ addslashes($qrCode->name) }}')"
                                                class="action-btn" title="Restore QR Code"
                                                style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-undo-alt"></i>
                                        </button>
                                        
                                        <!-- Force Delete Button -->
                                        <button onclick="showForceDeleteModal({{ $qrCode->id }}, '{{ addslashes($qrCode->name) }}')"
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
                                        <a href="{{ route('admin.security-posts.qr-codes.index', ['securityPost' => $post->id]) }}" 
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

            <!-- Pagination -->
            @if($trashedQrCodes->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <div class="text-sm" style="color: var(--text-secondary);">
                            Showing {{ $trashedQrCodes->firstItem() }} to {{ $trashedQrCodes->lastItem() }} of {{ $trashedQrCodes->total() }} results
                        </div>
                        <div class="flex space-x-2">
                            {{ $trashedQrCodes->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Restore Single Modal -->
<div id="restoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('restoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Restore QR Code</h3>
            <button onclick="closeModal('restoreModal')" class="modal-close">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
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

<!-- Force Delete Single Modal -->
<div id="forceDeleteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('forceDeleteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Permanently Delete QR Code</h3>
            <button onclick="closeModal('forceDeleteModal')" class="modal-close">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
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

<!-- Bulk Restore Modal -->
<div id="bulkRestoreModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('bulkRestoreModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Bulk Restore QR Codes</h3>
            <button onclick="closeModal('bulkRestoreModal')" class="modal-close">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
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
                     style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <!-- Selected QR codes will be listed here -->
                </div>
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

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('emptyTrashModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Empty Trash</h3>
            <button onclick="closeModal('emptyTrashModal')" class="modal-close">
                <i class="fas fa-times" style="color: var(--text-secondary);"></i>
            </button>
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
                <form action="{{ route('admin.security-posts.qr-codes.trash.empty', ['securityPost' => $post->id]) }}" method="POST" class="inline">
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

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay hidden">
    <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-transparent mb-4"
             style="border-color: var(--warning); border-top-color: transparent;"></div>
        <div class="text-white font-medium" id="loadingMessage">Processing...</div>
    </div>
</div>

<!-- Include all the CSS styles from the index blade -->
<style>
:root {
    --primary: #4f46e5;
    --primary-rgb: 79, 70, 229;
    --secondary: #8b5cf6;
    --secondary-rgb: 139, 92, 246;
    --success: #10b981;
    --success-rgb: 16, 185, 129;
    --danger: #ef4444;
    --danger-rgb: 239, 68, 68;
    --warning: #f59e0b;
    --warning-rgb: 245, 158, 11;
    --info: #3b82f6;
    --info-rgb: 59, 130, 246;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --card-bg: #ffffff;
    --bg-secondary: #f9fafb;
    --border-color: #e5e7eb;
}

[data-theme="dark"] {
    --text-primary: #f3f4f6;
    --text-secondary: #9ca3af;
    --card-bg: #1f2937;
    --bg-secondary: #111827;
    --border-color: #374151;
}

.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    transition: all 0.3s ease;
    border: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(var(--primary-rgb), 0.3);
}

/* QR Preview Styles */
.qr-preview {
    transition: transform 0.2s ease;
}

.qr-preview:hover {
    transform: scale(1.1);
}

/* Action Button Styles */
.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s ease;
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    z-index: 10000;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(var(--danger-rgb), 0.1);
}

.modal-close:hover i {
    color: var(--danger) !important;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.75);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10001;
    animation: fadeIn 0.2s ease;
}

.loading-overlay.hidden {
    display: none;
}

/* Toast Styles */
#toast-container {
    z-index: 10002;
}

#toast-container > div {
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from { opacity: 0; transform: translateX(100%); }
    to { opacity: 1; transform: translateX(0); }
}

/* Table Styles */
table {
    border-collapse: collapse;
    width: 100%;
}

th {
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

td {
    font-size: 0.875rem;
}

/* Checkbox Styles */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Bulk Actions Bar */
#bulkActionsBar {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Pagination Styles */
.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
}

.pagination li {
    margin: 0 2px;
}

.pagination li a,
.pagination li span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.pagination li a {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    text-decoration: none;
    border: 1px solid var(--border-color);
}

.pagination li a:hover {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination li.active span {
    background-color: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.pagination li.disabled span {
    background-color: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive Styles */
@media (max-width: 1024px) {
    table {
        font-size: 0.875rem;
    }
    
    th, td {
        padding: 0.75rem 0.5rem;
    }
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-btn {
        width: 32px;
        height: 32px;
    }
    
    .action-btn i {
        font-size: 0.875rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .card .p-6 {
        padding: 1rem;
    }
    
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    .action-btn {
        width: 28px;
        height: 28px;
    }
}

/* Hover Effects */
.hover-scale {
    transition: transform 0.2s ease;
}

.hover-scale:hover {
    transform: scale(1.05);
}

/* Focus States */
button:focus, a:focus, input:focus {
    outline: none;
    ring: 2px solid var(--primary);
    ring-opacity: 0.5;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* Grayscale Filter */
.grayscale {
    filter: grayscale(100%);
}

/* Opacity Utilities */
.opacity-50 {
    opacity: 0.5;
}

.opacity-75 {
    opacity: 0.75;
}
</style>
@endsection

@push('scripts')
<script>
// Post data
window.postData = {
    id: {{ $post->id }},
    name: '{{ $post->name }}'
};

let currentQrId = null;
let selectedQrCodes = new Set();

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Restore Single Modal - FIXED: Updated form action URL
function showRestoreModal(qrId, qrName) {
    currentQrId = qrId;
    document.getElementById('restoreQrName').textContent = qrName;
    
    const restoreForm = document.getElementById('restoreForm');
    restoreForm.action = `/admin/security-posts/{{ $post->id }}/qr-codes/trash/${qrId}/restore`;
    
    openModal('restoreModal');
}

// Force Delete Single Modal - FIXED: Updated form action URL
function showForceDeleteModal(qrId, qrName) {
    currentQrId = qrId;
    document.getElementById('forceDeleteQrName').textContent = qrName;
    
    const forceDeleteForm = document.getElementById('forceDeleteForm');
    forceDeleteForm.action = `/admin/security-posts/{{ $post->id }}/qr-codes/trash/${qrId}/force-delete`;
    
    openModal('forceDeleteModal');
}

// Empty Trash Modal
function showEmptyTrashModal() {
    openModal('emptyTrashModal');
}

// Bulk Actions
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.qr-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        if (checkbox.checked) {
            selectedQrCodes.add(cb.value);
        } else {
            selectedQrCodes.delete(cb.value);
        }
    });
    updateBulkActions();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.qr-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = true;
        selectedQrCodes.add(cb.value);
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateBulkActions();
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.qr-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
        selectedQrCodes.delete(cb.value);
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.qr-checkbox:checked');
    selectedQrCodes.clear();
    checkboxes.forEach(cb => {
        // Validate that the value is a number
        if (cb.value && !isNaN(cb.value) && cb.value.trim() !== '') {
            selectedQrCodes.add(cb.value);
        }
    });
    
    const selectedCount = selectedQrCodes.size;
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    if (selectedCount > 0) {
        bulkActionsBar.style.display = 'block';
        selectedCountSpan.textContent = selectedCount;
    } else {
        bulkActionsBar.style.display = 'none';
    }
}

// Bulk Restore
function showBulkRestoreModal() {
    if (selectedQrCodes.size === 0) {
        showToast('Please select at least one QR code to restore', 'warning');
        return;
    }
    
    const listContainer = document.getElementById('selectedQrCodesList');
    listContainer.innerHTML = '';
    
    const selectedNames = [];
    document.querySelectorAll('.qr-checkbox:checked').forEach(cb => {
        // Validate that the value is a number
        if (cb.value && !isNaN(cb.value) && cb.value.trim() !== '') {
            const row = cb.closest('tr');
            const nameElement = row.querySelector('td:nth-child(3) .font-medium');
            if (nameElement) {
                const name = nameElement.textContent;
                selectedNames.push(`<div class="text-sm py-1" style="color: var(--text-primary);">• ${name}</div>`);
            }
        }
    });
    
    if (selectedNames.length === 0) {
        showToast('No valid QR codes selected', 'error');
        return;
    }
    
    listContainer.innerHTML = selectedNames.join('');
    openModal('bulkRestoreModal');
}

function executeBulkRestore() {
    closeModal('bulkRestoreModal');
    
    // Filter out any non-numeric IDs
    const validIds = Array.from(selectedQrCodes).filter(id => !isNaN(id) && id.trim() !== '');
    
    if (validIds.length === 0) {
        showToast('No valid QR codes to restore', 'error');
        return;
    }
    
    showLoading('Restoring QR codes...');
    
    fetch(`/admin/security-posts/{{ $post->id }}/qr-codes/trash/bulk-restore`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            qr_code_ids: validIds
        })
    })
    .then(async response => {
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        }
        throw new Error('Invalid response format');
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to restore QR codes', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast(error.message || 'Network error occurred', 'error');
    });
}

// Bulk Force Delete - FIXED: Added validation and proper AJAX headers
function bulkForceDelete() {
    if (selectedQrCodes.size === 0) {
        showToast('Please select at least one QR code to delete', 'warning');
        return;
    }
    
    // Filter out any non-numeric IDs
    const validIds = Array.from(selectedQrCodes).filter(id => !isNaN(id) && id.trim() !== '');
    
    if (validIds.length === 0) {
        showToast('No valid QR codes to delete', 'error');
        return;
    }
    
    if (!confirm(`Are you sure you want to permanently delete ${validIds.length} QR code(s)? This action cannot be undone.`)) {
        return;
    }
    
    showLoading('Deleting QR codes...');
    
    // Create an array of fetch promises for each valid ID
    const promises = validIds.map(qrId => {
        return fetch(`/admin/security-posts/{{ $post->id }}/qr-codes/trash/${qrId}/force-delete`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    });
    
    Promise.all(promises)
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(results => {
            hideLoading();
            const allSuccessful = results.every(r => r.success);
            if (allSuccessful) {
                showToast('Selected QR codes have been permanently deleted', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                const failedCount = results.filter(r => !r.success).length;
                showToast(`${failedCount} QR code(s) could not be deleted`, 'error');
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            showToast(error.message || 'Network error occurred', 'error');
        });
}

// Toast notification - FIXED: Enhanced with better error handling
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.warn('Toast container not found');
        return;
    }
    
    const toast = document.createElement('div');
    
    const colors = {
        success: { bg: 'rgba(var(--success-rgb), 0.1)', text: 'var(--success)', border: 'rgba(var(--success-rgb), 0.3)', icon: 'fa-check-circle' },
        error: { bg: 'rgba(var(--danger-rgb), 0.1)', text: 'var(--danger)', border: 'rgba(var(--danger-rgb), 0.3)', icon: 'fa-exclamation-circle' },
        warning: { bg: 'rgba(var(--warning-rgb), 0.1)', text: 'var(--warning)', border: 'rgba(var(--warning-rgb), 0.3)', icon: 'fa-exclamation-triangle' },
        info: { bg: 'rgba(var(--info-rgb), 0.1)', text: 'var(--info)', border: 'rgba(var(--info-rgb), 0.3)', icon: 'fa-info-circle' }
    };
    
    const color = colors[type] || colors.info;
    
    toast.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transition-all duration-300 transform translate-x-full';
    toast.style.backgroundColor = color.bg;
    toast.style.color = color.text;
    toast.style.border = `1px solid ${color.border}`;
    toast.style.marginBottom = '0.5rem';
    toast.style.zIndex = '10002';
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${color.icon} mr-2"></i>
            <span class="text-sm font-medium">${message}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-4 hover:opacity-75">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Loading overlay
function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const messageEl = document.getElementById('loadingMessage');
    if (messageEl) messageEl.textContent = message;
    if (overlay) overlay.classList.remove('hidden');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('hidden');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Add click handlers to checkboxes
    document.querySelectorAll('.qr-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkActions);
    });
});

// Add global error handler
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    hideLoading();
    showToast('An unexpected error occurred', 'error');
});
</script>
@endpush