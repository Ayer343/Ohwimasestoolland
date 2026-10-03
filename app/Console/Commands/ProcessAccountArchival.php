<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAccountArchival extends Command
{
    protected $signature = 'accounts:process-archival';
    protected $description = 'Process account archival for landlords with no properties';

    public function handle()
    {
        $this->info('Starting account archival process...');
        
        // Find landlords with no properties and scheduled archival date passed
        $usersToArchive = User::where('type', User::TYPE_LANDLORD)
            ->whereDoesntHave('properties')
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->where('status', '!=', User::STATUS_ARCHIVED)
            ->get();
        
        $this->info("Found {$usersToArchive->count()} users scheduled for archival.");
        
        $archived = 0;
        $failed = 0;
        
        foreach ($usersToArchive as $user) {
            $this->line("Processing user: {$user->id} - {$user->name}");
            
            // Double-check they still have no properties
            if (Property::where('landlord_id', $user->id)->exists()) {
                $this->warn("User {$user->id} now has properties. Skipping archival.");
                continue;
            }
            
            // Attempt archival
            if ($user->archive('no_properties_after_grace_period')) {
                $archived++;
                $this->info("✓ User {$user->id} archived successfully.");
            } else {
                $failed++;
                $this->error("✗ Failed to archive user {$user->id}");
            }
        }
        
        $this->info("Archival process complete. Archived: {$archived}, Failed: {$failed}");
        
        // Send summary report to admins
        $this->sendArchivalSummaryReport($archived, $failed);
        
        return 0;
    }
    
    /**
     * Send summary report to admins
     */
    private function sendArchivalSummaryReport(int $archived, int $failed): void
    {
        try {
            $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
                ->where('status', User::STATUS_ACTIVE)
                ->get();
            
            foreach ($admins as $admin) {
                \Mail::send('emails.archival-summary', [
                    'archived_count' => $archived,
                    'failed_count' => $failed,
                    'date' => now()
                ], function($message) use ($admin) {
                    $message->to($admin->email)
                            ->subject('Account Archival Summary Report');
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send archival summary report', [
                'error' => $e->getMessage()
            ]);
        }
    }
}