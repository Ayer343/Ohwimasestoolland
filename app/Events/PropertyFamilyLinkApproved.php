<?php

namespace App\Events;

use App\Models\PropertyFamilyLink;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyFamilyLinkApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PropertyFamilyLink $link,
        public User $admin
    ) {}
}