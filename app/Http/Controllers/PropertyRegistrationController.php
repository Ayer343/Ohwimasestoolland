<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\RegistrationPlan;
use App\Models\PlanAgentAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyRegistrationController extends Controller
{
    /**
     * Generate registration pattern from plan
     */
    public function generateRegistrationPatternFromPlan(RegistrationPlan $plan)
    {
        $nextAvailableName = $plan->next_available_name ?? $plan->starting_point;
        
        if (!$nextAvailableName) {
            return null;
        }

        return $this->generatePatternFromName($plan->naming_pattern, $nextAvailableName);
    }

    /**
     * Generate pattern from name
     */
    private function generatePatternFromName($pattern, $name)
    {
        try {
            $generated = $pattern;
            
            if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
                if (preg_match('/([A-Za-z]+)(\d+)/', $name, $matches)) {
                    $letter = $matches[1];
                    $number = $matches[2];
                    $generated = str_replace(['{letter}', '{number}'], [$letter, $number], $pattern);
                } else {
                    return null;
                }
            } elseif (strpos($pattern, '{letter}') !== false) {
                if (preg_match('/^[a-zA-Z]+$/', $name)) {
                    $generated = str_replace('{letter}', $name, $pattern);
                } else {
                    return null;
                }
            } elseif (strpos($pattern, '{number}') !== false) {
                if (preg_match('/^\d+$/', $name)) {
                    $generated = str_replace('{number}', $name, $pattern);
                } else {
                    return null;
                }
            }
            
            return $generated;
        } catch (\Exception $e) {
            Log::error('Pattern generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update registration plan progress
     */
    public function updateRegistrationPlanProgress(RegistrationPlan $plan, $usedPattern = null)
    {
        DB::transaction(function () use ($plan, $usedPattern) {
            try {
                $freshPlan = RegistrationPlan::where('id', $plan->id)
                    ->lockForUpdate()
                    ->first();

                if (!$freshPlan) {
                    return;
                }

                $registeredSites = Property::where('registration_plan_id', $freshPlan->id)->count();
                
                $this->updateNextAvailableName($freshPlan);
                
                $freshPlan->houses_registered = $registeredSites;
                $freshPlan->save();

                if ($freshPlan->is_global_sequence && $usedPattern) {
                    Log::info("Global sequence plan {$freshPlan->id} used pattern: {$usedPattern}, next available: {$freshPlan->next_available_name}");
                }

                if ($registeredSites >= $freshPlan->estimated_houses && $freshPlan->status !== 'completed') {
                    $freshPlan->status = 'completed';
                    $freshPlan->save();
                    
                    if ($freshPlan->is_global_sequence) {
                        Log::info("Global sequence plan {$freshPlan->id} completed with {$registeredSites} properties registered. Final pattern: {$usedPattern}");
                    }
                }

                $freshPlan->refresh();

            } catch (\Exception $e) {
                Log::error('Error updating registration plan progress: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Update next available name
     */
    private function updateNextAvailableName(RegistrationPlan $plan)
    {
        $currentName = $plan->next_available_name ?? $plan->starting_point;
        $pattern = $plan->naming_pattern;
        $sequenceType = $plan->sequence_type;

        $nextName = $this->generateNextName($currentName, $pattern, $sequenceType);
        
        $nextName = $this->ensureUniqueNextName($plan, $nextName, $pattern, $sequenceType);
        
        $updated = DB::table('registration_plans')
            ->where('id', $plan->id)
            ->update(['next_available_name' => $nextName]);

        if ($updated === 0) {
            throw new \Exception('Failed to update next available name in database');
        }

        $plan->refresh();
        
        if ($plan->is_global_sequence) {
            Log::info("Global sequence plan {$plan->id} progressed from {$currentName} to: {$nextName}");
        }
    }

    /**
     * Generate next name
     */
    private function generateNextName($currentName, $pattern, $sequenceType)
    {
        if (empty($currentName)) {
            return $this->getStartingName($pattern);
        }

        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $this->generateCombinedNextName($currentName, $sequenceType);
        } elseif (strpos($pattern, '{letter}') !== false) {
            return $this->generateLetterNextName($currentName);
        } elseif (strpos($pattern, '{number}') !== false) {
            return $this->generateNumberNextName($currentName, $sequenceType);
        }

        return $this->generateSimpleNextName($currentName);
    }

    /**
     * Get starting name
     */
    private function getStartingName($pattern)
    {
        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return 'A1';
        } elseif (strpos($pattern, '{letter}') !== false) {
            return 'A';
        } elseif (strpos($pattern, '{number}') !== false) {
            return '1';
        }
        
        return '001';
    }

    /**
     * Generate combined next name
     */
    private function generateCombinedNextName($currentName, $sequenceType)
    {
        if (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
            $letter = $matches[1];
            $number = (int)$matches[2];
            
            $number += 1;
            
            switch ($sequenceType) {
                case 'even_only':
                    if ($number % 2 !== 0) $number += 1;
                    break;
                case 'odd_only':
                    if ($number % 2 === 0) $number += 1;
                    break;
            }
            
            if ($number > 99) {
                $letter = $this->incrementLetters($letter);
                $number = $sequenceType === 'even_only' ? 2 : ($sequenceType === 'odd_only' ? 1 : 1);
            }
            
            return $letter . $number;
        }
        
        return $this->generateSimpleNextName($currentName);
    }

    /**
     * Increment letters
     */
    private function incrementLetters($letters)
    {
        $length = strlen($letters);
        for ($i = $length - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                return $letters;
            }
            $letters[$i] = 'A';
        }
        return 'A' . $letters;
    }

    /**
     * Generate letter next name
     */
    private function generateLetterNextName($currentName)
    {
        return $this->incrementLetters($currentName);
    }

    /**
     * Generate number next name
     */
    private function generateNumberNextName($currentName, $sequenceType)
    {
        $number = (int)$currentName;
        
        switch ($sequenceType) {
            case 'even_only':
                return $number % 2 === 0 ? $number + 2 : $number + 1;
            case 'odd_only':
                return $number % 2 === 1 ? $number + 2 : $number + 1;
            default:
                return $number + 1;
        }
    }

    /**
     * Generate simple next name
     */
    private function generateSimpleNextName($currentName)
    {
        if (preg_match('/(.*?)(\d+)$/', $currentName, $matches)) {
            $prefix = $matches[1];
            $number = (int)$matches[2];
            return $prefix . ($number + 1);
        }
        
        return $currentName . '-1';
    }

    /**
     * Ensure the next available name is unique
     */
    private function ensureUniqueNextName(RegistrationPlan $plan, $proposedName, $pattern, $sequenceType)
    {
        $maxAttempts = 100;
        $attempt = 0;
        $currentName = $proposedName;

        while ($attempt < $maxAttempts) {
            $existingProperty = Property::where('registration_plan_id', $plan->id)
                ->where('registration_pattern', $currentName)
                ->exists();

            if (!$existingProperty) {
                return $currentName;
            }

            $currentName = $this->generateNextName($currentName, $pattern, $sequenceType);
            $attempt++;
        }

        throw new \Exception("Unable to generate a unique next available name after {$maxAttempts} attempts.");
    }

    /**
     * Get next pattern for a registration plan
     */
    public function getNextPattern($planId)
    {
        DB::beginTransaction();
        
        try {
            $registrationPlan = RegistrationPlan::where('id', $planId)
                ->lockForUpdate()
                ->firstOrFail();
            
            if (in_array($registrationPlan->status, ['completed', 'cancelled'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot generate pattern for completed or cancelled plan.'
                ], 400);
            }

            $nextPattern = $this->generateRegistrationPatternFromPlan($registrationPlan);
            
            if (!$nextPattern) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate pattern. Please check the registration plan configuration.'
                ], 400);
            }

            $nextNextName = $this->generateNextName(
                $registrationPlan->next_available_name ?? $registrationPlan->starting_point,
                $registrationPlan->naming_pattern,
                $registrationPlan->sequence_type
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'next_pattern' => $nextPattern,
                'next_available_name' => $registrationPlan->next_available_name,
                'next_next_name' => $nextNextName,
                'plan_progress' => [
                    'registered' => $registrationPlan->properties()->count(),
                    'estimated' => $registrationPlan->estimated_houses,
                    'percentage' => $registrationPlan->progress_percentage
                ],
                'plan_status' => $registrationPlan->status,
                'is_global_sequence' => $registrationPlan->is_global_sequence,
                'continues_from_plan_id' => $registrationPlan->continues_from_plan_id,
                'message' => 'Next pattern generated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating next pattern: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating pattern. Please try again.'
            ], 500);
        }
    }

    /**
     * Get global sequence information
     */
    public function getGlobalSequenceInfo(RegistrationPlan $plan)
    {
        if (!$plan->is_global_sequence) {
            return null;
        }

        $info = [
            'is_global_sequence' => true,
            'naming_pattern' => $plan->naming_pattern,
            'next_available_name' => $plan->next_available_name,
        ];

        if ($plan->continues_from_plan_id) {
            $continuedFromPlan = RegistrationPlan::find($plan->continues_from_plan_id);
            $info['continues_from'] = [
                'plan_id' => $plan->continues_from_plan_id,
                'zone' => $continuedFromPlan->zone ?? 'Unknown',
                'section' => $continuedFromPlan->section ?? 'Unknown',
            ];
        }

        return $info;
    }
}