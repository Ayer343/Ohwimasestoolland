<?php

namespace App\Listeners\Theme;

use App\Events\Theme\ThemeUpdated;
use App\Events\Theme\ThemeReset;
use App\Events\Theme\ColorsUpdated;
use App\Repositories\Theme\ThemeCacheRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class ClearThemeCache implements ShouldQueue
{
    public function __construct(
        protected ThemeCacheRepository $cache
    ) {}

    public function handle($event): void
    {
        $this->cache->clearAll($event->user);
    }
}