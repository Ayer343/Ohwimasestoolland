{{-- admin/testimonials/index.blade.php --}}
@php
    use App\Models\Testimonial;
    
    // Dynamic role detection
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isDeveloper = $user->isDeveloper();
    
    // Determine layout and route prefix based on user role
    if ($isDeveloper) {
        $layout = 'layouts.dev';
        $routePrefix = 'developer.testimonials';
        $pageTitle = 'Testimonials Management - Developer Portal';
        $breadcrumbTitle = 'Developer Testimonials';
        $dashboardRoute = 'developer.dashboard';
    } elseif ($isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.testimonials';
        $pageTitle = 'Testimonials Management - Super Admin Portal';
        $breadcrumbTitle = 'Super Admin Testimonials';
        $dashboardRoute = 'super-admin.dashboard';
    } elseif ($isAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.testimonials';
        $pageTitle = 'Testimonials Management - Admin Portal';
        $breadcrumbTitle = 'Admin Testimonials';
        $dashboardRoute = 'admin.dashboard';
    } else {
        // Fallback for any other authenticated user (should not reach here due to middleware)
        $layout = 'layouts.app';
        $routePrefix = 'admin.testimonials';
        $pageTitle = 'Testimonials Management';
        $breadcrumbTitle = 'Testimonials';
        $dashboardRoute = 'dashboard';
    }
    
    // Success/Error messages
    $successMessage = session('success');
    $errorMessage = session('error');
    
    // Get filter values
    $currentStatus = request('status');
    $currentFeatured = request('featured');
    $currentRating = request('rating');
    $currentSearch = request('search');
    
    // Get stats from controller
    $totalTestimonials = $stats['total'] ?? Testimonial::count();
    $approvedCount = $stats['approved'] ?? Testimonial::where('is_approved', true)->count();
    $pendingCount = $stats['pending'] ?? Testimonial::where('is_approved', false)->count();
    $featuredCount = $stats['featured'] ?? Testimonial::where('is_featured', true)->count();
    $averageRating = $stats['average_rating'] ?? round(Testimonial::where('is_approved', true)->avg('rating') ?? 0, 1);
    $trashedCount = $stats['trashed'] ?? Testimonial::onlyTrashed()->count();
    
    // Rating distribution
    $fiveStarCount = Testimonial::where('rating', 5)->count();
    $fourStarCount = Testimonial::where('rating', 4)->count();
    $threeStarCount = Testimonial::where('rating', 3)->count();
    $twoStarCount = Testimonial::where('rating', 2)->count();
    $oneStarCount = Testimonial::where('rating', 1)->count();
    
    // Determine if user can perform certain actions
    $canApprove = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canFeature = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canDelete = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canExport = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canBulkAction = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canViewTrash = $isAdmin || $isSuperAdmin || $isDeveloper;
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card with Role Badge -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-comments text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-star mr-2" style="color: var(--primary);"></i> 
                        Testimonials Management
                        @if($isDeveloper)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-code mr-1"></i> Developer Access
                        </span>
                        @elseif($isSuperAdmin)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <i class="fas fa-crown mr-1"></i> Super Admin Access
                        </span>
                        @elseif($isAdmin)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            <i class="fas fa-shield-alt mr-1"></i> Admin Access
                        </span>
                        @endif
                        @if($pendingCount > 0 && $canApprove)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-clock mr-1"></i> {{ $pendingCount }} Pending
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage and moderate customer testimonials</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ number_format($totalTestimonials) }} testimonial{{ $totalTestimonials != 1 ? 's' : '' }} total</span>
                        @if($trashedCount > 0 && $canViewTrash)
                        <span class="mx-1">•</span>
                        <i class="fas fa-trash-alt mr-1" style="color: var(--danger);"></i>
                        <span>{{ $trashedCount }} in trash</span>
                        @endif
                        @if($averageRating > 0)
                        <span class="mx-1">•</span>
                        <i class="fas fa-star mr-1" style="color: var(--warning);"></i>
                        <span>Average Rating: {{ $averageRating }}/5.0</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                
                @if($trashedCount > 0 && $canViewTrash)
                <a href="{{ route($routePrefix . '.trashed') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                    <i class="fas fa-trash-restore mr-1"></i> Trash ({{ $trashedCount }})
                </a>
                @endif
                
                @if(isset($dashboardRoute) && Route::has($dashboardRoute))
                <a href="{{ route($dashboardRoute) }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-dashboard mr-1"></i> 
                    @if($isDeveloper) Dev Dashboard
                    @elseif($isSuperAdmin) Super Admin Dashboard
                    @else Admin Dashboard
                    @endif
                </a>
                @endif
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

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total Testimonials</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($totalTestimonials) }}</p>
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
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Featured</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($featuredCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-star text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Average Rating</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $averageRating }} <span class="text-sm font-normal">/5</span></p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-star-half-alt text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">In Trash</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($trashedCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1);">
                        <i class="fas fa-trash-alt text-lg" style="color: var(--danger);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Table Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route($routePrefix . '.index') }}" class="space-y-4" id="filterForm">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       name="search" 
                                       value="{{ request('search') }}" 
                                       class="index-custom-input w-full pl-10 pr-3 py-2"
                                       placeholder="Name, email, content..."
                                       style="padding-left: 2.5rem;">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                </div>
                            </div>
                        </div>

                        @if($canApprove)
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="index-custom-dropdown w-full">
                                <option value="">All Status</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                        </div>
                        @endif

                        @if($canFeature)
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-star mr-1"></i> Featured Status
                            </label>
                            <select name="featured" class="index-custom-dropdown w-full">
                                <option value="">All</option>
                                <option value="true" {{ request('featured') == 'true' ? 'selected' : '' }}>Featured Only</option>
                                <option value="false" {{ request('featured') == 'false' ? 'selected' : '' }}>Non-Featured Only</option>
                            </select>
                        </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-chart-line mr-1"></i> Minimum Rating
                            </label>
                            <select name="rating" class="index-custom-dropdown w-full">
                                <option value="">All Ratings</option>
                                <option value="5" {{ request('rating') == '5' ? 'selected' : '' }}>5 Stars Only</option>
                                <option value="4" {{ request('rating') == '4' ? 'selected' : '' }}>4 Stars & Above</option>
                                <option value="3" {{ request('rating') == '3' ? 'selected' : '' }}>3 Stars & Above</option>
                                <option value="2" {{ request('rating') == '2' ? 'selected' : '' }}>2 Stars & Above</option>
                                <option value="1" {{ request('rating') == '1' ? 'selected' : '' }}>1 Star & Above</option>
                            </select>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <button type="submit" class="block w-full text-center btn-primary px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route($routePrefix . '.index') }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                @if($canViewTrash && $trashedCount > 0)
                                <a href="{{ route($routePrefix . '.trashed') }}" class="block w-full text-center px-3 py-2 rounded-lg font-medium" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                    <i class="fas fa-trash-restore mr-2"></i> View Trash @if($trashedCount > 0)<span class="ml-1 px-2 py-0.5 bg-gray-500 bg-opacity-20 rounded-full text-xs">{{ $trashedCount }}</span>@endif
                                </a>
                                @endif
                                
                                @if($canExport)
                                <a href="{{ route($routePrefix . '.export', ['csv']) . '?' . http_build_query(request()->except(['page', '_token'])) }}" class="block w-full text-center btn-modern bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 px-3 py-2 rounded-lg font-medium text-white">
                                    <i class="fas fa-file-export mr-2"></i> Export Data
                                </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Rating Distribution Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Rating Distribution
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--success);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">5 Stars</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $fiveStarCount }}</span>
                                <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $totalTestimonials > 0 ? round(($fiveStarCount / $totalTestimonials) * 100, 1) : 0 }}%)</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--info);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">4 Stars</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $fourStarCount }}</span>
                                <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $totalTestimonials > 0 ? round(($fourStarCount / $totalTestimonials) * 100, 1) : 0 }}%)</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--warning);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">3 Stars</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $threeStarCount }}</span>
                                <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $totalTestimonials > 0 ? round(($threeStarCount / $totalTestimonials) * 100, 1) : 0 }}%)</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--secondary);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">2 Stars</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $twoStarCount }}</span>
                                <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $totalTestimonials > 0 ? round(($twoStarCount / $totalTestimonials) * 100, 1) : 0 }}%)</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: var(--danger);"></span>
                                <span class="text-sm" style="color: var(--text-primary);">1 Star</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $oneStarCount }}</span>
                                <span class="text-xs ml-1" style="color: var(--text-secondary);">({{ $totalTestimonials > 0 ? round(($oneStarCount / $totalTestimonials) * 100, 1) : 0 }}%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testimonials Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            {{ $breadcrumbTitle }}
                            @if($currentStatus)
                            <span class="text-sm font-normal ml-2 px-2 py-1 rounded-full badge-primary">
                                {{ ucfirst($currentStatus) }}
                            </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $testimonials->firstItem() }} to {{ $testimonials->lastItem() }} of {{ $testimonials->total() }} entries
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        @if($canBulkAction)
                        @php
                            $hasSelectableTestimonials = $testimonials->filter(function($t) {
                                return !$t->trashed();
                            })->count() > 0;
                        @endphp
                        
                        @if($hasSelectableTestimonials)
                        <div class="relative">
                            <button type="button" id="bulkActionsBtn" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                                <i class="fas fa-check-double mr-2"></i> Bulk Actions
                                <i class="fas fa-chevron-down ml-2 text-xs"></i>
                            </button>
                            
                            <div id="bulkActionsDropdown" class="absolute right-0 mt-2 w-64 rounded-lg shadow-lg z-10 hidden" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                <div class="py-1">
                                    @if($canApprove)
                                    <button type="button" onclick="showBulkApproveModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i> Bulk Approve
                                    </button>
                                    @endif
                                    @if($canApprove)
                                    <button type="button" onclick="showBulkRejectModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i> Bulk Reject
                                    </button>
                                    @endif
                                    @if($canFeature)
                                    <button type="button" onclick="showBulkFeatureModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-star text-yellow-500 mr-2"></i> Bulk Feature
                                    </button>
                                    <button type="button" onclick="showBulkUnfeatureModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-star-half-alt text-yellow-500 mr-2"></i> Bulk Unfeature
                                    </button>
                                    @endif
                                    @if($canDelete)
                                    <hr class="my-1" style="border-color: var(--border-color);">
                                    <button type="button" onclick="showBulkSoftDeleteModal()" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-trash-alt text-red-500 mr-2"></i> Bulk Move to Trash
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
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
                        
                        @if($canExport)
                        <a href="{{ route($routePrefix . '.export', ['csv']) . '?' . http_build_query(request()->except(['page', '_token'])) }}" class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-download mr-2"></i> Export
                        </a>
                        @endif
                    </div>
                </div>

                @if($testimonials->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-comments text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No testimonials found</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">Try adjusting your filters or search criteria.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1200px]" id="testimonialsTable">
                            <thead>
                                <tr>
                                    @if($canBulkAction)
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded" style="width: 18px; height: 18px;">
                                    </th>
                                    @endif
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 250px;">
                                        User Information
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 350px;">
                                        Testimonial Content
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 100px;">
                                        Rating
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 120px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 110px;">
                                        Date
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); min-width: 160px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="testimonialsTableBody">
                                @foreach($testimonials as $testimonial)
                                @php
                                    $canBeBulkSelected = !$testimonial->trashed();
                                    $avatarUrl = $testimonial->avatar_url ?? ($testimonial->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($testimonial->name) . '&background=3b82f6&color=fff');
                                @endphp
                                <tr data-testimonial-id="{{ $testimonial->id }}" 
                                    data-status="{{ $testimonial->is_approved ? 'approved' : 'pending' }}"
                                    data-featured="{{ $testimonial->is_featured ? 'true' : 'false' }}"
                                    data-rating="{{ $testimonial->rating }}">
                                    
                                    @if($canBulkAction)
                                    <td class="p-3 text-center align-top">
                                        <input type="checkbox" class="testimonial-checkbox" value="{{ $testimonial->id }}" 
                                               data-can-bulk="{{ $canBeBulkSelected ? 'true' : 'false' }}"
                                               {{ !$canBeBulkSelected ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' }}
                                               onclick="updateBulkActions()">
                                    </td>
                                    @endif
                                    
                                    <!-- COLUMN 1: User Information -->
                                    <td class="p-3 align-top">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mr-3">
                                                <img src="{{ $avatarUrl }}" alt="{{ $testimonial->name }}" class="w-10 h-10 rounded-full object-cover">
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-semibold text-sm flex items-center flex-wrap gap-1" style="color: var(--text-primary);">
                                                    {{ $testimonial->name }}
                                                    @if($testimonial->user)
                                                    <span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-0.5 text-xs"></i> Registered
                                                    </span>
                                                    @endif
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
                                                @if($testimonial->property_location)
                                                <div class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                                    <i class="fas fa-map-marker-alt mr-1 text-xs"></i> {{ $testimonial->property_location }}
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMN 2: Testimonial Content -->
                                    <td class="p-3 align-top">
                                        <div class="testimonial-content">
                                            <p class="text-sm mb-2" style="color: var(--text-primary); line-height: 1.5;">
                                                {{ Str::limit($testimonial->content, 200) }}
                                            </p>
                                            @if($testimonial->content && strlen($testimonial->content) > 200)
                                            <button type="button" onclick="showFullContent('{{ addslashes($testimonial->content) }}', '{{ addslashes($testimonial->name) }}')" 
                                                    class="text-xs hover:underline" style="color: var(--primary);">
                                                <i class="fas fa-expand mr-1"></i> Read more
                                            </button>
                                            @endif
                                            @if($testimonial->metadata && isset($testimonial->metadata['submission_method']))
                                            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                                <i class="fas fa-globe mr-1 text-xs"></i> Submitted via: {{ $testimonial->metadata['submission_method'] }}
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
                                    
                                    <!-- COLUMN 4: Status -->
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
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium badge-warning">
                                                    <i class="fas fa-clock mr-1 text-xs"></i> Pending
                                                </span>
                                            @endif
                                            @if($testimonial->approved_at)
                                            <div class="text-xs mt-1" style="color: var(--success);">
                                                <i class="fas fa-check-circle mr-0.5 text-xs"></i> Approved: {{ $testimonial->approved_at->format('M j, Y') }}
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMN 5: Date -->
                                    <td class="p-3 align-top">
                                        <div class="text-sm" style="color: var(--text-primary);">
                                            {{ $testimonial->created_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-clock mr-1 text-xs"></i> {{ $testimonial->created_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMN 6: Actions -->
                                    <td class="p-3 align-top">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <!-- View Details Button -->
                                            <a href="{{ route($routePrefix . '.show', $testimonial->id) }}" class="action-btn view" data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <!-- Approve Button (only if not approved and user has permission) -->
                                            @if(!$testimonial->is_approved && $canApprove)
                                                <button type="button" 
                                                        onclick="showApproveModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                                        class="action-btn assign" 
                                                        data-tooltip="Approve Testimonial">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            @endif
                                            
                                            <!-- Toggle Featured Button (only if approved and user has permission) -->
                                            @if($testimonial->is_approved && $canFeature)
                                                <button type="button" 
                                                        onclick="toggleFeatured('{{ $testimonial->id }}', {{ $testimonial->is_featured ? 'true' : 'false' }})"
                                                        class="action-btn" 
                                                        data-tooltip="{{ $testimonial->is_featured ? 'Remove Featured' : 'Make Featured' }}"
                                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            @endif
                                            
                                            <!-- Reject/Soft Delete Button -->
                                            @if(!$testimonial->trashed() && $canDelete)
                                                <button type="button" 
                                                        onclick="showSoftDeleteModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                                        class="action-btn trash" 
                                                        data-tooltip="Move to Trash">
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
</div>

<!-- ============================================ -->
<!-- MODAL TEMPLATES (with dynamic route URLs) -->
<!-- ============================================ -->

<!-- Single Approve Modal -->
<div id="approveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Approve Testimonial
                </h3>
                <button type="button" onclick="hideApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="approveForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Approve testimonial from: <strong id="approveTestimonialName" class="font-semibold"></strong>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Admin Notes (Optional)
                        </label>
                        <textarea name="admin_notes" rows="3" class="index-custom-textarea w-full" placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="feature" value="1" class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                            <span class="text-sm" style="color: var(--text-primary);">Feature this testimonial</span>
                        </label>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This testimonial will be published on the website. The user will be notified via email if provided.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideApproveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Testimonial
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Approve Modal -->
<div id="bulkApproveModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkApproveModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i> Bulk Approve Testimonials
                </h3>
                <button type="button" onclick="hideBulkApproveModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkApproveForm" method="POST" action="{{ route($routePrefix . '.bulk-approve') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected testimonials:</p>
                        <div id="bulkSelectedList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="feature" value="1" class="mr-2 w-4 h-4" style="accent-color: var(--primary);">
                            <span class="text-sm" style="color: var(--text-primary);">Feature all selected testimonials</span>
                        </label>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--success);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This will approve all selected testimonials.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkApproveIdsContainer"></div>
                    <button type="button" onclick="hideBulkApproveModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-check mr-2"></i> Approve Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div id="bulkRejectModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkRejectModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-times-circle mr-2" style="color: var(--danger);"></i> Bulk Reject Testimonials
                </h3>
                <button type="button" onclick="hideBulkRejectModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkRejectForm" method="POST" action="{{ route($routePrefix . '.bulk-reject') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected testimonials:</p>
                        <div id="bulkRejectSelectedList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This will reject all selected testimonials and move them to trash.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkRejectIdsContainer"></div>
                    <button type="button" onclick="hideBulkRejectModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-times mr-2"></i> Reject Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Feature Modal -->
<div id="bulkFeatureModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkFeatureModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-star mr-2" style="color: var(--warning);"></i> Bulk Feature Testimonials
                </h3>
                <button type="button" onclick="hideBulkFeatureModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkFeatureForm" method="POST" action="{{ route($routePrefix . '.bulk-feature') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected testimonials:</p>
                        <div id="bulkFeatureSelectedList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--warning);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Featured testimonials will be highlighted on the homepage.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkFeatureIdsContainer"></div>
                    <button type="button" onclick="hideBulkFeatureModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-star mr-2"></i> Feature Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Unfeature Modal -->
<div id="bulkUnfeatureModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkUnfeatureModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-star-half-alt mr-2" style="color: var(--secondary);"></i> Bulk Unfeature Testimonials
                </h3>
                <button type="button" onclick="hideBulkUnfeatureModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkUnfeatureForm" method="POST" action="{{ route($routePrefix . '.bulk-unfeature') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected testimonials:</p>
                        <div id="bulkUnfeatureSelectedList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--secondary-rgb), 0.1); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--secondary);"></i>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                This will remove featured status from selected testimonials.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkUnfeatureIdsContainer"></div>
                    <button type="button" onclick="hideBulkUnfeatureModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-star-half-alt mr-2"></i> Unfeature Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Soft Delete Modal -->
<div id="bulkSoftDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideBulkSoftDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Bulk Move to Trash
                </h3>
                <button type="button" onclick="hideBulkSoftDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="bulkSoftDeleteForm" method="POST" action="{{ route($routePrefix . '.bulk-soft-delete') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">Selected testimonials:</p>
                        <div id="bulkSoftDeleteSelectedList" class="max-h-40 overflow-y-auto space-y-1 p-2 rounded" style="background-color: var(--bg-secondary);"></div>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p class="font-medium mb-1">Warning:</p>
                                <p>Selected testimonials will be moved to trash. You can restore them later.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="bulkSoftDeleteIdsContainer"></div>
                    <button type="button" onclick="hideBulkSoftDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Soft Delete Modal -->
<div id="softDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideSoftDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Move to Trash
                </h3>
                <button type="button" onclick="hideSoftDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="softDeleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Are you sure you want to move testimonial from <strong id="softDeleteName" class="font-semibold"></strong> to trash?
                        </p>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p>The testimonial will be moved to trash. You can restore it later from the trash section.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideSoftDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                    </button>
                </div>
            </form>
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
const canApprove = {{ $canApprove ? 'true' : 'false' }};
const canFeature = {{ $canFeature ? 'true' : 'false' }};
const canDelete = {{ $canDelete ? 'true' : 'false' }};

let selectedTestimonialIds = [];

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
    
    // Form submissions
    const approveForm = document.getElementById('approveForm');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSingleApprove(this);
        });
    }
    
    const bulkApproveForm = document.getElementById('bulkApproveForm');
    if (bulkApproveForm) {
        bulkApproveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'approve');
        });
    }
    
    const bulkRejectForm = document.getElementById('bulkRejectForm');
    if (bulkRejectForm) {
        bulkRejectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'reject');
        });
    }
    
    const bulkFeatureForm = document.getElementById('bulkFeatureForm');
    if (bulkFeatureForm) {
        bulkFeatureForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'feature');
        });
    }
    
    const bulkUnfeatureForm = document.getElementById('bulkUnfeatureForm');
    if (bulkUnfeatureForm) {
        bulkUnfeatureForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'unfeature');
        });
    }
    
    const bulkSoftDeleteForm = document.getElementById('bulkSoftDeleteForm');
    if (bulkSoftDeleteForm) {
        bulkSoftDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBulkAction(this, 'soft-delete');
        });
    }
    
    const softDeleteForm = document.getElementById('softDeleteForm');
    if (softDeleteForm) {
        softDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitSoftDelete(this);
        });
    }
});

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    if (!selectAll) return;
    
    const checkboxes = document.querySelectorAll('.testimonial-checkbox');
    checkboxes.forEach(checkbox => {
        if (!checkbox.disabled) {
            checkbox.checked = selectAll.checked;
        }
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.testimonial-checkbox:checked');
    selectedTestimonialIds = Array.from(checkboxes)
        .filter(cb => !cb.disabled && cb.getAttribute('data-can-bulk') === 'true')
        .map(cb => cb.value);
    
    const bulkActionsBtn = document.getElementById('bulkActionsBtn');
    
    if (bulkActionsBtn) {
        if (selectedTestimonialIds.length > 0) {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> ${selectedTestimonialIds.length} Selected <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        } else {
            bulkActionsBtn.innerHTML = `<i class="fas fa-check-double mr-2"></i> Bulk Actions <i class="fas fa-chevron-down ml-2 text-xs"></i>`;
        }
    }
}

// ============================================
// SINGLE APPROVE FUNCTIONS
// ============================================
function showApproveModal(testimonialId, name) {
    const modal = document.getElementById('approveModal');
    const form = document.getElementById('approveForm');
    const nameSpan = document.getElementById('approveTestimonialName');
    
    // Set the form action URL dynamically
    form.action = `/${routePrefix.replace(/\./g, '/')}/${testimonialId}/approve`;
    nameSpan.textContent = name || 'Unknown User';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function submitSingleApprove(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
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
            showNotification('success', data.message || 'Testimonial approved successfully.');
            hideApproveModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// ============================================
// TOGGLE FEATURED FUNCTION
// ============================================
function toggleFeatured(testimonialId, isFeatured) {
    if (!canFeature) {
        showNotification('error', 'You do not have permission to feature testimonials.');
        return;
    }
    
    const url = `/${routePrefix.replace(/\./g, '/')}/${testimonialId}/toggle-featured`;
    
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
            showNotification('success', data.message || `Testimonial ${isFeatured ? 'unfeatured' : 'featured'} successfully.`);
            setTimeout(() => window.location.reload(), 1000);
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
// SOFT DELETE FUNCTIONS
// ============================================
let currentSoftDeleteId = null;
let currentSoftDeleteName = null;

function showSoftDeleteModal(testimonialId, name) {
    if (!canDelete) {
        showNotification('error', 'You do not have permission to delete testimonials.');
        return;
    }
    
    currentSoftDeleteId = testimonialId;
    currentSoftDeleteName = name;
    
    const modal = document.getElementById('softDeleteModal');
    const form = document.getElementById('softDeleteForm');
    const nameSpan = document.getElementById('softDeleteName');
    
    form.action = `/${routePrefix.replace(/\./g, '/')}/${testimonialId}`;
    nameSpan.textContent = name || 'Unknown User';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideSoftDeleteModal() {
    const modal = document.getElementById('softDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentSoftDeleteId = null;
        currentSoftDeleteName = null;
    }
}

function submitSoftDelete(form) {
    if (!currentSoftDeleteId) {
        showNotification('error', 'No testimonial selected.');
        return;
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    fetch(form.action, {
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
            showNotification('success', data.message || 'Testimonial moved to trash successfully.');
            
            const row = document.querySelector(`tr[data-testimonial-id="${currentSoftDeleteId}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.remove();
                    updateTableAfterRemoval();
                }, 300);
            }
            
            hideSoftDeleteModal();
            selectedTestimonialIds = [];
            updateBulkActions();
            
            setTimeout(() => {
                const remainingRows = document.querySelectorAll('#testimonialsTableBody tr').length;
                if (remainingRows === 0) {
                    showEmptyState();
                } else {
                    window.location.reload();
                }
            }, 1500);
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// ============================================
// BULK ACTION FUNCTIONS
// ============================================
function showBulkApproveModal() {
    if (!canApprove) {
        showNotification('error', 'You do not have permission to approve testimonials.');
        return;
    }
    
    if (selectedTestimonialIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial.');
        return;
    }
    
    const modal = document.getElementById('bulkApproveModal');
    const selectedDiv = document.getElementById('bulkSelectedList');
    
    selectedDiv.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
        if (row) {
            const name = row.querySelector('td:nth-child(2) .font-semibold')?.textContent || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}`;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkApproveIdsContainer');
    container.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkApproveModal() {
    const modal = document.getElementById('bulkApproveModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkRejectModal() {
    if (!canApprove) {
        showNotification('error', 'You do not have permission to reject testimonials.');
        return;
    }
    
    if (selectedTestimonialIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial.');
        return;
    }
    
    const modal = document.getElementById('bulkRejectModal');
    const selectedDiv = document.getElementById('bulkRejectSelectedList');
    
    selectedDiv.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
        if (row) {
            const name = row.querySelector('td:nth-child(2) .font-semibold')?.textContent || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}`;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkRejectIdsContainer');
    container.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkRejectModal() {
    const modal = document.getElementById('bulkRejectModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkFeatureModal() {
    if (!canFeature) {
        showNotification('error', 'You do not have permission to feature testimonials.');
        return;
    }
    
    if (selectedTestimonialIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial.');
        return;
    }
    
    const modal = document.getElementById('bulkFeatureModal');
    const selectedDiv = document.getElementById('bulkFeatureSelectedList');
    
    selectedDiv.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
        if (row) {
            const name = row.querySelector('td:nth-child(2) .font-semibold')?.textContent || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}`;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkFeatureIdsContainer');
    container.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkFeatureModal() {
    const modal = document.getElementById('bulkFeatureModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkUnfeatureModal() {
    if (!canFeature) {
        showNotification('error', 'You do not have permission to unfeature testimonials.');
        return;
    }
    
    if (selectedTestimonialIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial.');
        return;
    }
    
    const modal = document.getElementById('bulkUnfeatureModal');
    const selectedDiv = document.getElementById('bulkUnfeatureSelectedList');
    
    selectedDiv.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
        if (row) {
            const name = row.querySelector('td:nth-child(2) .font-semibold')?.textContent || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}`;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkUnfeatureIdsContainer');
    container.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkUnfeatureModal() {
    const modal = document.getElementById('bulkUnfeatureModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showBulkSoftDeleteModal() {
    if (!canDelete) {
        showNotification('error', 'You do not have permission to delete testimonials.');
        return;
    }
    
    if (selectedTestimonialIds.length === 0) {
        showNotification('error', 'Please select at least one testimonial.');
        return;
    }
    
    const modal = document.getElementById('bulkSoftDeleteModal');
    const selectedDiv = document.getElementById('bulkSoftDeleteSelectedList');
    
    selectedDiv.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
        if (row) {
            const name = row.querySelector('td:nth-child(2) .font-semibold')?.textContent || 'Unknown';
            const div = document.createElement('div');
            div.className = 'text-sm py-1';
            div.innerHTML = `<i class="fas fa-user mr-2 text-gray-400"></i> ${escapeHtml(name)}`;
            selectedDiv.appendChild(div);
        }
    });
    
    const container = document.getElementById('bulkSoftDeleteIdsContainer');
    container.innerHTML = '';
    selectedTestimonialIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        container.appendChild(input);
    });
    
    const dropdown = document.getElementById('bulkActionsDropdown');
    if (dropdown) dropdown.classList.add('hidden');
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideBulkSoftDeleteModal() {
    const modal = document.getElementById('bulkSoftDeleteModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitBulkAction(form, actionType) {
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    submitButton.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
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
            showNotification('success', data.message);
            
            if (actionType === 'soft-delete') {
                selectedTestimonialIds.forEach((id, index) => {
                    const row = document.querySelector(`tr[data-testimonial-id="${id}"]`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            row.remove();
                            updateTableAfterRemoval();
                        }, 300);
                    }
                });
                
                selectedTestimonialIds = [];
                updateBulkActions();
                
                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                
                setTimeout(() => {
                    const remainingRows = document.querySelectorAll('#testimonialsTableBody tr').length;
                    if (remainingRows === 0) {
                        showEmptyState();
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }
            
            if (actionType === 'approve') hideBulkApproveModal();
            if (actionType === 'reject') hideBulkRejectModal();
            if (actionType === 'feature') hideBulkFeatureModal();
            if (actionType === 'unfeature') hideBulkUnfeatureModal();
            if (actionType === 'soft-delete') hideBulkSoftDeleteModal();
            
        } else {
            showNotification('error', data.message || 'An error occurred');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred while processing the request');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function updateTableAfterRemoval() {
    const remainingRows = document.querySelectorAll('#testimonialsTableBody tr').length;
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
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-comments text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No testimonials found</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">All testimonials have been moved to trash.</p>
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

.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.3);
}

.action-btn.assign {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
    border-color: rgba(var(--success-rgb), 0.3);
}

.action-btn.delete,
.action-btn.trash {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
    border-color: rgba(var(--danger-rgb), 0.3);
}

.action-btn.trash:hover,
.action-btn.delete:hover,
.action-btn.assign:hover,
.action-btn.view:hover {
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

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
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

.testimonial-checkbox, #selectAll {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.testimonial-checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.5;
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