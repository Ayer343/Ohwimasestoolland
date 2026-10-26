<?php
// app/Providers/FamilyLinkMiddlewareServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Auth\Middleware\Authorize;
use App\Http\Middleware\VerifyFamilyLinkStillActive;
use App\Http\Middleware\EnsureFamilyLinkPermission;

class FamilyLinkMiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Establishes the priority ordering for the family-link middleware
     * pair. Runs on the container's `booted` hook, which fires AFTER
     * the HTTP Kernel has been resolved and AFTER Laravel's own
     * middleware-priority list has been initialised.
     *
     * Why not `bootstrap/app.php`?
     *   On Laravel 11/12.0.x, `appendToPriorityList()` calls made from
     *   inside the `withMiddleware()` closure are silently dropped
     *   when the fluent builder's internal flush timing doesn't line
     *   up. Mutating the Kernel's priority list directly, after it has
     *   been constructed, sidesteps that entirely and works on every
     *   patch version.
     *
     * Resulting order:
     *
     *     … → AuthenticatesRequests
     *       → SubstituteBindings         ← {property} is now a model
     *       → VerifyFamilyLinkStillActive ← establishes link context
     *       → EnsureFamilyLinkPermission  ← inspects that context
     *       → Authorize → …
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            /** @var Kernel $kernel */
            $kernel = $this->app->make(Kernel::class);

            // active → immediately after SubstituteBindings
            $kernel->addToMiddlewarePriorityAfter(
                SubstituteBindings::class,
                VerifyFamilyLinkStillActive::class
            );

            // permission → immediately after active
            $kernel->addToMiddlewarePriorityAfter(
                VerifyFamilyLinkStillActive::class,
                EnsureFamilyLinkPermission::class
            );
        });
    }
}