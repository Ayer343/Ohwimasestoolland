<?php

namespace App\Traits;

use App\Services\BillingAccessService;

trait ChecksBillingAccess
{
    /**
     * Throws 402/403 if the current user can't perform a write action.
     */
    protected function assertBillingAllowsWrite(string $operation = 'this action'): void
    {
        app(BillingAccessService::class)->assertCanPerformWrite($operation);
    }

    /**
     * Boolean variant — useful for hiding UI or conditional logic.
     */
    protected function billingAllowsWrite(string $operation = 'this action'): bool
    {
        return app(BillingAccessService::class)->canPerformWrite(null, $operation);
    }

    /**
     * Convenience: is the current admin in read-only mode?
     */
    protected function isBillingReadOnly(): bool
    {
        return app(BillingAccessService::class)->shouldRestrictAdminToReadOnly();
    }
}