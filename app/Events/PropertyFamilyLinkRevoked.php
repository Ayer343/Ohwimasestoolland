<?php

namespace App\Events;

use App\Models\PropertyFamilyLink;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyFamilyLinkRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PropertyFamilyLink $link,
        public User $actor,
        public ?string $reason = null
    ) {}
}