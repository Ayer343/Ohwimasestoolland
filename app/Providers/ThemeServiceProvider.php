<?php

namespace App\Providers;

use App\Contracts\Theme\ThemeRepositoryInterface;
use App\Contracts\Theme\ThemeServiceInterface;
use App\Repositories\Theme\ThemeRepository;
use App\Services\Theme\Support\SidebarThemeRegistry;
use App\Services\Theme\ThemeCssGenerator;
use App\Services\Theme\ThemeService;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public array $singletons = [
        SidebarThemeRegistry::class => SidebarThemeRegistry::class,
        ThemeCssGenerator::class    => ThemeCssGenerator::class,
        ThemeRepository::class      => ThemeRepository::class,
        ThemeService::class         => ThemeService::class,
    ];

    public function register(): void
    {
        // Aliases so interface-typed dependencies resolve to the
        // same singleton as their concrete counterparts.
        $this->app->alias(ThemeRepository::class, ThemeRepositoryInterface::class);
        $this->app->alias(ThemeService::class, ThemeServiceInterface::class);
    }
}