<?php
// app/Events/UserDeleted.php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeleted
{
    use Dispatchable, SerializesModels;
    
    public $user;
    public $deleter;
    public $reason;
    
    public function __construct(User $user, User $deleter, ?string $reason = null)
    {
        $this->user = $user;
        $this->deleter = $deleter;
        $this->reason = $reason;
    }
}