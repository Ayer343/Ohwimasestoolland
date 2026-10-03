<?php

namespace App\Events\Billing;

use App\Models\AdminBillingRecord;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public $payment;
    public $agreement;
    public $user;

    public function __construct($payment, AdminBillingRecord $agreement, User $user)
    {
        $this->payment = $payment;
        $this->agreement = $agreement;
        $this->user = $user;
    }
}