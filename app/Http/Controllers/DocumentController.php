<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Document;
use App\Models\PropertyUnit;
use App\Models\User;

class DocumentController extends Controller
{
    public function preview($path, $disk = 'private')
    {
        try {
            $decodedPath = base64_decode($path);
            
            if (!Storage::disk($disk)->exists($decodedPath)) {
                abort(404, 'Document not found');
            }
            
            // Check file type for preview support
            $mimeType = Storage::disk($disk)->mimeType($decodedPath);
            $fileName = basename($decodedPath);
            
            // Allow preview for images and PDFs only
            if (str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf') {
                $file = Storage::disk($disk)->get($decodedPath);
                return Response::make($file, 200, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="' . $fileName . '"'
                ]);
            }
            
            // For text files
            if ($mimeType === 'text/plain') {
                $file = Storage::disk($disk)->get($decodedPath);
                return Response::make($file, 200, [
                    'Content-Type' => 'text/plain',
                    'Content-Disposition' => 'inline; filename="' . $fileName . '"'
                ]);
            }
            
            // For unsupported types, return error
            abort(415, 'File type not supported for preview. Please download the file instead.');
            
        } catch (\Exception $e) {
            Log::error('Failed to preview document: ' . $e->getMessage(), [
                'path' => $path,
                'disk' => $disk,
                'user_id' => Auth::id()
            ]);
            
            abort(500, 'Unable to preview document: ' . $e->getMessage());
        }
    }
    
    public function download($path, $disk = 'private')
    {
        try {
            $decodedPath = base64_decode($path);
            
            if (!Storage::disk($disk)->exists($decodedPath)) {
                abort(404, 'Document not found');
            }
            
            return Storage::disk($disk)->download($decodedPath);
            
        } catch (\Exception $e) {
            Log::error('Failed to download document: ' . $e->getMessage(), [
                'path' => $path,
                'disk' => $disk,
                'user_id' => Auth::id()
            ]);
            
            abort(500, 'Unable to download document: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload a document (used by tenants and others)
     */
    public function upload(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Validate request
            $validator = Validator::make($request->all(), [
                'unit_id' => 'required|exists:property_units,id',
                'document_type' => 'required|string|max:255',
                'document_name' => 'required|string|max:255',
                'document_file' => 'required|file|max:5120', // 5MB max
                'description' => 'nullable|string|max:1000',
            ]);
            
            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }
            
            $unit = PropertyUnit::findOrFail($request->unit_id);
            
            // Authorization checks
            if ($user->isTenant()) {
                // Tenant can only upload to their own unit
                if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
                    abort(403, 'You can only upload documents to your assigned unit.');
                }
            } elseif ($user->isLandlord()) {
                // Landlord can only upload to their own properties
                if ($unit->property->landlord_id !== $user->id) {
                    abort(403, 'You can only upload documents to your own properties.');
                }
            } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
                // Admins can upload to any unit
                // No additional checks needed
            } else {
                abort(403, 'You do not have permission to upload documents.');
            }
            
            // Get the file
            $file = $request->file('document_file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            
            // Generate unique filename
            $filename = Str::uuid() . '.' . $extension;
            
            // Define storage path based on user type
            if ($user->isTenant()) {
                $path = 'tenant-documents/' . $user->id . '/' . $unit->id . '/' . $filename;
            } elseif ($user->isLandlord()) {
                $path = 'landlord-documents/' . $user->id . '/' . $unit->property_id . '/' . $unit->id . '/' . $filename;
            } else {
                $path = 'admin-documents/' . $user->id . '/' . $unit->id . '/' . $filename;
            }
            
            // Store the file
            Storage::disk('private')->put($path, file_get_contents($file));
            
            // Create document record
            $document = new Document();
            $document->user_id = $user->id;
            $document->documentable_type = 'App\Models\PropertyUnit';
            $document->documentable_id = $unit->id;
            $document->name = $request->document_name;
            $document->original_name = $originalName;
            $document->path = base64_encode($path);
            $document->disk = 'private';
            $document->type = $request->document_type;
            $document->description = $request->description;
            $document->size = $size;
            $document->mime_type = $file->getMimeType();
            $document->extension = $extension;
            $document->save();
            
            // Log the upload
            Log::info('Document uploaded successfully', [
                'user_id' => $user->id,
                'document_id' => $document->id,
                'unit_id' => $unit->id,
                'type' => $request->document_type,
                'size' => $size,
            ]);
            
            return redirect()->back()->with('success', 'Document uploaded successfully.');
            
        } catch (\Exception $e) {
            Log::error('Failed to upload document: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->except(['document_file'])
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to upload document: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Download application document (from tenant_documents array)
     */
    public function downloadApplicationDocument($unitId, $index)
    {
        try {
            $unit = PropertyUnit::findOrFail($unitId);
            $user = Auth::user();
            
            // Authorization checks
            if ($user->isTenant()) {
                // Tenant can only access their own unit's documents
                if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
                    abort(403, 'You can only access documents for your assigned unit.');
                }
            } elseif ($user->isLandlord()) {
                // Landlord can only access their own properties' documents
                if ($unit->property->landlord_id !== $user->id) {
                    abort(403, 'You can only access documents for your own properties.');
                }
            } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
                abort(403, 'You do not have permission to access these documents.');
            }
            
            $documents = $unit->tenant_documents ?? [];
            
            if (!isset($documents[$index])) {
                abort(404, 'Document not found.');
            }
            
            $documentData = $documents[$index];
            
            // Check if document has file path (for newer uploads)
            if (isset($documentData['path']) && isset($documentData['disk'])) {
                // Handle base64 encoded path
                $path = base64_decode($documentData['path']);
                $disk = $documentData['disk'] ?? 'private';
                
                if (Storage::disk($disk)->exists($path)) {
                    $fileName = $documentData['name'] ?? 'document_' . ($index + 1) . '.pdf';
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    
                    if (!$extension) {
                        $fileName .= '.pdf'; // Default extension
                    }
                    
                    return Storage::disk($disk)->download($path, $fileName);
                }
            }
            
            // For legacy documents stored differently
            // You might need to implement different logic based on your storage structure
            abort(404, 'Document file not found in storage.');
            
        } catch (\Exception $e) {
            Log::error('Failed to download application document: ' . $e->getMessage(), [
                'unit_id' => $unitId,
                'index' => $index,
                'user_id' => Auth::id()
            ]);
            
            abort(500, 'Unable to download document: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete a document
     */
    public function destroy($unitId, $documentId)
    {
        try {
            $document = Document::findOrFail($documentId);
            $unit = PropertyUnit::findOrFail($unitId);
            $user = Auth::user();
            
            // Authorization checks
            if ($user->isTenant()) {
                // Tenant can only delete their own documents from their unit
                if ($document->user_id !== $user->id) {
                    abort(403, 'You can only delete your own documents.');
                }
                if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
                    abort(403, 'You can only delete documents from your assigned unit.');
                }
            } elseif ($user->isLandlord()) {
                // Landlord can delete their own documents or tenant documents from their properties
                $isLandlordDocument = $document->user_id === $user->id;
                $isTenantDocument = $unit->tenant_id && $document->user_id === $unit->tenant_id;
                $isUnitOwner = $unit->property->landlord_id === $user->id;
                
                if (!($isLandlordDocument || ($isTenantDocument && $isUnitOwner))) {
                    abort(403, 'You can only delete your own documents or tenant documents from your properties.');
                }
            } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
                // Admins can delete any document
                // No additional checks needed
            } else {
                abort(403, 'You do not have permission to delete documents.');
            }
            
            // Check if document belongs to the specified unit
            if ($document->documentable_type !== 'App\Models\PropertyUnit' || 
                $document->documentable_id != $unitId) {
                abort(404, 'Document not found for this unit.');
            }
            
            // Delete the file from storage
            try {
                $path = base64_decode($document->path);
                $disk = $document->disk ?? 'private';
                
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Exception $storageException) {
                Log::warning('Failed to delete file from storage, but continuing with DB deletion: ' . $storageException->getMessage(), [
                    'document_id' => $document->id,
                    'path' => $document->path,
                ]);
                // Continue with DB deletion even if storage deletion fails
            }
            
            // Delete the database record
            $documentName = $document->name;
            $document->delete();
            
            // Log the deletion
            Log::info('Document deleted', [
                'user_id' => $user->id,
                'document_id' => $documentId,
                'document_name' => $documentName,
                'unit_id' => $unitId,
            ]);
            
            return redirect()->back()->with('success', 'Document deleted successfully.');
            
        } catch (\Exception $e) {
            Log::error('Failed to delete document: ' . $e->getMessage(), [
                'document_id' => $documentId,
                'unit_id' => $unitId,
                'user_id' => Auth::id()
            ]);
            
            return redirect()->back()->with('error', 'Failed to delete document: ' . $e->getMessage());
        }
    }
    
    /**
     * Get document statistics for a unit
     */
    public function getDocumentStatistics($unitId)
    {
        try {
            $unit = PropertyUnit::findOrFail($unitId);
            $user = Auth::user();
            
            // Authorization checks
            if ($user->isTenant()) {
                if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
                    abort(403, 'You can only view documents for your assigned unit.');
                }
            } elseif ($user->isLandlord()) {
                if ($unit->property->landlord_id !== $user->id) {
                    abort(403, 'You can only view documents for your own properties.');
                }
            } elseif (!$user->isAdmin() && !$user->isSuperAdmin()) {
                abort(403, 'You do not have permission to view document statistics.');
            }
            
            // Get uploaded documents count
            $uploadedCount = Document::where('documentable_type', 'App\Models\PropertyUnit')
                ->where('documentable_id', $unitId)
                ->count();
            
            // Get total size of uploaded documents
            $totalSize = Document::where('documentable_type', 'App\Models\PropertyUnit')
                ->where('documentable_id', $unitId)
                ->sum('size');
            
            // Get document count by type
            $documentsByType = Document::where('documentable_type', 'App\Models\PropertyUnit')
                ->where('documentable_id', $unitId)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();
            
            // Get application documents count
            $applicationDocs = $unit->tenant_documents ?? [];
            $applicationCount = count($applicationDocs);
            
            // Get lease documents count
            $leaseDocuments = [];
            if ($unit->currentLease) {
                $leaseDocuments = $unit->currentLease->documents ?? [];
            }
            $leaseCount = count($leaseDocuments);
            
            // Calculate storage usage
            $maxStorage = config('filesystems.max_upload_size', 5) * 1024 * 1024; // Default 5MB in bytes
            $storageUsage = min(($totalSize / ($maxStorage * 10)) * 100, 100); // Max 10x multiplier
            
            return response()->json([
                'success' => true,
                'data' => [
                    'uploaded_count' => $uploadedCount,
                    'application_count' => $applicationCount,
                    'lease_count' => $leaseCount,
                    'total_documents' => $uploadedCount + $applicationCount + $leaseCount,
                    'total_size_bytes' => $totalSize,
                    'total_size_formatted' => $this->formatFileSize($totalSize),
                    'documents_by_type' => $documentsByType,
                    'storage_usage_percentage' => round($storageUsage, 2),
                    'max_storage_mb' => config('filesystems.max_upload_size', 5),
                    'max_storage_bytes' => $maxStorage,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get document statistics: ' . $e->getMessage(), [
                'unit_id' => $unitId,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document statistics: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper method to format file size
     */
    private function formatFileSize($bytes)
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * Get recent documents for dashboard
     */
    public function getRecentDocuments($limit = 10)
    {
        try {
            $user = Auth::user();
            
            $query = Document::with(['documentable', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit($limit);
            
            // Filter based on user type
            if ($user->isTenant()) {
                $query->where('user_id', $user->id)
                    ->orWhere(function($q) use ($user) {
                        $q->where('documentable_type', 'App\Models\PropertyUnit')
                          ->whereHas('documentable', function($q2) use ($user) {
                              $q2->where('tenant_id', $user->id);
                          });
                    });
            } elseif ($user->isLandlord()) {
                $query->where('user_id', $user->id)
                    ->orWhere(function($q) use ($user) {
                        $q->where('documentable_type', 'App\Models\PropertyUnit')
                          ->whereHas('documentable.property', function($q2) use ($user) {
                              $q2->where('landlord_id', $user->id);
                          });
                    });
            }
            // Admins and Super Admins can see all documents
            
            $documents = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $documents->map(function($document) {
                    return [
                        'id' => $document->id,
                        'name' => $document->name,
                        'type' => $document->type,
                        'size' => $this->formatFileSize($document->size),
                        'created_at' => $document->created_at->format('M d, Y'),
                        'uploaded_by' => $document->user->name ?? 'Unknown',
                        'related_to' => $this->getDocumentableInfo($document),
                        'download_url' => route('property-units.documents.download', [
                            'id' => $document->documentable_id,
                            'path' => $document->path,
                            'disk' => $document->disk
                        ]),
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get recent documents: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recent documents: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Helper method to get documentable information
     */
    private function getDocumentableInfo($document)
    {
        if ($document->documentable_type === 'App\Models\PropertyUnit') {
            $unit = $document->documentable;
            return $unit ? $unit->property->property_name . ' - Unit ' . $unit->unit_number : 'Unknown Unit';
        } elseif ($document->documentable_type === 'App\Models\RentalAgreement') {
            $lease = $document->documentable;
            return $lease ? 'Lease Agreement' : 'Lease Document';
        }
        
        return 'Unknown';
    }
    
    /**
     * Validate document upload (AJAX)
     */
    public function validateUpload(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'document_file' => 'required|file|max:5120', // 5MB max
                'document_type' => 'required|string|max:255',
            ], [
                'document_file.max' => 'The document file size must not exceed 5MB.',
                'document_file.mimes' => 'Allowed file types: PDF, JPG, PNG, DOC, DOCX.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Check file type
            $file = $request->file('document_file');
            $allowedMimes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/jpg',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['document_file' => ['Invalid file type. Allowed types: PDF, JPG, PNG, DOC, DOCX.']]
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'File validation successful',
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $this->formatFileSize($file->getSize()),
                    'type' => $file->getMimeType(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to validate upload: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get storage usage information
     */
    public function getStorageUsage($unitId = null)
    {
        try {
            $user = Auth::user();
            $totalSize = 0;
            
            if ($unitId) {
                // Get storage usage for specific unit
                $unit = PropertyUnit::findOrFail($unitId);
                
                // Authorization checks
                if ($user->isTenant()) {
                    if ($unit->tenant_id !== $user->id || $unit->tenant_status !== PropertyUnit::TENANT_STATUS_APPROVED) {
                        abort(403, 'You can only view storage usage for your assigned unit.');
                    }
                } elseif ($user->isLandlord()) {
                    if ($unit->property->landlord_id !== $user->id) {
                        abort(403, 'You can only view storage usage for your own properties.');
                    }
                }
                
                $totalSize = Document::where('documentable_type', 'App\Models\PropertyUnit')
                    ->where('documentable_id', $unitId)
                    ->sum('size');
                    
            } else {
                // Get total storage usage for user
                if ($user->isTenant()) {
                    $totalSize = Document::where('user_id', $user->id)->sum('size');
                } elseif ($user->isLandlord()) {
                    // Landlord's documents + tenant documents from their properties
                    $totalSize = Document::where('user_id', $user->id)->sum('size');
                    
                    // Add tenant documents from landlord's properties
                    $tenantDocumentsSize = Document::where('documentable_type', 'App\Models\PropertyUnit')
                        ->whereHas('documentable.property', function($q) use ($user) {
                            $q->where('landlord_id', $user->id);
                        })
                        ->where('user_id', '!=', $user->id)
                        ->sum('size');
                    
                    $totalSize += $tenantDocumentsSize;
                } else {
                    // Admin/Super Admin - all documents
                    $totalSize = Document::sum('size');
                }
            }
            
            $maxStorage = config('filesystems.max_upload_size', 5) * 1024 * 1024; // Default 5MB in bytes
            $usagePercentage = min(($totalSize / ($maxStorage * 10)) * 100, 100); // Max 10x multiplier
            
            return response()->json([
                'success' => true,
                'data' => [
                    'used_bytes' => $totalSize,
                    'used_formatted' => $this->formatFileSize($totalSize),
                    'max_bytes' => $maxStorage,
                    'max_formatted' => $this->formatFileSize($maxStorage),
                    'usage_percentage' => round($usagePercentage, 2),
                    'remaining_bytes' => max(0, $maxStorage - $totalSize),
                    'remaining_formatted' => $this->formatFileSize(max(0, $maxStorage - $totalSize)),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get storage usage: ' . $e->getMessage(), [
                'unit_id' => $unitId,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get storage usage: ' . $e->getMessage()
            ], 500);
        }
    }
}