<?php

namespace App\Events\Billing;

use App\Models\AdminBillingRecord;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgreementCreated
{
    use Dispatchable, SerializesModels;

    public $agreement;
    public $user;

    public function __construct(AdminBillingRecord $agreement, User $user)
    {
        $this->agreement = $agreement;
        $this->user = $user;
    }
}