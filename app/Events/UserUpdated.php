<?php
// app/Events/UserUpdated.php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUpdated
{
    use Dispatchable, SerializesModels;
    
    public $user;
    public $updater;
    public $changes;
    
    public function __construct(User $user, User $updater, array $changes = [])
    {
        $this->user = $user;
        $this->updater = $updater;
        $this->changes = $changes;
    }
}