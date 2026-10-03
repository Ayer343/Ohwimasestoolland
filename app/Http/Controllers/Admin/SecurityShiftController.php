<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityShift;
use App\Models\SecuritySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\SecurityPost;
use App\Models\User;  
use Carbon\Carbon;

class SecurityShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SecurityShift::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status == 'active');
        }

        // Filter by rotation type
        if ($request->has('rotation_type') && $request->rotation_type) {
            $query->where('rotation_type', $request->rotation_type);
        }

        // Filter by required personnel
        if ($request->has('personnel') && $request->personnel) {
            if ($request->personnel == '1') {
                $query->where('required_personnel', 1);
            } elseif ($request->personnel == '2') {
                $query->where('required_personnel', 2);
            } elseif ($request->personnel == '3') {
                $query->where('required_personnel', '>=', 3);
            }
        }

        // Filter by shift category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Sort functionality
        $sortBy = $request->get('sort_by', 'start_time');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Get statistics
        $totalShifts = SecurityShift::count();
        $activeShifts = SecurityShift::where('is_active', true)->count();
        $activeToday = SecurityShift::active()->forToday()->count();
        $averageDuration = SecurityShift::avg('duration_hours') ?? 0;
        $shortestDuration = SecurityShift::min('duration_hours') ?? 0;
        $totalPersonnel = SecurityShift::sum('required_personnel') ?? 0;
        
        // New statistics
        $dayShifts = SecurityShift::where('category', 'day')->count();
        $nightShifts = SecurityShift::where('category', 'night')->count();
        $rotatingShifts = SecurityShift::where('rotation_type', 'rotating')->count();

        // Get shifts with schedule count
        $query->withCount('schedules');
        
        $shifts = $query->paginate(20);

        // Get shift categories for filter
        $shiftCategories = [
            'day' => 'Day Shifts',
            'night' => 'Night Shifts',
            'evening' => 'Evening Shifts',
            'special' => 'Special Shifts',
            'holiday' => 'Holiday Shifts'
        ];

        // Get rotation types
        $rotationTypes = [
            'fixed' => 'Fixed Shift',
            'rotating' => 'Rotating Shift'
        ];

        return view('admin.security-shifts.index', compact(
            'shifts',
            'totalShifts',
            'activeShifts',
            'activeToday',
            'averageDuration',
            'shortestDuration',
            'totalPersonnel',
            'dayShifts',
            'nightShifts',
            'rotatingShifts',
            'shiftCategories',
            'rotationTypes'
        ));
    }

 /**
     * Display trashed schedules
     */
    public function trash(Request $request)
    {
        $query = SecuritySchedule::onlyTrashed()->with(['post', 'shift', 'securityUser', 'assignedBy']);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('post', function($q2) use ($search) {
                    $q2->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('code', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('shift', function($q2) use ($search) {
                    $q2->where('name', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('securityUser', function($q2) use ($search) {
                    $q2->where('name', 'LIKE', "%{$search}%");
                });
            });
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('assignment_date', $request->date);
        }

        // Filter by post
        if ($request->filled('post_id')) {
            $query->where('security_post_id', $request->post_id);
        }

        // Filter by shift
        if ($request->filled('shift_id')) {
            $query->where('security_shift_id', $request->shift_id);
        }

        // Filter by personnel
        if ($request->filled('security_user_id')) {
            $query->where('security_user_id', $request->security_user_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'deleted_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Get statistics
        $trashStats = [
            'total_trashed' => SecuritySchedule::onlyTrashed()->count(),
            'trashed_by_period' => [
                'today' => SecuritySchedule::onlyTrashed()->whereDate('deleted_at', today())->count(),
                'this_week' => SecuritySchedule::onlyTrashed()
                    ->whereBetween('deleted_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
            ],
            'oldest_trashed' => SecuritySchedule::onlyTrashed()
                ->orderBy('deleted_at', 'asc')
                ->first()?->deleted_at?->format('M j, Y') ?? 'N/A'
        ];

        // Get data for filters
        $securityPosts = SecurityPost::orderBy('name')->get();
        $securityShifts = SecurityShift::orderBy('name')->get();
        $securityPersonnel = User::where('type', User::TYPE_SECURITY_PERSONNEL)
                                ->orderBy('name')
                                ->get();

        $trashedSchedules = $query->paginate(20);

        return view('admin.security-schedules.trash', compact(
            'trashedSchedules',
            'securityPosts',
            'securityShifts',
            'securityPersonnel',
            'trashStats'
        ));
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dayTypes = [
            'all_days' => 'All Days',
            'weekday' => 'Weekdays',
            'weekend' => 'Weekends',
            'custom' => 'Custom Days'
        ];

        $daysOfWeek = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ];

        $shiftCategories = [
            'day' => 'Day Shift (06:00 - 18:00)',
            'night' => 'Night Shift (18:00 - 06:00)',
            'evening' => 'Evening Shift (14:00 - 22:00)',
            'special' => 'Special Shift',
            'holiday' => 'Holiday Shift'
        ];

        $rotationTypes = [
            'fixed' => 'Fixed (Same shift always)',
            'rotating' => 'Rotating (Changes periodically)'
        ];

        $rotationSequences = [
            'morning_evening' => 'Morning → Evening → Night → Off',
            'evening_morning' => 'Evening → Morning → Night → Off',
            'night_morning' => 'Night → Morning → Evening → Off'
        ];

        return view('admin.security-shifts.create', compact(
            'dayTypes',
            'daysOfWeek',
            'shiftCategories',
            'rotationTypes',
            'rotationSequences'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Debug logging
        Log::info('=== SHIFT CREATION STARTED ===', [
            'has_break_raw' => $request->input('has_break'),
            'has_handover_raw' => $request->input('has_handover'),
            'is_active_raw' => $request->input('is_active'),
            'request_data' => $request->except('_token')
        ]);

        $validator = $this->validateShift($request);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $data = $this->prepareShiftData($request);
            Log::info('Prepared shift data:', $data);

            // Handle applicable days based on day type
            if ($data['day_type'] === 'all_days' || $data['day_type'] === 'weekday' || $data['day_type'] === 'weekend') {
                $data['applicable_days'] = null;
            } elseif ($data['day_type'] === 'custom' && $request->has('applicable_days')) {
                $data['applicable_days'] = $request->applicable_days;
                Log::info('Custom days set:', ['days' => $data['applicable_days']]);
            }

            // Handle rotation configuration
            if ($data['rotation_type'] === 'rotating') {
                $rotationConfig = [
                    'rotation_days' => (int)($request->rotation_days ?? 7),
                    'rotation_sequence' => $request->rotation_sequence ?? 'morning_evening',
                    'last_rotation_date' => null,
                    'current_sequence_index' => 0
                ];
                $data['rotation_config'] = $rotationConfig;
                Log::info('Rotation config set:', $rotationConfig);
            }

            // FIXED: Handle break schedule - check for value '1' AND actual break data
            $hasBreak = $request->input('has_break') === '1' && 
                        $request->has('break_names') && 
                        is_array($request->break_names) && 
                        count(array_filter($request->break_names)) > 0;

            if ($hasBreak) {
                Log::info('Processing break schedule - checkbox is checked and break data exists', [
                    'raw_value' => $request->input('has_break'),
                    'break_count' => count($request->break_names)
                ]);
                
                $breaks = [];
                
                if ($request->has('break_names') && is_array($request->break_names)) {
                    $breakCount = count($request->break_names);
                    Log::info('Number of breaks:', ['count' => $breakCount]);
                    
                    for ($i = 0; $i < $breakCount; $i++) {
                        // Only add break if it has required data
                        if (!empty($request->break_names[$i]) && 
                            !empty($request->break_start_times[$i]) && 
                            !empty($request->break_end_times[$i])) {
                            
                            // Check if break is paid
                            $isPaid = isset($request->break_paid[$i]) && $request->break_paid[$i] === '1';
                            
                            $breaks[] = [
                                'name' => $request->break_names[$i],
                                'start_time' => $request->break_start_times[$i],
                                'end_time' => $request->break_end_times[$i],
                                'duration_minutes' => (int)($request->break_durations[$i] ?? 0),
                                'is_paid' => $isPaid,
                                'description' => $request->break_descriptions[$i] ?? null
                            ];
                            
                            Log::info('Added break:', [
                                'index' => $i,
                                'name' => $request->break_names[$i],
                                'start' => $request->break_start_times[$i],
                                'end' => $request->break_end_times[$i],
                                'duration' => $request->break_durations[$i] ?? 0,
                                'is_paid' => $isPaid
                            ]);
                        } else {
                            Log::warning('Skipped incomplete break at index: ' . $i, [
                                'has_name' => !empty($request->break_names[$i]),
                                'has_start' => !empty($request->break_start_times[$i]),
                                'has_end' => !empty($request->break_end_times[$i])
                            ]);
                        }
                    }
                }
                
                $data['break_schedule'] = [
                    'has_break' => true,
                    'breaks' => $breaks,
                    'total_break_minutes' => array_sum(array_column($breaks, 'duration_minutes')),
                    'total_breaks' => count($breaks)
                ];
                Log::info('Break schedule set:', [
                    'total_breaks' => count($breaks),
                    'total_break_minutes' => $data['break_schedule']['total_break_minutes']
                ]);
            } else {
                $data['break_schedule'] = null;
                Log::info('No break schedule - checkbox not checked or no break data', [
                    'raw_value' => $request->input('has_break'),
                    'has_input' => $request->has('has_break')
                ]);
            }

            // FIXED: Handle handover configuration - check for value '1'
            $hasHandover = $request->input('has_handover') === '1';

            if ($hasHandover) {
                Log::info('Processing handover configuration - checkbox is checked', [
                    'raw_value' => $request->input('has_handover')
                ]);
                
                // Check if handover notes are required
                $handoverNotesRequired = $request->input('handover_notes_required') === '1';
                
                $data['handover_config'] = [
                    'has_handover' => true,
                    'handover_duration' => (int)($request->handover_duration ?? 30),
                    'handover_notes_required' => $handoverNotesRequired,
                    'handover_checklist' => $request->handover_checklist ?? [
                        'equipment_check',
                        'incident_report',
                        'visitor_logs',
                        'key_handover'
                    ]
                ];
                Log::info('Handover config set:', [
                    'duration' => $data['handover_config']['handover_duration'],
                    'notes_required' => $data['handover_config']['handover_notes_required'],
                    'checklist_items' => count($data['handover_config']['handover_checklist'])
                ]);
            } else {
                $data['handover_config'] = null;
                Log::info('No handover config - checkbox is not checked', [
                    'raw_value' => $request->input('has_handover'),
                    'has_input' => $request->has('has_handover')
                ]);
            }

            // Create the shift
            Log::info('Attempting to create shift with data:', [
                'name' => $data['name'],
                'code' => $data['code'],
                'category' => $data['category'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'has_break_schedule' => !is_null($data['break_schedule']),
                'has_handover_config' => !is_null($data['handover_config'])
            ]);
            
            $shift = SecurityShift::create($data);
            Log::info('Shift created successfully with ID: ' . $shift->id);

            // Log the creation with detailed information
            Log::info('Security shift created', [
                'shift_id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code,
                'created_by' => auth()->id(),
                'created_by_email' => auth()->user()->email ?? 'unknown',
                'category' => $shift->category,
                'rotation_type' => $shift->rotation_type,
                'has_breaks' => !is_null($shift->break_schedule),
                'break_count' => $shift->break_schedule['total_breaks'] ?? 0,
                'total_break_minutes' => $shift->break_schedule['total_break_minutes'] ?? 0,
                'has_handover' => !is_null($shift->handover_config),
                'handover_duration' => $shift->handover_config['handover_duration'] ?? 0,
                'required_personnel' => $shift->required_personnel,
                'is_active' => $shift->is_active,
                'is_overnight' => $shift->is_overnight,
                'duration_hours' => $shift->duration_hours,
                'created_at' => now()->toDateTimeString()
            ]);

            DB::commit();

            Log::info('=== SHIFT CREATION COMPLETED SUCCESSFULLY ===', [
                'shift_id' => $shift->id,
                'processing_time_ms' => round((microtime(true) - LARAVEL_START) * 1000, 2)
            ]);

            return redirect()->route('admin.security-shifts.index')
                ->with('success', 'Security shift created successfully.');

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('DATABASE ERROR: ' . $e->getMessage());
            Log::error('SQL: ' . $e->getSql());
            Log::error('Bindings: ' . json_encode($e->getBindings()));
            
            return redirect()->back()
                ->with('error', 'Database error: ' . $this->getUserFriendlyDatabaseError($e))
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GENERAL ERROR: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Error creating security shift: ' . $this->getUserFriendlyError($e))
                ->withInput();
        }
    }

    /**
     * Validate shift data.
     */
    private function validateShift(Request $request, $shiftId = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:security_shifts,code' . ($shiftId ? ',' . $shiftId : ''),
            'category' => 'required|in:day,night,evening,special,holiday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'day_type' => 'required|in:all_days,weekday,weekend,custom',
            'rotation_type' => 'required|in:fixed,rotating',
            'is_active' => 'sometimes|boolean',
            'required_personnel' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string|max:1000',
        ];

        // Custom days validation
        if ($request->day_type === 'custom') {
            $rules['applicable_days'] = 'required|array|min:1';
            $rules['applicable_days.*'] = 'integer|between:1,7';
        }

        // Rotation validation
        if ($request->rotation_type === 'rotating') {
            $rules['rotation_days'] = 'required|integer|min:1|max:30';
            $rules['rotation_sequence'] = 'required|in:morning_evening,evening_morning,night_morning';
        }

        // FIXED: Check for actual break data presence
        $hasBreak = $request->input('has_break') === '1' && 
                    $request->has('break_names') && 
                    is_array($request->break_names) && 
                    count(array_filter($request->break_names)) > 0;

        // Only validate break fields if we have actual break data
        if ($hasBreak) {
            $rules['break_names'] = 'required|array|min:1';
            $rules['break_names.*'] = 'required|string|max:100';
            $rules['break_start_times'] = 'required|array|min:1';
            $rules['break_start_times.*'] = 'required|date_format:H:i';
            $rules['break_end_times'] = 'required|array|min:1';
            $rules['break_end_times.*'] = 'required|date_format:H:i';
            $rules['break_durations'] = 'required|array|min:1';
            $rules['break_durations.*'] = 'required|integer|min:1|max:240';
        }

        // FIXED: Handle handover validation
        $hasHandover = $request->input('has_handover') === '1';

        if ($hasHandover) {
            $rules['handover_duration'] = 'nullable|integer|min:5|max:120';
        }

        $validator = Validator::make($request->all(), $rules);

        // FIXED: Only validate break times if we have break data
        $validator->after(function ($validator) use ($request, $hasBreak) {
            if ($hasBreak && $request->has('break_start_times') && $request->has('break_end_times')) {
                try {
                    $shiftStart = Carbon::createFromFormat('H:i', $request->start_time);
                    $shiftEnd = Carbon::createFromFormat('H:i', $request->end_time);
                    
                    // Handle overnight shifts
                    if ($shiftEnd <= $shiftStart) {
                        $shiftEnd->addDay();
                    }
                    
                    foreach ($request->break_start_times as $index => $breakStart) {
                        if (!isset($request->break_end_times[$index])) continue;
                        
                        // Skip empty values
                        if (empty($breakStart) || empty($request->break_end_times[$index])) {
                            continue;
                        }
                        
                        try {
                            $breakStartTime = Carbon::createFromFormat('H:i', $breakStart);
                            $breakEndTime = Carbon::createFromFormat('H:i', $request->break_end_times[$index]);
                            
                            // Handle overnight breaks
                            if ($breakEndTime <= $breakStartTime) {
                                $breakEndTime->addDay();
                            }
                            
                            // Check if break is within shift hours
                            if ($breakStartTime < $shiftStart || $breakEndTime > $shiftEnd) {
                                $validator->errors()->add(
                                    "break_start_times.{$index}",
                                    "Break '{$request->break_names[$index]}' must be within shift hours ({$request->start_time} - {$request->end_time})"
                                );
                            }
                            
                            // Check if break duration matches times
                            if (isset($request->break_durations[$index])) {
                                $calculatedDuration = $breakStartTime->diffInMinutes($breakEndTime);
                                $providedDuration = (int) $request->break_durations[$index];
                                
                                if (abs($calculatedDuration - $providedDuration) > 5) {
                                    $validator->errors()->add(
                                        "break_durations.{$index}",
                                        "Break duration doesn't match start/end times. Expected: {$calculatedDuration} minutes"
                                    );
                                }
                            }
                        } catch (\Exception $e) {
                            $validator->errors()->add(
                                "break_start_times.{$index}",
                                "Invalid time format for break '{$request->break_names[$index]}'."
                            );
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not validate break times due to invalid shift time format');
                }
            }
        });

        return $validator;
    }

    /**
     * Prepare shift data from request.
     */
    private function prepareShiftData(Request $request)
    {
        try {
            $start = Carbon::createFromFormat('H:i', $request->start_time);
            $end = Carbon::createFromFormat('H:i', $request->end_time);
        } catch (\Exception $e) {
            throw new \Exception('Invalid time format. Please use HH:MM format.');
        }

        // Handle overnight shifts
        if ($end <= $start) {
            $end->addDay();
        }

        $durationHours = round($end->diffInMinutes($start) / 60, 2);
        $isOvernight = $request->end_time <= $request->start_time;

        // FIXED: Better handling of is_active checkbox
        $isActive = $request->input('is_active') === '1';

        return [
            'name' => $request->name,
            'code' => $request->code,
            'category' => $request->category,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'duration_hours' => $durationHours,
            'is_overnight' => $isOvernight,
            'day_type' => $request->day_type,
            'rotation_type' => $request->rotation_type,
            'is_active' => $isActive,
            'required_personnel' => (int)$request->required_personnel,
            'description' => $request->description,
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(SecurityShift $securityShift)
    {
        // Load related data with counts
        $securityShift->loadCount(['schedules']);
        $securityShift->load([
            'schedules' => function($query) {
                $query->whereDate('assignment_date', '>=', today())
                      ->orderBy('assignment_date')
                      ->limit(10)
                      ->with(['post', 'securityUser']);
            }
        ]);

        // Get shift statistics
        $shiftStats = [
            'total_assignments' => $securityShift->schedules()->count(),
            'completed_shifts' => $securityShift->schedules()->where('status', 'completed')->count(),
            'active_assignments' => $securityShift->schedules()
                ->whereDate('assignment_date', '>=', today())
                ->whereIn('status', ['scheduled', 'active'])
                ->count(),
            'attendance_rate' => $this->calculateShiftAttendanceRate($securityShift),
            'average_checkin_time' => $this->calculateAverageCheckinTime($securityShift),
        ];

        // Get upcoming rotations if rotating shift
        $upcomingRotations = [];
        if ($securityShift->rotation_type === 'rotating') {
            $upcomingRotations = $this->getUpcomingRotations($securityShift);
        }

        return view('admin.security-shifts.show', compact(
            'securityShift',
            'shiftStats',
            'upcomingRotations'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SecurityShift $securityShift)
    {
        $dayTypes = [
            'all_days' => 'All Days',
            'weekday' => 'Weekdays',
            'weekend' => 'Weekends',
            'custom' => 'Custom Days'
        ];

        $daysOfWeek = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ];

        $shiftCategories = [
            'day' => 'Day Shift (06:00 - 18:00)',
            'night' => 'Night Shift (18:00 - 06:00)',
            'evening' => 'Evening Shift (14:00 - 22:00)',
            'special' => 'Special Shift',
            'holiday' => 'Holiday Shift'
        ];

        $rotationTypes = [
            'fixed' => 'Fixed (Same shift always)',
            'rotating' => 'Rotating (Changes periodically)'
        ];

        $rotationSequences = [
            'morning_evening' => 'Morning → Evening → Night → Off',
            'evening_morning' => 'Evening → Morning → Night → Off',
            'night_morning' => 'Night → Morning → Evening → Off'
        ];

        return view('admin.security-shifts.edit', compact(
            'securityShift',
            'dayTypes',
            'daysOfWeek',
            'shiftCategories',
            'rotationTypes',
            'rotationSequences'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SecurityShift $securityShift)
    {
        $validator = $this->validateShift($request, $securityShift->id);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $data = $this->prepareShiftData($request);

            // Handle applicable days based on day type
            if ($data['day_type'] === 'all_days' || $data['day_type'] === 'weekday' || $data['day_type'] === 'weekend') {
                $data['applicable_days'] = null;
            } elseif ($data['day_type'] === 'custom' && $request->has('applicable_days')) {
                $data['applicable_days'] = $request->applicable_days;
            }

            // Handle rotation configuration
            if ($data['rotation_type'] === 'rotating') {
                $rotationConfig = [
                    'rotation_days' => (int)($request->rotation_days ?? 7),
                    'rotation_sequence' => $request->rotation_sequence ?? 'morning_evening',
                    'last_rotation_date' => $securityShift->rotation_config['last_rotation_date'] ?? null,
                    'current_sequence_index' => $securityShift->rotation_config['current_sequence_index'] ?? 0
                ];
                $data['rotation_config'] = $rotationConfig;
            } else {
                $data['rotation_config'] = null;
            }

            // FIXED: Handle break schedule
            $hasBreak = $request->input('has_break') === '1' && 
                        $request->has('break_names') && 
                        is_array($request->break_names) && 
                        count(array_filter($request->break_names)) > 0;

            if ($hasBreak) {
                $breaks = [];
                
                if ($request->has('break_names') && is_array($request->break_names)) {
                    $breakCount = count($request->break_names);
                    
                    for ($i = 0; $i < $breakCount; $i++) {
                        $breaks[] = [
                            'name' => $request->break_names[$i] ?? 'Break',
                            'start_time' => $request->break_start_times[$i] ?? null,
                            'end_time' => $request->break_end_times[$i] ?? null,
                            'duration_minutes' => (int)($request->break_durations[$i] ?? 0),
                            'is_paid' => isset($request->break_paid[$i]) && $request->break_paid[$i] === '1',
                            'description' => $request->break_descriptions[$i] ?? null
                        ];
                    }
                }
                
                $data['break_schedule'] = [
                    'has_break' => true,
                    'breaks' => $breaks,
                    'total_break_minutes' => array_sum(array_column($breaks, 'duration_minutes')),
                    'total_breaks' => count($breaks)
                ];
            } else {
                $data['break_schedule'] = null;
            }

            // FIXED: Handle handover configuration
            $hasHandover = $request->input('has_handover') === '1';

            if ($hasHandover) {
                $data['handover_config'] = [
                    'has_handover' => true,
                    'handover_duration' => (int)($request->handover_duration ?? 30),
                    'handover_notes_required' => $request->input('handover_notes_required') === '1',
                    'handover_checklist' => $request->handover_checklist ?? [
                        'equipment_check',
                        'incident_report',
                        'visitor_logs',
                        'key_handover'
                    ]
                ];
            } else {
                $data['handover_config'] = null;
            }

            $securityShift->update($data);

            // Log the update
            Log::info('Security shift updated', [
                'shift_id' => $securityShift->id,
                'name' => $securityShift->name,
                'updated_by' => auth()->id(),
                'changes' => array_keys($data)
            ]);

            DB::commit();

            return redirect()->route('admin.security-shifts.show', $securityShift)
                ->with('success', 'Security shift updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating security shift: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Error updating security shift: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage (Soft Delete).
     */
    public function destroy(SecurityShift $securityShift)
    {
        try {
            $shiftName = $securityShift->name;
            $securityShift->delete();

            Log::info('Security shift soft deleted', [
                'shift_id' => $securityShift->id,
                'name' => $shiftName,
                'deleted_by' => auth()->id(),
                'deleted_at' => now()
            ]);

            return redirect()->route('admin.security-shifts.index')
                ->with('success', 'Security shift moved to trash successfully.');

        } catch (\Exception $e) {
            Log::error('Error soft deleting security shift: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error moving shift to trash: ' . $e->getMessage());
        }
    }

    /**
     * Restore a single schedule
     */
    public function restore($id)
    {
        try {
            DB::beginTransaction();
            
            $schedule = SecuritySchedule::onlyTrashed()->findOrFail($id);
            
            // Check for conflicts before restoring
            $existingSchedule = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                ->where('security_shift_id', $schedule->security_shift_id)
                ->where('assignment_date', $schedule->assignment_date)
                ->where('id', '!=', $schedule->id)
                ->first();
                
            if ($existingSchedule) {
                return redirect()->back()
                    ->with('error', 'Cannot restore schedule. A schedule already exists for this post, shift, and date.');
            }
            
            $schedule->restore();
            
            Log::info('Schedule restored from trash', [
                'schedule_id' => $schedule->id,
                'post' => $schedule->post->name ?? 'Unknown',
                'date' => $schedule->assignment_date,
                'restored_by' => auth()->id()
            ]);
            
            DB::commit();
            
            return redirect()->route('admin.security-schedules.trash')
                ->with('success', 'Schedule restored successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error restoring schedule: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Error restoring schedule: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a single schedule
     */
    public function forceDelete($id)
    {
        try {
            DB::beginTransaction();
            
            $schedule = SecuritySchedule::onlyTrashed()->findOrFail($id);
            $scheduleInfo = [
                'id' => $schedule->id,
                'post' => $schedule->post->name ?? 'Unknown',
                'date' => $schedule->assignment_date
            ];
            
            $schedule->forceDelete();
            
            Log::info('Schedule permanently deleted', [
                'schedule_id' => $scheduleInfo['id'],
                'post' => $scheduleInfo['post'],
                'date' => $scheduleInfo['date'],
                'deleted_by' => auth()->id()
            ]);
            
            DB::commit();
            
            return redirect()->route('admin.security-schedules.trash')
                ->with('success', 'Schedule permanently deleted successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error force deleting schedule: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Error deleting schedule: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore schedules
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'exists:security_schedules,id'
        ]);

        try {
            DB::beginTransaction();
            
            $schedules = SecuritySchedule::onlyTrashed()
                ->whereIn('id', $request->schedule_ids)
                ->get();
                
            $restoredCount = 0;
            $failedRestores = [];

            foreach ($schedules as $schedule) {
                // Check for conflicts
                $existingSchedule = SecuritySchedule::where('security_post_id', $schedule->security_post_id)
                    ->where('security_shift_id', $schedule->security_shift_id)
                    ->where('assignment_date', $schedule->assignment_date)
                    ->where('id', '!=', $schedule->id)
                    ->first();
                    
                if ($existingSchedule) {
                    $failedRestores[] = $schedule->post->name . ' - ' . $schedule->assignment_date;
                    continue;
                }
                
                $schedule->restore();
                $restoredCount++;
            }
            
            DB::commit();

            $message = "{$restoredCount} schedule(s) restored successfully.";
            if (!empty($failedRestores)) {
                $message .= " Failed to restore: " . implode(', ', $failedRestores) . " (conflict with existing schedules)";
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error restoring schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk force delete schedules
     */
    public function bulkForceDelete(Request $request)
    {
        $request->validate([
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'exists:security_schedules,id'
        ]);

        try {
            DB::beginTransaction();
            
            $deletedCount = SecuritySchedule::onlyTrashed()
                ->whereIn('id', $request->schedule_ids)
                ->forceDelete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} schedule(s) permanently deleted successfully."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting schedules: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Clear all trash or older than specified days
     */
    public function clearOldTrash(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:0'
        ]);

        try {
            DB::beginTransaction();
            
            $days = $request->get('days', 0);
            
            $query = SecuritySchedule::onlyTrashed();
            
            if ($days > 0) {
                $query->where('deleted_at', '<=', now()->subDays($days));
            }
            
            $deletedCount = $query->forceDelete();
            
            DB::commit();

            return redirect()->route('admin.security-schedules.trash')
                ->with('success', "{$deletedCount} old schedule(s) permanently deleted successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Error clearing trash: ' . $e->getMessage());
        }
    }
    

    /**
     * Calculate shift attendance rate
     */
    private function calculateShiftAttendanceRate(SecurityShift $shift)
    {
        $totalShifts = $shift->schedules()->count();
        $completedShifts = $shift->schedules()->where('status', 'completed')->count();
        $absentShifts = $shift->schedules()->where('status', 'absent')->count();
        
        if ($totalShifts === 0) return 100;
        
        $attendedShifts = $completedShifts + ($totalShifts - $completedShifts - $absentShifts);
        return round(($attendedShifts / $totalShifts) * 100, 2);
    }

    /**
     * Calculate average check-in time
     */
    private function calculateAverageCheckinTime(SecurityShift $shift)
    {
        $schedules = $shift->schedules()
            ->whereNotNull('checkin_time')
            ->where('status', 'completed')
            ->get();

        if ($schedules->isEmpty()) return null;

        $totalMinutesLate = 0;
        $count = 0;

        foreach ($schedules as $schedule) {
            $shiftStart = Carbon::parse($schedule->assignment_date->format('Y-m-d') . ' ' . $shift->start_time);
            $checkinTime = Carbon::parse($schedule->checkin_time);
            
            if ($checkinTime->greaterThan($shiftStart)) {
                $totalMinutesLate += $checkinTime->diffInMinutes($shiftStart);
                $count++;
            }
        }

        return $count > 0 ? round($totalMinutesLate / $count, 2) : 0;
    }

    /**
     * Get upcoming rotations for rotating shift
     */
    private function getUpcomingRotations(SecurityShift $shift, $days = 30)
    {
        $rotations = [];
        $startDate = today();
        
        $sequence = $this->getRotationSequence($shift->rotation_config['rotation_sequence'] ?? 'morning_evening');
        $currentIndex = $shift->rotation_config['current_sequence_index'] ?? 0;
        $rotationDays = $shift->rotation_config['rotation_days'] ?? 7;
        
        $date = $startDate->copy();
        $currentIndex = $currentIndex % count($sequence);
        
        for ($i = 0; $i < $days; $i++) {
            $rotations[] = [
                'date' => $date->copy(),
                'shift_type' => $sequence[$currentIndex]
            ];
            
            $date->addDay();
            
            if ($i > 0 && ($i + 1) % $rotationDays === 0) {
                $currentIndex = ($currentIndex + 1) % count($sequence);
            }
        }
        
        return $rotations;
    }

    /**
     * Get rotation sequence based on type
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
     * Get user-friendly database error message
     */
    private function getUserFriendlyDatabaseError($e)
    {
        $message = $e->getMessage();
        
        if (str_contains($message, 'Duplicate entry')) {
            if (str_contains($message, 'code')) {
                return 'A shift with this code already exists. Please use a different code.';
            }
            return 'A duplicate entry was found. Please check your data.';
        }
        
        if (str_contains($message, 'foreign key constraint')) {
            return 'This operation violates data integrity. Please check related records.';
        }
        
        if (str_contains($message, 'Data too long')) {
            return 'One or more fields exceed the maximum length.';
        }
        
        return 'A database error occurred. Please try again.';
    }

    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyError($e)
    {
        $message = $e->getMessage();
        
        if (str_contains($message, 'Invalid time format')) {
            return $message;
        }
        
        if (str_contains($message, 'Unique constraint')) {
            return 'A shift with this code already exists.';
        }
        
        Log::warning('Original error message: ' . $message);
        
        return 'An unexpected error occurred. Please try again or contact support.';
    }

    /**
     * Bulk actions for shifts.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete,export,restore,force_delete',
            'ids' => 'required|array',
            'ids.*' => 'exists:security_shifts,id'
        ]);

        try {
            DB::beginTransaction();

            switch ($request->action) {
                case 'activate':
                    SecurityShift::whereIn('id', $request->ids)->update(['is_active' => true]);
                    $message = 'Selected shifts activated successfully.';
                    break;

                case 'deactivate':
                    SecurityShift::whereIn('id', $request->ids)->update(['is_active' => false]);
                    $message = 'Selected shifts deactivated successfully.';
                    break;

                case 'delete':
                    SecurityShift::whereIn('id', $request->ids)->delete();
                    $message = 'Selected shifts moved to trash successfully.';
                    break;

                case 'restore':
                    return $this->bulkRestore($request);

                case 'force_delete':
                    return $this->bulkForceDelete($request);

                case 'export':
                    return $this->exportSelected($request->ids);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error performing bulk action: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Export selected shifts
     */
    private function exportSelected($ids)
    {
        $shifts = SecurityShift::whereIn('id', $ids)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="security_shifts_export_' . date('Y-m-d_H-i-s') . '.csv"',
        ];

        $callback = function() use ($shifts) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Code', 'Name', 'Category', 'Start Time', 'End Time', 
                'Duration (hours)', 'Is Overnight', 'Day Type', 'Applicable Days',
                'Rotation Type', 'Required Personnel', 'Status', 'Description',
                'Break Schedule', 'Handover Config', 'Rotation Config'
            ]);

            foreach ($shifts as $shift) {
                fputcsv($file, [
                    $shift->code,
                    $shift->name,
                    $shift->category,
                    $shift->start_time,
                    $shift->end_time,
                    $shift->duration_hours,
                    $shift->is_overnight ? 'Yes' : 'No',
                    $shift->day_type,
                    $shift->applicable_days ? implode(',', $shift->applicable_days) : '',
                    $shift->rotation_type,
                    $shift->required_personnel,
                    $shift->is_active ? 'Active' : 'Inactive',
                    $shift->description,
                    $shift->break_schedule ? json_encode($shift->break_schedule) : '',
                    $shift->handover_config ? json_encode($shift->handover_config) : '',
                    $shift->rotation_config ? json_encode($shift->rotation_config) : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Toggle shift status.
     */
    public function toggleStatus(SecurityShift $securityShift)
    {
        try {
            $securityShift->update([
                'is_active' => !$securityShift->is_active
            ]);

            Log::info('Security shift status toggled', [
                'shift_id' => $securityShift->id,
                'name' => $securityShift->name,
                'new_status' => $securityShift->is_active ? 'active' : 'inactive',
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shift status updated successfully.',
                'is_active' => $securityShift->fresh()->is_active
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating shift status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get shifts for schedule creation with enhanced logic.
     */
    public function getShiftsForSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'post_id' => 'nullable|exists:security_posts,id',
            'personnel_count' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $date = Carbon::parse($request->date);
        $dayOfWeek = $date->dayOfWeekIso;
        $isHoliday = $this->isHoliday($date);

        $query = SecurityShift::active();

        if ($isHoliday) {
            $query->where(function($q) {
                $q->where('category', 'holiday')
                  ->orWhere('is_active_on_holidays', true);
            });
        }

        $query->where(function($q) use ($dayOfWeek) {
            $q->where('day_type', 'all_days')
              ->orWhere(function($q2) use ($dayOfWeek) {
                  $q2->where('day_type', 'weekday')
                     ->whereRaw('? between 1 and 5', [$dayOfWeek]);
              })
              ->orWhere(function($q2) use ($dayOfWeek) {
                  $q2->where('day_type', 'weekend')
                     ->whereRaw('? in (6, 7)', [$dayOfWeek]);
              })
              ->orWhere(function($q2) use ($dayOfWeek) {
                  $q2->where('day_type', 'custom')
                     ->whereJsonContains('applicable_days', $dayOfWeek);
              });
        });

        if ($request->filled('personnel_count')) {
            $query->where('required_personnel', '<=', $request->personnel_count);
        }

        if ($request->filled('post_id')) {
            $post = \App\Models\SecurityPost::find($request->post_id);
            if ($post) {
                $query->where(function($q) use ($post) {
                    if ($post->type === 'main_gate') {
                        $q->whereIn('category', ['day', 'night']);
                    }
                });
            }
        }

        $shifts = $query->get()->map(function($shift) use ($date) {
            return [
                'id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code,
                'category' => $shift->category,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'duration_hours' => $shift->duration_hours,
                'required_personnel' => $shift->required_personnel,
                'is_overnight' => $shift->is_overnight,
                'rotation_type' => $shift->rotation_type,
                'break_schedule' => $shift->break_schedule,
                'handover_config' => $shift->handover_config,
                'is_applicable' => $this->checkShiftApplicability($shift, $date),
                'time_range' => $shift->getTimeRange(),
                'breaks_formatted' => $this->formatBreaks($shift->break_schedule)
            ];
        });

        return response()->json([
            'success' => true,
            'shifts' => $shifts,
            'date' => $date->format('Y-m-d'),
            'day_of_week' => $date->format('l'),
            'is_holiday' => $isHoliday,
            'total_shifts' => $shifts->count()
        ]);
    }

    /**
     * Check if date is a holiday
     */
    private function isHoliday(Carbon $date)
    {
        // Implement holiday logic here
        return false;
    }

    /**
     * Check shift applicability for specific date
     */
    private function checkShiftApplicability(SecurityShift $shift, Carbon $date)
    {
        $dayOfWeek = $date->dayOfWeekIso;
        
        switch ($shift->day_type) {
            case 'all_days':
                return true;
            case 'weekday':
                return $dayOfWeek >= 1 && $dayOfWeek <= 5;
            case 'weekend':
                return $dayOfWeek >= 6;
            case 'custom':
                return is_array($shift->applicable_days) && in_array($dayOfWeek, $shift->applicable_days);
            default:
                return false;
        }
    }

    /**
     * Format breaks for display
     */
    private function formatBreaks($breakSchedule)
    {
        if (!$breakSchedule || !isset($breakSchedule['has_break']) || !$breakSchedule['has_break'] || empty($breakSchedule['breaks'])) {
            return 'No breaks';
        }

        $formatted = [];
        foreach ($breakSchedule['breaks'] as $break) {
            $formatted[] = "{$break['name']}: {$break['start_time']} - {$break['end_time']} ({$break['duration_minutes']} min)";
        }

        return implode(', ', $formatted);
    }

    /**
     * Check if shifts are in use (for bulk delete validation)
     */
    public function checkShiftsInUse(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:security_shifts,id'
        ]);

        $usedShifts = SecurityShift::whereIn('id', $request->ids)
            ->where(function($query) {
                $query->has('schedules');
            })->get(['id', 'name', 'code']);

        return response()->json([
            'in_use_count' => $usedShifts->count(),
            'used_shifts' => $usedShifts
        ]);
    }

    /**
     * Get shift recommendations for a post
     */
    public function getRecommendedShifts(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:security_posts,id',
            'date' => 'required|date'
        ]);

        $post = \App\Models\SecurityPost::find($request->post_id);
        $date = Carbon::parse($request->date);

        $commonShifts = SecurityShift::active()
            ->whereHas('schedules', function($query) use ($post) {
                $query->where('security_post_id', $post->id)
                      ->whereDate('assignment_date', '>=', now()->subDays(30));
            })
            ->withCount(['schedules' => function($query) use ($post) {
                $query->where('security_post_id', $post->id);
            }])
            ->orderBy('schedules_count', 'desc')
            ->limit(5)
            ->get();

        $matchingShifts = SecurityShift::active()
            ->where('required_personnel', '<=', $post->max_personnel)
            ->where(function($query) use ($post) {
                switch ($post->type) {
                    case 'main_gate':
                        $query->whereIn('category', ['day', 'night'])
                              ->where('required_personnel', '>=', 2);
                        break;
                    case 'internal_gate':
                        $query->whereIn('category', ['day', 'evening'])
                              ->where('required_personnel', 1);
                        break;
                    case 'patrol_route':
                        $query->whereIn('category', ['day', 'night'])
                              ->where('required_personnel', 1);
                        break;
                }
            })
            ->get();

        return response()->json([
            'success' => true,
            'common_shifts' => $commonShifts,
            'matching_shifts' => $matchingShifts,
            'post' => [
                'id' => $post->id,
                'name' => $post->name,
                'type' => $post->type,
                'max_personnel' => $post->max_personnel
            ]
        ]);
    }

    /**
     * Process shift rotation
     */
    public function processRotation(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|exists:security_shifts,id',
            'date' => 'required|date'
        ]);

        $shift = SecurityShift::find($request->shift_id);
        
        if ($shift->rotation_type !== 'rotating') {
            return response()->json([
                'success' => false,
                'message' => 'This shift is not configured for rotation'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $date = Carbon::parse($request->date);
            $rotationConfig = $shift->rotation_config;
            $sequence = $this->getRotationSequence($rotationConfig['rotation_sequence'] ?? 'morning_evening');
            
            $currentIndex = $rotationConfig['current_sequence_index'] ?? 0;
            $newIndex = ($currentIndex + 1) % count($sequence);
            
            $shift->update([
                'rotation_config->current_sequence_index' => $newIndex,
                'rotation_config->last_rotation_date' => $date->format('Y-m-d')
            ]);

            Log::info('Shift rotation processed', [
                'shift_id' => $shift->id,
                'shift_name' => $shift->name,
                'rotation_date' => $date->format('Y-m-d'),
                'new_sequence_index' => $newIndex,
                'new_shift_type' => $sequence[$newIndex],
                'processed_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shift rotation processed successfully',
                'new_shift_type' => $sequence[$newIndex],
                'next_rotation_date' => $date->addDays($rotationConfig['rotation_days'] ?? 7)->format('Y-m-d')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing shift rotation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing rotation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shift calendar for a period
     */
    public function shiftCalendar(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'shift_id' => 'nullable|exists:security_shifts,id'
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $shifts = SecurityShift::active()->get();

        $calendarData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeekIso;
            $applicableShifts = $shifts->filter(function($shift) use ($dayOfWeek) {
                return $this->checkShiftApplicability($shift, Carbon::now()->setISODate(2024, 1, $dayOfWeek));
            });

            $calendarData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $currentDate->format('l'),
                'is_weekend' => $dayOfWeek >= 6,
                'applicable_shifts' => $applicableShifts->values(),
                'total_applicable' => $applicableShifts->count()
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'calendar_data' => $calendarData,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'total_days' => $startDate->diffInDays($endDate) + 1
            ],
            'total_shifts' => $shifts->count()
        ]);
    }
}