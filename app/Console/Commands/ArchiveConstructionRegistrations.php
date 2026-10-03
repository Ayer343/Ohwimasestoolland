<?php

namespace App\Console\Commands;

use App\Models\LandlordConstructionRegistration;
use App\Models\ArchivedConstructionRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveConstructionRegistrations extends Command
{
    protected $signature = 'registrations:archive 
                            {--year= : Specific year to archive (default: previous year)}
                            {--dry-run : Preview what will be archived without actually moving}
                            {--force : Force archive even if not January}
                            {--permanent-delete : Permanently delete from archive after archiving}';

    protected $description = 'Archive old construction registrations to archive table and remove from main table';

    public function handle()
    {
        $year = $this->option('year') ?? now()->subYear()->year;
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $permanentDelete = $this->option('permanent-delete');

        // Check if we should run (only in January unless forced)
        if (!$force && now()->month !== 1) {
            $this->warn('Archiving should only run in January. Use --force to override.');
            return 1;
        }

        $this->info("Starting registration archiving for year: {$year}");
        
        // Find registrations to archive (completed statuses from previous year)
        $registrations = LandlordConstructionRegistration::whereYear('created_at', $year)
            ->whereIn('status', [
                LandlordConstructionRegistration::STATUS_APPROVED,
                LandlordConstructionRegistration::STATUS_REJECTED,
                LandlordConstructionRegistration::STATUS_CANCELLED
            ])
            ->get();

        if ($registrations->isEmpty()) {
            $this->info("No registrations found to archive for year {$year}");
            return 0;
        }

        $this->info("Found {$registrations->count()} registrations to archive");

        if ($dryRun) {
            $this->table(
                ['ID', 'Name', 'Property', 'Status', 'Created At'],
                $registrations->map(fn($r) => [
                    $r->id,
                    $r->name,
                    $r->property_name ?? 'N/A',
                    $r->status,
                    $r->created_at->format('Y-m-d')
                ])
            );
            $this->info("Dry run completed. No data was moved.");
            return 0;
        }

        // Process archiving in batches
        $archived = 0;
        $failed = 0;
        $permanentlyDeleted = 0;

        DB::beginTransaction();

        try {
            foreach ($registrations as $registration) {
                try {
                    // Create archive record
                    $archivedRecord = ArchivedConstructionRegistration::create([
                        // Copy all attributes from original
                        'registration_type' => $registration->registration_type,
                        'purpose' => $registration->purpose,
                        'name' => $registration->name,
                        'email' => $registration->email,
                        'primary_phone' => $registration->primary_phone,
                        'additional_phones' => $registration->additional_phones,
                        'landlord_id' => $registration->landlord_id,
                        'property_name' => $registration->property_name,
                        'plot_number' => $registration->plot_number,
                        'street_name' => $registration->street_name,
                        'digital_address' => $registration->digital_address,
                        'land_description' => $registration->land_description,
                        'land_ownership_document' => $registration->land_ownership_document,
                        'zone' => $registration->zone,
                        'section' => $registration->section,
                        'property_type' => $registration->property_type,
                        'custom_property_type' => $registration->custom_property_type,
                        'property_status' => $registration->property_status,
                        'estimated_bedrooms' => $registration->estimated_bedrooms,
                        'has_plans' => $registration->has_plans,
                        'estimated_completion' => $registration->estimated_completion,
                        'construction_documents' => $registration->construction_documents,
                        'existing_property_type' => $registration->existing_property_type,
                        'existing_custom_property_type' => $registration->existing_custom_property_type,
                        'existing_property_status' => $registration->existing_property_status,
                        'existing_bedrooms' => $registration->existing_bedrooms,
                        'existing_bathrooms' => $registration->existing_bathrooms,
                        'year_built' => $registration->year_built,
                        'property_photos' => $registration->property_photos,
                        'property_documents' => $registration->property_documents,
                        'has_tenants' => $registration->has_tenants,
                        'tenant_count' => $registration->tenant_count,
                        'tenant_data' => $registration->tenant_data,
                        'status' => $registration->status,
                        'access_token' => $registration->access_token,
                        'submitted_at' => $registration->submitted_at,
                        'reviewed_at' => $registration->reviewed_at,
                        'reviewed_by' => $registration->reviewed_by,
                        'approved_property_id' => $registration->approved_property_id,
                        'cancelled_at' => $registration->cancelled_at,
                        'rejection_reason' => $registration->rejection_reason,
                        'admin_notes' => $registration->admin_notes,
                        'info_requested' => $registration->info_requested,
                        'assigned_to' => $registration->assigned_to,
                        'assigned_at' => $registration->assigned_at,
                        
                        // Archive metadata
                        'archived_at' => now(),
                        'archive_year' => $year,
                        'archive_reason' => 'Year-end archiving',
                        'archived_by' => null, // System archive
                        'original_id' => $registration->id,
                        'original_created_at' => $registration->created_at,
                        'original_updated_at' => $registration->updated_at,
                        'original_deleted_at' => $registration->deleted_at,
                    ]);

                    // Delete from main table
                    $registration->forceDelete(); // Use forceDelete to bypass soft delete
                    
                    $archived++;
                    $this->info("Archived registration #{$registration->id}: {$registration->name}");

                    // If permanent delete flag is set, also delete from archive
                    if ($permanentDelete) {
                        $archivedRecord->delete();
                        $permanentlyDeleted++;
                        $this->info("Permanently deleted from archive: {$registration->name}");
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $this->error("Failed to archive registration #{$registration->id}: " . $e->getMessage());
                    Log::error("Archive failed for registration {$registration->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            $this->info("\n=== ARCHIVING COMPLETED ===");
            $this->info("Total processed: {$registrations->count()}");
            $this->info("Successfully archived: {$archived}");
            $this->info("Failed: {$failed}");
            if ($permanentDelete) {
                $this->info("Permanently deleted from archive: {$permanentlyDeleted}");
            }

            // Log summary
            Log::info("Registration archiving completed", [
                'year' => $year,
                'processed' => $registrations->count(),
                'archived' => $archived,
                'failed' => $failed,
                'permanently_deleted' => $permanentlyDeleted
            ]);

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Archiving failed: " . $e->getMessage());
            Log::error("Registration archiving failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}