<?php
// app/Services/SanitationService.php

namespace App\Services;

use App\Models\WasteCollectionRequest;
use App\Models\SanitationPersonnel;
use App\Models\User;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SanitationService
{
    /**
     * Find available sanitation personnel near a location.
     */
    public function findAvailablePersonnel($lat, $lng, $radius = 10, $limit = 5)
    {
        return SanitationPersonnel::available()
            ->nearLocation($lat, $lng, $radius)
            ->limit($limit)
            ->get();
    }

    /**
     * Assign the best available personnel to a request.
     */
    public function autoAssignRequest(WasteCollectionRequest $request)
    {
        if (!$request->latitude || !$request->longitude) {
            Log::warning('Cannot auto-assign request without location', [
                'request_id' => $request->id
            ]);
            return false;
        }

        $personnel = $this->findAvailablePersonnel(
            $request->latitude,
            $request->longitude,
            10,
            1
        );

        if ($personnel->isEmpty()) {
            Log::info('No available personnel found for request', [
                'request_id' => $request->id
            ]);
            return false;
        }

        $assigned = $personnel->first();
        $request->assignTo($assigned->id);
        
        Log::info('Auto-assigned request to personnel', [
            'request_id' => $request->id,
            'personnel_id' => $assigned->id
        ]);

        return $assigned;
    }

    /**
     * Get the current status of a request.
     */
    public function getRequestStatus(WasteCollectionRequest $request)
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'status_label' => $request->status_label,
            'status_badge' => $request->status_badge,
            'assigned_to' => $request->assignedTo?->full_name,
            'current_location' => $request->assignedTo?->current_location,
            'estimated_arrival' => $this->calculateEstimatedArrival($request),
            'history' => $request->getStatusHistory(),
        ];
    }

    /**
     * Calculate estimated arrival time.
     */
    public function calculateEstimatedArrival(WasteCollectionRequest $request)
    {
        if (!$request->assigned_to || !$request->assignedTo->latitude) {
            return null;
        }

        // Simple distance-based estimation
        $distance = $this->calculateDistance(
            $request->assignedTo->latitude,
            $request->assignedTo->longitude,
            $request->latitude,
            $request->longitude
        );

        // Assume average speed of 30 km/h in urban areas
        $estimatedMinutes = ($distance / 30) * 60;

        return now()->addMinutes($estimatedMinutes);
    }

    /**
     * Calculate distance between two points using Haversine formula.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get waste collection statistics for a property.
     */
    public function getPropertyStats(Property $property)
    {
        $requests = $property->wasteCollectionRequests();

        return [
            'total_requests' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'active' => $requests->whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])->count(),
            'completed' => $requests->where('status', 'completed')->count(),
            'total_weight' => $requests->where('status', 'completed')->sum('waste_weight_kg'),
            'last_collection' => $requests->where('status', 'completed')->latest('completed_at')->first()?->completed_at,
            'average_completion_time' => $requests->where('status', 'completed')->avg('completion_time'),
        ];
    }

    /**
     * Get user's waste collection history.
     */
    public function getUserHistory(User $user, $limit = 20)
    {
        return WasteCollectionRequest::where('requested_by', $user->id)
            ->with(['property', 'assignedTo', 'worker'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get daily statistics for a sanitation personnel.
     */
    public function getPersonnelDailyStats(SanitationPersonnel $personnel)
    {
        return $personnel->getDailyStats();
    }

    /**
     * Get weekly statistics for a sanitation personnel.
     */
    public function getPersonnelWeeklyStats(SanitationPersonnel $personnel)
    {
        return $personnel->getWeeklyStats();
    }

    /**
     * Get overall system statistics.
     */
    public function getSystemStats()
    {
        return [
            'total_requests' => WasteCollectionRequest::count(),
            'pending' => WasteCollectionRequest::where('status', 'pending')->count(),
            'active' => WasteCollectionRequest::whereIn('status', ['assigned', 'en_route', 'arrived', 'in_progress'])->count(),
            'completed_today' => WasteCollectionRequest::whereDate('created_at', today())->where('status', 'completed')->count(),
            'total_weight_today' => WasteCollectionRequest::whereDate('created_at', today())->where('status', 'completed')->sum('waste_weight_kg'),
            'active_personnel' => SanitationPersonnel::active()->count(),
            'available_personnel' => SanitationPersonnel::available()->count(),
            'avg_completion_time' => WasteCollectionRequest::where('status', 'completed')->avg('completion_time'),
        ];
    }
}