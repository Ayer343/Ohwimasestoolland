<?php

namespace App\Events\Billing;

use App\Models\AdminBillingRecord;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgreementTerminated
{
    use Dispatchable, SerializesModels;

    public $agreement;
    public $user;
    public $terminationReason;

    public function __construct(AdminBillingRecord $agreement, User $user, string $terminationReason)
    {
        $this->agreement = $agreement;
        $this->user = $user;
        $this->terminationReason = $terminationReason;
    }
}