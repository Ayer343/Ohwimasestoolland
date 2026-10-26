<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Services\AdCampaignService;

class AdPublicController extends Controller
{
    public function __construct(private AdCampaignService $adService) {}

    public function click(string $uuid)
    {
        $campaign = AdCampaign::where('uuid', $uuid)->firstOrFail();

        // Only count clicks on live campaigns
        if ($campaign->isActive()) {
            $campaign->increment('clicks_count');
        }

        $unit = $campaign->unit;
        if (!$unit) {
            return redirect()->route('welcome');
        }

        return redirect()->route('property-units.public-show', $unit->id);
    }
}