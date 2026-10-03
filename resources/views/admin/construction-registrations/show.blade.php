@extends('layouts.app')

@section('title', 'Registration Details - ' . $registration->name)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon based on registration type -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        @if($registration->isConstruction() && !$registration->property_type && !$registration->construction_documents)
                            <i class="fas fa-map-marked-alt text-xl"></i>
                        @elseif($registration->isConstruction())
                            <i class="fas fa-hard-hat text-xl"></i>
                        @elseif($registration->isPropertyCapture())
                            <i class="fas fa-building text-xl"></i>
                        @else
                            <i class="fas fa-file-alt text-xl"></i>
                        @endif
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i> 
                        {{ $registration->registration_type_label }}
                        @if($registration->isConstruction() && !$registration->property_type && !$registration->construction_documents)
                            <span class="ml-3 px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-map-marked-alt mr-1"></i> Vacant Land
                            </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-user mr-2"></i>
                        <span>{{ $registration->name }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock mr-2"></i>
                        <span>Submitted {{ $registration->submitted_at ? $registration->submitted_at->diffForHumans() : $registration->created_at->diffForHumans() }}</span>
                        @if($registration->purpose)
                            <span class="mx-2">•</span>
                            <i class="fas fa-tag mr-2"></i>
                            <span>{{ $registration->purpose_label }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                @if(!in_array($registration->status, [
                    App\Models\LandlordConstructionRegistration::STATUS_APPROVED, 
                    App\Models\LandlordConstructionRegistration::STATUS_REJECTED,
                    App\Models\LandlordConstructionRegistration::STATUS_CANCELLED
                ]))
                <button onclick="openStatusModal()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-check-circle mr-2"></i> Update Status
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="card p-4">
        <div class="flex items-center space-x-4 overflow-x-auto">
            <a href="#overview" 
               class="tab-link active px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
               onclick="switchTab('overview', event)">
                <i class="fas fa-info-circle mr-2"></i> Overview
            </a>
            <a href="#documents" 
               class="tab-link px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="color: var(--text-secondary);"
               onclick="switchTab('documents', event)">
                <i class="fas fa-file-alt mr-2"></i> Documents
                @php
                    $documentCount = $registration->documents->count() + 
                                    ($registration->land_ownership_document ? 1 : 0) + 
                                    (is_array($registration->construction_documents) ? count($registration->construction_documents) : 0) + 
                                    (is_array($registration->property_documents) ? count($registration->property_documents) : 0);
                @endphp
                @if($documentCount > 0)
                <span class="ml-2 px-2 py-0.5 rounded-full text-xs"
                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    {{ $documentCount }}
                </span>
                @endif
            </a>
            <a href="#notes" 
               class="tab-link px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="color: var(--text-secondary);"
               onclick="switchTab('notes', event)">
                <i class="fas fa-sticky-note mr-2"></i> Notes
                @if($registration->notes->count() > 0)
                <span class="ml-2 px-2 py-0.5 rounded-full text-xs"
                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    {{ $registration->notes->count() }}
                </span>
                @endif
            </a>
            <a href="#timeline" 
               class="tab-link px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="color: var(--text-secondary);"
               onclick="switchTab('timeline', event)">
                <i class="fas fa-history mr-2"></i> Timeline
            </a>
            @if($registration->approvedProperty)
            <a href="#property" 
               class="tab-link px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="color: var(--text-secondary);"
               onclick="switchTab('property', event)">
                <i class="fas fa-building mr-2"></i> Created Property
            </a>
            @endif
            @if($registration->tenants->count() > 0 || ($registration->tenant_data && count($registration->tenant_data) > 0) || $hasLegacyTenantData)
            <a href="#tenants" 
               class="tab-link px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
               style="color: var(--text-secondary);"
               onclick="switchTab('tenants', event)">
                <i class="fas fa-users mr-2"></i> Tenants
                <span class="ml-2 px-2 py-0.5 rounded-full text-xs"
                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    {{ $tenantStats['total'] }}
                </span>
            </a>
            @endif
        </div>
    </div>

    <!-- Status Banner -->
    @php
        $statusStyles = [
            App\Models\LandlordConstructionRegistration::STATUS_PENDING => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-clock', 'label' => 'Pending Review'],
            App\Models\LandlordConstructionRegistration::STATUS_IN_REVIEW => ['bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)', 'icon' => 'fa-search', 'label' => 'In Review'],
            App\Models\LandlordConstructionRegistration::STATUS_APPROVED => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'icon' => 'fa-check-circle', 'label' => 'Approved'],
            App\Models\LandlordConstructionRegistration::STATUS_REJECTED => ['bg' => 'rgba(var(--danger-rgb), 0.1)', 'text' => 'var(--danger)', 'icon' => 'fa-times-circle', 'label' => 'Rejected'],
            App\Models\LandlordConstructionRegistration::STATUS_NEEDS_INFO => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'icon' => 'fa-question-circle', 'label' => 'Needs Information'],
            App\Models\LandlordConstructionRegistration::STATUS_CANCELLED => ['bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--text-secondary)', 'icon' => 'fa-ban', 'label' => 'Cancelled'],
        ];
        $style = $statusStyles[$registration->status] ?? $statusStyles[App\Models\LandlordConstructionRegistration::STATUS_PENDING];
    @endphp
    <div class="card p-4" style="background-color: {{ $style['bg'] }}; border-left: 4px solid {{ $style['text'] }};">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas {{ $style['icon'] }} text-2xl mr-3" style="color: {{ $style['text'] }};"></i>
                <div>
                    <span class="text-lg font-semibold" style="color: {{ $style['text'] }};">{{ $style['label'] }}</span>
                    @if($registration->reviewed_at)
                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                        Reviewed by {{ $registration->reviewer?->name }} on {{ $registration->reviewed_at->format('M d, Y H:i') }}
                    </p>
                    @endif
                </div>
            </div>
            @if($registration->assignedTo)
            <div class="flex items-center">
                <div class="text-right mr-3">
                    <div class="text-sm font-medium" style="color: var(--text-primary);">Assigned to</div>
                    <div class="text-sm" style="color: var(--text-secondary);">{{ $registration->assignedTo->name }}</div>
                    @if($registration->assigned_at)
                    <div class="text-xs" style="color: var(--text-secondary);">{{ $registration->days_since_assignment }} days ago</div>
                    @endif
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
            @endif
            @if($registration->is_overdue)
            <div class="flex items-center">
                <span class="px-3 py-1 rounded-full text-xs font-medium"
                      style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Overdue ({{ $registration->days_since_submission }} days)
                </span>
            </div>
            @endif
        </div>
    </div>

    <!-- Overview Tab Content -->
    <div id="overview-tab" class="tab-content">
        <div class="grid grid-cols-1 gap-6">
            <!-- Registration Summary Card -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-clipboard-list mr-2" style="color: var(--primary);"></i> Registration Summary
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Registration Type</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $registration->registration_type_label }}</div>
                        </div>
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Purpose</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $registration->purpose_label }}</div>
                        </div>
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Access Token</div>
                            <div class="font-mono text-sm" style="color: var(--text-primary);">{{ $registration->access_token }}</div>
                        </div>
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                            <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Submission Date</div>
                            <div class="font-semibold" style="color: var(--text-primary);">{{ $registration->submitted_at ? $registration->submitted_at->format('M d, Y H:i') : 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Applicant Information -->
                <div class="card md:col-span-1">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-user mr-2" style="color: var(--primary);"></i> Applicant Information
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Full Name</label>
                                <p class="mt-1 font-medium" style="color: var(--text-primary);">{{ $registration->name }}</p>
                            </div>
                            
                            @if($registration->landlord)
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Linked Landlord Account</label>
                                <p class="mt-1">
                                    <a href="{{ route('admin.users.show', $registration->landlord) }}" 
                                       class="text-primary hover:underline">
                                        {{ $registration->landlord->name }}
                                    </a>
                                </p>
                            </div>
                            @endif
                            
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Contact Information</label>
                                <div class="mt-2 space-y-2">
                                    @if($registration->primary_phone)
                                    <div class="flex items-center">
                                        <i class="fas fa-phone mr-2" style="color: var(--primary); width: 16px;"></i>
                                        <span style="color: var(--text-primary);">{{ $registration->primary_phone }}</span>
                                    </div>
                                    @endif
                                    
                                    @if($registration->additional_phones && is_array($registration->additional_phones))
                                        @foreach($registration->additional_phones as $phone)
                                        <div class="flex items-center">
                                            <i class="fas fa-phone-alt mr-2" style="color: var(--secondary); width: 16px;"></i>
                                            <span style="color: var(--text-primary);">{{ $phone }}</span>
                                        </div>
                                        @endforeach
                                    @endif
                                    
                                    @if($registration->email)
                                    <div class="flex items-center">
                                        <i class="fas fa-envelope mr-2" style="color: var(--warning); width: 16px;"></i>
                                        <span style="color: var(--text-primary);">{{ $registration->email }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Land/Plot Information -->
                <div class="card md:col-span-2">
                    <div class="p-6 border-b" style="border-color: var(--border-color);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-map-marked-alt mr-2" style="color: var(--primary);"></i> Land/Plot Information
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Name</label>
                                <p class="mt-1 font-medium" style="color: var(--text-primary);">{{ $registration->property_name ?: 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Plot Number</label>
                                <p class="mt-1" style="color: var(--text-primary);">{{ $registration->plot_number ?: 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Street Name</label>
                                <p class="mt-1" style="color: var(--text-primary);">{{ $registration->street_name ?: 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Digital Address</label>
                                <p class="mt-1" style="color: var(--text-primary);">{{ $registration->digital_address ?: 'Not provided' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Zone</label>
                                <p class="mt-1" style="color: var(--text-primary);">{{ $registration->zone ?: 'Not assigned' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Section</label>
                                <p class="mt-1" style="color: var(--text-primary);">{{ $registration->section ?: 'Not assigned' }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Full Address</label>
                                <p class="mt-1 p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); color: var(--text-primary);">
                                    {{ $registration->full_address }}
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium" style="color: var(--text-secondary);">Land Description</label>
                                <p class="mt-1" style="color: var(--text-primary);">{{ $registration->land_description ?: 'No description provided' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Construction Details Section - Only show if there are construction details -->
            @if($registration->isConstruction() && (
                $registration->property_type || 
                $registration->property_status || 
                $registration->estimated_bedrooms || 
                $registration->estimated_completion || 
                $registration->has_plans || 
                ($registration->construction_documents && is_array($registration->construction_documents))
            ))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hard-hat mr-2" style="color: var(--primary);"></i> Construction Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @if($registration->property_type)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Type</label>
                            <p class="mt-1">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $registration->formatted_property_type }}
                                </span>
                            </p>
                        </div>
                        @endif
                        
                        @if($registration->property_status)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Status</label>
                            <p class="mt-1">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var({{ $registration->property_status === 'completed' ? 'success' : ($registration->property_status === 'under_construction' ? 'warning' : 'info') }}-rgb), 0.1); color: var(--{{ $registration->property_status === 'completed' ? 'success' : ($registration->property_status === 'under_construction' ? 'warning' : 'info') }});">
                                    {{ ucfirst(str_replace('_', ' ', $registration->property_status)) }}
                                </span>
                            </p>
                        </div>
                        @endif
                        
                        @if($registration->estimated_bedrooms)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Estimated Bedrooms</label>
                            <p class="mt-1" style="color: var(--text-primary);">{{ $registration->estimated_bedrooms }}</p>
                        </div>
                        @endif
                        
                        @if($registration->estimated_completion)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Estimated Completion</label>
                            <p class="mt-1" style="color: var(--text-primary);">{{ $registration->estimated_completion->format('M d, Y') }}</p>
                        </div>
                        @endif
                        
                        @if($registration->has_plans)
                        <div class="md:col-span-3">
                            <div class="flex items-center">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle mr-1"></i> Has construction plans
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>
                    
                    @if($registration->construction_documents && is_array($registration->construction_documents))
                    <div class="mt-6">
                        <label class="text-sm font-medium mb-2 block" style="color: var(--text-secondary);">Construction Documents</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($registration->construction_documents as $index => $document)
                            <a href="{{ Storage::url($document) }}" target="_blank" 
                               class="p-3 rounded-lg flex items-center border hover:shadow-md transition-all"
                               style="border-color: var(--border-color);">
                                <i class="fas fa-file-pdf text-red-500 mr-3"></i>
                                <span class="text-sm truncate" style="color: var(--text-primary);">Document {{ $index + 1 }}</span>
                                <i class="fas fa-download ml-auto text-xs" style="color: var(--primary);"></i>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Vacant Land Notice (when no construction details) -->
            @if($registration->isConstruction() && !$registration->property_type && !$registration->construction_documents)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-map-marked-alt mr-2" style="color: var(--info);"></i> Vacant Land Registration
                    </h3>
                </div>
                <div class="p-6">
                    <div class="alert alert-info mb-0" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">This is a vacant land registration</p>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    The landlord has registered vacant land without construction details. 
                                    When approving this registration, the property will be created with status "vacant". 
                                    The landlord can provide construction details later when ready to build.
                                </p>
                                @if($registration->purpose === \App\Models\LandlordConstructionRegistration::PURPOSE_BOTH)
                                <p class="text-sm mt-2" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <strong>Note:</strong> The landlord indicated they intend to register this property permanently after construction.
                                </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Property Capture Details Section -->
            @if($registration->isPropertyCapture())
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-building mr-2" style="color: var(--primary);"></i> Existing Property Details
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        @if($registration->existing_property_type)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Type</label>
                            <p class="mt-1">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $registration->formatted_existing_property_type }}
                                </span>
                            </p>
                        </div>
                        @endif
                        
                        @if($registration->existing_property_status)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Status</label>
                            <p class="mt-1">
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var({{ $registration->existing_property_status === 'active' ? 'success' : 'warning' }}-rgb), 0.1); color: var(--{{ $registration->existing_property_status === 'active' ? 'success' : 'warning' }});">
                                    {{ ucfirst(str_replace('_', ' ', $registration->existing_property_status)) }}
                                </span>
                            </p>
                        </div>
                        @endif
                        
                        @if($registration->existing_bedrooms)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Bedrooms</label>
                            <p class="mt-1" style="color: var(--text-primary);">{{ $registration->existing_bedrooms }}</p>
                        </div>
                        @endif
                        
                        @if($registration->existing_bathrooms)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Bathrooms</label>
                            <p class="mt-1" style="color: var(--text-primary);">{{ $registration->existing_bathrooms }}</p>
                        </div>
                        @endif
                        
                        @if($registration->year_built)
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Year Built</label>
                            <p class="mt-1" style="color: var(--text-primary);">{{ $registration->year_built }}</p>
                        </div>
                        @endif
                        
                        <div>
                            <label class="text-sm font-medium" style="color: var(--text-secondary);">Has Tenants</label>
                            <p class="mt-1">
                                @if($registration->has_tenants)
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-check mr-1"></i> Yes ({{ $registration->tenant_summary }})
                                </span>
                                @else
                                <span class="px-3 py-1 rounded-full text-xs font-medium"
                                      style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                    <i class="fas fa-times mr-1"></i> No
                                </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    @if($registration->property_photos && is_array($registration->property_photos))
                    <div class="mt-6">
                        <label class="text-sm font-medium mb-2 block" style="color: var(--text-secondary);">Property Photos</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach($registration->property_photos as $photo)
                            <a href="{{ Storage::url($photo) }}" target="_blank" 
                               class="aspect-square rounded-lg overflow-hidden border hover:shadow-lg transition-all relative block"
                               style="border-color: var(--border-color);">
                                <img src="{{ Storage::url($photo) }}" alt="Property photo" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-30 transition-all flex items-center justify-center">
                                    <i class="fas fa-download text-white opacity-0 hover:opacity-100"></i>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    @if($registration->property_documents && is_array($registration->property_documents))
                    <div class="mt-6">
                        <label class="text-sm font-medium mb-2 block" style="color: var(--text-secondary);">Property Documents</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($registration->property_documents as $index => $document)
                            <a href="{{ Storage::url($document) }}" target="_blank" 
                               class="p-3 rounded-lg flex items-center border hover:shadow-md transition-all"
                               style="border-color: var(--border-color);">
                                <i class="fas fa-file-pdf text-red-500 mr-3"></i>
                                <span class="text-sm truncate" style="color: var(--text-primary);">Document {{ $index + 1 }}</span>
                                <i class="fas fa-download ml-auto text-xs" style="color: var(--primary);"></i>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Land Ownership Document -->
            @if($registration->land_ownership_document)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Land Ownership Document
                    </h3>
                </div>
                <div class="p-6">
                    <a href="{{ Storage::url($registration->land_ownership_document) }}" target="_blank" 
                       class="inline-flex items-center p-4 rounded-lg border hover:shadow-md transition-all"
                       style="border-color: var(--border-color);">
                        <i class="fas fa-file-pdf text-red-500 text-2xl mr-3"></i>
                        <div>
                            <div class="font-medium" style="color: var(--text-primary);">Ownership Document</div>
                            <div class="text-sm" style="color: var(--text-secondary);">Click to view/download</div>
                        </div>
                        <i class="fas fa-download ml-4" style="color: var(--primary);"></i>
                    </a>
                </div>
            </div>
            @endif

            <!-- Admin Notes -->
            @if($registration->admin_notes)
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color);">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-2" style="color: var(--warning);"></i> Admin Notes
                    </h3>
                </div>
                <div class="p-6">
                    <p class="p-4 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary);">
                        {{ $registration->admin_notes }}
                    </p>
                </div>
            </div>
            @endif

            <!-- Rejection Reason -->
            @if($registration->status === App\Models\LandlordConstructionRegistration::STATUS_REJECTED && $registration->rejection_reason)
            <div class="card" style="border-left: 4px solid var(--danger);">
                <div class="p-6">
                    <div class="flex items-start">
                        <i class="fas fa-times-circle text-2xl mr-3" style="color: var(--danger);"></i>
                        <div>
                            <h4 class="font-semibold" style="color: var(--danger);">Rejection Reason</h4>
                            <p class="mt-2" style="color: var(--text-primary);">{{ $registration->rejection_reason }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Info Requested -->
            @if($registration->status === App\Models\LandlordConstructionRegistration::STATUS_NEEDS_INFO && $registration->info_requested)
            <div class="card" style="border-left: 4px solid var(--warning);">
                <div class="p-6">
                    <div class="flex items-start">
                        <i class="fas fa-question-circle text-2xl mr-3" style="color: var(--warning);"></i>
                        <div>
                            <h4 class="font-semibold" style="color: var(--warning);">Information Requested</h4>
                            <p class="mt-2" style="color: var(--text-primary);">{{ $registration->info_requested }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Documents Tab Content -->
    <div id="documents-tab" class="tab-content hidden">
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i> Documents
                    </h3>
                </div>
            </div>
            <div class="p-6">
                @php
                    $hasDocuments = $registration->documents->count() > 0 || 
                                    $registration->land_ownership_document || 
                                    ($registration->construction_documents && is_array($registration->construction_documents)) ||
                                    ($registration->property_documents && is_array($registration->property_documents));
                @endphp

                @if($hasDocuments)
                    <!-- Land Ownership Document -->
                    @if($registration->land_ownership_document)
                    <div class="mb-6">
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> Land Ownership Document
                        </h4>
                        <div class="border rounded-lg p-4 document-card" style="border-color: var(--border-color);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-sm" style="color: var(--text-primary);">Ownership Document</h4>
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            Uploaded by landlord
                                        </p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="{{ Storage::url($registration->land_ownership_document) }}" target="_blank" 
                                       class="action-btn"
                                       title="Download"
                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Construction Documents -->
                    @if($registration->construction_documents && is_array($registration->construction_documents))
                    <div class="mb-6">
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-hard-hat mr-2" style="color: var(--warning);"></i> Construction Documents
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($registration->construction_documents as $index => $document)
                            <div class="border rounded-lg p-4 document-card" style="border-color: var(--border-color);">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-sm" style="color: var(--text-primary);">Document {{ $index + 1 }}</h4>
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Construction related
                                            </p>
                                        </div>
                                    </div>
                                    <a href="{{ Storage::url($document) }}" target="_blank" 
                                       class="action-btn"
                                       title="Download"
                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Property Documents -->
                    @if($registration->property_documents && is_array($registration->property_documents))
                    <div class="mb-6">
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-building mr-2" style="color: var(--info);"></i> Property Documents
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($registration->property_documents as $index => $document)
                            <div class="border rounded-lg p-4 document-card" style="border-color: var(--border-color);">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-sm" style="color: var(--text-primary);">Document {{ $index + 1 }}</h4>
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                Property document
                                            </p>
                                        </div>
                                    </div>
                                    <a href="{{ Storage::url($document) }}" target="_blank" 
                                       class="action-btn"
                                       title="Download"
                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Additional Uploaded Documents -->
                    @if($registration->documents->count() > 0)
                    <div>
                        <h4 class="text-md font-semibold mb-3 flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-upload mr-2" style="color: var(--secondary);"></i> Additional Documents
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($registration->documents as $document)
                            <div class="border rounded-lg p-4 document-card" style="border-color: var(--border-color);">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3"
                                             style="background-color: rgba(var(--{{ $document->file_color }}-rgb), 0.1); color: var(--{{ $document->file_color }});">
                                            <i class="fas {{ $document->file_icon }}"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-sm" style="color: var(--text-primary);">{{ $document->filename }}</h4>
                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $document->formatted_file_size }} • {{ $document->created_at->format('M d, Y') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-1">
                                        <a href="{{ route('admin.construction-registrations.documents.download', [$registration, $document]) }}" 
                                           class="action-btn"
                                           title="Download"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        @if(auth()->id() === $document->uploaded_by)
                                        <button onclick="deleteDocument({{ $document->id }})"
                                                class="action-btn"
                                                title="Delete"
                                                style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs"
                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        {{ $document->type_label }}
                                    </span>
                                    @if($document->is_verified)
                                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs"
                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-check-circle mr-1"></i> Verified
                                    </span>
                                    @endif
                                </div>
                                @if($document->description)
                                <p class="text-xs mt-3 pt-3 border-t" style="border-color: var(--border-color); color: var(--text-secondary);">
                                    {{ $document->description }}
                                </p>
                                @endif
                                <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                    <i class="fas fa-user mr-1"></i> Uploaded by {{ $document->uploader?->name ?? 'Unknown' }}
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @else
                <div class="text-center py-8">
                    <i class="fas fa-file-alt text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No documents available</p>
                    <p style="color: var(--text-secondary);">The landlord hasn't uploaded any documents yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Notes Tab Content -->
    <div id="notes-tab" class="tab-content hidden">
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-sticky-note mr-2" style="color: var(--primary);"></i> Notes
                    </h3>
                    <button onclick="openNoteModal()" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-plus mr-2"></i> Add Note
                    </button>
                </div>
            </div>
            <div class="p-6">
                @if($registration->notes->count() > 0)
                <div class="space-y-4">
                    @foreach($registration->notes as $note)
                    <div class="border rounded-lg p-4" style="border-color: var(--border-color); {{ $note->is_internal ? 'border-left: 3px solid var(--warning);' : '' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start flex-1">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 mt-1"
                                     style="background-color: rgba(var({{ $note->is_internal ? 'warning' : 'info' }}-rgb), 0.1); color: var(--{{ $note->is_internal ? 'warning' : 'info' }});">
                                    <i class="fas fa-{{ $note->is_internal ? 'lock' : 'comment' }}"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $note->user?->name ?? 'Unknown' }}</span>
                                        <span class="mx-2" style="color: var(--text-secondary);">•</span>
                                        <span class="text-sm" style="color: var(--text-secondary);">{{ $note->created_at->format('M d, Y H:i') }}</span>
                                        @if($note->is_internal)
                                        <span class="ml-3 px-2 py-0.5 rounded-full text-xs"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-lock mr-1"></i> Internal
                                        </span>
                                        @endif
                                    </div>
                                    <p class="text-sm" style="color: var(--text-primary);">{{ $note->content }}</p>
                                </div>
                            </div>
                            @if(auth()->id() === $note->user_id)
                            <button onclick="deleteNote({{ $note->id }})"
                                    class="action-btn ml-2"
                                    title="Delete"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <i class="fas fa-trash"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8">
                    <i class="fas fa-sticky-note text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No notes added</p>
                    <p style="color: var(--text-secondary);">Add notes to track discussions and decisions</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Timeline Tab Content -->
    <div id="timeline-tab" class="tab-content hidden">
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Timeline
                </h3>
            </div>
            <div class="p-6">
                <div class="relative">
                    <!-- Timeline line -->
                    <div class="absolute left-8 top-0 bottom-0 w-0.5" style="background-color: var(--border-color);"></div>
                    
                    <div class="space-y-6">
                        <!-- Created -->
                        <div class="relative flex items-start">
                            <div class="absolute left-0 w-16 flex justify-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center z-10"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 2px solid var(--card-bg);">
                                    <i class="fas fa-plus"></i>
                                </div>
                            </div>
                            <div class="ml-16 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium" style="color: var(--text-primary);">Registration Created</h4>
                                    <span class="text-sm" style="color: var(--text-secondary);">{{ $registration->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">Application submitted by {{ $registration->name }}</p>
                            </div>
                        </div>

                        <!-- Submitted -->
                        @if($registration->submitted_at && $registration->submitted_at->ne($registration->created_at))
                        <div class="relative flex items-start">
                            <div class="absolute left-0 w-16 flex justify-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center z-10"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 2px solid var(--card-bg);">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <div class="ml-16 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium" style="color: var(--text-primary);">Formally Submitted</h4>
                                    <span class="text-sm" style="color: var(--text-secondary);">{{ $registration->submitted_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">Registration formally submitted for review</p>
                            </div>
                        </div>
                        @endif

                        <!-- Assigned -->
                        @if($registration->assigned_at)
                        <div class="relative flex items-start">
                            <div class="absolute left-0 w-16 flex justify-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center z-10"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 2px solid var(--card-bg);">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                            </div>
                            <div class="ml-16 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium" style="color: var(--text-primary);">Assigned to Admin</h4>
                                    <span class="text-sm" style="color: var(--text-secondary);">{{ $registration->assigned_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    Assigned to {{ $registration->assignedTo?->name ?? 'Unknown' }}
                                </p>
                            </div>
                        </div>
                        @endif

                        <!-- Reviewed -->
                        @if($registration->reviewed_at)
                        <div class="relative flex items-start">
                            <div class="absolute left-0 w-16 flex justify-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center z-10"
                                     style="background-color: rgba(var({{ $registration->status === 'approved' ? 'success' : ($registration->status === 'rejected' ? 'danger' : 'info') }}-rgb), 0.1); color: var(--{{ $registration->status === 'approved' ? 'success' : ($registration->status === 'rejected' ? 'danger' : 'info') }}); border: 2px solid var(--card-bg);">
                                    <i class="fas {{ $registration->status === 'approved' ? 'fa-check' : ($registration->status === 'rejected' ? 'fa-times' : 'fa-search') }}"></i>
                                </div>
                            </div>
                            <div class="ml-16 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium" style="color: var(--text-primary);">Registration {{ ucfirst(str_replace('_', ' ', $registration->status)) }}</h4>
                                    <span class="text-sm" style="color: var(--text-secondary);">{{ $registration->reviewed_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                    Reviewed by {{ $registration->reviewer?->name ?? 'Unknown' }}
                                </p>
                                @if($registration->status === 'approved' && $registration->approvedProperty)
                                <p class="text-sm mt-2">
                                    <a href="{{ route('properties.show', $registration->approvedProperty) }}" 
                                       class="inline-flex items-center text-primary hover:underline">
                                        <i class="fas fa-building mr-1"></i> View created property
                                    </a>
                                </p>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Cancelled -->
                        @if($registration->cancelled_at)
                        <div class="relative flex items-start">
                            <div class="absolute left-0 w-16 flex justify-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center z-10"
                                     style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary); border: 2px solid var(--card-bg);">
                                    <i class="fas fa-ban"></i>
                                </div>
                            </div>
                            <div class="ml-16 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium" style="color: var(--text-primary);">Registration Cancelled</h4>
                                    <span class="text-sm" style="color: var(--text-secondary);">{{ $registration->cancelled_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-sm mt-1" style="color: var(--text-secondary);">Registration was cancelled</p>
                            </div>
                        </div>
                        @endif

                        <!-- Activity Log Entries -->
                        @foreach($registration->activityLog as $log)
                        <div class="relative flex items-start">
                            <div class="absolute left-0 w-16 flex justify-center">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center z-10"
                                     style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary); border: 2px solid var(--card-bg);">
                                    <i class="fas fa-circle text-xs"></i>
                                </div>
                            </div>
                            <div class="ml-16 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-medium" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</h4>
                                    <span class="text-xs" style="color: var(--text-secondary);">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">{{ $log->description }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ENHANCED TENANTS TAB CONTENT WITH INVITATION TRACKING - FIXED VERSION -->
    @if($registration->tenants->count() > 0 || ($registration->tenant_data && count($registration->tenant_data) > 0) || $hasLegacyTenantData)
    <div id="tenants-tab" class="tab-content hidden">
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                            <i class="fas fa-users mr-2" style="color: var(--primary);"></i> Tenants
                            <span class="ml-3 px-2 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                {{ $tenantStats['total'] }} Total
                            </span>
                        </h3>
                        @if($registration->has_tenants)
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                            This property is marked as rented. Tenants listed below are managed through the system.
                        </p>
                        @endif
                    </div>
                    <div class="flex space-x-2">
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> {{ $tenantStats['approved'] }} Approved
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                            <i class="fas fa-clock mr-1"></i> {{ $tenantStats['pending'] }} Pending
                        </span>
                        @if($tenantStats['rejected'] > 0)
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            <i class="fas fa-times-circle mr-1"></i> {{ $tenantStats['rejected'] }} Rejected
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="p-6">
                <!-- Tenant Invitation Statistics Summary -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--info);">{{ $tenantStats['invitations_sent'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Invitations Sent</div>
                    </div>
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--success);">{{ $tenantStats['invitations_accepted'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Accepted</div>
                    </div>
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ $tenantStats['invitations_pending'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Pending Response</div>
                    </div>
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--danger-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--danger);">{{ $tenantStats['invitations_failed'] + $tenantStats['invitations_expired'] + $tenantStats['invitations_cancelled'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Failed/Expired</div>
                    </div>
                    <div class="p-4 rounded-lg text-center" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-2xl font-bold" style="color: var(--primary);">{{ $tenantStats['with_accounts'] }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Has User Account</div>
                    </div>
                </div>

                <!-- Legacy Tenant Data Migration Warning -->
                @if($hasLegacyTenantData && $needsTenantMigration)
                <div class="alert alert-warning mb-6 p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--warning);"></i>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Legacy Tenant Data Detected</p>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    This registration has {{ count($registration->tenant_data) }} tenant(s) stored in legacy JSON format that need to be migrated to individual records.
                                </p>
                            </div>
                        </div>
                        <button onclick="migrateTenantData()" 
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-database mr-2"></i> Migrate Now
                        </button>
                    </div>
                </div>
                @endif

                @if($registration->tenants->count() > 0)
                    <!-- Tenants from registration_tenants table with invitation tracking -->
                    <div class="space-y-4">
                        @foreach($tenantInvitationDetails as $tenantDetail)
                        @php
                            // Get the actual tenant model from the registration relationship
                            $tenant = $registration->tenants->firstWhere('id', $tenantDetail['tenant_id']);
                        @endphp
                        <div class="border rounded-lg p-4 tenant-card" style="border-color: var(--border-color);">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start flex-1">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4"
                                         style="background-color: rgba(var(--{{ $tenantDetail['tenant_status'] === 'approved' ? 'success' : ($tenantDetail['tenant_status'] === 'rejected' ? 'danger' : 'warning') }}-rgb), 0.1); color: var(--{{ $tenantDetail['tenant_status'] === 'approved' ? 'success' : ($tenantDetail['tenant_status'] === 'rejected' ? 'danger' : 'warning') }});">
                                        <i class="fas fa-user-circle text-2xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2 flex-wrap">
                                            <h4 class="font-semibold text-lg" style="color: var(--text-primary);">{{ $tenantDetail['tenant_name'] }}</h4>
                                            @if($tenantDetail['tenant_status'] === 'approved')
                                            <span class="ml-3 px-2 py-0.5 rounded-full text-xs font-medium"
                                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                                <i class="fas fa-check-circle mr-1"></i> Approved
                                            </span>
                                            @elseif($tenantDetail['tenant_status'] === 'rejected')
                                            <span class="ml-3 px-2 py-0.5 rounded-full text-xs font-medium"
                                                  style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                <i class="fas fa-times-circle mr-1"></i> Rejected
                                            </span>
                                            @else
                                            <span class="ml-3 px-2 py-0.5 rounded-full text-xs font-medium"
                                                  style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                <i class="fas fa-clock mr-1"></i> Pending Approval
                                            </span>
                                            @endif
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                            <div>
                                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Contact</label>
                                                @if($tenant)
                                                <p class="text-sm flex items-center mt-1" style="color: var(--text-primary);">
                                                    <i class="fas fa-phone mr-2" style="color: var(--primary); width: 14px;"></i>
                                                    {{ $tenant->phone }}
                                                </p>
                                                @if($tenant->email)
                                                <p class="text-sm flex items-center mt-1" style="color: var(--text-primary);">
                                                    <i class="fas fa-envelope mr-2" style="color: var(--warning); width: 14px;"></i>
                                                    {{ $tenant->email }}
                                                </p>
                                                @endif
                                                @else
                                                <p class="text-sm" style="color: var(--text-secondary);">Contact info not available</p>
                                                @endif
                                            </div>
                                            
                                            <!-- Invitation Status Section -->
                                            @if($tenantDetail['has_invitation'])
                                            <div>
                                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Invitation Status</label>
                                                <div class="mt-1">
                                                    @php
                                                        $invitation = $tenantDetail['invitation'];
                                                        $badgeClass = $invitation['status_badge_class'] ?? 'badge-secondary';
                                                        $badgeColors = [
                                                            'badge-success' => 'success',
                                                            'badge-warning' => 'warning',
                                                            'badge-danger' => 'danger',
                                                            'badge-info' => 'info',
                                                            'badge-secondary' => 'secondary'
                                                        ];
                                                        $color = $badgeColors[$badgeClass] ?? 'secondary';
                                                    @endphp
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                                          style="background-color: rgba(var(--{{ $color }}-rgb), 0.1); color: var(--{{ $color }});">
                                                        <i class="fas {{ $invitation['status'] === 'completed' ? 'fa-check-circle' : ($invitation['status'] === 'sent' ? 'fa-paper-plane' : ($invitation['status'] === 'pending' ? 'fa-clock' : 'fa-exclamation-circle')) }} mr-1"></i>
                                                        {{ $invitation['status_label'] }}
                                                    </span>
                                                    @if($invitation['is_expired'])
                                                    <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium"
                                                          style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                                        <i class="fas fa-hourglass-end mr-1"></i> Expired
                                                    </span>
                                                    @endif
                                                </div>
                                                @if($invitation['sent_at'])
                                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    Sent: {{ $invitation['sent_at'] }}
                                                </p>
                                                @endif
                                                @if($invitation['expires_at'] && $invitation['status'] !== 'completed')
                                                <p class="text-xs" style="color: var(--text-secondary);">
                                                    Expires: {{ $invitation['expires_at'] }}
                                                </p>
                                                @endif
                                                @if($invitation['accepted_at'])
                                                <p class="text-xs mt-1" style="color: var(--success);">
                                                    <i class="fas fa-check-circle mr-1"></i> Accepted: {{ $invitation['accepted_at'] }}
                                                </p>
                                                @endif
                                            </div>
                                            
                                            <div>
                                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Invitation Details</label>
                                                <div class="mt-1 space-y-1">
                                                    <p class="text-xs flex items-center" style="color: var(--text-secondary);">
                                                        <i class="fas fa-tag mr-1" style="width: 12px;"></i>
                                                        Channels: {{ $invitation['formatted_sent_channels'] ?? 'None' }}
                                                    </p>
                                                    @if($invitation['failure_reason'])
                                                    <p class="text-xs flex items-center" style="color: var(--danger);">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                                        {{ $invitation['failure_reason'] }}
                                                    </p>
                                                    @endif
                                                    @if($invitation['invitation_url'] && $invitation['is_active'])
                                                    <div class="mt-2">
                                                        <button onclick="copyInvitationLink('{{ $invitation['invitation_url'] }}')"
                                                                class="text-xs px-2 py-1 rounded inline-flex items-center"
                                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                            <i class="fas fa-copy mr-1"></i> Copy Link
                                                        </button>
                                                        <button onclick="resendInvitation({{ $invitation['id'] }})"
                                                                class="text-xs px-2 py-1 rounded inline-flex items-center ml-1"
                                                                style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                            <i class="fas fa-redo mr-1"></i> Resend
                                                        </button>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                            @else
                                            <div>
                                                <label class="text-xs font-medium" style="color: var(--text-secondary);">Invitation</label>
                                                <p class="text-xs mt-1" style="color: var(--warning);">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> No invitation sent
                                                </p>
                                                @if($tenantDetail['tenant_status'] === 'approved')
                                                <button onclick="sendTenantInvitation({{ $tenantDetail['tenant_id'] }})"
                                                        class="mt-2 text-xs px-2 py-1 rounded inline-flex items-center"
                                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                    <i class="fas fa-paper-plane mr-1"></i> Send Invitation
                                                </button>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Invitation Statistics for this tenant -->
                                        @if($tenantDetail['invitation_stats']['total'] > 1)
                                        <div class="mt-3 pt-3 border-t text-xs" style="border-color: var(--border-color);">
                                            <span style="color: var(--text-secondary);">Total invitations sent: {{ $tenantDetail['invitation_stats']['total'] }}</span>
                                            @if($tenantDetail['invitation_stats']['sent'] > 0)
                                            <span class="ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                {{ $tenantDetail['invitation_stats']['sent'] }} sent
                                            </span>
                                            @endif
                                            @if($tenantDetail['invitation_stats']['completed'] > 0)
                                            <span class="ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                {{ $tenantDetail['invitation_stats']['completed'] }} accepted
                                            </span>
                                            @endif
                                            @if($tenantDetail['invitation_stats']['failed'] > 0)
                                            <span class="ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                {{ $tenantDetail['invitation_stats']['failed'] }} failed
                                            </span>
                                            @endif
                                            @if($tenantDetail['invitation_stats']['expired'] > 0)
                                            <span class="ml-2 px-2 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                                {{ $tenantDetail['invitation_stats']['expired'] }} expired
                                            </span>
                                            @endif
                                        </div>
                                        @endif
                                        
                                        @if($tenant && $tenant->notes)
                                        <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05);">
                                            <label class="text-xs font-medium" style="color: var(--text-secondary);">Notes</label>
                                            <p class="text-sm mt-1" style="color: var(--text-primary);">{{ $tenant->notes }}</p>
                                        </div>
                                        @endif
                                        
                                        @if($tenant && $tenant->approved_by && $tenant->approved_at)
                                        <div class="mt-3 text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                            Approved by {{ $tenant->approver?->name ?? 'Unknown' }} on {{ $tenant->approved_at->format('M d, Y H:i') }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Tenant Actions -->
                                @if($tenantDetail['tenant_status'] === 'pending')
                                <div class="flex space-x-2 ml-4">
                                    <button onclick="approveTenant({{ $tenantDetail['tenant_id'] }})"
                                            class="action-btn"
                                            title="Approve Tenant"
                                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="rejectTenant({{ $tenantDetail['tenant_id'] }})"
                                            class="action-btn"
                                            title="Reject Tenant"
                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @elseif($registration->tenant_data && count($registration->tenant_data) > 0)
                    <!-- Fallback: Display tenant data from JSON field (legacy data) -->
                    <div class="alert alert-info mb-4" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">Legacy Tenant Data</p>
                                <p class="text-sm" style="color: var(--text-secondary);">These tenants were registered with the initial application and need to be migrated to individual records.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($registration->tenant_data as $index => $tenantData)
                        <div class="border rounded-lg p-4" style="border-color: var(--border-color);">
                            <div class="flex items-start">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold" style="color: var(--text-primary);">{{ $tenantData['name'] ?? 'Unknown' }}</h4>
                                    @if(isset($tenantData['phone']))
                                    <p class="text-sm flex items-center mt-1" style="color: var(--text-primary);">
                                        <i class="fas fa-phone mr-2" style="color: var(--primary);"></i>
                                        {{ $tenantData['phone'] }}
                                    </p>
                                    @endif
                                    @if(isset($tenantData['email']) && $tenantData['email'])
                                    <p class="text-sm flex items-center mt-1" style="color: var(--text-primary);">
                                        <i class="fas fa-envelope mr-2" style="color: var(--warning);"></i>
                                        {{ $tenantData['email'] }}
                                    </p>
                                    @endif
                                    @if(isset($tenantData['notes']) && $tenantData['notes'])
                                    <p class="text-xs mt-2 p-2 rounded" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                                        {{ $tenantData['notes'] }}
                                    </p>
                                    @endif
                                    <div class="mt-2">
                                        <span class="px-2 py-0.5 rounded-full text-xs"
                                              style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-clock mr-1"></i> Pending Migration
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <!-- Bulk Migration Action -->
                    @if($needsTenantMigration)
                    <div class="mt-4 text-right">
                        <button onclick="migrateTenantData()" 
                                class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                            <i class="fas fa-database mr-2"></i> Migrate All to Individual Records
                        </button>
                    </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <i class="fas fa-users-slash text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No tenants registered</p>
                        <p style="color: var(--text-secondary);">This property has no associated tenants.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Property Tab Content -->
    @if($registration->approvedProperty)
    <div id="property-tab" class="tab-content hidden">
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-building mr-2" style="color: var(--success);"></i> Created Property
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Name</label>
                        <p class="mt-1 font-medium" style="color: var(--text-primary);">{{ $registration->approvedProperty->property_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Type</label>
                        <p class="mt-1">{{ $registration->approvedProperty->propertyType?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Registration Plan</label>
                        <p class="mt-1">{{ $registration->approvedProperty->registrationPlan?->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Landlord</label>
                        <p class="mt-1">
                            <a href="{{ route('admin.users.show', $registration->approvedProperty->landlord) }}" 
                               class="text-primary hover:underline">
                                {{ $registration->approvedProperty->landlord?->name ?? 'N/A' }}
                            </a>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">House Number</label>
                        <p class="mt-1">{{ $registration->approvedProperty->house_number ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Street Name</label>
                        <p class="mt-1">{{ $registration->approvedProperty->street_name ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Zone</label>
                        <p class="mt-1">{{ $registration->zone ?: $registration->approvedProperty->zone ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Section</label>
                        <p class="mt-1">{{ $registration->section ?: $registration->approvedProperty->section ?: 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-secondary);">Property Status</label>
                        <p class="mt-1">
                            <span class="px-3 py-1 rounded-full text-xs font-medium"
                                  style="background-color: rgba(var(--{{ $registration->approvedProperty->status === 'active' ? 'success' : ($registration->approvedProperty->status === 'under_construction' ? 'warning' : 'info') }}-rgb), 0.1); color: var(--{{ $registration->approvedProperty->status === 'active' ? 'success' : ($registration->approvedProperty->status === 'under_construction' ? 'warning' : 'info') }});">
                                <i class="fas {{ $registration->approvedProperty->status === 'active' ? 'fa-check-circle' : ($registration->approvedProperty->status === 'under_construction' ? 'fa-hard-hat' : 'fa-door-open') }} mr-1"></i>
                                {{ ucfirst(str_replace('_', ' ', $registration->approvedProperty->status)) }}
                            </span>
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <a href="{{ route('properties.show', $registration->approvedProperty) }}" 
                           class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-external-link-alt mr-2"></i> View Full Property Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('statusModal')"></div>
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">Update Registration Status</h3>
            <button type="button" class="modal-close" onclick="closeModal('statusModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="{{ route('admin.construction-registrations.update-status', $registration) }}" method="POST" id="statusForm">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <!-- Registration Information Summary -->
                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border-left: 4px solid var(--primary);">
                        <h4 class="font-medium mb-3 flex items-center" style="color: var(--primary);">
                            <i class="fas fa-info-circle mr-2"></i> Registration Information
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Property Name:</span>
                                <span style="color: var(--text-primary);font-weight:600;" id="propertyNameDisplay">{{ $registration->property_name ?: 'Not specified' }}</span>
                            </div>
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Plot Number:</span>
                                <span style="color: var(--text-primary);">{{ $registration->plot_number ?: 'Not specified' }}</span>
                            </div>
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Applicant Name:</span>
                                <span style="color: var(--text-primary);">{{ $registration->name }}</span>
                            </div>
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Applicant Phone:</span>
                                <span style="color: var(--text-primary);">{{ $registration->primary_phone }}</span>
                            </div>
                            @if($registration->property_type || $registration->existing_property_type)
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Property Type from App:</span>
                                <span style="color: var(--text-primary);">
                                    {{ $registration->formatted_property_type ?: $registration->formatted_existing_property_type }}
                                </span>
                            </div>
                            @endif
                            @if($registration->tenants->count() > 0)
                            <div>
                                <span class="font-medium" style="color: var(--text-secondary);">Tenants:</span>
                                <span style="color: var(--text-primary);">{{ $registration->tenants->count() }} registered ({{ $registration->tenants->where('status', 'pending')->count() }} pending)</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" id="statusSelect" class="form-input w-full p-3 rounded-lg border" required
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                onchange="handleStatusChange()">
                            <option value="">Select Status...</option>
                            <option value="{{ App\Models\LandlordConstructionRegistration::STATUS_PENDING }}" {{ $registration->status === App\Models\LandlordConstructionRegistration::STATUS_PENDING ? 'selected' : '' }}>Pending</option>
                            <option value="{{ App\Models\LandlordConstructionRegistration::STATUS_IN_REVIEW }}" {{ $registration->status === App\Models\LandlordConstructionRegistration::STATUS_IN_REVIEW ? 'selected' : '' }}>In Review</option>
                            <option value="{{ App\Models\LandlordConstructionRegistration::STATUS_APPROVED }}" {{ $registration->status === App\Models\LandlordConstructionRegistration::STATUS_APPROVED ? 'selected' : '' }}>Approved</option>
                            <option value="{{ App\Models\LandlordConstructionRegistration::STATUS_REJECTED }}" {{ $registration->status === App\Models\LandlordConstructionRegistration::STATUS_REJECTED ? 'selected' : '' }}>Rejected</option>
                            <option value="{{ App\Models\LandlordConstructionRegistration::STATUS_NEEDS_INFO }}" {{ $registration->status === App\Models\LandlordConstructionRegistration::STATUS_NEEDS_INFO ? 'selected' : '' }}>Needs Information</option>
                        </select>
                    </div>

                    <!-- Approval Fields (shown when approved is selected) -->
                    <div id="approvalFields" class="space-y-4 hidden">
                        <div class="p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                            <h4 class="font-medium mb-3 flex items-center" style="color: var(--success);">
                                <i class="fas fa-check-circle mr-2"></i> Approval Details
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- REGISTRATION PLAN SELECTION -->
                                <div class="md:col-span-2">
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        Registration Plan <span class="text-red-500">*</span>
                                    </label>
                                    <select name="registration_plan_id" 
                                            id="registrationPlanSelect"
                                            class="form-input w-full p-3 rounded-lg border" 
                                            required
                                            onchange="handleRegistrationPlanChange()">
                                        <option value="">-- Select Registration Plan --</option>
                                        @forelse($registrationPlans as $plan)
                                            <option value="{{ $plan->id }}" 
                                                    data-zone="{{ $plan->zone }}" 
                                                    data-section="{{ $plan->section }}"
                                                    data-estimated="{{ $plan->estimated_houses }}"
                                                    data-registered="{{ $plan->registered_count }}"
                                                    data-available="{{ $plan->available_spots }}"
                                                    data-occupancy="{{ $plan->occupancy_rate }}"
                                                    data-status="{{ $plan->status }}"
                                                    {{ !$plan->is_available ? 'disabled' : '' }}>
                                                {{ $plan->zone }} - {{ $plan->section }}
                                                ({{ $plan->estimated_houses }} houses)
                                                @if($plan->available_spots)
                                                    - Available: {{ $plan->available_spots }}
                                                @endif
                                                @if(!$plan->is_available)
                                                    [FULL]
                                                @endif
                                                - {{ ucfirst($plan->status) }}
                                            </option>
                                        @empty
                                            <option value="" disabled>No registration plans available</option>
                                        @endforelse
                                    </select>
                                    @if($registrationPlans->isEmpty())
                                        <p class="text-sm mt-2" style="color: var(--danger);">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> 
                                            No registration plans available. Please create a registration plan first.
                                        </p>
                                    @endif
                                </div>

                                <!-- Plan Details Display -->
                                <div id="planDetails" class="md:col-span-2 hidden">
                                    <div class="p-4 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border-left: 4px solid var(--info);">
                                        <div class="flex items-start">
                                            <i class="fas fa-info-circle mt-1 mr-3" style="color: var(--info);"></i>
                                            <div class="flex-1">
                                                <h5 class="font-semibold mb-2" style="color: var(--info);">Plan Information</h5>
                                                <div id="planInfo" class="grid grid-cols-2 gap-3 text-sm">
                                                    <!-- Dynamically filled by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Plan not available warning -->
                                <div id="planNotAvailableWarning" class="md:col-span-2 hidden">
                                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                        <i class="fas fa-exclamation-circle mr-2"></i>
                                        <span id="planNotAvailableMessage">Selected plan has no available spots. Please choose another plan.</span>
                                    </div>
                                </div>

                                <!-- Property Type Selection - READ-ONLY FOR VACANT LAND -->
                                @php
                                    $isVacantLand = $registration->isConstruction() && !$registration->property_type && !$registration->construction_documents;
                                    $selectedTypeId = isset($selectedPropertyType) ? $selectedPropertyType->id : '';
                                @endphp

                                <div>
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        Property Type 
                                        @if(!$isVacantLand)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    
                                    <select name="property_type_id" id="propertyTypeSelect" 
                                            class="form-input w-full p-3 rounded-lg border" 
                                            {{ $isVacantLand ? '' : 'required' }}
                                            {{ $isVacantLand ? 'disabled' : '' }}
                                            onchange="handlePropertyTypeChange()"
                                            style="{{ $isVacantLand ? 'background-color: rgba(var(--secondary-rgb), 0.05); cursor: not-allowed; opacity: 0.8;' : '' }}">
                                        <option value="">Select Type...</option>
                                        @foreach($propertyTypes as $type)
                                            <option value="{{ $type->id }}" 
                                                    data-custom="{{ $type->slug === 'custom' ? 'true' : 'false' }}"
                                                    {{ $selectedTypeId == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    
                                    @if($isVacantLand)
                                        <p class="text-xs mt-1 text-info">
                                            <i class="fas fa-info-circle mr-1"></i> 
                                            <strong>This is vacant land.</strong> Property type will be set when construction details are provided later.
                                        </p>
                                        <!-- Hidden input to still submit the value if needed -->
                                        <input type="hidden" name="property_type_id" value="{{ $selectedTypeId }}">
                                    @elseif($selectedTypeId)
                                        <p class="text-xs mt-1 text-success">
                                            <i class="fas fa-check-circle mr-1"></i> Auto-selected from application: {{ $registration->formatted_property_type ?: $registration->formatted_existing_property_type }}
                                        </p>
                                    @else
                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-info-circle mr-1"></i> Select the property type for this registration.
                                        </p>
                                    @endif
                                </div>
                                
                                <div id="customPropertyTypeField" class="{{ isset($isCustomProperty) && $isCustomProperty ? '' : 'hidden' }} md:col-span-1">
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        Custom Property Type 
                                        @if(!$isVacantLand)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <input type="text" name="custom_property_type" class="form-input w-full p-3 rounded-lg border"
                                           placeholder="e.g., Duplex, Villa, etc."
                                           value="{{ $customPropertyValue ?? '' }}"
                                           {{ $isVacantLand ? 'readonly' : '' }}
                                           style="{{ $isVacantLand ? 'background-color: rgba(var(--secondary-rgb), 0.05); cursor: not-allowed; opacity: 0.8;' : '' }}">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Specify the custom property type</p>
                                    @if(isset($customPropertyValue))
                                        <p class="text-xs mt-1 text-info">
                                            <i class="fas fa-info-circle mr-1"></i> Pre-filled from application: "{{ $customPropertyValue }}"
                                        </p>
                                    @endif
                                </div>
                                
                                <!-- Zone and Section - Auto-filled from selected plan -->
                                <div>
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        Zone <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="zone" id="zoneInput" 
                                           class="form-input w-full p-3 rounded-lg border"
                                           placeholder="Auto-filled from plan" 
                                           value="{{ $registration->zone }}" 
                                           readonly
                                           required>
                                </div>
                                
                                <div>
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        Section
                                    </label>
                                    <input type="text" name="section" id="sectionInput" 
                                           class="form-input w-full p-3 rounded-lg border"
                                           placeholder="Auto-filled from plan" 
                                           value="{{ $registration->section }}" 
                                           readonly>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Optional - will be auto-filled if available</p>
                                </div>
                                
                                <!-- Assign to Landlord - AUTO-SELECTED based on applicant -->
                                <div class="md:col-span-2">
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">
                                        Assign to Landlord <span class="text-red-500">*</span>
                                    </label>
                                    <select name="landlord_id" id="landlordSelect" class="form-input w-full p-3 rounded-lg border" required>
                                        <option value="">Select Landlord...</option>
                                        @foreach($landlords as $landlord)
                                            <option value="{{ $landlord->id }}" 
                                                    data-properties="{{ $landlord->properties->pluck('property_name')->implode(',') }}"
                                                    data-email="{{ $landlord->email }}"
                                                    data-phone="{{ $landlord->phone }}"
                                                    {{ $registration->landlord_id == $landlord->id ? 'selected' : '' }}
                                                    {{ $landlord->name === $registration->name ? 'selected' : '' }}>
                                                {{ $landlord->name }} ({{ $landlord->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div id="landlordMatchInfo" class="text-xs mt-2">
                                        @php
                                            $matchedLandlord = $landlords->firstWhere('name', $registration->name);
                                        @endphp
                                        @if($matchedLandlord)
                                            <span class="text-success">
                                                <i class="fas fa-check-circle mr-1"></i> 
                                                Auto-selected landlord matching applicant name: <strong>"{{ $registration->name }}"</strong>
                                                @if($matchedLandlord->email)
                                                    ({{ $matchedLandlord->email }})
                                                @endif
                                            </span>
                                        @else
                                            <span style="color: var(--warning);">
                                                <i class="fas fa-info-circle mr-1"></i> 
                                                {{ $registration->landlord_id ? 'Landlord already linked to this registration' : 'No matching landlord found. Please select manually.' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Landlord Invitation Options -->
                            <div class="mt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="send_invitation" value="1" class="mr-2" onchange="toggleInvitationChannels()">
                                    <span style="color: var(--text-primary);">Send invitation to landlord</span>
                                </label>
                            </div>
                            
                            <div id="invitationChannels" class="mt-3 hidden">
                                <label class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Channels</label>
                                <div class="flex space-x-4">
                                    @foreach($availableChannels as $channel)
                                        @if($channel['available'])
                                        <label class="flex items-center">
                                            <input type="checkbox" name="invitation_channels[]" value="{{ $channel['type'] }}" class="mr-2">
                                            <span style="color: var(--text-primary);">
                                                <i class="fas {{ $channel['icon'] }} mr-1" style="color: var(--{{ $channel['color'] }});"></i>
                                                {{ ucfirst($channel['type']) }}
                                                @if(isset($channel['pending']))
                                                    <span class="text-xs" style="color: var(--warning);">({{ $channel['note'] }})</span>
                                                @endif
                                            </span>
                                        </label>
                                        @endif
                                    @endforeach
                                </div>
                                @if(empty(array_filter($availableChannels, fn($c) => $c['available'])))
                                    <p class="text-sm" style="color: var(--warning);">
                                        No communication channels available for this landlord.
                                    </p>
                                @endif
                            </div>

                            <!-- Auto-approve tenants option -->
                            @if($registration->has_tenants && $registration->tenants->where('status', 'pending')->count() > 0)
                            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                                <h5 class="font-medium mb-3" style="color: var(--text-primary);">Tenant Approval</h5>
                                <label class="flex items-center mb-3">
                                    <input type="checkbox" name="auto_approve_tenants" value="1" class="mr-2" checked>
                                    <span style="color: var(--text-primary);">
                                        <i class="fas fa-check-circle mr-1" style="color: var(--success);"></i>
                                        Automatically approve all pending tenants ({{ $registration->tenants->where('status', 'pending')->count() }} pending)
                                    </span>
                                </label>
                                <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                                    When checked, all pending tenants will be automatically approved when the registration is approved.
                                    They will be linked to the property and ready for invitations.
                                </p>
                            </div>
                            @endif

                            <!-- Tenant Invitation Options -->
                            @if($registration->tenants->where('status', 'approved')->count() > 0 || ($registration->has_tenants && $registration->tenants->where('status', 'pending')->count() > 0))
                            <div class="mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                                <h5 class="font-medium mb-3" style="color: var(--text-primary);">Tenant Invitations</h5>
                                <label class="flex items-center mb-3">
                                    <input type="checkbox" name="send_tenant_invitations" value="1" class="mr-2" onchange="toggleTenantInvitationChannels()">
                                    <span style="color: var(--text-primary);">
                                        Send invitations to approved tenants
                                        @if($registration->tenants->where('status', 'approved')->count() > 0)
                                            ({{ $registration->tenants->where('status', 'approved')->count() }} already approved)
                                        @elseif($registration->has_tenants && $registration->tenants->where('status', 'pending')->count() > 0)
                                            (will be approved after auto-approval)
                                        @endif
                                    </span>
                                </label>
                                
                                <div id="tenantInvitationChannels" class="mt-3 hidden">
                                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Tenant Invitation Channels</label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="tenant_invitation_channels[]" value="email" class="mr-2" checked>
                                            <span style="color: var(--text-primary);">
                                                <i class="fas fa-envelope mr-1" style="color: var(--warning);"></i> Email
                                            </span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="tenant_invitation_channels[]" value="sms" class="mr-2" checked>
                                            <span style="color: var(--text-primary);">
                                                <i class="fas fa-phone mr-1" style="color: var(--primary);"></i> SMS
                                            </span>
                                        </label>
                                    </div>
                                    <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                        Invitations will be sent to all approved tenants. Each tenant will receive a unique link to access their tenant portal.
                                    </p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Rejection Fields -->
                    <div id="rejectionFields" class="hidden">
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Rejection Reason <span class="text-red-500">*</span></label>
                        <textarea name="rejection_reason" rows="3" 
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Please provide a reason for rejection...">{{ $registration->rejection_reason }}</textarea>
                    </div>

                    <!-- Needs Info Fields -->
                    <div id="needsInfoFields" class="hidden">
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Information Requested <span class="text-red-500">*</span></label>
                        <textarea name="info_requested" rows="3" 
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Describe what information is needed from the applicant...">{{ $registration->info_requested }}</textarea>
                    </div>

                    <!-- Common Fields -->
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" rows="3" 
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add any notes about this status update...">{{ $registration->admin_notes }}</textarea>
                    </div>

                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="notify_landlord" value="1" class="mr-2" checked>
                            <span style="color: var(--text-primary);">Notify landlord about this status change</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('statusModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-save mr-2"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Note Modal -->
<div id="noteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('noteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Add Note</h3>
            <button type="button" class="modal-close" onclick="closeModal('noteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="{{ route('admin.construction-registrations.add-note', $registration) }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Note</label>
                        <textarea name="content" rows="4" 
                                  class="form-input w-full p-3 rounded-lg border" required
                                  placeholder="Enter your note..."></textarea>
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_internal" value="1" class="mr-2" checked>
                            <span style="color: var(--text-primary);">Internal note (only visible to admins)</span>
                        </label>
                    </div>
                    
                    <div id="notifyLandlordField">
                        <label class="flex items-center">
                            <input type="checkbox" name="notify_landlord" value="1" class="mr-2">
                            <span style="color: var(--text-primary);">Notify landlord about this note</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('noteModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Note
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Approve Tenant Modal -->
<div id="approveTenantModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('approveTenantModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Approve Tenant</h3>
            <button type="button" class="modal-close" onclick="closeModal('approveTenantModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="approveTenantForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-user-check text-5xl mb-3" style="color: var(--success);"></i>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Approve Tenant</h4>
                    <p class="mb-4" style="color: var(--text-secondary);">
                        Are you sure you want to approve this tenant? They will be able to access the tenant portal.
                    </p>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="create_user_account" value="1" class="mr-2" checked>
                        <span style="color: var(--text-primary);">Create user account for tenant</span>
                    </label>
                    <p class="text-xs mt-1 ml-6" style="color: var(--text-secondary);">
                        This will create a login account for the tenant to access the portal.
                    </p>
                </div>
                
                <div class="mt-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="send_invitation" value="1" class="mr-2" checked>
                        <span style="color: var(--text-primary);">Send invitation to tenant</span>
                    </label>
                </div>
                
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Channels</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="invitation_channels[]" value="email" class="mr-2" checked>
                            <span style="color: var(--text-primary);"><i class="fas fa-envelope mr-1" style="color: var(--warning);"></i> Email</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="invitation_channels[]" value="sms" class="mr-2" checked>
                            <span style="color: var(--text-primary);"><i class="fas fa-phone mr-1" style="color: var(--primary);"></i> SMS</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('approveTenantModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success); border: 1px solid var(--success);">
                    <i class="fas fa-check-circle mr-2"></i> Approve Tenant
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Tenant Modal -->
<div id="rejectTenantModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rejectTenantModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Reject Tenant</h3>
            <button type="button" class="modal-close" onclick="closeModal('rejectTenantModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="rejectTenantForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-user-times text-5xl mb-3" style="color: var(--danger);"></i>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Reject Tenant</h4>
                    <p class="mb-4" style="color: var(--text-secondary);">
                        Are you sure you want to reject this tenant? This action cannot be undone.
                    </p>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Rejection Reason</label>
                    <textarea name="rejection_reason" rows="3" 
                              class="form-input w-full p-3 rounded-lg border" required
                              placeholder="Please provide a reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('rejectTenantModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger); border: 1px solid var(--danger);">
                    <i class="fas fa-times-circle mr-2"></i> Reject Tenant
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Send Tenant Invitation Modal -->
<div id="sendTenantInvitationModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('sendTenantInvitationModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Send Tenant Invitation</h3>
            <button type="button" class="modal-close" onclick="closeModal('sendTenantInvitationModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="sendTenantInvitationForm" method="POST">
            @csrf
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-paper-plane text-5xl mb-3" style="color: var(--info);"></i>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Send Invitation to Tenant</h4>
                    <p class="mb-4" style="color: var(--text-secondary);">
                        This will send an invitation to the tenant to access their portal.
                    </p>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Invitation Channels</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="channels[]" value="email" class="mr-2" checked>
                            <span style="color: var(--text-primary);"><i class="fas fa-envelope mr-1" style="color: var(--warning);"></i> Email</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="channels[]" value="sms" class="mr-2" checked>
                            <span style="color: var(--text-primary);"><i class="fas fa-phone mr-1" style="color: var(--primary);"></i> SMS</span>
                        </label>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block mb-2 font-medium" style="color: var(--text-primary);">Custom Message (Optional)</label>
                    <textarea name="custom_message" rows="3" 
                              class="form-input w-full p-3 rounded-lg border"
                              placeholder="Add a personal message to the invitation..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('sendTenantInvitationModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--info); border: 1px solid var(--info);">
                    <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Document Modal -->
<div id="deleteDocumentModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteDocumentModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Document</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteDocumentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Are you sure?</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action cannot be undone. The document will be permanently deleted.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('deleteDocumentModal')">
                Cancel
            </button>
            <form id="deleteDocumentForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmDeleteDocument()">
                <i class="fas fa-trash-alt mr-2"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Delete Note Modal -->
<div id="deleteNoteModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('deleteNoteModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete Note</h3>
            <button type="button" class="modal-close" onclick="closeModal('deleteNoteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4" style="color: var(--danger);"></i>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Are you sure?</h4>
                <p class="mb-4" style="color: var(--text-secondary);">
                    This action cannot be undone. The note will be permanently deleted.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                    onclick="closeModal('deleteNoteModal')">
                Cancel
            </button>
            <form id="deleteNoteForm" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
            <button type="button" 
                    class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                    style="background-color: var(--danger); border: 1px solid var(--danger);"
                    onclick="confirmDeleteNote()">
                <i class="fas fa-trash-alt mr-2"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
// Tab switching
function switchTab(tabId, event) {
    event?.preventDefault();
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Show selected tab
    document.getElementById(tabId + '-tab').classList.remove('hidden');
    
    // Update tab links
    document.querySelectorAll('.tab-link').forEach(link => {
        link.style.backgroundColor = 'transparent';
        link.style.color = 'var(--text-secondary)';
    });
    
    event.target.closest('.tab-link').style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
    event.target.closest('.tab-link').style.color = 'var(--primary)';
    
    // Update URL hash
    window.location.hash = tabId;
}

// Status modal functions
function openStatusModal() {
    openModal('statusModal');
    // Run landlord matching when modal opens
    setTimeout(matchLandlordByProperty, 500);
}

function handleStatusChange() {
    const status = document.getElementById('statusSelect').value;
    const approvalFields = document.getElementById('approvalFields');
    const rejectionFields = document.getElementById('rejectionFields');
    const needsInfoFields = document.getElementById('needsInfoFields');
    
    // Hide all conditional fields first
    approvalFields.classList.add('hidden');
    rejectionFields.classList.add('hidden');
    needsInfoFields.classList.add('hidden');
    
    // Show the relevant fields based on status
    if (status === '{{ App\Models\LandlordConstructionRegistration::STATUS_APPROVED }}') {
        approvalFields.classList.remove('hidden');
        // Run landlord matching when approval fields are shown
        setTimeout(matchLandlordByProperty, 100);
    } else if (status === '{{ App\Models\LandlordConstructionRegistration::STATUS_REJECTED }}') {
        rejectionFields.classList.remove('hidden');
    } else if (status === '{{ App\Models\LandlordConstructionRegistration::STATUS_NEEDS_INFO }}') {
        needsInfoFields.classList.remove('hidden');
    }
}

// Enhanced function to match landlord based on applicant name
function matchLandlordByProperty() {
    const applicantName = '{{ $registration->name }}';
    const landlordSelect = document.getElementById('landlordSelect');
    const matchInfo = document.getElementById('landlordMatchInfo');
    
    if (!landlordSelect) {
        console.log('Landlord select not found');
        return;
    }
    
    console.log('Matching landlord for applicant:', applicantName);
    
    let bestMatch = null;
    let bestMatchScore = 0;
    let matchedLandlords = [];
    
    // Loop through landlord options
    Array.from(landlordSelect.options).forEach(option => {
        if (!option.value) return;
        
        const landlordName = option.text.split('(')[0].trim();
        const landlordEmail = option.getAttribute('data-email') || '';
        const landlordPhone = option.getAttribute('data-phone') || '';
        
        // Check if applicant name matches landlord name (case insensitive)
        if (landlordName.toLowerCase().trim() === applicantName.toLowerCase().trim()) {
            // Exact match - highest priority
            bestMatchScore = 100;
            bestMatch = option;
            matchedLandlords.push({
                option: option,
                score: 100,
                type: 'exact',
                name: landlordName
            });
        } else if (landlordName.toLowerCase().trim().includes(applicantName.toLowerCase().trim()) || 
                   applicantName.toLowerCase().trim().includes(landlordName.toLowerCase().trim())) {
            // Partial match
            const score = 80;
            if (score > bestMatchScore) {
                bestMatchScore = score;
                bestMatch = option;
            }
            matchedLandlords.push({
                option: option,
                score: score,
                type: 'partial',
                name: landlordName
            });
        } else {
            // Check if registration already has a landlord linked
            if (option.selected && option.value == '{{ $registration->landlord_id }}') {
                bestMatchScore = 90;
                bestMatch = option;
                matchedLandlords.push({
                    option: option,
                    score: 90,
                    type: 'linked',
                    name: landlordName
                });
            }
        }
    });
    
    console.log('Matched landlords:', matchedLandlords);
    
    if (bestMatch) {
        landlordSelect.value = bestMatch.value;
        
        let message = `<span class="text-success"><i class="fas fa-check-circle mr-1"></i> `;
        
        if (bestMatchScore === 100) {
            message += `Exact match found for applicant: <strong>"${applicantName}"</strong>`;
        } else if (bestMatchScore === 90) {
            message += `Landlord already linked to this registration: <strong>"${bestMatch.text.split('(')[0].trim()}"</strong>`;
        } else if (bestMatchScore === 80) {
            message += `Partial match found for applicant: <strong>"${applicantName}"</strong> → matched with "${bestMatch.text.split('(')[0].trim()}"`;
        } else {
            message += `Selected potential matching landlord for applicant: <strong>"${applicantName}"</strong>`;
        }
        
        // Add email/phone info if available
        const email = bestMatch.getAttribute('data-email');
        const phone = bestMatch.getAttribute('data-phone');
        if (email) {
            message += ` (${email})`;
        } else if (phone) {
            message += ` (${phone})`;
        }
        
        message += `</span>`;
        matchInfo.innerHTML = message;
        
        console.log('Selected landlord:', bestMatch.text);
    } else {
        // No match found - check if there's a landlord with same name in the system
        const allLandlords = Array.from(landlordSelect.options)
            .filter(o => o.value)
            .map(o => o.text.split('(')[0].trim());
        
        matchInfo.innerHTML = `
            <span style="color: var(--warning);">
                <i class="fas fa-exclamation-triangle mr-1"></i> 
                No matching landlord found for "<strong>${applicantName}</strong>". 
                Please select manually from ${allLandlords.length} available landlords.
                ${allLandlords.length > 0 ? `<br><small>Available: ${allLandlords.slice(0, 5).join(', ')}${allLandlords.length > 5 ? '...' : ''}</small>` : ''}
            </span>
        `;
        
        // If registration already has a landlord linked, select it
        if ('{{ $registration->landlord_id }}') {
            const linkedOption = Array.from(landlordSelect.options)
                .find(o => o.value == '{{ $registration->landlord_id }}');
            if (linkedOption) {
                landlordSelect.value = linkedOption.value;
                matchInfo.innerHTML = `
                    <span class="text-info">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Using previously linked landlord: <strong>"${linkedOption.text.split('(')[0].trim()}"</strong>
                    </span>
                `;
            }
        }
    }
}

// Function to handle registration plan selection
function handleRegistrationPlanChange() {
    const select = document.getElementById('registrationPlanSelect');
    if (!select) return;
    
    const selectedOption = select.options[select.selectedIndex];
    const zoneInput = document.getElementById('zoneInput');
    const sectionInput = document.getElementById('sectionInput');
    const planDetails = document.getElementById('planDetails');
    const planInfo = document.getElementById('planInfo');
    const planNotAvailableWarning = document.getElementById('planNotAvailableWarning');
    
    if (planNotAvailableWarning) {
        planNotAvailableWarning.classList.add('hidden');
    }
    
    if (selectedOption && selectedOption.value) {
        const zone = selectedOption.getAttribute('data-zone');
        const section = selectedOption.getAttribute('data-section');
        const estimated = selectedOption.getAttribute('data-estimated');
        const registered = selectedOption.getAttribute('data-registered');
        const available = selectedOption.getAttribute('data-available');
        const occupancy = selectedOption.getAttribute('data-occupancy');
        const status = selectedOption.getAttribute('data-status');
        const planName = selectedOption.text.trim().split(' - ')[0];
        
        if (selectedOption.disabled || (available && parseInt(available) <= 0)) {
            if (planNotAvailableWarning) {
                planNotAvailableWarning.classList.remove('hidden');
            }
            zoneInput.value = '';
            sectionInput.value = '';
        } else {
            if (zone) {
                zoneInput.value = zone;
            }
            if (section) {
                sectionInput.value = section;
            }
        }
        
        if (planInfo) {
            planInfo.innerHTML = `
                <div><strong>Plan:</strong> ${planName}</div>
                <div><strong>Estimated Houses:</strong> ${estimated || 'N/A'}</div>
                <div><strong>Registered:</strong> ${registered || '0'}</div>
                <div><strong>Available Spots:</strong> <span style="color: ${available && parseInt(available) > 0 ? 'var(--success)' : 'var(--danger)'};">${available || '0'}</span></div>
                <div><strong>Occupancy Rate:</strong> ${occupancy || '0'}%</div>
                <div><strong>Status:</strong> ${status ? status.charAt(0).toUpperCase() + status.slice(1) : 'N/A'}</div>
                ${zone ? `<div><strong>Zone:</strong> ${zone}</div>` : ''}
                ${section ? `<div><strong>Section:</strong> ${section}</div>` : ''}
            `;
        }
        
        if (planDetails) {
            planDetails.classList.remove('hidden');
        }
    } else {
        if (planDetails) {
            planDetails.classList.add('hidden');
        }
        if (planNotAvailableWarning) {
            planNotAvailableWarning.classList.add('hidden');
        }
        zoneInput.value = '{{ $registration->zone }}';
        sectionInput.value = '{{ $registration->section }}';
    }
}

function handlePropertyTypeChange() {
    const select = document.getElementById('propertyTypeSelect');
    if (!select) return;
    
    // Check if the select is disabled (vacant land)
    if (select.disabled) {
        return;
    }
    
    const selectedOption = select.options[select.selectedIndex];
    const isCustom = selectedOption?.getAttribute('data-custom') === 'true';
    const customField = document.getElementById('customPropertyTypeField');
    
    if (isCustom) {
        customField.classList.remove('hidden');
    } else {
        customField.classList.add('hidden');
    }
}

function toggleInvitationChannels() {
    const sendInvitation = document.querySelector('input[name="send_invitation"]')?.checked;
    const channels = document.getElementById('invitationChannels');
    
    if (sendInvitation) {
        channels.classList.remove('hidden');
    } else {
        channels.classList.add('hidden');
    }
}

function toggleTenantInvitationChannels() {
    const sendTenantInvitations = document.querySelector('input[name="send_tenant_invitations"]')?.checked;
    const channels = document.getElementById('tenantInvitationChannels');
    
    if (sendTenantInvitations) {
        channels.classList.remove('hidden');
    } else {
        channels.classList.add('hidden');
    }
}

// Tenant invitation functions
let currentTenantId = null;
let currentInvitationId = null;

function approveTenant(tenantId) {
    currentTenantId = tenantId;
    const form = document.getElementById('approveTenantForm');
    form.action = `{{ url('admin/construction-registrations/tenants') }}/${tenantId}/approve`;
    openModal('approveTenantModal');
}

function rejectTenant(tenantId) {
    currentTenantId = tenantId;
    const form = document.getElementById('rejectTenantForm');
    form.action = `{{ url('admin/construction-registrations/tenants') }}/${tenantId}/reject`;
    openModal('rejectTenantModal');
}

function sendTenantInvitation(tenantId) {
    currentTenantId = tenantId;
    const form = document.getElementById('sendTenantInvitationForm');
    form.action = `{{ url('admin/construction-registrations/tenants') }}/${tenantId}/send-invitation`;
    openModal('sendTenantInvitationModal');
}

function resendInvitation(invitationId) {
    if (confirm('Are you sure you want to resend this invitation?')) {
        fetch(`{{ url('admin/construction-registrations/invitations') }}/${invitationId}/resend`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Invitation resent successfully', 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showToast('Failed to resend invitation: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Failed to resend invitation', 'error');
        });
    }
}

function copyInvitationLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        showToast('Invitation link copied to clipboard', 'success');
    }).catch(() => {
        showToast('Failed to copy link', 'error');
    });
}

function migrateTenantData() {
    if (confirm('This will create individual tenant records from the JSON data. Continue?')) {
        fetch('{{ route("admin.construction-registrations.migrate-tenants", $registration) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Tenant data migrated successfully', 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showToast('Migration failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Migration failed', 'error');
        });
    }
}

let documentToDelete = null;

function deleteDocument(documentId) {
    documentToDelete = documentId;
    document.getElementById('deleteDocumentForm').action = `{{ url('admin/construction-registrations/documents') }}/${documentId}`;
    openModal('deleteDocumentModal');
}

function confirmDeleteDocument() {
    document.getElementById('deleteDocumentForm').submit();
}

// Notes
function openNoteModal() {
    openModal('noteModal');
}

let noteToDelete = null;

function deleteNote(noteId) {
    noteToDelete = noteId;
    document.getElementById('deleteNoteForm').action = `{{ url('admin/construction-registrations/notes') }}/${noteId}`;
    openModal('deleteNoteModal');
}

function confirmDeleteNote() {
    document.getElementById('deleteNoteForm').submit();
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Toast notification
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Close modal on overlay click
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
        document.querySelectorAll('.modal').forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check URL hash for tab
    if (window.location.hash) {
        const tab = window.location.hash.substring(1);
        const tabLink = document.querySelector(`a[href="#${tab}"]`);
        if (tabLink) {
            tabLink.click();
        }
    }
    
    // Initialize status change handlers
    handleStatusChange();
    
    // Check if there's a selected registration plan on page load
    const planSelect = document.getElementById('registrationPlanSelect');
    if (planSelect && planSelect.value) {
        handleRegistrationPlanChange();
    }
    
    // Handle pre-selected property type
    const propertyTypeSelect = document.getElementById('propertyTypeSelect');
    if (propertyTypeSelect && propertyTypeSelect.value) {
        const event = new Event('change');
        propertyTypeSelect.dispatchEvent(event);
    }
    
    // Check if property type is disabled (vacant land)
    if (propertyTypeSelect && propertyTypeSelect.disabled) {
        console.log('Property type is disabled for vacant land');
    }
});
</script>

<style>
/* Tab styles */
.tab-link {
    transition: all 0.2s ease;
    cursor: pointer;
}

.tab-link:hover {
    background-color: rgba(var(--primary-rgb), 0.05) !important;
    color: var(--primary) !important;
}

/* Action button styles */
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Status banner */
.status-banner {
    transition: all 0.3s ease;
}

/* Document cards */
.document-card {
    transition: all 0.2s ease;
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Tenant cards */
.tenant-card {
    transition: all 0.2s ease;
}

.tenant-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

/* Timeline styles */
.timeline-item {
    position: relative;
}

.timeline-item:last-child .timeline-line {
    display: none;
}

/* Modal styles */
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
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background-color: var(--card-bg);
    z-index: 10;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    position: sticky;
    bottom: 0;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Toast container */
#toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 10000;
}

/* Form elements */
.form-input {
    width: 100%;
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background-color: var(--card-bg);
    color: var(--text-primary);
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-input[readonly] {
    background-color: rgba(var(--secondary-rgb), 0.05);
    cursor: not-allowed;
}

.form-input:disabled {
    background-color: rgba(var(--secondary-rgb), 0.05);
    cursor: not-allowed;
    opacity: 0.8;
}

/* Disabled options styling */
select option:disabled {
    color: var(--text-secondary);
    background-color: rgba(var(--secondary-rgb), 0.1);
}

/* Alert styles */
.alert {
    padding: 1rem;
    border-radius: 0.5rem;
}

.alert-info {
    background-color: rgba(var(--info-rgb), 0.1);
    border: 1px solid rgba(var(--info-rgb), 0.2);
}

/* Responsive */
@media (max-width: 768px) {
    .modal-container {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
}
</style>
@endsection