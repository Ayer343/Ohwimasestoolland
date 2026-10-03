<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\BiometricConfig;
use App\Models\BiometricLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;

class BiometricConfigController extends Controller
{
    /**
     * Display biometric configuration dashboard
     */
    public function index(Request $request)
    {
        $config = BiometricConfig::firstOrCreate([], [
            'provider' => 'aws_rekognition', // Default provider
            'confidence_threshold' => 95,
            'liveness_detection' => true,
            'max_attempts' => 3,
            'lockout_duration' => 15, // minutes
            'enabled' => true,
            'settings' => [
                'face_detection' => [
                    'min_face_size' => 50,
                    'max_faces' => 1,
                    'quality_filter' => true
                ],
                'match_criteria' => [
                    'similarity_threshold' => 90,
                    'eye_distance_tolerance' => 0.15,
                    'pose_tolerance' => 15 // degrees
                ],
                'liveness' => [
                    'blink_detection' => true,
                    'head_movement' => true,
                    'challenge_response' => false
                ],
                'image_requirements' => [
                    'min_resolution' => '640x480',
                    'recommended_resolution' => '1280x720',
                    'max_size' => 5242880, // 5MB
                    'formats' => ['jpg', 'jpeg', 'png']
                ]
            ]
        ]);

        // Get statistics
        $stats = $this->getBiometricStatistics();

        // Get recent verification attempts
        $recentLogs = BiometricLog::with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get users with registered faces
        $usersWithFaces = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->whereNotNull('face_reference_photo')
            ->where('face_registered_at', '!=', null)
            ->withCount(['biometricLogs as success_count' => function($q) {
                $q->where('status', 'success');
            }])
            ->withCount(['biometricLogs as fail_count' => function($q) {
                $q->where('status', 'failed');
            }])
            ->orderBy('face_registered_at', 'desc')
            ->paginate(20);

        return view('admin.biometric.index', compact(
            'config',
            'stats',
            'recentLogs',
            'usersWithFaces'
        ));
    }

    /**
     * Update biometric configuration
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:aws_rekognition,azure_face,google_vision,local',
            'confidence_threshold' => 'required|integer|min:50|max:99',
            'liveness_detection' => 'boolean',
            'max_attempts' => 'required|integer|min:1|max:10',
            'lockout_duration' => 'required|integer|min:1|max:120',
            'enabled' => 'boolean',
            'settings' => 'nullable|array',
            'settings.face_detection.min_face_size' => 'nullable|integer|min:20|max:200',
            'settings.face_detection.quality_filter' => 'nullable|boolean',
            'settings.match_criteria.similarity_threshold' => 'nullable|integer|min:60|max:99',
            'settings.liveness.blink_detection' => 'nullable|boolean',
            'settings.liveness.head_movement' => 'nullable|boolean',
            'settings.image_requirements.max_size' => 'nullable|integer|min:1024|max:10485760',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $config = BiometricConfig::first();
            
            if (!$config) {
                $config = new BiometricConfig();
            }

            // Merge existing settings with new ones
            $currentSettings = $config->settings ?? [];
            $newSettings = array_merge($currentSettings, $request->settings ?? []);

            $config->fill([
                'provider' => $request->provider,
                'confidence_threshold' => $request->confidence_threshold,
                'liveness_detection' => $request->boolean('liveness_detection', true),
                'max_attempts' => $request->max_attempts,
                'lockout_duration' => $request->lockout_duration,
                'enabled' => $request->boolean('enabled', true),
                'settings' => $newSettings,
                'updated_by' => auth()->id()
            ]);

            $config->save();

            // Clear cached configuration
            Cache::forget('biometric_config');

            Log::info('Biometric configuration updated', [
                'updated_by' => auth()->id(),
                'provider' => $request->provider,
                'threshold' => $request->confidence_threshold
            ]);

            return redirect()->route('admin.biometric.index')
                ->with('success', 'Biometric configuration updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update biometric config: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update configuration.')
                ->withInput();
        }
    }

    /**
     * Register face for a security personnel
     */
    public function registerFace(Request $request, $userId)
    {
        $user = User::where('type', User::TYPE_SECURITY_PERSONNEL)
            ->findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'face_image' => 'required|image|max:5120|mimes:jpeg,jpg,png',
            'capture_method' => 'required|in:upload,webcam,mobile',
            'device_info' => 'nullable|string|max:500',
            'multiple_angles' => 'nullable|boolean',
            'additional_images' => 'nullable|array|max:5',
            'additional_images.*' => 'image|max:5120|mimes:jpeg,jpg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Process main face image
            $image = $request->file('face_image');
            
            // Validate image quality
            $qualityCheck = $this->validateFaceImageQuality($image);
            if (!$qualityCheck['passed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Face image quality check failed: ' . $qualityCheck['reason']
                ], 400);
            }

            // Detect and analyze face
            $faceAnalysis = $this->analyzeFace($image);
            if (!$faceAnalysis['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Face detection failed: ' . $faceAnalysis['error']
                ], 400);
            }

            // Process additional angles if provided
            $additionalAngles = [];
            if ($request->hasFile('additional_images')) {
                foreach ($request->file('additional_images') as $angleImage) {
                    $angleAnalysis = $this->analyzeFace($angleImage);
                    if ($angleAnalysis['success']) {
                        $path = $this->storeFaceImage($angleImage, $user, 'angle');
                        $additionalAngles[] = [
                            'path' => $path,
                            'analysis' => $angleAnalysis['data']
                        ];
                    }
                }
            }

            // Store main face image
            $mainImagePath = $this->storeFaceImage($image, $user, 'reference');

            // Extract face features/template
            $faceTemplate = $this->extractFaceFeatures($image, $faceAnalysis['data']);

            // Store face data
            $faceData = [
                'reference_image' => $mainImagePath,
                'face_template' => $faceTemplate,
                'face_analysis' => $faceAnalysis['data'],
                'additional_angles' => $additionalAngles,
                'capture_method' => $request->capture_method,
                'device_info' => $request->device_info,
                'captured_at' => now()->toDateTimeString(),
                'captured_by' => auth()->id(),
                'quality_score' => $qualityCheck['score'],
                'face_features' => [
                    'face_id' => $faceAnalysis['data']['face_id'] ?? null,
                    'confidence' => $faceAnalysis['data']['confidence'] ?? 0,
                    'landmarks' => $faceAnalysis['data']['landmarks'] ?? [],
                    'pose' => $faceAnalysis['data']['pose'] ?? [],
                    'quality' => $faceAnalysis['data']['quality'] ?? []
                ]
            ];

            // Update user with face data
            $user->update([
                'face_reference_photo' => $mainImagePath,
                'face_data' => json_encode($faceData),
                'face_registered_at' => now(),
                'face_registered_by' => auth()->id(),
                'face_version' => ($user->face_version ?? 0) + 1
            ]);

            // Log biometric registration
            BiometricLog::create([
                'user_id' => $user->id,
                'action' => 'registration',
                'status' => 'success',
                'confidence' => $faceAnalysis['data']['confidence'] ?? null,
                'metadata' => json_encode([
                    'capture_method' => $request->capture_method,
                    'quality_score' => $qualityCheck['score'],
                    'additional_angles' => count($additionalAngles),
                    'registered_by' => auth()->id()
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            // Clear user cache
            Cache::forget("user_{$user->id}_face_data");

            return response()->json([
                'success' => true,
                'message' => 'Face registered successfully.',
                'data' => [
                    'face_id' => $faceAnalysis['data']['face_id'] ?? null,
                    'quality_score' => $qualityCheck['score'],
                    'registered_at' => now()->toDateTimeString(),
                    'face_version' => $user->face_version
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Face registration failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Face registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify face against registered reference
     */
    public function verifyFace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'face_image' => 'required|image|max:5120|mimes:jpeg,jpg,png',
            'verification_context' => 'nullable|in:checkin,checkout,break_start,break_end,manual',
            'location' => 'nullable|array',
            'location.lat' => 'required_with:location|numeric',
            'location.lng' => 'required_with:location|numeric',
            'device_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        // Check if user has registered face
        if (!$user->face_reference_photo || !$user->face_data) {
            return response()->json([
                'success' => false,
                'message' => 'No face reference found for this user.'
            ], 400);
        }

        // Check if user is locked out
        if ($this->isUserLockedOut($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.',
                'locked_until' => $user->biometric_lockout_until
            ], 429);
        }

        try {
            $image = $request->file('face_image');
            $config = BiometricConfig::first();

            // Perform face verification
            $verificationResult = $this->performFaceVerification(
                $image,
                $user->face_data,
                $config
            );

            // Log the attempt
            BiometricLog::create([
                'user_id' => $user->id,
                'action' => 'verification',
                'context' => $request->verification_context,
                'status' => $verificationResult['success'] ? 'success' : 'failed',
                'confidence' => $verificationResult['confidence'] ?? null,
                'metadata' => json_encode([
                    'threshold' => $config->confidence_threshold,
                    'verification_time_ms' => $verificationResult['processing_time'] ?? null,
                    'face_matched' => $verificationResult['face_matched'] ?? false,
                    'liveness_passed' => $verificationResult['liveness_passed'] ?? false,
                    'device_id' => $request->device_id,
                    'location' => $request->location
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Update attempt counters
            $this->updateAttemptCounters($user, $verificationResult['success']);

            if ($verificationResult['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Face verified successfully.',
                    'data' => [
                        'confidence' => $verificationResult['confidence'],
                        'match_score' => $verificationResult['match_score'],
                        'processing_time_ms' => $verificationResult['processing_time']
                    ]
                ]);
            } else {
                $remainingAttempts = $this->getRemainingAttempts($user);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Face verification failed. ' . $remainingAttempts . ' attempts remaining.',
                    'remaining_attempts' => $remainingAttempts,
                    'reason' => $verificationResult['reason'] ?? 'Unknown error'
                ], 401);
            }

        } catch (\Exception $e) {
            Log::error('Face verification error: ' . $e->getMessage(), [
                'user_id' => $request->user_id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed due to system error.'
            ], 500);
        }
    }

    /**
     * Test biometric configuration
     */
    public function testConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'test_image' => 'required|image|max:5120',
            'test_type' => 'required|in:detection,quality,liveness',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $image = $request->file('test_image');
            $results = [];

            switch ($request->test_type) {
                case 'detection':
                    $results = $this->analyzeFace($image);
                    break;
                    
                case 'quality':
                    $results = $this->validateFaceImageQuality($image);
                    break;
                    
                case 'liveness':
                    $results = $this->testLivenessDetection($image);
                    break;
            }

            return response()->json([
                'success' => true,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get biometric statistics
     */
    private function getBiometricStatistics()
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $thisWeek = $now->copy()->startOfWeek();
        $thisMonth = $now->copy()->startOfMonth();

        return [
            'total_registered' => User::whereNotNull('face_reference_photo')->count(),
            'registered_today' => User::whereDate('face_registered_at', $today)->count(),
            'registered_this_week' => User::where('face_registered_at', '>=', $thisWeek)->count(),
            'registered_this_month' => User::where('face_registered_at', '>=', $thisMonth)->count(),
            
            'verifications' => [
                'today' => [
                    'total' => BiometricLog::whereDate('created_at', $today)->count(),
                    'success' => BiometricLog::whereDate('created_at', $today)->where('status', 'success')->count(),
                    'failed' => BiometricLog::whereDate('created_at', $today)->where('status', 'failed')->count(),
                ],
                'this_week' => [
                    'total' => BiometricLog::where('created_at', '>=', $thisWeek)->count(),
                    'success_rate' => $this->calculateSuccessRate($thisWeek, $now),
                ],
                'avg_confidence' => BiometricLog::where('status', 'success')
                    ->whereNotNull('confidence')
                    ->avg('confidence'),
            ],
            
            'performance' => [
                'avg_processing_time_ms' => 150, // This would come from actual metrics
                'p95_processing_time_ms' => 250,
                'p99_processing_time_ms' => 350,
            ],
            
            'lockouts' => [
                'currently_locked' => User::where('biometric_lockout_until', '>', $now)->count(),
                'today_lockouts' => BiometricLog::whereDate('created_at', $today)
                    ->where('metadata', 'like', '%lockout%')
                    ->count(),
            ]
        ];
    }

    /**
     * Calculate success rate for biometric verifications
     */
    private function calculateSuccessRate($startDate, $endDate)
    {
        $total = BiometricLog::whereBetween('created_at', [$startDate, $endDate])->count();
        if ($total == 0) return 100;
        
        $success = BiometricLog::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'success')
            ->count();
        
        return round(($success / $total) * 100, 1);
    }

    /**
     * Validate face image quality
     */
    private function validateFaceImageQuality($image)
    {
        // This would integrate with face recognition service
        // For now, return simulated result
        return [
            'passed' => true,
            'score' => 85,
            'reason' => null,
            'metrics' => [
                'brightness' => 70,
                'contrast' => 65,
                'sharpness' => 80,
                'face_size' => 120,
                'face_angle' => 5
            ]
        ];
    }

    /**
     * Analyze face in image
     */
    private function analyzeFace($image)
    {
        // This would call face recognition API
        // Simulated response
        return [
            'success' => true,
            'data' => [
                'face_id' => 'face_' . uniqid(),
                'confidence' => 98.5,
                'landmarks' => [
                    'left_eye' => [120, 150],
                    'right_eye' => [180, 150],
                    'nose' => [150, 180],
                    'mouth_left' => [130, 210],
                    'mouth_right' => [170, 210]
                ],
                'pose' => [
                    'pitch' => 2.5,
                    'roll' => 1.2,
                    'yaw' => 3.1
                ],
                'quality' => [
                    'brightness' => 75,
                    'sharpness' => 82,
                    'contrast' => 68
                ]
            ]
        ];
    }

    /**
     * Extract face features/template
     */
    private function extractFaceFeatures($image, $analysis)
    {
        // This would generate a face template/embedding
        // For now, return dummy data
        return [
            'template_id' => 'temp_' . uniqid(),
            'feature_vector_length' => 128,
            'algorithm' => 'deepface_v3',
            'created_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Store face image
     */
    private function storeFaceImage($image, $user, $type)
    {
        $path = 'faces/' . $user->id . '/' . $type . '/' . date('Y/m/d/');
        $filename = $type . '_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        
        // Process and optimize image
        $img = Image::make($image);
        
        // Resize if too large while maintaining aspect ratio
        if ($img->width() > 1024) {
            $img->resize(1024, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }
        
        // Convert to JPEG for consistency
        $img->encode('jpg', 85);
        
        // Store
        Storage::disk('public')->put($path . $filename, $img);
        
        return $path . $filename;
    }

    /**
     * Perform face verification
     */
    private function performFaceVerification($image, $faceData, $config)
    {
        // This would call face recognition API
        // Simulated verification
        $startTime = microtime(true);
        
        // Simulate processing
        usleep(100000); // 100ms
        
        $confidence = rand(85, 99);
        $threshold = $config->confidence_threshold ?? 95;
        
        return [
            'success' => $confidence >= $threshold,
            'confidence' => $confidence,
            'match_score' => $confidence,
            'face_matched' => $confidence >= 80,
            'liveness_passed' => true,
            'processing_time' => round((microtime(true) - $startTime) * 1000, 2),
            'reason' => $confidence >= $threshold ? null : 'Low confidence match'
        ];
    }

    /**
     * Check if user is locked out
     */
    private function isUserLockedOut($user)
    {
        return $user->biometric_lockout_until && 
               Carbon::parse($user->biometric_lockout_until)->isFuture();
    }

    /**
     * Update attempt counters
     */
    private function updateAttemptCounters($user, $success)
    {
        if ($success) {
            $user->update([
                'biometric_failed_attempts' => 0,
                'biometric_lockout_until' => null
            ]);
        } else {
            $failedAttempts = ($user->biometric_failed_attempts ?? 0) + 1;
            $config = BiometricConfig::first();
            
            $updateData = ['biometric_failed_attempts' => $failedAttempts];
            
            if ($failedAttempts >= ($config->max_attempts ?? 3)) {
                $updateData['biometric_lockout_until'] = now()->addMinutes(
                    $config->lockout_duration ?? 15
                );
            }
            
            $user->update($updateData);
        }
    }

    /**
     * Get remaining attempts
     */
    private function getRemainingAttempts($user)
    {
        $config = BiometricConfig::first();
        $maxAttempts = $config->max_attempts ?? 3;
        $failedAttempts = $user->biometric_failed_attempts ?? 0;
        
        return max(0, $maxAttempts - $failedAttempts);
    }

    /**
     * Test liveness detection
     */
    private function testLivenessDetection($image)
    {
        // Simulate liveness check
        return [
            'passed' => true,
            'confidence' => 92,
            'checks' => [
                'blink_detected' => true,
                'head_movement' => true,
                'texture_analysis' => 'real_face',
                'depth_analysis' => 'valid'
            ]
        ];
    }
}