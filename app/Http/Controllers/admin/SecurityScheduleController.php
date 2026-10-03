<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use App\Models\SecurityShift;
use App\Models\SecuritySchedule;
use App\Models\SecurityScheduleTemplate;
use App\Models\RotationGroup;
use App\Models\User;
use App\Models\PostQrCode;
use App\Models\PostNfcTag;
use App\Models\UserDevice;
use App\Models\VerificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SecurityScheduleController extends Controller
{
    /**
     * Display a listing of security schedules
     */
    public function index(Request $request)
    {
        $query = SecuritySchedule::with(['post', 'shift', 'securityUser', 'assignedBy'])
            ->orderBy('assignment_date', 'desc')
            ->orderBy('security_post_id');

        // Filters
        if ($request->filled('date')) {
            $query->whereDate('assignment_date', $request->date);
        } else {
            $query->whereDate('assignment_date', '>=', today()->subDays(3));
        }

        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('security_shift_id', $request->shift_id);
        }

        if ($request->filled('security_user_id')) {
            $query->where('security_user_id', $request->security_user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('shift_category')) {
            $query->whereHas('shift', function($q) use ($request) {
                $q->where('category', $request->shift_category);
            });
        }

        if ($request->filled('verification_status')) {
            if ($request->verification_status === 'verified') {
                $query->whereNotNull('checkin_verification');
            } elseif ($request->verification_status === 'unverified') {
                $query->whereNull('checkin_verification');
            } elseif ($request->verification_status === 'failed') {
                $query->whereNotNull('verification_attempts');
            }
        }

        if ($request->filled('late_flagged')) {
            $query->where('late_flagged', $request->boolean('late_flagged'));
        }

        if ($request->filled('offline_mode')) {
            $query->where('offline_mode', $request->boolean('offline_mode'));
        }

        if ($request->filled('supervisor_override')) {
            $query->where('supervisor_override', $request->boolean('supervisor_override'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('securityUser', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%")
                       ->orWhere('badge_number', 'like', "%{$search}%");
                })
                ->orWhereHas('post', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%")
                       ->orWhere('location', 'like', "%{$search}%");
                })
                ->orWhereHas('shift', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        $schedules = $query->paginate(20);

        // Get data for filters - eager load only needed fields
        $securityPosts = SecurityPost::active()->get(['id', 'name', 'max_personnel', 'latitude', 'longitude']);
        $securityShifts = SecurityShift::active()->get(['id', 'name', 'start_time', 'end_time', 'category']);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name', 'phone', 'badge_number']);

        // Get today's schedule summary
        $todaySummary = $this->getTodayScheduleSummary();

        // Get upcoming handovers
        $upcomingHandovers = $this->getUpcomingHandovers();

        // Get verification statistics
        $verificationStats = $this->getVerificationStatistics();

        // Get statistics
        $stats = $this->getScheduleStatistics();

        return view('admin.security-schedules.index', compact(
            'schedules',
            'securityPosts',
            'securityShifts',
            'securityPersonnel',
            'todaySummary',
            'upcomingHandovers',
            'verificationStats',
            'stats',
            'request'
        ));
    }

    /**
     * Show the form for creating a new security schedule
     */
    public function create()
    {
        // Show all non-deleted posts (not just active ones) - select only needed fields
        $securityPosts = SecurityPost::whereNull('deleted_at')
            ->orderBy('is_active', 'desc')  // Show active posts first
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'location', 'latitude', 'longitude', 'equipment', 'is_active', 'checkin_radius', 'require_gps_verification']);
        
        $securityShifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight', 
            'rotation_type', 'handover_config', 'applicable_days', 'break_schedule', 'required_personnel'
        ]);
        
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->with(['schedules' => function($query) {
                $query->latest('assignment_date')->limit(1);
            }])
            ->get(['id', 'name', 'phone', 'email', 'badge_number', 'preferences']);

        $rotationGroups = RotationGroup::where('status', 'active')
            ->with(['post:id,name', 'shift:id,name'])
            ->get();

        return view('admin.security-schedules.create', compact(
            'securityPosts',
            'securityShifts',
            'securityPersonnel',
            'rotationGroups'
        ));
    }

    /**
     * Display the specified security schedule
     */
    public function show($id)
    {
        $schedule = SecuritySchedule::with([
            'post', 
            'shift', 
            'securityUser', 
            'assignedBy',
            'rotatedFromUser',
            'approvedBy',
            'handoverCompletedBy'
        ])->findOrFail($id);
        
        // Get verification details
        $verificationLogs = VerificationLog::where('schedule_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get device information if available
        $deviceInfo = null;
        if ($schedule->checkin_device_id) {
            $deviceInfo = UserDevice::where('device_id', $schedule->checkin_device_id)
                ->where('user_id', $schedule->security_user_id)
                ->first();
        }
        
        return view('admin.security-schedules.show', compact(
            'schedule',
            'verificationLogs',
            'deviceInfo'
        ));
    }

    /**
     * OPTIMIZED: Store a newly created security schedule
     */
    public function store(Request $request)
    {
        Log::info('===== STORE METHOD STARTED =====', [
            'request_data' => $request->except(['_token']),
            'user_id' => auth()->id(),
            'timestamp' => now()->toDateTimeString()
        ]);
        
        $startTime = microtime(true);
        
        // ========== VALIDATION SECTION ==========
        Log::info('Step 1: Starting validation');
        $validationStart = microtime(true);
        
        $validator = Validator::make($request->all(), [
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'security_user_id' => 'required|exists:users,id',
            'assignment_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string|max:1000',
            'include_breaks' => 'nullable|boolean',
            'rotation_group_id' => 'nullable|exists:rotation_groups,id',
            'rotation_group_type' => 'nullable|string',
            'rotation_sequence_number' => 'nullable|integer',
            'rotation_preference_score' => 'nullable|integer|min:1|max:10',
            'is_rotated' => 'nullable|boolean',
        ], [
            'security_user_id.exists' => 'The selected security personnel does not exist or is not active.',
            'security_post_id.exists' => 'The selected security post does not exist or is not active.',
            'rotation_group_id.exists' => 'The selected rotation group does not exist.',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed - basic rules', [
                'errors' => $validator->errors()->toArray(),
                'time_taken' => microtime(true) - $validationStart
            ]);
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        Log::info('Step 1: Basic validation passed', [
            'time_taken' => microtime(true) - $validationStart
        ]);

        // Custom validation
        Log::info('Step 2: Starting custom validation');
        $customValidationStart = microtime(true);
        
        $validator->after(function ($validator) use ($request) {
            try {
                Log::info('Custom validation - checking user', [
                    'user_id' => $request->security_user_id
                ]);
                
                // OPTIMIZATION: Use DB facade for faster queries
                $userQueryStart = microtime(true);
                $user = DB::table('users')
                    ->where('id', $request->security_user_id)
                    ->first(['id', 'type', 'status']);
                
                Log::info('User query completed', [
                    'time_taken' => microtime(true) - $userQueryStart,
                    'user_found' => !is_null($user),
                    'user_type' => $user->type ?? null
                ]);
                
                if (!$user) {
                    Log::warning('User not found');
                    $validator->errors()->add('security_user_id', 'User not found.');
                    return;
                }
                
                if ($user->type !== User::TYPE_SECURITY_PERSONNEL) {
                    Log::warning('User is not security personnel', [
                        'user_type' => $user->type
                    ]);
                    $validator->errors()->add('security_user_id', 'The selected user is not a security personnel.');
                    return;
                }

                // Check if user is already assigned to another post on the same date
                Log::info('Checking existing assignment', [
                    'user_id' => $request->security_user_id,
                    'date' => $request->assignment_date
                ]);
                
                $assignmentQueryStart = microtime(true);
                $existingAssignment = DB::table('security_schedules')
                    ->where('security_user_id', $request->security_user_id)
                    ->whereDate('assignment_date', $request->assignment_date)
                    ->exists();
                
                Log::info('Existing assignment check completed', [
                    'time_taken' => microtime(true) - $assignmentQueryStart,
                    'exists' => $existingAssignment
                ]);
                
                if ($existingAssignment) {
                    Log::warning('User already assigned on this date');
                    $validator->errors()->add('security_user_id', 'This security personnel is already assigned to another post on this date.');
                }

                // Check post capacity
                Log::info('Checking post capacity', [
                    'post_id' => $request->security_post_id
                ]);
                
                $postQueryStart = microtime(true);
                $post = DB::table('security_posts')
                    ->where('id', $request->security_post_id)
                    ->whereNull('deleted_at')
                    ->first(['id', 'max_personnel']);
                
                Log::info('Post query completed', [
                    'time_taken' => microtime(true) - $postQueryStart,
                    'post_found' => !is_null($post),
                    'max_personnel' => $post->max_personnel ?? null
                ]);
                
                if ($post) {
                    $date = Carbon::parse($request->assignment_date);
                    $capacityQueryStart = microtime(true);
                    $assignedCount = DB::table('security_schedules')
                        ->where('security_post_id', $request->security_post_id)
                        ->whereDate('assignment_date', $date)
                        ->count();
                    
                    Log::info('Capacity check completed', [
                        'time_taken' => microtime(true) - $capacityQueryStart,
                        'assigned_count' => $assignedCount,
                        'max_personnel' => $post->max_personnel
                    ]);
                    
                    if ($assignedCount >= $post->max_personnel) {
                        Log::warning('Post at full capacity');
                        $validator->errors()->add('security_post_id', "This post has reached maximum capacity ({$post->max_personnel} personnel) for the selected date.");
                    }
                } else {
                    Log::warning('Security post not found');
                    $validator->errors()->add('security_post_id', 'Security post not found.');
                }

                // Check shift applicability
                Log::info('Checking shift applicability', [
                    'shift_id' => $request->security_shift_id
                ]);
                
                $shiftQueryStart = microtime(true);
                $shift = DB::table('security_shifts')
                    ->where('id', $request->security_shift_id)
                    ->first(['id', 'rotation_type', 'start_time', 'end_time', 'duration_hours']);
                
                Log::info('Shift query completed', [
                    'time_taken' => microtime(true) - $shiftQueryStart,
                    'shift_found' => !is_null($shift)
                ]);
                
                if ($shift) {
                    $date = Carbon::parse($request->assignment_date);
                    
                    // Check applicability - we need to load the full model for this method
                    $fullShift = SecurityShift::find($shift->id);
                    
                    $applicableCheckStart = microtime(true);
                    $isApplicable = $fullShift->isApplicableOnDay($date->dayOfWeekIso);
                    Log::info('Applicability check completed', [
                        'time_taken' => microtime(true) - $applicableCheckStart,
                        'day_of_week' => $date->dayOfWeekIso,
                        'is_applicable' => $isApplicable
                    ]);
                    
                    if (!$isApplicable) {
                        Log::warning('Shift not applicable on selected date');
                        $validator->errors()->add('security_shift_id', 'This shift is not applicable on the selected date.');
                    }

                    // Check if user has conflicting shifts (for rotating shifts)
                    if ($user && $shift->rotation_type === 'rotating') {
                        Log::info('Checking rotating shift constraints');
                        $rotatingCheckStart = microtime(true);
                        
                        $lastShift = DB::table('security_schedules')
                            ->where('security_user_id', $request->security_user_id)
                            ->where('status', 'completed')
                            ->orderBy('assignment_date', 'desc')
                            ->first(['assignment_date']);
                        
                        if ($lastShift) {
                            $hoursBetweenShifts = $date->diffInHours(Carbon::parse($lastShift->assignment_date));
                            $minHoursRequired = 12;
                            
                            if ($hoursBetweenShifts < $minHoursRequired) {
                                $validator->errors()->add(
                                    'security_user_id',
                                    "This personnel needs at least {$minHoursRequired} hours rest between shifts."
                                );
                            }
                        }

                        // Check weekly hour limits
                        $weekStart = $date->copy()->startOfWeek();
                        $weekEnd = $date->copy()->endOfWeek();
                        
                        $weeklyHours = DB::table('security_schedules')
                            ->join('security_shifts', 'security_schedules.security_shift_id', '=', 'security_shifts.id')
                            ->where('security_schedules.security_user_id', $request->security_user_id)
                            ->whereBetween('assignment_date', [$weekStart, $weekEnd])
                            ->where('status', '!=', 'cancelled')
                            ->sum('security_shifts.duration_hours');

                        $maxWeeklyHours = 60;
                        
                        if (($weeklyHours + $shift->duration_hours) > $maxWeeklyHours) {
                            $validator->errors()->add(
                                'security_user_id',
                                "This personnel will exceed the maximum weekly hours ({$maxWeeklyHours} hours)."
                            );
                        }
                        
                        Log::info('Rotating shift constraints check completed', [
                            'time_taken' => microtime(true) - $rotatingCheckStart
                        ]);
                    }
                } else {
                    Log::warning('Security shift not found');
                    $validator->errors()->add('security_shift_id', 'Security shift not found.');
                }
            } catch (\Exception $e) {
                Log::error('Exception in custom validation: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]);
                $validator->errors()->add('general', 'Validation error occurred. Please try again.');
            }
        });

        // Execute validation
        Log::info('Executing validator');
        $validatorExecutionStart = microtime(true);
        
        if ($validator->fails()) {
            Log::warning('Custom validation failed', [
                'errors' => $validator->errors()->toArray(),
                'time_taken' => microtime(true) - $validatorExecutionStart,
                'total_custom_validation_time' => microtime(true) - $customValidationStart
            ]);
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        Log::info('Step 2: Custom validation passed', [
            'time_taken' => microtime(true) - $customValidationStart
        ]);

        // ========== OPTIMIZED DATABASE INSERT SECTION ==========
        Log::info('Step 3: Starting database transaction');
        $dbStart = microtime(true);
        
        DB::beginTransaction();

        try {
            Log::info('Fetching shift for handover calculation');
            $shiftQueryStart = microtime(true);
            $shift = SecurityShift::find($request->security_shift_id);
            Log::info('Shift fetched', [
                'time_taken' => microtime(true) - $shiftQueryStart,
                'shift_id' => $shift->id ?? null
            ]);
            
            // Calculate handover information
            Log::info('Calculating handover information');
            $handoverStart = microtime(true);
            $handoverInfo = $this->calculateHandoverTimesOptimized(
                $shift, 
                $request->assignment_date,
                $request->security_post_id
            );
            Log::info('Handover calculation completed', [
                'time_taken' => microtime(true) - $handoverStart,
                'has_handover' => !is_null($handoverInfo)
            ]);

            // Prepare rotation data
            Log::info('Preparing rotation data');
            $rotationStart = microtime(true);
            $rotationData = $this->prepareRotationDataOptimized($shift, $request->security_user_id);
            Log::info('Rotation data preparation completed', [
                'time_taken' => microtime(true) - $rotationStart,
                'has_rotation_data' => !is_null($rotationData)
            ]);

            // OPTIMIZATION 1: Prepare data for direct DB insert
            Log::info('Preparing optimized insert data');
            
            // Base schedule data
            $scheduleData = [
                'security_post_id' => $request->security_post_id,
                'security_shift_id' => $request->security_shift_id,
                'security_user_id' => $request->security_user_id,
                'assigned_by' => auth()->id(),
                'assignment_date' => $request->assignment_date,
                'status' => 'scheduled',
                'notes' => $request->notes,
                'emergency_contact' => $request->emergency_contact,
                'special_instructions' => $request->special_instructions,
                'include_breaks' => $request->boolean('include_breaks', false),
                'handover_info' => $handoverInfo ? json_encode($handoverInfo) : null,
                'rotation_data' => $rotationData ? json_encode($rotationData) : null,
                'created_at' => now(),
                'updated_at' => now(),
                'performance_metrics' => json_encode([
                    'expected_duration' => $shift->duration_hours ?? 8,
                    'break_count' => $shift->break_schedule['break_count'] ?? 0,
                    'requires_handover' => !is_null($handoverInfo)
                ])
            ];

            // Add rotation fields
            $scheduleData['rotation_group_id'] = $request->rotation_group_id;
            $scheduleData['rotation_group_type'] = $request->rotation_group_type;
            $scheduleData['rotation_sequence_number'] = $request->rotation_sequence_number ?? 0;
            $scheduleData['rotation_preference_score'] = $request->rotation_preference_score ?? 5;
            $scheduleData['is_rotated'] = $request->boolean('is_rotated', false);
            $scheduleData['rotation_swap_count'] = 0;

            // Remove null values to avoid constraint issues
            $scheduleData = array_filter($scheduleData, function($value) {
                return !is_null($value);
            });

            Log::info('Schedule data prepared', [
                'data_keys' => array_keys($scheduleData)
            ]);

            // OPTIMIZATION 2: Use Query Builder instead of Eloquent
            Log::info('Attempting optimized insert');
            $insertStart = microtime(true);
            
            $id = DB::table('security_schedules')->insertGetId($scheduleData);
            
            Log::info('Optimized insert completed', [
                'time_taken' => microtime(true) - $insertStart,
                'new_schedule_id' => $id
            ]);

            // OPTIMIZATION 3: Load the model separately for notifications (only if needed)
            $schedule = null;
            if ($id) {
                // Load minimal relations for logging
                $schedule = SecuritySchedule::with(['shift', 'post', 'securityUser'])
                    ->find($id);
            }

            DB::commit();
            Log::info('Transaction committed successfully');

            // OPTIMIZATION 4: Queue notifications after commit (don't block response)
            if ($schedule) {
                try {
                    Log::info('Queueing notifications');
                    $this->queueAssignmentNotification($schedule);
                    $this->queueHandoverChecklistGeneration($schedule);
                    Log::info('Notifications queued');
                } catch (\Exception $e) {
                    Log::warning('Notification queuing failed: ' . $e->getMessage());
                }
            }

            $totalTime = microtime(true) - $startTime;
            Log::info('===== STORE METHOD COMPLETED SUCCESSFULLY =====', [
                'total_time' => $totalTime,
                'schedule_id' => $id,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('admin.security-schedules.index')
                ->with('success', 'Security schedule created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            $totalTime = microtime(true) - $startTime;
            
            Log::error('===== STORE METHOD FAILED =====', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'total_time' => $totalTime,
                'request_data' => $request->except(['_token'])
            ]);
            
            // Check for specific database errors
            if ($e instanceof \Illuminate\Database\QueryException) {
                Log::error('Database error details', [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'error_info' => $e->errorInfo
                ]);
                
                if (isset($e->errorInfo[1])) {
                    switch ($e->errorInfo[1]) {
                        case 1364:
                            return redirect()->back()
                                ->with('error', 'A required field is missing. Please contact administrator.')
                                ->withInput();
                        case 1452:
                            return redirect()->back()
                                ->with('error', 'Invalid reference: One of the selected items does not exist.')
                                ->withInput();
                        case 1062:
                            return redirect()->back()
                                ->with('error', 'This assignment already exists for the selected date.')
                                ->withInput();
                    }
                }
            }

            return redirect()->back()
                ->with('error', 'Failed to create security schedule. Please try again.')
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified security schedule
     */
    public function edit($id)
    {
        $schedule = SecuritySchedule::with(['shift', 'post', 'securityUser'])->findOrFail($id);
        
        $securityPosts = SecurityPost::whereNull('deleted_at')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'max_personnel', 'type', 'location', 'is_active', 'latitude', 'longitude']);
        
        $securityShifts = SecurityShift::active()->get([
            'id', 'name', 'start_time', 'end_time', 'category', 'is_overnight', 
            'rotation_type', 'handover_config', 'applicable_days'
        ]);
        
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name', 'phone', 'email', 'badge_number']);

        $rotationGroups = RotationGroup::where('status', 'active')
            ->with(['post:id,name', 'shift:id,name'])
            ->get();

        // Get current assignments count for the selected post/date
        $currentAssignments = 0;
        if ($schedule->security_post_id && $schedule->assignment_date) {
            $currentAssignments = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                ->whereDate('assignment_date', $schedule->assignment_date)
                ->where('id', '!=', $schedule->id)
                ->count();
        }

        return view('admin.security-schedules.edit', compact(
            'schedule',
            'securityPosts',
            'securityShifts',
            'securityPersonnel',
            'rotationGroups',
            'currentAssignments'
        ));
    }

    /**
     * Update the specified security schedule
     */
    public function update(Request $request, $id)
    {
        set_time_limit(120);
        
        $schedule = SecuritySchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'security_post_id' => 'required|exists:security_posts,id',
            'security_shift_id' => 'required|exists:security_shifts,id',
            'security_user_id' => 'required|exists:users,id',
            'assignment_date' => 'required|date',
            'status' => 'required|in:scheduled,active,completed,absent,cancelled',
            'notes' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string|max:1000',
            'include_breaks' => 'nullable|boolean',
            'handover_completed' => 'nullable|boolean',
            'is_approved' => 'nullable|boolean',
            'late_minutes' => 'nullable|integer|min:0',
            'overtime_minutes' => 'nullable|integer|min:0',
            'total_minutes' => 'nullable|integer|min:0',
            'break_duration' => 'nullable|integer|min:0',
            'checkin_time' => 'nullable|date',
            'checkout_time' => 'nullable|date|after_or_equal:checkin_time',
            'late_flagged' => 'nullable|boolean',
            'late_reason' => 'nullable|string|max:255',
            'supervisor_override' => 'nullable|boolean',
            'override_reason' => 'required_if:supervisor_override,true|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Prevent certain status changes based on business rules
        if (in_array($schedule->status, ['completed', 'absent', 'cancelled']) && $schedule->status !== $request->status) {
            return redirect()->back()
                ->with('error', 'Cannot change status from ' . $schedule->status . '.')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $shift = SecurityShift::find($request->security_shift_id);
            
            // Only recalculate handover if relevant fields changed
            $handoverInfo = $schedule->handover_info;
            if ($schedule->security_shift_id != $request->security_shift_id || 
                $schedule->assignment_date != $request->assignment_date ||
                $schedule->security_post_id != $request->security_post_id) {
                
                $handoverInfo = $this->calculateHandoverTimesOptimized(
                    $shift, 
                    $request->assignment_date,
                    $request->security_post_id
                );
            }

            // Prepare update data
            $updateData = [
                'security_post_id' => $request->security_post_id,
                'security_shift_id' => $request->security_shift_id,
                'security_user_id' => $request->security_user_id,
                'assignment_date' => $request->assignment_date,
                'status' => $request->status,
                'notes' => $request->notes,
                'emergency_contact' => $request->emergency_contact,
                'special_instructions' => $request->special_instructions,
                'handover_info' => $handoverInfo,
                'include_breaks' => $request->boolean('include_breaks', $schedule->include_breaks),
            ];

            // Add optional fields if provided
            if ($request->has('handover_completed')) {
                $updateData['handover_completed'] = $request->boolean('handover_completed');
                if ($request->boolean('handover_completed') && !$schedule->handover_completed_at) {
                    $updateData['handover_completed_at'] = now();
                    $updateData['handover_completed_by'] = auth()->id();
                }
            }

            if ($request->has('is_approved')) {
                $updateData['is_approved'] = $request->boolean('is_approved');
                if ($request->boolean('is_approved') && !$schedule->approved_at) {
                    $updateData['approved_by'] = auth()->id();
                    $updateData['approved_at'] = now();
                    $updateData['approval_metadata'] = json_encode([
                        'approved_by_name' => auth()->user()->name,
                        'approved_at' => now()->toDateTimeString(),
                        'notes' => $request->approval_notes ?? null
                    ]);
                }
            }

            // Update time tracking fields if provided
            if ($request->filled('checkin_time')) {
                $updateData['checkin_time'] = Carbon::parse($request->checkin_time);
            }
            
            if ($request->filled('checkout_time')) {
                $updateData['checkout_time'] = Carbon::parse($request->checkout_time);
            }
            
            if ($request->filled('late_minutes')) {
                $updateData['late_minutes'] = $request->late_minutes;
            }
            
            if ($request->filled('overtime_minutes')) {
                $updateData['overtime_minutes'] = $request->overtime_minutes;
            }
            
            if ($request->filled('total_minutes')) {
                $updateData['total_minutes'] = $request->total_minutes;
            }
            
            if ($request->filled('break_duration')) {
                $updateData['break_duration'] = $request->break_duration;
            }

            if ($request->has('late_flagged')) {
                $updateData['late_flagged'] = $request->boolean('late_flagged');
                $updateData['late_reason'] = $request->late_reason;
            }

            // Handle supervisor override
            if ($request->boolean('supervisor_override')) {
                $updateData['supervisor_override'] = true;
                $updateData['supervisor_id'] = auth()->id();
                $updateData['override_reason'] = $request->override_reason;
                
                // Log the override
                Log::info('Supervisor override applied', [
                    'schedule_id' => $schedule->id,
                    'supervisor_id' => auth()->id(),
                    'reason' => $request->override_reason
                ]);
            }

            $schedule->update($updateData);

            // Clear relevant caches
            $this->clearScheduleCaches($schedule);

            DB::commit();

            Log::info('Security schedule updated', [
                'schedule_id' => $schedule->id,
                'updated_by' => auth()->id(),
                'changes' => $schedule->getChanges()
            ]);

            // Queue notification if assignment changed
            if ($schedule->wasChanged('security_user_id') || $schedule->wasChanged('assignment_date')) {
                $this->queueAssignmentNotification($schedule);
            }

            return redirect()->route('admin.security-schedules.index')
                ->with('success', 'Security schedule updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update security schedule: ' . $e->getMessage(), [
                'schedule_id' => $id,
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update security schedule. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified security schedule (Soft Delete)
     */
    public function destroy($id)
    {
        try {
            $schedule = SecuritySchedule::findOrFail($id);
            
            // Check if schedule can be deleted
            if (in_array($schedule->status, ['active', 'completed'])) {
                return redirect()->back()
                    ->with('error', 'Cannot delete an active or completed schedule.');
            }
            
            // Log deletion
            $auditLog = $schedule->audit_log ?? [];
            $auditLog[] = [
                'action' => 'deleted',
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()->name,
                'deleted_at' => now()->toDateTimeString(),
                'reason' => 'Manual deletion'
            ];
            $schedule->audit_log = $auditLog;
            $schedule->save();
            
            $schedule->delete();

            // Clear caches
            $this->clearScheduleCaches($schedule);

            Log::info('Security schedule soft deleted', [
                'schedule_id' => $id,
                'deleted_by' => auth()->id(),
                'deleted_at' => now()
            ]);

            return redirect()->route('admin.security-schedules.index')
                ->with('success', 'Security schedule moved to trash successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to soft delete security schedule: ' . $e->getMessage(), [
                'schedule_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to delete security schedule. Please try again.');
        }
    }

    /**
     * Display a listing of trashed (soft deleted) security schedules
     */
    public function trash(Request $request)
    {
        $query = SecuritySchedule::onlyTrashed()
            ->with(['post', 'shift', 'securityUser', 'assignedBy'])
            ->orderBy('deleted_at', 'desc');

        // Apply filters to trashed records
        if ($request->filled('date')) {
            $query->whereDate('assignment_date', $request->date);
        }

        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('security_shift_id', $request->shift_id);
        }

        if ($request->filled('security_user_id')) {
            $query->where('security_user_id', $request->security_user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('securityUser', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orWhereHas('post', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhereHas('shift', function($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        $trashedSchedules = $query->paginate(20)->withQueryString();

        // Get data for filters
        $securityPosts = SecurityPost::active()->get(['id', 'name']);
        $securityShifts = SecurityShift::active()->get(['id', 'name']);
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id', 'name']);

        // Get statistics for trashed records
        $trashStats = [
            'total_trashed' => SecuritySchedule::onlyTrashed()->count(),
            'trashed_by_period' => [
                'today' => SecuritySchedule::onlyTrashed()->whereDate('deleted_at', today())->count(),
                'this_week' => SecuritySchedule::onlyTrashed()->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month' => SecuritySchedule::onlyTrashed()->whereMonth('deleted_at', now()->month)->count(),
            ],
            'oldest_trashed' => SecuritySchedule::onlyTrashed()->oldest('deleted_at')->first()?->deleted_at?->diffForHumans(),
            'deleted_by_users' => $this->getDeletedByStats(),
        ];

        return view('admin.security-schedules.trash', compact(
            'trashedSchedules',
            'securityPosts',
            'securityShifts',
            'securityPersonnel',
            'trashStats',
            'request'
        ));
    }

    /**
     * Restore the specified soft deleted security schedule
     */
    public function restore($id)
    {
        try {
            $schedule = SecuritySchedule::onlyTrashed()->findOrFail($id);
            
            // Check if restoration would cause conflicts
            $conflicts = $this->checkRestorationConflicts($schedule);
            
            if (!empty($conflicts)) {
                $conflictMessages = implode('<br>', array_map(function($conflict) {
                    return "• {$conflict}";
                }, $conflicts));
                
                return redirect()->route('admin.security-schedules.trash')
                    ->with('error', 'Cannot restore schedule due to conflicts:<br>' . $conflictMessages);
            }

            $schedule->restore();

            // Clear caches
            $this->clearScheduleCaches($schedule);

            Log::info('Security schedule restored', [
                'schedule_id' => $schedule->id,
                'restored_by' => auth()->id(),
                'restored_at' => now(),
                'original_deleted_at' => $schedule->deleted_at
            ]);

            return redirect()->route('admin.security-schedules.trash')
                ->with('success', 'Security schedule restored successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to restore security schedule: ' . $e->getMessage(), [
                'schedule_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to restore security schedule. Please try again.');
        }
    }

    /**
     * Bulk restore multiple soft deleted security schedules
     */
    public function bulkRestore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'exists:security_schedules,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $restoredCount = 0;
            $failedRestores = [];
            $conflictDetails = [];

            foreach ($request->schedule_ids as $scheduleId) {
                try {
                    $schedule = SecuritySchedule::onlyTrashed()->find($scheduleId);
                    
                    if (!$schedule) {
                        $failedRestores[] = [
                            'id' => $scheduleId,
                            'reason' => 'Schedule not found in trash'
                        ];
                        continue;
                    }

                    // Check for conflicts
                    $conflicts = $this->checkRestorationConflicts($schedule);
                    
                    if (!empty($conflicts)) {
                        $failedRestores[] = [
                            'id' => $scheduleId,
                            'reason' => 'Conflict detected',
                            'conflicts' => $conflicts
                        ];
                        $conflictDetails[$scheduleId] = $conflicts;
                        continue;
                    }

                    $schedule->restore();
                    $restoredCount++;

                    Log::info('Security schedule bulk restored', [
                        'schedule_id' => $schedule->id,
                        'restored_by' => auth()->id()
                    ]);

                } catch (\Exception $e) {
                    $failedRestores[] = [
                        'id' => $scheduleId,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            $message = $restoredCount > 0 
                ? "{$restoredCount} schedule(s) restored successfully."
                : "No schedules were restored.";

            if (!empty($failedRestores)) {
                $message .= " " . count($failedRestores) . " schedule(s) could not be restored.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'restored_count' => $restoredCount,
                'failed_count' => count($failedRestores),
                'failed_restores' => $failedRestores,
                'conflict_details' => $conflictDetails
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk restore security schedules: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'schedule_ids' => $request->schedule_ids
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk restore schedules.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Force delete the specified security schedule (Permanent Delete)
 */
public function forceDelete($id)
{
    try {
        // First check if it exists in the main table (not deleted)
        $schedule = SecuritySchedule::find($id);
        
        if ($schedule) {
            return redirect()->back()
                ->with('error', 'Schedule must be moved to trash first before permanent deletion.')
                ->withInput();
        }
        
        // Then check if it's in trash
        $schedule = SecuritySchedule::onlyTrashed()->find($id);
        
        if (!$schedule) {
            Log::warning('Force delete attempted on non-existent schedule', [
                'schedule_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return redirect()->route('admin.security-schedules.trash')
                ->with('error', 'Schedule not found in trash. It may have already been deleted.');
        }
        
        // Store data for logging before deletion
        $scheduleData = [
            'id' => $schedule->id,
            'post_id' => $schedule->security_post_id,
            'user_id' => $schedule->security_user_id,
            'shift_id' => $schedule->security_shift_id,
            'date' => $schedule->assignment_date->format('Y-m-d'),
            'deleted_at' => $schedule->deleted_at->format('Y-m-d H:i:s')
        ];

        $schedule->forceDelete();

        // Clear caches
        $this->clearScheduleCaches($schedule);

        Log::info('Security schedule permanently deleted', [
            'schedule_id' => $id,
            'deleted_by' => auth()->id(),
            'schedule_data' => $scheduleData,
            'permanent_deleted_at' => now()
        ]);

        return redirect()->route('admin.security-schedules.trash')
            ->with('success', 'Security schedule permanently deleted.');

    } catch (\Exception $e) {
        Log::error('Failed to permanently delete security schedule: ' . $e->getMessage(), [
            'schedule_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()
            ->with('error', 'Failed to permanently delete security schedule. Please try again.');
    }
}

   /**
 * Bulk force delete multiple security schedules (Permanent Delete)
 */
public function bulkForceDelete(Request $request)
{
    $validator = Validator::make($request->all(), [
        'schedule_ids' => 'required|array',
        'schedule_ids.*' => 'required|integer', // Remove exists validation
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        $deletedCount = 0;
        $failedDeletes = [];
        $notInTrash = [];

        foreach ($request->schedule_ids as $scheduleId) {
            try {
                // Check if in main table (not deleted)
                $exists = SecuritySchedule::find($scheduleId);
                if ($exists) {
                    $notInTrash[] = [
                        'id' => $scheduleId,
                        'reason' => 'Schedule must be moved to trash first'
                    ];
                    continue;
                }
                
                $schedule = SecuritySchedule::onlyTrashed()->find($scheduleId);
                
                if (!$schedule) {
                    $failedDeletes[] = [
                        'id' => $scheduleId,
                        'reason' => 'Schedule not found in trash'
                    ];
                    continue;
                }

                $schedule->forceDelete();
                $deletedCount++;

                Log::info('Security schedule bulk permanently deleted', [
                    'schedule_id' => $scheduleId,
                    'deleted_by' => auth()->id()
                ]);

            } catch (\Exception $e) {
                $failedDeletes[] = [
                    'id' => $scheduleId,
                    'reason' => $e->getMessage()
                ];
            }
        }

        DB::commit();

        $message = $deletedCount > 0 
            ? "{$deletedCount} schedule(s) permanently deleted."
            : "No schedules were deleted.";

        if (!empty($notInTrash)) {
            $message .= " " . count($notInTrash) . " schedule(s) need to be trashed first.";
        }

        if (!empty($failedDeletes)) {
            $message .= " " . count($failedDeletes) . " schedule(s) could not be deleted.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'failed_count' => count($failedDeletes) + count($notInTrash),
            'failed_deletes' => array_merge($failedDeletes, $notInTrash)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to bulk permanently delete security schedules: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'schedule_ids' => $request->schedule_ids
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to bulk delete schedules.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Clear all trashed schedules older than specified days
     */
    public function clearOldTrash(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $cutoffDate = now()->subDays($request->days);
            
            $oldTrashed = SecuritySchedule::onlyTrashed()
                ->where('deleted_at', '<', $cutoffDate)
                ->get();
            
            $count = $oldTrashed->count();
            
            foreach ($oldTrashed as $schedule) {
                $schedule->forceDelete();
            }

            Log::info('Old trashed schedules cleared', [
                'deleted_count' => $count,
                'days_threshold' => $request->days,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                'deleted_by' => auth()->id()
            ]);

            return redirect()->route('admin.security-schedules.trash')
                ->with('success', "{$count} schedule(s) older than {$request->days} days have been permanently deleted.");

        } catch (\Exception $e) {
            Log::error('Failed to clear old trashed schedules: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'days' => $request->days
            ]);

            return redirect()->back()
                ->with('error', 'Failed to clear old trash. Please try again.');
        }
    }

    /**
     * Check for conflicts before restoring a soft deleted schedule
     */
    private function checkRestorationConflicts($schedule)
    {
        $conflicts = [];

        try {
            // Check if the post still exists and is active
            $post = SecurityPost::withTrashed()->find($schedule->security_post_id);
            if (!$post) {
                $conflicts[] = 'Associated security post no longer exists.';
            } elseif ($post->trashed()) {
                $conflicts[] = 'Associated security post is deleted. Please restore the post first.';
            } elseif (!$post->is_active) {
                $conflicts[] = 'Associated security post is inactive.';
            }

            // Check if the shift still exists
            $shift = SecurityShift::withTrashed()->find($schedule->security_shift_id);
            if (!$shift) {
                $conflicts[] = 'Associated shift no longer exists.';
            } elseif ($shift->trashed()) {
                $conflicts[] = 'Associated shift is deleted. Please restore the shift first.';
            } elseif (!$shift->is_active) {
                $conflicts[] = 'Associated shift is inactive.';
            }

            // Check if the user still exists
            $user = User::withTrashed()->find($schedule->security_user_id);
            if (!$user) {
                $conflicts[] = 'Associated security personnel no longer exists.';
            } elseif ($user->trashed()) {
                $conflicts[] = 'Associated security personnel is deleted. Please restore the user first.';
            } elseif ($user->status !== User::STATUS_ACTIVE) {
                $conflicts[] = 'Associated security personnel is not active.';
            }

            // Check for duplicate assignment on the same date
            $existingAssignment = SecuritySchedule::where('security_user_id', $schedule->security_user_id)
                ->whereDate('assignment_date', $schedule->assignment_date)
                ->where('id', '!=', $schedule->id)
                ->exists();
            
            if ($existingAssignment) {
                $conflicts[] = 'This personnel already has an assignment on this date.';
            }

            // Check post capacity
            if ($post && !$post->trashed()) {
                $assignedCount = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                    ->whereDate('assignment_date', $schedule->assignment_date)
                    ->count();
                
                if ($assignedCount >= $post->max_personnel) {
                    $conflicts[] = "This post has reached maximum capacity ({$post->max_personnel} personnel) for the selected date.";
                }
            }

            // Check shift applicability
            if ($shift && !$shift->trashed()) {
                $date = Carbon::parse($schedule->assignment_date);
                $dayOfWeek = $date->dayOfWeekIso;
                
                if (!$shift->isApplicableOnDay($dayOfWeek)) {
                    $conflicts[] = 'This shift is not applicable on the selected date.';
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to check restoration conflicts', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
            $conflicts[] = 'Error checking restoration conflicts: ' . $e->getMessage();
        }

        return $conflicts;
    }

    /**
     * Get statistics of deleted by users
     */
    private function getDeletedByStats()
    {
        try {
            $trashedSchedules = SecuritySchedule::onlyTrashed()->get();
            $deletedByStats = [];

            foreach ($trashedSchedules as $schedule) {
                if (isset($schedule->audit_log) && is_array($schedule->audit_log)) {
                    foreach ($schedule->audit_log as $log) {
                        if ($log['action'] === 'deleted' && isset($log['deleted_by_name'])) {
                            $name = $log['deleted_by_name'];
                            $deletedByStats[$name] = ($deletedByStats[$name] ?? 0) + 1;
                        }
                    }
                }
            }

            arsort($deletedByStats);
            return $deletedByStats;
        } catch (\Exception $e) {
            Log::warning('Failed to get deleted by stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate rotating shift constraints
     */
    private function validateRotatingShiftConstraints($validator, $request, $user, $shift, $date)
    {
        if ($shift->rotation_type !== 'rotating') {
            return;
        }

        // Check minimum gap between shifts
        $lastShift = SecuritySchedule::where('security_user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('assignment_date', 'desc')
            ->first();

        if ($lastShift) {
            $hoursBetweenShifts = $date->diffInHours($lastShift->assignment_date);
            $minHoursRequired = 12;
            
            if ($hoursBetweenShifts < $minHoursRequired) {
                $validator->errors()->add(
                    'security_user_id',
                    "This personnel needs at least {$minHoursRequired} hours rest between shifts. Last shift was on {$lastShift->assignment_date->format('Y-m-d')}"
                );
            }
        }

        // Check weekly hour limits
        $weekStart = $date->copy()->startOfWeek();
        $weekEnd = $date->copy()->endOfWeek();
        
        $weeklyHours = SecuritySchedule::where('security_user_id', $user->id)
            ->whereBetween('assignment_date', [$weekStart, $weekEnd])
            ->where('status', '!=', 'cancelled')
            ->with('shift')
            ->get()
            ->sum(function($schedule) {
                return $schedule->shift->duration_hours;
            });

        $maxWeeklyHours = 60;
        
        if (($weeklyHours + $shift->duration_hours) > $maxWeeklyHours) {
            $validator->errors()->add(
                'security_user_id',
                "This personnel will exceed the maximum weekly hours ({$maxWeeklyHours} hours). Current weekly total: {$weeklyHours} hours"
            );
        }
    }

    /**
     * OPTIMIZED: Calculate handover times with better performance
     */
    private function calculateHandoverTimesOptimized($shift, $assignmentDate, $postId = null)
    {
        if (!$shift || !$shift->handover_config || empty($shift->handover_config['has_handover'])) {
            return null;
        }

        $handoverDuration = $shift->handover_config['handover_duration'] ?? 30;
        $assignmentDate = Carbon::parse($assignmentDate);
        
        if (!$postId) {
            return null;
        }

        $cacheKey = "handover_{$postId}_{$assignmentDate->format('Y-m-d')}_{$shift->id}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function() use ($postId, $assignmentDate, $shift, $handoverDuration) {
            try {
                $previousSchedule = SecuritySchedule::where('security_post_id', $postId)
                    ->whereDate('assignment_date', $assignmentDate)
                    ->where('security_shift_id', '!=', $shift->id)
                    ->whereIn('status', ['active', 'scheduled'])
                    ->orderBy('assignment_date', 'desc')
                    ->select('id', 'security_shift_id')
                    ->first();
                
                if (!$previousSchedule) {
                    return null;
                }

                $previousShift = Cache::remember("shift_{$previousSchedule->security_shift_id}", now()->addHour(), function() use ($previousSchedule) {
                    return SecurityShift::find($previousSchedule->security_shift_id, ['id', 'end_time']);
                });

                if (!$previousShift) {
                    return null;
                }

                $handoverStart = Carbon::parse($previousShift->end_time);
                $handoverEnd = Carbon::parse($shift->start_time);

                return [
                    'previous_shift_id' => $previousSchedule->id,
                    'handover_start' => $handoverStart->format('H:i'),
                    'handover_end' => $handoverEnd->format('H:i'),
                    'handover_duration' => $handoverDuration,
                    'handover_window' => $handoverStart->diffInMinutes($handoverEnd),
                    'notes_required' => $shift->handover_config['handover_notes_required'] ?? true,
                    'checklist' => $shift->handover_config['handover_checklist'] ?? []
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to fetch previous schedule for handover', [
                    'post_id' => $postId,
                    'date' => $assignmentDate,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * OPTIMIZED: Prepare rotation data with caching
     */
    private function prepareRotationDataOptimized($shift, $userId)
    {
        if (!$shift || $shift->rotation_type !== 'rotating') {
            return null;
        }

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $rotationConfig = $shift->rotation_config ?? [];
        $sequence = $this->getRotationSequence($rotationConfig['rotation_sequence'] ?? 'morning_evening');
        $currentIndex = $rotationConfig['current_sequence_index'] ?? 0;

        $cacheKey = "rotation_history_{$userId}_{$shift->id}";
        $history = Cache::remember($cacheKey, now()->addMinutes(10), function() use ($userId, $shift) {
            return $this->getUserRotationHistoryOptimized($userId, $shift->id);
        });

        $nextRotationDate = null;
        if (!empty($rotationConfig['last_rotation_date'])) {
            try {
                $lastRotation = Carbon::parse($rotationConfig['last_rotation_date']);
                $rotationDays = $rotationConfig['rotation_days'] ?? 7;
                $nextRotationDate = $lastRotation->addDays($rotationDays);
            } catch (\Exception $e) {
                Log::warning('Failed to calculate next rotation date', [
                    'shift_id' => $shift->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'sequence_type' => $rotationConfig['rotation_sequence'] ?? 'morning_evening',
            'current_position' => $sequence[$currentIndex] ?? $sequence[0],
            'next_position' => $sequence[($currentIndex + 1) % count($sequence)] ?? $sequence[0],
            'next_rotation_date' => $nextRotationDate?->format('Y-m-d'),
            'rotation_days' => $rotationConfig['rotation_days'] ?? 7,
            'user_rotation_history' => $history
        ];
    }

    /**
     * OPTIMIZED: Get user rotation history with better performance
     */
    private function getUserRotationHistoryOptimized($userId, $shiftId)
    {
        try {
            return SecuritySchedule::where('security_user_id', $userId)
                ->where('security_shift_id', $shiftId)
                ->where('status', 'completed')
                ->orderBy('assignment_date', 'desc')
                ->limit(5)
                ->get(['assignment_date', 'status', 'checkin_time', 'checkout_time', 'late_minutes', 'overtime_minutes'])
                ->map(function($schedule) {
                    return [
                        'date' => $schedule->assignment_date->format('Y-m-d'),
                        'status' => $schedule->status,
                        'duration' => $this->calculateDuration($schedule->checkin_time, $schedule->checkout_time),
                        'was_on_time' => ($schedule->late_minutes ?? 0) == 0,
                        'had_overtime' => ($schedule->overtime_minutes ?? 0) > 0
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get user rotation history', [
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Helper method to calculate duration
     */
    private function calculateDuration($checkin, $checkout)
    {
        if (!$checkin || !$checkout) {
            return null;
        }
        
        try {
            $checkinTime = $checkin instanceof Carbon ? $checkin : Carbon::parse($checkin);
            $checkoutTime = $checkout instanceof Carbon ? $checkout : Carbon::parse($checkout);
            return $checkinTime->diffInMinutes($checkoutTime);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get rotation sequence
     */
    private function getRotationSequence($sequenceType)
    {
        $sequences = [
            'morning_evening' => ['morning', 'evening', 'night', 'off'],
            'evening_morning' => ['evening', 'morning', 'night', 'off'],
            'night_morning' => ['night', 'morning', 'evening', 'off']
        ];
        
        return $sequences[$sequenceType] ?? ['morning', 'evening', 'night', 'off'];
    }

    /**
     * Queue assignment notification for background processing
     */
    private function queueAssignmentNotification($schedule)
    {
        try {
            Log::info('Assignment notification queued', [
                'schedule_id' => $schedule->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue assignment notification', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queue handover checklist generation for background processing
     */
    private function queueHandoverChecklistGeneration($schedule)
    {
        if (!$schedule->handover_info) {
            return;
        }

        try {
            $this->generateHandoverChecklistOptimized($schedule);
        } catch (\Exception $e) {
            Log::warning('Failed to queue handover checklist generation', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * OPTIMIZED: Generate handover checklist
     */
    private function generateHandoverChecklistOptimized($schedule)
    {
        if (!$schedule->handover_info) {
            return;
        }

        try {
            $handoverInfo = $schedule->handover_info;
            $checklistItems = $handoverInfo['checklist'] ?? [];
            
            if (!empty($checklistItems)) {
                $handoverInfo['checklist_items'] = array_map(function($item) {
                    return [
                        'item' => $item,
                        'completed' => false,
                        'completed_by' => null,
                        'completed_at' => null,
                        'notes' => null
                    ];
                }, $checklistItems);
                
                $schedule->timestamps = false;
                $schedule->update(['handover_info' => $handoverInfo]);
                $schedule->timestamps = true;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to generate handover checklist', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear schedule-related caches
     */
    private function clearScheduleCaches($schedule)
    {
        try {
            $cacheKeys = [
                "handover_{$schedule->security_post_id}_{$schedule->assignment_date->format('Y-m-d')}_{$schedule->security_shift_id}",
                "rotation_history_{$schedule->security_user_id}_{$schedule->security_shift_id}",
                "shift_{$schedule->security_shift_id}",
                "post_capacity_{$schedule->security_post_id}_{$schedule->assignment_date->format('Y-m-d')}",
                "schedule_stats_" . today()->format('Y-m-d')
            ];
            
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear schedule caches', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * ==================== SHIFT GROUP ROTATION METHODS ====================
     */

    /**
     * Rotate personnel between day and night shift groups
     */
    public function rotateShiftGroups(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rotation_frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'rotation_pattern' => 'required|in:full_swap,staggered,partial',
            'exclude_personnel' => 'nullable|array',
            'exclude_personnel.*' => 'exists:users,id',
            'respect_preferences' => 'boolean',
            'maintain_coverage' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $post = SecurityPost::find($request->post_id);
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            
            $schedules = SecuritySchedule::with(['shift:id,category', 'securityUser:id,name'])
                ->where('security_post_id', $request->post_id)
                ->whereBetween('assignment_date', [$startDate, $endDate])
                ->orderBy('assignment_date')
                ->orderBy('security_shift_id')
                ->get(['id', 'security_post_id', 'security_shift_id', 'security_user_id', 'assignment_date']);

            $groupedByDate = $schedules->groupBy(function($schedule) {
                return $schedule->assignment_date->format('Y-m-d');
            });

            $rotatedSchedules = [];
            $rotationLog = [];
            $rotationCounter = 0;
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->format('Y-m-d');
                
                if ($this->shouldRotateOnDate($currentDate, $startDate, $request->rotation_frequency, $rotationCounter)) {
                    
                    $dateSchedules = $groupedByDate->get($dateStr, collect());
                    
                    if ($dateSchedules->isNotEmpty()) {
                        $rotatedForDate = $this->applyShiftGroupRotation(
                            $dateSchedules,
                            $request->rotation_pattern,
                            $request->exclude_personnel ?? [],
                            $request->maintain_coverage ?? true
                        );
                        
                        $rotatedSchedules = array_merge($rotatedSchedules, $rotatedForDate['schedules']);
                        $rotationLog[$dateStr] = $rotatedForDate['log'];
                        
                        foreach ($rotatedForDate['schedules'] as $rotated) {
                            $schedule = SecuritySchedule::find($rotated['id']);
                            if ($schedule) {
                                $rotationData = $schedule->rotation_data ?? [];
                                $rotationData['rotated_from'] = $rotated['old_user_id'];
                                $rotationData['rotated_at'] = now();
                                $rotationData['rotation_type'] = 'shift_group';
                                $rotationData['previous_shift'] = $rotated['old_shift_id'];
                                $rotationData['new_shift'] = $rotated['new_shift_id'];
                                $rotationData['rotation_group'] = 'day_night_swap';
                                
                                $schedule->update([
                                    'security_user_id' => $rotated['new_user_id'],
                                    'rotated_from_user_id' => $rotated['old_user_id'],
                                    'is_rotated' => true,
                                    'rotated_at' => now(),
                                    'rotation_swap_count' => ($schedule->rotation_swap_count ?? 0) + 1,
                                    'rotation_data' => $rotationData
                                ]);
                                
                                $this->sendRotationNotification(
                                    $rotated['old_user_id'],
                                    $rotated['new_user_id'],
                                    $schedule
                                );
                            }
                        }
                    }
                }
                
                $rotationCounter++;
                $currentDate->addDay();
            }

            DB::commit();

            Log::info('Shift group rotation completed', [
                'post_id' => $request->post_id,
                'post_name' => $post->name,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'frequency' => $request->rotation_frequency,
                'pattern' => $request->rotation_pattern,
                'total_rotations' => count($rotatedSchedules),
                'rotated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shift group rotation completed successfully.',
                'rotated_count' => count($rotatedSchedules),
                'rotation_log' => $rotationLog,
                'summary' => $this->generateRotationSummary($rotatedSchedules, $rotationLog)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to rotate shift groups: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate shift groups.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine if rotation should occur on a given date
     */
    private function shouldRotateOnDate(Carbon $date, Carbon $startDate, $frequency, $counter)
    {
        switch ($frequency) {
            case 'daily':
                return true;
            case 'weekly':
                return $date->dayOfWeek === Carbon::MONDAY;
            case 'biweekly':
                $weeksSinceStart = $startDate->diffInWeeks($date);
                return $date->dayOfWeek === Carbon::MONDAY && $weeksSinceStart % 2 === 0;
            case 'monthly':
                return $date->day === 1;
            default:
                return false;
        }
    }

    /**
     * Apply shift group rotation to schedules for a specific date
     */
    private function applyShiftGroupRotation($schedules, $pattern, $excludePersonnel, $maintainCoverage)
    {
        $dayShifts = $schedules->filter(function($schedule) {
            return $schedule->shift && $schedule->shift->category === 'day';
        })->values();

        $nightShifts = $schedules->filter(function($schedule) {
            return $schedule->shift && $schedule->shift->category === 'night';
        })->values();

        $rotated = [];
        $log = [];

        if ($dayShifts->isEmpty() || $nightShifts->isEmpty()) {
            return ['schedules' => $rotated, 'log' => $log];
        }

        $dayPersonnel = $dayShifts->pluck('security_user_id')->toArray();
        $nightPersonnel = $nightShifts->pluck('security_user_id')->toArray();
        
        $availableDayPersonnel = array_diff($dayPersonnel, $excludePersonnel);
        $availableNightPersonnel = array_diff($nightPersonnel, $excludePersonnel);

        $dayScheduleMap = [];
        foreach ($dayShifts as $schedule) {
            $dayScheduleMap[$schedule->security_user_id] = $schedule;
        }

        $nightScheduleMap = [];
        foreach ($nightShifts as $schedule) {
            $nightScheduleMap[$schedule->security_user_id] = $schedule;
        }

        switch ($pattern) {
            case 'full_swap':
                $result = $this->performFullSwap(
                    $dayShifts, 
                    $nightShifts, 
                    $availableDayPersonnel,
                    $availableNightPersonnel,
                    $dayScheduleMap,
                    $nightScheduleMap,
                    $log
                );
                $rotated = $result['rotated'];
                $log = $result['log'];
                break;
                
            case 'staggered':
                $result = $this->performStaggeredSwap(
                    $dayShifts,
                    $nightShifts,
                    $availableDayPersonnel,
                    $availableNightPersonnel,
                    $dayScheduleMap,
                    $nightScheduleMap,
                    $log
                );
                $rotated = $result['rotated'];
                $log = $result['log'];
                break;
                
            case 'partial':
                $result = $this->performPartialSwap(
                    $dayShifts,
                    $nightShifts,
                    $availableDayPersonnel,
                    $availableNightPersonnel,
                    $dayScheduleMap,
                    $nightScheduleMap,
                    $log
                );
                $rotated = $result['rotated'];
                $log = $result['log'];
                break;
        }

        if ($maintainCoverage && !empty($rotated)) {
            $rotated = $this->ensureCoverageMaintained($rotated, $schedules, $log);
        }

        return [
            'schedules' => $rotated,
            'log' => $log
        ];
    }

    /**
     * Perform full swap between day and night shifts
     */
    private function performFullSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayScheduleMap, $nightScheduleMap, &$log)
    {
        $rotated = [];
        $swapCount = min(count($availableDay), count($availableNight));
        
        $dayToSwap = array_slice($availableDay, 0, $swapCount);
        $nightToSwap = array_slice($availableNight, 0, $swapCount);
        
        for ($i = 0; $i < $swapCount; $i++) {
            $dayUserId = $dayToSwap[$i];
            $nightUserId = $nightToSwap[$i];
            
            $daySchedule = $dayScheduleMap[$dayUserId] ?? null;
            $nightSchedule = $nightScheduleMap[$nightUserId] ?? null;
            
            if ($daySchedule && $nightSchedule) {
                $rotated[] = [
                    'id' => $daySchedule->id,
                    'old_user_id' => $daySchedule->security_user_id,
                    'new_user_id' => $nightSchedule->security_user_id,
                    'old_shift_id' => $daySchedule->security_shift_id,
                    'new_shift_id' => $nightSchedule->security_shift_id,
                    'date' => $daySchedule->assignment_date->format('Y-m-d')
                ];
                
                $rotated[] = [
                    'id' => $nightSchedule->id,
                    'old_user_id' => $nightSchedule->security_user_id,
                    'new_user_id' => $daySchedule->security_user_id,
                    'old_shift_id' => $nightSchedule->security_shift_id,
                    'new_shift_id' => $daySchedule->security_shift_id,
                    'date' => $nightSchedule->assignment_date->format('Y-m-d')
                ];
                
                $log[] = "Full swap: {$daySchedule->securityUser->name} (Day) ↔ {$nightSchedule->securityUser->name} (Night)";
            }
        }
        
        return ['rotated' => $rotated, 'log' => $log];
    }

    /**
     * Perform staggered swap (rotate half the personnel)
     */
    private function performStaggeredSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayScheduleMap, $nightScheduleMap, &$log)
    {
        $rotated = [];
        
        $daySwapCount = ceil(count($availableDay) / 2);
        $nightSwapCount = ceil(count($availableNight) / 2);
        $swapCount = min($daySwapCount, $nightSwapCount);
        
        $dayIndices = array_rand($availableDay, min($swapCount, count($availableDay)));
        if (!is_array($dayIndices)) {
            $dayIndices = [$dayIndices];
        }
        
        $nightIndices = array_rand($availableNight, min($swapCount, count($availableNight)));
        if (!is_array($nightIndices)) {
            $nightIndices = [$nightIndices];
        }
        
        for ($i = 0; $i < min(count($dayIndices), count($nightIndices)); $i++) {
            $dayUserId = $availableDay[$dayIndices[$i]];
            $nightUserId = $availableNight[$nightIndices[$i]];
            
            $daySchedule = $dayScheduleMap[$dayUserId] ?? null;
            $nightSchedule = $nightScheduleMap[$nightUserId] ?? null;
            
            if ($daySchedule && $nightSchedule) {
                $rotated[] = [
                    'id' => $daySchedule->id,
                    'old_user_id' => $daySchedule->security_user_id,
                    'new_user_id' => $nightSchedule->security_user_id,
                    'old_shift_id' => $daySchedule->security_shift_id,
                    'new_shift_id' => $nightSchedule->security_shift_id,
                    'date' => $daySchedule->assignment_date->format('Y-m-d')
                ];
                
                $rotated[] = [
                    'id' => $nightSchedule->id,
                    'old_user_id' => $nightSchedule->security_user_id,
                    'new_user_id' => $daySchedule->security_user_id,
                    'old_shift_id' => $nightSchedule->security_shift_id,
                    'new_shift_id' => $daySchedule->security_shift_id,
                    'date' => $nightSchedule->assignment_date->format('Y-m-d')
                ];
                
                $log[] = "Staggered swap: {$daySchedule->securityUser->name} ↔ {$nightSchedule->securityUser->name}";
            }
        }
        
        return ['rotated' => $rotated, 'log' => $log];
    }

    /**
     * Perform partial swap (only willing participants)
     */
    private function performPartialSwap($dayShifts, $nightShifts, $availableDay, $availableNight, $dayScheduleMap, $nightScheduleMap, &$log)
    {
        $rotated = [];
        
        $willingDay = $this->getWillingForRotation($availableDay, 'day_to_night');
        $willingNight = $this->getWillingForRotation($availableNight, 'night_to_day');
        
        $swapCount = min(count($willingDay), count($willingNight));
        
        for ($i = 0; $i < $swapCount; $i++) {
            $dayUserId = $willingDay[$i];
            $nightUserId = $willingNight[$i];
            
            $daySchedule = $dayScheduleMap[$dayUserId] ?? null;
            $nightSchedule = $nightScheduleMap[$nightUserId] ?? null;
            
            if ($daySchedule && $nightSchedule) {
                $rotated[] = [
                    'id' => $daySchedule->id,
                    'old_user_id' => $daySchedule->security_user_id,
                    'new_user_id' => $nightSchedule->security_user_id,
                    'old_shift_id' => $daySchedule->security_shift_id,
                    'new_shift_id' => $nightSchedule->security_shift_id,
                    'date' => $daySchedule->assignment_date->format('Y-m-d')
                ];
                
                $rotated[] = [
                    'id' => $nightSchedule->id,
                    'old_user_id' => $nightSchedule->security_user_id,
                    'new_user_id' => $daySchedule->security_user_id,
                    'old_shift_id' => $nightSchedule->security_shift_id,
                    'new_shift_id' => $daySchedule->security_shift_id,
                    'date' => $nightSchedule->assignment_date->format('Y-m-d')
                ];
                
                $log[] = "Voluntary swap: {$daySchedule->securityUser->name} ↔ {$nightSchedule->securityUser->name}";
            }
        }
        
        return ['rotated' => $rotated, 'log' => $log];
    }

    /**
     * Get personnel willing to rotate
     */
    private function getWillingForRotation($personnelIds, $direction)
    {
        $willing = [];
        
        foreach ($personnelIds as $id) {
            $user = Cache::remember("user_{$id}_preferences", now()->addHour(), function() use ($id) {
                return User::find($id, ['id', 'preferences']);
            });
            
            if ($user && $user->preferences && 
                isset($user->preferences['willing_to_rotate'][$direction]) && 
                $user->preferences['willing_to_rotate'][$direction]) {
                $willing[] = $id;
            }
        }
        
        return $willing;
    }

    /**
     * Ensure coverage is maintained after rotation
     */
    private function ensureCoverageMaintained($rotated, $originalSchedules, &$log)
    {
        $postIds = $originalSchedules->pluck('security_post_id')->unique();
        
        foreach ($postIds as $postId) {
            $postSchedules = $originalSchedules->where('security_post_id', $postId);
            
            $rotatedForPost = array_filter($rotated, function($r) use ($postId, $postSchedules) {
                $schedule = $postSchedules->firstWhere('id', $r['id']);
                return $schedule && $schedule->security_post_id == $postId;
            });
            
            $assignedUserIds = array_column($rotatedForPost, 'new_user_id');
            $duplicateUsers = array_diff_assoc($assignedUserIds, array_unique($assignedUserIds));
            
            if (!empty($duplicateUsers)) {
                $log[] = "Coverage adjustment needed: Duplicate assignments detected";
                $rotated = $this->fixDuplicateAssignments($rotated, $originalSchedules, $postId);
            }
            
            if (count($rotatedForPost) < count($postSchedules)) {
                $log[] = "Coverage adjustment needed: Missing assignments";
                $rotated = $this->fixMissingAssignments($rotated, $originalSchedules, $postId);
            }
        }
        
        return $rotated;
    }

    /**
     * Fix duplicate assignments after rotation
     */
    private function fixDuplicateAssignments($rotated, $originalSchedules, $postId)
    {
        $fixed = [];
        $usedUsers = [];
        
        foreach ($rotated as $rotation) {
            $schedule = $originalSchedules->firstWhere('id', $rotation['id']);
            if (!$schedule) continue;
            
            if (in_array($rotation['new_user_id'], $usedUsers)) {
                $fixed[] = [
                    'id' => $rotation['id'],
                    'old_user_id' => $rotation['old_user_id'],
                    'new_user_id' => $rotation['old_user_id'],
                    'old_shift_id' => $rotation['old_shift_id'],
                    'new_shift_id' => $rotation['old_shift_id'],
                    'date' => $rotation['date']
                ];
            } else {
                $fixed[] = $rotation;
                $usedUsers[] = $rotation['new_user_id'];
            }
        }
        
        return $fixed;
    }

    /**
     * Fix missing assignments after rotation
     */
    private function fixMissingAssignments($rotated, $originalSchedules, $postId)
    {
        $originalIds = $originalSchedules->where('security_post_id', $postId)
            ->pluck('id')
            ->toArray();
        
        $rotatedIds = array_column($rotated, 'id');
        
        $missingIds = array_diff($originalIds, $rotatedIds);
        
        foreach ($missingIds as $missingId) {
            $original = $originalSchedules->firstWhere('id', $missingId);
            if ($original) {
                $rotated[] = [
                    'id' => $original->id,
                    'old_user_id' => $original->security_user_id,
                    'new_user_id' => $original->security_user_id,
                    'old_shift_id' => $original->security_shift_id,
                    'new_shift_id' => $original->security_shift_id,
                    'date' => $original->assignment_date->format('Y-m-d')
                ];
            }
        }
        
        return $rotated;
    }

    /**
     * Send notification about rotation
     */
    private function sendRotationNotification($oldUserId, $newUserId, $schedule)
    {
        try {
            $oldUser = User::find($oldUserId);
            $newUser = User::find($newUserId);
            
            if ($oldUser && $newUser) {
                Log::info('Rotation notifications prepared', [
                    'old_user' => $oldUser->id,
                    'new_user' => $newUser->id,
                    'schedule_id' => $schedule->id
                ]);
                
                $this->queueRotationNotification($oldUser, $newUser, $schedule);
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to send rotation notification', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queue rotation notification
     */
    private function queueRotationNotification($oldUser, $newUser, $schedule)
    {
        Log::info('Rotation notification queued', [
            'old_user_id' => $oldUser->id,
            'new_user_id' => $newUser->id,
            'schedule_id' => $schedule->id
        ]);
    }

    /**
     * Generate rotation summary
     */
    private function generateRotationSummary($rotatedSchedules, $rotationLog)
    {
        $summary = [
            'total_rotations' => count($rotatedSchedules),
            'unique_dates_rotated' => count($rotationLog),
            'personnel_affected' => [],
            'shift_type_changes' => [
                'day_to_night' => 0,
                'night_to_day' => 0
            ]
        ];
        
        foreach ($rotatedSchedules as $rotation) {
            $userId = $rotation['new_user_id'];
            if (!in_array($userId, $summary['personnel_affected'])) {
                $summary['personnel_affected'][] = $userId;
            }
            
            $oldShift = Cache::remember("shift_{$rotation['old_shift_id']}", now()->addHour(), function() use ($rotation) {
                return SecurityShift::find($rotation['old_shift_id'], ['id', 'category']);
            });
            
            $newShift = Cache::remember("shift_{$rotation['new_shift_id']}", now()->addHour(), function() use ($rotation) {
                return SecurityShift::find($rotation['new_shift_id'], ['id', 'category']);
            });
            
            if ($oldShift && $newShift) {
                if ($oldShift->category === 'day' && $newShift->category === 'night') {
                    $summary['shift_type_changes']['day_to_night']++;
                } elseif ($oldShift->category === 'night' && $newShift->category === 'day') {
                    $summary['shift_type_changes']['night_to_day']++;
                }
            }
        }
        
        $summary['personnel_affected_count'] = count($summary['personnel_affected']);
        
        return $summary;
    }

    /**
     * ==================== BULK ASSIGNMENT METHODS ====================
     */

   /**
 * Bulk assign security personnel with enhanced features
 */
public function bulkAssign(Request $request)
{
    $startTime = microtime(true);
    Log::info('===== BULK ASSIGN STARTED =====', [
        'request_data' => $request->except(['_token']),
        'user_id' => auth()->id()
    ]);

    $validator = Validator::make($request->all(), [
        'security_post_id' => 'required|exists:security_posts,id',
        'security_shift_id' => 'required|exists:security_shifts,id',
        'security_user_ids' => 'required|array|min:1',
        'security_user_ids.*' => 'exists:users,id',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'recurrence_type' => 'required|in:daily,weekly,monthly,custom,rotating',
        'recurrence_days' => 'nullable|array',
        'recurrence_days.*' => 'integer|min:1|max:7',
        'rotation_pattern' => 'nullable|in:full_swap,staggered,partial,sequential,alternating,preference_based',
        'rotation_pattern_config' => 'nullable|array',
        'include_breaks' => 'nullable|boolean',
        'include_handover' => 'nullable|boolean',
        'notes' => 'nullable|string|max:500',
        'emergency_contact' => 'nullable|string|max:255',
        'special_instructions' => 'nullable|string|max:1000',
        'create_rotation_group' => 'nullable|boolean',
        'rotation_group_name' => 'required_if:create_rotation_group,true|nullable|string|max:255',
        'maintain_coverage' => 'nullable|boolean',
        'respect_preferences' => 'nullable|boolean',
        'notify_personnel' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        $post = Cache::remember("post_{$request->security_post_id}", now()->addMinutes(10), function() use ($request) {
            return SecurityPost::withTrashed()->find($request->security_post_id);
        });
        
        $shift = Cache::remember("shift_{$request->security_shift_id}", now()->addMinutes(10), function() use ($request) {
            return SecurityShift::withTrashed()->find($request->security_shift_id);
        });
        
        if (!$post || !$shift) {
            throw new \Exception('Security post or shift not found.');
        }

        if ($post->trashed() || !$post->is_active) {
            throw new \Exception('Security post is inactive or deleted.');
        }

        if ($shift->trashed() || !$shift->is_active) {
            throw new \Exception('Security shift is inactive or deleted.');
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $createdScheduleIds = [];
        $failedAssignments = [];
        $rotationGroup = null;
        
        if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern')) {
            try {
                $rotationGroup = $this->getOrCreateRotationGroup($request, $post, $shift);
                Log::info('Rotation group ready', [
                    'group_id' => $rotationGroup->id,
                    'group_name' => $rotationGroup->name,
                    'pattern' => $request->rotation_pattern
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create rotation group, proceeding without group', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $applicableDates = $this->calculateApplicableDates(
            $startDate, 
            $endDate, 
            $request->recurrence_type, 
            $request->recurrence_days ?? []
        );
        
        $existingAssignments = DB::table('security_schedules')
            ->whereIn('security_user_id', $request->security_user_ids)
            ->whereBetween('assignment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get(['security_user_id', 'assignment_date'])
            ->mapWithKeys(function($item) {
                return [$item->security_user_id . '_' . $item->assignment_date => true];
            })
            ->toArray();

        $handoverInfo = null;
        if ($request->boolean('include_handover', false)) {
            $handoverInfo = $this->calculateHandoverTimesOptimized($shift, $startDate, $request->security_post_id);
            $handoverInfo = $handoverInfo ? json_encode($handoverInfo) : null;
        }

        $userPreferenceScores = [];
        if ($request->boolean('respect_preferences', true) && $rotationGroup) {
            foreach ($request->security_user_ids as $userId) {
                $userPreferenceScores[$userId] = $this->calculateUserPreferenceScore($userId, $shift, $post, $rotationGroup);
            }
            arsort($userPreferenceScores);
            $sortedUserIds = array_keys($userPreferenceScores);
        } else {
            $sortedUserIds = $request->security_user_ids;
        }

        $batchData = [];
        $userCount = count($sortedUserIds);
        $userIndex = 0;
        $now = now();
        $rotationSequence = [];

        foreach ($applicableDates as $date) {
            $dateStr = $date->format('Y-m-d');
            
            $assignedCount = Cache::remember(
                "post_capacity_{$post->id}_{$dateStr}", 
                now()->addMinutes(5), 
                function() use ($post, $dateStr) {
                    return DB::table('security_schedules')
                        ->where('security_post_id', $post->id)
                        ->whereDate('assignment_date', $dateStr)
                        ->count();
                }
            );
            
            $availableSlots = $post->max_personnel - $assignedCount;
            
            if ($availableSlots <= 0) {
                $failedAssignments[] = [
                    'date' => $dateStr,
                    'post_id' => $post->id,
                    'post_name' => $post->name,
                    'reason' => 'Post at full capacity'
                ];
                continue;
            }

            $dayOfWeek = $date->dayOfWeekIso;
            
            // FIXED: Only check applicability if the shift has specific applicable days
            // If applicable_days is null, it means "All Days" - always applicable
            if ($shift->applicable_days !== null && !empty($shift->applicable_days)) {
                if (!in_array($dayOfWeek, $shift->applicable_days)) {
                    $failedAssignments[] = [
                        'date' => $dateStr,
                        'post_id' => $post->id,
                        'post_name' => $post->name,
                        'reason' => 'Shift not applicable on this day'
                    ];
                    continue;
                }
            }

            $personnelToAssign = [];
            $slotsToFill = min($availableSlots, $userCount);
            
            if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern') && $rotationGroup) {
                $personnelToAssign = $this->getPersonnelForRotatingDate(
                    $sortedUserIds,
                    $userPreferenceScores,
                    $date,
                    $startDate,
                    $request->rotation_pattern,
                    $slotsToFill,
                    $existingAssignments
                );
                
                $rotationSequence[] = [
                    'date' => $dateStr,
                    'assigned_members' => $personnelToAssign
                ];
            } else {
                for ($i = 0; $i < $slotsToFill; $i++) {
                    $userId = $sortedUserIds[($userIndex + $i) % $userCount];
                    $dateKey = $userId . '_' . $dateStr;
                    
                    if (!isset($existingAssignments[$dateKey])) {
                        if ($this->validatePersonnelConstraints($userId, $date, $shift)) {
                            $personnelToAssign[] = $userId;
                            $existingAssignments[$dateKey] = true;
                        } else {
                            $failedAssignments[] = [
                                'date' => $dateStr,
                                'post_id' => $post->id,
                                'post_name' => $post->name,
                                'user_id' => $userId,
                                'reason' => 'Failed personnel constraints (rest/weekly hours)'
                            ];
                        }
                    }
                }
                $userIndex = ($userIndex + $slotsToFill) % $userCount;
            }

            foreach ($personnelToAssign as $userId) {
                $rotationData = null;
                $isRotated = false;
                $rotationPreferenceScore = 5;
                
                if ($rotationGroup) {
                    $isRotated = true;
                    $rotationPreferenceScore = $userPreferenceScores[$userId] ?? 5;
                    $rotationData = [
                        'rotation_group_id' => $rotationGroup->id,
                        'rotation_group_name' => $rotationGroup->name,
                        'rotation_pattern' => $request->rotation_pattern,
                        'rotation_date' => $now->format('Y-m-d H:i:s'),
                        'preference_score' => $rotationPreferenceScore,
                        'sequence_position' => array_search($userId, $sortedUserIds) ?: 0,
                    ];
                }

                $batchData[] = [
                    'security_post_id' => $post->id,
                    'security_shift_id' => $shift->id,
                    'security_user_id' => $userId,
                    'assigned_by' => auth()->id(),
                    'assignment_date' => $dateStr,
                    'status' => 'scheduled',
                    'notes' => $request->notes,
                    'emergency_contact' => $request->emergency_contact,
                    'special_instructions' => $request->special_instructions,
                    'include_breaks' => $request->boolean('include_breaks', false),
                    'handover_info' => $handoverInfo,
                    'rotation_data' => $rotationData ? json_encode($rotationData) : null,
                    'rotation_group_id' => $rotationGroup?->id,
                    'rotation_group_type' => $shift->category,
                    'rotation_sequence_number' => $rotationGroup ? $rotationGroup->rotation_config['current_sequence_index'] ?? 0 : 0,
                    'rotation_preference_score' => $rotationPreferenceScore,
                    'is_rotated' => $isRotated,
                    'rotation_swap_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($batchData) >= 50) {
                DB::table('security_schedules')->insert($batchData);
                $createdScheduleIds = array_merge($createdScheduleIds, array_fill(0, count($batchData), 0));
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('security_schedules')->insert($batchData);
            $createdScheduleIds = array_merge($createdScheduleIds, array_fill(0, count($batchData), 0));
        }

        if ($rotationGroup && !empty($rotationSequence)) {
            $this->updateRotationGroupAfterBulkAssign($rotationGroup, $rotationSequence, $sortedUserIds, $request);
        }

        if ($request->boolean('maintain_coverage', true)) {
            $this->ensureCoverageForBulkAssign($post, $shift, $startDate, $endDate, $applicableDates);
        }

        DB::commit();

        if (!empty($createdScheduleIds) && $request->boolean('notify_personnel', true)) {
            $this->queueBulkAssignNotifications($post, $shift, $request->security_user_ids, $startDate, $endDate, $now);
        }

        if ($rotationGroup && $request->recurrence_type === 'rotating') {
            $this->queueRotationExecution($rotationGroup, $startDate, $endDate, $request);
        }

        $totalTime = microtime(true) - $startTime;
        $createdCount = count($createdScheduleIds);
        
        Log::info('===== BULK ASSIGN COMPLETED =====', [
            'created_count' => $createdCount,
            'failed_count' => count($failedAssignments),
            'rotation_group_id' => $rotationGroup?->id,
            'rotation_pattern' => $request->rotation_pattern,
            'total_time' => $totalTime
        ]);

        $summary = [
            'total_assignments' => $createdCount,
            'unique_users' => count(array_unique($request->security_user_ids)),
            'date_range' => [
                'from' => $startDate->format('Y-m-d'),
                'to' => $endDate->format('Y-m-d')
            ],
            'failed_assignments_by_reason' => $this->groupFailedAssignments($failedAssignments),
            'rotation_group' => $rotationGroup ? [
                'id' => $rotationGroup->id,
                'name' => $rotationGroup->name,
                'pattern' => $request->rotation_pattern
            ] : null,
            'coverage_maintained' => $request->boolean('maintain_coverage', true),
            'preferences_respected' => $request->boolean('respect_preferences', true)
        ];

        return response()->json([
            'success' => true,
            'message' => $createdCount . ' schedule(s) created successfully.' . 
                        ($rotationGroup ? ' Rotation group "' . $rotationGroup->name . '" created.' : ''),
            'created_count' => $createdCount,
            'failed_count' => count($failedAssignments),
            'failed_assignments' => $failedAssignments,
            'summary' => $summary,
            'rotation_group' => $rotationGroup
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('===== BULK ASSIGN FAILED =====', [
            'error_message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'time_taken' => microtime(true) - $startTime,
            'request' => $request->except(['_token'])
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to process bulk assignment: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get or create rotation group for bulk assignment
     */
    private function getOrCreateRotationGroup($request, $post, $shift)
    {
        if ($request->boolean('create_rotation_group', true) && $request->filled('rotation_group_name')) {
            $rotationGroup = RotationGroup::create([
                'name' => $request->rotation_group_name,
                'code' => $this->generateGroupCode($request->rotation_group_name),
                'description' => 'Auto-created from bulk assignment',
                'security_post_id' => $post->id,
                'security_shift_id' => $shift->id,
                'group_type' => $shift->category === 'night' ? 'night' : 'day',
                'rotation_config' => [
                    'type' => $request->rotation_pattern,
                    'interval_days' => $this->getIntervalForPattern($request->rotation_pattern),
                    'start_date' => $request->start_date,
                    'last_rotation_date' => null,
                    'next_rotation_date' => $request->start_date,
                    'current_sequence_index' => 0,
                    'sequence' => $this->getSequenceForPattern($request->rotation_pattern),
                    'rotation_history' => []
                ],
                'max_members' => $post->max_personnel,
                'min_members' => 2,
                'current_members' => count($request->security_user_ids),
                'preference_weights' => [
                    'seniority' => 25,
                    'performance' => 25,
                    'availability' => 20,
                    'preferred_shift' => 15,
                    'rotation_willingness' => 15
                ],
                'auto_rotate' => true,
                'auto_rotate_schedule' => $this->getScheduleForPattern($request->rotation_pattern),
                'auto_rotate_time' => '00:00',
                'status' => 'active',
                'settings' => [
                    'require_handover' => $request->boolean('include_handover', true),
                    'notify_on_rotate' => true,
                    'maintain_coverage' => $request->boolean('maintain_coverage', true),
                    'allow_swaps' => true
                ],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($request->security_user_ids as $userId) {
                $preferenceScore = $this->calculateMemberPreferenceScore($userId, $rotationGroup);
                
                DB::table('rotation_group_members')->insert([
                    'rotation_group_id' => $rotationGroup->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'joined_at' => now(),
                    'preference_score' => $preferenceScore,
                    'assigned_by' => auth()->id(),
                    'status' => 'active',
                    'rotation_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Created new rotation group from bulk assign', [
                'group_id' => $rotationGroup->id,
                'name' => $rotationGroup->name,
                'members' => count($request->security_user_ids)
            ]);

            return $rotationGroup;
        }

        $existingGroup = RotationGroup::where('security_post_id', $post->id)
            ->where('security_shift_id', $shift->id)
            ->where('status', 'active')
            ->first();

        if ($existingGroup) {
            foreach ($request->security_user_ids as $userId) {
                $exists = DB::table('rotation_group_members')
                    ->where('rotation_group_id', $existingGroup->id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$exists) {
                    $preferenceScore = $this->calculateMemberPreferenceScore($userId, $existingGroup);
                    
                    DB::table('rotation_group_members')->insert([
                        'rotation_group_id' => $existingGroup->id,
                        'user_id' => $userId,
                        'role' => 'member',
                        'joined_at' => now(),
                        'preference_score' => $preferenceScore,
                        'assigned_by' => auth()->id(),
                        'status' => 'active',
                        'rotation_count' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $existingGroup->update([
                'current_members' => DB::table('rotation_group_members')
                    ->where('rotation_group_id', $existingGroup->id)
                    ->where('status', 'active')
                    ->count()
            ]);

            return $existingGroup;
        }

        return null;
    }

    /**
     * Get personnel for rotating date based on pattern
     */
    private function getPersonnelForRotatingDate($userIds, $preferenceScores, $date, $startDate, $pattern, $slotsToFill, $existingAssignments)
    {
        $assigned = [];
        $daysDiff = $startDate->diffInDays($date);
        $userCount = count($userIds);
        
        switch ($pattern) {
            case 'full_swap':
                $half = ceil($userCount / 2);
                $groupA = array_slice($userIds, 0, $half);
                $groupB = array_slice($userIds, $half);
                $weekIndex = floor($daysDiff / 7) % 2;
                $candidates = $weekIndex == 0 ? $groupA : $groupB;
                break;
                
            case 'staggered':
                $rotateCount = ceil($userCount / 2);
                $startIndex = ($daysDiff % $userCount);
                $candidates = [];
                for ($i = 0; $i < $rotateCount; $i++) {
                    $candidates[] = $userIds[($startIndex + $i) % $userCount];
                }
                break;
                
            case 'partial':
                $willingIds = array_filter($userIds, function($id) use ($preferenceScores) {
                    return ($preferenceScores[$id] ?? 5) >= 7;
                });
                $candidates = !empty($willingIds) ? $willingIds : $userIds;
                break;
                
            case 'sequential':
                $startIndex = $daysDiff % $userCount;
                $candidates = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $candidates[] = $userIds[($startIndex + $i) % $userCount];
                }
                break;
                
            case 'alternating':
                $sortedByScore = array_keys($preferenceScores);
                $half = ceil($userCount / 2);
                $topPerformers = array_slice($sortedByScore, 0, $half);
                $bottomPerformers = array_slice($sortedByScore, $half);
                $weekIndex = floor($daysDiff / 7) % 2;
                $candidates = $weekIndex == 0 ? $topPerformers : $bottomPerformers;
                break;
                
            case 'preference_based':
                $candidates = array_keys($preferenceScores);
                break;
                
            default:
                $candidates = $userIds;
        }
        
        foreach ($candidates as $userId) {
            if (count($assigned) >= $slotsToFill) break;
            
            $dateKey = $userId . '_' . $date->format('Y-m-d');
            if (!isset($existingAssignments[$dateKey])) {
                $assigned[] = $userId;
                $existingAssignments[$dateKey] = true;
            }
        }
        
        return $assigned;
    }

    /**
     * Validate personnel constraints (rest period, weekly hours)
     */
    private function validatePersonnelConstraints($userId, $date, $shift)
    {
        try {
            $lastShift = SecuritySchedule::where('security_user_id', $userId)
                ->where('status', 'completed')
                ->whereDate('assignment_date', '<', $date)
                ->orderBy('assignment_date', 'desc')
                ->first(['assignment_date']);

            if ($lastShift) {
                $hoursSinceLastShift = $date->diffInHours($lastShift->assignment_date);
                $minRestHours = 12;
                
                if ($hoursSinceLastShift < $minRestHours) {
                    return false;
                }
            }

            $weekStart = $date->copy()->startOfWeek();
            $weekEnd = $date->copy()->endOfWeek();
            
            $weeklyHours = SecuritySchedule::where('security_user_id', $userId)
                ->whereBetween('assignment_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'cancelled')
                ->with('shift:id,duration_hours')
                ->get()
                ->sum(function($schedule) {
                    return $schedule->shift->duration_hours ?? 0;
                });

            $maxWeeklyHours = 60;
            
            if (($weeklyHours + $shift->duration_hours) > $maxWeeklyHours) {
                return false;
            }

            return true;
            
        } catch (\Exception $e) {
            Log::warning('Failed to validate personnel constraints', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    /**
     * Calculate user preference score for rotation
     */
    private function calculateUserPreferenceScore($userId, $shift, $post, $rotationGroup = null)
    {
        try {
            $user = User::find($userId);
            if (!$user) return 5;

            $score = 5;

            $preferences = $user->preferences ?? [];
            $preferredShifts = $preferences['preferred_shifts'] ?? [];
            if (in_array($shift->category, $preferredShifts)) {
                $score += 2;
            }

            $preferredPosts = $preferences['preferred_posts'] ?? [];
            if (in_array($post->id, $preferredPosts)) {
                $score += 1;
            }

            $willingToRotate = $preferences['willing_to_rotate'] ?? false;
            if ($willingToRotate) {
                $score += 2;
                
                if (is_array($willingToRotate)) {
                    if ($shift->category === 'night' && ($willingToRotate['day_to_night'] ?? false)) {
                        $score += 1;
                    }
                    if ($shift->category === 'day' && ($willingToRotate['night_to_day'] ?? false)) {
                        $score += 1;
                    }
                }
            }

            if ($user->created_at) {
                $yearsEmployed = $user->created_at->diffInYears(now());
                $score += min(2, $yearsEmployed);
            }

            $performanceRating = $preferences['performance_rating'] ?? 7;
            $score += ($performanceRating - 5) / 2;

            return max(1, min(10, round($score, 1)));
            
        } catch (\Exception $e) {
            return 5;
        }
    }

    /**
     * Update rotation group after bulk assign
     */
    private function updateRotationGroupAfterBulkAssign($rotationGroup, $rotationSequence, $userIds, $request)
    {
        try {
            $rotationConfig = $rotationGroup->rotation_config;
            
            $history = $rotationConfig['rotation_history'] ?? [];
            $history[] = [
                'id' => uniqid('rot_'),
                'date' => now()->format('Y-m-d H:i:s'),
                'type' => 'bulk_assign',
                'pattern' => $request->rotation_pattern,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'sequence' => $rotationSequence,
                'user_ids' => $userIds,
                'executed_by' => auth()->id(),
                'executed_by_name' => auth()->user()?->name,
            ];
            
            $rotationConfig['rotation_history'] = $history;
            $rotationConfig['last_rotation_date'] = now()->format('Y-m-d H:i:s');
            
            $stats = $rotationConfig['rotation_stats'] ?? [
                'total_rotations' => 0,
                'successful_rotations' => 0,
                'failed_rotations' => 0
            ];
            $stats['total_rotations']++;
            $stats['successful_rotations']++;
            $rotationConfig['rotation_stats'] = $stats;
            
            $rotationGroup->update([
                'rotation_config' => $rotationConfig,
                'last_rotated_at' => now(),
                'updated_by' => auth()->id()
            ]);

            foreach ($rotationSequence as $day) {
                foreach ($day['assigned_members'] as $userId) {
                    DB::table('rotation_group_members')
                        ->where('rotation_group_id', $rotationGroup->id)
                        ->where('user_id', $userId)
                        ->increment('rotation_count');
                }
            }

            Log::info('Rotation group updated after bulk assign', [
                'group_id' => $rotationGroup->id,
                'sequence_count' => count($rotationSequence)
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to update rotation group after bulk assign', [
                'group_id' => $rotationGroup->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Ensure coverage for bulk assign
     */
    private function ensureCoverageForBulkAssign($post, $shift, $startDate, $endDate, $applicableDates)
    {
        try {
            $currentDate = $startDate->copy();
            $coverageIssues = [];

            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->format('Y-m-d');
                
                if (!in_array($dateStr, array_map(function($d) { return $d->format('Y-m-d'); }, $applicableDates))) {
                    $currentDate->addDay();
                    continue;
                }

                $assignedCount = SecuritySchedule::where('security_post_id', $post->id)
                    ->where('security_shift_id', $shift->id)
                    ->whereDate('assignment_date', $dateStr)
                    ->count();

                $requiredCount = min($shift->required_personnel ?? 1, $post->max_personnel);

                if ($assignedCount < $requiredCount) {
                    $coverageIssues[] = [
                        'date' => $dateStr,
                        'assigned' => $assignedCount,
                        'required' => $requiredCount,
                        'shortfall' => $requiredCount - $assignedCount
                    ];

                    Log::warning('Coverage issue detected after bulk assign', [
                        'post_id' => $post->id,
                        'date' => $dateStr,
                        'assigned' => $assignedCount,
                        'required' => $requiredCount
                    ]);
                }

                $currentDate->addDay();
            }

            if (!empty($coverageIssues)) {
                Log::warning('Bulk assign completed with coverage issues', [
                    'post_id' => $post->id,
                    'issues' => $coverageIssues,
                    'total_days_with_issues' => count($coverageIssues)
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to ensure coverage for bulk assign', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queue bulk assign notifications
     */
    private function queueBulkAssignNotifications($post, $shift, $userIds, $startDate, $endDate, $now)
    {
        try {
            $schedules = SecuritySchedule::with(['shift', 'post', 'securityUser'])
                ->where('security_post_id', $post->id)
                ->where('security_shift_id', $shift->id)
                ->whereIn('security_user_id', $userIds)
                ->whereBetween('assignment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('created_at', '>=', $now->subSeconds(10))
                ->get();

            if ($schedules->isNotEmpty()) {
                dispatch(function() use ($schedules) {
                    foreach ($schedules as $schedule) {
                        $this->queueAssignmentNotification($schedule);
                        if ($schedule->handover_info) {
                            $this->queueHandoverChecklistGeneration($schedule);
                        }
                    }
                })->afterResponse();

                Log::info('Bulk assign notifications queued', [
                    'schedule_count' => $schedules->count()
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to queue bulk assign notifications', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queue rotation execution
     */
    private function queueRotationExecution($rotationGroup, $startDate, $endDate, $request)
    {
        try {
            dispatch(function() use ($rotationGroup, $startDate, $endDate, $request) {
                try {
                    $rotationGroup->executeRotation([
                        'rotation_type' => $request->rotation_pattern,
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'apply_to_schedules' => false,
                        'maintain_coverage' => $request->boolean('maintain_coverage', true),
                        'respect_preferences' => $request->boolean('respect_preferences', true),
                        'notify_members' => $request->boolean('notify_personnel', true),
                    ]);

                    Log::info('Rotation execution queued successfully', [
                        'group_id' => $rotationGroup->id
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to execute queued rotation', [
                        'group_id' => $rotationGroup->id,
                        'error' => $e->getMessage()
                    ]);
                }
            })->afterResponse();

        } catch (\Exception $e) {
            Log::warning('Failed to queue rotation execution', [
                'group_id' => $rotationGroup->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get interval for rotation pattern
     */
    private function getIntervalForPattern($pattern)
    {
        $intervals = [
            'full_swap' => 7,
            'staggered' => 3,
            'partial' => 7,
            'sequential' => 1,
            'alternating' => 7,
            'preference_based' => 7
        ];
        
        return $intervals[$pattern] ?? 7;
    }

    /**
     * Get schedule for rotation pattern
     */
    private function getScheduleForPattern($pattern)
    {
        $schedules = [
            'full_swap' => 'weekly',
            'staggered' => 'daily',
            'partial' => 'weekly',
            'sequential' => 'daily',
            'alternating' => 'weekly',
            'preference_based' => 'weekly'
        ];
        
        return $schedules[$pattern] ?? 'weekly';
    }

    /**
     * Get sequence for rotation pattern
     */
    private function getSequenceForPattern($pattern)
    {
        $sequences = [
            'full_swap' => ['A', 'B'],
            'staggered' => ['A', 'B', 'C', 'D'],
            'partial' => ['A', 'B'],
            'sequential' => ['A', 'B', 'C', 'D'],
            'alternating' => ['A', 'B'],
            'preference_based' => ['A', 'B', 'C', 'D']
        ];
        
        return $sequences[$pattern] ?? ['A', 'B', 'C', 'D'];
    }

    /**
     * Generate group code from name
     */
    private function generateGroupCode($name)
    {
        $words = explode(' ', $name);
        $code = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }
        
        $originalCode = $code;
        $counter = 1;
        while (RotationGroup::where('code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }
        
        return $code;
    }

    /**
     * Calculate member preference score for rotation group
     */
    private function calculateMemberPreferenceScore($userId, $group)
    {
        $user = User::find($userId);
        if (!$user) return 5;

        $weights = $group->preference_weights ?? [
            'seniority' => 25,
            'performance' => 25,
            'availability' => 20,
            'preferred_shift' => 15,
            'rotation_willingness' => 15
        ];

        $score = 0;

        if ($user->created_at) {
            $yearsEmployed = $user->created_at->diffInYears(now());
            $seniorityScore = min(10, $yearsEmployed * 2);
            $score += ($seniorityScore / 10) * ($weights['seniority'] / 100);
        }

        $performanceScore = $user->preferences['performance_rating'] ?? 7;
        $score += ($performanceScore / 10) * ($weights['performance'] / 100);

        $availabilityScore = $this->calculateAvailabilityScore($userId, $group);
        $score += ($availabilityScore / 10) * ($weights['availability'] / 100);

        $preferredShiftScore = $this->calculatePreferredShiftScore($user, $group);
        $score += ($preferredShiftScore / 10) * ($weights['preferred_shift'] / 100);

        $willingnessScore = $user->preferences['rotation_willingness'] ?? 5;
        $score += ($willingnessScore / 10) * ($weights['rotation_willingness'] / 100);

        return round($score * 10, 1);
    }

    /**
     * Calculate availability score for user
     */
    private function calculateAvailabilityScore($userId, $group)
    {
        try {
            $thirtyDaysAgo = now()->subDays(30);
            
            $totalAssigned = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $thirtyDaysAgo)
                ->where('status', '!=', 'cancelled')
                ->count();

            $completed = SecuritySchedule::where('security_user_id', $userId)
                ->where('assignment_date', '>=', $thirtyDaysAgo)
                ->where('status', 'completed')
                ->count();

            if ($totalAssigned == 0) return 5;

            return min(10, round(($completed / $totalAssigned) * 10, 1));
        } catch (\Exception $e) {
            return 5;
        }
    }

    /**
     * Calculate preferred shift score
     */
    private function calculatePreferredShiftScore($user, $group)
    {
        $preferences = $user->preferences ?? [];
        $preferredShifts = $preferences['preferred_shifts'] ?? [];
        
        if (empty($preferredShifts)) return 5;

        $shiftCategory = $group->shift->category ?? null;
        
        if (in_array($shiftCategory, $preferredShifts)) {
            return 10;
        }

        return 3;
    }

    /**
     * Calculate applicable dates based on recurrence type
     */
    private function calculateApplicableDates($startDate, $endDate, $recurrenceType, $recurrenceDays)
    {
        $dates = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            if ($this->isDateApplicable($currentDate, $recurrenceType, $recurrenceDays)) {
                $dates[] = $currentDate->copy();
            }
            $currentDate->addDay();
        }

        return $dates;
    }

    /**
 * Check date applicability for recurrence
 */
private function isDateApplicable(Carbon $date, $recurrenceType, $recurrenceDays)
{
    switch ($recurrenceType) {
        case 'daily':
            return true;
            
        case 'weekly':
            $dayOfWeek = $date->dayOfWeekIso;
            // If no recurrence days specified, return TRUE for ALL days
            if (empty($recurrenceDays)) {
                return true;
            }
            return in_array($dayOfWeek, $recurrenceDays);
            
        case 'monthly':
            $dayOfMonth = $date->day;
            // If no recurrence days specified, return TRUE for ALL days
            if (empty($recurrenceDays)) {
                return true;
            }
            return in_array($dayOfMonth, $recurrenceDays);
            
        case 'rotating':
        case 'custom':
            return true;
            
        default:
            return false;
    }
}

    /**
     * Send assignment notification to security personnel
     */
    private function sendAssignmentNotification(SecuritySchedule $schedule)
    {
        try {
            $user = $schedule->securityUser;
            $post = $schedule->post;
            $shift = $schedule->shift;

            if (!$user || !$post || !$shift) {
                Log::warning('Cannot send notification: missing relations', [
                    'schedule_id' => $schedule->id
                ]);
                return;
            }

            Log::info('Assignment notification prepared', [
                'user_id' => $user->id,
                'schedule_id' => $schedule->id
            ]);

            $this->queueAssignmentNotification($schedule);

        } catch (\Exception $e) {
            Log::error('Failed to prepare assignment notification: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update schedule status with enhanced features
     */
    public function updateStatus(Request $request, $scheduleId)
    {
        $schedule = SecuritySchedule::findOrFail($scheduleId);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:checkin,checkout,mark_absent,mark_complete,start_break,end_break,verify_location',
            'location' => 'nullable|array',
            'location.lat' => 'required_with:location|numeric',
            'location.lng' => 'required_with:location|numeric',
            'accuracy' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
            'break_id' => 'nullable|integer',
            'handover_notes' => 'nullable|string|max:1000',
            'handover_checklist' => 'nullable|array',
            'supervisor_override' => 'nullable|boolean',
            'override_reason' => 'required_if:supervisor_override,true|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            switch ($request->action) {
                case 'checkin':
                    $this->processCheckin($schedule, $request);
                    $message = 'Checked in successfully.';
                    break;
                
                case 'checkout':
                    $this->processCheckout($schedule, $request);
                    $message = 'Checked out successfully.';
                    break;
                
                case 'verify_location':
                    $verification = $this->verifyLocation($schedule, $request);
                    return response()->json([
                        'success' => true,
                        'message' => 'Location verified',
                        'verification' => $verification
                    ]);
                    break;
                
                case 'mark_absent':
                    $schedule->markAsAbsent($request->notes);
                    $message = 'Marked as absent.';
                    break;
                
                case 'mark_complete':
                    $schedule->markAsComplete($request->notes);
                    $message = 'Marked as complete.';
                    break;
                
                case 'start_break':
                    $this->startBreak($schedule, $request);
                    $message = 'Break started.';
                    break;
                
                case 'end_break':
                    $this->endBreak($schedule, $request);
                    $message = 'Break ended.';
                    break;
            }

            if ($request->filled('handover_checklist')) {
                $this->updateHandoverChecklist($schedule, $request->handover_checklist);
            }

            if ($request->filled('handover_notes')) {
                $handoverInfo = $schedule->handover_info ?? [];
                $handoverInfo['notes'] = $request->handover_notes;
                $handoverInfo['notes_submitted_at'] = now();
                $handoverInfo['notes_submitted_by'] = auth()->id();
                
                $schedule->update(['handover_info' => $handoverInfo]);
            }

            DB::commit();

            $this->clearScheduleCaches($schedule);

            return response()->json([
                'success' => true,
                'message' => $message,
                'schedule' => $schedule->fresh()->load(['post', 'shift', 'securityUser']),
                'handover_info' => $schedule->handover_info
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update schedule status: ' . $e->getMessage(), [
                'schedule_id' => $scheduleId,
                'action' => $request->action,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify location for a schedule
     */
    private function verifyLocation($schedule, $request)
    {
        $post = $schedule->post;
        
        if (!$post->latitude || !$post->longitude) {
            return [
                'verified' => true,
                'message' => 'Post has no location configured',
                'distance' => null
            ];
        }

        $distance = $this->calculateDistance(
            $request->location['lat'],
            $request->location['lng'],
            $post->latitude,
            $post->longitude
        );

        $allowedRadius = $post->checkin_radius ?? 100;
        $accuracy = $request->accuracy ?? 10;
        $effectiveRadius = $allowedRadius + ($accuracy * 2);
        
        $verified = $distance <= $effectiveRadius;

        return [
            'verified' => $verified,
            'distance' => round($distance, 1),
            'allowed_radius' => $allowedRadius,
            'accuracy' => $accuracy,
            'effective_radius' => $effectiveRadius,
            'message' => $verified 
                ? "Within allowed radius ({$distance}m)"
                : "Too far from post ({$distance}m)"
        ];
    }

    /**
     * Calculate distance between two coordinates
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        return $angle * $earthRadius;
    }

    /**
     * Process checkin with enhanced features
     */
    private function processCheckin($schedule, $request)
    {
        $checkinData = [
            'checkin_time' => now(),
            'status' => 'active',
            'checkin_notes' => $request->notes
        ];

        if ($request->has('location')) {
            $checkinData['checkin_location'] = $request->location;
            $checkinData['checkin_accuracy'] = $request->accuracy;
        }

        $schedule->update($checkinData);
        
        $shift = $schedule->shift;
        $scheduledStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
        $checkinTime = now();
        
        if ($checkinTime->greaterThan($scheduledStart)) {
            $lateMinutes = $checkinTime->diffInMinutes($scheduledStart);
            $gracePeriod = $schedule->post->checkin_grace_period ?? 5;
            
            $schedule->update([
                'late_minutes' => $lateMinutes,
                'late_flagged' => $lateMinutes > $gracePeriod,
                'late_reason' => $lateMinutes > $gracePeriod ? "Arrived {$lateMinutes} minutes late" : null
            ]);
            
            if ($lateMinutes > $gracePeriod) {
                Log::warning('Late checkin detected', [
                    'schedule_id' => $schedule->id,
                    'user_id' => $schedule->security_user_id,
                    'late_minutes' => $lateMinutes,
                    'grace_period' => $gracePeriod
                ]);
            }
        }
    }

    /**
     * Process checkout with enhanced features
     */
    private function processCheckout($schedule, $request)
    {
        $checkoutData = [
            'checkout_time' => now(),
            'status' => 'completed',
            'checkout_notes' => $request->notes
        ];

        if ($request->has('location')) {
            $checkoutData['checkout_location'] = $request->location;
            $checkoutData['checkout_accuracy'] = $request->accuracy;
        }

        $schedule->update($checkoutData);
        
        $shift = $schedule->shift;
        $scheduledEnd = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->end_time);
        $checkoutTime = now();
        
        if ($scheduledEnd <= Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time)) {
            $scheduledEnd->addDay();
        }
        
        if ($checkoutTime->greaterThan($scheduledEnd)) {
            $overtimeMinutes = $checkoutTime->diffInMinutes($scheduledEnd);
            $schedule->update(['overtime_minutes' => $overtimeMinutes]);
            
            Log::info('Overtime recorded', [
                'schedule_id' => $schedule->id,
                'user_id' => $schedule->security_user_id,
                'overtimeMinutes' => $overtimeMinutes
            ]);
        }
        
        if ($schedule->checkin_time) {
            $totalMinutes = $schedule->checkin_time->diffInMinutes($checkoutTime);
            if ($schedule->break_duration > 0) {
                $totalMinutes -= $schedule->break_duration;
            }
            $schedule->update(['total_minutes' => $totalMinutes]);
        }
    }

    /**
     * Start break
     */
    private function startBreak($schedule, $request)
    {
        if (!$schedule->include_breaks) {
            throw new \Exception('This schedule does not include breaks.');
        }

        $breakId = $request->break_id;
        $shift = $schedule->shift;
        
        if (!$shift->break_schedule || empty($shift->break_schedule['has_break'])) {
            throw new \Exception('No breaks configured for this shift.');
        }

        $breaks = $shift->break_schedule['breaks'] ?? [];
        $break = $breaks[$breakId] ?? null;
        
        if (!$break) {
            throw new \Exception('Invalid break ID.');
        }

        $breakData = [
            'current_break_id' => $breakId,
            'break_start_time' => now(),
            'break_status' => 'active'
        ];

        if ($request->has('location')) {
            $breakData['break_start_location'] = $request->location;
        }

        $schedule->update($breakData);
    }

    /**
     * End break
     */
    private function endBreak($schedule, $request)
    {
        if (!$schedule->current_break_id) {
            throw new \Exception('No active break to end.');
        }

        $breakDuration = now()->diffInMinutes($schedule->break_start_time);
        
        $shift = $schedule->shift;
        $breaks = $shift->break_schedule['breaks'] ?? [];
        $break = $breaks[$schedule->current_break_id] ?? null;
        
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
    }

    /**
     * Update handover checklist
     */
    private function updateHandoverChecklist($schedule, $checklistUpdates)
    {
        if (!$schedule->handover_info || !isset($schedule->handover_info['checklist_items'])) {
            return;
        }

        $handoverInfo = $schedule->handover_info;
        $checklistItems = $handoverInfo['checklist_items'];
        
        foreach ($checklistUpdates as $index => $update) {
            if (isset($checklistItems[$index])) {
                $checklistItems[$index]['completed'] = $update['completed'] ?? false;
                $checklistItems[$index]['completed_by'] = auth()->id();
                $checklistItems[$index]['completed_at'] = now();
                $checklistItems[$index]['notes'] = $update['notes'] ?? null;
            }
        }
        
        $handoverInfo['checklist_items'] = $checklistItems;
        $handoverInfo['checklist_completed_at'] = now();
        $handoverInfo['checklist_completed_by'] = auth()->id();
        
        $schedule->update(['handover_info' => $handoverInfo]);
    }

    /**
     * Get today's schedule summary
     */
    private function getTodayScheduleSummary()
    {
        $today = today()->toDateString();

        $schedules = SecuritySchedule::whereDate('assignment_date', $today)
            ->with(['post:id,name,code,max_personnel', 'shift:id,name,category', 'securityUser:id,name'])
            ->get();

        $totalAssigned = $schedules->count();
        $checkedIn = $schedules->where('status', 'active')->count();
        $absent = $schedules->where('status', 'absent')->count();
        $completed = $schedules->where('status', 'completed')->count();
        $lateCheckins = $schedules->where('late_minutes', '>', 0)->count();
        $onBreak = $schedules->where('break_status', 'active')->count();
        $verifiedCheckins = $schedules->whereNotNull('checkin_verification')->count();
        $offlineCheckins = $schedules->where('offline_mode', true)->count();

        $byCategory = $schedules->groupBy(function($schedule) {
            return $schedule->shift->category ?? 'unknown';
        })->map(function($group) {
            return [
                'count' => $group->count(),
                'name' => $group->first()->shift->category ?? 'unknown'
            ];
        });

        $byPost = $schedules->groupBy('post.name')->map(function($group) {
            $firstSchedule = $group->first();
            $post = $firstSchedule->post;
            $assignedCount = $group->count();
            $maxPersonnel = $post->max_personnel ?? 0;
            
            return [
                'count' => $assignedCount,
                'post' => $post,
                'post_name' => $post->name,
                'post_id' => $post->id,
                'post_code' => $post->code ?? '',
                'max_personnel' => $maxPersonnel,
                'fully_staffed' => $assignedCount >= $maxPersonnel,
                'coverage_percentage' => $maxPersonnel > 0 
                    ? round(($assignedCount / $maxPersonnel) * 100, 2) 
                    : 0,
                'remaining_slots' => max(0, $maxPersonnel - $assignedCount),
                'is_overstaffed' => $assignedCount > $maxPersonnel,
                'is_understaffed' => $assignedCount < $maxPersonnel,
                'status_color' => $this->getCoverageStatusColor($assignedCount, $maxPersonnel),
                'assigned_personnel' => $group->map(function($schedule) {
                    return [
                        'id' => $schedule->security_user_id,
                        'name' => $schedule->securityUser->name ?? 'Unknown',
                        'shift' => $schedule->shift->name ?? 'Unknown',
                        'status' => $schedule->status,
                        'checkin_time' => $schedule->checkin_time,
                        'verified' => !is_null($schedule->checkin_verification)
                    ];
                })->toArray()
            ];
        })->sortByDesc('fully_staffed')->sortByDesc('coverage_percentage');

        $allPosts = SecurityPost::active()->get(['id', 'name', 'max_personnel']);
        $postCoverage = [];
        
        foreach ($allPosts as $post) {
            $postSchedules = $schedules->where('security_post_id', $post->id);
            $assignedCount = $postSchedules->count();
            $maxPersonnel = $post->max_personnel;
            
            $postCoverage[] = [
                'post' => $post,
                'post_name' => $post->name,
                'assigned' => $assignedCount,
                'required' => $maxPersonnel,
                'coverage_rate' => $maxPersonnel > 0 
                    ? round(($assignedCount / $maxPersonnel) * 100, 2)
                    : 0,
                'fully_staffed' => $assignedCount >= $maxPersonnel,
                'remaining_slots' => max(0, $maxPersonnel - $assignedCount),
                'verified_count' => $postSchedules->whereNotNull('checkin_verification')->count()
            ];
        }

        return [
            'total_assigned' => $totalAssigned,
            'checked_in' => $checkedIn,
            'absent' => $absent,
            'completed' => $completed,
            'late_checkins' => $lateCheckins,
            'on_break' => $onBreak,
            'verified_checkins' => $verifiedCheckins,
            'offline_checkins' => $offlineCheckins,
            'by_category' => $byCategory,
            'by_post' => $byPost,
            'post_coverage' => $postCoverage,
            'schedules' => $schedules,
            'summary_date' => $today,
            'total_posts' => $allPosts->count(),
            'fully_staffed_posts' => collect($postCoverage)->where('fully_staffed', true)->count(),
            'understaffed_posts' => collect($postCoverage)->where('fully_staffed', false)->where('assigned', '>', 0)->count(),
            'unstaffed_posts' => collect($postCoverage)->where('assigned', 0)->count(),
        ];
    }

    /**
     * Get verification statistics
     */
    private function getVerificationStatistics()
    {
        $today = today();
        $startOfWeek = $today->copy()->startOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();

        return [
            'today' => [
                'total' => VerificationLog::whereDate('created_at', $today)->count(),
                'success' => VerificationLog::whereDate('created_at', $today)->where('status', 'success')->count(),
                'failed' => VerificationLog::whereDate('created_at', $today)->where('status', 'failed')->count(),
                'by_method' => VerificationLog::whereDate('created_at', $today)
                    ->selectRaw('method, count(*) as count')
                    ->groupBy('method')
                    ->pluck('count', 'method')
                    ->toArray()
            ],
            'this_week' => [
                'total' => VerificationLog::whereBetween('created_at', [$startOfWeek, $today])->count(),
                'unique_users' => VerificationLog::whereBetween('created_at', [$startOfWeek, $today])
                    ->distinct('user_id')->count('user_id'),
                'success_rate' => $this->calculateSuccessRate($startOfWeek, $today)
            ],
            'this_month' => [
                'total' => VerificationLog::whereBetween('created_at', [$startOfMonth, $today])->count(),
                'by_device' => VerificationLog::whereBetween('created_at', [$startOfMonth, $today])
                    ->whereNotNull('device_id')
                    ->selectRaw('device_id, count(*) as count')
                    ->groupBy('device_id')
                    ->orderBy('count', 'desc')
                    ->limit(5)
                    ->get()
            ]
        ];
    }

    /**
     * Calculate verification success rate
     */
    private function calculateSuccessRate($startDate, $endDate)
    {
        $total = VerificationLog::whereBetween('created_at', [$startDate, $endDate])->count();
        if ($total == 0) return 100;
        
        $success = VerificationLog::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'success')
            ->count();
        
        return round(($success / $total) * 100, 1);
    }

    /**
     * Get coverage status color
     */
    private function getCoverageStatusColor($assigned, $max)
    {
        if ($max == 0) return 'secondary';
        $percentage = ($assigned / $max) * 100;
        
        if ($percentage >= 100) return 'success';
        if ($percentage >= 75) return 'info';
        if ($percentage >= 50) return 'warning';
        return 'danger';
    }

    /**
     * Get upcoming handovers
     */
    private function getUpcomingHandovers()
    {
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();

        return SecuritySchedule::whereDate('assignment_date', $today)
            ->orWhereDate('assignment_date', $tomorrow)
            ->whereNotNull('handover_info')
            ->where('handover_completed', false)
            ->with(['post:id,name', 'shift:id,name,start_time,end_time', 'securityUser:id,name'])
            ->get(['id', 'security_post_id', 'security_shift_id', 'security_user_id', 'assignment_date', 'handover_info'])
            ->filter(function($schedule) {
                $handoverInfo = $schedule->handover_info;
                if (!$handoverInfo || empty($handoverInfo['handover_start'])) {
                    return false;
                }
                
                try {
                    $handoverTime = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $handoverInfo['handover_start']);
                    return $handoverTime->isFuture() && $handoverTime->diffInHours(now()) <= 2;
                } catch (\Exception $e) {
                    return false;
                }
            })
            ->sortBy(function($schedule) {
                return $schedule->handover_info['handover_start'] ?? '00:00';
            })
            ->values();
    }

    /**
     * Get schedule statistics
     */
    private function getScheduleStatistics()
    {
        $today = today()->toDateString();
        $weekStart = today()->startOfWeek();
        $weekEnd = today()->endOfWeek();
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        $cacheKey = "schedule_stats_" . today()->format('Y-m-d');
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function() use ($today, $weekStart, $weekEnd, $monthStart, $monthEnd) {
            return [
                'today' => [
                    'total' => SecuritySchedule::whereDate('assignment_date', $today)->count(),
                    'completed' => SecuritySchedule::whereDate('assignment_date', $today)
                        ->where('status', 'completed')
                        ->count(),
                    'absent' => SecuritySchedule::whereDate('assignment_date', $today)
                        ->where('status', 'absent')
                        ->count(),
                    'late' => SecuritySchedule::whereDate('assignment_date', $today)
                        ->where('late_minutes', '>', 0)
                        ->count(),
                ],
                'this_week' => [
                    'total' => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])->count(),
                    'completed' => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])
                        ->where('status', 'completed')
                        ->count(),
                    'overtime_hours' => SecuritySchedule::whereBetween('assignment_date', [$weekStart, $weekEnd])
                        ->sum('overtime_minutes') / 60,
                ],
                'this_month' => [
                    'total' => SecuritySchedule::whereBetween('assignment_date', [$monthStart, $monthEnd])->count(),
                    'by_status' => SecuritySchedule::whereBetween('assignment_date', [$monthStart, $monthEnd])
                        ->selectRaw('status, count(*) as count')
                        ->groupBy('status')
                        ->pluck('count', 'status')
                        ->toArray(),
                    'by_shift_category' => SecuritySchedule::whereBetween('assignment_date', [$monthStart, $monthEnd])
                        ->join('security_shifts', 'security_schedules.security_shift_id', '=', 'security_shifts.id')
                        ->selectRaw('security_shifts.category, count(*) as count')
                        ->groupBy('security_shifts.category')
                        ->pluck('count', 'security_shifts.category')
                        ->toArray(),
                    'verification_rate' => $this->calculateVerificationRate($monthStart, $monthEnd)
                ],
                'personnel_stats' => $this->getPersonnelStatistics(),
            ];
        });
    }

    /**
     * Calculate verification rate for period
     */
    private function calculateVerificationRate($startDate, $endDate)
    {
        $total = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->whereNotNull('checkin_time')
            ->count();
        
        if ($total == 0) return 100;
        
        $verified = SecuritySchedule::whereBetween('assignment_date', [$startDate, $endDate])
            ->whereNotNull('checkin_verification')
            ->count();
        
        return round(($verified / $total) * 100, 1);
    }

    /**
     * Get personnel statistics
     */
    private function getPersonnelStatistics()
    {
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();

        try {
            return User::where('type', User::TYPE_SECURITY_PERSONNEL)
                ->where('status', User::STATUS_ACTIVE)
                ->withCount(['schedules' => function($query) use ($monthStart, $monthEnd) {
                    $query->whereBetween('assignment_date', [$monthStart, $monthEnd]);
                }])
                ->withCount(['schedules as completed_schedules' => function($query) use ($monthStart, $monthEnd) {
                    $query->whereBetween('assignment_date', [$monthStart, $monthEnd])
                          ->where('status', 'completed');
                }])
                ->withCount(['schedules as absent_schedules' => function($query) use ($monthStart, $monthEnd) {
                    $query->whereBetween('assignment_date', [$monthStart, $monthEnd])
                          ->where('status', 'absent');
                }])
                ->having('schedules_count', '>', 0)
                ->orderBy('schedules_count', 'desc')
                ->limit(10)
                ->get(['id', 'name'])
                ->map(function($user) {
                    $attendanceRate = $user->schedules_count > 0 
                        ? round(($user->completed_schedules / $user->schedules_count) * 100, 2)
                        : 0;
                    
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'total_shifts' => $user->schedules_count,
                        'completed_shifts' => $user->completed_schedules,
                        'absent_shifts' => $user->absent_schedules,
                        'attendance_rate' => $attendanceRate,
                    ];
                });
        } catch (\Exception $e) {
            Log::warning('Failed to get personnel statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Group failed assignments by reason
     */
    private function groupFailedAssignments($failedAssignments)
    {
        $grouped = [];
        
        foreach ($failedAssignments as $failed) {
            $reason = $failed['reason'] ?? 'Unknown';
            $grouped[$reason] = ($grouped[$reason] ?? 0) + 1;
        }
        
        return $grouped;
    }

    /**
     * Perform bulk actions on security schedules
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'exists:security_schedules,id',
            'action' => 'required|in:trash,force_delete,restore,apply_rotation,swap_groups,mark_verified,clear_verification',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $scheduleIds = $request->schedule_ids;
            $action = $request->action;
            $successCount = 0;
            $failedItems = [];

            switch ($action) {
                case 'trash':
                    foreach ($scheduleIds as $id) {
                        try {
                            $schedule = SecuritySchedule::find($id);
                            if ($schedule && !in_array($schedule->status, ['active', 'completed'])) {
                                $schedule->delete();
                                $successCount++;
                            } else {
                                $failedItems[] = [
                                    'id' => $id,
                                    'reason' => 'Cannot delete active or completed schedule'
                                ];
                            }
                        } catch (\Exception $e) {
                            $failedItems[] = [
                                'id' => $id,
                                'reason' => $e->getMessage()
                            ];
                        }
                    }
                    $message = "{$successCount} schedule(s) moved to trash successfully.";
                    break;

                case 'restore':
                    foreach ($scheduleIds as $id) {
                        try {
                            $schedule = SecuritySchedule::onlyTrashed()->find($id);
                            if ($schedule) {
                                $conflicts = $this->checkRestorationConflicts($schedule);
                                if (empty($conflicts)) {
                                    $schedule->restore();
                                    $successCount++;
                                } else {
                                    $failedItems[] = [
                                        'id' => $id,
                                        'reason' => 'Restoration conflicts: ' . implode(', ', $conflicts)
                                    ];
                                }
                            } else {
                                $failedItems[] = [
                                    'id' => $id,
                                    'reason' => 'Schedule not found in trash'
                                ];
                            }
                        } catch (\Exception $e) {
                            $failedItems[] = [
                                'id' => $id,
                                'reason' => $e->getMessage()
                            ];
                        }
                    }
                    $message = "{$successCount} schedule(s) restored successfully.";
                    break;

                case 'force_delete':
                    foreach ($scheduleIds as $id) {
                        try {
                            $schedule = SecuritySchedule::onlyTrashed()->find($id);
                            if ($schedule) {
                                $schedule->forceDelete();
                                $successCount++;
                            } else {
                                $failedItems[] = [
                                    'id' => $id,
                                    'reason' => 'Schedule not found in trash'
                                ];
                            }
                        } catch (\Exception $e) {
                            $failedItems[] = [
                                'id' => $id,
                                'reason' => $e->getMessage()
                            ];
                        }
                    }
                    $message = "{$successCount} schedule(s) permanently deleted.";
                    break;

                case 'mark_verified':
                    foreach ($scheduleIds as $id) {
                        try {
                            $schedule = SecuritySchedule::find($id);
                            if ($schedule) {
                                $schedule->update([
                                    'checkin_verification' => json_encode([
                                        'method' => 'bulk_admin_override',
                                        'verified_at' => now()->toDateTimeString(),
                                        'verified_by' => auth()->id(),
                                        'verified_by_name' => auth()->user()->name
                                    ])
                                ]);
                                $successCount++;
                            }
                        } catch (\Exception $e) {
                            $failedItems[] = [
                                'id' => $id,
                                'reason' => $e->getMessage()
                            ];
                        }
                    }
                    $message = "{$successCount} schedule(s) marked as verified.";
                    break;

                case 'clear_verification':
                    foreach ($scheduleIds as $id) {
                        try {
                            $schedule = SecuritySchedule::find($id);
                            if ($schedule) {
                                $schedule->update([
                                    'checkin_verification' => null
                                ]);
                                $successCount++;
                            }
                        } catch (\Exception $e) {
                            $failedItems[] = [
                                'id' => $id,
                                'reason' => $e->getMessage()
                            ];
                        }
                    }
                    $message = "{$successCount} schedule(s) verification cleared.";
                    break;

                default:
                    throw new \Exception('Invalid action');
            }

            DB::commit();

            foreach ($scheduleIds as $id) {
                $this->clearScheduleCaches(SecuritySchedule::withTrashed()->find($id));
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'success_count' => $successCount,
                'failed_count' => count($failedItems),
                'failed_items' => $failedItems
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to perform bulk update: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk action: ' . $e->getMessage()
            ], 500);
        }
    }

   /**
 * Preview bulk assignment before executing
 */
public function previewBulkAssign(Request $request)
{
    $validator = Validator::make($request->all(), [
        'security_post_id' => 'required|exists:security_posts,id',
        'security_shift_id' => 'required|exists:security_shifts,id',
        'security_user_ids' => 'required|array|min:1',
        'security_user_ids.*' => 'exists:users,id',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'recurrence_type' => 'required|in:daily,weekly,monthly,custom,rotating',
        'recurrence_days' => 'nullable|array',
        'recurrence_days.*' => 'integer|min:1|max:7',
        'rotation_pattern' => 'nullable|in:full_swap,staggered,partial,sequential,alternating,preference_based',
        'maintain_coverage' => 'nullable|boolean',
        'respect_preferences' => 'nullable|boolean',
        'include_breaks' => 'nullable|boolean',
        'include_handover' => 'nullable|boolean',
        'create_rotation_group' => 'nullable|boolean',
        'rotation_group_name' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $startTime = microtime(true);
        Log::info('===== BULK ASSIGN PREVIEW STARTED =====', [
            'request_data' => $request->except(['_token']),
            'user_id' => auth()->id()
        ]);

        $post = Cache::remember("post_{$request->security_post_id}", now()->addMinutes(10), function() use ($request) {
            return SecurityPost::withTrashed()->find($request->security_post_id);
        });
        
        $shift = Cache::remember("shift_{$request->security_shift_id}", now()->addMinutes(10), function() use ($request) {
            return SecurityShift::withTrashed()->find($request->security_shift_id);
        });
        
        if (!$post || !$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Post or Shift not found'
            ], 404);
        }

        if ($post->trashed() || !$post->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Security post is inactive or deleted.'
            ], 422);
        }

        if ($shift->trashed() || !$shift->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Security shift is inactive or deleted.'
            ], 422);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $preview = [];
        $warnings = [];
        $statistics = [
            'total_days' => 0,
            'applicable_days' => 0,
            'full_capacity_days' => 0,
            'skipped_days' => 0,
            'personnel_assignments' => [],
        ];

        $existingAssignments = DB::table('security_schedules')
            ->whereIn('security_user_id', $request->security_user_ids)
            ->whereBetween('assignment_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get(['security_user_id', 'assignment_date'])
            ->mapWithKeys(function($item) {
                return [$item->security_user_id . '_' . $item->assignment_date => true];
            })
            ->toArray();

        $userPreferenceScores = [];
        $sortedUserIds = $request->security_user_ids;
        
        if ($request->boolean('respect_preferences', true) && $request->recurrence_type === 'rotating') {
            foreach ($request->security_user_ids as $userId) {
                $userPreferenceScores[$userId] = $this->calculatePreviewPreferenceScore($userId, $shift, $post);
            }
            arsort($userPreferenceScores);
            $sortedUserIds = array_keys($userPreferenceScores);
        }

        $applicableDates = $this->calculateApplicableDates(
            $startDate, 
            $endDate, 
            $request->recurrence_type, 
            $request->recurrence_days ?? []
        );

        $statistics['total_days'] = $startDate->diffInDays($endDate) + 1;
        $statistics['applicable_days'] = count($applicableDates);

        $userCount = count($sortedUserIds);
        $userIndex = 0;

        foreach ($applicableDates as $date) {
            $dateStr = $date->format('Y-m-d');
            
            $dayOfWeek = $date->dayOfWeekIso;
            
            // FIXED: Only check applicability if the shift has specific applicable days
            if ($shift->applicable_days !== null && !empty($shift->applicable_days)) {
                if (!in_array($dayOfWeek, $shift->applicable_days)) {
                    $warnings[] = [
                        'date' => $dateStr,
                        'type' => 'shift_not_applicable',
                        'message' => "Shift not applicable on {$date->format('l')}"
                    ];
                    continue;
                }
            }

            $assignedCount = SecuritySchedule::where('security_post_id', $request->security_post_id)
                ->whereDate('assignment_date', $date)
                ->count();
            
            $availableSlots = $post->max_personnel - $assignedCount;
            
            if ($availableSlots <= 0) {
                $statistics['full_capacity_days']++;
                $preview[] = [
                    'date' => $dateStr,
                    'status' => 'skipped',
                    'reason' => 'Post at full capacity',
                    'post_name' => $post->name,
                    'shift_name' => $shift->name,
                    'available_slots' => 0,
                ];
                continue;
            }

            $personnelToAssign = [];
            $slotsToFill = min($availableSlots, $userCount);
            
            if ($request->recurrence_type === 'rotating' && $request->filled('rotation_pattern')) {
                $personnelToAssign = $this->getPreviewPersonnelForRotatingDate(
                    $sortedUserIds,
                    $userPreferenceScores,
                    $date,
                    $startDate,
                    $request->rotation_pattern,
                    $slotsToFill,
                    $existingAssignments
                );
            } else {
                for ($i = 0; $i < $slotsToFill; $i++) {
                    $userId = $sortedUserIds[($userIndex + $i) % $userCount];
                    $dateKey = $userId . '_' . $dateStr;
                    
                    if (!isset($existingAssignments[$dateKey])) {
                        if ($this->validatePreviewConstraints($userId, $date, $shift)) {
                            $personnelToAssign[] = $userId;
                            $existingAssignments[$dateKey] = true;
                        }
                    }
                }
                $userIndex = ($userIndex + $slotsToFill) % $userCount;
            }

            $previewDate = [
                'date' => $dateStr,
                'day_of_week' => $date->format('l'),
                'post_id' => $post->id,
                'post_name' => $post->name,
                'post_code' => $post->code,
                'post_location' => $post->location,
                'shift_id' => $shift->id,
                'shift_name' => $shift->name,
                'shift_time' => substr($shift->start_time, 0, 5) . ' - ' . substr($shift->end_time, 0, 5),
                'shift_category' => $shift->category,
                'available_slots' => $availableSlots,
                'slots_filled' => count($personnelToAssign),
                'assignments' => [],
                'rotation_pattern' => $request->rotation_pattern,
                'has_handover' => $request->boolean('include_handover', false),
                'has_breaks' => $request->boolean('include_breaks', false) && $shift->break_schedule['has_break'] ?? false,
            ];

            foreach ($personnelToAssign as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $preferenceScore = $userPreferenceScores[$userId] ?? 5;
                    
                    $assignment = [
                        'user_id' => $user->id,
                        'personnel_name' => $user->name,
                        'personnel_phone' => $user->phone,
                        'badge_number' => $user->badge_number,
                        'preference_score' => round($preferenceScore, 1),
                        'seniority_years' => $user->created_at ? round($user->created_at->diffInYears(now()), 1) : 0,
                    ];

                    if ($user->preferences) {
                        $assignment['preferences'] = [
                            'preferred_shifts' => $user->preferences['preferred_shifts'] ?? [],
                            'willing_to_rotate' => $user->preferences['willing_to_rotate'] ?? false,
                        ];
                    }

                    $previewDate['assignments'][] = $assignment;

                    if (!isset($statistics['personnel_assignments'][$userId])) {
                        $statistics['personnel_assignments'][$userId] = [
                            'name' => $user->name,
                            'count' => 0
                        ];
                    }
                    $statistics['personnel_assignments'][$userId]['count']++;
                }
            }

            $preview[] = $previewDate;
        }

        $totalAssignments = array_sum(array_column($preview, 'slots_filled'));
        $uniquePersonnel = count(array_filter($statistics['personnel_assignments']));
        
        $avgPerPersonnel = $uniquePersonnel > 0 ? round($totalAssignments / $uniquePersonnel, 1) : 0;

        $rotationGroupInfo = null;
        if ($request->boolean('create_rotation_group', false) && $request->recurrence_type === 'rotating') {
            $rotationGroupInfo = [
                'name' => $request->rotation_group_name ?: 'Auto-generated group',
                'members' => $uniquePersonnel,
                'pattern' => $request->rotation_pattern,
            ];
        }

        $response = [
            'success' => true,
            'preview' => $preview,
            'count' => $totalAssignments,
            'statistics' => [
                'total_assignments' => $totalAssignments,
                'unique_personnel' => $uniquePersonnel,
                'avg_per_personnel' => $avgPerPersonnel,
                'total_days' => $statistics['total_days'],
                'applicable_days' => $statistics['applicable_days'],
                'full_capacity_days' => $statistics['full_capacity_days'],
                'coverage_rate' => $statistics['applicable_days'] > 0 
                    ? round((($statistics['applicable_days'] - $statistics['full_capacity_days']) / $statistics['applicable_days']) * 100, 1)
                    : 0,
            ],
            'summary' => [
                'post' => [
                    'id' => $post->id,
                    'name' => $post->name,
                    'code' => $post->code,
                    'max_personnel' => $post->max_personnel,
                    'location' => $post->location,
                    'coordinates' => $post->latitude && $post->longitude ? [
                        'lat' => $post->latitude,
                        'lng' => $post->longitude
                    ] : null
                ],
                'shift' => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'time' => substr($shift->start_time, 0, 5) . ' - ' . substr($shift->end_time, 0, 5),
                    'category' => $shift->category,
                    'duration' => $shift->duration_hours,
                ],
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'recurrence' => [
                    'type' => $request->recurrence_type,
                    'days' => $request->recurrence_days ?? [],
                ],
                'rotation' => $request->recurrence_type === 'rotating' ? [
                    'pattern' => $request->rotation_pattern,
                    'group' => $rotationGroupInfo,
                ] : null,
                'options' => [
                    'include_breaks' => $request->boolean('include_breaks', false),
                    'include_handover' => $request->boolean('include_handover', false),
                    'maintain_coverage' => $request->boolean('maintain_coverage', true),
                    'respect_preferences' => $request->boolean('respect_preferences', true),
                ],
            ],
            'warnings' => $warnings,
        ];

        $totalTime = microtime(true) - $startTime;
        Log::info('===== BULK ASSIGN PREVIEW COMPLETED =====', [
            'preview_count' => $totalAssignments,
            'total_time' => $totalTime
        ]);

        return response()->json($response);

    } catch (\Exception $e) {
        Log::error('Failed to preview bulk assignment: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to generate preview: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Calculate preference score for preview
     */
    private function calculatePreviewPreferenceScore($userId, $shift, $post)
    {
        try {
            $user = User::find($userId);
            if (!$user) return 5;

            $preferences = $user->preferences ?? [];
            $score = 5;

            if ($user->created_at) {
                $yearsEmployed = $user->created_at->diffInYears(now());
                $score += min(2, $yearsEmployed);
            }

            $performanceRating = $preferences['performance_rating'] ?? 7;
            $score += ($performanceRating - 5) / 2.5;

            $preferredShifts = $preferences['preferred_shifts'] ?? [];
            if (in_array($shift->category, $preferredShifts)) {
                $score += 2;
            }

            $preferredPosts = $preferences['preferred_posts'] ?? [];
            if (in_array($post->id, $preferredPosts)) {
                $score += 1;
            }

            $willingToRotate = $preferences['willing_to_rotate'] ?? false;
            if ($willingToRotate) {
                $score += 2;
            }

            return max(1, min(10, $score));
        } catch (\Exception $e) {
            return 5;
        }
    }

    /**
     * Get personnel for rotating date in preview
     */
    private function getPreviewPersonnelForRotatingDate($userIds, $preferenceScores, $date, $startDate, $pattern, $slotsToFill, &$existingAssignments)
    {
        $assigned = [];
        $daysDiff = $startDate->diffInDays($date);
        $userCount = count($userIds);
        
        switch ($pattern) {
            case 'full_swap':
                $half = ceil($userCount / 2);
                $groupA = array_slice($userIds, 0, $half);
                $groupB = array_slice($userIds, $half);
                $weekIndex = floor($daysDiff / 7) % 2;
                $candidates = $weekIndex == 0 ? $groupA : $groupB;
                break;
                
            case 'staggered':
                $rotateCount = ceil($userCount / 2);
                $startIndex = ($daysDiff % $userCount);
                $candidates = [];
                for ($i = 0; $i < $rotateCount; $i++) {
                    $candidates[] = $userIds[($startIndex + $i) % $userCount];
                }
                break;
                
            case 'partial':
                $willingIds = array_filter($userIds, function($id) use ($preferenceScores) {
                    return ($preferenceScores[$id] ?? 5) >= 7;
                });
                $candidates = !empty($willingIds) ? $willingIds : $userIds;
                break;
                
            case 'sequential':
                $startIndex = $daysDiff % $userCount;
                $candidates = [];
                for ($i = 0; $i < $userCount; $i++) {
                    $candidates[] = $userIds[($startIndex + $i) % $userCount];
                }
                break;
                
            case 'alternating':
                $sortedByScore = array_keys($preferenceScores);
                $half = ceil($userCount / 2);
                $topPerformers = array_slice($sortedByScore, 0, $half);
                $bottomPerformers = array_slice($sortedByScore, $half);
                $weekIndex = floor($daysDiff / 7) % 2;
                $candidates = $weekIndex == 0 ? $topPerformers : $bottomPerformers;
                break;
                
            case 'preference_based':
                $candidates = array_keys($preferenceScores);
                break;
                
            default:
                $candidates = $userIds;
        }
        
        foreach ($candidates as $userId) {
            if (count($assigned) >= $slotsToFill) break;
            
            $dateKey = $userId . '_' . $date->format('Y-m-d');
            if (!isset($existingAssignments[$dateKey])) {
                $assigned[] = $userId;
                $existingAssignments[$dateKey] = true;
            }
        }
        
        return $assigned;
    }

    /**
     * Validate personnel constraints for preview
     */
    private function validatePreviewConstraints($userId, $date, $shift)
    {
        try {
            $lastShift = SecuritySchedule::where('security_user_id', $userId)
                ->where('status', 'completed')
                ->whereDate('assignment_date', '<', $date)
                ->orderBy('assignment_date', 'desc')
                ->first(['assignment_date']);

            if ($lastShift) {
                $hoursSinceLastShift = $date->diffInHours($lastShift->assignment_date);
                $minRestHours = 12;
                
                if ($hoursSinceLastShift < $minRestHours) {
                    return false;
                }
            }

            $weekStart = $date->copy()->startOfWeek();
            $weekEnd = $date->copy()->endOfWeek();
            
            $weeklyHours = SecuritySchedule::where('security_user_id', $userId)
                ->whereBetween('assignment_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'cancelled')
                ->with('shift:id,duration_hours')
                ->get()
                ->sum(function($schedule) {
                    return $schedule->shift->duration_hours ?? 0;
                });

            $maxWeeklyHours = 60;
            
            if (($weeklyHours + $shift->duration_hours) > $maxWeeklyHours) {
                return false;
            }

            return true;
            
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Display security schedules in calendar view
     */
    public function calendar(Request $request)
    {
        $startDate = $request->filled('start_date') 
            ? Carbon::parse($request->start_date) 
            : now()->startOfMonth();
        
        $endDate = $request->filled('end_date') 
            ? Carbon::parse($request->end_date) 
            : now()->endOfMonth();

        $calendarStart = $startDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $endDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $schedules = SecuritySchedule::with([
            'post:id,name,color',
            'shift:id,name,start_time,end_time',
            'securityUser:id,name,phone',
            'assignedBy:id,name'
        ])
        ->whereBetween('assignment_date', [$calendarStart, $calendarEnd])
        ->orderBy('assignment_date')
        ->orderBy('security_post_id')
        ->get()
        ->groupBy(function($schedule) {
            return $schedule->assignment_date->format('Y-m-d');
        });

        $today = now()->format('Y-m-d');
        
        $totalSchedules = SecuritySchedule::whereBetween('assignment_date', [$calendarStart, $calendarEnd])->count();
        
        $activeToday = SecuritySchedule::whereDate('assignment_date', $today)
            ->where('status', 'active')
            ->count();
        
        $absentToday = SecuritySchedule::whereDate('assignment_date', $today)
            ->where('status', 'absent')
            ->count();
        
        $totalPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->count();

        $securityPosts = SecurityPost::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('admin.security-schedules.calendar', compact(
            'schedules',
            'startDate',
            'endDate',
            'calendarStart',
            'calendarEnd',
            'totalSchedules',
            'activeToday',
            'absentToday',
            'totalPersonnel',
            'securityPosts'
        ));
    }

    /**
     * Get date details for AJAX request (used by the calendar modal)
     */
    public function getDateDetails(Request $request)
    {
        try {
            $date = Carbon::parse($request->date);
            
            $schedules = SecuritySchedule::with([
                'post:id,name',
                'shift:id,name,start_time,end_time',
                'securityUser:id,name,phone',
                'assignedBy:id,name'
            ])
            ->whereDate('assignment_date', $date)
            ->orderBy('security_shift_id')
            ->get();

            $summary = [
                'active' => $schedules->where('status', 'active')->count(),
                'scheduled' => $schedules->where('status', 'scheduled')->count(),
                'completed' => $schedules->where('status', 'completed')->count(),
                'absent' => $schedules->where('status', 'absent')->count(),
                'verified' => $schedules->whereNotNull('checkin_verification')->count(),
                'late' => $schedules->where('late_minutes', '>', 0)->count(),
            ];

            $formattedSchedules = $schedules->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'status' => $schedule->status,
                    'security_user' => [
                        'id' => $schedule->securityUser->id,
                        'name' => $schedule->securityUser->name,
                        'phone' => $schedule->securityUser->phone,
                    ],
                    'post' => [
                        'id' => $schedule->post->id,
                        'name' => $schedule->post->name,
                    ],
                    'shift' => [
                        'id' => $schedule->shift->id,
                        'name' => $schedule->shift->name,
                        'time_range' => $schedule->shift->getTimeRange(),
                    ],
                    'assigned_by' => [
                        'id' => $schedule->assignedBy->id,
                        'name' => $schedule->assignedBy->name,
                    ],
                    'checkin_time' => $schedule->checkin_time?->format('Y-m-d H:i:s'),
                    'checkout_time' => $schedule->checkout_time?->format('Y-m-d H:i:s'),
                    'verified' => !is_null($schedule->checkin_verification),
                    'late_minutes' => $schedule->late_minutes,
                    'verification_method' => $schedule->checkin_verification['method'] ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'schedules' => $formattedSchedules,
                'summary' => $summary,
                'date' => $date->format('Y-m-d')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get date details: ' . $e->getMessage(), [
                'date' => $request->date,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load date details'
            ], 500);
        }
    }

    /**
     * Export schedule data with enhanced features
     */
    public function exportSchedules(Request $request)
    {
        $query = SecuritySchedule::with(['post', 'shift', 'securityUser', 'assignedBy']);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('assignment_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('shift_category')) {
            $query->whereHas('shift', function($q) use ($request) {
                $q->where('category', $request->shift_category);
            });
        }

        $schedules = $query->orderBy('assignment_date')->orderBy('security_post_id')->get();

        $fileName = 'security_schedules_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($schedules) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Schedule ID',
                'Date',
                'Day of Week',
                'Security Post',
                'Post Type',
                'Post Coordinates',
                'Shift',
                'Shift Category',
                'Start Time',
                'End Time',
                'Duration (hours)',
                'Is Overnight',
                'Security Personnel',
                'Personnel Phone',
                'Badge Number',
                'Assigned By',
                'Check-in Time',
                'Check-out Time',
                'Check-in Location',
                'Check-in Accuracy',
                'Verification Method',
                'Verification Status',
                'Late Minutes',
                'Late Flagged',
                'Overtime Minutes',
                'Total Minutes',
                'Status',
                'Breaks Included',
                'Break Duration',
                'Handover Required',
                'Handover Completed',
                'Rotation Type',
                'Current Rotation',
                'Rotation Group',
                'Emergency Contact',
                'Special Instructions',
                'Notes',
                'Offline Mode',
                'Supervisor Override',
                'Created At',
                'Updated At',
            ]);

            foreach ($schedules as $schedule) {
                $shift = $schedule->shift;
                $post = $schedule->post;
                $rotationData = $schedule->rotation_data ?? [];
                $verification = $schedule->checkin_verification;
                
                fputcsv($file, [
                    $schedule->id,
                    $schedule->assignment_date->format('Y-m-d'),
                    $schedule->assignment_date->format('l'),
                    $post->name ?? 'N/A',
                    $post->type ?? 'N/A',
                    $post->latitude && $post->longitude ? "{$post->latitude}, {$post->longitude}" : 'N/A',
                    $shift->name . ' (' . ($shift->getTimeRange() ?? 'N/A') . ')',
                    $shift->category ?? 'N/A',
                    $shift->start_time ?? 'N/A',
                    $shift->end_time ?? 'N/A',
                    $shift->duration_hours ?? 0,
                    $shift->is_overnight ? 'Yes' : 'No',
                    $schedule->securityUser->name ?? 'N/A',
                    $schedule->securityUser->phone ?? 'N/A',
                    $schedule->securityUser->badge_number ?? 'N/A',
                    $schedule->assignedBy->name ?? 'N/A',
                    $schedule->checkin_time ? $schedule->checkin_time->format('Y-m-d H:i:s') : 'N/A',
                    $schedule->checkout_time ? $schedule->checkout_time->format('Y-m-d H:i:s') : 'N/A',
                    $schedule->checkin_location ? json_encode($schedule->checkin_location) : 'N/A',
                    $schedule->checkin_accuracy ?? 'N/A',
                    $verification['method'] ?? 'N/A',
                    $verification ? 'Verified' : 'Unverified',
                    $schedule->late_minutes ?? 0,
                    $schedule->late_flagged ? 'Yes' : 'No',
                    $schedule->overtime_minutes ?? 0,
                    $schedule->total_minutes ?? 'N/A',
                    ucfirst($schedule->status),
                    $schedule->include_breaks ? 'Yes' : 'No',
                    $schedule->break_duration ?? 0,
                    $schedule->handover_info ? 'Yes' : 'No',
                    $schedule->handover_completed ? 'Yes' : 'No',
                    $shift->rotation_type ?? 'N/A',
                    $rotationData['current_position'] ?? 'N/A',
                    $rotationData['rotation_group_name'] ?? 'N/A',
                    $schedule->emergency_contact ?? '',
                    $schedule->special_instructions ?? '',
                    $schedule->notes ?? '',
                    $schedule->offline_mode ? 'Yes' : 'No',
                    $schedule->supervisor_override ? 'Yes' : 'No',
                    $schedule->created_at->format('Y-m-d H:i:s'),
                    $schedule->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Get global QR code statistics across all posts
     */
    public function getQrCodeStatistics()
    {
        try {
            // Get total QR codes
            $totalQrCodes = PostQrCode::count();
            
            // Get active QR codes (not expired and active)
            $activeQrCodes = PostQrCode::where('is_active', true)
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->count();
            
            // Get QR codes by type
            $byType = PostQrCode::select('code_type', DB::raw('count(*) as count'))
                ->groupBy('code_type')
                ->pluck('count', 'code_type')
                ->toArray();
            
            // Get QR codes by post
            $byPost = PostQrCode::select('post_id', DB::raw('count(*) as total'))
                ->selectRaw('SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active')
                ->groupBy('post_id')
                ->get()
                ->keyBy('post_id')
                ->toArray();
            
            // Get recent QR code activity (last 7 days)
            $recentActivity = PostQrCode::where('created_at', '>=', now()->subDays(7))
                ->count();
            
            // Get expiring soon QR codes (next 7 days)
            $expiringSoon = PostQrCode::where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(7))
                ->where('expires_at', '>', now())
                ->count();
            
            // Get expired QR codes
            $expired = PostQrCode::where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count();
            
            // Get one-time use QR codes that have been used
            $oneTimeUsed = PostQrCode::where('code_type', 'one_time')
                ->where('uses_count', '>', 0)
                ->count();
            
            return response()->json([
                'success' => true,
                'stats' => [
                    'total' => $totalQrCodes,
                    'active' => $activeQrCodes,
                    'by_type' => $byType,
                    'by_post' => $byPost,
                    'recent_activity' => $recentActivity,
                    'expiring_soon' => $expiringSoon,
                    'expired' => $expired,
                    'one_time_used' => $oneTimeUsed,
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to get QR code statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load QR code statistics',
                'stats' => [
                    'total' => 0,
                    'active' => 0,
                    'by_type' => [],
                    'by_post' => [],
                    'recent_activity' => 0,
                    'expiring_soon' => 0,
                    'expired' => 0,
                    'one_time_used' => 0,
                ]
            ], 500);
        }
}

/**
 * Get post capacity for a specific date
 * 
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getPostCapacity(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:security_posts,id',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $postId = $request->post_id;
        $date = Carbon::parse($request->date);
        $dateStr = $date->format('Y-m-d');

        // Get post details
        $post = SecurityPost::find($postId);
        
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
                'capacity' => 0,
                'assigned' => 0,
                'available' => 0,
                'max_personnel' => 0,
                'is_full' => true
            ], 404);
        }

        // Get current assigned count for this post on this date
        $assignedCount = SecuritySchedule::where('security_post_id', $postId)
            ->whereDate('assignment_date', $dateStr)
            ->where('status', '!=', 'cancelled')
            ->count();

        $maxPersonnel = $post->max_personnel ?? 0;
        $availableSlots = max(0, $maxPersonnel - $assignedCount);
        $isFull = $availableSlots <= 0;

        // Get personnel currently assigned to this post on this date
        $assignedPersonnel = SecuritySchedule::where('security_post_id', $postId)
            ->whereDate('assignment_date', $dateStr)
            ->where('status', '!=', 'cancelled')
            ->with(['securityUser:id,name,phone,badge_number'])
            ->get(['id', 'security_user_id', 'security_shift_id', 'status'])
            ->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'user_id' => $schedule->security_user_id,
                    'name' => $schedule->securityUser->name ?? 'Unknown',
                    'phone' => $schedule->securityUser->phone ?? 'N/A',
                    'badge_number' => $schedule->securityUser->badge_number ?? 'N/A',
                    'status' => $schedule->status,
                    'shift_id' => $schedule->security_shift_id,
                ];
            });

        // Get available personnel (not assigned to this post on this date)
        $assignedUserIds = SecuritySchedule::where('security_post_id', $postId)
            ->whereDate('assignment_date', $dateStr)
            ->where('status', '!=', 'cancelled')
            ->pluck('security_user_id')
            ->toArray();

        $availablePersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotIn('id', $assignedUserIds)
            ->get(['id', 'name', 'phone', 'badge_number'])
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'badge_number' => $user->badge_number,
                ];
            });

        return response()->json([
            'success' => true,
            'capacity' => $maxPersonnel,
            'assigned' => $assignedCount,
            'available' => $availableSlots,
            'max_personnel' => $maxPersonnel,
            'is_full' => $isFull,
            'post' => [
                'id' => $post->id,
                'name' => $post->name,
                'code' => $post->code,
                'location' => $post->location,
                'latitude' => $post->latitude,
                'longitude' => $post->longitude,
                'is_active' => $post->is_active,
            ],
            'assigned_personnel' => $assignedPersonnel,
            'available_personnel' => $availablePersonnel,
            'date' => $dateStr,
            'coverage_percentage' => $maxPersonnel > 0 
                ? round(($assignedCount / $maxPersonnel) * 100, 2) 
                : 0,
            'status' => $isFull ? 'full' : ($assignedCount > 0 ? 'partial' : 'empty')
        ]);

    } catch (\Exception $e) {
        \Log::error('Failed to get post capacity: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to get post capacity: ' . $e->getMessage(),
            'capacity' => 0,
            'assigned' => 0,
            'available' => 0,
            'max_personnel' => 0,
            'is_full' => true
        ], 500);
    }
}

}