{{-- developer/super-admins/trash.blade.php --}}
@php
    $isDeveloper = auth()->user()->isDeveloper();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    $statistics = $statistics ?? [];
    $trashedSuperAdmins = $trashedSuperAdmins ?? collect();
    
    $searchTerm = request('search', '');
    $dateFrom = request('date_from', '');
    $dateTo = request('date_to', '');
    
    $canRestore = $isDeveloper && !$isSuperAdmin;
    $canPermanentDelete = $isDeveloper && !$isSuperAdmin;
    $canEmptyTrash = $isDeveloper && !$isSuperAdmin && ($statistics['total_trashed'] ?? 0) > 0;
@endphp

@extends('layouts.dev')

@section('title', 'Trashed Super Admins - Developer Portal')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white; border-color: var(--warning);">
                        <i class="fas fa-trash-restore text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--warning);"></i> 
                        Trashed Super Admins
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and manage soft-deleted Super Admin accounts</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($statistics['total_trashed'] ?? 0) }} items in trash</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-1"></i>
                        <span>{{ number_format($statistics['trashed_this_week'] ?? 0) }} this week</span>
                    </div>
                </div>
            </div>
            <div>
                <a href="{{ route('developer.super-admins.index') }}" 
                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Super Admins
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-trash-alt text-lg" style="color: var(--warning);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['total_trashed'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Total in Trash</p>
        </div>
        
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--primary-rgb), 0.1);">
                <i class="fas fa-calendar-week text-lg" style="color: var(--primary);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['trashed_this_week'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">This Week</p>
        </div>
        
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--success-rgb), 0.1);">
                <i class="fas fa-calendar-alt text-lg" style="color: var(--success);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['trashed_this_month'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">This Month</p>
        </div>
        
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--danger-rgb), 0.1);">
                <i class="fas fa-hourglass-half text-lg" style="color: var(--danger);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">
                @if(isset($statistics['oldest_trashed']) && $statistics['oldest_trashed'])
                    {{ $statistics['oldest_trashed']->deleted_at->diffInDays(now()) }} days
                @else
                    0
                @endif
            </p>
            <p class="text-xs" style="color: var(--text-secondary);">Oldest in Trash</p>
        </div>
    </div>

    <!-- Warning Card for Empty Trash -->
    <div class="card border-l-4" style="border-left-color: var(--danger); background-color: rgba(var(--danger-rgb), 0.05);">
        <div class="p-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3 text-xl" style="color: var(--danger);"></i>
                    <div>
                        <p class="font-medium" style="color: var(--text-primary);">Items in trash can be restored or permanently deleted</p>
                        <p class="text-sm" style="color: var(--text-secondary);">Items older than 30 days may be automatically removed by system policy.</p>
                    </div>
                </div>
                @if($canEmptyTrash && ($statistics['total_trashed'] ?? 0) > 0)
                <button onclick="showEmptyTrashModal()" 
                        class="px-4 py-2 rounded-lg font-medium text-white transition"
                        style="background-color: var(--danger);">
                    <i class="fas fa-trash-alt mr-2"></i> Empty Entire Trash
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="p-5">
            <form method="GET" action="{{ route('developer.super-admins.trash') }}" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-search mr-1"></i> Search
                    </label>
                    <input type="text" 
                           name="search" 
                           value="{{ $searchTerm }}" 
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                           placeholder="Name, email, phone...">
                </div>
                
                <div class="w-48">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-calendar mr-1"></i> From Date
                    </label>
                    <input type="date" 
                           name="date_from" 
                           value="{{ $dateFrom }}" 
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                </div>
                
                <div class="w-48">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-calendar mr-1"></i> To Date
                    </label>
                    <input type="date" 
                           name="date_to" 
                           value="{{ $dateTo }}" 
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                </div>
                
                <div class="w-32">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                        <i class="fas fa-table mr-1"></i> Per Page
                    </label>
                    <select name="per_page" class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                            style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                            onchange="this.form.submit()">
                        <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="px-4 py-2 rounded-lg font-medium text-white transition"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-filter mr-2"></i> Apply
                    </button>
                </div>
                
                <div>
                    <a href="{{ route('developer.super-admins.trash') }}" class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Trashed Super Admins Table -->
    <div class="card">
        <div class="p-5">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    Deleted Super Admins ({{ $trashedSuperAdmins->total() }})
                </h3>
                
                @if($trashedSuperAdmins->total() > 0 && $canRestore)
                <div class="flex gap-2">
                    <button type="button" 
                            onclick="showBulkRestoreModal()"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center transition"
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-trash-restore mr-2"></i> Bulk Restore
                    </button>
                    @if($canPermanentDelete)
                    <button type="button" 
                            onclick="showBulkPermanentDeleteModal()"
                            class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center transition"
                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-skull-crossbones mr-2"></i> Bulk Delete
                    </button>
                    @endif
                </div>
                @endif
            </div>

            @if($trashedSuperAdmins->isEmpty())
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-trash-restore text-2xl" style="color: var(--warning);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Trash is Empty</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">No Super Admin accounts found in trash.</p>
                    <a href="{{ route('developer.super-admins.index') }}" 
                       class="mt-4 inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition"
                       style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Super Admins
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                    <input type="checkbox" id="selectAllTrash" class="rounded border-gray-300 dark:border-gray-600">
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Super Admin</th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Contact</th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted By</th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Deleted At</th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Days in Trash</th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trashedSuperAdmins as $superAdmin)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    <input type="checkbox" class="trash-checkbox rounded border-gray-300 dark:border-gray-600" value="{{ $superAdmin->id }}">
                                </td>
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0 text-sm font-semibold"
                                             style="background: linear-gradient(135deg, var(--warning) 0%, var(--danger) 100%); color: white;">
                                            {{ $superAdmin->initials ?? strtoupper(substr($superAdmin->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $superAdmin->name }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->username ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $superAdmin->email }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->phone ?? 'No phone' }}</div>
                                </td>
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $superAdmin->deleted_by_name ?? 'Unknown' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">ID: {{ $superAdmin->deleted_by ?? 'System' }}</div>
                                </td>
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    <div class="text-sm" style="color: var(--text-primary);">{{ $superAdmin->deleted_at->format('M d, Y H:i') }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->deleted_at->diffForHumans() }}</div>
                                </td>
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    @php
                                        $daysInTrash = $superAdmin->deleted_at->diffInDays(now());
                                        $isExpired = $daysInTrash > 30;
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                          style="{{ $isExpired ? 'background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);' : 'background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);' }}">
                                        <i class="fas fa-hourglass-{{ $isExpired ? 'end' : 'half' }} mr-1"></i>
                                        {{ $daysInTrash }} days
                                    </span>
                                </td>
                                <td class="p-3" style="background-color: var(--card-bg);">
                                    <div class="flex flex-wrap gap-2">
                                        @if($canRestore)
                                        <button onclick="restoreUser({{ $superAdmin->id }}, '{{ addslashes($superAdmin->name) }}')" 
                                                class="action-btn restore" title="Restore">
                                            <i class="fas fa-trash-restore"></i>
                                        </button>
                                        @endif
                                        @if($canPermanentDelete)
                                        <button onclick="showPermanentDeleteModal({{ $superAdmin->id }}, '{{ addslashes($superAdmin->name) }}')" 
                                                class="action-btn permanent-delete" title="Permanently Delete">
                                            <i class="fas fa-skull-crossbones"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 mt-4 border-t" style="border-color: var(--border-color);">
                    <div class="text-sm" style="color: var(--text-secondary);">
                        Showing {{ $trashedSuperAdmins->firstItem() }} to {{ $trashedSuperAdmins->lastItem() }} of {{ $trashedSuperAdmins->total() }} entries
                    </div>
                    <div>
                        {{ $trashedSuperAdmins->appends(request()->except('page'))->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Restore Confirmation Modal - Theme Aware -->
<div id="restoreModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideRestoreModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-restore mr-2" style="color: var(--primary);"></i> Confirm Restore
                </h3>
                <button type="button" onclick="hideRestoreModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm" id="restoreMessage" style="color: var(--text-secondary);"></p>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideRestoreModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" id="confirmRestoreBtn"
                        class="px-4 py-2 rounded-lg font-medium text-white transition"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-trash-restore mr-2"></i> Restore
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Permanent Delete Modal - Theme Aware -->
<div id="permanentDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hidePermanentDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-skull-crossbones mr-2" style="color: var(--danger);"></i> Permanently Delete
                </h3>
                <button type="button" onclick="hidePermanentDeleteModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm mb-4" id="permanentDeleteMessage" style="color: var(--text-secondary);"></p>
                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <p class="font-medium text-sm" style="color: var(--danger);">⚠️ Warning:</p>
                    <ul class="text-xs mt-1 space-y-1" style="color: var(--danger);">
                        <li>• This action is IRREVERSIBLE</li>
                        <li>• All user data will be permanently deleted</li>
                        <li>• Related invitations will be deleted</li>
                        <li>• Cannot be restored after this action</li>
                    </ul>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Type <strong class="font-mono" style="color: var(--danger);">PERMANENT</strong> to confirm:
                    </label>
                    <input type="text" 
                           id="permanent_delete_confirmation" 
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                           placeholder="Type PERMANENT here"
                           oninput="checkPermanentDeleteConfirmation(this)">
                </div>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hidePermanentDeleteModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" id="confirmPermanentDeleteBtn"
                        class="px-4 py-2 rounded-lg font-medium text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background-color: var(--danger);"
                        disabled>
                    <i class="fas fa-skull-crossbones mr-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Empty Trash Modal - Theme Aware -->
<div id="emptyTrashModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideEmptyTrashModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Empty Entire Trash
                </h3>
                <button type="button" onclick="hideEmptyTrashModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm mb-4" style="color: var(--text-secondary);">
                    Are you sure you want to permanently delete <strong style="color: var(--danger);">{{ number_format($statistics['total_trashed'] ?? 0) }}</strong> Super Admin(s) from trash?
                </p>
                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                    <p class="font-medium text-sm" style="color: var(--danger);">⚠️ DANGER: This action cannot be undone!</p>
                    <ul class="text-xs mt-1 space-y-1" style="color: var(--danger);">
                        <li>• All trashed Super Admins will be permanently deleted</li>
                        <li>• Data cannot be recovered</li>
                        <li>• Users who created other accounts will be skipped</li>
                    </ul>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Type <strong class="font-mono" style="color: var(--danger);">EMPTY_ALL_TRASH</strong> to confirm:
                    </label>
                    <input type="text" 
                           id="empty_trash_confirmation" 
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                           placeholder="Type EMPTY_ALL_TRASH here"
                           oninput="checkEmptyTrashConfirmation(this)">
                </div>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideEmptyTrashModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" id="confirmEmptyTrashBtn"
                        class="px-4 py-2 rounded-lg font-medium text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background-color: var(--danger);"
                        disabled>
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete All
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let restoreUserId = null;
let permanentDeleteUserId = null;
const baseUrl = '/developer/super-admins';

// Select All checkbox
const selectAllTrash = document.getElementById('selectAllTrash');
if (selectAllTrash) {
    selectAllTrash.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.trash-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkButtonsState();
    });
}

// Update bulk buttons state based on selected checkboxes
function updateBulkButtonsState() {
    const selectedCount = document.querySelectorAll('.trash-checkbox:checked').length;
    const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
    const bulkPermanentDeleteBtn = document.getElementById('bulkPermanentDeleteBtn');
    
    if (bulkRestoreBtn) {
        if (selectedCount === 0) {
            bulkRestoreBtn.disabled = true;
            bulkRestoreBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            bulkRestoreBtn.disabled = false;
            bulkRestoreBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
    
    if (bulkPermanentDeleteBtn) {
        if (selectedCount === 0) {
            bulkPermanentDeleteBtn.disabled = true;
            bulkPermanentDeleteBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            bulkPermanentDeleteBtn.disabled = false;
            bulkPermanentDeleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
}

// Add event listeners to all trash checkboxes
document.querySelectorAll('.trash-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkButtonsState);
});

// Show notification function
function showNotification(message, type = 'success') {
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white`;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2 text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function restoreUser(userId, userName) {
    restoreUserId = userId;
    const messageEl = document.getElementById('restoreMessage');
    if (messageEl) {
        messageEl.innerHTML = `Are you sure you want to restore Super Admin: <strong>${escapeHtml(userName)}</strong>?`;
    }
    const modal = document.getElementById('restoreModal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideRestoreModal() {
    const modal = document.getElementById('restoreModal');
    if (modal) modal.classList.add('hidden');
    restoreUserId = null;
    document.body.style.overflow = 'auto';
}

function showPermanentDeleteModal(userId, userName) {
    permanentDeleteUserId = userId;
    const messageEl = document.getElementById('permanentDeleteMessage');
    if (messageEl) {
        messageEl.innerHTML = `⚠️ Permanently delete Super Admin: <strong>${escapeHtml(userName)}</strong>? This action cannot be undone.`;
    }
    const confirmationInput = document.getElementById('permanent_delete_confirmation');
    if (confirmationInput) confirmationInput.value = '';
    const confirmBtn = document.getElementById('confirmPermanentDeleteBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    const modal = document.getElementById('permanentDeleteModal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hidePermanentDeleteModal() {
    const modal = document.getElementById('permanentDeleteModal');
    if (modal) modal.classList.add('hidden');
    permanentDeleteUserId = null;
    document.body.style.overflow = 'auto';
}

function checkPermanentDeleteConfirmation(input) {
    const confirmBtn = document.getElementById('confirmPermanentDeleteBtn');
    if (!confirmBtn) return;
    
    if (input.value === 'PERMANENT') {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function showEmptyTrashModal() {
    const confirmationInput = document.getElementById('empty_trash_confirmation');
    if (confirmationInput) confirmationInput.value = '';
    const confirmBtn = document.getElementById('confirmEmptyTrashBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    const modal = document.getElementById('emptyTrashModal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideEmptyTrashModal() {
    const modal = document.getElementById('emptyTrashModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function checkEmptyTrashConfirmation(input) {
    const confirmBtn = document.getElementById('confirmEmptyTrashBtn');
    if (!confirmBtn) return;
    
    if (input.value === 'EMPTY_ALL_TRASH') {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

// Confirm restore button with AJAX
const confirmRestoreBtn = document.getElementById('confirmRestoreBtn');
if (confirmRestoreBtn) {
    confirmRestoreBtn.addEventListener('click', async function() {
        if (restoreUserId) {
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';
            
            try {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                
                const response = await fetch(`${baseUrl}/${restoreUserId}/restore`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message || 'Super Admin restored successfully!', 'success');
                    hideRestoreModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to restore Super Admin', 'error');
                    hideRestoreModal();
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while restoring.', 'error');
                hideRestoreModal();
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    });
}

// Confirm permanent delete button with AJAX
const confirmPermanentDeleteBtn = document.getElementById('confirmPermanentDeleteBtn');
if (confirmPermanentDeleteBtn) {
    confirmPermanentDeleteBtn.addEventListener('click', async function() {
        if (permanentDeleteUserId) {
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
            
            try {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                
                const response = await fetch(`${baseUrl}/${permanentDeleteUserId}/force-delete`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-HTTP-Method-Override': 'DELETE'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message || 'Super Admin permanently deleted!', 'success');
                    hidePermanentDeleteModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to permanently delete', 'error');
                    hidePermanentDeleteModal();
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while deleting.', 'error');
                hidePermanentDeleteModal();
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    });
}

// Confirm empty trash button with AJAX
const confirmEmptyTrashBtn = document.getElementById('confirmEmptyTrashBtn');
if (confirmEmptyTrashBtn) {
    confirmEmptyTrashBtn.addEventListener('click', async function() {
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Emptying Trash...';
        
        try {
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('confirmation', 'EMPTY_ALL_TRASH');
            
            const response = await fetch(`${baseUrl}/empty-trash`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-HTTP-Method-Override': 'DELETE'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification(data.message || 'Trash emptied successfully!', 'success');
                hideEmptyTrashModal();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to empty trash', 'error');
                hideEmptyTrashModal();
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred while emptying trash.', 'error');
            hideEmptyTrashModal();
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
}

function createInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

// Bulk Restore with AJAX
async function showBulkRestoreModal() {
    const selected = document.querySelectorAll('.trash-checkbox:checked');
    if (selected.length === 0) {
        showNotification('Please select at least one user to restore.', 'error');
        return;
    }
    
    if (!confirm(`Restore ${selected.length} Super Admin(s) from trash?`)) {
        return;
    }
    
    const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
    const originalHtml = bulkRestoreBtn ? bulkRestoreBtn.innerHTML : 'Restore';
    if (bulkRestoreBtn) {
        bulkRestoreBtn.disabled = true;
        bulkRestoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Restoring...';
    }
    
    try {
        const userIds = Array.from(selected).map(cb => cb.value);
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        userIds.forEach(id => {
            formData.append('user_ids[]', id);
        });
        
        const response = await fetch(`${baseUrl}/bulk-restore`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            let message = data.message || `Restored ${data.results.success} out of ${userIds.length} Super Admins`;
            if (data.results.failed > 0) {
                message += `. Failed: ${data.results.failed}`;
            }
            showNotification(message, data.results.failed > 0 ? 'warning' : 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Bulk restore failed', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred during bulk restore.', 'error');
    } finally {
        if (bulkRestoreBtn) {
            bulkRestoreBtn.disabled = false;
            bulkRestoreBtn.innerHTML = originalHtml;
        }
    }
}

// Bulk Permanent Delete with AJAX
async function showBulkPermanentDeleteModal() {
    const selected = document.querySelectorAll('.trash-checkbox:checked');
    if (selected.length === 0) {
        showNotification('Please select at least one user to permanently delete.', 'error');
        return;
    }
    
    if (!confirm(`⚠️ PERMANENT: Delete ${selected.length} Super Admin(s) permanently? This cannot be undone.`)) {
        return;
    }
    
    const bulkPermanentDeleteBtn = document.getElementById('bulkPermanentDeleteBtn');
    const originalHtml = bulkPermanentDeleteBtn ? bulkPermanentDeleteBtn.innerHTML : 'Delete';
    if (bulkPermanentDeleteBtn) {
        bulkPermanentDeleteBtn.disabled = true;
        bulkPermanentDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
    }
    
    try {
        const userIds = Array.from(selected).map(cb => cb.value);
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('confirmation', 'accepted');
        userIds.forEach(id => {
            formData.append('user_ids[]', id);
        });
        
        const response = await fetch(`${baseUrl}/bulk-permanent-delete`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-HTTP-Method-Override': 'DELETE'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            let message = data.message || `Permanently deleted ${data.results.success} out of ${userIds.length} Super Admins`;
            if (data.results.failed > 0) {
                message += `. Failed: ${data.results.failed}`;
                if (data.results.details && data.results.details.length > 0) {
                    message += ` - ${data.results.details.map(d => d.message).join(', ')}`;
                }
            }
            showNotification(message, data.results.failed > 0 ? 'warning' : 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Bulk permanent delete failed', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred during bulk delete.', 'error');
    } finally {
        if (bulkPermanentDeleteBtn) {
            bulkPermanentDeleteBtn.disabled = false;
            bulkPermanentDeleteBtn.innerHTML = originalHtml;
        }
    }
}

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideRestoreModal();
        hidePermanentDeleteModal();
        hideEmptyTrashModal();
    }
});

// Add CSS animation for notification
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .custom-notification {
        z-index: 9999;
        backdrop-filter: blur(8px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
    }
    
    .btn-disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
`;
document.head.appendChild(style);

// Initialize bulk buttons state on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBulkButtonsState();
});
</script>

<style>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.action-btn.restore {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.action-btn.restore:hover {
    background-color: rgba(var(--primary-rgb), 0.2);
    transform: translateY(-2px);
}

.action-btn.permanent-delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.action-btn.permanent-delete:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-2px);
}

/* Modal animations */
#restoreModal, #permanentDeleteModal, #emptyTrashModal {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.7rem;
}

td {
    vertical-align: middle;
}
</style>
@endpush

@endsection