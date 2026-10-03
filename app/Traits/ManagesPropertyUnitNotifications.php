<?php
// app/Traits/ManagesPropertyUnitNotifications.php

namespace App\Traits;

use App\Notifications\LandlordCreatePropertyUnitNotification;
use App\Notifications\LandlordPropertyUnitsOverdueNotification;
use Carbon\Carbon;

trait ManagesPropertyUnitNotifications
{
    /**
     * Send notification to landlord about creating property units.
     *
     * @param \App\Models\Property $property
     * @param array $tenants
     * @param array $propertyDetails
     * @return void
     */
    protected function sendPropertyUnitCreationNotification($property, $tenants = [], $propertyDetails = [])
    {
        try {
            $landlord = $property->landlord;
            
            if (!$landlord) {
                \Log::warning('Cannot send property unit notification: No landlord associated', [
                    'property_id' => $property->id
                ]);
                return;
            }
            
            // Send notification
            $landlord->notify(new LandlordCreatePropertyUnitNotification($property, $tenants, $propertyDetails));
            
            // Store in session for UI feedback
            session()->flash('property_unit_notification_sent', true);
            
            \Log::info('Property unit creation notification sent to landlord', [
                'landlord_id' => $landlord->id,
                'property_id' => $property->id,
                'tenant_count' => count($tenants)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to send property unit creation notification', [
                'property_id' => $property->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check for properties that need unit creation and send reminders.
     *
     * @return void
     */
    public function checkAndSendPropertyUnitReminders()
    {
        $properties = \App\Models\Property::whereHas('tenants')
            ->whereDoesntHave('propertyUnits') // Assuming you have property_units table
            ->where('created_at', '<=', now()->subDays(7))
            ->with('landlord')
            ->get();
        
        foreach ($properties as $property) {
            $daysSinceCreation = Carbon::parse($property->created_at)->diffInDays(now());
            $tenantCount = $property->tenants()->count();
            
            // Send reminder based on how overdue
            if ($daysSinceCreation >= 14) {
                // Critical - send every 2 days
                if ($daysSinceCreation % 2 == 0) {
                    $this->sendOverdueNotification($property, $daysSinceCreation, $tenantCount);
                }
            } elseif ($daysSinceCreation >= 7) {
                // Overdue - send daily for first week of being overdue
                $this->sendOverdueNotification($property, $daysSinceCreation, $tenantCount);
            }
        }
    }
    
    /**
     * Send overdue notification for property units.
     *
     * @param \App\Models\Property $property
     * @param int $daysOverdue
     * @param int $tenantCount
     * @return void
     */
    protected function sendOverdueNotification($property, $daysOverdue, $tenantCount)
    {
        try {
            $landlord = $property->landlord;
            
            if ($landlord) {
                $landlord->notify(new LandlordPropertyUnitsOverdueNotification($property, $daysOverdue, $tenantCount));
                
                \Log::info('Overdue property unit notification sent', [
                    'landlord_id' => $landlord->id,
                    'property_id' => $property->id,
                    'days_overdue' => $daysOverdue
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send overdue notification', [
                'property_id' => $property->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Mark property units as created and clear notifications.
     *
     * @param \App\Models\Property $property
     * @return void
     */
    protected function markPropertyUnitsAsCreated($property)
    {
        try {
            $landlord = $property->landlord;
            
            if ($landlord) {
                // Delete all pending notifications about creating property units
                $landlord->notifications()
                    ->where('type', LandlordCreatePropertyUnitNotification::class)
                    ->orWhere('type', LandlordPropertyUnitsOverdueNotification::class)
                    ->where('data->property_id', $property->id)
                    ->delete();
                
                // Create a completion notification
                $landlord->notify(new \App\Notifications\PropertyUnitsCreatedNotification($property));
                
                \Log::info('Property units marked as created, notifications cleared', [
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to mark property units as created', [
                'property_id' => $property->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}