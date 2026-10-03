<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class PropertyHistoryController extends Controller
{
    /**
     * Get property ownership history
     */
    public function propertyOwnershipHistory(Property $property, Request $request)
    {
        if (!Gate::allows('view-property-history', $property)) {
            return $this->unauthorizedResponse($request, 'You are not authorized to view this property\'s ownership history.');
        }

        $cacheKey = "property_history_{$property->id}_" . md5(serialize($request->all()));
        $history = Cache::remember($cacheKey, 3600, function() use ($property) {
            return $property->ownership_history()
                ->with(['transfer', 'previousOwner', 'newOwner'])
                ->orderBy('transfer_date', 'desc')
                ->get();
        });

        $stats = [
            'total_transfers' => $history->count(),
            'current_owner_duration' => now()->diffInDays($property->landlord_since),
            'average_ownership_duration' => $this->calculateAverageOwnershipDuration($history),
            'most_frequent_owner' => $this->getMostFrequentOwner($history)
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'property' => $property->load('landlord'),
                'ownership_history' => $history,
                'statistics' => $stats,
                'timeline_data' => $this->formatHistoryForTimeline($history)
            ]);
        }

        return view('properties.ownership-history', compact('property', 'history', 'stats'));
    }

    /**
     * Calculate average ownership duration
     */
    private function calculateAverageOwnershipDuration($history)
    {
        if ($history->count() < 2) {
            return null;
        }

        $totalDays = 0;
        $count = 0;

        for ($i = 0; $i < $history->count() - 1; $i++) {
            $current = $history[$i];
            $next = $history[$i + 1];
            
            if ($current->transfer_date && $next->transfer_date) {
                $totalDays += $current->transfer_date->diffInDays($next->transfer_date);
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count) : null;
    }

    /**
     * Get most frequent owner
     */
    private function getMostFrequentOwner($history)
    {
        if ($history->isEmpty()) {
            return null;
        }

        $ownerCounts = [];
        
        foreach ($history as $record) {
            $ownerId = $record->new_owner_id ?? $record->landlord_id;
            if ($ownerId) {
                $ownerCounts[$ownerId] = ($ownerCounts[$ownerId] ?? 0) + 1;
            }
        }

        if (empty($ownerCounts)) {
            return null;
        }

        $mostFrequentId = array_keys($ownerCounts, max($ownerCounts))[0];
        
        $owner = User::find($mostFrequentId);
        
        return $owner ? [
            'id' => $owner->id,
            'name' => $owner->name,
            'count' => max($ownerCounts)
        ] : null;
    }

    /**
     * Format history for timeline
     */
    private function formatHistoryForTimeline($history)
    {
        if (!$history || $history->isEmpty()) {
            return collect();
        }

        return $history->map(function($record) {
            try {
                $date = null;
                if ($record->transfer_date) {
                    if ($record->transfer_date instanceof \Carbon\Carbon) {
                        $date = $record->transfer_date->format('Y-m-d');
                    } else {
                        $date = \Carbon\Carbon::parse($record->transfer_date)->format('Y-m-d');
                    }
                } else {
                    $date = $record->created_at ? $record->created_at->format('Y-m-d') : 'N/A';
                }

                $previousOwnerName = 'Unknown';
                if (isset($record->previousOwner) && $record->previousOwner) {
                    $previousOwnerName = $record->previousOwner->name ?? 'Unknown';
                } elseif (isset($record->previous_owner_name)) {
                    $previousOwnerName = $record->previous_owner_name;
                }

                $newOwnerName = 'Unknown';
                if (isset($record->newOwner) && $record->newOwner) {
                    $newOwnerName = $record->newOwner->name ?? 'Unknown';
                } elseif (isset($record->new_owner_name)) {
                    $newOwnerName = $record->new_owner_name;
                } elseif (isset($record->landlord) && $record->landlord) {
                    $newOwnerName = $record->landlord->name ?? 'Unknown';
                }

                return [
                    'date' => $date,
                    'event' => 'Ownership Transfer',
                    'description' => "Transferred from {$previousOwnerName} to {$newOwnerName}",
                    'reference' => $record->document_reference ?? ($record->reference ?? 'N/A'),
                    'owner' => $newOwnerName,
                    'transfer_id' => $record->id ?? null,
                    'property_id' => $record->property_id ?? null
                ];
            } catch (\Exception $e) {
                return null;
            }
        })->filter();
    }

    /**
     * Unauthorized response
     */
    private function unauthorizedResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 403);
        }
        return redirect()->back()->with('error', $message);
    }
}