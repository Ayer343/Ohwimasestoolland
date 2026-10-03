{{-- admin/testimonials/show.blade.php --}}
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
        $pageTitle = 'Testimonial Details - Developer Portal';
        $dashboardRoute = 'developer.dashboard';
        $roleBadge = 'Developer Access';
        $roleBadgeColor = 'info';
    } elseif ($isAdmin || $isSuperAdmin) {
        $layout = 'layouts.app';
        $routePrefix = 'admin.testimonials';
        $pageTitle = 'Testimonial Details - Admin Portal';
        $dashboardRoute = 'admin.dashboard';
        $roleBadge = 'Admin Access';
        $roleBadgeColor = 'primary';
    } else {
        $layout = 'layouts.app';
        $routePrefix = 'testimonials';
        $pageTitle = 'Testimonial Details';
        $dashboardRoute = 'dashboard';
        $roleBadge = 'Staff Access';
        $roleBadgeColor = 'secondary';
    }
    
    $successMessage = session('success');
    $errorMessage = session('error');
    
    $avatarUrl = $testimonial->avatar_url ?? ($testimonial->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($testimonial->name) . '&background=3b82f6&color=fff');
    $trashedCount = Testimonial::onlyTrashed()->count();
    
    // Determine if user can perform certain actions
    $canApprove = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canFeature = $isAdmin || $isSuperAdmin || $isDeveloper;
    $canDelete = $isAdmin || $isSuperAdmin || $isDeveloper;
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
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-comment-dots text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-star mr-2" style="color: var(--primary);"></i> 
                        Testimonial Details
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: {{ $testimonial->is_approved ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }}; color: {{ $testimonial->is_approved ? 'var(--success)' : 'var(--warning)' }}; border: 1px solid {{ $testimonial->is_approved ? 'rgba(var(--success-rgb), 0.3)' : 'rgba(var(--warning-rgb), 0.3)' }};">
                            <i class="fas {{ $testimonial->is_approved ? 'fa-check-circle' : 'fa-clock' }} mr-1"></i> 
                            {{ $testimonial->is_approved ? 'Approved' : 'Pending Approval' }}
                        </span>
                        @if($testimonial->is_featured)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-star mr-1"></i> Featured
                        </span>
                        @endif
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas {{ $isDeveloper ? 'fa-code' : 'fa-shield-alt' }} mr-1"></i> {{ $roleBadge }}
                        </span>
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Submitted by {{ $testimonial->name }}</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span>{{ $testimonial->created_at->format('F j, Y g:i A') }}</span>
                        @if($testimonial->approved_at)
                        <span class="mx-1">•</span>
                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                        <span>Approved {{ $testimonial->approved_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-hashtag mr-1"></i> ID: {{ $testimonial->id }}
                </div>
                
                <a href="{{ route($routePrefix . '.index') }}" 
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to List
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

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Main Details -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Avatar Section -->
                        <div class="flex flex-col items-center text-center md:w-1/3">
                            <div class="relative">
                                <img src="{{ $avatarUrl }}" alt="{{ $testimonial->name }}" 
                                     class="w-40 h-40 rounded-full object-cover border-4" 
                                     style="border-color: var(--primary);">
                                @if($testimonial->is_featured)
                                <div class="absolute -top-2 -right-2 w-8 h-8 rounded-full flex items-center justify-center"
                                     style="background-color: var(--warning);">
                                    <i class="fas fa-star text-white text-sm"></i>
                                </div>
                                @endif
                            </div>
                            
                            <div class="mt-4 text-center">
                                <div class="flex justify-center gap-1 mb-2">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star {{ $i <= $testimonial->rating ? 'text-warning' : 'text-muted' }}" 
                                           style="font-size: 20px; {{ $i <= $testimonial->rating ? 'color: var(--warning);' : 'color: var(--text-secondary);' }}"></i>
                                    @endfor
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                                      style="background-color: {{ $testimonial->rating >= 4 ? 'rgba(var(--success-rgb), 0.1)' : 'rgba(var(--warning-rgb), 0.1)' }};
                                             color: {{ $testimonial->rating >= 4 ? 'var(--success)' : 'var(--warning)' }};
                                             border: 1px solid {{ $testimonial->rating >= 4 ? 'rgba(var(--success-rgb), 0.3)' : 'rgba(var(--warning-rgb), 0.3)' }};">
                                    {{ $testimonial->rating }} / 5 Stars
                                </span>
                            </div>
                        </div>
                        
                        <!-- Details Section -->
                        <div class="flex-1">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="border-b pb-3" style="border-color: var(--border-color);">
                                    <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Full Name</label>
                                    <p class="text-base font-semibold mt-1" style="color: var(--text-primary);">{{ $testimonial->name }}</p>
                                </div>
                                
                                <div class="border-b pb-3" style="border-color: var(--border-color);">
                                    <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Email Address</label>
                                    <p class="text-base mt-1" style="color: var(--text-primary);">
                                        @if($testimonial->email)
                                            <a href="mailto:{{ $testimonial->email }}" class="hover:underline" style="color: var(--primary);">
                                                <i class="fas fa-envelope mr-1"></i> {{ $testimonial->email }}
                                            </a>
                                        @else
                                            <span class="italic" style="color: var(--text-secondary);">Not provided</span>
                                        @endif
                                    </p>
                                </div>
                                
                                @if($testimonial->role)
                                <div class="border-b pb-3" style="border-color: var(--border-color);">
                                    <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Role / Title</label>
                                    <p class="text-base mt-1" style="color: var(--text-primary);">
                                        <i class="fas fa-briefcase mr-1" style="color: var(--text-secondary);"></i> {{ $testimonial->role }}
                                    </p>
                                </div>
                                @endif
                                
                                @if($testimonial->property_location)
                                <div class="border-b pb-3" style="border-color: var(--border-color);">
                                    <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Property Location</label>
                                    <p class="text-base mt-1" style="color: var(--text-primary);">
                                        <i class="fas fa-map-marker-alt mr-1" style="color: var(--text-secondary);"></i> {{ $testimonial->property_location }}
                                    </p>
                                </div>
                                @endif
                                
                                @if($testimonial->approved_at)
                                <div class="border-b pb-3" style="border-color: var(--border-color);">
                                    <label class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Approved By</label>
                                    <p class="text-base mt-1" style="color: var(--text-primary);">
                                        <i class="fas fa-user-check mr-1" style="color: var(--success);"></i>
                                        @if($testimonial->approver)
                                            {{ $testimonial->approver->name }}
                                        @else
                                            System Admin
                                        @endif
                                        <span class="text-sm ml-2" style="color: var(--text-secondary);">
                                            ({{ $testimonial->approved_at->format('F j, Y g:i A') }})
                                        </span>
                                    </p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- Testimonial Content -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <label class="text-xs font-medium uppercase tracking-wider mb-3 block" style="color: var(--text-secondary);">
                            <i class="fas fa-quote-left mr-1"></i> Testimonial Content
                        </label>
                        <div class="p-4 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                            <p class="text-base leading-relaxed" style="color: var(--text-primary); line-height: 1.6;">
                                "{{ $testimonial->content }}"
                            </p>
                        </div>
                    </div>
                    
                    <!-- Metadata Section -->
                    <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                        <label class="text-xs font-medium uppercase tracking-wider mb-3 block" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i> Submission Information
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-globe mt-0.5" style="color: var(--text-secondary);"></i>
                                <div>
                                    <span class="text-xs block" style="color: var(--text-secondary);">IP Address</span>
                                    <span class="text-sm font-mono" style="color: var(--text-primary);">{{ $testimonial->metadata['ip_address'] ?? 'Unknown' }}</span>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-clock mt-0.5" style="color: var(--text-secondary);"></i>
                                <div>
                                    <span class="text-xs block" style="color: var(--text-secondary);">Submitted</span>
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $testimonial->created_at->format('F j, Y g:i A') }}</span>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-user-check mt-0.5" style="color: var(--text-secondary);"></i>
                                <div>
                                    <span class="text-xs block" style="color: var(--text-secondary);">Submission Type</span>
                                    <span class="text-sm" style="color: var(--text-primary);">
                                        @if($testimonial->user_id)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-user-check mr-1"></i> Registered User
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                                                <i class="fas fa-user-friends mr-1"></i> Guest
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-tachometer-alt mt-0.5" style="color: var(--text-secondary);"></i>
                                <div>
                                    <span class="text-xs block" style="color: var(--text-secondary);">Submission Method</span>
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $testimonial->metadata['submission_method'] ?? 'Web Form' }}</span>
                                </div>
                            </div>
                        </div>
                        
                        @if(isset($testimonial->metadata['user_agent']))
                        <div class="mt-3 pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="flex items-start space-x-2">
                                <i class="fas fa-laptop mt-0.5" style="color: var(--text-secondary);"></i>
                                <div class="flex-1">
                                    <span class="text-xs block" style="color: var(--text-secondary);">User Agent</span>
                                    <span class="text-xs font-mono break-all" style="color: var(--text-primary);">{{ $testimonial->metadata['user_agent'] }}</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Action Footer -->
                <div class="px-6 py-4 border-t flex flex-wrap gap-3" style="border-color: var(--border-color); background-color: var(--bg-secondary); border-radius: 0 0 0.5rem 0.5rem;">
                    @if(!$testimonial->is_approved && $canApprove)
                        <button type="button" 
                                onclick="showApproveModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                class="btn-primary px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-check mr-2"></i> Approve Testimonial
                        </button>
                    @endif
                    
                    @if($testimonial->is_approved && $canFeature)
                        <button type="button" 
                                onclick="toggleFeatured('{{ $testimonial->id }}', {{ $testimonial->is_featured ? 'true' : 'false' }})"
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center" 
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-star mr-2"></i> {{ $testimonial->is_featured ? 'Remove Featured' : 'Make Featured' }}
                        </button>
                    @endif
                    
                    @if($canDelete)
                        <button type="button" 
                                onclick="showDeleteModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                class="btn-danger px-4 py-2 rounded-lg font-medium text-white inline-flex items-center">
                            <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                        </button>
                    @endif
                    
                    <a href="{{ route($routePrefix . '.index') }}" 
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center ml-auto">
                        <i class="fas fa-arrow-left mr-2"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Sidebar -->
        <div class="lg:col-span-1">
            <!-- User Information Card (if user exists) -->
            @if($testimonial->user)
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i> User Information
                    </h3>
                    
                    <div class="text-center mb-4">
                        <img src="{{ $testimonial->user->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($testimonial->user->name) . '&background=3b82f6&color=fff' }}" 
                             alt="{{ $testimonial->user->name }}" 
                             class="w-24 h-24 rounded-full object-cover mx-auto mb-3 border-2" 
                             style="border-color: var(--primary);">
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">{{ $testimonial->user->name }}</h4>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">{{ $testimonial->user->getTypeName() }}</p>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--info-rgb), 0.1);">
                                <i class="fas fa-envelope text-xs" style="color: var(--info);"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs" style="color: var(--text-secondary);">Email</p>
                                <p class="text-sm" style="color: var(--text-primary);">{{ $testimonial->user->email }}</p>
                            </div>
                        </div>
                        
                        @if($testimonial->user->phone)
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--success-rgb), 0.1);">
                                <i class="fas fa-phone text-xs" style="color: var(--success);"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs" style="color: var(--text-secondary);">Phone</p>
                                <p class="text-sm" style="color: var(--text-primary);">{{ $testimonial->user->phone }}</p>
                            </div>
                        </div>
                        @endif
                        
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--warning-rgb), 0.1);">
                                <i class="fas fa-calendar-alt text-xs" style="color: var(--warning);"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs" style="color: var(--text-secondary);">Member Since</p>
                                <p class="text-sm" style="color: var(--text-primary);">{{ $testimonial->user->created_at->format('M j, Y') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <a href="{{ route('admin.users.show', $testimonial->user->id) }}" 
                           class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium">
                            <i class="fas fa-user mr-2"></i> View Full Profile
                        </a>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Quick Actions Card -->
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i> Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        <a href="{{ route($routePrefix . '.index') }}" 
                           class="flex items-center p-3 rounded-lg transition-all hover:translate-x-1" 
                           style="background-color: rgba(var(--secondary-rgb), 0.1); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <i class="fas fa-list text-sm" style="color: var(--secondary);"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">All Testimonials</p>
                                <p class="text-xs" style="color: var(--text-secondary);">View all testimonials</p>
                            </div>
                            <i class="fas fa-chevron-right ml-auto text-xs" style="color: var(--text-secondary);"></i>
                        </a>
                        
                        <a href="{{ route($routePrefix . '.trashed') }}" 
                           class="flex items-center p-3 rounded-lg transition-all hover:translate-x-1" 
                           style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--danger-rgb), 0.2);">
                                <i class="fas fa-trash-restore text-sm" style="color: var(--danger);"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Trashed Items</p>
                                <p class="text-xs" style="color: var(--text-secondary);">{{ $trashedCount }} item(s) in trash</p>
                            </div>
                            <i class="fas fa-chevron-right ml-auto text-xs" style="color: var(--text-secondary);"></i>
                        </a>
                        
                        @if(!$testimonial->is_approved && $canApprove)
                        <button type="button" 
                                onclick="showApproveModal('{{ $testimonial->id }}', '{{ addslashes($testimonial->name) }}')"
                                class="flex items-center p-3 rounded-lg transition-all hover:translate-x-1 w-full text-left" 
                                style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: rgba(var(--success-rgb), 0.2);">
                                <i class="fas fa-check text-sm" style="color: var(--success);"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: var(--text-primary);">Quick Approve</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Approve this testimonial</p>
                            </div>
                            <i class="fas fa-chevron-right ml-auto text-xs" style="color: var(--text-secondary);"></i>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Stats Card -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i> Quick Stats
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Total Testimonials</span>
                            <span class="text-lg font-semibold" style="color: var(--text-primary);">{{ \App\Models\Testimonial::count() }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Approved</span>
                            <span class="text-lg font-semibold" style="color: var(--success);">{{ \App\Models\Testimonial::where('is_approved', true)->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Pending</span>
                            <span class="text-lg font-semibold" style="color: var(--warning);">{{ \App\Models\Testimonial::where('is_approved', false)->count() }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm" style="color: var(--text-secondary);">Featured</span>
                            <span class="text-lg font-semibold" style="color: var(--warning);">{{ \App\Models\Testimonial::where('is_featured', true)->count() }}</span>
                        </div>
                        <div class="pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center">
                                <span class="text-sm" style="color: var(--text-secondary);">Average Rating</span>
                                <span class="text-lg font-semibold" style="color: var(--primary);">{{ round(\App\Models\Testimonial::where('is_approved', true)->avg('rating') ?? 0, 1) }} / 5</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL TEMPLATES -->
<!-- ============================================ -->

<!-- Approve Modal -->
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

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container rounded-lg shadow-xl w-full max-w-md">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Delete Testimonial
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="mb-4">
                        <p class="text-sm mb-2" style="color: var(--text-primary);">
                            Are you sure you want to permanently delete testimonial from <strong id="deleteTestimonialName" class="font-semibold"></strong>?
                        </p>
                        <p class="text-xs mt-2" style="color: var(--danger);">
                            <i class="fas fa-exclamation-circle mr-1"></i> This action cannot be undone.
                        </p>
                    </div>
                    
                    <div class="rounded-lg p-4" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <div class="flex items-start">
                            <i class="fas fa-trash-alt mr-2 mt-0.5" style="color: var(--danger);"></i>
                            <div class="text-sm" style="color: var(--text-secondary);">
                                <p>This will permanently delete the testimonial and all associated data including:</p>
                                <ul class="list-disc list-inside mt-2 text-xs space-y-0.5">
                                    <li>Avatar image (if uploaded)</li>
                                    <li>All metadata</li>
                                    <li>Approval records</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="hideDeleteModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
                    <button type="submit" class="btn-danger px-4 py-2 rounded-lg font-medium text-white">
                        <i class="fas fa-trash-alt mr-2"></i> Delete Permanently
                    </button>
                </div>
            </form>
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

let currentTestimonialId = null;

document.addEventListener('DOMContentLoaded', function() {
    autoHideMessages();
    initTooltips();
    
    // Form submissions
    const approveForm = document.getElementById('approveForm');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitApprove(this);
        });
    }
    
    const deleteForm = document.getElementById('deleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitDelete(this);
        });
    }
});

// ============================================
// APPROVE FUNCTIONS
// ============================================
function showApproveModal(testimonialId, name) {
    if (!canApprove) {
        showNotification('error', 'You do not have permission to approve testimonials.');
        return;
    }
    
    currentTestimonialId = testimonialId;
    const modal = document.getElementById('approveModal');
    const form = document.getElementById('approveForm');
    const nameSpan = document.getElementById('approveTestimonialName');
    
    form.action = `/${routePrefix}/${testimonialId}/approve`;
    nameSpan.textContent = name || 'Unknown User';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentTestimonialId = null;
    }
}

function submitApprove(form) {
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
    
    const action = isFeatured ? 'unfeature' : 'feature';
    const url = `/${routePrefix}/${testimonialId}/toggle-featured`;
    
    showNotification('info', 'Processing...');
    
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
            showNotification('success', data.message || `Testimonial ${action}d successfully.`);
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
// DELETE FUNCTIONS
// ============================================
function showDeleteModal(testimonialId, name) {
    if (!canDelete) {
        showNotification('error', 'You do not have permission to delete testimonials.');
        return;
    }
    
    currentTestimonialId = testimonialId;
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const nameSpan = document.getElementById('deleteTestimonialName');
    
    form.action = `/${routePrefix}/${testimonialId}`;
    nameSpan.textContent = name || 'Unknown User';
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        currentTestimonialId = null;
    }
}

function submitDelete(form) {
    if (!currentTestimonialId) {
        showNotification('error', 'No testimonial selected.');
        return;
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
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
            showNotification('success', data.message || 'Testimonial deleted successfully.');
            hideDeleteModal();
            setTimeout(() => {
                window.location.href = `/${routePrefix}`;
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
// HELPER FUNCTIONS
// ============================================
function showNotification(type, message) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    const notification = document.createElement('div');
    notification.className = `mb-4 p-4 rounded-lg shadow-lg`;
    notification.style.backgroundColor = type === 'success' ? 'rgba(var(--success-rgb), 0.1)' : (type === 'error' ? 'rgba(var(--danger-rgb), 0.1)' : 'rgba(var(--info-rgb), 0.1)');
    notification.style.border = type === 'success' ? '1px solid rgba(var(--success-rgb), 0.3)' : (type === 'error' ? '1px solid rgba(var(--danger-rgb), 0.3)' : '1px solid rgba(var(--info-rgb), 0.3)');
    notification.style.animation = 'slideIn 0.3s ease';
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')} mr-2" 
                   style="color: ${type === 'success' ? 'var(--success)' : (type === 'error' ? 'var(--danger)' : 'var(--info)')};"></i>
                <span style="color: ${type === 'success' ? 'var(--success)' : (type === 'error' ? 'var(--danger)' : 'var(--info)')};">${escapeHtml(message)}</span>
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

.action-btn:hover {
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