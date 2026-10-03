<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyOwnershipTransfer;
use App\Models\PropertyOwnershipTransferArchive;
use App\Models\User;
use App\Services\TransferArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransferArchiveController extends Controller
{
    protected $archiveService;

    public function __construct(TransferArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    /**
     * Display archive index
     */
    public function index(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransferArchive::query()
            ->with(['property', 'currentLandlord', 'newLandlord', 'archivedBy'])
            ->orderBy('archived_at', 'desc');
        
        if ($request->filled('year')) {
            $query->where('archive_year', $request->year);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('type')) {
            if ($request->type === 'reversal') {
                $query->where('metadata->is_reversal', true);
            } elseif ($request->type === 'regular') {
                $query->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                });
            }
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('document_reference', 'LIKE', "%{$search}%")
                  ->orWhere('new_owner_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('property', function($prop) use ($search) {
                      $prop->where('property_name', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        $archives = $query->paginate($request->get('per_page', 20));
        
        $availableYears = PropertyOwnershipTransferArchive::select('archive_year')
            ->distinct()
            ->orderBy('archive_year', 'desc')
            ->pluck('archive_year')
            ->toArray();
        
        $stats = $this->getArchiveStatistics();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'archives' => $archives,
                'statistics' => $stats,
                'available_years' => $availableYears,
            ]);
        }
        
        return view('admin.ownership-transfers.archive', compact('archives', 'stats', 'availableYears'));
    }

    /**
     * Show single archived transfer
     */
    public function show(Request $request, $archiveId)
    {
        $this->authorizeAdminAccess($request);
        
        $archive = PropertyOwnershipTransferArchive::with(['property', 'currentLandlord', 'newLandlord', 'archivedBy'])
            ->findOrFail($archiveId);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'archive' => $archive
            ]);
        }
        
        return view('admin.ownership-transfers.archive-show', compact('archive'));
    }

    /**
     * Preview archived transfer (AJAX)
     */
    public function preview(Request $request, $archiveId)
    {
        $this->authorizeAdminAccess($request);
        
        $archive = PropertyOwnershipTransferArchive::with(['property', 'currentLandlord', 'newLandlord', 'archivedBy'])
            ->findOrFail($archiveId);
        
        return response()->json([
            'success' => true,
            'archive' => [
                'id' => $archive->id,
                'archive_year' => $archive->archive_year,
                'document_reference' => $archive->document_reference,
                'status' => $archive->status,
                'status_label' => $archive->status_label,
                'is_reversal_record' => $archive->isReversalRecord(),
                'property' => $archive->property ? [
                    'name' => $archive->property->property_name,
                    'registration_pattern' => $archive->property->registration_pattern
                ] : null,
                'current_landlord' => $archive->currentLandlord ? [
                    'name' => $archive->currentLandlord->name
                ] : null,
                'new_owner_name' => $archive->new_owner_name,
                'transfer_date' => $archive->transfer_date ? $archive->transfer_date->format('Y-m-d') : null,
                'formatted_sale_amount' => $archive->formatted_sale_amount,
                'archived_at' => $archive->archived_at->toISOString(),
                'archived_by' => $archive->archivedBy ? ['name' => $archive->archivedBy->name] : null,
                'archive_reason' => $archive->archive_reason,
            ]
        ]);
    }

    /**
     * Archive a transfer (move from main table to archive)
     * This is called when permanently deleting from trash
     */
    public function archiveTransfer(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::withTrashed()->findOrFail($transferId);
        
        DB::beginTransaction();
        
        try {
            // Check if already archived
            $existingArchive = PropertyOwnershipTransferArchive::where('original_transfer_id', $transfer->id)
                ->orWhere('document_reference', $transfer->document_reference)
                ->first();
                
            if ($existingArchive) {
                return response()->json([
                    'success' => false,
                    'message' => 'This transfer is already archived.'
                ], 409);
            }
            
            // Create archive record
            $archiveData = [
                'original_transfer_id' => $transfer->id,
                'property_id' => $transfer->property_id,
                'current_landlord_id' => $transfer->current_landlord_id,
                'new_landlord_id' => $transfer->new_landlord_id,
                'requested_by_id' => $transfer->requested_by_id,
                'admin_approved_by_id' => $transfer->admin_approved_by_id,
                'completed_by_id' => $transfer->completed_by_id,
                'rejected_by_id' => $transfer->rejected_by_id,
                'status' => $transfer->status,
                'transfer_date' => $transfer->transfer_date,
                'sale_amount' => $transfer->sale_amount,
                'document_type' => $transfer->document_type,
                'document_reference' => $transfer->document_reference,
                'document_url' => $transfer->document_url,
                'certificate_url' => $transfer->certificate_url,
                'new_owner_name' => $transfer->new_owner_name,
                'new_owner_phone' => $transfer->new_owner_phone,
                'new_owner_email' => $transfer->new_owner_email,
                'new_owner_address' => $transfer->new_owner_address,
                'reason_for_transfer' => $transfer->reason_for_transfer,
                'notes' => $transfer->notes,
                'admin_notes' => $transfer->admin_notes,
                'rejection_reason' => $transfer->rejection_reason,
                'approved_at' => $transfer->approved_at,
                'rejected_at' => $transfer->rejected_at,
                'cancelled_at' => $transfer->cancelled_at,
                'completed_at' => $transfer->completed_at,
                'metadata' => $transfer->metadata,
                'archive_year' => now()->year,
                'archived_at' => now(),
                'archived_by' => auth()->id(),
                'archive_reason' => $request->input('archive_reason', 'permanent_deletion_from_trash'),
                'reversal_status' => $transfer->reversal_status,
                'is_reversed' => $transfer->is_reversed,
                'reversal_transfer_id' => $transfer->reversal_transfer_id,
                'original_transfer_id' => $transfer->original_transfer_id,
            ];
            
            $archive = PropertyOwnershipTransferArchive::create($archiveData);
            
            // Also archive any related reversal records
            if (!$transfer->isReversalRecord()) {
                $reversalRecords = PropertyOwnershipTransfer::withTrashed()
                    ->where(function($q) use ($transfer) {
                        $q->where('original_transfer_id', $transfer->id)
                          ->orWhere('reversal_transfer_id', $transfer->id)
                          ->orWhere('metadata->original_transfer_id', $transfer->id);
                    })
                    ->get();
                
                foreach ($reversalRecords as $reversal) {
                    // Check if reversal is already archived
                    $existingReversalArchive = PropertyOwnershipTransferArchive::where('original_transfer_id', $reversal->id)
                        ->orWhere('document_reference', $reversal->document_reference)
                        ->first();
                    
                    if (!$existingReversalArchive) {
                        $reversalArchiveData = [
                            'original_transfer_id' => $reversal->id,
                            'property_id' => $reversal->property_id,
                            'current_landlord_id' => $reversal->current_landlord_id,
                            'new_landlord_id' => $reversal->new_landlord_id,
                            'requested_by_id' => $reversal->requested_by_id,
                            'status' => $reversal->status,
                            'transfer_date' => $reversal->transfer_date,
                            'sale_amount' => $reversal->sale_amount,
                            'document_type' => $reversal->document_type,
                            'document_reference' => $reversal->document_reference,
                            'document_url' => $reversal->document_url,
                            'certificate_url' => $reversal->certificate_url,
                            'new_owner_name' => $reversal->new_owner_name,
                            'new_owner_phone' => $reversal->new_owner_phone,
                            'new_owner_email' => $reversal->new_owner_email,
                            'reason_for_transfer' => $reversal->reason_for_transfer,
                            'notes' => $reversal->notes,
                            'metadata' => $reversal->metadata,
                            'archive_year' => now()->year,
                            'archived_at' => now(),
                            'archived_by' => auth()->id(),
                            'archive_reason' => 'related_reversal_record',
                            'reversal_status' => $reversal->reversal_status,
                            'is_reversed' => $reversal->is_reversed,
                            'original_transfer_id' => $reversal->original_transfer_id,
                        ];
                        
                        PropertyOwnershipTransferArchive::create($reversalArchiveData);
                    }
                }
            }
            
            // Log the archiving action
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_archived',
                'description' => "Archived transfer #{$transfer->id} ({$transfer->document_reference})" . ($transfer->isReversalRecord() ? " (reversal record)" : ""),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'transfer_id' => $transfer->id,
                    'archive_id' => $archive->id,
                    'is_reversal' => $transfer->isReversalRecord(),
                    'archive_reason' => $request->input('archive_reason', 'permanent_deletion_from_trash')
                ]
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Transfer archived successfully.',
                'archive_id' => $archive->id,
                'is_reversal' => $transfer->isReversalRecord()
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to archive transfer: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive multiple transfers (bulk)
     */
    public function bulkArchive(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'archive_reason' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $archived = 0;
        $failed = [];
        $archiveReason = $request->input('archive_reason', 'bulk_archive_from_trash');
        
        foreach ($request->transfer_ids as $transferId) {
            $archiveRequest = new \Illuminate\Http\Request();
            $archiveRequest->merge([
                'archive_reason' => $archiveReason
            ]);
            
            $result = $this->archiveTransfer($archiveRequest, $transferId);
            $responseData = json_decode($result->getContent(), true);
            
            if ($responseData['success'] ?? false) {
                $archived++;
            } else {
                $failed[] = [
                    'id' => $transferId,
                    'reason' => $responseData['message'] ?? 'Unknown error'
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Archived {$archived} transfers, " . count($failed) . " failed.",
            'archived_count' => $archived,
            'failed' => $failed
        ]);
    }

    /**
     * Restore single archive back to main table
     */
    public function restore(Request $request, $archiveId)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make(['archive_id' => $archiveId], [
            'archive_id' => 'required|exists:property_ownership_transfer_archives,id',
        ]);
        
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->with('error', 'Invalid archive record.');
        }
        
        $result = $this->archiveService->restoreFromArchive([$archiveId], [
            'restore_files' => true,
            'delete_after_restore' => false,
            'restored_by' => auth()->id(),
        ]);
        
        if ($request->expectsJson()) {
            return response()->json($result);
        }
        
        if ($result['success']) {
            return redirect()->back()->with('success', 
                "Successfully restored 1 record from archive."
            );
        }
        
        return redirect()->back()->with('error', $result['message'] ?? 'Failed to restore from archive');
    }

    /**
     * Bulk restore from archive
     */
    public function bulkRestore(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'archive_ids' => 'required|array',
            'archive_ids.*' => 'exists:property_ownership_transfer_archives,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $result = $this->archiveService->restoreFromArchive($request->archive_ids, [
            'restore_files' => true,
            'delete_after_restore' => false,
            'restored_by' => auth()->id(),
        ]);
        
        return response()->json($result);
    }

    /**
     * Delete single archive permanently
     */
    public function destroy(Request $request, $archiveId)
    {
        $this->authorizeAdminAccess($request);
        
        $archive = PropertyOwnershipTransferArchive::findOrFail($archiveId);
        
        try {
            if ($archive->document_url) {
                Storage::disk('public')->delete($archive->document_url);
            }
            if ($archive->certificate_url) {
                Storage::disk('public')->delete($archive->certificate_url);
            }
            
            $archive->delete();
            
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'archive_permanently_deleted',
                'description' => "Permanently deleted archive #{$archive->id} ({$archive->document_reference})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'archive_id' => $archive->id,
                    'is_reversal' => $archive->isReversalRecord()
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Archive deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete archive: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete archives permanently
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'archive_ids' => 'required|array',
            'archive_ids.*' => 'exists:property_ownership_transfer_archives,id',
            'deletion_reason' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $deleted = 0;
        $failed = [];
        $deletionReason = $request->input('deletion_reason');
        
        foreach ($request->archive_ids as $archiveId) {
            try {
                $archive = PropertyOwnershipTransferArchive::find($archiveId);
                if ($archive) {
                    if ($archive->document_url) {
                        Storage::disk('public')->delete($archive->document_url);
                    }
                    if ($archive->certificate_url) {
                        Storage::disk('public')->delete($archive->certificate_url);
                    }
                    $archive->delete();
                    $deleted++;
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $archiveId,
                    'reason' => $e->getMessage()
                ];
            }
        }
        
        if ($deleted > 0) {
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_archive_delete',
                'description' => "Bulk permanently deleted {$deleted} archived transfer records",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'deleted_count' => $deleted,
                    'failed_count' => count($failed),
                    'archive_ids' => $request->archive_ids,
                    'deletion_reason' => $deletionReason
                ]
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} archives, " . count($failed) . " failed.",
            'deleted_count' => $deleted,
            'failed' => $failed
        ]);
    }

    /**
     * Empty entire archive
     */
    public function emptyArchive(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'confirmation' => 'required|in:DELETE_ARCHIVE',
            'deletion_reason' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please type "DELETE_ARCHIVE" to confirm.',
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $archives = PropertyOwnershipTransferArchive::all();
            $deletedCount = $archives->count();
            $deletedValue = $archives->where('status', 'completed')->sum('sale_amount');
            $deletionReason = $request->input('deletion_reason');
            
            // Delete associated files for each archive
            foreach ($archives as $archive) {
                if ($archive->document_url && Storage::disk('public')->exists($archive->document_url)) {
                    Storage::disk('public')->delete($archive->document_url);
                }
                if ($archive->certificate_url && Storage::disk('public')->exists($archive->certificate_url)) {
                    Storage::disk('public')->delete($archive->certificate_url);
                }
            }
            
            // Delete all archive records
            PropertyOwnershipTransferArchive::truncate();
            
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'archive_emptied',
                'description' => "Emptied the entire transfer archive: {$deletedCount} records permanently deleted",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'deleted_count' => $deletedCount,
                    'deleted_value' => $deletedValue,
                    'deletion_reason' => $deletionReason
                ]
            ]);
            
            Cache::forget('archive_statistics');
            Cache::forget('archive_years');
            Cache::forget('archive_export_data');
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Archive emptied successfully. {$deletedCount} records permanently deleted.",
                'deleted_count' => $deletedCount,
                'deleted_value' => $deletedValue
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to empty archive: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to empty archive: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete old archives (older than specified years)
     */
    public function deleteOldArchives(Request $request, $years)
    {
        $this->authorizeAdminAccess($request);
        
        $result = $this->archiveService->deleteOldArchives($years);
        
        return response()->json([
            'success' => true,
            'message' => "Deleted {$result['deleted_count']} archives older than {$years} years.",
            'deleted_count' => $result['deleted_count'],
            'cutoff_year' => $result['cutoff_year']
        ]);
    }

    /**
     * Export archives to CSV
     */
    public function exportCsv(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransferArchive::query()
            ->with(['property', 'currentLandlord', 'newLandlord']);
        
        if ($request->filled('year')) {
            $query->where('archive_year', $request->year);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('type')) {
            if ($request->type === 'reversal') {
                $query->where('metadata->is_reversal', true);
            } elseif ($request->type === 'regular') {
                $query->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                });
            }
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('document_reference', 'LIKE', "%{$search}%")
                  ->orWhere('new_owner_name', 'LIKE', "%{$search}%");
            });
        }
        
        $archives = $query->get();
        
        $filename = "archive_transfers_" . date('Y-m-d_His') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($archives) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Archive ID', 'Original ID', 'Archive Year', 'Document Reference',
                'Property Name', 'Current Owner', 'New Owner', 'Transfer Date',
                'Sale Amount', 'Status', 'Archived At', 'Document Type',
                'Reason for Transfer', 'Rejection Reason', 'Admin Notes',
                'Is Reversal Record', 'Reversal Status', 'Is Reversed'
            ]);
            
            foreach ($archives as $archive) {
                fputcsv($file, [
                    $archive->id,
                    $archive->original_transfer_id ?? 'N/A',
                    $archive->archive_year,
                    $archive->document_reference,
                    $archive->property->property_name ?? 'N/A',
                    $archive->currentLandlord->name ?? 'N/A',
                    $archive->new_owner_name,
                    $archive->transfer_date ? $archive->transfer_date->format('Y-m-d') : 'N/A',
                    $archive->sale_amount ? number_format($archive->sale_amount, 2) : 'N/A',
                    $archive->status_label,
                    $archive->archived_at->format('Y-m-d H:i:s'),
                    $archive->document_type_label,
                    $archive->reason_for_transfer ?? 'N/A',
                    $archive->rejection_reason ?? 'N/A',
                    $archive->admin_notes ?? 'N/A',
                    $archive->isReversalRecord() ? 'Yes' : 'No',
                    $archive->reversal_status ?? 'N/A',
                    $archive->is_reversed ? 'Yes' : 'No'
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export archive by year
     */
    public function exportByYear(Request $request, $year)
    {
        $this->authorizeAdminAccess($request);
        
        $archives = PropertyOwnershipTransferArchive::with(['property', 'currentLandlord', 'newLandlord'])
            ->where('archive_year', $year)
            ->get();
        
        $filename = "archive_transfers_{$year}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($archives, $year) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Archive ID', 'Original ID', 'Archive Year', 'Document Reference',
                'Property Name', 'Current Owner', 'New Owner', 'Transfer Date',
                'Sale Amount', 'Status', 'Archived At', 'Document Type',
                'Is Reversal Record'
            ]);
            
            foreach ($archives as $archive) {
                fputcsv($file, [
                    $archive->id,
                    $archive->original_transfer_id ?? 'N/A',
                    $archive->archive_year,
                    $archive->document_reference,
                    $archive->property->property_name ?? 'N/A',
                    $archive->currentLandlord->name ?? 'N/A',
                    $archive->new_owner_name,
                    $archive->transfer_date ? $archive->transfer_date->format('Y-m-d') : 'N/A',
                    $archive->sale_amount ? number_format($archive->sale_amount, 2) : 'N/A',
                    $archive->status_label,
                    $archive->archived_at->format('Y-m-d H:i:s'),
                    $archive->document_type_label,
                    $archive->isReversalRecord() ? 'Yes' : 'No'
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export single archive
     */
    public function exportSingleArchive(Request $request, $archiveId)
    {
        $this->authorizeAdminAccess($request);
        
        $archive = PropertyOwnershipTransferArchive::with(['property', 'currentLandlord', 'newLandlord'])
            ->findOrFail($archiveId);
        
        $filename = "archive_transfer_{$archive->id}_{$archive->document_reference}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($archive) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'Archive ID', 'Original ID', 'Archive Year', 'Document Reference',
                'Property Name', 'Current Owner', 'New Owner', 'Transfer Date',
                'Sale Amount', 'Status', 'Archived At', 'Document Type',
                'Reason for Transfer', 'Rejection Reason', 'Admin Notes',
                'Is Reversal Record', 'Reversal Status', 'Is Reversed',
                'Archive Reason', 'Archived By'
            ]);
            
            fputcsv($file, [
                $archive->id,
                $archive->original_transfer_id ?? 'N/A',
                $archive->archive_year,
                $archive->document_reference,
                $archive->property->property_name ?? 'N/A',
                $archive->currentLandlord->name ?? 'N/A',
                $archive->new_owner_name,
                $archive->transfer_date ? $archive->transfer_date->format('Y-m-d') : 'N/A',
                $archive->sale_amount ? number_format($archive->sale_amount, 2) : 'N/A',
                $archive->status_label,
                $archive->archived_at->format('Y-m-d H:i:s'),
                $archive->document_type_label,
                $archive->reason_for_transfer ?? 'N/A',
                $archive->rejection_reason ?? 'N/A',
                $archive->admin_notes ?? 'N/A',
                $archive->isReversalRecord() ? 'Yes' : 'No',
                $archive->reversal_status ?? 'N/A',
                $archive->is_reversed ? 'Yes' : 'No',
                $archive->archive_reason ?? 'N/A',
                $archive->archivedBy->name ?? 'N/A'
            ]);
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get archive statistics
     */
    public function stats(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $stats = $this->getArchiveStatistics();
        $stats['storage_usage_mb'] = $this->calculateArchiveStorageUsage();
        $stats['years_available'] = PropertyOwnershipTransferArchive::select('archive_year')
            ->distinct()
            ->orderBy('archive_year', 'desc')
            ->pluck('archive_year')
            ->toArray();
        $stats['reversal_records_archived'] = PropertyOwnershipTransferArchive::where('metadata->is_reversal', true)->count();
        $stats['regular_transfers_archived'] = PropertyOwnershipTransferArchive::where(function($q) {
            $q->whereNull('metadata->is_reversal')
              ->orWhere('metadata->is_reversal', false);
        })->count();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'statistics' => $stats,
                'last_updated' => now()->toISOString(),
            ]);
        }
        
        return $stats;
    }

    /**
     * Get archive years for filter
     */
    public function getYears(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $years = PropertyOwnershipTransferArchive::select('archive_year')
            ->distinct()
            ->orderBy('archive_year', 'desc')
            ->pluck('archive_year');
        
        return response()->json([
            'success' => true,
            'years' => $years
        ]);
    }

    /**
     * Get archive statistics by year
     */
    public function yearStats(Request $request, $year)
    {
        $this->authorizeAdminAccess($request);
        
        $stats = [
            'year' => $year,
            'total' => PropertyOwnershipTransferArchive::where('archive_year', $year)->count(),
            'by_status' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                ->select('status', \DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get(),
            'total_value' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                ->where('status', 'completed')
                ->sum('sale_amount'),
            'reversal_count' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                ->where('metadata->is_reversal', true)
                ->count(),
            'regular_count' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                ->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                })->count()
        ];
        
        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Search within archives
     */
    public function search(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransferArchive::query()
            ->with(['property', 'currentLandlord', 'newLandlord']);
        
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('document_reference', 'LIKE', "%{$search}%")
                  ->orWhere('new_owner_name', 'LIKE', "%{$search}%")
                  ->orWhere('new_owner_phone', 'LIKE', "%{$search}%")
                  ->orWhere('new_owner_email', 'LIKE', "%{$search}%")
                  ->orWhereHas('property', function($prop) use ($search) {
                      $prop->where('property_name', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        $results = $query->orderBy('archived_at', 'desc')
            ->paginate($request->get('per_page', 20));
        
        return response()->json([
            'success' => true,
            'results' => $results,
            'search_term' => $request->q
        ]);
    }

    /**
     * Generate archive report
     */
    public function generateReport(Request $request, $year)
    {
        $this->authorizeAdminAccess($request);
        
        $archives = PropertyOwnershipTransferArchive::with(['property', 'currentLandlord', 'newLandlord'])
            ->where('archive_year', $year)
            ->get();
        
        $reversalsCount = $archives->filter(function($a) { return $a->isReversalRecord(); })->count();
        $regularCount = $archives->count() - $reversalsCount;
        
        $stats = [
            'year' => $year,
            'total' => $archives->count(),
            'regular_count' => $regularCount,
            'reversal_count' => $reversalsCount,
            'by_status' => $archives->groupBy('status')->map->count(),
            'total_value' => $archives->where('status', 'completed')->sum('sale_amount'),
            'generated_at' => now()->toISOString(),
            'generated_by' => auth()->user()->name
        ];
        
        $pdf = Pdf::loadView('reports.archive-summary', compact('archives', 'stats', 'year'));
        
        return $pdf->download("archive_report_{$year}.pdf");
    }

    /**
     * Download archive certificate
     */
    public function downloadCertificate(Request $request, $archiveId)
    {
        $this->authorizeAdminAccess($request);
        
        $archive = PropertyOwnershipTransferArchive::findOrFail($archiveId);
        
        if (!$archive->certificate_url || !Storage::disk('public')->exists($archive->certificate_url)) {
            abort(404, 'Certificate not found.');
        }
        
        $filename = "archive_certificate_{$archive->id}.pdf";
        $path = storage_path('app/public/' . $archive->certificate_url);
        
        return response()->download($path, $filename);
    }

    /**
     * View archive activity logs
     */
    public function archiveActivityLogs(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $logs = \App\Models\ActivityLog::whereIn('action', [
            'transfer_archived',
            'transfer_restored_from_archive',
            'archive_permanently_deleted',
            'bulk_archive_restore',
            'bulk_archive_delete',
            'archive_emptied'
        ])->orderBy('created_at', 'desc')
          ->paginate($request->get('per_page', 20));
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'logs' => $logs
            ]);
        }
        
        return view('admin.ownership-transfers.archive-activity', compact('logs'));
    }

    /**
     * View archive restore history
     */
    public function archiveRestoreHistory(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $restores = \App\Models\ActivityLog::where('action', 'transfer_restored_from_archive')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
        
        return response()->json([
            'success' => true,
            'restores' => $restores
        ]);
    }

    /**
     * Run archive cleanup (remove old archives)
     */
    public function runArchiveCleanup(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'years' => 'required|integer|min:1|max:10',
            'confirmation' => 'required|in:CLEANUP_ARCHIVES'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $result = $this->archiveService->deleteOldArchives($request->years);
        
        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$result['deleted_count']} archives older than {$request->years} years.",
            'deleted_count' => $result['deleted_count'],
            'cutoff_year' => $result['cutoff_year']
        ]);
    }

    /**
     * Get archives by year (API endpoint)
     */
    public function getArchivesByYear(Request $request, $year)
    {
        $this->authorizeAdminAccess($request);
        
        $archives = PropertyOwnershipTransferArchive::where('archive_year', $year)
            ->with(['property', 'currentLandlord', 'newLandlord'])
            ->paginate($request->get('per_page', 20));
        
        return response()->json([
            'success' => true,
            'archives' => $archives,
            'year' => $year
        ]);
    }

    /**
     * Get archive summary by year range
     */
    public function getArchiveSummaryByYearRange(Request $request, $startYear, $endYear)
    {
        $this->authorizeAdminAccess($request);
        
        $summary = PropertyOwnershipTransferArchive::whereBetween('archive_year', [$startYear, $endYear])
            ->select(
                'archive_year',
                \DB::raw('COUNT(*) as count'),
                \DB::raw('SUM(CASE WHEN status = "completed" THEN sale_amount ELSE 0 END) as total_value'),
                \DB::raw('SUM(CASE WHEN metadata->is_reversal = true THEN 1 ELSE 0 END) as reversal_count')
            )
            ->groupBy('archive_year')
            ->orderBy('archive_year')
            ->get();
        
        return response()->json([
            'success' => true,
            'summary' => $summary,
            'start_year' => $startYear,
            'end_year' => $endYear
        ]);
    }

    /**
     * Compare archive statistics across years
     */
    public function compareArchiveYears(Request $request, $years)
    {
        $this->authorizeAdminAccess($request);
        
        $yearArray = explode(',', $years);
        $comparison = [];
        
        foreach ($yearArray as $year) {
            $comparison[$year] = [
                'total' => PropertyOwnershipTransferArchive::where('archive_year', $year)->count(),
                'completed' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                    ->where('status', 'completed')->count(),
                'rejected' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                    ->where('status', 'rejected')->count(),
                'cancelled' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                    ->where('status', 'cancelled')->count(),
                'total_value' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                    ->where('status', 'completed')->sum('sale_amount'),
                'reversal_count' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                    ->where('metadata->is_reversal', true)->count(),
                'regular_count' => PropertyOwnershipTransferArchive::where('archive_year', $year)
                    ->where(function($q) {
                        $q->whereNull('metadata->is_reversal')
                          ->orWhere('metadata->is_reversal', false);
                    })->count()
            ];
        }
        
        return response()->json([
            'success' => true,
            'comparison' => $comparison,
            'years' => $yearArray
        ]);
    }

    /**
     * Get archive storage usage
     */
    public function getArchiveStorageUsage(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $storageUsage = $this->calculateArchiveStorageUsage();
        
        return response()->json([
            'success' => true,
            'storage_usage_mb' => $storageUsage,
            'storage_usage_gb' => round($storageUsage / 1024, 2)
        ]);
    }

    /**
     * Calculate archive storage usage
     */
    private function calculateArchiveStorageUsage(): float
    {
        $archives = PropertyOwnershipTransferArchive::all();
        $totalBytes = 0;
        
        foreach ($archives as $archive) {
            if ($archive->document_url) {
                try {
                    $size = Storage::disk('public')->size($archive->document_url);
                    $totalBytes += $size;
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
            
            if ($archive->certificate_url) {
                try {
                    $size = Storage::disk('public')->size($archive->certificate_url);
                    $totalBytes += $size;
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
        }
        
        return round($totalBytes / (1024 * 1024), 2);
    }

    /**
     * Get archive statistics (internal method)
     */
    private function getArchiveStatistics()
    {
        return [
            'total_archived' => PropertyOwnershipTransferArchive::count(),
            'by_year' => PropertyOwnershipTransferArchive::select('archive_year', \DB::raw('COUNT(*) as count'))
                ->groupBy('archive_year')
                ->orderBy('archive_year', 'desc')
                ->get(),
            'by_status' => PropertyOwnershipTransferArchive::select('status', \DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get(),
            'total_value' => PropertyOwnershipTransferArchive::where('status', 'completed')->sum('sale_amount'),
            'oldest_archive' => PropertyOwnershipTransferArchive::min('archive_year'),
            'newest_archive' => PropertyOwnershipTransferArchive::max('archive_year'),
            'reversal_count' => PropertyOwnershipTransferArchive::where('metadata->is_reversal', true)->count(),
            'regular_count' => PropertyOwnershipTransferArchive::where(function($q) {
                $q->whereNull('metadata->is_reversal')
                  ->orWhere('metadata->is_reversal', false);
            })->count(),
        ];
    }

    /**
     * Authorize admin access
     */
    private function authorizeAdminAccess(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADMIN])) {
            if ($request->expectsJson()) {
                abort(403, 'Admin privileges required.');
            }
            abort(403, 'You do not have permission to access this section.');
        }
    }
}