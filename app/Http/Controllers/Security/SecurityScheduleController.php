<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\SecuritySchedule;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use App\Models\RotationGroup;
use App\Models\SecurityAvailability;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SecurityScheduleController extends Controller
{
    /**
     * ==================== DASHBOARD & LISTINGS ====================
     */

    /**
     * Display schedules for the logged-in security personnel
     */
    public function mySchedules(Request $request)
    {
        $user = auth()->user();
        
        // Today's schedule
        $todaySchedule = SecuritySchedule::with(['post', 'shift', 'rotationGroup'])
            ->where('security_user_id', $user->id)
            ->whereDate('assignment_date', today())
            ->first();
        
        // Upcoming schedules (excluding today)
        $upcomingSchedules = SecuritySchedule::with(['post', 'shift', 'rotationGroup'])
            ->where('security_user_id', $user->id)
            ->whereDate('assignment_date', '>', today())
            ->orderBy('assignment_date')
            ->paginate(10, ['*'], 'upcoming_page');
        
        // History (past schedules)
        $historySchedules = SecuritySchedule::with(['post', 'shift'])
            ->where('security_user_id', $user->id)
            ->whereDate('assignment_date', '<', today())
            ->orderBy('assignment_date', 'desc')
            ->paginate(15, ['*'], 'history_page');
        
        // Statistics
        $totalSchedules = SecuritySchedule::where('security_user_id', $user->id)->count();
        $completedCount = SecuritySchedule::where('security_user_id', $user->id)
            ->where('status', 'completed')->count();
        $upcomingCount = SecuritySchedule::where('security_user_id', $user->id)
            ->whereDate('assignment_date', '>', today())->count();
        $rotationCount = SecuritySchedule::where('security_user_id', $user->id)
            ->where('is_rotated', true)->count();
        
        // Rotation groups the user belongs to
        $rotationGroups = RotationGroup::whereHas('members', function($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'active');
        })->with(['post', 'shift'])->get();
        
        // Calculate earnings for history items
        foreach ($historySchedules as $schedule) {
            $schedule->earnings = $this->calculateEarnings($schedule);
        }
        
        return view('security.schedules.my-schedules', compact(
            'todaySchedule',
            'upcomingSchedules',
            'historySchedules',
            'totalSchedules',
            'completedCount',
            'upcomingCount',
            'rotationCount',
            'rotationGroups'
        ));
    }

    /**
     * View a single schedule detail
     */
    public function showSchedule($id)
    {
        $schedule = SecuritySchedule::with([
            'post', 
            'shift', 
            'rotationGroup',
            'rotatedFromUser',
            'approvedBy'
        ])->where('security_user_id', auth()->id())
          ->findOrFail($id);
        
        // Get previous shift for handover context
        if ($schedule->handover_info && isset($schedule->handover_info['previous_shift_id'])) {
            $schedule->previousSchedule = SecuritySchedule::with('shift', 'securityUser')
                ->find($schedule->handover_info['previous_shift_id']);
        }
        
        return view('security.schedules.show', compact('schedule'));
    }

    /**
     * ==================== CHECK-IN VIEW ====================
     */

    /**
 * Show the check-in page for a specific schedule
 */
public function showCheckin($scheduleId)
{
    Log::info('=== SHOW CHECKIN METHOD CALLED ===', [
        'schedule_id' => $scheduleId,
        'user_id' => auth()->id(),
        'timestamp' => now()->toDateTimeString()
    ]);
    
    $schedule = SecuritySchedule::where('id', $scheduleId)
        ->where('security_user_id', auth()->id())
        ->with(['post', 'shift'])
        ->firstOrFail();
    
    Log::info('Schedule found', [
        'schedule_id' => $schedule->id,
        'post_id' => $schedule->post->id,
        'post_name' => $schedule->post->name,
        'checkin_time' => $schedule->checkin_time
    ]);
    
    // Prevent check-in if already checked in
    if ($schedule->checkin_time) {
        Log::info('User already checked in, redirecting', [
            'schedule_id' => $scheduleId,
            'checkin_time' => $schedule->checkin_time
        ]);
        
        return redirect()->route('security.my-schedules')
            ->with('info', 'You have already checked in for this shift at ' . $schedule->checkin_time->format('H:i'));
    }
    
    // Get post capabilities for verification methods
    $post = $schedule->post;
    $verificationMethods = [];
    
    Log::info('Post details', [
        'post_id' => $post->id,
        'latitude' => $post->latitude,
        'longitude' => $post->longitude,
        'checkin_radius' => $post->checkin_radius
    ]);
    
    // Determine available verification methods based on post configuration
    if ($post->latitude && $post->longitude) {
        $verificationMethods[] = 'gps';
        Log::info('GPS verification available');
    }
    
    // Check if QR codes are configured for this post
    $hasQRCode = DB::table('post_qr_codes')
        ->where('post_id', $post->id)
        ->where('is_active', true)
        ->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->where(function($q) {
            $q->whereNull('max_uses')
              ->orWhereColumn('uses_count', '<', 'max_uses');
        })
        ->exists();
    
    Log::info('QR code check', ['has_qr' => $hasQRCode]);
    
    if ($hasQRCode) {
        $verificationMethods[] = 'qr';
        $post->has_qr = true;
    } else {
        $post->has_qr = false;
    }
    
    // Check if NFC tags are configured
    $hasNFCTag = DB::table('post_nfc_tags')
        ->where('post_id', $post->id)
        ->where('is_active', true)
        ->exists();
    
    Log::info('NFC tag check', ['has_nfc' => $hasNFCTag]);
    
    if ($hasNFCTag) {
        $verificationMethods[] = 'nfc';
        $post->has_nfc = true;
    } else {
        $post->has_nfc = false;
    }
    
    // Biometric check
    $user = auth()->user();
    $preferences = $user->preferences ?? [];
    $hasBiometric = $preferences['biometric_enabled'] ?? false;
    
    Log::info('Biometric check', [
        'has_biometric' => $hasBiometric,
        'preferences' => $preferences
    ]);
    
    if ($hasBiometric) {
        $verificationMethods[] = 'biometric';
        $post->has_biometric = true;
    } else {
        $post->has_biometric = false;
    }
    
    // Manual override for supervisors
    $isSupervisor = method_exists($user, 'isSupervisor') && $user->isSupervisor();
    Log::info('Supervisor check', ['is_supervisor' => $isSupervisor]);
    
    if ($isSupervisor) {
        $verificationMethods[] = 'manual';
    }
    
    // ========== FIX: ADD FALLBACK METHODS ==========
    // If no verification methods are available, add a simple confirmation method
    if (empty($verificationMethods)) {
        $verificationMethods[] = 'confirm';
        Log::info('No verification methods available, added fallback confirm method');
    }
    
    // Always ensure GPS is available by setting fallback coordinates if needed
    if (!in_array('gps', $verificationMethods)) {
        // We'll still show GPS but with a message
        $verificationMethods[] = 'gps_fallback';
        Log::info('Added GPS fallback method');
    }
    
    // Set post flags for JavaScript (ensure they're always set)
    $post->has_qr = $post->has_qr ?? false;
    $post->has_nfc = $post->has_nfc ?? false;
    $post->has_biometric = $post->has_biometric ?? false;
    $post->has_gps = in_array('gps', $verificationMethods) || in_array('gps_fallback', $verificationMethods);
    $post->has_confirm = in_array('confirm', $verificationMethods);
    
    Log::info('Final verification methods', [
        'methods' => $verificationMethods,
        'post_flags' => [
            'has_qr' => $post->has_qr,
            'has_nfc' => $post->has_nfc,
            'has_biometric' => $post->has_biometric,
            'has_gps' => $post->has_gps,
            'has_confirm' => $post->has_confirm
        ]
    ]);
    
    // Calculate time until shift with proper date parsing
    try {
        $shiftStartTime = $schedule->shift->start_time;
        
        // Check if start_time contains a full date (contains hyphens)
        if (strpos($shiftStartTime, '-') !== false) {
            // It's a full datetime, parse it directly
            $shiftStart = Carbon::parse($shiftStartTime);
        } else {
            // It's just a time, combine with assignment date
            $shiftStart = Carbon::parse(
                $schedule->assignment_date->format('Y-m-d') . ' ' . $shiftStartTime
            );
        }
        
        $now = now();
        $minutesUntilShift = $now->diffInMinutes($shiftStart, false);
        
        $isEarly = $minutesUntilShift > 0;
        $isLate = $minutesUntilShift < 0;
        $minutesDifference = abs($minutesUntilShift);
        
        Log::info('Time calculation', [
            'shift_start' => $shiftStart->toDateTimeString(),
            'now' => $now->toDateTimeString(),
            'minutes_until_shift' => $minutesUntilShift,
            'is_early' => $isEarly,
            'is_late' => $isLate
        ]);
        
    } catch (\Exception $e) {
        // Fallback if parsing fails
        Log::warning('Failed to parse shift start time', [
            'schedule_id' => $scheduleId,
            'start_time' => $schedule->shift->start_time,
            'error' => $e->getMessage()
        ]);
        
        $minutesUntilShift = null;
        $isEarly = false;
        $isLate = false;
        $minutesDifference = 0;
    }
    
    Log::info('Rendering checkin view', [
        'view_data' => [
            'schedule_id' => $schedule->id,
            'verificationMethods' => $verificationMethods,
            'isEarly' => $isEarly,
            'isLate' => $isLate
        ]
    ]);
    
    return view('security.schedules.checkin', compact(
        'schedule',
        'verificationMethods',
        'isEarly',
        'isLate',
        'minutesDifference',
        'minutesUntilShift'
    ));
}

    /**
     * ==================== SMART CHECK-IN/OUT OPERATIONS ====================
     */

    /**
     * Smart check-in with multiple verification methods
     */
    public function smartCheckin(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->with(['post', 'shift'])
            ->firstOrFail();
        
        // Check if already checked in
        if ($schedule->checkin_time) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked in at ' . $schedule->checkin_time->format('H:i')
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'location' => 'required|array',
            'location.lat' => 'required|numeric|between:-90,90',
            'location.lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:100',
            'verification_method' => 'required|in:gps,qr,nfc,biometric,manual',
            'verification_code' => 'required_if:verification_method,qr|string|nullable',
            'device_id' => 'nullable|string',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'selfie' => 'nullable|image|max:5120', // 5MB max
            'notes' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $post = $schedule->post;
            $verificationResults = [];
            $verificationPassed = true;
            $warningMessages = [];
            
            // 1. GPS Location Verification
            if ($request->verification_method === 'gps' || $request->has('location')) {
                $gpsVerification = $this->verifyGPSCoordinates(
                    $request->location,
                    $post,
                    $request->accuracy ?? 10
                );
                
                $verificationResults['gps'] = $gpsVerification;
                
                if (!$gpsVerification['verified']) {
                    $verificationPassed = false;
                    $warningMessages[] = $gpsVerification['message'];
                }
            }
            
            // 2. QR Code Verification (if provided)
            if ($request->verification_method === 'qr' && $request->verification_code) {
                $qrVerification = $this->verifyQRCode(
                    $request->verification_code,
                    $post
                );
                
                $verificationResults['qr'] = $qrVerification;
                
                if (!$qrVerification['verified']) {
                    $verificationPassed = false;
                    $warningMessages[] = $qrVerification['message'];
                }
            }
            
            // 3. NFC Tag Verification (if applicable)
            if ($request->verification_method === 'nfc' && $request->device_id) {
                $nfcVerification = $this->verifyNFCTag(
                    $request->device_id,
                    $post
                );
                
                $verificationResults['nfc'] = $nfcVerification;
                
                if (!$nfcVerification['verified']) {
                    $verificationPassed = false;
                    $warningMessages[] = $nfcVerification['message'];
                }
            }
            
            // 4. Biometric/Face Verification (if photo/selfie provided)
            if ($request->hasFile('selfie')) {
                $biometricVerification = $this->verifyBiometric(
                    $request->file('selfie'),
                    auth()->user()
                );
                
                $verificationResults['biometric'] = $biometricVerification;
                
                if (!$biometricVerification['verified']) {
                    $verificationPassed = false;
                    $warningMessages[] = $biometricVerification['message'];
                }
            }
            
            // 5. Time-based verification (check if checking in within allowed window)
            $timeVerification = $this->verifyCheckinTime($schedule, $request->verification_method);
            $verificationResults['time'] = $timeVerification;
            
            if (!$timeVerification['verified']) {
                $verificationPassed = false;
                $warningMessages[] = $timeVerification['message'];
            }
            
            // 6. Device Fingerprint Verification
            if ($request->device_id) {
                $deviceVerification = $this->verifyDeviceFingerprint(
                    $request->device_id,
                    auth()->user()
                );
                
                $verificationResults['device'] = $deviceVerification;
                
                if (!$deviceVerification['verified']) {
                    $verificationPassed = false;
                    $warningMessages[] = $deviceVerification['message'];
                }
            }
            
            // ========== HANDLE VERIFICATION RESULT ==========
            
            if (!$verificationPassed && $request->verification_method !== 'manual') {
                // Log failed verification attempt
                $this->logVerificationAttempt($schedule, 'failed', $verificationResults);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Location verification failed. Please ensure you are at the correct post.',
                    'warnings' => $warningMessages,
                    'verification_results' => $verificationResults
                ], 403);
            }
            
            // ========== PROCESS CHECK-IN ==========
            
            // Save verification metadata
            $verificationMetadata = [
                'method' => $request->verification_method,
                'results' => $verificationResults,
                'timestamp' => now()->toIso8601String(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_id' => $request->device_id,
                'manual_override' => $request->verification_method === 'manual',
                'manual_reason' => $request->manual_reason ?? null,
                'verified_at' => now()->toIso8601String()
            ];
            
            // Save check-in photo if provided
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('checkin-photos/' . date('Y/m/d'), 'public');
            }
            
            // Save selfie if provided
            $selfiePath = null;
            if ($request->hasFile('selfie')) {
                $selfiePath = $request->file('selfie')->store('selfies/' . date('Y/m/d'), 'private');
            }
            
            $schedule->checkin_time = now();
            $schedule->status = 'active';
            $schedule->checkin_notes = $request->notes;
            $schedule->checkin_location = $request->location;
            $schedule->checkin_accuracy = $request->accuracy;
            $schedule->checkin_verification = $verificationMetadata;
            $schedule->checkin_photo = $photoPath;
            $schedule->checkin_selfie = $selfiePath;
            $schedule->checkin_device_id = $request->device_id;
            $schedule->checkin_ip = $request->ip();
            
            // Check if late
            $shift = $schedule->shift;
            $scheduledStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
            $checkinTime = now();
            
            if ($checkinTime->greaterThan($scheduledStart)) {
                $lateMinutes = $checkinTime->diffInMinutes($scheduledStart);
                $schedule->late_minutes = $lateMinutes;
                
                // Calculate grace period (e.g., 5 minutes)
                $gracePeriod = $post->checkin_grace_period ?? 5;
                
                if ($lateMinutes > $gracePeriod) {
                    $schedule->late_flagged = true;
                    $schedule->late_reason = 'Arrived ' . $lateMinutes . ' minutes late';
                    
                    Log::warning('Late checkin detected', [
                        'schedule_id' => $schedule->id,
                        'user_id' => auth()->id(),
                        'late_minutes' => $lateMinutes,
                        'grace_period' => $gracePeriod
                    ]);
                }
            }
            
            $schedule->save();
            
            // Log successful verification
            $this->logVerificationAttempt($schedule, 'success', $verificationResults);
            
            DB::commit();
            
            // Clear relevant caches
            $this->clearSecurityCaches($schedule);
            
            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'checkin_time' => $schedule->checkin_time->format('H:i'),
                'late_minutes' => $schedule->late_minutes ?? 0,
                'status' => $schedule->status,
                'verification_results' => $verificationResults,
                'post_name' => $post->name,
                'post_location' => $post->location,
                'redirect' => route('security.my-schedules')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Smart check-in failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Check-in failed. Please try again or contact supervisor.'
            ], 500);
        }
    }

    /**
     * Smart check-out with verification
     */
    public function smartCheckout(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->where('status', 'active')
            ->with(['post', 'shift'])
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'location' => 'required|array',
            'location.lat' => 'required|numeric|between:-90,90',
            'location.lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:100',
            'verification_method' => 'required|in:gps,qr,nfc,biometric,manual',
            'handover_completed' => 'nullable|boolean',
            'handover_notes' => 'nullable|string|max:1000',
            'device_id' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $post = $schedule->post;
            $verificationResults = [];
            $verificationPassed = true;
            
            // 1. GPS Location Verification
            if ($request->verification_method === 'gps' || $request->has('location')) {
                $gpsVerification = $this->verifyGPSCoordinates(
                    $request->location,
                    $post,
                    $request->accuracy ?? 10
                );
                
                $verificationResults['gps'] = $gpsVerification;
                
                if (!$gpsVerification['verified']) {
                    $verificationPassed = false;
                }
            }
            
            // 2. Time-based verification (check if checking out within allowed window)
            $timeVerification = $this->verifyCheckoutTime($schedule);
            $verificationResults['time'] = $timeVerification;
            
            if (!$timeVerification['verified']) {
                $verificationPassed = false;
            }
            
            // 3. Verify handover completion if required
            if ($schedule->handover_info && !$request->handover_completed) {
                $verificationPassed = false;
                $verificationResults['handover'] = [
                    'verified' => false,
                    'message' => 'Handover must be completed before checkout'
                ];
            }
            
            if (!$verificationPassed && $request->verification_method !== 'manual') {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkout verification failed',
                    'verification_results' => $verificationResults
                ], 403);
            }
            
            // Process checkout
            $schedule->checkout_time = now();
            $schedule->status = 'completed';
            $schedule->checkout_notes = $request->notes;
            $schedule->checkout_location = $request->location;
            $schedule->checkout_accuracy = $request->accuracy;
            $schedule->checkout_verification = [
                'method' => $request->verification_method,
                'results' => $verificationResults,
                'timestamp' => now()->toIso8601String(),
                'device_id' => $request->device_id
            ];
            
            // Calculate overtime
            $shift = $schedule->shift;
            $scheduledEnd = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
            $checkoutTime = now();
            
            // Handle overnight shifts
            if ($scheduledEnd <= Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
                $scheduledEnd->addDay();
            }
            
            if ($checkoutTime->greaterThan($scheduledEnd)) {
                $overtimeMinutes = $checkoutTime->diffInMinutes($scheduledEnd);
                $schedule->overtime_minutes = $overtimeMinutes;
                
                Log::info('Overtime recorded', [
                    'schedule_id' => $schedule->id,
                    'user_id' => auth()->id(),
                    'overtime_minutes' => $overtimeMinutes
                ]);
            }
            
            // Calculate total duration
            if ($schedule->checkin_time) {
                $schedule->total_minutes = $schedule->checkin_time->diffInMinutes($checkoutTime);
                
                // Subtract break time if applicable
                if ($schedule->break_duration > 0) {
                    $schedule->total_minutes -= $schedule->break_duration;
                }
            }
            
            $schedule->save();
            
            // Update handover if provided
            if ($request->handover_completed && $schedule->handover_info) {
                $handoverInfo = $schedule->handover_info;
                $handoverInfo['handover_notes'] = $request->handover_notes;
                $handoverInfo['handover_completed_at'] = now()->toDateTimeString();
                $handoverInfo['handover_completed_by'] = auth()->id();
                
                $schedule->update([
                    'handover_info' => $handoverInfo,
                    'handover_completed' => true,
                    'handover_completed_at' => now()
                ]);
            }
            
            DB::commit();
            
            // Clear caches
            $this->clearSecurityCaches($schedule);
            
            return response()->json([
                'success' => true,
                'message' => 'Checked out successfully',
                'checkout_time' => $schedule->checkout_time->format('H:i'),
                'overtime_minutes' => $schedule->overtime_minutes ?? 0,
                'total_hours' => round(($schedule->total_minutes ?? 0) / 60, 1),
                'verification_results' => $verificationResults,
                'redirect' => route('security.my-schedules')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Smart checkout failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Checkout failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Legacy check-in method (for backward compatibility)
     */
    public function checkin(Request $request, $scheduleId)
    {
        return $this->smartCheckin($request, $scheduleId);
    }

    /**
     * Legacy check-out method (for backward compatibility)
     */
    public function checkout(Request $request, $scheduleId)
    {
        return $this->smartCheckout($request, $scheduleId);
    }

    /**
     * ==================== VERIFICATION HELPER METHODS ====================
     */

    /**
     * Verify GPS coordinates against post location
     */
    private function verifyGPSCoordinates($userLocation, $post, $accuracy = 10)
    {
        // Default values if post doesn't have coordinates
        if (!$post->latitude || !$post->longitude) {
            return [
                'verified' => true,
                'method' => 'gps',
                'message' => 'Post location not configured. GPS check skipped.',
                'distance' => null,
                'threshold' => null
            ];
        }
        
        // Calculate distance using Haversine formula
        $earthRadius = 6371000; // meters
        
        $latFrom = deg2rad($userLocation['lat']);
        $lonFrom = deg2rad($userLocation['lng']);
        $latTo = deg2rad($post->latitude);
        $lonTo = deg2rad($post->longitude);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        $distance = $angle * $earthRadius; // distance in meters
        
        // Get allowed radius from post (default 100 meters)
        $allowedRadius = $post->checkin_radius ?? 100;
        
        // Account for GPS accuracy
        $effectiveRadius = $allowedRadius + ($accuracy * 2); // Add margin for accuracy
        
        $verified = $distance <= $effectiveRadius;
        
        return [
            'verified' => $verified,
            'method' => 'gps',
            'distance' => round($distance, 1),
            'allowed_radius' => $allowedRadius,
            'accuracy' => $accuracy,
            'effective_radius' => $effectiveRadius,
            'message' => $verified 
                ? "You are within the allowed radius (" . round($distance, 1) . "m)"
                : "You are " . round($distance, 1) . "m from the post (max allowed: {$allowedRadius}m)",
            'post_coordinates' => [
                'lat' => $post->latitude,
                'lng' => $post->longitude
            ]
        ];
    }

    /**
     * Verify QR code scanned at post - FIXED VERSION
     */
    private function verifyQRCode($scannedCode, $post)
    {
        // Check cache first
        $cacheKey = "qr_code_{$post->id}";
        $validCodes = Cache::get($cacheKey, []);
        
        // Check if code is valid in cache
        $verified = in_array($scannedCode, $validCodes);
        
        // If not found in cache, check database
        if (!$verified) {
            $qrRecord = DB::table('post_qr_codes')
                ->where('post_id', $post->id)
                ->where('code', $scannedCode)
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->where(function($q) {
                    $q->whereNull('max_uses')
                      ->orWhereColumn('uses_count', '<', 'max_uses');
                })
                ->first();
                
            $verified = !is_null($qrRecord);
            
            if ($verified && $qrRecord) {
                // Increment uses count
                DB::table('post_qr_codes')
                    ->where('id', $qrRecord->id)
                    ->update([
                        'uses_count' => $qrRecord->uses_count + 1,
                        'updated_at' => now()
                    ]);
                
                // If it's a one-time code, also mark as inactive
                if ($qrRecord->code_type === 'one_time') {
                    DB::table('post_qr_codes')
                        ->where('id', $qrRecord->id)
                        ->update(['is_active' => false]);
                }
                
                // Add to cache for future checks
                $validCodes[] = $scannedCode;
                Cache::put($cacheKey, $validCodes, now()->addMinutes(5));
            }
        }
        
        return [
            'verified' => $verified,
            'method' => 'qr',
            'message' => $verified ? 'QR code verified' : 'Invalid or expired QR code',
            'code' => substr($scannedCode, 0, 8) . '...' // Log partially for security
        ];
    }

    /**
     * Verify NFC tag
     */
    private function verifyNFCTag($tagId, $post)
    {
        // NFC tags can be placed at each post
        $validTag = DB::table('post_nfc_tags')
            ->where('post_id', $post->id)
            ->where('tag_id', $tagId)
            ->where('is_active', true)
            ->first();
        
        $verified = !is_null($validTag);
        
        if ($verified && $validTag) {
            // Update last used
            DB::table('post_nfc_tags')
                ->where('id', $validTag->id)
                ->update([
                    'last_used_at' => now(),
                    'last_used_by' => auth()->id()
                ]);
        }
        
        return [
            'verified' => $verified,
            'method' => 'nfc',
            'message' => $verified ? 'NFC tag verified' : 'Invalid NFC tag for this post',
            'tag_id' => substr($tagId, 0, 8) . '...'
        ];
    }

    /**
     * Verify biometric (face match)
     */
    private function verifyBiometric($selfieFile, $user)
    {
        // This would integrate with a face recognition service
        // For now, we'll simulate
        
        $verified = false;
        $confidence = 0;
        
        try {
            // Get stored face reference from preferences
            $preferences = $user->preferences ?? [];
            $referencePhoto = $preferences['face_reference_photo'] ?? null;
            
            if (!$referencePhoto) {
                return [
                    'verified' => true, // Skip if no reference
                    'method' => 'biometric',
                    'message' => 'Face reference not configured',
                    'confidence' => null
                ];
            }
            
            // In production, you'd call an API like:
            // - AWS Rekognition
            // - Microsoft Face API
            // - Custom face recognition model
            
            // Simulate API call - replace with actual face recognition
            // $result = FaceRecognition::compare($referencePhoto, $selfieFile);
            // $verified = $result->confidence > 0.95;
            // $confidence = $result->confidence;
            
            // For demo, assume verified
            $verified = true;
            $confidence = 0.98;
            
        } catch (\Exception $e) {
            Log::error('Biometric verification failed: ' . $e->getMessage());
            
            return [
                'verified' => false,
                'method' => 'biometric',
                'message' => 'Biometric verification error',
                'confidence' => null
            ];
        }
        
        return [
            'verified' => $verified,
            'method' => 'biometric',
            'message' => $verified ? 'Face verified' : 'Face does not match records',
            'confidence' => $confidence
        ];
    }

    /**
     * Verify check-in time
     */
    private function verifyCheckinTime($schedule, $method)
    {
        $shift = $schedule->shift;
        $scheduledStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
        $now = now();
        
        // Allow check-in up to 30 minutes early
        $earliestAllowed = $scheduledStart->copy()->subMinutes(30);
        
        // Allow check-in up to 60 minutes late (with flagging)
        $latestAllowed = $scheduledStart->copy()->addMinutes(60);
        
        if ($now < $earliestAllowed) {
            return [
                'verified' => false,
                'message' => 'Too early to check in. You can check in from ' . $earliestAllowed->format('H:i'),
                'early_minutes' => $now->diffInMinutes($earliestAllowed)
            ];
        }
        
        if ($now > $latestAllowed && $method !== 'manual') {
            return [
                'verified' => false,
                'message' => 'Too late to check in. Please contact supervisor for manual override.',
                'late_minutes' => $scheduledStart->diffInMinutes($now)
            ];
        }
        
        return [
            'verified' => true,
            'message' => 'Check-in time accepted',
            'is_early' => $now < $scheduledStart,
            'is_late' => $now > $scheduledStart,
            'minutes_difference' => $scheduledStart->diffInMinutes($now)
        ];
    }

    /**
     * Verify checkout time
     */
    private function verifyCheckoutTime($schedule)
    {
        $shift = $schedule->shift;
        $scheduledEnd = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
        
        // Handle overnight shifts
        if ($scheduledEnd <= Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
            $scheduledEnd->addDay();
        }
        
        $now = now();
        
        // Allow checkout up to 30 minutes early (with reason)
        $earliestAllowed = $scheduledEnd->copy()->subMinutes(30);
        
        if ($now < $earliestAllowed) {
            return [
                'verified' => false,
                'message' => 'Too early to check out. Shift ends at ' . $scheduledEnd->format('H:i'),
                'early_minutes' => $now->diffInMinutes($scheduledEnd)
            ];
        }
        
        return [
            'verified' => true,
            'message' => 'Checkout time accepted',
            'is_overtime' => $now > $scheduledEnd,
            'overtime_minutes' => $now > $scheduledEnd ? $scheduledEnd->diffInMinutes($now) : 0
        ];
    }

    /**
     * Verify device fingerprint
     */
    private function verifyDeviceFingerprint($deviceId, $user)
    {
        // Check if this device is registered to this user
        $registeredDevice = DB::table('user_devices')
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();
        
        $verified = !is_null($registeredDevice) && $registeredDevice->is_trusted;
        
        // If not registered, create pending record
        if (!$registeredDevice) {
            DB::table('user_devices')->insert([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_name' => request()->header('User-Agent'),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'is_trusted' => false,
                'requires_verification' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } elseif (!$verified) {
            // Update last seen for untrusted device
            DB::table('user_devices')
                ->where('id', $registeredDevice->id)
                ->update(['last_seen_at' => now()]);
        } else {
            // Update last seen for trusted device
            DB::table('user_devices')
                ->where('id', $registeredDevice->id)
                ->update(['last_seen_at' => now()]);
        }
        
        return [
            'verified' => $verified,
            'method' => 'device',
            'message' => $verified ? 'Trusted device' : 'New device detected - will require additional verification',
            'is_trusted' => $verified
        ];
    }

    /**
     * Log verification attempt
     */
    private function logVerificationAttempt($schedule, $status, $results)
    {
        try {
            DB::table('verification_logs')->insert([
                'user_id' => auth()->id(),
                'schedule_id' => $schedule->id,
                'action' => 'checkin',
                'status' => $status,
                'results' => json_encode($results),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log verification attempt', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * ==================== BREAK MANAGEMENT ====================
     */

    /**
     * Start break for security personnel
     */
    public function startBreak(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->where('status', 'active')
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'break_id' => 'required|integer',
            'location' => 'nullable|array',
            'location.lat' => 'required_with:location|numeric',
            'location.lng' => 'required_with:location|numeric',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            // Check if already on break
            if ($schedule->break_status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Already on a break'
                ], 400);
            }
            
            if (!$schedule->include_breaks) {
                return response()->json([
                    'success' => false,
                    'message' => 'This schedule does not include breaks.'
                ], 400);
            }
            
            $shift = $schedule->shift;
            if (!$shift->break_schedule || empty($shift->break_schedule['has_break'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No breaks configured for this shift.'
                ], 400);
            }
            
            $breaks = $shift->break_schedule['breaks'] ?? [];
            $break = $breaks[$request->break_id] ?? null;
            
            if (!$break) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid break ID.'
                ], 400);
            }
            
            $breakData = [
                'current_break_id' => $request->break_id,
                'break_start_time' => now(),
                'break_status' => 'active'
            ];
            
            if ($request->has('location')) {
                $breakData['break_start_location'] = $request->location;
            }
            
            $schedule->update($breakData);
            
            Log::info('Break started', [
                'schedule_id' => $schedule->id,
                'user_id' => auth()->id(),
                'break_id' => $request->break_id,
                'break_name' => $break['name'] ?? 'Unknown'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Break started',
                'break' => [
                    'name' => $break['name'] ?? 'Break',
                    'start_time' => now()->format('H:i'),
                    'scheduled_duration' => $break['duration'] ?? 30
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Start break failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to start break.'
            ], 500);
        }
    }

    /**
     * End break for security personnel
     */
    public function endBreak(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->where('break_status', 'active')
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|array',
            'location.lat' => 'required_with:location|numeric',
            'location.lng' => 'required_with:location|numeric',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            if (!$schedule->current_break_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active break to end.'
                ], 400);
            }
            
            $breakDuration = now()->diffInMinutes($schedule->break_start_time);
            
            // Get break details for logging
            $shift = $schedule->shift;
            $breaks = $shift->break_schedule['breaks'] ?? [];
            $break = $breaks[$schedule->current_break_id] ?? null;
            
            // Update break history
            $breakHistory = $schedule->break_history ?? [];
            $breakHistory[] = [
                'break_id' => $schedule->current_break_id,
                'break_name' => $break['name'] ?? 'Unknown',
                'start_time' => $schedule->break_start_time->format('Y-m-d H:i:s'),
                'end_time' => now()->format('Y-m-d H:i:s'),
                'duration' => $breakDuration,
                'notes' => $request->notes,
                'start_location' => $schedule->break_start_location,
                'end_location' => $request->location
            ];
            
            $updateData = [
                'break_end_time' => now(),
                'break_duration' => ($schedule->break_duration ?? 0) + $breakDuration,
                'break_status' => 'completed',
                'break_notes' => $request->notes,
                'break_history' => $breakHistory,
                'current_break_id' => null,
                'break_start_time' => null
            ];
            
            if ($request->has('location')) {
                $updateData['break_end_location'] = $request->location;
            }
            
            $schedule->update($updateData);
            
            Log::info('Break ended', [
                'schedule_id' => $schedule->id,
                'user_id' => auth()->id(),
                'break_duration' => $breakDuration,
                'break_name' => $break['name'] ?? 'Unknown'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Break ended',
                'duration' => $breakDuration,
                'total_break_minutes' => $schedule->break_duration
            ]);
            
        } catch (\Exception $e) {
            Log::error('End break failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to end break.'
            ], 500);
        }
    }

    /**
     * ==================== HANDOVER MANAGEMENT ====================
     */

    /**
     * Get handover details for a schedule
     */
    public function handoverDetails($scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->with(['post', 'shift'])
            ->firstOrFail();
        
        if (!$schedule->handover_info) {
            return response()->json([
                'success' => false,
                'message' => 'No handover required for this schedule'
            ], 404);
        }
        
        // Get previous shift information
        $previousSchedule = null;
        if (isset($schedule->handover_info['previous_shift_id'])) {
            $previousSchedule = SecuritySchedule::with(['shift', 'securityUser'])
                ->find($schedule->handover_info['previous_shift_id']);
        }
        
        // Format checklist items
        $checklist = [];
        if (isset($schedule->handover_info['checklist_items'])) {
            $checklist = $schedule->handover_info['checklist_items'];
        } elseif (isset($schedule->handover_info['checklist'])) {
            // Convert simple checklist to items format
            foreach ($schedule->handover_info['checklist'] as $item) {
                $checklist[] = [
                    'item' => $item,
                    'completed' => false,
                    'required' => true
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'handover' => [
                'handover_start' => $schedule->handover_info['handover_start'] ?? null,
                'handover_end' => $schedule->handover_info['handover_end'] ?? null,
                'handover_duration' => $schedule->handover_info['handover_duration'] ?? 30,
                'notes_required' => $schedule->handover_info['notes_required'] ?? true,
                'checklist' => $checklist,
                'previous_shift' => $previousSchedule ? [
                    'user_name' => $previousSchedule->securityUser->name ?? 'Unknown',
                    'shift_time' => $previousSchedule->shift->getTimeRange(),
                    'end_time' => $previousSchedule->shift->end_time
                ] : null
            ],
            'schedule' => [
                'id' => $schedule->id,
                'post_name' => $schedule->post->name,
                'shift_name' => $schedule->shift->name,
                'date' => $schedule->assignment_date->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Complete handover for a schedule
     */
    public function completeHandover(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->whereNotNull('handover_info')
            ->where('handover_completed', false)
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'checklist_completed' => 'nullable|array',
            'checklist_completed.*' => 'string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $handoverInfo = $schedule->handover_info;
            
            // Update checklist items
            if (!empty($request->checklist_completed) && isset($handoverInfo['checklist_items'])) {
                foreach ($handoverInfo['checklist_items'] as $index => $item) {
                    if (in_array($item['item'], $request->checklist_completed)) {
                        $handoverInfo['checklist_items'][$index]['completed'] = true;
                        $handoverInfo['checklist_items'][$index]['completed_at'] = now()->toDateTimeString();
                        $handoverInfo['checklist_items'][$index]['completed_by'] = auth()->id();
                        $handoverInfo['checklist_items'][$index]['completed_by_name'] = auth()->user()->name;
                    }
                }
            } elseif (!empty($request->checklist_completed) && isset($handoverInfo['checklist'])) {
                // Handle simple checklist format
                $handoverInfo['checklist_completed'] = $request->checklist_completed;
            }
            
            $handoverInfo['handover_notes'] = $request->notes;
            $handoverInfo['handover_completed_at'] = now()->toDateTimeString();
            $handoverInfo['handover_completed_by'] = auth()->id();
            $handoverInfo['handover_completed_by_name'] = auth()->user()->name;
            
            $schedule->update([
                'handover_info' => $handoverInfo,
                'handover_completed' => true,
                'handover_completed_at' => now(),
                'handover_completed_by' => auth()->id()
            ]);
            
            DB::commit();
            
            Log::info('Handover completed', [
                'schedule_id' => $schedule->id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Handover completed successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Handover completion failed: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete handover'
            ], 500);
        }
    }

    /**
     * ==================== PREFERENCES MANAGEMENT ====================
     */

    /**
     * Show preferences form for security personnel
     */
    public function preferences()
    {
        $user = auth()->user();
        
        // Get security posts for preference selection
        $securityPosts = SecurityPost::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'location', 'latitude', 'longitude']);
        
        // Get user's existing preferences or set defaults
        $preferences = $user->preferences ?? [
            'preferred_shifts' => [],
            'preferred_posts' => [],
            'willing_to_rotate' => [
                'day_to_night' => false,
                'night_to_day' => false,
                'evening' => false
            ],
            'availability' => array_fill(1, 7, true),
            'max_hours_per_week' => 40,
            'preferred_days_off' => [],
            'notification_preferences' => [
                'email' => true,
                'sms' => true,
                'push' => false
            ],
            'biometric_enabled' => false // Added biometric preference
        ];
        
        // Calculate performance rating based on history
        $performanceRating = $this->calculatePerformanceRating($user->id);
        
        // Get rotation groups the user belongs to
        $rotationGroups = RotationGroup::whereHas('members', function($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'active');
        })->with(['post', 'shift'])->get();
        
        // Get the first rotation group (if any) for single group display
        $group = $rotationGroups->first();
        
        // Calculate rotation group statistics (if user is in a group)
        if ($group) {
            // Get group members with their details
            $members = DB::table('rotation_group_members')
                ->where('rotation_group_id', $group->id)
                ->where('rotation_group_members.status', 'active')
                ->join('users', 'rotation_group_members.user_id', '=', 'users.id')
                ->select(
                    'rotation_group_members.*',
                    'users.name',
                    'users.email',
                    'users.phone',
                    'users.badge_number',
                    'users.created_at as user_created_at',
                    'users.preferences'
                )
                ->orderBy('preference_score', 'desc')
                ->get();
            
            $totalMembers = $members->count();
            $myScore = $members->firstWhere('user_id', $user->id)->preference_score ?? 0;
            $myRotationCount = $members->firstWhere('user_id', $user->id)->rotation_count ?? 0;
            $avgScore = $members->avg('preference_score') ?? 0;
            $totalRotations = $members->sum('rotation_count') ?? 0;
            
            // Get rotation schedule
            $rotationSchedule = SecuritySchedule::with(['shift', 'securityUser'])
                ->where('rotation_group_id', $group->id)
                ->where('assignment_date', '>=', now()->subDays(7))
                ->where('assignment_date', '<=', now()->addDays(14))
                ->orderBy('assignment_date')
                ->get();
            
            // Get next rotation date
            $nextRotation = null;
            if ($group->rotation_config && isset($group->rotation_config['next_rotation_date'])) {
                $nextRotation = Carbon::parse($group->rotation_config['next_rotation_date']);
            }
        } else {
            // Set default values if user is not in any rotation group
            $members = collect([]);
            $totalMembers = 0;
            $myScore = 0;
            $myRotationCount = 0;
            $avgScore = 0;
            $totalRotations = 0;
            $rotationSchedule = collect([]);
            $nextRotation = null;
        }
        
        return view('security.schedules.preferences', compact(
            'securityPosts',
            'preferences',
            'performanceRating',
            'rotationGroups',
            'group',
            'members',
            'totalMembers',
            'myScore',
            'myRotationCount',
            'avgScore',
            'totalRotations',
            'rotationSchedule',
            'nextRotation'
        ));
    }

    /**
     * Update security personnel preferences
     */
    public function updatePreferences(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'preferred_shifts' => 'nullable|array',
            'preferred_shifts.*' => 'in:day,evening,night',
            'preferred_posts' => 'nullable|array',
            'preferred_posts.*' => 'exists:security_posts,id',
            'willing_to_rotate' => 'nullable|array',
            'willing_to_rotate.day_to_night' => 'nullable|boolean',
            'willing_to_rotate.night_to_day' => 'nullable|boolean',
            'willing_to_rotate.evening' => 'nullable|boolean',
            'availability' => 'nullable|array',
            'availability.*' => 'boolean',
            'max_hours_per_week' => 'nullable|integer|min:20|max:60',
            'preferred_days_off' => 'nullable|array',
            'preferred_days_off.*' => 'integer|between:1,7',
            'notification_preferences' => 'nullable|array',
            'notification_preferences.email' => 'boolean',
            'notification_preferences.sms' => 'boolean',
            'notification_preferences.push' => 'boolean',
            'biometric_enabled' => 'nullable|boolean', // Added biometric preference
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $user = auth()->user();
        
        // Get existing preferences or initialize
        $existingPrefs = $user->preferences ?? [];
        
        $preferences = array_merge($existingPrefs, [
            'preferred_shifts' => $request->preferred_shifts ?? [],
            'preferred_posts' => $request->preferred_posts ?? [],
            'willing_to_rotate' => [
                'day_to_night' => $request->input('willing_to_rotate.day_to_night', false),
                'night_to_day' => $request->input('willing_to_rotate.night_to_day', false),
                'evening' => $request->input('willing_to_rotate.evening', false),
            ],
            'availability' => $request->availability ?? array_fill(1, 7, true),
            'max_hours_per_week' => $request->max_hours_per_week ?? 40,
            'preferred_days_off' => $request->preferred_days_off ?? [],
            'notification_preferences' => [
                'email' => $request->input('notification_preferences.email', true),
                'sms' => $request->input('notification_preferences.sms', true),
                'push' => $request->input('notification_preferences.push', false),
            ],
            'biometric_enabled' => $request->boolean('biometric_enabled', false), // Added biometric preference
            'updated_at' => now()->toDateTimeString()
        ]);
        
        $user->update(['preferences' => $preferences]);
        
        // Clear cached preferences
        Cache::forget("user_{$user->id}_preferences");
        
        Log::info('Security personnel preferences updated', [
            'user_id' => $user->id
        ]);
        
        return redirect()->route('security.preferences')
            ->with('success', 'Preferences updated successfully.');
    }

    /**
     * ==================== AVAILABILITY MANAGEMENT ====================
     */

    /**
     * Show availability calendar for security personnel
     */
    public function availability(Request $request)
    {
        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;
        
        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        
        $daysInMonth = $startOfMonth->daysInMonth;
        
        // Get existing availability records
        $availabilities = SecurityAvailability::where('user_id', auth()->id())
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date;
            });
        
        // Calculate statistics
        $availableCount = $availabilities->where('status', 'available')->count();
        $unavailableCount = $availabilities->where('status', 'unavailable')->count();
        $preferCount = $availabilities->where('status', 'prefer')->count();
        
        $currentMonth = $startOfMonth->format('F Y');
        
        // Get previous/next month links
        $prevMonth = $startOfMonth->copy()->subMonth();
        $nextMonth = $startOfMonth->copy()->addMonth();
        
        return view('security.schedules.availability', compact(
            'year',
            'month',
            'daysInMonth',
            'startOfMonth',
            'availabilities',
            'availableCount',
            'unavailableCount',
            'preferCount',
            'currentMonth',
            'prevMonth',
            'nextMonth'
        ));
    }

    /**
     * Update security personnel availability for a specific date
     */
    public function updateAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'status' => 'required|in:available,unavailable,prefer',
            'notes' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            $date = Carbon::parse($request->date);
            
            // Cannot set availability for past dates
            if ($date->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set availability for past dates'
                ], 400);
            }
            
            $availability = SecurityAvailability::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'date' => $date->format('Y-m-d')
                ],
                [
                    'status' => $request->status,
                    'notes' => $request->notes ?? null,
                    'updated_at' => now()
                ]
            );
            
            // Clear any cached availability
            Cache::forget("availability_" . auth()->id() . "_" . $date->format('Y-m-d'));
            Cache::forget("user_" . auth()->id() . "_availability_" . $date->format('Y-m'));
            
            Log::info('Availability updated', [
                'user_id' => auth()->id(),
                'date' => $date->format('Y-m-d'),
                'status' => $request->status
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Availability updated',
                'status' => $request->status,
                'date' => $date->format('Y-m-d')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update availability: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update availability'
            ], 500);
        }
    }

    /**
     * Get availability for a date range (AJAX)
     */
    public function getAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            $availabilities = SecurityAvailability::where('user_id', auth()->id())
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->get(['date', 'status', 'notes']);
            
            return response()->json([
                'success' => true,
                'availabilities' => $availabilities
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch availability'
            ], 500);
        }
    }

    /**
     * ==================== ROTATION GROUP MANAGEMENT ====================
     */

    /**
     * List all rotation groups the user belongs to
     */
    public function rotationGroups()
    {
        $user = auth()->user();
        
        $rotationGroups = RotationGroup::whereHas('members', function($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'active');
        })
        ->with(['post', 'shift', 'members'])
        ->withCount('members as members_count')
        ->get()
        ->map(function($group) {
            $group->avg_score = $group->members->avg('preference_score');
            $group->total_rotations = $group->members->sum('rotation_count');
            return $group;
        });
        
        return view('security.schedules.rotation-groups', compact('rotationGroups'));
    }

    /**
     * Show rotation group details for security personnel
     */
    public function rotationGroup($groupId)
    {
        $group = RotationGroup::with(['post', 'shift'])
            ->where('id', $groupId)
            ->whereHas('members', function($q) {
                $q->where('user_id', auth()->id())->where('status', 'active');
            })
            ->firstOrFail();
        
        $members = DB::table('rotation_group_members')
            ->where('rotation_group_id', $group->id)
            ->where('rotation_group_members.status', 'active')
            ->join('users', 'rotation_group_members.user_id', '=', 'users.id')
            ->select(
                'rotation_group_members.*',
                'users.name',
                'users.email',
                'users.phone',
                'users.badge_number',
                'users.created_at as user_created_at',
                'users.preferences'
            )
            ->orderBy('preference_score', 'desc')
            ->get();
        
        // Get my preference score
        $myMember = $members->firstWhere('user_id', auth()->id());
        $myScore = $myMember ? $myMember->preference_score : 0;
        $myRotationCount = $myMember ? $myMember->rotation_count : 0;
        
        // Get rotation schedule for this group
        $rotationSchedule = SecuritySchedule::with(['shift', 'securityUser'])
            ->where('rotation_group_id', $group->id)
            ->where('assignment_date', '>=', now()->subDays(7))
            ->where('assignment_date', '<=', now()->addDays(14))
            ->orderBy('assignment_date')
            ->get();
        
        // Calculate group statistics
        $totalMembers = $members->count();
        $avgScore = $members->avg('preference_score');
        $totalRotations = $members->sum('rotation_count');
        
        // Get next rotation date
        $nextRotation = null;
        if ($group->rotation_config && isset($group->rotation_config['next_rotation_date'])) {
            $nextRotation = Carbon::parse($group->rotation_config['next_rotation_date']);
        }
        
        return view('security.schedules.rotation-group', compact(
            'group',
            'members',
            'myScore',
            'myRotationCount',
            'rotationSchedule',
            'totalMembers',
            'avgScore',
            'totalRotations',
            'nextRotation'
        ));
    }

    /**
     * ==================== REPORTING & UTILITIES ====================
     */

    /**
     * Report an issue with a schedule
     */
    public function reportIssue(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'issue_type' => 'required|in:schedule_conflict,late_arrival,cannot_attend,equipment_issue,other',
            'description' => 'required|string|max:1000',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            // Store issue in database
            $issueId = DB::table('security_issues')->insertGetId([
                'user_id' => auth()->id(),
                'schedule_id' => $scheduleId,
                'issue_type' => $request->issue_type,
                'description' => $request->description,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info('Issue reported by security personnel', [
                'user_id' => auth()->id(),
                'schedule_id' => $scheduleId,
                'issue_id' => $issueId,
                'issue_type' => $request->issue_type
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Issue reported successfully. An administrator will review it shortly.',
                'issue_id' => $issueId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to report issue: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'schedule_id' => $scheduleId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to report issue. Please try again.'
            ], 500);
        }
    }

    /**
     * Get upcoming handovers for the user
     */
    public function upcomingHandovers()
    {
        $user = auth()->id();
        
        $handovers = SecuritySchedule::where('security_user_id', $user)
            ->whereNotNull('handover_info')
            ->where('handover_completed', false)
            ->whereDate('assignment_date', '>=', today())
            ->whereDate('assignment_date', '<=', today()->addDays(2))
            ->with(['post', 'shift'])
            ->orderBy('assignment_date', 'asc')
            ->get()
            ->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'date' => $schedule->assignment_date->format('Y-m-d'),
                    'post_name' => $schedule->post->name,
                    'shift_name' => $schedule->shift->name,
                    'handover_time' => $schedule->handover_info['handover_start'] ?? null,
                    'has_checklist' => isset($schedule->handover_info['checklist']) || isset($schedule->handover_info['checklist_items'])
                ];
            });
        
        return response()->json([
            'success' => true,
            'handovers' => $handovers,
            'count' => $handovers->count()
        ]);
    }

    /**
     * Mark schedule as read/acknowledged
     */
    public function acknowledgeSchedule($scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->firstOrFail();
        
        $schedule->update([
            'acknowledged_at' => now(),
            'acknowledged' => true
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Schedule acknowledged'
        ]);
    }

    /**
     * Request shift swap
     */
    public function requestSwap(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->where('status', 'scheduled')
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'preferred_dates' => 'nullable|array',
            'preferred_dates.*' => 'date|after:today',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            // Create swap request
            $requestId = DB::table('shift_swap_requests')->insertGetId([
                'schedule_id' => $scheduleId,
                'user_id' => auth()->id(),
                'reason' => $request->reason,
                'preferred_dates' => json_encode($request->preferred_dates),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info('Shift swap requested', [
                'user_id' => auth()->id(),
                'schedule_id' => $scheduleId,
                'request_id' => $requestId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Swap request submitted successfully',
                'request_id' => $requestId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to request swap: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit swap request'
            ], 500);
        }
    }

    /**
     * Generate calendar file for a schedule
     */
    public function generateCalendar($scheduleId)
    {
        $schedule = SecuritySchedule::where('id', $scheduleId)
            ->where('security_user_id', auth()->id())
            ->with(['post', 'shift'])
            ->firstOrFail();
        
        $startDateTime = Carbon::parse($schedule->shift->start_time);
        $endDateTime = Carbon::parse($schedule->shift->end_time);
        
        // Handle overnight shifts if needed
        if ($endDateTime <= $startDateTime) {
            $endDateTime->addDay();
        }
        
        $description = "Security Shift at {$schedule->post->name}\n";
        $description .= "Shift: {$schedule->shift->name}\n";
        $description .= "Location: {$schedule->post->location}\n";
        if ($schedule->special_instructions) {
            $description .= "Instructions: {$schedule->special_instructions}";
        }
        
        $calendarContent = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Security Schedule System//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:" . uniqid() . "@security-system
DTSTAMP:" . now()->format('Ymd\THis\Z') . "
DTSTART:" . $startDateTime->format('Ymd\THis\Z') . "
DTEND:" . $endDateTime->format('Ymd\THis\Z') . "
SUMMARY:Security Shift - {$schedule->post->name}
LOCATION:{$schedule->post->location}
DESCRIPTION:" . $this->escapeCalendarText($description) . "
STATUS:CONFIRMED
SEQUENCE:0
BEGIN:VALARM
TRIGGER:-PT15M
ACTION:DISPLAY
DESCRIPTION:Reminder
END:VALARM
END:VEVENT
END:VCALENDAR";
        
        return response($calendarContent)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="shift_' . $scheduleId . '.ics"')
            ->header('Content-Length', strlen($calendarContent));
    }

    /**
     * Get schedule statistics for security personnel dashboard
     */
    public function getStatistics()
    {
        $user = auth()->id();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        
        // This month's stats
        $monthlyStats = [
            'total' => SecuritySchedule::where('security_user_id', $user)
                ->whereBetween('assignment_date', [$monthStart, $now])
                ->count(),
            'completed' => SecuritySchedule::where('security_user_id', $user)
                ->whereBetween('assignment_date', [$monthStart, $now])
                ->where('status', 'completed')
                ->count(),
            'absent' => SecuritySchedule::where('security_user_id', $user)
                ->whereBetween('assignment_date', [$monthStart, $now])
                ->where('status', 'absent')
                ->count(),
            'late' => SecuritySchedule::where('security_user_id', $user)
                ->whereBetween('assignment_date', [$monthStart, $now])
                ->where('late_minutes', '>', 0)
                ->count(),
            'overtime' => SecuritySchedule::where('security_user_id', $user)
                ->whereBetween('assignment_date', [$monthStart, $now])
                ->where('overtime_minutes', '>', 0)
                ->count(),
            'total_hours' => SecuritySchedule::where('security_user_id', $user)
                ->whereBetween('assignment_date', [$monthStart, $now])
                ->where('status', 'completed')
                ->with('shift')
                ->get()
                ->sum(function($s) {
                    return $s->shift->duration_hours ?? 0;
                }),
            'earnings' => $this->calculateMonthlyEarnings($user, $monthStart, $now)
        ];
        
        // Overall stats
        $overallStats = [
            'total_shifts' => SecuritySchedule::where('security_user_id', $user)->count(),
            'total_hours' => SecuritySchedule::where('security_user_id', $user)
                ->where('status', 'completed')
                ->with('shift')
                ->get()
                ->sum(function($s) {
                    return $s->shift->duration_hours ?? 0;
                }),
            'attendance_rate' => $this->calculateAttendanceRate($user),
            'punctuality_score' => $this->calculatePunctualityScore($user),
            'rotation_count' => SecuritySchedule::where('security_user_id', $user)
                ->where('is_rotated', true)
                ->count()
        ];
        
        return response()->json([
            'success' => true,
            'monthly' => $monthlyStats,
            'overall' => $overallStats
        ]);
    }

    /**
     * ==================== PRIVATE HELPER METHODS ====================
     */

    /**
     * Calculate earnings for a schedule
     */
    private function calculateEarnings($schedule)
    {
        $rate = auth()->user()->hourly_rate ?? 15;
        $duration = $schedule->shift->duration_hours ?? 8;
        $earnings = $duration * $rate;
        
        // Add overtime premium (1.5x)
        if ($schedule->overtime_minutes > 0) {
            $overtimeHours = $schedule->overtime_minutes / 60;
            $earnings += $overtimeHours * ($rate * 0.5);
        }
        
        return round($earnings, 2);
    }

    /**
     * Calculate monthly earnings
     */
    private function calculateMonthlyEarnings($userId, $startDate, $endDate)
    {
        $schedules = SecuritySchedule::where('security_user_id', $userId)
            ->whereBetween('assignment_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->with('shift')
            ->get();
        
        $user = User::find($userId);
        $rate = $user->hourly_rate ?? 15;
        $total = 0;
        
        foreach ($schedules as $schedule) {
            $duration = $schedule->shift->duration_hours ?? 8;
            $total += $duration * $rate;
            
            if ($schedule->overtime_minutes > 0) {
                $total += ($schedule->overtime_minutes / 60) * ($rate * 0.5);
            }
        }
        
        return round($total, 2);
    }

    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate($userId)
    {
        $total = SecuritySchedule::where('security_user_id', $userId)->count();
        if ($total == 0) return 100;
        
        $completed = SecuritySchedule::where('security_user_id', $userId)
            ->where('status', 'completed')
            ->count();
        
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Calculate punctuality score
     */
    private function calculatePunctualityScore($userId)
    {
        $completed = SecuritySchedule::where('security_user_id', $userId)
            ->where('status', 'completed')
            ->count();
        
        if ($completed == 0) return 100;
        
        $onTime = SecuritySchedule::where('security_user_id', $userId)
            ->where('status', 'completed')
            ->where(function($q) {
                $q->whereNull('late_minutes')->orWhere('late_minutes', 0);
            })
            ->count();
        
        return round(($onTime / $completed) * 100, 1);
    }

    /**
     * Calculate performance rating for user
     */
    private function calculatePerformanceRating($userId)
    {
        try {
            $thirtyDaysAgo = now()->subDays(30);
            
            $schedules = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $thirtyDaysAgo)
                ->where('status', 'completed')
                ->get();
            
            if ($schedules->isEmpty()) {
                return 7.5;
            }
            
            $totalSchedules = $schedules->count();
            $onTimeCount = $schedules->where('late_minutes', 0)->count();
            $noOvertimeCount = $schedules->where('overtime_minutes', 0)->count();
            
            $punctualityScore = ($onTimeCount / $totalSchedules) * 3;
            $efficiencyScore = ($noOvertimeCount / $totalSchedules) * 3;
            $reliabilityScore = 4; // Simplified
            
            return round(5 + $punctualityScore + $efficiencyScore + $reliabilityScore, 1);
            
        } catch (\Exception $e) {
            Log::warning('Failed to calculate performance rating', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 7.5;
        }
    }

    /**
     * Clear security personnel-related caches
     */
    private function clearSecurityCaches($schedule)
    {
        try {
            $cacheKeys = [
                "user_{$schedule->security_user_id}_stats",
                "user_{$schedule->security_user_id}_upcoming",
                "availability_" . auth()->id() . "_" . $schedule->assignment_date->format('Y-m-d')
            ];
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear security caches', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Escape text for calendar file
     */
    private function escapeCalendarText($text)
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\n", '\\n', $text);
        return $text;
    }


/**
 * Show checkout page for a schedule
 */
public function showCheckout($scheduleId)
{
    $schedule = SecuritySchedule::where('id', $scheduleId)
        ->where('security_user_id', auth()->id())
        ->with(['post', 'shift'])
        ->firstOrFail();
    
    // Prevent checkout if not checked in
    if (!$schedule->checkin_time) {
        return redirect()->route('security.schedules.show', $schedule)
            ->with('error', 'You must check in first before checking out.');
    }
    
    // Prevent checkout if already checked out
    if ($schedule->checkout_time) {
        return redirect()->route('security.schedules.show', $schedule)
            ->with('info', 'You have already checked out from this shift.');
    }
    
    $verificationMethods = ['gps']; // Add logic similar to showCheckin()
    
    return view('security.schedules.checkout', compact('schedule', 'verificationMethods'));
}

/**
 * Get breaks for a schedule (AJAX)
 */
public function getBreaks($scheduleId)
{
    $schedule = SecuritySchedule::where('id', $scheduleId)
        ->where('security_user_id', auth()->id())
        ->with('shift')
        ->firstOrFail();
    
    if (!$schedule->include_breaks) {
        return response()->json([
            'success' => false,
            'message' => 'No breaks configured for this schedule'
        ], 404);
    }
    
    $shift = $schedule->shift;
    $breaks = $shift->break_schedule['breaks'] ?? [];
    
    return response()->json([
        'success' => true,
        'breaks' => $breaks,
        'current_break' => $schedule->current_break_id,
        'break_status' => $schedule->break_status,
        'break_history' => $schedule->break_history ?? []
    ]);
}

}