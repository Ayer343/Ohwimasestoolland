<?php
// app/Listeners/LogUserActivity.php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

class LogUserActivity
{
    public function handle($event): void
    {
        $activityData = $this->getActivityData($event);
        
        ActivityLog::create($activityData);
    }
    
    protected function getActivityData($event): array
    {
        $baseData = [
            'user_id' => auth()->id(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];
        
        switch (get_class($event)) {
            case 'App\Events\UserCreated':
                return array_merge($baseData, [
                    'action' => 'user_created',
                    'description' => "Created user: {$event->user->name} (ID: {$event->user->id})",
                    'metadata' => [
                        'created_user_id' => $event->user->id,
                        'created_user_name' => $event->user->name,
                        'created_user_type' => $event->user->type,
                    ]
                ]);
                
            case 'App\Events\UserUpdated':
                return array_merge($baseData, [
                    'action' => 'user_updated',
                    'description' => "Updated user: {$event->user->name} (ID: {$event->user->id})",
                    'metadata' => [
                        'updated_user_id' => $event->user->id,
                        'updated_user_name' => $event->user->name,
                        'changes' => $event->changes,
                    ]
                ]);
                
            case 'App\Events\UserDeleted':
                return array_merge($baseData, [
                    'action' => 'user_deleted',
                    'description' => "Deleted user: {$event->user->name} (ID: {$event->user->id})",
                    'metadata' => [
                        'deleted_user_id' => $event->user->id,
                        'deleted_user_name' => $event->user->name,
                        'deletion_reason' => $event->reason,
                    ]
                ]);
                
            default:
                return array_merge($baseData, [
                    'action' => 'user_activity',
                    'description' => 'User activity occurred',
                    'metadata' => [],
                ]);
        }
    }
}