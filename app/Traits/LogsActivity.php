<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * Boot the trait.
     */
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('create', 'Created ' . class_basename($model), $model->toArray());
        });
        
        static::updated(function ($model) {
            $changes = [
                'old_data' => $model->getOriginal(),
                'new_data' => $model->getChanges()
            ];
            $model->logActivity('update', 'Updated ' . class_basename($model), $changes);
        });
        
        static::deleted(function ($model) {
            $model->logActivity('delete', 'Deleted ' . class_basename($model), $model->toArray());
        });
    }
    
    /**
     * Log activity for this model.
     */
    public function logActivity($action, $description = null, $data = [])
    {
        return Activity::log($action, $description, $this, $data);
    }
    
    /**
     * Get name for activity logging (override in your model if needed).
     */
    public function getActivityName()
    {
        return $this->name ?? $this->id ?? class_basename($this);
    }
}