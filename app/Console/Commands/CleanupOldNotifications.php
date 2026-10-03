<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldNotifications extends Command
{
    protected $signature = 'notifications:cleanup {--days=30 : Remove notifications older than X days}';
    protected $description = 'Clean up old notifications from database';

    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $deleted = DB::table('notifications')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Deleted {$deleted} notifications older than {$days} days.");
        
        return Command::SUCCESS;
    }
}