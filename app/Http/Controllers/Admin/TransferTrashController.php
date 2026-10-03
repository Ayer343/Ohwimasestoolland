<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class TransferTrashController extends Controller
{
    /**
     * Display trashed transfers (UNIFIED - includes both regular and reversal records)
     */
    public function trash(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        Cache::forget('trashed_transfers_' . md5(serialize($request->all())));
        Cache::forget('trashed_transfers_stats');

        $query = PropertyOwnershipTransfer::onlyTrashed()
            ->with([
                'property' => function($q) {
                    $q->withTrashed()->select('id', 'property_name', 'registration_pattern', 'street_name', 'zone');
                },
                'currentLandlord' => function($q) {
                    $q->withTrashed()->select('id', 'name', 'email', 'phone');
                },
                'newLandlord' => function($q) {
                    $q->withTrashed()->select('id', 'name', 'email', 'phone');
                },
                'originalTransfer' => function($q) {
                    $q->withTrashed()->select('id', 'document_reference', 'status');
                },
                'reversalTransfer' => function($q) {
                    $q->withTrashed()->select('id', 'document_reference', 'status');
                }
            ])
            ->orderBy('deleted_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            switch ($request->type) {
                case 'regular':
                    $query->where(function($q) {
                        $q->whereNull('metadata->is_reversal')
                          ->orWhere('metadata->is_reversal', false);
                    });
                    break;
                case 'reversal':
                    $query->where('metadata->is_reversal', true);
                    break;
                case 'reversal_with_original':
                    $query->where(function($q) {
                        $q->where('metadata->is_reversal', true)
                          ->whereNotNull('metadata->original_transfer_id');
                    });
                    break;
                case 'orphaned_reversals':
                    $query->where(function($q) {
                        $q->where('metadata->is_reversal', true)
                          ->whereNull('metadata->original_transfer_id');
                    });
                    break;
            }
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('deleted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('deleted_at', '<=', $request->date_to);
        }

        if ($request->filled('deleted_by')) {
            $query->where('deleted_by', $request->deleted_by);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('document_reference', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('new_owner_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('new_owner_email', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('new_owner_phone', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('property', function($propertyQuery) use ($searchTerm) {
                      $propertyQuery->where('property_name', 'LIKE', "%{$searchTerm}%")
                                   ->orWhere('registration_pattern', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        $transfers = $query->paginate($request->get('per_page', 20));

        $trashStats = $this->getUnifiedTrashStats();
        $deleters = $this->getDeletersList();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'transfers' => $transfers,
                'statistics' => $trashStats,
                'deleters' => $deleters,
                'filters' => [
                    'available_types' => [
                        'all' => 'All Transfers',
                        'regular' => 'Regular Transfers Only',
                        'reversal' => 'Reversal Records Only',
                        'reversal_with_original' => 'Reversals with Original',
                        'orphaned_reversals' => 'Orphaned Reversals'
                    ]
                ],
                'pagination' => [
                    'total' => $transfers->total(),
                    'per_page' => $transfers->perPage(),
                    'current_page' => $transfers->currentPage(),
                    'last_page' => $transfers->lastPage(),
                ]
            ]);
        }

        return view('admin.ownership-transfers.trash', compact('transfers', 'trashStats', 'deleters'));
    }

    /**
     * Get unified trash statistics (includes reversal data)
     */
    private function getUnifiedTrashStats(): array
    {
        return [
            'total_trashed' => PropertyOwnershipTransfer::onlyTrashed()->count(),
            'regular_transfers' => PropertyOwnershipTransfer::onlyTrashed()
                ->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                })->count(),
            'reversal_records' => PropertyOwnershipTransfer::onlyTrashed()
                ->where('metadata->is_reversal', true)
                ->count(),
            'reversals_with_original' => PropertyOwnershipTransfer::onlyTrashed()
                ->where('metadata->is_reversal', true)
                ->whereNotNull('metadata->original_transfer_id')
                ->count(),
            'orphaned_reversals' => PropertyOwnershipTransfer::onlyTrashed()
                ->where('metadata->is_reversal', true)
                ->whereNull('metadata->original_transfer_id')
                ->count(),
            'by_status' => [
                'pending' => PropertyOwnershipTransfer::onlyTrashed()->where('status', PropertyOwnershipTransfer::STATUS_PENDING)->count(),
                'approved' => PropertyOwnershipTransfer::onlyTrashed()->where('status', PropertyOwnershipTransfer::STATUS_APPROVED)->count(),
                'rejected' => PropertyOwnershipTransfer::onlyTrashed()->where('status', PropertyOwnershipTransfer::STATUS_REJECTED)->count(),
                'cancelled' => PropertyOwnershipTransfer::onlyTrashed()->where('status', PropertyOwnershipTransfer::STATUS_CANCELLED)->count(),
                'completed' => PropertyOwnershipTransfer::onlyTrashed()->where('status', PropertyOwnershipTransfer::STATUS_COMPLETED)->count(),
            ],
            'by_date' => [
                'last_7_days' => PropertyOwnershipTransfer::onlyTrashed()->where('deleted_at', '>=', now()->subDays(7))->count(),
                'last_30_days' => PropertyOwnershipTransfer::onlyTrashed()->where('deleted_at', '>=', now()->subDays(30))->count(),
                'last_90_days' => PropertyOwnershipTransfer::onlyTrashed()->where('deleted_at', '>=', now()->subDays(90))->count(),
                'older_than_30_days' => PropertyOwnershipTransfer::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->count(),
                'older_than_90_days' => PropertyOwnershipTransfer::onlyTrashed()->where('deleted_at', '<', now()->subDays(90))->count(),
            ],
            'by_user' => $this->getDeletersList(),
            'storage_used_mb' => $this->calculateTrashStorageUsage(),
            'reversal_storage_used_mb' => $this->calculateReversalStorageUsage(),
        ];
    }

    /**
     * Get list of users who have deleted transfers (for filter)
     */
    private function getDeletersList()
    {
        $deleters = PropertyOwnershipTransfer::onlyTrashed()
            ->select('deleted_by')
            ->whereNotNull('deleted_by')
            ->distinct()
            ->get()
            ->map(function($item) {
                $user = User::withTrashed()->find($item->deleted_by);
                return [
                    'id' => $item->deleted_by,
                    'name' => $user ? ($user->name ?? 'Unknown User') : 'Unknown User'
                ];
            })
            ->values()
            ->toArray();

        return $deleters;
    }

    /**
     * Restore a single trashed transfer
     */
    public function restore(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::withTrashed()
            ->whereNotNull('deleted_at')
            ->findOrFail($transferId);

        if (!$transfer->canBeRestored()) {
            return $this->errorResponse($request, 'This record cannot be restored.');
        }

        DB::beginTransaction();

        try {
            if (!$transfer->property) {
                throw new \Exception('Associated property no longer exists. Cannot restore.');
            }

            if (!$transfer->isReversalRecord()) {
                $existingTransfer = PropertyOwnershipTransfer::where('property_id', $transfer->property_id)
                    ->where('status', PropertyOwnershipTransfer::STATUS_PENDING)
                    ->where('id', '!=', $transfer->id)
                    ->first();

                if ($existingTransfer && $transfer->status === PropertyOwnershipTransfer::STATUS_PENDING) {
                    throw new \Exception('There is already a pending transfer request for this property.');
                }
            }

            if (Schema::hasColumn('property_ownership_transfers', 'deleted_by')) {
                $transfer->deleted_by = null;
            }

            $transfer->restore();

            $metadata = $transfer->metadata ?? [];
            $metadata['restored'] = [
                'restored_at' => now()->toISOString(),
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()->name,
                'ip_address' => $request->ip(),
                'is_reversal_record' => $transfer->isReversalRecord()
            ];
            unset($metadata['soft_deleted']);
            $transfer->metadata = $metadata;
            $transfer->save();

            if ($transfer->isReversalRecord()) {
                $originalTransferId = $transfer->metadata['original_transfer_id'] ?? null;
                if ($originalTransferId) {
                    $originalTransfer = PropertyOwnershipTransfer::onlyTrashed()->find($originalTransferId);
                    if ($originalTransfer) {
                        $originalTransfer->restore();
                    }
                }
            }

            if ($transfer->reversalTransfer && $transfer->reversalTransfer->trashed()) {
                $transfer->reversalTransfer->restore();
            }

            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');

            DB::commit();

            $message = $transfer->isReversalRecord() 
                ? 'Reversal record restored successfully.' 
                : 'Transfer restored successfully.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'transfer' => $transfer->fresh(),
                    'is_reversal' => $transfer->isReversalRecord()
                ]);
            }

            return redirect()->route('admin.ownership-transfers.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to restore: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore multiple transfers
     */
    public function bulkRestore(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'restore_originals' => 'boolean'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        $transferIds = $request->transfer_ids;
        $restoreOriginals = $request->boolean('restore_originals', false);
        $restored = 0;
        $originalsRestored = 0;
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($transferIds as $transferId) {
                $transfer = PropertyOwnershipTransfer::onlyTrashed()->find($transferId);
                
                if (!$transfer) {
                    $failed[] = ['id' => $transferId, 'reason' => 'Record not found in trash'];
                    continue;
                }
                
                if (!$transfer->canBeRestored()) {
                    $failed[] = ['id' => $transferId, 'reason' => 'Cannot be restored'];
                    continue;
                }

                if (!$transfer->property) {
                    $failed[] = ['id' => $transferId, 'reason' => 'Property no longer exists'];
                    continue;
                }

                $transfer->restore();
                
                $metadata = $transfer->metadata ?? [];
                $metadata['restored'] = [
                    'restored_at' => now()->toISOString(),
                    'restored_by' => auth()->id(),
                    'restored_by_name' => auth()->user()->name,
                    'bulk_restore' => true,
                    'is_reversal_record' => $transfer->isReversalRecord()
                ];
                unset($metadata['soft_deleted']);
                $transfer->metadata = $metadata;
                $transfer->save();
                
                $this->clearTransferCache($transfer);
                $restored++;

                if ($restoreOriginals && $transfer->isReversalRecord()) {
                    $originalTransferId = $transfer->metadata['original_transfer_id'] ?? null;
                    if ($originalTransferId) {
                        $originalTransfer = PropertyOwnershipTransfer::onlyTrashed()->find($originalTransferId);
                        if ($originalTransfer) {
                            $originalTransfer->restore();
                            $originalsRestored++;
                        }
                    }
                }
            }

            if ($restored > 0) {
                $this->clearAllTransferCaches();
                Cache::forget('trashed_transfers_stats');
                
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'bulk_transfer_restore',
                    'description' => "Bulk restored {$restored} records from trash" . ($originalsRestored > 0 ? " (including {$originalsRestored} original transfers)" : ""),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'restored_count' => $restored,
                        'originals_restored' => $originalsRestored,
                        'failed_count' => count($failed),
                        'transfer_ids' => $transferIds,
                        'restored_originals' => $restoreOriginals
                    ]
                ]);
            }

            DB::commit();

            $message = "Bulk restore completed: {$restored} record(s) restored.";
            if ($originalsRestored > 0) {
                $message .= " {$originalsRestored} original transfer(s) also restored.";
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'restored_count' => $restored,
                    'originals_restored' => $originalsRestored,
                    'failed' => $failed
                ]);
            }

            return redirect()->route('admin.ownership-transfers.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to process bulk restore: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a single trashed transfer
     */
    public function forceDelete(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::withTrashed()
            ->whereNotNull('deleted_at')
            ->findOrFail($transferId);

        if (!$transfer->canBePermanentlyDeleted()) {
            return $this->errorResponse($request, 'This record cannot be permanently deleted.');
        }

        DB::beginTransaction();

        try {
            if ($transfer->document_url) {
                Storage::disk('public')->delete($transfer->document_url);
            }
            if ($transfer->certificate_url) {
                Storage::disk('public')->delete($transfer->certificate_url);
            }

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_permanent_delete',
                'description' => "Permanently deleted " . ($transfer->isReversalRecord() ? "reversal record" : "ownership transfer") . ": {$transfer->document_reference}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'transfer_id' => $transfer->id,
                    'document_reference' => $transfer->document_reference,
                    'status' => $transfer->status,
                    'is_reversal' => $transfer->isReversalRecord()
                ]
            ]);

            $transfer->forceDelete();
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');

            DB::commit();

            $message = $transfer->isReversalRecord() 
                ? 'Reversal record permanently deleted successfully.' 
                : 'Transfer permanently deleted successfully.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return redirect()->route('admin.ownership-transfers.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to permanently delete: ' . $e->getMessage());
        }
    }

    /**
     * Bulk permanently delete multiple transfers
     */
    public function bulkForceDelete(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'deletion_reason' => 'nullable|string|max:1000',
            'delete_originals' => 'boolean'
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $transferIds = $request->transfer_ids;
        $deleteOriginals = $request->boolean('delete_originals', false);
        $deleted = 0;
        $originalsDeleted = 0;
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($transferIds as $transferId) {
                $transfer = PropertyOwnershipTransfer::withTrashed()->find($transferId);
                
                if (!$transfer) {
                    $failed[] = ['id' => $transferId, 'reason' => 'Record not found'];
                    continue;
                }
                
                if ($transfer->document_url) {
                    Storage::disk('public')->delete($transfer->document_url);
                }
                if ($transfer->certificate_url) {
                    Storage::disk('public')->delete($transfer->certificate_url);
                }

                if ($deleteOriginals && $transfer->isReversalRecord()) {
                    $originalTransferId = $transfer->metadata['original_transfer_id'] ?? null;
                    if ($originalTransferId) {
                        $originalTransfer = PropertyOwnershipTransfer::withTrashed()->find($originalTransferId);
                        if ($originalTransfer) {
                            if ($originalTransfer->document_url) {
                                Storage::disk('public')->delete($originalTransfer->document_url);
                            }
                            if ($originalTransfer->certificate_url) {
                                Storage::disk('public')->delete($originalTransfer->certificate_url);
                            }
                            $originalTransfer->forceDelete();
                            $originalsDeleted++;
                        }
                    }
                }

                $transfer->forceDelete();
                $deleted++;
            }

            if ($deleted > 0) {
                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'bulk_transfer_permanent_delete',
                    'description' => "Bulk permanently deleted {$deleted} record(s)" . ($originalsDeleted > 0 ? " (including {$originalsDeleted} original transfers)" : ""),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'deleted_count' => $deleted,
                        'originals_deleted' => $originalsDeleted,
                        'failed_count' => count($failed),
                        'transfer_ids' => $transferIds,
                        'deletion_reason' => $request->deletion_reason
                    ]
                ]);

                $this->clearAllTransferCaches();
                Cache::forget('trashed_transfers_stats');
            }

            DB::commit();

            $message = "Bulk permanent deletion completed: {$deleted} record(s) deleted.";
            if ($originalsDeleted > 0) {
                $message .= " {$originalsDeleted} original transfer(s) also deleted.";
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deleted,
                    'originals_deleted' => $originalsDeleted,
                    'failed' => $failed
                ]);
            }

            return redirect()->route('admin.ownership-transfers.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process bulk permanent deletion: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to process bulk permanent deletion: ' . $e->getMessage());
        }
    }

    /**
     * Empty entire trash
     */
    public function emptyTrash(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $data = $request->json()->all();
        if (empty($data)) {
            $data = $request->all();
        }
        
        $confirmation = $request->input('confirmation') ?? ($data['confirmation'] ?? null);
        $olderThanDays = $request->input('older_than_days') ?? ($data['older_than_days'] ?? null);
        $deletionReason = $request->input('deletion_reason') ?? ($data['deletion_reason'] ?? null);
        $includeReversals = $request->input('include_reversals', true);
        $includeRegular = $request->input('include_regular', true);

        $validator = Validator::make(
            ['confirmation' => $confirmation, 'older_than_days' => $olderThanDays],
            [
                'confirmation' => 'required|in:DELETE_ALL',
                'older_than_days' => 'nullable|integer|min:1'
            ]
        );

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator);
        }

        DB::beginTransaction();

        try {
            $query = PropertyOwnershipTransfer::onlyTrashed();
            
            if (!$includeRegular && $includeReversals) {
                $query->where('metadata->is_reversal', true);
            } elseif ($includeRegular && !$includeReversals) {
                $query->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                });
            }
            
            if ($olderThanDays) {
                $cutoffDate = now()->subDays((int)$olderThanDays);
                $query->where('deleted_at', '<', $cutoffDate);
            }
            
            $transfersToDelete = $query->get();
            $deleteCount = $transfersToDelete->count();

            if ($deleteCount === 0) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No records found to delete.'
                    ], 404);
                }
                return redirect()->back()->with('info', 'No records found to delete.');
            }

            foreach ($transfersToDelete as $transfer) {
                if ($transfer->document_url) {
                    Storage::disk('public')->delete($transfer->document_url);
                }
                if ($transfer->certificate_url) {
                    Storage::disk('public')->delete($transfer->certificate_url);
                }
            }

            $deletedQuery = PropertyOwnershipTransfer::onlyTrashed();
            
            if (!$includeRegular && $includeReversals) {
                $deletedQuery->where('metadata->is_reversal', true);
            } elseif ($includeRegular && !$includeReversals) {
                $deletedQuery->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                });
            }
            
            if ($olderThanDays) {
                $deletedQuery->where('deleted_at', '<', now()->subDays((int)$olderThanDays));
            }
            
            $deletedCount = $deletedQuery->forceDelete();

            $regularCount = PropertyOwnershipTransfer::onlyTrashed()
                ->where(function($q) {
                    $q->whereNull('metadata->is_reversal')
                      ->orWhere('metadata->is_reversal', false);
                })->count();
                
            $reversalCount = PropertyOwnershipTransfer::onlyTrashed()
                ->where('metadata->is_reversal', true)
                ->count();

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'empty_transfer_trash',
                'description' => "Emptied ownership transfer trash: {$deletedCount} records permanently deleted",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'deleted_count' => $deletedCount,
                    'regular_remaining' => $regularCount,
                    'reversal_remaining' => $reversalCount,
                    'older_than_days' => $olderThanDays,
                    'deletion_reason' => $deletionReason,
                    'included_regular' => $includeRegular,
                    'included_reversals' => $includeReversals
                ]
            ]);

            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');

            DB::commit();

            $message = "Trash emptied successfully. {$deletedCount} records permanently deleted.";
            if ($regularCount > 0 || $reversalCount > 0) {
                $message .= " Remaining: {$regularCount} regular transfer(s), {$reversalCount} reversal record(s).";
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount,
                    'regular_remaining' => $regularCount,
                    'reversal_remaining' => $reversalCount
                ]);
            }

            return redirect()->route('admin.ownership-transfers.trash')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to empty trash: ' . $e->getMessage());
        }
    }

    /**
     * Preview trash contents
     */
    public function previewTrash(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransfer::onlyTrashed()
            ->with(['property', 'currentLandlord']);

        if ($request->filled('older_than_days')) {
            $cutoffDate = now()->subDays((int)$request->older_than_days);
            $query->where('deleted_at', '<', $cutoffDate);
        }

        $totalCount = $query->count();
        $preview = $query->limit(50)->get()->map(function($transfer) {
            return [
                'id' => $transfer->id,
                'document_reference' => $transfer->document_reference,
                'property_name' => $transfer->property->property_name ?? 'N/A',
                'current_owner' => $transfer->currentLandlord->name ?? 'N/A',
                'status' => $transfer->status_label,
                'is_reversal' => $transfer->isReversalRecord(),
                'deleted_at' => $transfer->deleted_at ? $transfer->deleted_at->format('Y-m-d H:i:s') : 'N/A',
                'days_in_trash' => $transfer->deleted_at ? $transfer->deleted_at->diffInDays(now()) : 0,
            ];
        });

        $storageUsage = $this->calculateTrashStorageUsage();

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'total_count' => $totalCount,
            'preview_limit' => 50,
            'showing_preview' => $totalCount > 50,
            'storage_usage_mb' => $storageUsage
        ]);
    }

    /**
     * Get trash statistics
     */
    public function trashStats(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $stats = Cache::remember('trashed_transfers_stats', 300, function() {
            return $this->getUnifiedTrashStats();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'statistics' => $stats,
                'last_updated' => now()->toISOString()
            ]);
        }

        return $stats;
    }

    /**
     * Soft delete a transfer (move to trash)
     */
    public function destroy(Request $request, $transferId)
    {
        $this->authorizeAdminAccess($request);
        
        $transfer = PropertyOwnershipTransfer::findOrFail($transferId);
        
        if (!$transfer->isReversalRecord() && $transfer->status === PropertyOwnershipTransfer::STATUS_COMPLETED) {
            return $this->errorResponse($request, 'Completed transfers cannot be deleted.');
        }
        
        DB::beginTransaction();
        
        try {
            if (Schema::hasColumn('property_ownership_transfers', 'deleted_by')) {
                $transfer->deleted_by = auth()->id();
            }
            
            $metadata = $transfer->metadata ?? [];
            $metadata['soft_deleted'] = [
                'deleted_at' => now()->toISOString(),
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'status_at_deletion' => $transfer->status,
                'deleted_reason' => $request->input('deletion_reason'),
                'ip_address' => $request->ip(),
                'is_reversal_record' => $transfer->isReversalRecord()
            ];
            $transfer->metadata = $metadata;
            $transfer->save();
            
            $transfer->delete();
            
            $this->clearTransferCache($transfer);
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            $recordType = $transfer->isReversalRecord() ? 'Reversal record' : 'Transfer';
            
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_soft_delete',
                'description' => "Moved {$recordType} #{$transfer->id} ({$transfer->document_reference}) to trash",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'transfer_id' => $transfer->id,
                    'document_reference' => $transfer->document_reference,
                    'status' => $transfer->status,
                    'is_reversal' => $transfer->isReversalRecord()
                ]
            ]);
            
            DB::commit();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "{$recordType} moved to trash successfully.",
                    'transfer_id' => $transfer->id,
                    'is_reversal' => $transfer->isReversalRecord()
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.index')
                ->with('success', "{$recordType} moved to trash successfully.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Bulk soft delete
     */
    public function bulkSoftDelete(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'transfer_ids' => 'required|array',
            'transfer_ids.*' => 'exists:property_ownership_transfers,id',
            'deletion_reason' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        $transferIds = $request->transfer_ids;
        $deleted = 0;
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($transferIds as $transferId) {
                $transfer = PropertyOwnershipTransfer::find($transferId);
                
                if (!$transfer) {
                    $failed[] = $transferId;
                    continue;
                }
                
                $metadata = $transfer->metadata ?? [];
                $metadata['soft_deleted'] = [
                    'deleted_at' => now()->toISOString(),
                    'deleted_by' => auth()->id(),
                    'deleted_by_name' => auth()->user()->name,
                    'status_at_deletion' => $transfer->status,
                    'deleted_reason' => $request->deletion_reason,
                    'ip_address' => $request->ip(),
                    'bulk_deletion' => true,
                    'is_reversal_record' => $transfer->isReversalRecord()
                ];
                $transfer->metadata = $metadata;
                $transfer->save();
                
                $transfer->delete();
                $deleted++;
            }

            DB::commit();

            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');

            $message = "{$deleted} record(s) moved to trash successfully.";

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deleted,
                    'failed' => $failed
                ]);
            }

            return redirect()->route('admin.ownership-transfers.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to move records to trash: ' . $e->getMessage());
        }
    }

    /**
     * Get fresh trashed count (bypass cache)
     */
    public function getFreshTrashedCount()
    {
        $this->authorizeAdminAccess(request());
        
        $regularCount = PropertyOwnershipTransfer::onlyTrashed()
            ->where(function($q) {
                $q->whereNull('metadata->is_reversal')
                  ->orWhere('metadata->is_reversal', false);
            })->count();
            
        $reversalCount = PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->count();
            
        $totalCount = $regularCount + $reversalCount;
        
        Cache::put('trashed_transfers_stats_fresh', $totalCount, 300);
        
        return response()->json([
            'total' => $totalCount,
            'regular' => $regularCount,
            'reversal' => $reversalCount
        ]);
    }

    /**
     * Export trashed transfers as CSV (unified)
     */
    public function exportTrash(Request $request, $format = 'csv')
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransfer::onlyTrashed()
            ->with(['property', 'currentLandlord', 'newLandlord']);

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

        if ($request->filled('date_from')) {
            $query->whereDate('deleted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('deleted_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('document_reference', 'LIKE', "%{$search}%")
                  ->orWhere('new_owner_name', 'LIKE', "%{$search}%");
            });
        }

        $transfers = $query->get();
        
        $filename = "trashed_transfers_" . date('Y-m-d_His') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($transfers) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'ID', 'Document Reference', 'Property Name', 'Current Owner', 'New Owner',
                'Status at Deletion', 'Transfer Date', 'Sale Amount', 'Deleted At', 'Days in Trash',
                'Document Type', 'Reason for Transfer', 'Rejection Reason', 'Deleted By',
                'Is Reversal Record', 'Original Transfer ID'
            ]);
            
            foreach ($transfers as $transfer) {
                $deletedByInfo = $transfer->metadata['soft_deleted']['deleted_by_name'] ?? 'Unknown';
                $originalTransferId = $transfer->metadata['original_transfer_id'] ?? '';
                
                fputcsv($file, [
                    $transfer->id,
                    $transfer->document_reference,
                    $transfer->property->property_name ?? 'N/A',
                    $transfer->currentLandlord->name ?? 'N/A',
                    $transfer->new_owner_name,
                    $transfer->status_label,
                    $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : 'N/A',
                    $transfer->sale_amount ? number_format($transfer->sale_amount, 2) : 'N/A',
                    $transfer->deleted_at ? $transfer->deleted_at->format('Y-m-d H:i:s') : 'N/A',
                    $transfer->deleted_at ? $transfer->deleted_at->diffInDays(now()) : 0,
                    $transfer->document_type_label,
                    $transfer->reason_for_transfer ?? 'N/A',
                    $transfer->rejection_reason ?? 'N/A',
                    $deletedByInfo,
                    $transfer->isReversalRecord() ? 'Yes' : 'No',
                    $originalTransferId
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * View trash activity log
     */
    public function trashActivityLog(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $activityLogs = ActivityLog::whereIn('action', [
                'transfer_soft_delete',
                'transfer_restore',
                'transfer_permanent_delete',
                'bulk_transfer_restore',
                'bulk_transfer_permanent_delete',
                'empty_transfer_trash',
                'reversal_transfer_restore',
                'reversal_transfer_permanent_delete',
                'bulk_reversal_restore',
                'bulk_reversal_permanent_delete'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'activity_logs' => $activityLogs
            ]);
        }

        return view('admin.ownership-transfers.trash-activity', compact('activityLogs'));
    }

    /**
     * Auto cleanup old trashed records (unified)
     */
    public function autoCleanupTrash(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $daysToKeep = config('ownership_transfer.trash_retention_days', 90);
        
        try {
            $deletedCount = PropertyOwnershipTransfer::cleanupOldTrashedRecords($daysToKeep);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Auto cleanup completed. {$deletedCount} records permanently deleted.",
                    'deleted_count' => $deletedCount,
                    'retention_days' => $daysToKeep
                ]);
            }
            
            return $deletedCount;
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auto cleanup failed: ' . $e->getMessage()
                ], 500);
            }
            
            return 0;
        }
    }

    /**
     * Get reversal transfers in trash
     */
    public function trashedReversals(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $query = PropertyOwnershipTransfer::onlyTrashed()
            ->where(function($q) {
                $q->where('metadata->is_reversal', true)
                  ->orWhereNotNull('reversal_transfer_id');
            })
            ->with([
                'property' => function($q) {
                    $q->withTrashed()->select('id', 'property_name');
                },
                'currentLandlord' => function($q) {
                    $q->withTrashed()->select('id', 'name');
                },
                'newLandlord' => function($q) {
                    $q->withTrashed()->select('id', 'name');
                }
            ]);
        
        if ($request->filled('original_transfer_id')) {
            $query->where('metadata->original_transfer_id', $request->original_transfer_id);
        }
        
        $reversals = $query->paginate($request->get('per_page', 20));
        
        $reversals->getCollection()->transform(function($reversal) {
            $originalTransferId = $reversal->metadata['original_transfer_id'] ?? null;
            if ($originalTransferId) {
                $reversal->original_transfer = PropertyOwnershipTransfer::withTrashed()
                    ->find($originalTransferId);
            }
            return $reversal;
        });
        
        $stats = [
            'total_reversals_in_trash' => PropertyOwnershipTransfer::onlyTrashed()
                ->where('metadata->is_reversal', true)
                ->count(),
            'reversals_by_original_status' => PropertyOwnershipTransfer::onlyTrashed()
                ->where('metadata->is_reversal', true)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get(),
            'storage_used_mb' => $this->calculateReversalStorageUsage()
        ];
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'reversals' => $reversals,
                'statistics' => $stats
            ]);
        }
        
        return view('admin.ownership-transfers.trashed-reversals', compact('reversals', 'stats'));
    }

    /**
     * Restore a reversal transfer (and optionally the original)
     */
    public function restoreReversal(Request $request, $reversalId)
    {
        $this->authorizeAdminAccess($request);
        
        $reversal = PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->findOrFail($reversalId);
        
        $restoreOriginal = $request->boolean('restore_original', false);
        
        DB::beginTransaction();
        
        try {
            $reversal->restore();
            
            $metadata = $reversal->metadata ?? [];
            $metadata['restored'] = [
                'restored_at' => now()->toISOString(),
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()->name,
                'is_reversal_restore' => true
            ];
            unset($metadata['soft_deleted']);
            $reversal->metadata = $metadata;
            $reversal->save();
            
            $originalTransferId = $reversal->metadata['original_transfer_id'] ?? null;
            if ($restoreOriginal && $originalTransferId) {
                $originalTransfer = PropertyOwnershipTransfer::onlyTrashed()->find($originalTransferId);
                if ($originalTransfer) {
                    $originalTransfer->restore();
                    
                    $originalMetadata = $originalTransfer->metadata ?? [];
                    $originalMetadata['restored'] = [
                        'restored_at' => now()->toISOString(),
                        'restored_by' => auth()->id(),
                        'restored_by_name' => auth()->user()->name,
                        'restored_with_reversal' => true
                    ];
                    unset($originalMetadata['soft_deleted']);
                    $originalTransfer->metadata = $originalMetadata;
                    $originalTransfer->save();
                }
            }
            
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'reversal_transfer_restore',
                'description' => "Restored reversal transfer #{$reversal->id}" . ($restoreOriginal ? " and original transfer #{$originalTransferId}" : ""),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'reversal_id' => $reversal->id,
                    'original_transfer_id' => $originalTransferId,
                    'restored_original' => $restoreOriginal
                ]
            ]);
            
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            DB::commit();
            
            $message = "Reversal transfer restored successfully.";
            if ($restoreOriginal && $originalTransferId) {
                $message .= " Original transfer also restored.";
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'reversal' => $reversal->fresh(),
                    'original_restored' => $restoreOriginal
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.trashed-reversals')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to restore reversal: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a reversal transfer
     */
    public function forceDeleteReversal(Request $request, $reversalId)
    {
        $this->authorizeAdminAccess($request);
        
        $reversal = PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->findOrFail($reversalId);
        
        $deleteOriginal = $request->boolean('delete_original', false);
        $originalTransferId = $reversal->metadata['original_transfer_id'] ?? null;
        
        DB::beginTransaction();
        
        try {
            if ($reversal->document_url) {
                Storage::disk('public')->delete($reversal->document_url);
            }
            if ($reversal->certificate_url) {
                Storage::disk('public')->delete($reversal->certificate_url);
            }
            
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'reversal_transfer_permanent_delete',
                'description' => "Permanently deleted reversal transfer #{$reversal->id}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'reversal_id' => $reversal->id,
                    'original_transfer_id' => $originalTransferId
                ]
            ]);
            
            $reversal->forceDelete();
            
            if ($deleteOriginal && $originalTransferId) {
                $originalTransfer = PropertyOwnershipTransfer::onlyTrashed()->find($originalTransferId);
                if ($originalTransfer) {
                    if ($originalTransfer->document_url) {
                        Storage::disk('public')->delete($originalTransfer->document_url);
                    }
                    if ($originalTransfer->certificate_url) {
                        Storage::disk('public')->delete($originalTransfer->certificate_url);
                    }
                    $originalTransfer->forceDelete();
                }
            }
            
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            DB::commit();
            
            $message = "Reversal transfer permanently deleted.";
            if ($deleteOriginal && $originalTransferId) {
                $message .= " Original transfer also deleted.";
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.trashed-reversals')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to delete reversal: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore reversal transfers
     */
    public function bulkRestoreReversals(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'reversal_ids' => 'required|array',
            'reversal_ids.*' => 'exists:property_ownership_transfers,id',
            'restore_originals' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        
        $reversalIds = $request->reversal_ids;
        $restoreOriginals = $request->boolean('restore_originals', false);
        $restored = 0;
        $originalsRestored = 0;
        $failed = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($reversalIds as $reversalId) {
                $reversal = PropertyOwnershipTransfer::onlyTrashed()
                    ->where('metadata->is_reversal', true)
                    ->find($reversalId);
                
                if (!$reversal) {
                    $failed[] = ['id' => $reversalId, 'reason' => 'Reversal not found in trash'];
                    continue;
                }
                
                $reversal->restore();
                
                $metadata = $reversal->metadata ?? [];
                $metadata['restored'] = [
                    'restored_at' => now()->toISOString(),
                    'restored_by' => auth()->id(),
                    'restored_by_name' => auth()->user()->name,
                    'bulk_restore' => true
                ];
                unset($metadata['soft_deleted']);
                $reversal->metadata = $metadata;
                $reversal->save();
                $restored++;
                
                if ($restoreOriginals) {
                    $originalTransferId = $reversal->metadata['original_transfer_id'] ?? null;
                    if ($originalTransferId) {
                        $originalTransfer = PropertyOwnershipTransfer::onlyTrashed()->find($originalTransferId);
                        if ($originalTransfer) {
                            $originalTransfer->restore();
                            $originalsRestored++;
                        }
                    }
                }
            }
            
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_reversal_restore',
                'description' => "Bulk restored {$restored} reversal transfers" . ($originalsRestored > 0 ? " and {$originalsRestored} original transfers" : ""),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'restored_reversals' => $restored,
                    'restored_originals' => $originalsRestored,
                    'reversal_ids' => $reversalIds
                ]
            ]);
            
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            DB::commit();
            
            $message = "Bulk restore completed: {$restored} reversal(s) restored.";
            if ($originalsRestored > 0) {
                $message .= " {$originalsRestored} original transfer(s) also restored.";
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'restored_reversals' => $restored,
                    'restored_originals' => $originalsRestored,
                    'failed' => $failed
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.trashed-reversals')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to process bulk restore: ' . $e->getMessage());
        }
    }

    /**
     * Bulk permanently delete reversal transfers
     */
    public function bulkForceDeleteReversals(Request $request)
    {
        $this->authorizeAdminAccess($request);
        
        $validator = Validator::make($request->all(), [
            'reversal_ids' => 'required|array',
            'reversal_ids.*' => 'exists:property_ownership_transfers,id',
            'delete_originals' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        
        $reversalIds = $request->reversal_ids;
        $deleteOriginals = $request->boolean('delete_originals', false);
        $deleted = 0;
        $originalsDeleted = 0;
        $failed = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($reversalIds as $reversalId) {
                $reversal = PropertyOwnershipTransfer::onlyTrashed()
                    ->where('metadata->is_reversal', true)
                    ->find($reversalId);
                
                if (!$reversal) {
                    $failed[] = ['id' => $reversalId, 'reason' => 'Reversal not found'];
                    continue;
                }
                
                if ($reversal->document_url) {
                    Storage::disk('public')->delete($reversal->document_url);
                }
                if ($reversal->certificate_url) {
                    Storage::disk('public')->delete($reversal->certificate_url);
                }
                
                $originalTransferId = $reversal->metadata['original_transfer_id'] ?? null;
                
                $reversal->forceDelete();
                $deleted++;
                
                if ($deleteOriginals && $originalTransferId) {
                    $originalTransfer = PropertyOwnershipTransfer::onlyTrashed()->find($originalTransferId);
                    if ($originalTransfer) {
                        if ($originalTransfer->document_url) {
                            Storage::disk('public')->delete($originalTransfer->document_url);
                        }
                        if ($originalTransfer->certificate_url) {
                            Storage::disk('public')->delete($originalTransfer->certificate_url);
                        }
                        $originalTransfer->forceDelete();
                        $originalsDeleted++;
                    }
                }
            }
            
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_reversal_permanent_delete',
                'description' => "Bulk permanently deleted {$deleted} reversal transfers" . ($originalsDeleted > 0 ? " and {$originalsDeleted} original transfers" : ""),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'deleted_reversals' => $deleted,
                    'deleted_originals' => $originalsDeleted,
                    'reversal_ids' => $reversalIds
                ]
            ]);
            
            $this->clearAllTransferCaches();
            Cache::forget('trashed_transfers_stats');
            
            DB::commit();
            
            $message = "Bulk permanent deletion completed: {$deleted} reversal(s) deleted.";
            if ($originalsDeleted > 0) {
                $message .= " {$originalsDeleted} original transfer(s) also deleted.";
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'deleted_reversals' => $deleted,
                    'deleted_originals' => $originalsDeleted,
                    'failed' => $failed
                ]);
            }
            
            return redirect()->route('admin.ownership-transfers.trashed-reversals')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, 'Failed to process bulk deletion: ' . $e->getMessage());
        }
    }

    /**
     * Calculate storage used by reversal records
     */
    private function calculateReversalStorageUsage(): float
    {
        $reversals = PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->get();
        
        $totalBytes = 0;
        
        foreach ($reversals as $reversal) {
            if ($reversal->document_url) {
                try {
                    $size = Storage::disk('public')->size($reversal->document_url);
                    $totalBytes += $size;
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
            
            if ($reversal->certificate_url) {
                try {
                    $size = Storage::disk('public')->size($reversal->certificate_url);
                    $totalBytes += $size;
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
        }
        
        return round($totalBytes / (1024 * 1024), 2);
    }

    /**
 * Get reversal statistics dashboard
 */
public function reversalTrashDashboard(Request $request)
{
    $this->authorizeAdminAccess($request);
    
    // Get counts directly without complex GROUP BY
    $totalReversalsInTrash = PropertyOwnershipTransfer::onlyTrashed()
        ->where('metadata->is_reversal', true)
        ->count();
    
    // Get reversals by status - using simple count queries instead of GROUP BY on JSON
    $reversalsByStatus = [];
    $statuses = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
    foreach ($statuses as $status) {
        $count = PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->where('status', $status)
            ->count();
        if ($count > 0) {
            $reversalsByStatus[] = (object)['status' => $status, 'count' => $count];
        }
    }
    
    // Get reversals by original status (only those with original_transfer_id)
    $reversalsByOriginalStatus = [];
    foreach ($statuses as $status) {
        $count = PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->whereNotNull('metadata->original_transfer_id')
            ->where('status', $status)
            ->count();
        if ($count > 0) {
            $reversalsByOriginalStatus[] = (object)['status' => $status, 'count' => $count];
        }
    }
    
    // Get reversals by date
    $reversalsByDate = [
        'today' => PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->whereDate('deleted_at', now()->today())
            ->count(),
        'this_week' => PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count(),
        'older_than_30_days' => PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->where('deleted_at', '<', now()->subDays(30))
            ->count(),
        'older_than_90_days' => PropertyOwnershipTransfer::onlyTrashed()
            ->where('metadata->is_reversal', true)
            ->where('deleted_at', '<', now()->subDays(90))
            ->count(),
    ];
    
    // Get storage usage
    $storageUsedMb = $this->calculateReversalStorageUsage();
    
    // Get orphaned reversals
    $orphanedReversals = PropertyOwnershipTransfer::onlyTrashed()
        ->where('metadata->is_reversal', true)
        ->whereNull('metadata->original_transfer_id')
        ->count();
    
    // Get reversals with original
    $reversalsWithOriginal = PropertyOwnershipTransfer::onlyTrashed()
        ->where('metadata->is_reversal', true)
        ->whereNotNull('metadata->original_transfer_id')
        ->count();
    
    // Get by deleted by - using collection after fetch to avoid GROUP BY issues
    $reversals = PropertyOwnershipTransfer::onlyTrashed()
        ->where('metadata->is_reversal', true)
        ->whereNotNull('metadata->soft_deleted->deleted_by_name')
        ->get(['metadata']);
    
    $byDeletedBy = [];
    foreach ($reversals as $reversal) {
        $deletedByName = $reversal->metadata['soft_deleted']['deleted_by_name'] ?? null;
        if ($deletedByName) {
            if (!isset($byDeletedBy[$deletedByName])) {
                $byDeletedBy[$deletedByName] = 0;
            }
            $byDeletedBy[$deletedByName]++;
        }
    }
    
    // Convert to array format
    $byDeletedByArray = [];
    foreach ($byDeletedBy as $name => $count) {
        $byDeletedByArray[] = (object)['deleted_by' => $name, 'count' => $count];
    }
    
    $stats = [
        'total_reversals_in_trash' => $totalReversalsInTrash,
        'reversals_by_status' => $reversalsByStatus,
        'reversals_by_original_status' => $reversalsByOriginalStatus,
        'reversals_by_date' => $reversalsByDate,
        'storage_used_mb' => $storageUsedMb,
        'orphaned_reversals' => $orphanedReversals,
        'reversals_with_original' => $reversalsWithOriginal,
        'by_deleted_by' => $byDeletedByArray
    ];
    
    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }
    
    return view('admin.ownership-transfers.reversal-trash-dashboard', compact('stats'));
}

    /**
     * Calculate trash storage usage (unified)
     */
    private function calculateTrashStorageUsage(): float
    {
        $trashedTransfers = PropertyOwnershipTransfer::onlyTrashed()->get();
        $totalBytes = 0;

        foreach ($trashedTransfers as $transfer) {
            if ($transfer->document_url) {
                try {
                    $size = Storage::disk('public')->size($transfer->document_url);
                    $totalBytes += $size;
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
            
            if ($transfer->certificate_url) {
                try {
                    $size = Storage::disk('public')->size($transfer->certificate_url);
                    $totalBytes += $size;
                } catch (\Exception $e) {
                    // File might not exist
                }
            }
        }

        return round($totalBytes / (1024 * 1024), 2);
    }

    /**
     * Clear cache for a specific transfer
     */
    private function clearTransferCache(PropertyOwnershipTransfer $transfer)
    {
        Cache::forget("transfer_{$transfer->id}");
        Cache::forget('ownership_transfer_stats');
    }

    /**
     * Clear all transfer-related caches
     */
    private function clearAllTransferCaches()
    {
        Cache::forget('ownership_transfer_stats');
        Cache::forget('trashed_transfers_stats');
        
        $keys = Cache::get('ownership_transfer_cache_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::put('ownership_transfer_cache_keys', [], 3600);
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

    /**
     * Error response helper
     */
    private function errorResponse(Request $request, $message, $code = 500)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $code);
        }
        return redirect()->back()->with('error', $message);
    }

    /**
     * Validation error response helper
     */
    private function validationErrorResponse(Request $request, $validator)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validation failed', 
                'errors' => $validator->errors()
            ], 422);
        }
        return redirect()->back()->withErrors($validator)->withInput();
    }
}