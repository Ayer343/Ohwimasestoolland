<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CalendarController extends Controller
{
    /**
     * Display the contractor calendar
     */
    public function index()
    {
        $userId = auth()->id();
        $user = auth()->user();
        
        // Get all contracts with milestones for calendar
        $contracts = ConstructionContract::where('contractor_user_id', $userId)
            ->with(['milestones'])
            ->get();
        
        // Prepare calendar events
        $events = [];
        
        foreach ($contracts as $contract) {
            // Add contract start date
            if ($contract->contract_start_date) {
                $events[] = [
                    'id' => 'contract_start_' . $contract->id,
                    'title' => 'Contract Start: ' . $contract->title,
                    'start' => $contract->contract_start_date->format('Y-m-d'),
                    'end' => $contract->contract_start_date->format('Y-m-d'),
                    'color' => '#3b82f6',
                    'allDay' => true,
                    'contract_id' => $contract->id,
                    'type' => 'contract_start',
                    'url' => route('contractor.contracts.show', $contract),
                ];
            }
            
            // Add contract end date
            if ($contract->contract_end_date) {
                $events[] = [
                    'id' => 'contract_end_' . $contract->id,
                    'title' => 'Contract End: ' . $contract->title,
                    'start' => $contract->contract_end_date->format('Y-m-d'),
                    'end' => $contract->contract_end_date->format('Y-m-d'),
                    'color' => '#ef4444',
                    'allDay' => true,
                    'contract_id' => $contract->id,
                    'type' => 'contract_end',
                    'url' => route('contractor.contracts.show', $contract),
                ];
            }
            
            // Add milestones
            foreach ($contract->milestones as $milestone) {
                $dueDate = $milestone->due_date ?? $milestone->estimated_completion_date ?? null;
                if ($dueDate) {
                    $color = match($milestone->status) {
                        'completed' => '#10b981',
                        'in_progress' => '#f59e0b',
                        'pending' => '#6b7280',
                        default => '#3b82f6',
                    };
                    
                    $events[] = [
                        'id' => 'milestone_' . $milestone->id,
                        'title' => $milestone->title,
                        'start' => Carbon::parse($dueDate)->format('Y-m-d'),
                        'end' => Carbon::parse($dueDate)->format('Y-m-d'),
                        'color' => $color,
                        'allDay' => true,
                        'contract_id' => $contract->id,
                        'milestone_id' => $milestone->id,
                        'status' => $milestone->status,
                        'type' => 'milestone',
                        'url' => route('contractor.contracts.show', $contract),
                    ];
                }
            }
        }
        
        // Get upcoming events (next 30 days)
        $upcomingEvents = collect($events)->filter(function($event) {
            return Carbon::parse($event['start'])->isFuture() || Carbon::parse($event['start'])->isToday();
        })->sortBy('start')->take(10);
        
        return view('contractor.calendar', compact('user', 'events', 'upcomingEvents'));
    }
    
    /**
     * Get calendar events for AJAX
     */
    public function getEvents(Request $request)
    {
        $userId = auth()->id();
        $start = $request->input('start');
        $end = $request->input('end');
        
        $contracts = ConstructionContract::where('contractor_user_id', $userId)
            ->with(['milestones'])
            ->get();
        
        $events = [];
        
        foreach ($contracts as $contract) {
            // Add contract dates
            if ($contract->contract_start_date) {
                $date = Carbon::parse($contract->contract_start_date);
                if ($date->between($start, $end)) {
                    $events[] = [
                        'id' => 'contract_start_' . $contract->id,
                        'title' => 'Contract Start: ' . $contract->title,
                        'start' => $contract->contract_start_date->format('Y-m-d'),
                        'end' => $contract->contract_start_date->format('Y-m-d'),
                        'color' => '#3b82f6',
                        'allDay' => true,
                        'textColor' => '#ffffff',
                    ];
                }
            }
            
            if ($contract->contract_end_date) {
                $date = Carbon::parse($contract->contract_end_date);
                if ($date->between($start, $end)) {
                    $events[] = [
                        'id' => 'contract_end_' . $contract->id,
                        'title' => 'Contract End: ' . $contract->title,
                        'start' => $contract->contract_end_date->format('Y-m-d'),
                        'end' => $contract->contract_end_date->format('Y-m-d'),
                        'color' => '#ef4444',
                        'allDay' => true,
                        'textColor' => '#ffffff',
                    ];
                }
            }
            
            // Add milestones
            foreach ($contract->milestones as $milestone) {
                $dueDate = $milestone->due_date ?? $milestone->estimated_completion_date ?? null;
                if ($dueDate) {
                    $date = Carbon::parse($dueDate);
                    if ($date->between($start, $end)) {
                        $color = match($milestone->status) {
                            'completed' => '#10b981',
                            'in_progress' => '#f59e0b',
                            'pending' => '#6b7280',
                            default => '#3b82f6',
                        };
                        
                        $events[] = [
                            'id' => 'milestone_' . $milestone->id,
                            'title' => $milestone->title,
                            'start' => $date->format('Y-m-d'),
                            'end' => $date->format('Y-m-d'),
                            'color' => $color,
                            'allDay' => true,
                            'textColor' => '#ffffff',
                            'extendedProps' => [
                                'contract_id' => $contract->id,
                                'milestone_id' => $milestone->id,
                                'status' => $milestone->status,
                                'contract_title' => $contract->title,
                            ],
                        ];
                    }
                }
            }
        }
        
        return response()->json($events);
    }
}