{{-- admin/testimonials/trashed.blade.php --}}
@php
    use App\Models\Testimonial;
    
    // Dynamic role detection
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $isDeveloper = auth()->user()->isDeveloper();
    
    // Determine layout and route prefix based on user role
    if ($isDeveloper) {
        $layout = 'layouts.dev';
        $routePrefix = 'developer.testimonials';
        $pageTitle = 'Trashed Testimonials - Developer Portal';
        $dashboardRoute = 'developer.dashboard';
        $roleBadge = 'Developer Access';
        $roleBadgeColor = 'info';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.testimonials';
        $pageTitle = 'Trashed Testimonials - Admin Portal';
        $dashboardRoute = 'admin.dashboard';
        $roleBadge = 'Admin Access';
        $roleBadgeColor = 'primary';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'testimonials';
        $pageTitle = 'Trashed Testimonials';
        $dashboardRoute = 'dashboard';
        $roleBadge = 'Staff Access';
        $roleBadgeColor = 'secondary';
    }
    
    $successMessage = session('success');
    $errorMessage = session('error');
    $activeCount = Testimonial::count();
    $trashedCount = $testimonials->total();
    
    // Determine if user can perform certain actions
    $canRestore = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canPermanentDelete = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canEmptyTrash = $isAdmin || $isSuperAdmin || $isDeveloper;
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--danger) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--danger);">
                        <i class="fas fa-trash-restore text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> 
                        Trashed Testimonials
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-trash mr-1"></i> {{ $trashedCount }} in Trash
                        </span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas {{ $isDeveloper ? 'fa-code' : 'fa-shield-alt' }} mr-1"></i> {{ $roleBadge }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Restore or permanently delete testimonials from trash</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        <span>{{ number_format($activeCount) }} active testimonial{{ $activeCount != 1 ? 's' : '' }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-clock mr-1"></i>
                        <span>Items are automatically deleted after 30 days in trash</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                
                <a href="{{ route($routePrefix . '.index') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Active
                </a>
                
                <a href="{{ route($dashboardRoute) }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Success Messages -->
    @if($successMessage)
    <div class="success-message" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                <span class="font-medium" style="color: var(--success);">{{ $successMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Error Messages -->
    @if($errorMessage)
    <div class="error-message" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3); border-radius: 0.5rem; padding: 1rem;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2" style="color: var(--danger);"></i>
                <span class="font-medium" style="color: var(--danger);">{{ $errorMessage }}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    @endif

    <!-- Info Banner -->
    @if($trashedCount > 0)
    <div class="rounded-lg p-4 flex items-center justify-between flex-wrap gap-3" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
        <div class="flex items-center">
            <i class="fas fa-clock mr-3 text-xl" style="color: var(--warning);"></i>
            <div>
                <p class="text-sm font-medium" style="color: var(--text-primary);">Items in trash are automatically deleted after 30 days</p>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">You can restore items anytime before they are permanently deleted.</p>
            </div>
        </div>
        @if($canEmptyTrash)
        <button type="button" onclick="showEmptyTrashModal()" 
                class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
            <i class="fas fa-trash-alt mr-1"></i> Empty Trash
        </button>
        @endif
    </div>
    @endif

    <!-- Main Content -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-trash-restore mr-2" style="color: var(--danger);"></i>
                        Deleted Testimonials
                    </h3>
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $testimonials->firstItem() }} to {{ $testimonials->lastItem() }} of {{ $testimonials->total() }} entries
                    </p>
                </div>
                
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <!-- Bulk Actions -->
                    @if($trashedCount > 0 && ($canRestore || $canPermanentDelete))
                    <div class="relative">
                        <button type="button" id="bulkActionsBtn" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-check-double mr-2"></i> Bulk Actions
                            <i class="fas fa-chevron-down ml-2 text-xs"></i>
                        </button>
                        
                        <div id="bulkActionsDropdown" class="absolute right-0 mt-2 w-56 rounded-lg shadow-lg z-10 hidden" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                            <div class="py-1">
                                @if($canRestore)
                                <button type="button" onclick="submitBulkRestore()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-trash-restore text-green-500 mr-2"></i> Restore Selected
                                </button>
                                @endif
                                @if($canPermanentDelete)
                                <hr class="my-1" style="border-color: var(--border-color);">
                                <button type="button" onclick="showBulkPermanentDeleteModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-trash-alt text-red-500 mr-2"></i> Permanently Delete Selected
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="flex items-center space-x-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Show:</span>
                        <select onchange="updatePerPage(this.value)" class="index-custom-dropdown text-sm py-1 px-2 rounded">
                            <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                </div>
            </div>

            @if($testimonials->isEmpty())
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-trash-restore text-2xl" style="color: var(--danger);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Trash is empty</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">No testimonials found in trash.</p>
                    <a href="{{ route($routePrefix . '.index') }}" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center mt-4">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Active Testimonials
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px]" id="trashedTable">
                        <thead>
                            <tr>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded" style="width: 18px; height: 18px;">
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 200px;">
                                    User Information
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 300px;">
                                    Testimonial Content
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 100px;">
                                    Rating
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 160px;">
                                    Deleted At
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 180px;">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="trashedTableBody">
                            @foreach($testimonials as $testimonial)
                            @php
                                $avatarUrl = $testimonial->avatar_url ?? ($testimonial->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($testimonial->name) . '&background=ef4444&color=fff');
                                $daysInTrash = $testimonial->deleted_at->diffInDays(now());
                                $isExpiringSoon = $daysInTrash >= 25;
                            @endphp
                            <tr data-testimonial-id="{{ $testimonial->id }}" data-deleted-at="{{ $testimonial->deleted_at }}">
                                <td class="p-3 text-center align-top">
                                    <input type="checkbox" class="trashed-checkbox" value="{{ $testimonial->id }}" onclick="updateBulkActions()">
                                </td>
                                
                                <!-- COLUMN 1: User Information -->
                                <td class="p-3 align-top">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mr-3">
                                            <img src="{{ $avatarUrl }}" alt="{{ $testimonial->name }}" class="w-10 h-10 rounded-full object-cover">
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-semibold text-sm" style="color: var(--text-primary);">
                                                {{ $testimonial->name }}
                                            </div>
                                            @if($testimonial->role)
                                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                <i class="fas fa-briefcase mr-1 text-xs"></i> {{ $testimonial->role }}
                                            </div>
                                            @endif
                                            @if($testimonial->email)
                                            <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                <i class="fas fa-envelope mr-1 text-xs"></i> {{ $testimonial->email }}
                                            </div>
                                            @endif
                                            @if($testimonial->user)
                                            <div class="text-xs mt-0.5">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-user-check mr-0.5 text-xs"></i> Registered User
                                                </span>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- COLUMN 2: Testimonial Content -->
                                <td class="p-3 align-top">
                                    <div class="testimonial-content">
                                        <p class="text-sm mb-2" style="color: var(--text-primary); line-height: 1.5;">
                                            {{ Str::limit($testimonial->content, 150) }}
                                        </p>
                                        @if($testimonial->content && strlen($testimonial->content) > 150)
                                        <button type="button" onclick="showFullContent('{{ addslashes($testimonial->content) }}', '{{ addslashes($testimonial->name) }}')" 
                                                class="text-xs hover:underline" style="color: var(--primary);">
                                            <i class="fas fa-expand mr-1"></i> Read more
                                        </button>
                                        @endif
                                        @if($testimonial->property_location)
                                        <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                            <i class="fas fa-map-marker-alt mr-1 text-xs"></i> {{ $testimonial->property_location }}
                                        </div>
                                        @endif
                                    </div>
                                </td>
                                
                                <!-- COLUMN 3: Rating -->
                                <td class="p-3 align-top">
                                    <div class="flex flex-col items-start gap-1">
                                        <div class="flex items-center">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="fas fa-star text-xs {{ $i <= $testimonial->rating ? 'text-warning' : 'text-muted' }}" 
                                                   style="{{ $i <= $testimonial->rating ? 'color: var(--warning);' : 'color: var(--text-secondary);' }}"></i>
                                            @endfor
                                        </div>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                              style="background-color: {{ $testimonial->rating >= 4 ? 'rgba(var(--success-rgb), 0.1)' : ($testimonial->rating >= 3 ? 'rgba(var(--warning-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)') }};
                                                     color: {{ $testimonial->rating >= 4 ? 'var(--success)' : ($testimonial->rating >= 3 ? 'var(--warning)' : 'var(--danger)') }};
                                                     border: 1px solid {{ $testimonial->rating >= 4 ? 'rgba(var(--success-rgb), 0.3)' : ($testimonial->rating >= 3 ? 'rgba(var(--warning-rgb), 0.3)' : 'rgba(var(--danger-rgb), 0.3)') }};">
                                            {{ $testimonial->rating }}/5.0
                                        </span>
                                    </div>
                                </td>
                                
                                <!-- COLUMN 4: Deleted At -->
                                <td class="p-3 align-top">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $testimonial->deleted_at->format('M j, Y g:i A') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1 text-xs"></i> {{ $testimonial->deleted_at->diffForHumans() }}
                                    </div>
                                    @if($isExpiringSoon)
                                    <div class="text-xs mt-1">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-exclamation-triangle mr-0.5 text-xs"></i> Expires soon
                                        </span>
                                    </div>
                                    @endif
                                    @php
                                        $daysLeft = 30 - $daysInTrash;
                                    @endphp
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2" style="background-color: var(--bg-secondary);">
                                        <div class="h-1.5 rounded-full" style="width: {{ ($daysInTrash / 30) * 100 }}%; background-color: {{ $isExpiringSoon ? 'var(--danger)' : 'var(--warning)' }};"></div>
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        {{ $daysLeft }} days until permanent deletion
                                    </div>
                                </td>
                                
                                <!-- COLUMN 5: Actions -->
                                <td class="p-3 align-top">
                                    <div class="flex flex-wrap items-center gap-1">
                                        @if($canRestore)
                                        <button type="button" 
                                                onclick="confirmRestore('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                                class="action-btn restore" 
                                                data-tooltip="Restore Testimonial">
                                            <i class="fas fa-trash-restore"></i>
                                        </button>
                                        @endif
                                        
                                        @if($canPermanentDelete)
                                        <button type="button" 
                                                onclick="showPermanentDeleteModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                                class="action-btn delete" 
                                                data-tooltip="Permanently Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                    <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                        Showing {{ $testimonials->firstItem() }} to {{ $testimonials->lastItem() }} of {{ $testimonials->total() }} entries
                    </div>
                    <div class="pagination">
                        {{ $testimonials->appends(request()->except('page'))->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL TEMPLATES -->
<!-- ============================================ -->

<!-- Confirm Restore Modal -->
<div id="restoreModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideRestoreModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-restore mr-2" style="color: var(--success);"></i> Restore Testimonial
                </h3>
                <button type="button" onclick="hideRestoreModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        Are you sure you want to restore testimonial from <strong id="restoreName" class="font-semibold"></strong>?
                    </p>
                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i> The testimonial will be moved back to active testimonials and will be visible on the website if approved.
                    </p>
                </div>
                
                <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <p>Restoring will:</p>
                            <ul class="list-disc list-inside mt-1 text-xs space-y-0.5">
                                <li>Move the testimonial back to active list</li>
                                <li>Keep all original data including approval status</li>
                                <li>Preserve featured status if previously featured</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideRestoreModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="executeRestore()" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-restore mr-2"></i> Restore Testimonial
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Permanent Delete Modal (Single) -->
<div id="permanentDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hidePermanentDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Permanently Delete Testimonial
                </h3>
                <button type="button" onclick="hidePermanentDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        Are you sure you want to permanently delete testimonial from <strong id="deleteName" class="font-semibold"></strong>?
                    </p>
                    <p class="text-xs mt-2" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-1"></i> This action cannot be undone.
                    </p>
                </div>
                
                <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-trash-alt mr-2 mt-0.5" style="color: var(--danger);"></i>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <p>This will permanently delete:</p>
                            <ul class="list-disc list-inside mt-1 text-xs space-y-0.5">
                                <li>The testimonial content</li>
                                <li>All metadata and ratings</li>
                                <li>Avatar image (if uploaded)</li>
                                <li>Approval records</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hidePermanentDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="executePermanentDelete()" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-alt mr-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Permanent Delete Modal -->
<div id="bulkPermanentDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkPermanentDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Bulk Permanently Delete
                </h3>
                <button type="button" onclick="hideBulkPermanentDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        Are you sure you want to permanently delete <strong id="bulkDeleteCount" class="font-semibold"></strong> selected testimonial(s)?
                    </p>
                    <div id="bulkDeleteList" class="max-h-32 overflow-y-auto space-y-1 p-2 rounded mt-3" style="background-color: var(--bg-secondary);"></div>
                    <p class="text-xs mt-3" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-1"></i> This action cannot be undone.
                    </p>
                </div>
                
                <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-trash-alt mr-2 mt-0.5" style="color: var(--danger);"></i>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <p>All selected testimonials will be permanently deleted immediately.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideBulkPermanentDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <button type="button" onclick="executeBulkPermanentDelete()" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Empty Trash Modal -->
<div id="emptyTrashModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideEmptyTrashModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Empty Trash
                </h3>
                <button type="button" onclick="hideEmptyTrashModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="text-sm mb-2" style="color: var(--text-primary);">
                        Are you sure you want to permanently delete <strong id="emptyTrashCount" class="font-semibold"></strong> testimonial(s) from trash?
                    </p>
                    <p class="text-xs mt-2" style="color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-1"></i> This action cannot be undone. All testimonials in trash will be permanently deleted.
                    </p>
                </div>
                
                <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <div class="flex items-start">
                        <i class="fas fa-trash-alt mr-2 mt-0.5" style="color: var(--danger);"></i>
                        <div class="text-sm" style="color: var(--text-secondary);">
                            <p>This action will permanently delete all testimonials currently in trash.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideEmptyTrashModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                <form id="emptyTrashForm" method="POST" action="{{ route($routePrefix . '.empty-trash') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Empty Trash
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Full Content Modal -->
<div id="fullContentModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideFullContentModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-quote-left mr-2" style="color: var(--primary);"></i> Full Testimonial
                </h3>
                <button type="button" onclick="hideFullContentModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <p class="text-sm font-medium" style="color: var(--text-secondary);">From: <span id="fullContentName" class="font-semibold" style="color: var(--text-primary);"></span></p>
                </div>
                <div class="p-4 rounded-lg mt-2" style="background-color: var(--bg-secondary);">
                    <p id="fullContentText" class="text-sm leading-relaxed" style="color: var(--text-primary);"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="hideFullContentModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Store route prefix for JavaScript
const routePrefix = '{{ $routePrefix }}';
const canRestore = {{ $canRestore ? 'true' : 'false' }};
const canPermanentDelete = {{ $canPermanentDelete ? 'true' : 'false' }};

let selectedTrashedIds = [];
let currentRestoreId = null;
let currentRestoreName = null;
let currentDeleteId = null;
let currentDeleteName = null;

document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    initTooltips();
    
    // Bulk actions dropdown
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    const bulkActionsDropdown = document.getElementById('bulkActionsDropdown');
    
    if (bulkActionsBtn) {
        bulkActionsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bulkActionsDropdown.classList.toggle('hidden');
        });
    }
    
    document.addEventListener('click', function(event) {
        if (bulkActionsDropdown && !bulkActionsDropdown.classList.contains('hidden')) {
            if (!bulkActionsBtn.contains(event.target) && !bulkActionsDropdown.contains(event.target)) {
                bulkActionsDropdown.classList.add('hidden');
            }
        }
    });
});

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.trashed-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.trashed-checkbox:checked');
    selectedTrashedIds = Array.from(checkboxes).map(cb => cb.value);
    
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    
    if (bulkActionsBtn) {
        if (selectedTrashedIds.length > 0) {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${selectedTrashedIds.length} Selected <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        } else {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> Bulk Actions <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        }
    }
}

// ============================================
// RESTORE FUNCTIONS
// ============================================
function confirmRestore(testimonialId, name) {
    if (!canRestore) {
        showNotification('error', 'You do not have permission to restore testimonials.');
        return;
    }
    
    currentRestoreId = testimonialId;
    currentRestoreName = name;
    
    const modal = document.getElementById('restoreModal');
    const nameSpan = document.getElementById('restoreName');
    nameSpan.textContent = name || 'Unknown User';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideRestoreModal() {
    const modal = document.getElementById('restoreModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentRestoreId = null;
        currentRestoreName = null;
    }
}

function executeRestore() {
    if (!currentRestoreId) {
        showNotification('error', 'No testimonial selected.');
        return;
    }
    
    const url = `/${routePrefix}/${currentRestoreId}/restore`;
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Testimonial restored successfully.');
            
            const row = document.querySelector(`tr[data-testimonial-id="${currentRestoreId}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.remove();
                    updateTableAfterRemoval();
                    
                    const remainingRows = document.querySelectorAll('#trashedTableBody tr').length;
                    if (remainingRows === 0) {
                        showEmptyState();
                    }
                }, 300);
            }
            
            hideRestoreModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

function submitBulkRestore() {
    if (!canRestore) {
        showNotification('error', 'You do not have permission to restore testimonials.');
        return;
    }
    
    if (selectedTrashedIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial to restore.');
        return;
    }
    
    const url = `/${routePrefix}/bulk-restore`;
    const formData = new FormData();
    selectedTrashedIds.forEach(id => formData.append('ids[]', id));
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || `${selectedTrashedIds.length} testimonial(s) restored successfully.`);
            
            selectedTrashedIds.forEach(id => {
                const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => row.remove(), 300);
                }
            });
            
            updateTableAfterRemoval();
            selectedTrashedIds = [];
            updateBulkActions();
            
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            
            setTimeout(() => {
                const remainingRows = document.querySelectorAll('#trashedTableBody tr').length;
                if (remainingRows === 0) {
                    showEmptyState();
                } else {
                    window.location.reload();
                }
            }, 1500);
            
            const dropdown = document.getElementById('bulkActionsDropdown');
            if (dropdown) dropdown.classList.add('hidden');
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

// ============================================
// PERMANENT DELETE FUNCTIONS
// ============================================
function showPermanentDeleteModal(testimonialId, name) {
    if (!canPermanentDelete) {
        showNotification('error', 'You do not have permission to permanently delete testimonials.');
        return;
    }
    
    currentDeleteId = testimonialId;
    currentDeleteName = name;
    
    const modal = document.getElementById('permanentDeleteModal');
    const nameSpan = document.getElementById('deleteName');
    nameSpan.textContent = name || 'Unknown User';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hidePermanentDeleteModal() {
    const modal = document.getElementById('permanentDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentDeleteId = null;
        currentDeleteName = null;
    }
}

function executePermanentDelete() {
    if (!currentDeleteId) {
        showNotification('error', 'No testimonial selected.');
        return;
    }
    
    const url = `/${routePrefix}/${currentDeleteId}`;
    
    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Testimonial permanently deleted.');
            
            const row = document.querySelector(`tr[data-testimonial-id="${currentDeleteId}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.remove();
                    updateTableAfterRemoval();
                    
                    const remainingRows = document.querySelectorAll('#trashedTableBody tr').length;
                    if (remainingRows === 0) {
                        showEmptyState();
                    }
                }, 300);
            }
            
            hidePermanentDeleteModal();
            selectedTrashedIds = [];
            updateBulkActions();
            
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

function showBulkPermanentDeleteModal() {
    if (!canPermanentDelete) {
        showNotification('error', 'You do not have permission to permanently delete testimonials.');
        return;
    }
    
    if (selectedTrashedIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial to delete.');
        return;
    }
    
    const modal = document.getElementById('bulkPermanentDeleteModal');
    const countSpan = document.getElementById('bulkDeleteCount');
    const listDiv = document.getElementById('bulkDeleteList');
    
    countSpan.textContent = selectedTrashedIds.length;
    
    listDiv.innerHTML = '';
    selectedTrashedIds.forEach(id => {
        const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
        if (row) {
            const name = row.querySelector('td:nth-child(2) .font-semibold')?.textContent || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}`;
            listDiv.appendChild(div);
        }
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkPermanentDeleteModal() {
    const modal = document.getElementById('bulkPermanentDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function executeBulkPermanentDelete() {
    const url = `/${routePrefix}/bulk-permanent-delete`;
    const formData = new FormData();
    selectedTrashedIds.forEach(id => formData.append('ids[]', id));
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || `${selectedTrashedIds.length} testimonial(s) permanently deleted.`);
            
            selectedTrashedIds.forEach(id => {
                const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => row.remove(), 300);
                }
            });
            
            updateTableAfterRemoval();
            selectedTrashedIds = [];
            updateBulkActions();
            
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            
            setTimeout(() => {
                const remainingRows = document.querySelectorAll('#trashedTableBody tr').length;
                if (remainingRows === 0) {
                    showEmptyState();
                } else {
                    window.location.reload();
                }
            }, 1500);
            
            hideBulkPermanentDeleteModal();
        } else {
            showNotification('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
    });
}

function showEmptyTrashModal() {
    if (!canEmptyTrash) {
        showNotification('error', 'You do not have permission to empty trash.');
        return;
    }
    
    const trashedCount = {{ $trashedCount }};
    if (trashedCount === 0) {
        showNotification('error', 'Trash is already empty.');
        return;
    }
    
    const modal = document.getElementById('emptyTrashModal');
    const countSpan = document.getElementById('emptyTrashCount');
    countSpan.textContent = trashedCount;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideEmptyTrashModal() {
    const modal = document.getElementById('emptyTrashModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ============================================
// FULL CONTENT MODAL
// ============================================
function showFullContent(content, name) {
    const modal = document.getElementById('fullContentModal');
    const nameSpan = document.getElementById('fullContentName');
    const contentSpan = document.getElementById('fullContentText');
    
    nameSpan.textContent = name || 'Unknown User';
    contentSpan.textContent = content;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideFullContentModal() {
    const modal = document.getElementById('fullContentModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function updateTableAfterRemoval() {
    const remainingRows = document.querySelectorAll('#trashedTableBody tr').length;
    const paginationDiv = document.querySelector('.flex.flex-col.md\\:flex-row.justify-between');
    if (paginationDiv) {
        const textDiv = paginationDiv.querySelector('.text-sm');
        if (textDiv) {
            const currentPage = getCurrentPage();
            const perPage = getPerPage();
            const firstItem = ((currentPage - 1) * perPage) + 1;
            const lastItem = Math.min(currentPage * perPage, remainingRows);
            textDiv.textContent = `Showing ${firstItem} to ${lastItem} of ${remainingRows} entries`;
        }
    }
}

function showEmptyState() {
    const tableContainer = document.querySelector('.overflow-x-auto');
    const paginationDiv = document.querySelector('.flex.flex-col.md\\:flex-row.justify-between');
    const parentDiv = document.querySelector('.card.p-6');
    
    if (tableContainer && parentDiv) {
        tableContainer.style.display = 'none';
        if (paginationDiv) paginationDiv.style.display = 'none';
        
        if (!document.getElementById('emptyState')) {
            const emptyStateHtml = `
                <div class="text-center py-12" id="emptyState">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-trash-restore text-2xl" style="color: var(--danger);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Trash is empty</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">No testimonials found in trash.</p>
                    <a href="${window.location.origin}/${routePrefix}" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center mt-4">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Active Testimonials
                    </a>
                </div>
            `;
            
            const emptyDiv = document.createElement('div');
            emptyDiv.innerHTML = emptyStateHtml;
            parentDiv.appendChild(emptyDiv);
        }
    }
}

function getCurrentPage() {
    const urlParams = new URLSearchParams(window.location.search);
    return parseInt(urlParams.get('page')) || 1;
}

function getPerPage() {
    const urlParams = new URLSearchParams(window.location.search);
    return parseInt(urlParams.get('per_page')) || 20;
}

function showNotification(type, message) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    const notification = document.createElement('div');
    notification.className = `mb-4 p-4 rounded-lg shadow-lg`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)';
    notification.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : '1px solid rgba(var(--danger-rgb), 0.3)';
    notification.style.animation = 'slideIn 0.3s ease';
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2" style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.success-message, .error-message').forEach(msg => {
            if (msg.style.display !== 'none') msg.style.display = 'none';
        });
    }, 5000);
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
                background: var(--text-primary);
                color: var(--card-bg);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                white-space: nowrap;
            `;
            document.body.appendChild(tooltip);
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
</script>

<style>
.index-custom-input,
.index-custom-dropdown,
.index-custom-textarea {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    width: 100%;
}

.index-custom-input:focus,
.index-custom-dropdown:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    cursor: pointer;
}

.action-btn.restore {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.restore:hover,
.action-btn.delete:hover {
    transform: translateY(-1px);
}

.modal-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 16px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--card-bg);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
}

.modal-close-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 50%;
}

.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.btn-danger {
    background-color: var(--danger) !important;
    color: white !important;
    border: 1px solid var(--danger) !important;
}

.btn-danger:hover {
    background-color: #c82333 !important;
}

table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

table th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.75rem;
    padding: 0.75rem;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-secondary) !important;
}

table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    background-color: var(--card-bg) !important;
}

.trashed-checkbox, #selectAll {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.text-warning {
    color: var(--warning) !important;
}

.text-muted {
    color: var(--text-secondary) !important;
}

.tooltip {
    pointer-events: none;
}

@media (max-width: 768px) {
    .action-btn {
        padding: 0.25rem 0.5rem;
    }
    .modal-container {
        margin: 1rem;
    }
}
</style>
@endpush
@endsection