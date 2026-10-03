<?php

namespace App\Http\Controllers;

use App\Models\RegistrationPlan;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RegistrationPlanPatternController extends Controller
{
    /**
     * API endpoint to validate pattern and check for conflicts
     */
    public function validatePattern(Request $request)
    {
        $validated = $request->validate([
            'naming_pattern' => 'required|string|max:100',
            'starting_point' => 'required|string|max:50',
            'sequence_type' => 'required|in:sequential,even_only,odd_only',
            'zone' => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
            'plan_id' => 'nullable|exists:registration_plans,id'
        ]);

        // Basic pattern validation
        $patternValidation = $this->validateNamingPattern(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $validated['sequence_type']
        );

        if (!$patternValidation['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $patternValidation['message'],
                'conflicts' => []
            ]);
        }

        // Check for pattern conflicts within the same zone/section
        $patternConflict = $this->checkPatternConflict(
            $validated['naming_pattern'],
            $validated['starting_point'],
            $validated['plan_id'] ?? null,
            $validated['zone'],
            $validated['section'] ?? null
        );

        if ($patternConflict['has_conflict']) {
            return response()->json([
                'valid' => false,
                'message' => $patternConflict['message'],
                'conflicts' => ['pattern' => true]
            ]);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Pattern is valid and conflict-free'
        ]);
    }

    /**
     * Get next pattern for a registration plan
     */
    public function getNextPattern(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'nullable|exists:registration_plans,id',
                'current_name' => 'required|string|max:50',
                'naming_pattern' => 'required|string|max:100',
                'sequence_type' => 'required|in:sequential,even_only,odd_only',
                'count' => 'nullable|integer|min:1|max:10'
            ]);

            $nextName = $this->generateNextName(
                $validated['current_name'],
                $validated['naming_pattern'],
                $validated['sequence_type']
            );

            // Generate preview sequence
            $preview = [];
            $current = $validated['current_name'];
            $previewCount = $validated['count'] ?? 5;
            
            for ($i = 0; $i < $previewCount; $i++) {
                $preview[] = $current;
                $current = $this->generateNextName($current, $validated['naming_pattern'], $validated['sequence_type']);
            }

            return response()->json([
                'success' => true,
                'next_name' => $nextName,
                'sequence_preview' => $preview,
                'current_name' => $validated['current_name']
            ]);

        } catch (\Exception $e) {
            Log::error('getNextPattern error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate next pattern: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next pattern for a specific registration plan (API endpoint)
     */
    public function getNextPatternForPlan($id)
    {
        try {
            $registrationPlan = RegistrationPlan::findOrFail($id);
            
            // Generate the next pattern based on the plan's current state
            $nextName = $this->generateNextName(
                $registrationPlan->next_available_name ?? $registrationPlan->starting_point,
                $registrationPlan->naming_pattern,
                $registrationPlan->sequence_type
            );
            
            // Calculate plan progress
            $registeredCount = $registrationPlan->properties()->count();
            $estimatedCount = $registrationPlan->estimated_houses;
            $progressPercentage = $estimatedCount > 0 ? round(($registeredCount / $estimatedCount) * 100) : 0;
            
            // Get next next name for preview
            $nextNextName = $this->generateNextName($nextName, $registrationPlan->naming_pattern, $registrationPlan->sequence_type);
            
            return response()->json([
                'success' => true,
                'next_pattern' => $nextName,
                'next_available_name' => $nextName,
                'next_next_name' => $nextNextName,
                'plan_progress' => [
                    'registered' => $registeredCount,
                    'estimated' => $estimatedCount,
                    'percentage' => $progressPercentage
                ],
                'plan_status' => $registrationPlan->status,
                'agent_assignment_type' => $registrationPlan->agent_assignment_type,
                'assigned_agents_count' => $registrationPlan->assignedAgents->where('is_active', true)->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get next pattern for plan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate next pattern: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate naming pattern and starting point compatibility
     */
    private function validateNamingPattern($pattern, $startingPoint, $sequenceType)
    {
        // Check if pattern contains valid placeholders
        if (!preg_match('/\{([a-zA-Z_]+)\}/', $pattern)) {
            return [
                'valid' => false,
                'message' => 'Naming pattern must contain at least one placeholder like {letter}, {number}, etc.'
            ];
        }

        // Validate starting point based on pattern type
        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            // Combined pattern - supports both {letter}{number} (A1) and {number}{letter} (1A)
            $isLetterNumber = preg_match('/^[A-Za-z]+\d+$/', $startingPoint);
            $isNumberLetter = preg_match('/^\d+[A-Za-z]+$/', $startingPoint);
            
            if (!$isLetterNumber && !$isNumberLetter) {
                return [
                    'valid' => false,
                    'message' => 'Starting point must be in format like A1, B2 (letter+number) OR 1A, 2B (number+letter) for combined patterns.'
                ];
            }
            
            // Extract number for sequence validation
            $number = intval(preg_replace('/[^0-9]/', '', $startingPoint));
            
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return [
                    'valid' => false,
                    'message' => 'For even-only sequences, starting point must have an even number (A2, B4 OR 2A, 4B, etc.).'
                ];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return [
                    'valid' => false,
                    'message' => 'For odd-only sequences, starting point must have an odd number (A1, B3 OR 1A, 3B, etc.).'
                ];
            }
            
        } elseif (strpos($pattern, '{letter}') !== false) {
            // Letter-only pattern
            if (!preg_match('/^[A-Za-z]+$/', $startingPoint)) {
                return [
                    'valid' => false,
                    'message' => 'Starting point must contain only letters for letter-only patterns.'
                ];
            }
        } elseif (strpos($pattern, '{number}') !== false) {
            // Number-only pattern
            if (!is_numeric($startingPoint)) {
                return [
                    'valid' => false,
                    'message' => 'Starting point must be a number for number-only patterns.'
                ];
            }
            
            // Validate sequence type for number patterns
            $number = intval($startingPoint);
            if ($sequenceType === 'even_only' && $number % 2 !== 0) {
                return [
                    'valid' => false,
                    'message' => 'For even-only sequences, starting point must be an even number (2, 4, 6, etc.).'
                ];
            }
            if ($sequenceType === 'odd_only' && $number % 2 === 0) {
                return [
                    'valid' => false,
                    'message' => 'For odd-only sequences, starting point must be an odd number (1, 3, 5, etc.).'
                ];
            }
        }

        return ['valid' => true, 'message' => 'Pattern is valid'];
    }

    /**
     * Check for pattern conflicts within same zone/section
     */
    private function checkPatternConflict($namingPattern, $patternValue, $planId = null, $zone, $section = null)
    {
        // Check if any property in the same zone/section already uses this pattern
        $query = Property::whereHas('registrationPlan', function($q) use ($zone, $section) {
                $q->where('zone', $zone);
                if ($section) {
                    $q->where('section', $section);
                }
            })
            ->where('registration_pattern', $patternValue);
        
        if ($planId) {
            // For updates, exclude properties from the current plan
            $query->whereHas('registrationPlan', function($q) use ($planId) {
                $q->where('id', '!=', $planId);
            });
        }
        
        $existingProperty = $query->first();
        
        if ($existingProperty) {
            $conflictingPlan = $existingProperty->registrationPlan;
            
            return [
                'has_conflict' => true,
                'message' => "Pattern '{$patternValue}' already exists in plan: " .
                           "{$conflictingPlan->zone}" . 
                           ($conflictingPlan->section ? " - {$conflictingPlan->section}" : "") .
                           ". Please use a different pattern."
            ];
        }
        
        return ['has_conflict' => false];
    }

    /**
     * Generate the next name in sequence
     */
    private function generateNextName($currentName, $pattern, $sequenceType)
    {
        if (empty($currentName)) {
            return $this->getStartingName($pattern, $sequenceType);
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
     * Get starting name based on pattern and sequence type
     */
    private function getStartingName($pattern, $sequenceType)
    {
        if (strpos($pattern, '{letter}') !== false && strpos($pattern, '{number}') !== false) {
            return $sequenceType === 'even_only' ? 'A2' : 'A1';
        } elseif (strpos($pattern, '{letter}') !== false) {
            return 'A';
        } elseif (strpos($pattern, '{number}') !== false) {
            return $sequenceType === 'even_only' ? '2' : '1';
        }
        
        return '001';
    }

    /**
     * Generate next name for combined letter-number patterns
     * Supports both {letter}{number} (A1) and {number}{letter} (1A) formats
     */
    private function generateCombinedNextName($currentName, $sequenceType)
{
    // Check if pattern is number+letter format (e.g., 1A, 2B)
    if (preg_match('/(\d+)([A-Za-z]+)/', $currentName, $matches)) {
        $number = (int)$matches[1];
        $letter = $matches[2];
        
        // Increment number first
        $number += 1;
        
        // Handle sequence types for numbers
        switch ($sequenceType) {
            case 'even_only':
                if ($number % 2 !== 0) $number += 1;
                break;
            case 'odd_only':
                if ($number % 2 === 0) $number += 1;
                break;
        }
        
        // If number exceeds 99, increment letter and reset number
        if ($number > 99) {
            $letter = $this->incrementLetters($letter);
            $number = $sequenceType === 'even_only' ? 2 : ($sequenceType === 'odd_only' ? 1 : 1);
        }
        
        return $number . $letter;
    }
    // Check if pattern is letter+number format (e.g., A1, B2)
    elseif (preg_match('/([A-Za-z]+)(\d+)/', $currentName, $matches)) {
        // ... letter-number logic
        return $letter . $number;
    }
    
    return $this->generateSimpleNextName($currentName);
}

    /**
     * Get next number based on sequence type
     */
    private function getNextNumber($currentNumber, $sequenceType)
    {
        $next = $currentNumber + 1;
        
        switch ($sequenceType) {
            case 'even_only':
                return $next % 2 === 0 ? $next : $next + 1;
            case 'odd_only':
                return $next % 2 === 1 ? $next : $next + 1;
            default: // sequential
                return $next;
        }
    }

    /**
     * Get starting number based on sequence type
     */
    private function getStartingNumber($sequenceType)
    {
        switch ($sequenceType) {
            case 'even_only':
                return 2;
            case 'odd_only':
                return 1;
            default: // sequential
                return 1;
        }
    }

    /**
     * Increment letters (A->B, Z->AA, etc.)
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
     * Generate next name for letter-only patterns
     */
    private function generateLetterNextName($currentName)
    {
        return $this->incrementLetters($currentName);
    }

    /**
     * Generate next name for number-only patterns
     */
    private function generateNumberNextName($currentName, $sequenceType)
    {
        $number = (int)$currentName;
        
        switch ($sequenceType) {
            case 'even_only':
                return $number % 2 === 0 ? $number + 2 : $number + 1;
            case 'odd_only':
                return $number % 2 === 1 ? $number + 2 : $number + 1;
            default: // sequential
                return $number + 1;
        }
    }

    /**
     * Generate next name for simple string patterns
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
}