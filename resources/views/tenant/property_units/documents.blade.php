{{-- resources/views/tenant/property_units/documents.blade.php --}}
@php
    // Get authenticated user
    $user = auth()->user();
    
    // Ensure user is a tenant
    if (!$user->isTenant()) {
        abort(403, 'This page is only available for tenants.');
    }
    
    // Get the tenant's assigned unit
    $unit = $user->propertyUnits()
        ->where('tenant_status', \App\Models\PropertyUnit::TENANT_STATUS_APPROVED)
        ->with(['property', 'currentLease'])
        ->first();
    
    if (!$unit) {
        abort(404, 'No unit assigned to this tenant.');
    }
    
    // Get documents from unit (tenant_documents field)
    $documents = $unit->tenant_documents ?? [];
    
    // Get lease documents
    $leaseDocuments = [];
    if ($unit->currentLease) {
        $leaseDocuments = $unit->currentLease->documents ?? [];
    }
    
    // Get uploaded documents from tenant
    $uploadedDocuments = \App\Models\Document::where('user_id', $user->id)
        ->where('documentable_type', 'App\Models\PropertyUnit')
        ->where('documentable_id', $unit->id)
        ->orWhere(function($query) use ($user, $unit) {
            $query->where('user_id', $user->id)
                  ->where('documentable_type', 'App\Models\RentalAgreement')
                  ->whereHas('documentable', function($q) use ($unit) {
                      $q->where('unit_id', $unit->id);
                  });
        })
        ->orderBy('created_at', 'desc')
        ->get();
    
    // Allowed document types
    $allowedDocumentTypes = [
        'id_proof' => 'ID Proof (National ID/Passport)',
        'proof_of_income' => 'Proof of Income',
        'employment_letter' => 'Employment Letter',
        'bank_statement' => 'Bank Statement',
        'reference_letter' => 'Reference Letter',
        'utility_bill' => 'Utility Bill',
        'rent_receipt' => 'Rent Receipt',
        'lease_agreement' => 'Lease Agreement',
        'maintenance_request' => 'Maintenance Request',
        'other' => 'Other Document'
    ];
    
    // Max file size (in MB)
    $maxFileSize = config('filesystems.max_upload_size', 5); // MB
    $maxFileSizeBytes = $maxFileSize * 1024 * 1024;
    
    // Allowed file extensions
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 
                     'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    // Page title
    $pageTitle = 'My Documents - ' . $unit->property->property_name . ' - Unit ' . $unit->unit_number;
    
    // Check for messages
    $successMessage = session('success');
    $errorMessage = session('error');
    $validationErrors = session('errors');
    
    // Helper function for file size formatting
    function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    // Calculate total size and format it
    $totalSize = 0;
    foreach($uploadedDocuments as $document) {
        $totalSize += $document->size ?? 0;
    }
    $formattedSize = formatFileSize($totalSize);
    $usagePercentage = min(($totalSize / ($maxFileSizeBytes * 10)) * 100, 100);
@endphp

@extends('layouts.tenant')

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-folder-open text-2xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-folder-open mr-2" style="color: var(--primary);"></i> 
                        My Documents
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-building mr-2"></i>
                        <span>{{ $unit->property->property_name }} - Unit {{ $unit->unit_number }}</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-file-alt mr-1"></i>
                        <span>{{ count($uploadedDocuments) + count($leaseDocuments) + count($documents) }} documents</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('tenant.dashboard') }}" 
                   class="inline-flex items-center px-4 py-2.5 rounded-lg font-medium btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Success!</span>
            <span class="ml-2">{{ $successMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Error Message -->
    @if($errorMessage)
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="font-bold">Error!</span>
            <span class="ml-2">{{ $errorMessage }}</span>
        </div>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Validation Errors -->
    @if($validationErrors)
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span class="font-bold">Please fix the following errors:</span>
        </div>
        <ul class="mt-2 ml-6 list-disc">
            @foreach ($validationErrors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Unit Information Card -->
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-2"></i> Unit Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Property</div>
                    <div class="font-semibold flex items-center" style="color: var(--primary);">
                        <i class="fas fa-building mr-2"></i> {{ $unit->property->property_name }}
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Unit</div>
                    <div class="font-semibold flex items-center" style="color: var(--success);">
                        <i class="fas fa-hashtag mr-2"></i> {{ $unit->unit_number }}
                        @if($unit->unit_name)
                            <span class="ml-2 text-sm">({{ $unit->unit_name }})</span>
                        @endif
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Lease Status</div>
                    <div class="font-bold flex items-center" style="color: var(--warning);">
                        <i class="fas fa-file-contract mr-2"></i>
                        {{ $unit->currentLease ? 'Active' : 'No Active Lease' }}
                    </div>
                </div>
                
                <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                    <div class="text-sm mb-1" style="color: var(--text-secondary);">Documents</div>
                    <div class="font-semibold flex items-center" style="color: var(--info);">
                        <i class="fas fa-folder-open mr-2"></i> 
                        {{ count($uploadedDocuments) + count($leaseDocuments) + count($documents) }} total
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Upload Document Form -->
        <div class="lg:col-span-2">
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-6 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-cloud-upload-alt mr-2"></i> Upload New Document
                    </h3>

                    {{-- UPDATED: Using the shared document upload route --}}
                    <form action="{{ route('tenant.documents.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Document Type -->
                            <div>
                                <label for="document_type" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-file-alt mr-1"></i> Document Type <span class="text-red-500">*</span>
                                </label>
                                <select id="document_type" name="document_type" required
                                        class="index-custom-dropdown dark-dropdown w-full">
                                    <option value="">Select Document Type</option>
                                    @foreach($allowedDocumentTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- Document Name -->
                            <div>
                                <label for="document_name" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-font mr-1"></i> Document Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="document_name" name="document_name" required
                                       class="index-custom-input w-full"
                                       placeholder="e.g., January Rent Receipt, Maintenance Request Form">
                            </div>
                        </div>
                        
                        <!-- File Upload -->
                        <div class="mt-8">
                            <label class="block text-sm font-medium mb-4" style="color: var(--text-primary);">
                                <i class="fas fa-file-upload mr-1"></i> Document File <span class="text-red-500">*</span>
                            </label>
                            
                            <div class="border-2 border-dashed rounded-lg p-8 transition-colors"
                                 style="border-color: var(--primary); background-color: rgba(var(--primary-rgb), 0.02);"
                                 id="documentDropzone">
                                <div class="text-center">
                                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <i class="fas fa-cloud-upload-alt text-3xl"></i>
                                    </div>
                                    <p class="text-lg font-medium mb-2" style="color: var(--text-primary);">
                                        Upload Document
                                    </p>
                                    <p class="text-sm mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                                        Drag & drop files here or click to browse
                                    </p>
                                    
                                    <input type="file" 
                                           id="document_file" 
                                           name="document_file" 
                                           required
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                           class="hidden">
                                    <label for="document_file" 
                                           class="inline-flex items-center px-6 py-3 rounded-lg font-medium cursor-pointer btn-primary">
                                        <i class="fas fa-folder-open mr-3"></i> Browse Files
                                    </label>
                                    
                                    <p class="text-xs mt-4" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Allowed: PDF, JPG, PNG, DOC, DOCX (Max: {{ $maxFileSize }}MB each)
                                    </p>
                                </div>
                                
                                <div id="fileInfo" class="mt-6 p-4 rounded-lg hidden" 
                                     style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-file text-success mr-3 text-xl"></i>
                                            <div>
                                                <p class="font-medium" id="fileName" style="color: var(--text-primary);"></p>
                                                <p class="text-xs" id="fileSize" style="color: var(--text-secondary);"></p>
                                            </div>
                                        </div>
                                        <button type="button" 
                                                onclick="removeFile()"
                                                class="text-red-500 hover:text-red-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mt-8">
                            <label for="description" class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-align-left mr-1"></i> Description (Optional)
                            </label>
                            <textarea id="description" name="description" rows="3"
                                      class="index-custom-textarea w-full"
                                      placeholder="Add any additional details about this document..."></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="mt-8 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-3 rounded-lg font-medium text-white btn-primary"
                                    id="submitBtn">
                                <i class="fas fa-upload mr-2"></i> Upload Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documents Tabs Section -->
            <div class="card mt-6">
                <div class="p-0">
                    <!-- Tabs Navigation -->
                    <div class="border-b" style="border-color: var(--border-color);">
                        <nav class="flex -mb-px" id="documentsTabs">
                            <button type="button" 
                                    data-tab="all-documents"
                                    class="tab-button py-4 px-6 text-sm font-medium border-b-2 border-transparent flex items-center active"
                                    onclick="switchTab('all-documents')">
                                <i class="fas fa-file-alt mr-2"></i> All Documents
                                <span class="ml-2 px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ count($documents) + count($leaseDocuments) + count($uploadedDocuments) }}
                                </span>
                            </button>
                            <button type="button"
                                    data-tab="uploaded-documents"
                                    class="tab-button py-4 px-6 text-sm font-medium border-b-2 border-transparent flex items-center"
                                    onclick="switchTab('uploaded-documents')">
                                <i class="fas fa-cloud-upload-alt mr-2"></i> My Uploads
                                <span class="ml-2 px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    {{ count($uploadedDocuments) }}
                                </span>
                            </button>
                            <button type="button"
                                    data-tab="lease-documents"
                                    class="tab-button py-4 px-6 text-sm font-medium border-b-2 border-transparent flex items-center"
                                    onclick="switchTab('lease-documents')">
                                <i class="fas fa-file-contract mr-2"></i> Lease Documents
                                <span class="ml-2 px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    {{ count($leaseDocuments) }}
                                </span>
                            </button>
                            <button type="button"
                                    data-tab="application-documents"
                                    class="tab-button py-4 px-6 text-sm font-medium border-b-2 border-transparent flex items-center"
                                    onclick="switchTab('application-documents')">
                                <i class="fas fa-clipboard-list mr-2"></i> Application Docs
                                <span class="ml-2 px-2 py-1 rounded-full text-xs"
                                      style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    {{ count($documents) }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- All Documents Tab -->
                        <div id="all-documents-tab" class="tab-content">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-file-alt mr-2"></i> All Documents
                                </h3>
                                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                                    All documents related to your tenancy, including uploaded files, lease documents, and application documents.
                                </p>
                                
                                @if(count($documents) + count($leaseDocuments) + count($uploadedDocuments) === 0)
                                    <div class="text-center py-12">
                                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-folder-open text-3xl"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No documents found</h3>
                                        <p class="text-sm" style="color: var(--text-secondary);">Start by uploading your first document above.</p>
                                    </div>
                                @else
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Uploaded Documents -->
                                        @foreach($uploadedDocuments as $document)
                                            <div class="document-card" data-type="uploaded">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex items-center">
                                                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                            <i class="fas fa-cloud-upload-alt text-xl"></i>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold" style="color: var(--text-primary);">{{ $document->name }}</p>
                                                            <p class="text-xs mt-1 capitalize" style="color: var(--text-secondary);">
                                                                {{ str_replace('_', ' ', $document->type ?? 'other') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <span class="text-xs px-2 py-1 rounded-full"
                                                          style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                        Uploaded
                                                    </span>
                                                </div>
                                                
                                                @if($document->description)
                                                    <p class="text-sm mb-4" style="color: var(--text-secondary);">{{ $document->description }}</p>
                                                @endif
                                                
                                                <div class="flex items-center justify-between text-sm mb-4" style="color: var(--text-secondary);">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        {{ $document->created_at->format('M d, Y') }}
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-weight mr-1"></i>
                                                        {{ $document->size ? formatFileSize($document->size) : '0 Bytes' }}
                                                    </span>
                                                </div>
                                                
                                                <div class="flex space-x-2">
                                                    {{-- UPDATED: Use property-units documents download route --}}
                                                    <a href="{{ route('tenant.property-units.documents.download', ['id' => $unit->id, 'path' => $document->path, 'disk' => $document->disk ?? 'local']) }}" 
                                                       class="flex-1 inline-flex justify-center items-center px-3 py-2 rounded-lg font-medium btn-secondary">
                                                        <i class="fas fa-download mr-2"></i> Download
                                                    </a>
                                                    <button type="button" 
                                                            onclick="deleteDocument('{{ $document->id }}')"
                                                            class="inline-flex items-center px-3 py-2 rounded-lg font-medium"
                                                            style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                        <i class="fas fa-trash mr-2"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                        
                                        <!-- Lease Documents -->
                                        @foreach($leaseDocuments as $index => $document)
                                            <div class="document-card" data-type="lease">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex items-center">
                                                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                            <i class="fas fa-file-contract text-xl"></i>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold" style="color: var(--text-primary);">
                                                                {{ $document['name'] ?? 'Lease Agreement' }}
                                                            </p>
                                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                {{ $unit->currentLease->start_date->format('M d, Y') }} - {{ $unit->currentLease->end_date->format('M d, Y') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <span class="text-xs px-2 py-1 rounded-full"
                                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        Lease
                                                    </span>
                                                </div>
                                                
                                                <div class="flex items-center justify-between text-sm mb-4" style="color: var(--text-secondary);">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-file-signature mr-1"></i>
                                                        @if($unit->currentLease->tenant_signed_at)
                                                            Signed {{ $unit->currentLease->tenant_signed_at->format('M d, Y') }}
                                                        @else
                                                            Not Signed
                                                        @endif
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-money-bill-wave mr-1"></i>
                                                        GHS {{ number_format($unit->currentLease->monthly_rent, 2) }}/mo
                                                    </span>
                                                </div>
                                                
                                                <div class="flex space-x-2">
                                                    {{-- UPDATED: Use property-units.lease-details route --}}
                                                    <a href="{{ route('tenant.property-units.lease-details', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                                                       class="flex-1 inline-flex justify-center items-center px-3 py-2 rounded-lg font-medium"
                                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                                        <i class="fas fa-eye mr-2"></i> View
                                                    </a>
                                                    @if(!$unit->currentLease->tenant_signed_at)
                                                        {{-- UPDATED: Use property-units.tenant-sign-lease route --}}
                                                        <a href="{{ route('tenant.property-units.tenant-sign-lease', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                                                           class="flex-1 inline-flex justify-center items-center px-3 py-2 rounded-lg font-medium btn-primary">
                                                            <i class="fas fa-signature mr-2"></i> Sign
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                        
                                        <!-- Application Documents -->
                                        @foreach($documents as $index => $document)
                                            <div class="document-card" data-type="application">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex items-center">
                                                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3"
                                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                            @php
                                                                $icon = 'fa-file-alt';
                                                                if (str_contains(strtolower($document['name'] ?? ''), 'id')) {
                                                                    $icon = 'fa-id-card';
                                                                } elseif (str_contains(strtolower($document['name'] ?? ''), 'bank')) {
                                                                    $icon = 'fa-university';
                                                                } elseif (str_contains(strtolower($document['name'] ?? ''), 'employ')) {
                                                                    $icon = 'fa-briefcase';
                                                                }
                                                            @endphp
                                                            <i class="fas {{ $icon }} text-xl"></i>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold" style="color: var(--text-primary);">
                                                                {{ $document['name'] ?? 'Document ' . ($index + 1) }}
                                                            </p>
                                                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                                Application Document
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <span class="text-xs px-2 py-1 rounded-full"
                                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        Application
                                                    </span>
                                                </div>
                                                
                                                <div class="flex items-center justify-between text-sm mb-4" style="color: var(--text-secondary);">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        {{ isset($document['uploaded_at']) ? \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') : 'N/A' }}
                                                    </span>
                                                    <span class="capitalize">
                                                        {{ $document['type'] ?? 'unknown' }}
                                                    </span>
                                                </div>
                                                
                                                <div>
                                                    {{-- UPDATED: Using property-units documents download route with index --}}
                                                    <button type="button" 
                                                            onclick="downloadApplicationDocument('{{ $index }}')"
                                                            class="w-full inline-flex justify-center items-center px-4 py-2 rounded-lg font-medium"
                                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                        <i class="fas fa-download mr-2"></i> Download Document
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Uploaded Documents Tab -->
                        <div id="uploaded-documents-tab" class="tab-content hidden">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-cloud-upload-alt mr-2"></i> My Uploaded Documents
                                </h3>
                                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                                    Documents you have uploaded to the system.
                                </p>
                                
                                @if(count($uploadedDocuments) === 0)
                                    <div class="text-center py-12">
                                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-cloud-upload-alt text-3xl"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No uploaded documents</h3>
                                        <p class="text-sm" style="color: var(--text-secondary);">Upload your first document using the form above.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y" style="border-color: var(--border-color);">
                                            <thead>
                                                <tr style="background-color: rgba(var(--bg-input), 0.5);">
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                                        style="color: var(--text-secondary);">
                                                        Document
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                                        style="color: var(--text-secondary);">
                                                        Type
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                                        style="color: var(--text-secondary);">
                                                        Uploaded
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                                        style="color: var(--text-secondary);">
                                                        Size
                                                    </th>
                                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                                        style="color: var(--text-secondary);">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y" style="border-color: var(--border-color);">
                                                @foreach($uploadedDocuments as $document)
                                                    <tr class="hover:bg-opacity-50 transition-colors" 
                                                        style="background-color: rgba(var(--bg-input), 0.2);">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="flex items-center">
                                                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg mr-3"
                                                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                                    <i class="fas fa-file"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-sm font-medium" style="color: var(--text-primary);">
                                                                        {{ $document->name }}
                                                                    </div>
                                                                    @if($document->description)
                                                                        <div class="text-xs" style="color: var(--text-secondary);">
                                                                            {{ Str::limit($document->description, 50) }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <span class="px-2 py-1 text-xs font-medium rounded-full capitalize"
                                                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                                {{ str_replace('_', ' ', $document->type) }}
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--text-secondary);">
                                                            {{ $document->created_at->format('M d, Y') }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm" style="color: var(--text-secondary);">
                                                            {{ $document->size ? formatFileSize($document->size) : '0 Bytes' }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex space-x-3">
                                                                {{-- UPDATED: Use property-units documents download route --}}
                                                                <a href="{{ route('tenant.property-units.documents.download', ['id' => $unit->id, 'path' => $document->path, 'disk' => $document->disk ?? 'local']) }}" 
                                                                   class="hover:underline" style="color: var(--primary);">
                                                                    <i class="fas fa-download mr-1"></i> Download
                                                                </a>
                                                                <button type="button" 
                                                                        onclick="deleteDocument('{{ $document->id }}')"
                                                                        class="hover:underline" style="color: var(--danger);">
                                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Lease Documents Tab -->
                        <div id="lease-documents-tab" class="tab-content hidden">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-file-contract mr-2"></i> Lease Documents
                                </h3>
                                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                                    Official lease agreement and related documents.
                                </p>
                                
                                @if(count($leaseDocuments) === 0)
                                    <div class="text-center py-12">
                                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-file-contract text-3xl"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No lease documents</h3>
                                        <p class="text-sm" style="color: var(--text-secondary);">Your lease agreement will appear here once available.</p>
                                    </div>
                                @else
                                    <div class="space-y-6">
                                        @foreach($leaseDocuments as $index => $document)
                                            <div class="p-6 rounded-lg" 
                                                 style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                                <div class="flex items-start justify-between mb-6">
                                                    <div class="flex items-center">
                                                        <div class="w-14 h-14 rounded-full flex items-center justify-center mr-4"
                                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                            <i class="fas fa-file-contract text-2xl"></i>
                                                        </div>
                                                        <div>
                                                            <h4 class="text-lg font-semibold" style="color: var(--text-primary);">
                                                                {{ $document['name'] ?? 'Lease Agreement' }}
                                                            </h4>
                                                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                                                {{ $unit->currentLease->start_date->format('M d, Y') }} - {{ $unit->currentLease->end_date->format('M d, Y') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                                              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                            {{ $unit->currentLease->status ?? 'Active' }}
                                                        </span>
                                                        @if($unit->currentLease->tenant_signed_at)
                                                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                                                  style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                                Signed
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                                    <div class="p-3 rounded-lg text-center"
                                                         style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                                                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Monthly Rent</p>
                                                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                                                            GHS {{ number_format($unit->currentLease->monthly_rent, 2) }}
                                                        </p>
                                                    </div>
                                                    <div class="p-3 rounded-lg text-center"
                                                         style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                                                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Security Deposit</p>
                                                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                                                            GHS {{ number_format($unit->currentLease->security_deposit, 2) }}
                                                        </p>
                                                    </div>
                                                    <div class="p-3 rounded-lg text-center"
                                                         style="background-color: rgba(0,0,0,0.02); border: 1px solid var(--border-color);">
                                                        <p class="text-sm mb-1" style="color: var(--text-secondary);">Duration</p>
                                                        <p class="text-xl font-bold" style="color: var(--text-primary);">
                                                            {{ $unit->currentLease->duration_months }} months
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex flex-wrap gap-3">
                                                    {{-- UPDATED: Use property-units.lease-details route --}}
                                                    <a href="{{ route('tenant.property-units.lease-details', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                                                       class="inline-flex items-center px-4 py-2.5 rounded-lg font-medium"
                                                       style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                                        <i class="fas fa-eye mr-2"></i> View Lease
                                                    </a>
                                                    {{-- UPDATED: Use property-units.lease-download-pdf route --}}
                                                    <a href="{{ route('tenant.property-units.lease-download-pdf', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                                                       class="inline-flex items-center px-4 py-2.5 rounded-lg font-medium btn-secondary">
                                                        <i class="fas fa-download mr-2"></i> Download PDF
                                                    </a>
                                                    @if(!$unit->currentLease->tenant_signed_at)
                                                        {{-- UPDATED: Use property-units.tenant-sign-lease route --}}
                                                        <a href="{{ route('tenant.property-units.tenant-sign-lease', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                                                           class="inline-flex items-center px-4 py-2.5 rounded-lg font-medium text-white btn-primary">
                                                            <i class="fas fa-signature mr-2"></i> Sign Lease
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Application Documents Tab -->
                        <div id="application-documents-tab" class="tab-content hidden">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                                    <i class="fas fa-clipboard-list mr-2"></i> Application Documents
                                </h3>
                                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                                    Documents submitted during your application process.
                                </p>
                                
                                @if(count($documents) === 0)
                                    <div class="text-center py-12">
                                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-clipboard-list text-3xl"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No application documents</h3>
                                        <p class="text-sm" style="color: var(--text-secondary);">Your application documents will appear here.</p>
                                    </div>
                                @else
                                    <div class="space-y-4">
                                        @foreach($documents as $index => $document)
                                            <div class="p-4 rounded-lg flex items-center justify-between hover:shadow-sm transition-all"
                                                 style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        @php
                                                            $icon = 'fa-file-alt';
                                                            if (str_contains(strtolower($document['name'] ?? ''), 'id')) {
                                                                $icon = 'fa-id-card';
                                                            } elseif (str_contains(strtolower($document['name'] ?? ''), 'bank')) {
                                                                $icon = 'fa-university';
                                                            } elseif (str_contains(strtolower($document['name'] ?? ''), 'employ')) {
                                                                $icon = 'fa-briefcase';
                                                            }
                                                        @endphp
                                                        <i class="fas {{ $icon }}"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-medium" style="color: var(--text-primary);">
                                                            {{ $document['name'] ?? 'Document ' . ($index + 1) }}
                                                        </h4>
                                                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                                            Uploaded: {{ isset($document['uploaded_at']) ? \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') : 'N/A' }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div>
                                                    {{-- UPDATED: Using property-units documents download route with index --}}
                                                    <button type="button" 
                                                            onclick="downloadApplicationDocument('{{ $index }}')"
                                                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium"
                                                            style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                                        <i class="fas fa-download mr-1"></i> Download
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Information & Statistics -->
        <div class="space-y-6">
            <!-- Storage Usage -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-database mr-2"></i> Storage Usage
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-2">
                                <span style="color: var(--text-secondary);">Used: {{ $formattedSize }}</span>
                                <span style="color: var(--text-secondary);">Limit: {{ $maxFileSize }}MB</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: {{ $usagePercentage }}%"></div>
                            </div>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                You're using {{ number_format($usagePercentage, 1) }}% of your document storage space.
                            </p>
                        </div>
                        
                        <div class="p-3 rounded-lg mt-4" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <p class="text-sm font-medium mb-2 flex items-center" style="color: var(--info);">
                                <i class="fas fa-info-circle mr-2"></i> Storage Tips
                            </p>
                            <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>Compress large files before uploading</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>Delete old, unnecessary documents</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-xs mr-2 mt-0.5" style="color: var(--success);"></i>
                                    <span>Use PDF format for documents when possible</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Categories -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-folder mr-2"></i> Document Categories
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-opacity-50 transition-colors"
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.1);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">My Uploads</span>
                            </div>
                            <span class="text-sm font-bold" style="color: var(--info);">{{ count($uploadedDocuments) }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-opacity-50 transition-colors"
                             style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.1);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-file-contract"></i>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Lease Documents</span>
                            </div>
                            <span class="text-sm font-bold" style="color: var(--success);">{{ count($leaseDocuments) }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-opacity-50 transition-colors"
                             style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.1);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Application Docs</span>
                            </div>
                            <span class="text-sm font-bold" style="color: var(--warning);">{{ count($documents) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2"></i> Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        {{-- UPDATED: Using the shared upload route --}}
                        <a href="#" onclick="document.getElementById('uploadForm').scrollIntoView({behavior: 'smooth'}); return false;" 
                           class="flex items-center justify-between p-3 rounded-lg hover:shadow-sm transition-all"
                           style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Upload New Document</span>
                            </div>
                            <i class="fas fa-arrow-right" style="color: var(--primary);"></i>
                        </a>
                        
                        @if($unit->currentLease)
                            {{-- UPDATED: Use property-units.lease-details route --}}
                            <a href="{{ route('tenant.property-units.lease-details', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                               class="flex items-center justify-between p-3 rounded-lg hover:shadow-sm transition-all"
                               style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-file-contract"></i>
                                    </div>
                                    <span class="text-sm font-medium" style="color: var(--text-primary);">View Lease Agreement</span>
                                </div>
                                <i class="fas fa-arrow-right" style="color: var(--success);"></i>
                            </a>
                            
                            @if(!$unit->currentLease->tenant_signed_at)
                                {{-- UPDATED: Use property-units.tenant-sign-lease route --}}
                                <a href="{{ route('tenant.property-units.tenant-sign-lease', ['id' => $unit->id, 'leaseId' => $unit->currentLease->id]) }}" 
                                   class="flex items-center justify-between p-3 rounded-lg hover:shadow-sm transition-all"
                                   style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                             style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                            <i class="fas fa-signature"></i>
                                        </div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">Sign Lease Agreement</span>
                                    </div>
                                    <i class="fas fa-arrow-right" style="color: var(--warning);"></i>
                                </a>
                            @endif
                        @endif
                        
                        <a href="{{ route('tenant.dashboard') }}" 
                           class="flex items-center justify-between p-3 rounded-lg hover:shadow-sm transition-all"
                           style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-home"></i>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">Back to Dashboard</span>
                            </div>
                            <i class="fas fa-arrow-right" style="color: var(--info);"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Help & Support -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-question-circle mr-2"></i> Help & Support
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" 
                             style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <p class="text-sm font-medium mb-2 flex items-center" style="color: var(--info);">
                                <i class="fas fa-info-circle mr-2"></i> Need Help?
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                If you're having trouble uploading or accessing documents, please contact support.
                            </p>
                        </div>
                        
                        <div class="p-3 rounded-lg" 
                             style="background-color: rgba(var(--warning-rgb), 0.05); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <p class="text-sm font-medium mb-2 flex items-center" style="color: var(--warning);">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Important
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Keep your important documents safe. Always download and backup critical documents.
                            </p>
                        </div>
                        
                        {{-- UPDATED: Contact landlord route --}}
                        <div class="flex space-x-2">
                            <a href="{{ route('tenant.property-units.contact-landlord.form', $unit->id) }}" 
                               class="flex-1 inline-flex justify-center items-center px-3 py-2 rounded-lg text-sm font-medium btn-secondary">
                                <i class="fas fa-headset mr-2"></i> Contact Landlord
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="hideDeleteModal()"></div>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-300"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                            Delete Document
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Are you sure you want to delete this document? This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                {{-- UPDATED: Need to create a specific route for document deletion --}}
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Delete
                    </button>
                </form>
                <button type="button" 
                        onclick="hideDeleteModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Helper function for file size formatting (JavaScript version)
function formatFileSizeJS(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-primary', 'text-primary');
        button.classList.add('border-transparent');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    
    // Add active class to selected tab button
    const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
    activeButton.classList.remove('border-transparent');
    activeButton.classList.add('active', 'border-primary', 'text-primary');
}

// File upload handling
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('document_file');
    const dropzone = document.getElementById('documentDropzone');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    if (fileInput && dropzone) {
        // Click on dropzone to trigger file input
        dropzone.addEventListener('click', function(e) {
            if (e.target !== fileInput && e.target.type !== 'file') {
                fileInput.click();
            }
        });
        
        // File input change
        fileInput.addEventListener('change', function(e) {
            handleFileSelection(e.target.files[0]);
        });
        
        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropzone.style.borderColor = 'var(--primary)';
            dropzone.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
        }
        
        function unhighlight() {
            dropzone.style.borderColor = 'var(--primary)';
            dropzone.style.backgroundColor = 'rgba(var(--primary-rgb), 0.02)';
        }
        
        dropzone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length) {
                handleFileSelection(files[0]);
            }
        });
    }
    
    function handleFileSelection(file) {
        if (file) {
            // Validate file size
            const maxSize = {{ $maxFileSizeBytes }};
            if (file.size > maxSize) {
                alert(`File size exceeds maximum allowed size of {{ $maxFileSize }}MB`);
                document.getElementById('document_file').value = '';
                return;
            }
            
            // Validate file type
            const allowedTypes = @json($allowedExtensions);
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (!allowedTypes.includes(fileExtension)) {
                alert(`File type not allowed. Allowed types: ${allowedTypes.join(', ')}`);
                document.getElementById('document_file').value = '';
                return;
            }
            
            // Show file info
            fileInfo.classList.remove('hidden');
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSizeJS(file.size);
        }
    }
    
    // Initialize first tab
    switchTab('all-documents');
    
    // Auto-hide messages after 5 seconds
    setTimeout(() => {
        const messages = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100');
        messages.forEach(msg => {
            msg.style.display = 'none';
        });
    }, 5000);
    
    // Load document statistics
    loadDocumentStatistics();
    
    // Load storage usage
    loadStorageUsage();
});

// Remove file
function removeFile() {
    document.getElementById('document_file').value = '';
    document.getElementById('fileInfo').classList.add('hidden');
}

// Delete document modal
let documentToDelete = null;

function deleteDocument(documentId) {
    documentToDelete = documentId;
    // UPDATED: Use the correct tenant route for document deletion
    document.getElementById('deleteForm').action = `/tenant/property-units/{{ $unit->id }}/documents/${documentId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    documentToDelete = null;
}

// Download application document
function downloadApplicationDocument(index) {
    // UPDATED: Use the correct tenant route
    window.location.href = `/tenant/property-units/{{ $unit->id }}/documents/application/${index}/download`;
}

// Form submission with validation
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('document_file');
    const documentType = document.getElementById('document_type');
    const documentName = document.getElementById('document_name');
    
    // Basic validation
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please select a file to upload.');
        return false;
    }
    
    if (!documentType.value) {
        e.preventDefault();
        alert('Please select a document type.');
        return false;
    }
    
    if (!documentName.value.trim()) {
        e.preventDefault();
        alert('Please enter a document name.');
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Uploading...';
    
    return true;
});

// AJAX validation before upload
function validateUploadBeforeSubmit() {
    const fileInput = document.getElementById('document_file');
    const documentType = document.getElementById('document_type');
    
    if (!fileInput.files.length || !documentType.value) {
        return false;
    }
    
    const formData = new FormData();
    formData.append('document_file', fileInput.files[0]);
    formData.append('document_type', documentType.value);
    
    fetch('/api/documents/validate-upload', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Validation passed, submit the form
            document.getElementById('uploadForm').submit();
        } else {
            // Show validation errors
            const submitBtn = document.querySelector('#uploadForm button[type="submit"]');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload mr-2"></i> Upload Document';
            
            if (data.errors && data.errors.document_file) {
                alert(data.errors.document_file[0]);
            } else {
                alert('File validation failed. Please try again.');
            }
        }
    })
    .catch(error => {
        console.error('Validation error:', error);
        const submitBtn = document.querySelector('#uploadForm button[type="submit"]');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-upload mr-2"></i> Upload Document';
        alert('Validation failed. Please try again.');
    });
}

// Load document statistics via AJAX
function loadDocumentStatistics() {
    fetch(`/api/property-units/{{ $unit->id }}/document-statistics`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDocumentStatsUI(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to load document statistics:', error);
        });
}

// Update document statistics UI
function updateDocumentStatsUI(stats) {
    // Update total document count
    const totalDocsElement = document.querySelector('[data-stat="total-documents"]');
    if (totalDocsElement) {
        totalDocsElement.textContent = stats.total_documents;
    }
    
    // Update storage usage
    const storageUsageElement = document.querySelector('[data-stat="storage-usage"]');
    if (storageUsageElement) {
        storageUsageElement.textContent = `${stats.storage_usage_percentage}%`;
    }
    
    // Update storage progress bar
    const progressBar = document.querySelector('.progress-bar-fill');
    if (progressBar) {
        progressBar.style.width = `${stats.storage_usage_percentage}%`;
    }
    
    // Update used storage text
    const usedStorageElement = document.querySelector('[data-stat="used-storage"]');
    if (usedStorageElement) {
        usedStorageElement.textContent = stats.total_size_formatted;
    }
    
    // Update document type counts
    if (stats.documents_by_type) {
        Object.keys(stats.documents_by_type).forEach(type => {
            const element = document.querySelector(`[data-doc-type="${type}"]`);
            if (element) {
                element.textContent = stats.documents_by_type[type];
            }
        });
    }
}

// Load storage usage via AJAX
function loadStorageUsage() {
    fetch(`/api/storage-usage/{{ $unit->id }}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStorageUsageUI(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to load storage usage:', error);
        });
}

// Update storage usage UI
function updateStorageUsageUI(storageData) {
    // Update progress bar
    const progressBar = document.querySelector('.progress-bar-fill');
    if (progressBar) {
        progressBar.style.width = `${storageData.usage_percentage}%`;
    }
    
    // Update storage text
    const usedElement = document.querySelector('[data-storage="used"]');
    const remainingElement = document.querySelector('[data-storage="remaining"]');
    
    if (usedElement) {
        usedElement.textContent = storageData.used_formatted;
    }
    if (remainingElement) {
        remainingElement.textContent = storageData.remaining_formatted;
    }
}

// Download document with error handling
function downloadDocument(path, disk = 'private', documentName = 'document') {
    try {
        // Encode the path for the URL
        const encodedPath = btoa(path);
        window.location.href = `/tenant/property-units/{{ $unit->id }}/documents/download/${encodedPath}/${disk}`;
    } catch (error) {
        console.error('Download error:', error);
        alert('Failed to download document. Please try again.');
    }
}

// Preview document
function previewDocument(path, disk = 'private') {
    try {
        // Encode the path for the URL
        const encodedPath = btoa(path);
        window.open(`/tenant/property-units/{{ $unit->id }}/documents/preview/${encodedPath}/${disk}`, '_blank');
    } catch (error) {
        console.error('Preview error:', error);
        alert('Failed to preview document. This file type may not support preview.');
    }
}

// Confirm document deletion
function confirmDeleteDocument(documentId, documentName) {
    if (confirm(`Are you sure you want to delete "${documentName}"? This action cannot be undone.`)) {
        deleteDocument(documentId);
    }
}

// Refresh document list
function refreshDocumentList() {
    // Show loading indicator
    const refreshBtn = document.querySelector('[data-action="refresh-documents"]');
    if (refreshBtn) {
        const originalHTML = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        // Simulate refresh
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

// Search documents
function searchDocuments() {
    const searchTerm = document.getElementById('documentSearch').value.toLowerCase();
    const documentCards = document.querySelectorAll('.document-card');
    
    documentCards.forEach(card => {
        const documentName = card.querySelector('[data-document-name]')?.textContent.toLowerCase() || '';
        const documentType = card.querySelector('[data-document-type]')?.textContent.toLowerCase() || '';
        const documentDescription = card.querySelector('[data-document-description]')?.textContent.toLowerCase() || '';
        
        if (documentName.includes(searchTerm) || 
            documentType.includes(searchTerm) || 
            documentDescription.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Filter documents by type
function filterDocumentsByType(type) {
    const documentCards = document.querySelectorAll('.document-card');
    const tabButtons = document.querySelectorAll('.tab-button');
    
    // Update active tab
    tabButtons.forEach(button => {
        button.classList.remove('active', 'border-primary', 'text-primary');
        button.classList.add('border-transparent');
    });
    
    // Show/hide documents based on type
    documentCards.forEach(card => {
        const documentType = card.getAttribute('data-type');
        
        if (type === 'all' || documentType === type) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Switch to the corresponding tab
    switchTab(type === 'all' ? 'all-documents' : type + '-documents');
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Close modal with Escape key
    if (e.key === 'Escape' && !document.getElementById('deleteModal').classList.contains('hidden')) {
        hideDeleteModal();
    }
    
    // Tab navigation with arrow keys
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        const tabs = Array.from(document.querySelectorAll('.tab-button'));
        const currentTab = tabs.find(tab => tab.classList.contains('active'));
        const currentIndex = tabs.indexOf(currentTab);
        
        let newIndex;
        if (e.key === 'ArrowRight') {
            newIndex = (currentIndex + 1) % tabs.length;
        } else {
            newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        }
        
        const newTab = tabs[newIndex].getAttribute('data-tab');
        switchTab(newTab);
        e.preventDefault();
    }
    
    // Search with Ctrl+F or Cmd+F
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('documentSearch');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Refresh with F5
    if (e.key === 'F5') {
        e.preventDefault();
        refreshDocumentList();
    }
});

// Initialize tooltips
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute bg-gray-900 text-white text-xs rounded py-1 px-2 bottom-full left-1/2 transform -translate-x-1/2 mb-1 z-50';
            tooltip.textContent = tooltipText;
            tooltip.style.minWidth = '100px';
            tooltip.style.textAlign = 'center';
            
            this.appendChild(tooltip);
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.absolute');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
}

// Export documents
function exportDocuments(format = 'csv') {
    if (confirm(`Export all documents as ${format.toUpperCase()}?`)) {
        const exportUrl = `/tenant/property-units/{{ $unit->id }}/documents/export?format=${format}`;
        window.location.href = exportUrl;
    }
}

// Bulk document actions
function selectAllDocuments(selectAll) {
    const checkboxes = document.querySelectorAll('.document-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const selectedCount = document.querySelectorAll('.document-checkbox:checked').length;
    const bulkActions = document.getElementById('bulkActions');
    
    if (selectedCount > 0) {
        bulkActions.classList.remove('hidden');
        document.getElementById('selectedCount').textContent = selectedCount;
    } else {
        bulkActions.classList.add('hidden');
    }
}

function bulkDeleteDocuments() {
    const selectedIds = Array.from(document.querySelectorAll('.document-checkbox:checked'))
        .map(checkbox => checkbox.value)
        .filter(id => id);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one document to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedIds.length} selected document(s)? This action cannot be undone.`)) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/tenant/property-units/{{ $unit->id }}/documents/bulk-delete`;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        form.appendChild(csrfToken);
        
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'document_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    
    // Add event listeners for bulk actions
    const selectAllCheckbox = document.getElementById('selectAllDocuments');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            selectAllDocuments(this.checked);
        });
    }
    
    // Add event listeners to individual checkboxes
    document.querySelectorAll('.document-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    // Search input event listener
    const searchInput = document.getElementById('documentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', searchDocuments);
    }
});
</script>

<style>
/* Active tab styling */
.tab-button.active {
    border-bottom-color: var(--primary) !important;
    color: var(--primary) !important;
}

/* Document card styling */
.document-card {
    background-color: rgba(var(--bg-input), 0.3);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

/* Progress bar styling */
.progress-bar {
    height: 8px;
    border-radius: 4px;
    background-color: rgba(var(--primary-rgb), 0.1);
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
    background: linear-gradient(to right, var(--primary), var(--secondary));
}

/* Button styling */
.btn-primary {
    background: linear-gradient(to right, var(--primary), var(--secondary));
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary {
    background-color: rgba(var(--bg-input), 0.5);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--bg-input), 0.8);
    transform: translateY(-2px);
}

/* Index-specific form control styles */
.index-custom-dropdown,
.index-custom-input,
.index-custom-textarea {
    background-color: var(--bg-input);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    width: 100%;
    transition: all 0.3s ease;
}

.index-custom-dropdown:focus,
.index-custom-input:focus,
.index-custom-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.index-custom-dropdown {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-position: right 0.75rem center;
    background-repeat: no-repeat;
    background-size: 1.25em 1.25em;
    padding-right: 2.5rem;
    cursor: pointer;
}

/* Dark theme dropdown styling */
.dark-dropdown {
    background-color: #1f2937 !important;
    border-color: #4b5563 !important;
    color: #f3f4f6 !important;
}

.dark-dropdown option {
    background-color: #1f2937 !important;
    color: #f3f4f6 !important;
}

/* Table styling */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

th, td {
    padding: 1rem 1.5rem;
    text-align: left;
}

th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

tr:hover {
    background-color: rgba(var(--bg-input), 0.3);
}

/* Animation for tab switching */
.tab-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Loading animation */
.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Modal animations */
#deleteModal {
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: rgba(var(--bg-input), 0.5);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .flex.flex-wrap {
        flex-direction: column;
    }
    
    .flex.flex-wrap > * {
        margin-bottom: 0.5rem;
    }
    
    .overflow-x-auto {
        overflow-x: scroll;
    }
}
</style>
@endsection