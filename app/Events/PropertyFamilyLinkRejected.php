<?php

// app/Events/PropertyFamilyLinkRejected.php
class PropertyFamilyLinkRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PropertyFamilyLink $link,
        public User $admin,
        public string $reason = ''
    ) {}
}