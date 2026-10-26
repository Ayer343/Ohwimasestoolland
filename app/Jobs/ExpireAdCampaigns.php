<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireAdCampaigns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expired = AdCampaign::expired()->get();

        foreach ($expired as $campaign) {
            $campaign->expire();
        }

        Log::info('Ad campaign expiry run', ['count' => $expired->count()]);
    }
}