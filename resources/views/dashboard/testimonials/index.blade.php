@extends($layout ?? 'layouts.app')

@php
    use App\Models\Testimonial;
    
    // Get stats from controller
    $totalCount = $stats['total'] ?? 0;
    $approvedCount = $stats['approved'] ?? 0;
    $pendingCount = $stats['pending'] ?? 0;
    $avgRating = $stats['avg_rating'] ?? 0;
    
    // Get user info
    $user = auth()->user();
    $userName = $user->name ?? '';
    $userEmail = $user->email ?? '';
    
    // Get current role from session (dashboard switcher)
    $currentSelectedRole = session('selected_role');
    
    // Determine the role name based on session role or user's primary role
    if ($currentSelectedRole) {
        $roleName = ucfirst(str_replace('-', ' ', $currentSelectedRole));
    } else {
        $roleName = $user->getRoleName() ?? 'Community Member';
    }
    
    // Get all user roles for the dropdown (for multi-role users)
    $userRoles = $user->roles->pluck('slug')->toArray();
    $hasMultipleRoles = count($userRoles) > 1;
    
    // Role display names mapping
    $roleDisplayNames = [
        'super-admin' => 'Super Administrator',
        'admin' => 'Administrator',
        'developer' => 'Developer',
        'landlord' => 'Landlord / Property Owner',
        'field-agent' => 'Field Agent',
        'security-personnel' => 'Security Personnel',
        'tenant' => 'Tenant / Renter',
    ];
    
    // ============ FIXED: Determine layout based on user's primary role ============
    if (!isset($layout)) {
        // Check for role-based layouts in priority order
        if ($user->hasRole('developer')) {
            $layout = 'layouts.dev';
        } elseif ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            $layout = 'layouts.app';
        } elseif ($user->hasRole('field-agent')) {
            $layout = 'layouts.field';
        } elseif ($user->hasRole('landlord')) {
            $layout = 'layouts.landlord';
        } elseif ($user->hasRole('tenant')) {
            $layout = 'layouts.tenant';
        } elseif ($user->hasRole('security-personnel')) {
            $layout = 'layouts.secu';
        } else {
            // Fallback to default app layout
            $layout = 'layouts.app';
        }
    }
    
    // Also set a flag for the view to know if we're using admin layout
    $isUsingAdminLayout = in_array($layout, ['layouts.app', 'layouts.dev']);
@endphp

@section('title', 'My Testimonials - Dashboard')

@section('content')
<div class="grid grid-cols-1 gap-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2" style="color: var(--text-primary);">
                <i class="fas fa-comments mr-2" style="color: var(--primary);"></i> 
                My Testimonials
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Manage and track your submitted testimonials
            </p>
        </div>
        <button type="button" 
                onclick="openSubmitModal()"
                class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center gap-2">
            <i class="fas fa-plus"></i> 
            Share New Experience
        </button>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total Submitted</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-comments text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Approved</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($approvedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Pending Review</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($pendingCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-clock text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Average Rating</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ number_format($avgRating, 1) }} 
                            <span class="text-sm font-normal">/5</span>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-star-half-alt text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notificationContainer"></div>
    
    <!-- Testimonials List -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h3 class="text-lg font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                        My Testimonials
                    </h3>
                    @if(!$testimonials->isEmpty())
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Showing {{ $testimonials->firstItem() }} to {{ $testimonials->lastItem() }} of {{ $testimonials->total() }} entries
                    </p>
                    @endif
                </div>
                
                <div class="flex items-center space-x-3 mt-4 md:mt-0">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm" style="color: var(--text-secondary);">Show:</span>
                        <select onchange="updatePerPage(this.value)" class="index-custom-dropdown text-sm py-1 px-2 rounded">
                            <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>
                </div>
            </div>

            @if($testimonials->isEmpty())
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-comment-dots text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No Testimonials Yet</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">Share your experience with our community</p>
                    <button type="button" 
                            onclick="openSubmitModal()"
                            class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center gap-2">
                        <i class="fas fa-pen"></i> Write Your First Testimonial
                    </button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                    Date
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                    Rating
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 300px;">
                                    Content
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                    Status
                                </th>
                                <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="testimonialsTableBody">
                            @foreach($testimonials as $testimonial)
                            <tr data-testimonial-id="{{ $testimonial->id }}" 
                                data-status="{{ $testimonial->is_approved ? 'approved' : 'pending' }}">
                                
                                <td class="p-3 align-top">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $testimonial->created_at->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-clock mr-1 text-xs"></i> {{ $testimonial->created_at->diffForHumans() }}
                                    </div>
                                </td>
                                
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
                                
                                <td class="p-3 align-top">
                                    <p class="text-sm" style="color: var(--text-primary); line-height: 1.5;">
                                        {{ Str::limit($testimonial->content, 100) }}
                                    </p>
                                    @if(strlen($testimonial->content) > 100)
                                    <button type="button" 
                                            onclick="showFullContent('{{ addslashes($testimonial->content) }}', '{{ addslashes($testimonial->name) }}', {{ $testimonial->rating }})" 
                                            class="text-xs hover:underline mt-1" 
                                            style="color: var(--primary);">
                                        <i class="fas fa-expand mr-1"></i> Read more
                                    </button>
                                    @endif
                                    @if($testimonial->property_location)
                                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt mr-1 text-xs"></i> {{ $testimonial->property_location }}
                                    </div>
                                    @endif
                                </td>
                                
                                <td class="p-3 align-top">
                                    <div class="space-y-1">
                                        @if($testimonial->is_approved)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium badge-success">
                                                <i class="fas fa-check-circle mr-1 text-xs"></i> Approved
                                            </span>
                                            @if($testimonial->is_featured)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium badge-warning ml-1">
                                                <i class="fas fa-star mr-1 text-xs"></i> Featured
                                            </span>
                                            @endif
                                        @elseif($testimonial->trashed())
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium badge-danger">
                                                <i class="fas fa-trash-alt mr-1 text-xs"></i> Deleted
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium badge-warning">
                                                <i class="fas fa-clock mr-1 text-xs"></i> Pending Review
                                            </span>
                                        @endif
                                        @if($testimonial->approved_at)
                                        <div class="text-xs mt-1" style="color: var(--success);">
                                            <i class="fas fa-check-circle mr-0.5 text-xs"></i> Approved: {{ $testimonial->approved_at->format('M j, Y') }}
                                        </div>
                                        @endif
                                    </div>
                                </td>
                                
                                <td class="p-3 align-top">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <!-- View Details Button -->
                                        <button type="button" 
                                                onclick="viewTestimonial({{ $testimonial->id }}, '{{ addslashes($testimonial->name) }}', {{ $testimonial->rating }}, '{{ addslashes($testimonial->content) }}', '{{ addslashes($testimonial->role) }}', '{{ $testimonial->created_at->format('M d, Y') }}', '{{ addslashes($testimonial->property_location) }}')"
                                                class="action-btn view" 
                                                data-tooltip="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- Delete Button (only for pending, not approved, not trashed) -->
                                        @if(!$testimonial->is_approved && !$testimonial->trashed())
                                        <button type="button" 
                                                onclick="showDeleteModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                                class="action-btn trash" 
                                                data-tooltip="Delete Testimonial">
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
<!-- MODALS -->
<!-- ============================================ -->

<!-- Submit Testimonial Modal -->
<div id="submitTestimonialModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeSubmitModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-star mr-2" style="color: var(--warning);"></i> Share Your Experience
                </h3>
                <button type="button" onclick="closeSubmitModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="dashboardTestimonialForm">
                @csrf
                <div class="modal-body space-y-4">
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Your testimonial will be reviewed by our team before being published.
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 required" style="color: var(--text-primary);">
                            Your Name
                        </label>
                        <input type="text" name="name" class="index-custom-input w-full" value="{{ $userName }}" required readonly>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Using your registered name</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Email
                        </label>
                        <input type="email" name="email" class="index-custom-input w-full" value="{{ $userEmail }}" readonly>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">Your email will not be displayed publicly</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 required" style="color: var(--text-primary);">
                            Your Role
                        </label>
                        
                        @if($hasMultipleRoles)
                            <div class="relative">
                                <select name="role" id="userRoleSelect" class="index-custom-input w-full" required>
                                    <option value="{{ $roleName }}" selected>{{ $roleName }}</option>
                                    @foreach($userRoles as $roleSlug)
                                        @if($roleSlug !== ($currentSelectedRole ?? $user->getPrimaryRoleAttribute()?->slug))
                                            <option value="{{ $roleDisplayNames[$roleSlug] ?? ucfirst(str_replace('-', ' ', $roleSlug)) }}">
                                                {{ $roleDisplayNames[$roleSlug] ?? ucfirst(str_replace('-', ' ', $roleSlug)) }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="text-xs mt-1 flex items-center gap-2">
                                    <span style="color: var(--info);">
                                        <i class="fas fa-info-circle mr-1"></i> You have multiple roles
                                    </span>
                                    <span id="currentRoleBadge" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" 
                                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-exchange-alt mr-1"></i> Current: {{ $roleName }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <input type="text" name="role" id="userRoleInput" class="index-custom-input w-full" value="{{ $roleName }}" required readonly>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">Your role as registered in the system</p>
                        @endif
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 required" style="color: var(--text-primary);">
                            Rating
                        </label>
                        <div class="rating-input" id="dashboardRatingInput">
                            <i class="fas fa-star" data-rating="1"></i>
                            <i class="fas fa-star" data-rating="2"></i>
                            <i class="fas fa-star" data-rating="3"></i>
                            <i class="fas fa-star" data-rating="4"></i>
                            <i class="fas fa-star" data-rating="5"></i>
                            <input type="hidden" name="rating" id="dashboard_testimonial_rating" value="5">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 required" style="color: var(--text-primary);">
                            Your Feedback
                        </label>
                        <textarea name="content" class="index-custom-textarea w-full" rows="5" required 
                                  minlength="20" maxlength="2000" placeholder="Tell us about your experience..."></textarea>
                        <div class="character-counter mt-1 flex justify-between items-center">
                            <span class="char-count text-xs">0</span>
                            <span class="text-xs">/ 2000 characters</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Property Location <span class="text-xs font-normal" style="color: var(--text-secondary);">(Optional)</span>
                        </label>
                        <input type="text" name="property_location" class="index-custom-input w-full" 
                               placeholder="e.g., East Legon, Accra">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeSubmitModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Testimonial
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Testimonial Modal -->
<div id="viewTestimonialModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeViewModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-lg">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-quote-left mr-2" style="color: var(--primary);"></i> Testimonial Details
                </h3>
                <button type="button" onclick="closeViewModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="rating-display mb-2 flex justify-center"></div>
                    <h4 class="text-lg font-semibold testimonial-name" style="color: var(--text-primary);"></h4>
                    <p class="text-sm testimonial-role mt-1" style="color: var(--text-secondary);"></p>
                    <p class="text-xs testimonial-date mt-1" style="color: var(--text-secondary);"></p>
                    <p class="text-xs testimonial-location mt-1" style="color: var(--text-secondary);"></p>
                </div>
                <div class="rounded-lg p-4 testimonial-content-display" style="background-color: var(--bg-secondary); color: var(--text-primary); line-height: 1.6;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeViewModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Delete Testimonial
                </h3>
                <button type="button" onclick="closeDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Are you sure you want to delete testimonial from: <strong id="deleteTestimonialName" class="font-semibold"></strong>?
                        </p>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Warning:</p>
                                <p>This action cannot be undone. Only pending testimonials can be deleted.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Testimonial
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Full Content Modal -->
<div id="fullContentModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeFullContentModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-2xl">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-quote-left mr-2" style="color: var(--primary);"></i> Full Testimonial
                </h3>
                <button type="button" onclick="closeFullContentModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-secondary);">From: <span id="fullContentName" class="font-semibold" style="color: var(--text-primary);"></span></p>
                    </div>
                    <div id="fullContentRating" class="flex items-center"></div>
                </div>
                <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                    <p id="fullContentText" class="text-sm leading-relaxed" style="color: var(--text-primary);"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeFullContentModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .index-custom-input,
    .index-custom-dropdown,
    .index-custom-textarea {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 0.5rem;
        padding: 0.625rem 0.75rem;
        width: 100%;
        transition: all 0.2s ease;
    }
    
    .index-custom-input:focus,
    .index-custom-dropdown:focus,
    .index-custom-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    
    .index-custom-input:read-only,
    .index-custom-input[readonly] {
        background-color: var(--bg-secondary);
        cursor: not-allowed;
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
    
    .action-btn.view {
        background-color: rgba(var(--info-rgb), 0.1);
        color: var(--info);
        border-color: rgba(var(--info-rgb), 0.3);
    }
    
    .action-btn.trash {
        background-color: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
        border-color: rgba(var(--danger-rgb), 0.3);
    }
    
    .action-btn.view:hover,
    .action-btn.trash:hover {
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
        border-radius: 16px 16px 0 0;
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
        border-radius: 0 0 16px 16px;
    }
    
    .modal-close-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.2s ease;
    }
    
    .modal-close-btn:hover {
        background-color: rgba(var(--danger-rgb), 0.1);
    }
    
    .modal-close-btn:hover i {
        color: var(--danger);
    }
    
    .rating-input i {
        font-size: 1.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-right: 0.25rem;
        color: var(--text-secondary);
    }
    
    .rating-input i:hover,
    .rating-input i.active {
        color: var(--warning);
        transform: scale(1.1);
    }
    
    .rating-display i {
        font-size: 1.25rem;
        margin-right: 0.25rem;
    }
    
    .required:after {
        content: '*';
        color: var(--danger);
        margin-left: 0.25rem;
    }
    
    .character-counter {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .character-counter.danger {
        color: var(--danger);
    }
    
    .character-counter.warning {
        color: var(--warning);
    }
    
    .badge-success {
        background-color: rgba(var(--success-rgb), 0.1) !important;
        color: var(--success) !important;
        border: 1px solid rgba(var(--success-rgb), 0.3) !important;
    }
    
    .badge-warning {
        background-color: rgba(var(--warning-rgb), 0.1) !important;
        color: var(--warning) !important;
        border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
    }
    
    .badge-danger {
        background-color: rgba(var(--danger-rgb), 0.1) !important;
        color: var(--danger) !important;
        border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
    }
    
    .badge-info {
        background-color: rgba(var(--info-rgb), 0.1) !important;
        color: var(--info) !important;
        border: 1px solid rgba(var(--info-rgb), 0.3) !important;
    }
    
    .btn-primary {
        background-color: var(--primary) !important;
        color: white !important;
        border: 1px solid var(--primary) !important;
    }
    
    .btn-primary:hover {
        background-color: var(--secondary) !important;
        border-color: var(--secondary) !important;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background-color: rgba(var(--secondary-rgb), 0.1) !important;
        color: var(--secondary) !important;
        border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    }
    
    .btn-secondary:hover {
        background-color: rgba(var(--secondary-rgb), 0.2) !important;
        transform: translateY(-1px);
    }
    
    .btn-danger {
        background-color: var(--danger) !important;
        color: white !important;
        border: 1px solid var(--danger) !important;
    }
    
    .btn-danger:hover {
        background-color: #c82333 !important;
        transform: translateY(-1px);
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
    
    table tr:hover td {
        background-color: rgba(var(--primary-rgb), 0.02) !important;
    }
    
    .text-warning {
        color: var(--warning) !important;
    }
    
    .text-muted {
        color: var(--text-secondary) !important;
    }
    
    .pagination {
        display: flex;
        gap: 0.5rem;
    }
    
    .pagination nav {
        width: 100%;
    }
    
    .pagination .page-item .page-link {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        transition: all 0.2s ease;
    }
    
    .pagination .page-item.active .page-link {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .pagination .page-item .page-link:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
    }
    
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
    
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    
    .toast-notification {
        animation: slideIn 0.3s ease;
    }
    
    .toast-notification.hiding {
        animation: fadeOut 0.3s ease;
    }
    
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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

@push('scripts')
<script>
// Route variables for dynamic URL generation
const deleteRouteBase = '{{ url("dashboard/testimonials") }}/';
const storeRoute = '{{ route("dashboard.testimonials.store") }}';

let currentDeleteId = null;
let currentDeleteName = null;

// Store current role for dynamic updates
let currentUserRole = '{{ $roleName }}';
let currentSelectedRole = '{{ $currentSelectedRole ?? '' }}';
let userRolesList = @json($userRoles);
let roleDisplayNames = @json($roleDisplayNames);
let hasMultipleRoles = {{ $hasMultipleRoles ? 'true' : 'false' }};

document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    initRatingStars();
    initCharacterCounter();
    initSubmitForm();
    initDeleteForm();
    initRoleObserver();
});

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// ============ ROLE OBSERVER - Detects dashboard switches without AJAX ============
function initRoleObserver() {
    // Listen for storage events (localStorage/sessionStorage changes)
    window.addEventListener('storage', function(e) {
        if (e.key === 'selected_role' || e.key === 'dashboard_role') {
            console.log('Role change detected via storage:', e.oldValue, '->', e.newValue);
            if (e.newValue) {
                updateRoleDisplay(e.newValue);
            }
        }
    });
    
    // Listen for custom event from dashboard switcher
    document.addEventListener('dashboard-switched', function(e) {
        console.log('Dashboard switched event received:', e.detail);
        if (e.detail && e.detail.role) {
            updateRoleDisplay(e.detail.role);
        }
    });
    
    // Also check for message from parent window (for iframe scenarios)
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'role-switch' && e.data.role) {
            updateRoleDisplay(e.data.role);
        }
    });
    
    // Get role from meta tag if available
    const roleMeta = document.querySelector('meta[name="current-role"]');
    if (roleMeta && roleMeta.getAttribute('content')) {
        const metaRole = roleMeta.getAttribute('content');
        if (metaRole !== currentSelectedRole) {
            updateRoleDisplay(metaRole);
        }
    }
    
    // Try to get role from sessionStorage directly
    try {
        const storedRole = sessionStorage.getItem('selected_role') || localStorage.getItem('selected_role');
        if (storedRole && storedRole !== currentSelectedRole) {
            updateRoleDisplay(storedRole);
        }
    } catch(e) {
        // Silent fail
    }
}

function updateRoleDisplay(roleSlug) {
    // Remove 'selected_role=' prefix if present (from cookie string)
    if (roleSlug && roleSlug.startsWith('selected_role=')) {
        roleSlug = roleSlug.replace('selected_role=', '').replace(/[;&].*$/, '');
    }
    
    // Map role slug to display name
    const roleDisplayName = roleDisplayNames[roleSlug] || ucfirst(roleSlug.replace('-', ' '));
    
    // Update the role field in modal
    const roleSelect = document.getElementById('userRoleSelect');
    const roleInput = document.getElementById('userRoleInput');
    const roleBadge = document.getElementById('currentRoleBadge');
    
    if (roleSelect) {
        // Check if the option exists
        let optionExists = false;
        for (let i = 0; i < roleSelect.options.length; i++) {
            if (roleSelect.options[i].value === roleDisplayName) {
                roleSelect.options[i].selected = true;
                optionExists = true;
                break;
            }
        }
        
        // If option doesn't exist and user has this role, add it
        if (!optionExists && userRolesList.includes(roleSlug)) {
            const newOption = document.createElement('option');
            newOption.value = roleDisplayName;
            newOption.textContent = roleDisplayName;
            newOption.selected = true;
            roleSelect.appendChild(newOption);
        }
    }
    
    if (roleInput) {
        roleInput.value = roleDisplayName;
    }
    
    if (roleBadge) {
        roleBadge.innerHTML = `<i class="fas fa-exchange-alt mr-1"></i> Current: ${roleDisplayName}`;
    }
    
    // Update any hidden role fields
    const hiddenRoleField = document.querySelector('input[name="role"]');
    if (hiddenRoleField) {
        hiddenRoleField.value = roleDisplayName;
    }
    
    currentUserRole = roleDisplayName;
    currentSelectedRole = roleSlug;
    
    // Show subtle notification
    showNotification('info', `Your role has been updated to: ${roleDisplayName}`);
}

function ucfirst(str) {
    if (!str) return str;
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function initRatingStars() {
    const ratingStars = document.querySelectorAll('#dashboardRatingInput i');
    const ratingInput = document.getElementById('dashboard_testimonial_rating');
    
    if (!ratingStars.length || !ratingInput) return;
    
    ratingStars.forEach((star, index) => {
        if (index < 5) {
            star.classList.add('active');
        }
    });
    
    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingInput.value = rating;
            ratingStars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
    });
}

function initCharacterCounter() {
    const textarea = document.querySelector('textarea[name="content"]');
    const charCounter = document.querySelector('.character-counter');
    const charCountSpan = document.querySelector('.char-count');
    
    if (!textarea || !charCounter) return;
    
    const MAX_LENGTH = 2000;
    
    function updateCounter() {
        const length = textarea.value.length;
        if (charCountSpan) charCountSpan.textContent = length;
        
        charCounter.classList.remove('warning', 'danger');
        if (length > MAX_LENGTH * 0.9) {
            charCounter.classList.add('danger');
        } else if (length > MAX_LENGTH * 0.7) {
            charCounter.classList.add('warning');
        }
    }
    
    textarea.addEventListener('input', updateCounter);
    updateCounter();
}

function initSubmitForm() {
    const form = document.getElementById('dashboardTestimonialForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
        
        // Get the current role value (from select or input)
        const roleSelect = document.getElementById('userRoleSelect');
        const roleInput = document.getElementById('userRoleInput');
        let roleValue = currentUserRole;
        
        if (roleSelect && roleSelect.value) {
            roleValue = roleSelect.value;
        } else if (roleInput && roleInput.value) {
            roleValue = roleInput.value;
        }
        
        // Create form data with current role
        const formData = new FormData(form);
        formData.set('role', roleValue);
        
        try {
            const response = await fetch(storeRoute, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                closeSubmitModal();
                showNotification('success', data.message || 'Testimonial submitted successfully!');
                
                form.reset();
                const ratingInput = document.getElementById('dashboard_testimonial_rating');
                if (ratingInput) ratingInput.value = 5;
                const ratingStars = document.querySelectorAll('#dashboardRatingInput i');
                ratingStars.forEach((star, index) => {
                    if (index < 5) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                if (data.errors) {
                    for (let key in data.errors) {
                        showNotification('error', data.errors[key][0]);
                    }
                } else {
                    showNotification('error', data.message || 'Failed to submit testimonial');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('error', 'Network error. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

function initDeleteForm() {
    const form = document.getElementById('deleteForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!currentDeleteId) {
            showNotification('error', 'No testimonial selected.');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
        
        try {
            const deleteUrl = deleteRouteBase + currentDeleteId;
            
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('success', data.message || 'Testimonial deleted successfully');
                
                const row = document.querySelector(`tr[data-testimonial-id="${currentDeleteId}"]`);
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        const remainingRows = document.querySelectorAll('#testimonialsTableBody tr').length;
                        if (remainingRows === 0) {
                            setTimeout(() => location.reload(), 500);
                        }
                    }, 300);
                }
                
                closeDeleteModal();
                currentDeleteId = null;
                currentDeleteName = null;
            } else {
                showNotification('error', data.message || 'Failed to delete testimonial');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('error', 'Network error. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
}

// Modal Functions
function openSubmitModal() {
    const modal = document.getElementById('submitTestimonialModal');
    if (modal) {
        // Try to get latest role from storage before showing modal
        try {
            const storedRole = sessionStorage.getItem('selected_role') || localStorage.getItem('selected_role');
            if (storedRole && storedRole !== currentSelectedRole) {
                updateRoleDisplay(storedRole);
            }
        } catch(e) {}
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeSubmitModal() {
    const modal = document.getElementById('submitTestimonialModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function viewTestimonial(id, name, rating, content, role, date, location) {
    const modal = document.getElementById('viewTestimonialModal');
    if (!modal) return;
    
    const ratingDisplay = modal.querySelector('.rating-display');
    if (ratingDisplay) {
        ratingDisplay.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                ratingDisplay.innerHTML += '<i class="fas fa-star" style="color: var(--warning);"></i>';
            } else {
                ratingDisplay.innerHTML += '<i class="far fa-star" style="color: var(--text-secondary);"></i>';
            }
        }
    }
    
    modal.querySelector('.testimonial-name').textContent = name || 'Anonymous';
    modal.querySelector('.testimonial-role').textContent = role || 'Community Member';
    modal.querySelector('.testimonial-date').textContent = `Submitted on ${date || ''}`;
    modal.querySelector('.testimonial-content-display').textContent = content || '';
    
    const locationEl = modal.querySelector('.testimonial-location');
    if (locationEl) {
        if (location) {
            locationEl.innerHTML = `<i class="fas fa-map-marker-alt mr-1"></i> ${location}`;
            locationEl.style.display = 'block';
        } else {
            locationEl.style.display = 'none';
        }
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    const modal = document.getElementById('viewTestimonialModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showDeleteModal(testimonialId, name) {
    currentDeleteId = testimonialId;
    currentDeleteName = name;
    
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const nameSpan = document.getElementById('deleteTestimonialName');
    
    if (form) {
        form.action = deleteRouteBase + testimonialId;
    }
    if (nameSpan) {
        nameSpan.textContent = name || 'Unknown User';
    }
    
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentDeleteId = null;
        currentDeleteName = null;
    }
}

function showFullContent(content, name, rating) {
    const modal = document.getElementById('fullContentModal');
    if (!modal) return;
    
    modal.querySelector('#fullContentName').textContent = name || 'Anonymous';
    modal.querySelector('#fullContentText').textContent = content || '';
    
    const ratingContainer = modal.querySelector('#fullContentRating');
    if (ratingContainer) {
        ratingContainer.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                ratingContainer.innerHTML += '<i class="fas fa-star text-xs" style="color: var(--warning);"></i>';
            } else {
                ratingContainer.innerHTML += '<i class="far fa-star text-xs" style="color: var(--text-secondary);"></i>';
            }
        }
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeFullContentModal() {
    const modal = document.getElementById('fullContentModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Notification System
function showNotification(type, message) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `toast-notification mb-4 p-4 rounded-lg shadow-lg`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : (type === 'info' ? 'rgba(var(--info-rgb), 0.1)' : 'rgba(var(--danger-rgb), 0.1)');
    notification.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : (type === 'info' ? '1px solid rgba(var(--info-rgb), 0.3)' : '1px solid rgba(var(--danger-rgb), 0.3)');
    
    const iconColor = type === 'success' ? 'var(--success)' : (type === 'info' ? 'var(--info)' : 'var(--danger)');
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle');
    
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${icon} mr-2" style="color: ${iconColor};"></i>
                <span style="color: ${iconColor};">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.closest('.toast-notification').remove()" style="background: none; border: none; cursor: pointer; color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.add('hiding');
            setTimeout(() => {
                if (notification.parentElement) notification.remove();
            }, 300);
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        let tooltip = null;
        
        element.addEventListener('mouseenter', function(e) {
            tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: fixed;
                background: var(--text-primary);
                color: var(--card-bg);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                white-space: nowrap;
                pointer-events: none;
            `;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        });
    });
}
</script>
@endpush

@endsection