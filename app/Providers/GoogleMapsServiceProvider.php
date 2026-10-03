<?php
// app/Providers/GoogleMapsServiceProvider.php

namespace App\Providers;

use App\Services\GoogleMapsService;
use Illuminate\Support\ServiceProvider;

class GoogleMapsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(GoogleMapsService::class, function ($app) {
            return new GoogleMapsService();
        });
    }

    public function boot()
    {
        //
    }
}