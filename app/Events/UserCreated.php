<?php
// app/Events/UserCreated.php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, SerializesModels;
    
    public $user;
    public $creator;
    public $data;
    
    public function __construct(User $user, User $creator, array $data = [])
    {
        $this->user = $user;
        $this->creator = $creator;
        $this->data = $data;
    }
}