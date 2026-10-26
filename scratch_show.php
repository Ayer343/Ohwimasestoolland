<?php

use App\Models\User;
use App\Http\Controllers\Security\SecurityPersonnelAssignmentController;

$head = User::where('type', User::TYPE_SECURITY_PERSONNEL)
    ->where('can_be_supervisor', true)
    ->first();

if (! $head) {
    echo "No active head of security found.\n";
    exit(1);
}

auth()->login($head);

echo "HEAD: {$head->id} — {$head->name}\n\n";

$ctrl = app(SecurityPersonnelAssignmentController::class);
$resp = $ctrl->show(22);

echo "STATUS: " . $resp->getStatusCode() . "\n";
echo "BODY:\n" . json_encode(json_decode($resp->getContent(), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
