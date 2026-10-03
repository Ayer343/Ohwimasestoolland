<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use App\Models\PostQrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth; 

class PostQrCodeController extends Controller
{
    /**
     * Display QR codes for a specific post
     */
    public function index(Request $request, $postId)
    {
        $post = SecurityPost::findOrFail($postId);
        
        $qrCodes = PostQrCode::where('post_id', $postId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('admin.posts.qr-codes.index', compact('post', 'qrCodes'));
    }

    /**
     * Show form to generate new QR code
     */
    public function create($postId)
    {
        $post = SecurityPost::findOrFail($postId);
        
        return view('admin.posts.qr-codes.create', compact('post'));
    }

/**
 * Generate and store a new QR code
 */
public function store(Request $request, $postId)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:100',
        'description' => 'nullable|string|max:255',
        'code_type' => 'required|in:static,one_time,time_based',
        'expires_at' => 'nullable|date|after:now',
        'max_uses' => 'nullable|integer|min:1|max:1000',
        'size' => 'nullable|integer|min:100|max:1000',
        'foreground_color' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
        'background_color' => 'nullable|string|regex:/^#[a-fA-F0-9]{6}$/',
        'style' => 'nullable|in:square,dot,round',
        'format' => 'nullable|in:png,svg,eps',
        'static_expiry_override' => 'nullable|in:0,1',
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    try {
        DB::beginTransaction();

        $post = SecurityPost::findOrFail($postId);

        // Generate unique code
        $code = $this->generateUniqueQrCode();

        // Handle expires_at based on code_type
        $expiresAt = null;
        
        if ($request->code_type === 'static') {
            $expiresAt = null;
        } elseif ($request->code_type === 'time_based') {
            $expiresAt = $request->filled('expires_at') ? Carbon::parse($request->expires_at) : null;
        } elseif ($request->code_type === 'one_time') {
            $expiresAt = null;
        }

        // Handle max_uses based on code_type
        $maxUses = null;
        if ($request->code_type === 'one_time') {
            $maxUses = 1;
        } elseif ($request->code_type === 'time_based') {
            $maxUses = $request->filled('max_uses') ? $request->max_uses : null;
        } elseif ($request->code_type === 'static') {
            $maxUses = $request->filled('max_uses') ? $request->max_uses : null;
        }

        // Prepare metadata array
        $metadata = [
            'generated_at' => now()->toDateTimeString(),
            'generated_by' => auth()->user()->name,
            'generated_by_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'config' => [
                'size' => $request->size ?? 300,
                'foreground_color' => $request->foreground_color ?? '#000000',
                'background_color' => $request->background_color ?? '#ffffff',
                'style' => $request->style ?? 'square',
                'format' => 'png',
            ]
        ];

        // Prepare QR code data
        $qrData = [
            'post_id' => $postId,
            'name' => $request->name,
            'description' => $request->description,
            'code' => $code,
            'code_type' => $request->code_type,
            'expires_at' => $expiresAt,
            'max_uses' => $maxUses,
            'uses_count' => 0,
            'is_active' => true,
            'created_by' => auth()->id(),
            'metadata' => json_encode($metadata),
        ];

        $qrCode = PostQrCode::create($qrData);

        // Generate QR code image
        $qrImage = $this->generateQrCodeImage($qrCode, $request);

        // Store QR code image
        $path = $this->storeQrCodeImage($qrCode, $qrImage);

        // Update record with image path
        $qrCode->update(['image_path' => $path]);

        DB::commit();

        $successMessage = 'QR code generated successfully.';

        return redirect()->route('admin.security-posts.qr-codes.show', [
            'securityPost' => $postId,
            'qrCode' => $qrCode->id
        ])->with('success', $successMessage);

    } catch (\Exception $e) {
        DB::rollBack();

        return redirect()->back()
            ->with('error', 'Failed to generate QR code: ' . $e->getMessage())
            ->withInput();
    }
}

    /**
     * Display QR code details
     */
    public function show($postId, $qrCodeId)
    {
        $post = SecurityPost::findOrFail($postId);
        $qrCode = PostQrCode::where('post_id', $postId)
            ->with(['creator'])
            ->findOrFail($qrCodeId);

        // Get usage statistics with error handling
        $usageStats = null;
        
        try {
            // Check if verification_logs table exists and has the correct columns
            $verificationLogsTable = DB::getSchemaBuilder()->hasTable('verification_logs');
            
            if ($verificationLogsTable) {
                $columns = DB::getSchemaBuilder()->getColumnListing('verification_logs');
                
                // Build query based on available columns
                $query = DB::table('verification_logs');
                
                // Check which column name is used for the QR code reference
                if (in_array('qr_code_id', $columns)) {
                    $query->where('qr_code_id', $qrCode->id);
                } elseif (in_array('verification_code', $columns)) {
                    $query->where('verification_code', $qrCode->code);
                } elseif (in_array('code', $columns)) {
                    $query->where('code', $qrCode->code);
                } elseif (in_array('qr_code', $columns)) {
                    $query->where('qr_code', $qrCode->code);
                } else {
                    // No matching column found, set default stats
                    $usageStats = (object) [
                        'total_uses' => 0,
                        'unique_users' => 0,
                        'last_used_at' => null
                    ];
                    Log::warning('No matching column found in verification_logs table for QR code reference');
                }
                
                // If we have a valid query, execute it
                if ($usageStats === null) {
                    $usageStats = $query->select(
                        DB::raw('COUNT(*) as total_uses'),
                        DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                        DB::raw('MAX(created_at) as last_used_at')
                    )->first();
                }
            } else {
                // Table doesn't exist, set default stats
                $usageStats = (object) [
                    'total_uses' => 0,
                    'unique_users' => 0,
                    'last_used_at' => null
                ];
                Log::info('verification_logs table does not exist yet');
            }
        } catch (\Exception $e) {
            // Log the error but don't break the page
            Log::error('Error fetching QR code usage statistics: ' . $e->getMessage(), [
                'qr_code_id' => $qrCodeId,
                'qr_code' => $qrCode->code
            ]);
            
            // Set default stats
            $usageStats = (object) [
                'total_uses' => 0,
                'unique_users' => 0,
                'last_used_at' => null
            ];
        }

        // Ensure usageStats is always an object with required properties
        if (!$usageStats) {
            $usageStats = (object) [
                'total_uses' => 0,
                'unique_users' => 0,
                'last_used_at' => null
            ];
        }

        // Format display values
        $expiresAt = $qrCode->expires_at ? $qrCode->expires_at->format('Y-m-d H:i:s') : 'Never';
        $maxUses = $qrCode->max_uses ?? 'Unlimited';
        $isPermanent = $qrCode->code_type === 'static' && $qrCode->expires_at === null;

        return view('admin.posts.qr-codes.show', compact(
            'post', 
            'qrCode', 
            'usageStats',
            'expiresAt',
            'maxUses',
            'isPermanent'
        ));
    }

    /**
     * Display trashed QR codes for a specific post
     */
    public function trash($securityPost)
    {
        $post = SecurityPost::findOrFail($securityPost);
        
        $trashedQrCodes = PostQrCode::where('post_id', $securityPost)
            ->onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->paginate(20);
        
        return view('admin.posts.qr-codes.trash', compact('post', 'trashedQrCodes'));
    }

    /**
     * Restore a trashed QR code
     */
    public function restore($postId, $qrCodeId)
    {
        try {
            DB::beginTransaction();

            $qrCode = PostQrCode::where('post_id', $postId)
                ->onlyTrashed()
                ->findOrFail($qrCodeId);
            
            // Store restoration info in metadata
            $metadata = json_decode($qrCode->metadata, true) ?? [];
            $metadata['restorations'] = $metadata['restorations'] ?? [];
            $metadata['restorations'][] = [
                'restored_at' => now()->toDateTimeString(),
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()->name,
            ];
            
            $qrCode->metadata = json_encode($metadata);
            $qrCode->save();

            // Restore the QR code
            $qrCode->restore();

            DB::commit();

            Log::info('QR code restored from trash', [
                'qr_code_id' => $qrCode->id,
                'post_id' => $postId,
                'restored_by' => auth()->id()
            ]);

            return redirect()->route('admin.security-posts.qr-codes.show', [
                'securityPost' => $postId,
                'qrCode' => $qrCode->id
            ])->with('success', 'QR code restored successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to restore QR code: ' . $e->getMessage(), [
                'qr_code_id' => $qrCodeId,
                'post_id' => $postId,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to restore QR code.');
        }
    }

    public function forceDelete($postId, $qrCodeId)
{
    try {
        DB::beginTransaction();

        // First, check if it exists at all (even active)
        $exists = PostQrCode::where('post_id', $postId)
            ->withTrashed()  // Include both active and trashed
            ->where('id', $qrCodeId)
            ->exists();
        
        if (!$exists) {
            return redirect()->back()
                ->with('error', 'QR code not found. It may have been already deleted.');
        }
        
        // Now try to find it in trash
        $qrCode = PostQrCode::where('post_id', $postId)
            ->onlyTrashed()
            ->find($qrCodeId);
        
        if (!$qrCode) {
            // It exists but is active (not in trash)
            return redirect()->back()
                ->with('error', 'Cannot force delete. QR code is active. Please move to trash first.');
        }
        
        // Rest of your code...
        // Delete image file
        if ($qrCode->image_path && file_exists(storage_path('app/public/' . $qrCode->image_path))) {
            unlink(storage_path('app/public/' . $qrCode->image_path));
        }

        // Log the permanent deletion
        Log::info('QR code permanently deleted', [
            'qr_code_id' => $qrCode->id,
            'post_id' => $postId,
            'code' => $qrCode->code,
            'name' => $qrCode->name,
            'deleted_by' => auth()->id(),
            'original_deleted_at' => $qrCode->deleted_at
        ]);

        // Permanently delete the QR code
        $qrCode->forceDelete();

        DB::commit();

        return redirect()->route('admin.security-posts.qr-codes.trash.index', [
            'securityPost' => $postId
        ])->with('success', 'QR code permanently deleted.');

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to permanently delete QR code: ' . $e->getMessage(), [
            'qr_code_id' => $qrCodeId,
            'post_id' => $postId,
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()
            ->with('error', 'Failed to permanently delete QR code: ' . $e->getMessage());
    }
}

    /**
     * Bulk restore QR codes from trash
     */
    public function bulkRestore(Request $request, $postId)
    {
        $validator = Validator::make($request->all(), [
            'qr_code_ids' => 'required|array',
            'qr_code_ids.*' => 'exists:post_qr_codes,id',
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

            foreach ($request->qr_code_ids as $qrCodeId) {
                try {
                    $qrCode = PostQrCode::where('post_id', $postId)
                        ->onlyTrashed()
                        ->find($qrCodeId);
                    
                    if (!$qrCode) {
                        $failedRestores[] = [
                            'id' => $qrCodeId,
                            'reason' => 'QR code not found in trash'
                        ];
                        continue;
                    }

                    // Store restoration info in metadata
                    $metadata = json_decode($qrCode->metadata, true) ?? [];
                    $metadata['restorations'] = $metadata['restorations'] ?? [];
                    $metadata['restorations'][] = [
                        'restored_at' => now()->toDateTimeString(),
                        'restored_by' => auth()->id(),
                        'restored_by_name' => auth()->user()->name,
                    ];
                    
                    $qrCode->metadata = json_encode($metadata);
                    $qrCode->save();

                    $qrCode->restore();
                    $restoredCount++;

                    Log::info('QR code bulk restored', [
                        'qr_code_id' => $qrCodeId,
                        'post_id' => $postId,
                        'restored_by' => auth()->id()
                    ]);

                } catch (\Exception $e) {
                    $failedRestores[] = [
                        'id' => $qrCodeId,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$restoredCount} QR code(s) restored successfully.",
                'restored_count' => $restoredCount,
                'failed_count' => count($failedRestores),
                'failed_restores' => $failedRestores
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to bulk restore QR codes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'qr_code_ids' => $request->qr_code_ids
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk restore QR codes.'
            ], 500);
        }
    }

    /**
     * Empty trash (permanently delete all trashed QR codes for a post)
     */
    public function emptyTrash($postId)
    {
        try {
            DB::beginTransaction();

            $post = SecurityPost::findOrFail($postId);
            
            $trashedQrCodes = PostQrCode::where('post_id', $postId)
                ->onlyTrashed()
                ->get();

            $deletedCount = 0;
            $failedDeletes = [];

            foreach ($trashedQrCodes as $qrCode) {
                try {
                    // Delete image file
                    if ($qrCode->image_path && file_exists(storage_path('app/public/' . $qrCode->image_path))) {
                        unlink(storage_path('app/public/' . $qrCode->image_path));
                    }

                    $qrCode->forceDelete();
                    $deletedCount++;

                } catch (\Exception $e) {
                    $failedDeletes[] = [
                        'id' => $qrCode->id,
                        'code' => $qrCode->code,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            Log::info('QR code trash emptied', [
                'post_id' => $postId,
                'deleted_count' => $deletedCount,
                'failed_count' => count($failedDeletes),
                'deleted_by' => auth()->id()
            ]);

            if (count($failedDeletes) > 0) {
                return redirect()->route('admin.security-posts.qr-codes.trash', [
                    'securityPost' => $postId
                ])->with('warning', "{$deletedCount} QR codes deleted. " . count($failedDeletes) . " could not be deleted.");
            }

            return redirect()->route('admin.security-posts.qr-codes.trash', [
                'securityPost' => $postId
            ])->with('success', "{$deletedCount} QR codes permanently deleted.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to empty QR code trash: ' . $e->getMessage(), [
                'post_id' => $postId,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to empty trash.');
        }
    }

    /**
 * Download QR code image with instructions
 */
public function download($postId, $qrCodeId)
{
    $qrCode = PostQrCode::where('post_id', $postId)->findOrFail($qrCodeId);
    $post = SecurityPost::findOrFail($postId);

    if (!$qrCode->image_path || !file_exists(storage_path('app/public/' . $qrCode->image_path))) {
        // Regenerate if file missing
        $qrImage = $this->generateQrCodeImage($qrCode);
        $path = $this->storeQrCodeImage($qrCode, $qrImage);
        $qrCode->update(['image_path' => $path]);
    }

    // Check if user wants PDF or PNG
    $format = request()->get('format', 'png');
    
    if ($format === 'pdf') {
        return $this->downloadQrCodeAsPdf($qrCode, $post);
    } else {
        return $this->downloadQrCodeAsPng($qrCode, $post);
    }
}

/**
 * Download QR code as PNG with instructions overlay
 */
private function downloadQrCodeAsPng($qrCode, $post)
{
    // Path to QR code image
    $qrImagePath = storage_path('app/public/' . $qrCode->image_path);
    
    // Create an image from the QR code
    $qrImage = imagecreatefrompng($qrImagePath);
    
    // Get QR code dimensions
    $qrWidth = imagesx($qrImage);
    $qrHeight = imagesy($qrImage);
    
    // Calculate new canvas size (QR code + instructions area)
    $instructionsHeight = 400; // Height for instructions
    $canvasWidth = max(800, $qrWidth + 100); // Minimum width 800px
    $canvasHeight = $qrHeight + $instructionsHeight + 50; // QR + instructions + padding
    
    // Create a new true color image
    $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
    
    // Set colors
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 0, 0, 0);
    $primaryColor = imagecolorallocate($canvas, 79, 70, 229); // Indigo
    $successColor = imagecolorallocate($canvas, 16, 185, 129); // Green
    $warningColor = imagecolorallocate($canvas, 245, 158, 11); // Orange
    $dangerColor = imagecolorallocate($canvas, 239, 68, 68); // Red
    $grayLight = imagecolorallocate($canvas, 243, 244, 246);
    $grayText = imagecolorallocate($canvas, 75, 85, 99);
    $borderColor = imagecolorallocate($canvas, 209, 213, 219);
    
    // Fill background with white
    imagefill($canvas, 0, 0, $white);
    
    // Draw border
    imagerectangle($canvas, 0, 0, $canvasWidth - 1, $canvasHeight - 1, $borderColor);
    
    // Calculate position to center QR code horizontally
    $qrX = ($canvasWidth - $qrWidth) / 2;
    $qrY = 30; // 30px from top
    
    // Copy QR code onto canvas
    imagecopy($canvas, $qrImage, $qrX, $qrY, 0, 0, $qrWidth, $qrHeight);
    
    // Add title
    $this->addCenteredText($canvas, 28, $primaryColor, 'ATTENDANCE QR CODE', $canvasWidth / 2, $qrY + $qrHeight + 20);
    
    // Add post information
    $this->addCenteredText($canvas, 18, $black, $post->name . ' (' . $post->code . ')', $canvasWidth / 2, $qrY + $qrHeight + 50);
    
    // Add instructions section background
    $instructionsY = $qrY + $qrHeight + 80;
    imagefilledrectangle($canvas, 20, $instructionsY, $canvasWidth - 20, $instructionsY + 280, $grayLight);
    
    // Add "INSTRUCTIONS FOR SECURITY PERSONNEL" header
    $this->addText($canvas, 16, $primaryColor, '📋 INSTRUCTIONS FOR SECURITY PERSONNEL', 40, $instructionsY + 30);
    
    // Check-in instructions
    $this->addText($canvas, 14, $successColor, '✅ CHECK-IN PROCEDURE:', 40, $instructionsY + 60);
    $this->addText($canvas, 12, $black, '1. Open attendance app and select "CHECK-IN" mode', 60, $instructionsY + 80);
    $this->addText($canvas, 12, $black, '2. Scan this QR code when personnel arrive', 60, $instructionsY + 100);
    $this->addText($canvas, 12, $black, '3. Verify "CHECK-IN SUCCESSFUL" message appears', 60, $instructionsY + 120);
    
    // Check-out instructions
    $this->addText($canvas, 14, $warningColor, '⬆️ CHECK-OUT PROCEDURE:', 40, $instructionsY + 150);
    $this->addText($canvas, 12, $black, '1. Switch app to "CHECK-OUT" mode', 60, $instructionsY + 170);
    $this->addText($canvas, 12, $black, '2. Scan same QR code when personnel are leaving', 60, $instructionsY + 190);
    $this->addText($canvas, 12, $black, '3. Verify "CHECK-OUT SUCCESSFUL" message appears', 60, $instructionsY + 210);
    
    // Important notes
    $this->addText($canvas, 14, $dangerColor, '⚠️ IMPORTANT NOTES:', 40, $instructionsY + 240);
    $this->addText($canvas, 11, $grayText, '• Only scan at ' . $post->name . ' • Do not share this QR code • Contact supervisor if errors appear', 60, $instructionsY + 265);
    
    // Add validity information
    $validityY = $instructionsY + 300;
    $isPermanent = $qrCode->code_type === 'static' && $qrCode->expires_at === null;
    
    if ($isPermanent) {
        $this->addCenteredText($canvas, 12, $successColor, '✓ This is a PERMANENT QR code - Never expires', $canvasWidth / 2, $validityY);
    } elseif ($qrCode->expires_at) {
        $expiresAt = Carbon::parse($qrCode->expires_at);
        $expired = $expiresAt->isPast();
        $color = $expired ? $dangerColor : $warningColor;
        $status = $expired ? 'EXPIRED - DO NOT USE' : 'Valid until: ' . $expiresAt->format('d M Y H:i');
        $this->addCenteredText($canvas, 12, $color, '⏰ ' . $status, $canvasWidth / 2, $validityY);
    }
    
    // Add footer
    $this->addCenteredText($canvas, 10, $grayText, 'QR Code: ' . $qrCode->code . ' | Generated: ' . $qrCode->created_at->format('Y-m-d H:i'), $canvasWidth / 2, $canvasHeight - 20);
    
    // Start output buffering
    ob_start();
    imagepng($canvas);
    $imageData = ob_get_clean();
    
    // Clean up
    imagedestroy($qrImage);
    imagedestroy($canvas);
    
    // Generate filename
    $fileName = 'attendance_qr_' . $post->code . '_' . date('Y-m-d') . '.png';
    
    // Return response
    return response($imageData)
        ->header('Content-Type', 'image/png')
        ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
}

/**
 * Helper function to add centered text to image
 */
private function addCenteredText($image, $fontSize, $color, $text, $centerX, $y)
{
    // Approximate width based on font size (this is a simple approximation)
    $textWidth = strlen($text) * ($fontSize * 0.6);
    $x = $centerX - ($textWidth / 2);
    
    $this->addText($image, $fontSize, $color, $text, $x, $y);
}

/**
 * Helper function to add text to image using built-in GD functions
 */
private function addText($image, $fontSize, $color, $text, $x, $y)
{
    // Use built-in imagestring for simplicity (limited fonts)
    // For better fonts, you could use imagettftext with a TTF file
    
    // Convert color resource to array for imagestring
    $lines = explode("\n", wordwrap($text, 60, "\n"));
    $lineHeight = $fontSize + 4;
    
    foreach ($lines as $index => $line) {
        // imagestring uses pixel size 1-5, so we'll map our font sizes
        $gdFontSize = $this->mapFontSize($fontSize);
        imagestring($image, $gdFontSize, (int)$x, (int)($y + ($index * $lineHeight)), $line, $color);
    }
}

/**
 * Map our font sizes to GD's built-in font sizes (1-5)
 */
private function mapFontSize($size)
{
    if ($size <= 10) return 1;
    if ($size <= 12) return 2;
    if ($size <= 14) return 3;
    if ($size <= 18) return 4;
    return 5;
}

/**
 * Download QR code as PDF with instructions
 */
private function downloadQrCodeAsPdf($qrCode, $post)
{
    // This requires a PDF library like dompdf or TCPDF
    // For this example, I'll provide HTML that can be converted to PDF
    
    $html = view('admin.posts.qr-codes.print-instructions', compact('qrCode', 'post'))->render();
    
    // You can use barryvdh/laravel-dompdf package for PDF generation
    $pdf = \PDF::loadHTML($html);
    
    $fileName = 'attendance_qr_' . $post->code . '_' . date('Y-m-d') . '.pdf';
    
    return $pdf->download($fileName);
}

    /**
     * Update QR code status (activate/deactivate)
     */
    public function updateStatus(Request $request, $postId, $qrCodeId)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
            'reason' => 'required_if:is_active,false|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $qrCode = PostQrCode::where('post_id', $postId)->findOrFail($qrCodeId);
            
            $metadata = json_decode($qrCode->metadata, true) ?? [];
            $metadata['status_changes'] = $metadata['status_changes'] ?? [];
            $metadata['status_changes'][] = [
                'old_status' => $qrCode->is_active,
                'new_status' => $request->is_active,
                'changed_at' => now()->toDateTimeString(),
                'changed_by' => auth()->id(),
                'changed_by_name' => auth()->user()->name,
                'reason' => $request->reason
            ];
            
            $qrCode->update([
                'is_active' => $request->is_active,
                'metadata' => json_encode($metadata)
            ]);

            Log::info('QR code status updated', [
                'qr_code_id' => $qrCode->id,
                'is_active' => $request->is_active,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR code status updated successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update QR code status: ' . $e->getMessage(), [
                'qr_code_id' => $qrCodeId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update QR code status.'
            ], 500);
        }
    }

    /**
 * Regenerate QR code (with new code)
 */
public function regenerate(Request $request, $postId, $qrCodeId)
{
    try {
        DB::beginTransaction();

        $qrCode = PostQrCode::where('post_id', $postId)->findOrFail($qrCodeId);
        
        // Generate new unique code
        $newCode = $this->generateUniqueQrCode();
        
        // FIXED: Safely handle metadata - leveraging the model's array cast
        // No need for json_decode() anymore because of $casts in model!
        $metadata = $qrCode->metadata ?? []; // This is now automatically an array
        
        // Ensure it's an array (safety check)
        if (!is_array($metadata)) {
            $metadata = [];
        }
        
        // Initialize previous_codes array if it doesn't exist
        if (!isset($metadata['previous_codes']) || !is_array($metadata['previous_codes'])) {
            $metadata['previous_codes'] = [];
        }
        
        // Add current code to history before changing it
        $metadata['previous_codes'][] = [
            'code' => $qrCode->code,
            'regenerated_at' => now()->toDateTimeString(),
            'regenerated_by' => auth()->id(),
            'regenerated_by_name' => auth()->user()->name ?? 'System',
            'reason' => $request->input('reason', 'Manual regeneration'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];
        
        // Keep only last 50 regenerations to prevent metadata bloat
        if (count($metadata['previous_codes']) > 50) {
            $metadata['previous_codes'] = array_slice($metadata['previous_codes'], -50);
        }
        
        // Add regeneration event to metadata
        $metadata['last_regeneration'] = [
            'old_code' => $qrCode->code,
            'new_code' => $newCode,
            'regenerated_at' => now()->toDateTimeString(),
            'regenerated_by' => auth()->id(),
            'regenerated_by_name' => auth()->user()->name ?? 'System',
            'reason' => $request->input('reason', 'Manual regeneration')
        ];
        
        // Update regeneration count
        $metadata['regeneration_count'] = ($metadata['regeneration_count'] ?? 0) + 1;
        
        // FIXED: Update QR code - metadata will be auto-encoded by model cast
        $qrCode->update([
            'code' => $newCode,
            'uses_count' => 0, // Reset usage count for new code
            'metadata' => $metadata, // Laravel will auto-encode to JSON
            // Optionally reset expiration if needed? Uncomment if desired:
            // 'expires_at' => $qrCode->code_type === 'time_based' ? now()->addDays(30) : $qrCode->expires_at,
        ]);

        // Generate new image with updated code
        $qrImage = $this->generateQrCodeImage($qrCode, $request);
        $path = $this->storeQrCodeImage($qrCode, $qrImage);
        
        // Update image path
        $qrCode->update(['image_path' => $path]);

        DB::commit();

        // Clear any cached data for this QR code
        $this->clearQrCodeCache($qrCode);

        Log::info('QR code regenerated successfully', [
            'qr_code_id' => $qrCode->id,
            'post_id' => $postId,
            'old_code' => substr($qrCode->getOriginal('code'), 0, 8) . '...',
            'new_code' => substr($newCode, 0, 8) . '...',
            'regeneration_count' => $metadata['regeneration_count'],
            'regenerated_by' => auth()->id()
        ]);

        // Handle AJAX requests from the modal
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'QR code regenerated successfully.',
                'redirect' => route('admin.security-posts.qr-codes.show', [
                    'securityPost' => $postId,
                    'qrCode' => $qrCode->id
                ]),
                'data' => [
                    'new_code' => $newCode,
                    'qr_code_id' => $qrCode->id,
                    'image_path' => $qrCode->image_path
                ]
            ]);
        }

        // Regular form submission response
        return redirect()->route('admin.security-posts.qr-codes.show', [
            'securityPost' => $postId,
            'qrCode' => $qrCode->id
        ])->with('success', 'QR code regenerated successfully. The old code has been invalidated.');

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        
        Log::error('QR code not found for regeneration', [
            'qr_code_id' => $qrCodeId,
            'post_id' => $postId,
            'user_id' => auth()->id()
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found.'
            ], 404);
        }

        return redirect()->route('admin.security-posts.qr-codes.index', ['securityPost' => $postId])
            ->with('error', 'QR code not found.');

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to regenerate QR code: ' . $e->getMessage(), [
            'qr_code_id' => $qrCodeId,
            'post_id' => $postId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate QR code: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Failed to regenerate QR code: ' . $e->getMessage())
            ->withInput();
    }
}

/**
 * Helper method to clear QR code cache
 */
private function clearQrCodeCache($qrCode)
{
    try {
        Cache::forget("qr_code_{$qrCode->id}");
        Cache::forget("qr_code_{$qrCode->code}");
        Cache::forget("qr_code_{$qrCode->id}_stats");
        Cache::forget("post_{$qrCode->post_id}_qr_codes");
    } catch (\Exception $e) {
        // Cache might not be configured, silently fail
        Log::debug('Cache clear failed: ' . $e->getMessage());
    }
}

 /**
 * Delete QR code (soft delete - moves to trash)
 */
public function destroy($postId, $qrCodeId)
{
    try {
        // Validate that the QR code ID is numeric
        if (!is_numeric($qrCodeId)) {
            Log::warning('Attempted to delete QR code with non-numeric ID', [
                'qr_code_id' => $qrCodeId,
                'post_id' => $postId,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_id' => auth()->id() ?? 'guest'
            ]);
            
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid QR code ID'
                ], 400);
            }
            
            return redirect()->route('admin.security-posts.qr-codes.index', [
                'securityPost' => $postId
            ])->with('error', 'Invalid QR code ID.');
        }

        // Also validate post ID if needed
        if (!is_numeric($postId)) {
            return redirect()->back()->with('error', 'Invalid post ID.');
        }

        $qrCode = PostQrCode::where('post_id', $postId)->findOrFail($qrCodeId);
        
        // FIXED: Safely handle metadata - no json_decode needed!
        // $qrCode->metadata is already an array thanks to model cast
        $metadata = $qrCode->metadata ?? [];
        
        // Ensure it's an array
        if (!is_array($metadata)) {
            $metadata = [];
        }
        
        // Initialize deletions array if it doesn't exist
        if (!isset($metadata['deletions']) || !is_array($metadata['deletions'])) {
            $metadata['deletions'] = [];
        }
        
        // Store deletion info in metadata before soft delete
        $metadata['deletions'][] = [
            'deleted_at' => now()->toDateTimeString(),
            'deleted_by' => auth()->id(),
            'deleted_by_name' => auth()->user()->name ?? 'System',
            'reason' => 'Moved to trash',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];
        
        // Add deletion count
        $metadata['deletion_count'] = ($metadata['deletion_count'] ?? 0) + 1;
        
        // Add last deletion info
        $metadata['last_deletion'] = [
            'deleted_at' => now()->toDateTimeString(),
            'deleted_by' => auth()->id(),
            'deleted_by_name' => auth()->user()->name ?? 'System',
            'reason' => 'Moved to trash'
        ];
        
        // Update metadata - will be auto-encoded by model cast
        $qrCode->metadata = $metadata;
        $qrCode->save();

        // Soft delete the QR code
        $qrCode->delete();

        Log::info('QR code moved to trash', [
            'qr_code_id' => $qrCode->id,
            'post_id' => $postId,
            'deleted_by' => auth()->id(),
            'deletion_count' => $metadata['deletion_count']
        ]);

        // Handle AJAX request
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'QR code moved to trash successfully.',
                'redirect' => route('admin.security-posts.qr-codes.index', [
                    'securityPost' => $postId
                ])
            ]);
        }

        return redirect()->route('admin.security-posts.qr-codes.index', [
            'securityPost' => $postId
        ])->with('success', 'QR code moved to trash successfully.');

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        Log::error('QR code not found for deletion', [
            'qr_code_id' => $qrCodeId,
            'post_id' => $postId,
            'user_id' => auth()->id() ?? 'guest'
        ]);
        
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found.'
            ], 404);
        }
        
        return redirect()->route('admin.security-posts.qr-codes.index', [
            'securityPost' => $postId
        ])->with('error', 'QR code not found.');
        
    } catch (\Exception $e) {
        Log::error('Failed to delete QR code: ' . $e->getMessage(), [
            'qr_code_id' => $qrCodeId,
            'trace' => $e->getTraceAsString()
        ]);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete QR code: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Failed to delete QR code.');
    }
}

    /**
     * Bulk delete QR codes (soft delete - moves to trash)
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_code_ids' => 'required|array',
            'qr_code_ids.*' => 'exists:post_qr_codes,id',
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

            foreach ($request->qr_code_ids as $qrCodeId) {
                try {
                    $qrCode = PostQrCode::find($qrCodeId);
                    
                    if (!$qrCode) {
                        $failedDeletes[] = [
                            'id' => $qrCodeId,
                            'reason' => 'QR code not found'
                        ];
                        continue;
                    }

                    // Store deletion info in metadata
                    $metadata = json_decode($qrCode->metadata, true) ?? [];
                    $metadata['bulk_deletions'] = $metadata['bulk_deletions'] ?? [];
                    $metadata['bulk_deletions'][] = [
                        'deleted_at' => now()->toDateTimeString(),
                        'deleted_by' => auth()->id(),
                        'deleted_by_name' => auth()->user()->name,
                        'reason' => 'Bulk move to trash'
                    ];
                    
                    $qrCode->metadata = json_encode($metadata);
                    $qrCode->save();

                    $qrCode->delete();
                    $deletedCount++;

                    Log::info('QR code bulk deleted', [
                        'qr_code_id' => $qrCodeId,
                        'deleted_by' => auth()->id()
                    ]);

                } catch (\Exception $e) {
                    $failedDeletes[] = [
                        'id' => $qrCodeId,
                        'reason' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} QR code(s) moved to trash successfully.",
                'deleted_count' => $deletedCount,
                'failed_count' => count($failedDeletes),
                'failed_deletes' => $failedDeletes
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to bulk delete QR codes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'qr_code_ids' => $request->qr_code_ids
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete QR codes.'
            ], 500);
        }
    }

    /**
     * Export QR codes as CSV
     */
    public function export($postId)
    {
        $post = SecurityPost::findOrFail($postId);
        
        $qrCodes = PostQrCode::where('post_id', $postId)
            ->orderBy('created_at', 'desc')
            ->get();

        $fileName = 'qr_codes_' . $post->code . '_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($qrCodes, $post) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            
            fputcsv($file, [
                'ID',
                'Name',
                'Description',
                'Code',
                'Type',
                'Status',
                'Is Permanent',
                'Uses Count',
                'Max Uses',
                'Expires At',
                'Created At',
                'Created By',
                'Last Used',
                'Download URL'
            ]);

            foreach ($qrCodes as $qr) {
                $metadata = json_decode($qr->metadata, true);
                $isPermanent = $qr->code_type === 'static' && $qr->expires_at === null;
                
                // Safely get last used date
                $lastUsed = null;
                try {
                    // Try to get last used from verification_logs if table exists
                    if (DB::getSchemaBuilder()->hasTable('verification_logs')) {
                        $query = DB::table('verification_logs');
                        $columns = DB::getSchemaBuilder()->getColumnListing('verification_logs');
                        
                        if (in_array('qr_code_id', $columns)) {
                            $lastUsed = $query->where('qr_code_id', $qr->id)->max('created_at');
                        } elseif (in_array('verification_code', $columns)) {
                            $lastUsed = $query->where('verification_code', $qr->code)->max('created_at');
                        } elseif (in_array('code', $columns)) {
                            $lastUsed = $query->where('code', $qr->code)->max('created_at');
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Could not fetch last used date for export: ' . $e->getMessage());
                }

                fputcsv($file, [
                    $qr->id,
                    $qr->name,
                    $qr->description,
                    $qr->code,
                    $qr->code_type,
                    $qr->is_active ? 'Active' : 'Inactive',
                    $isPermanent ? 'Yes' : 'No',
                    $qr->uses_count,
                    $qr->max_uses ?? 'Unlimited',
                    $qr->expires_at ? Carbon::parse($qr->expires_at)->format('Y-m-d H:i:s') : 'Never',
                    $qr->created_at->format('Y-m-d H:i:s'),
                    $metadata['generated_by'] ?? 'System',
                    $lastUsed ? Carbon::parse($lastUsed)->format('Y-m-d H:i:s') : 'Never',
                    route('admin.security-posts.qr-codes.download', [
                        'securityPost' => $post->id,
                        'qrCode' => $qr->id
                    ])
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate unique QR code
     */
    private function generateUniqueQrCode()
    {
        $prefix = 'QR';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(8));
        $code = $prefix . '-' . $timestamp . '-' . $random;

        // Ensure uniqueness
        while (PostQrCode::where('code', $code)->exists()) {
            $random = strtoupper(Str::random(8));
            $code = $prefix . '-' . $timestamp . '-' . $random;
        }

        return $code;
    }

  /**
 * Generate QR code image using chillerlan/php-qrcode
 */
private function generateQrCodeImage($qrCode, $request = null)
{
    // FIXED: Don't json_decode - metadata is already an array thanks to model cast
    $metadata = $qrCode->metadata;
    
    // Safety check - ensure it's an array
    if (!is_array($metadata)) {
        // If it's still a string (fallback), decode it
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        } else {
            $metadata = [];
        }
    }
    
    $config = $metadata['config'] ?? [
        'size' => $request->size ?? 300,
        'foreground_color' => $request->foreground_color ?? '#000000',
        'background_color' => $request->background_color ?? '#ffffff',
        'style' => $request->style ?? 'square',
        'format' => 'png',
    ];

    // Build QR code data - OPTIMIZED for size
    // Start with base data that all codes have
    $qrDataArray = [
        't' => 'sc',                    // type: security_checkin
        'p' => $qrCode->post_id,         // post_id
        'c' => $qrCode->code,            // code
        'n' => substr($qrCode->name, 0, 20), // truncated name
        'vf' => $qrCode->created_at->timestamp, // valid_from
        'v' => '1'                       // version
    ];

    // Add one_time flag only for one_time codes (to save space)
    if ($qrCode->code_type === 'one_time') {
        $qrDataArray['ot'] = 1;
    }

    // Handle expiration based on code type
    if ($qrCode->code_type === 'time_based' && $qrCode->expires_at !== null) {
        // Time-based codes have expiration timestamp
        $qrDataArray['ve'] = $qrCode->expires_at->timestamp;
    } elseif ($qrCode->code_type === 'static') {
        // Static/permanent codes never expire
        $qrDataArray['perm'] = 1; // Add permanent flag
    }

    $qrData = json_encode($qrDataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Log data size for debugging
    $dataLength = strlen($qrData) * 8; // Convert to bits
    Log::info('QR code data generated', [
        'qr_code_id' => $qrCode->id,
        'code_type' => $qrCode->code_type,
        'bytes' => strlen($qrData),
        'bits' => $dataLength,
        'has_expiry' => isset($qrDataArray['ve']),
        'is_permanent' => $qrCode->code_type === 'static',
        'data' => $qrData
    ]);

    try {
        // Convert hex colors to RGB arrays
        $foregroundRgb = $this->hexToRgb($config['foreground_color']);
        $backgroundRgb = $this->hexToRgb($config['background_color']);
        
        // Log colors for debugging
        Log::debug('QR code colors', [
            'foreground_hex' => $config['foreground_color'],
            'foreground_rgb' => $foregroundRgb,
            'background_hex' => $config['background_color'],
            'background_rgb' => $backgroundRgb
        ]);
        
        // Calculate required version based on data length
        $dataBits = strlen($qrData) * 8;
        $version = $this->calculateOptimalVersion($dataBits);
        
        Log::info('Using QR code version', ['version' => $version, 'data_bits' => $dataBits]);
        
        // Calculate module size based on requested image size
        // Each version has (4*version + 17) modules per side
        $moduleCount = 4 * $version + 17;
        $margin = 4; // modules of margin
        $totalModules = $moduleCount + (2 * $margin);
        $moduleSize = max(1, (int)($config['size'] / $totalModules));
        
        // FIXED: Comprehensive module value configuration for proper color display
        $options = new QROptions([
            'version' => $version,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_H,
            'scale' => $moduleSize,
            'imageBase64' => false,
            'bgColor' => [$backgroundRgb['r'], $backgroundRgb['g'], $backgroundRgb['b']],
            'imageTransparent' => false,
            'drawLightModules' => true,
            'drawCircularModules' => $config['style'] === 'dot' || $config['style'] === 'round',
            'circleRadius' => $config['style'] === 'dot' ? 0.4 : ($config['style'] === 'round' ? 0.5 : 0.0),
            'keepAsSquare' => $config['style'] === 'square' ? [true] : [],
            // FIXED: All dark modules must use foreground color, all light modules use background
            'moduleValues' => [
                // Dark modules (QR code patterns) - ALL must use foreground color
                'dark' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'finderm' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'finderl' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'alignment' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'timing' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'format' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'version' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                
                // Light modules - must use background color
                'light' => [$backgroundRgb['r'], $backgroundRgb['g'], $backgroundRgb['b']],
                'separator' => [$backgroundRgb['r'], $backgroundRgb['g'], $backgroundRgb['b']],
                'quietzone' => [$backgroundRgb['r'], $backgroundRgb['g'], $backgroundRgb['b']],
                
                // Keep these for backward compatibility with older versions of the library
                'findermodule' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'findermiddle' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'alignmentmodule' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'darkmodule' => [$foregroundRgb['r'], $foregroundRgb['g'], $foregroundRgb['b']],
                'alignmentmiddle' => [$backgroundRgb['r'], $backgroundRgb['g'], $backgroundRgb['b']],
            ],
        ]);

        // Create QR Code instance and generate
        $qrCodeImage = (new QRCode($options))->render($qrData);
        
        // Verify the image was created with the right colors
        Log::info('QR code generated successfully with chillerlan/php-qrcode', [
            'foreground' => $config['foreground_color'],
            'background' => $config['background_color'],
            'style' => $config['style'],
            'module_count' => $moduleCount,
            'module_size' => $moduleSize,
            'image_size' => $moduleSize * $totalModules
        ]);
        
        return $qrCodeImage;

    } catch (\Exception $e) {
        Log::error('QR code generation with chillerlan failed: ' . $e->getMessage(), [
            'foreground' => $config['foreground_color'] ?? 'unknown',
            'background' => $config['background_color'] ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Fallback to even simpler data structure
        try {
            // Ultra-minimal data structure - just post_id and code
            $simpleData = $qrCode->post_id . '|' . $qrCode->code;
            
            // For fallback, use basic colors but still try to maintain user preferences
            $fallbackForeground = $foregroundRgb ?? ['r' => 0, 'g' => 0, 'b' => 0];
            $fallbackBackground = $backgroundRgb ?? ['r' => 255, 'g' => 255, 'b' => 255];
            
            $options = new QROptions([
                'version' => 10,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_H,
                'scale' => 10,
                'imageBase64' => false,
                'bgColor' => [$fallbackBackground['r'], $fallbackBackground['g'], $fallbackBackground['b']],
                'moduleValues' => [
                    'dark' => [$fallbackForeground['r'], $fallbackForeground['g'], $fallbackForeground['b']],
                    'light' => [$fallbackBackground['r'], $fallbackBackground['g'], $fallbackBackground['b']],
                ],
            ]);
            
            $qrCodeImage = (new QRCode($options))->render($simpleData);
            
            Log::info('QR code generated with simplified data structure', [
                'foreground' => $config['foreground_color'] ?? '#000000',
                'background' => $config['background_color'] ?? '#ffffff'
            ]);
            
            return $qrCodeImage;
            
        } catch (\Exception $e2) {
            Log::error('Fallback QR code generation also failed: ' . $e2->getMessage());
            
            // Ultimate fallback - just encode the code itself with default colors
            try {
                $options = new QROptions([
                    'version' => 10,
                    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel' => QRCode::ECC_H,
                    'scale' => 10,
                    'imageBase64' => false,
                ]);
                
                $qrCodeImage = (new QRCode($options))->render($qrCode->code);
                
                Log::info('QR code generated with ultimate fallback (just the code)');
                
                return $qrCodeImage;
                
            } catch (\Exception $e3) {
                Log::error('All QR code generation methods failed: ' . $e3->getMessage());
                throw new \Exception('Unable to generate QR code. Please check your PHP GD installation.');
            }
        }
    }
}

    /**
     * Calculate optimal QR code version based on data length and error correction
     */
    private function calculateOptimalVersion($dataLength, $eccLevel = 'H')
    {
        // Maximum data capacity for each version with H error correction
        // Source: https://www.qrcode.com/en/about/version.html
        $capacity = [
            1 => 80,   // Version 1: 80 bits
            2 => 128,  // Version 2: 128 bits
            3 => 208,  // Version 3: 208 bits
            4 => 288,  // Version 4: 288 bits
            5 => 368,  // Version 5: 368 bits
            6 => 480,  // Version 6: 480 bits
            7 => 592,  // Version 7: 592 bits
            8 => 688,  // Version 8: 688 bits
            9 => 800,  // Version 9: 800 bits
            10 => 912, // Version 10: 912 bits
            11 => 1024, // Version 11: 1024 bits
            12 => 1152, // Version 12: 1152 bits
            13 => 1280, // Version 13: 1280 bits
            14 => 1408, // Version 14: 1408 bits
            15 => 1536, // Version 15: 1536 bits
            16 => 1664, // Version 16: 1664 bits
            17 => 1792, // Version 17: 1792 bits
            18 => 1920, // Version 18: 1920 bits
            19 => 2048, // Version 19: 2048 bits
            20 => 2176, // Version 20: 2176 bits
            21 => 2304, // Version 21: 2304 bits
            22 => 2432, // Version 22: 2432 bits
            23 => 2560, // Version 23: 2560 bits
            24 => 2688, // Version 24: 2688 bits
            25 => 2816, // Version 25: 2816 bits
            26 => 2944, // Version 26: 2944 bits
            27 => 3072, // Version 27: 3072 bits
            28 => 3200, // Version 28: 3200 bits
            29 => 3328, // Version 29: 3328 bits
            30 => 3456, // Version 30: 3456 bits
            31 => 3584, // Version 31: 3584 bits
            32 => 3712, // Version 32: 3712 bits
            33 => 3840, // Version 33: 3840 bits
            34 => 3968, // Version 34: 3968 bits
            35 => 4096, // Version 35: 4096 bits
            36 => 4224, // Version 36: 4224 bits
            37 => 4352, // Version 37: 4352 bits
            38 => 4480, // Version 38: 4480 bits
            39 => 4608, // Version 39: 4608 bits
            40 => 4736, // Version 40: 4736 bits
        ];
        
        foreach ($capacity as $version => $bits) {
            if ($bits >= $dataLength) {
                return $version;
            }
        }
        
        return 40; // Maximum version if somehow exceeded
    }

    /**
     * Store QR code image
     */
    private function storeQrCodeImage($qrCode, $image)
    {
        $path = 'qr-codes/' . date('Y/m/d/');
        $filename = 'qr_' . $qrCode->post_id . '_' . $qrCode->id . '_' . time() . '.png';
        
        $fullPath = $path . $filename;
        
        // Ensure directory exists
        if (!file_exists(storage_path('app/public/' . $path))) {
            mkdir(storage_path('app/public/' . $path), 0755, true);
        }

        // Save image
        file_put_contents(storage_path('app/public/' . $fullPath), $image);

        return $fullPath;
    }

    /**
     * Convert hex color to RGB array
     */
    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) == 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }
}