<?php
// app/Events/PropertyFamilyLinkRejected.php

namespace App\Events;

use App\Models\PropertyFamilyLink;   // ← ADD THIS
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyFamilyLinkRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PropertyFamilyLink $link,
        public User $admin,
        public string $reason,
    ) {}
}