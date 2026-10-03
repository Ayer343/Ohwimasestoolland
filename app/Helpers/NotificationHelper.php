<?php

namespace App\Helpers;

class NotificationHelper
{
    /**
     * Get notification icon based on type/category.
     */
    public static function getIcon($category, $priority = 1)
    {
        $icons = [
            'action_required' => 'fas fa-exclamation-circle',
            'agreement' => 'fas fa-handshake',
            'payment' => 'fas fa-money-bill-wave',
            'alert' => 'fas fa-exclamation-triangle',
            'system' => 'fas fa-cog',
            'general' => 'fas fa-bell',
        ];
        
        return $icons[$category] ?? 'fas fa-bell';
    }
    
    /**
     * Get notification color based on priority.
     */
    public static function getColor($priority)
    {
        switch ($priority) {
            case 3:
                return 'red';
            case 2:
                return 'orange';
            case 1:
                return 'blue';
            default:
                return 'gray';
        }
    }
    
    /**
     * Get priority label.
     */
    public static function getPriorityLabel($priority)
    {
        switch ($priority) {
            case 3:
                return 'High';
            case 2:
                return 'Medium';
            case 1:
                return 'Normal';
            default:
                return 'Low';
        }
    }
}