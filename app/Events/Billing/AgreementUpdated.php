<?php

namespace App\Events\Billing;

use App\Models\AdminBillingRecord;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgreementUpdated
{
    use Dispatchable, SerializesModels;

    public $agreement;
    public $user;
    public $changeReason;

    public function __construct(AdminBillingRecord $agreement, User $user, string $changeReason)
    {
        $this->agreement = $agreement;
        $this->user = $user;
        $this->changeReason = $changeReason;
    }
}