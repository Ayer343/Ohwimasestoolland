<?php

namespace App\Listeners\Theme;

use App\Events\Theme\ThemeUpdated;
use App\Events\Theme\ColorsUpdated;
use App\Services\Theme\ThemeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateThemeAssets implements ShouldQueue
{
    public function __construct(
        protected ThemeService $themeService
    ) {}

    public function handle($event): void
    {
        $settings = $event->settings ?? $event->themeService->getSettings($event->user);
        $colors = $event->colors ?? $event->themeService->getColors($event->user);
        
        // Generate and cache CSS
        $css = $event->themeService->generateThemeCss($settings, $colors);
        
        // You could also generate other assets here:
        // - Compiled CSS files
        // - Theme-specific JavaScript
        // - Images/Sprites
        // - JSON theme manifests
    }
}