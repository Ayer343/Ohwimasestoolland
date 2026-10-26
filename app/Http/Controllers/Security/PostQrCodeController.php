<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\PostQrCode;
use App\Models\SecurityPost;
use Carbon\Carbon;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PostQrCodeController extends Controller
{
    // ============================================================
    // AUTHORIZATION + RESPONSE HELPERS
    // ============================================================

    private function canManage(): bool
    {
        $user = auth()->user();
        return $user && Gate::forUser($user)->allows('manage-security-schedules');
    }

    private function authorizeManage(): void
    {
        if (!$this->canManage()) {
            abort(403, 'You do not have permission to manage QR codes.');
        }
    }

    /**
     * Guards IDOR: supervisor must have access to this post.
     *
     * FIX: Previously checked `Gate::denies('view', $post)`, which no
     * type-6 supervisor was ever granted, so every QR action 403'd with
     * "You do not have access to this security post."
     *
     * This now aligns with the rest of the supervisor area, which gates
     * on `manage-security-schedules`. If your app also scopes supervisors
     * to specific posts (via direct column, pivot, or area), uncomment
     * the appropriate block below.
     */
    private function assertPostAccessible(SecurityPost $post): void
    {
        $user = auth()->user();
        if (!$user) abort(403);

        // Admin / super-admin bypass (defense-in-depth — route middleware
        // should already prevent them from reaching security.* routes).
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return;
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return;

        // Primary gate — same gate used by the rest of the supervisor area
        // (SecurityShiftController, RotationGroupController, SecurityPostController).
        if (Gate::forUser($user)->denies('manage-security-schedules')) {
            abort(403, 'You do not have permission to manage QR codes.');
        }

        // -----------------------------------------------------------------
        // OPTIONAL POST-SCOPE ENFORCEMENT
        // -----------------------------------------------------------------
        // Uncomment & adapt ONE of the following if supervisors should only
        // see QR codes for posts they personally supervise. Leave all
        // commented out if any user with `manage-security-schedules` should
        // be able to manage QR codes for any post.
        //
        // Example 1 — direct column on security_posts:
        // if ((int) $post->area_supervisor_id !== (int) $user->id) {
        //     abort(403, 'You do not have access to this security post.');
        // }
        //
        // Example 2 — pivot table `post_supervisors` (requires
        // User::supervisedPosts() relation):
        // $hasAnyAssignment = $user->supervisedPosts()->exists();
        // if ($hasAnyAssignment
        //     && !$user->supervisedPosts()->where('security_posts.id', $post->id)->exists()) {
        //     abort(403, 'You do not have access to this security post.');
        // }
        //
        // Example 3 — area scoping:
        // if ((int) $post->area_id !== (int) $user->security_area_id) {
        //     abort(403, 'You do not have access to this security post.');
        // }
        // -----------------------------------------------------------------
    }

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $data));
        }
        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    private function failure(Request $request, string $message, int $status = 422, $errors = null, ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(
                array_merge(['success' => false, 'message' => $message], $errors ? ['errors' => $errors] : []),
                $status
            );
        }
        $r = $redirect ? redirect($redirect) : back();
        if ($errors) $r->withErrors($errors);
        return $r->with('error', $message)->withInput();
    }

    // ============================================================
    // INDEX / SHOW / CREATE
    // ============================================================

    public function index(Request $request, $postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $query = PostQrCode::where('post_id', $post->id)->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")
                ->orWhere('code', 'LIKE', "%{$s}%"));
        }
        if ($request->filled('code_type')) {
            $query->where('code_type', $request->code_type);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $qrCodes = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => PostQrCode::where('post_id', $post->id)->count(),
            'active' => PostQrCode::where('post_id', $post->id)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'trashed' => PostQrCode::where('post_id', $post->id)->onlyTrashed()->count(),
            'expired' => PostQrCode::where('post_id', $post->id)
                ->where('is_active', true)->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())->count(),
        ];

        return view('security.posts.qr-codes.index', compact('post', 'qrCodes', 'stats'));
    }

    public function create($postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        return view('security.posts.qr-codes.create', compact('post'));
    }

    public function show($postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $qrCode = PostQrCode::where('post_id', $post->id)
            ->with(['creator'])
            ->findOrFail($qrCodeId);

        $usageStats = $this->usageStats($qrCode);

        $expiresAt = $qrCode->expires_at
            ? $qrCode->expires_at->format('Y-m-d H:i:s')
            : 'Never';
        $maxUses = $qrCode->max_uses ?? 'Unlimited';
        $isPermanent = $qrCode->code_type === 'static' && $qrCode->expires_at === null;

        return view('security.posts.qr-codes.show', compact(
            'post', 'qrCode', 'usageStats', 'expiresAt', 'maxUses', 'isPermanent'
        ));
    }

    // ============================================================
    // STORE
    // ============================================================

    public function store(Request $request, $postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

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
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $code = $this->generateUniqueQrCode();

            [$expiresAt, $maxUses] = $this->resolveCodeConstraints($request);

            $metadata = [
                'generated_at' => now()->toIso8601String(),
                'generated_by' => auth()->user()?->name ?? 'System',
                'generated_by_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'config' => [
                    'size' => (int) ($request->size ?? 300),
                    'foreground_color' => $request->foreground_color ?? '#000000',
                    'background_color' => $request->background_color ?? '#ffffff',
                    'style' => $request->style ?? 'square',
                    'format' => 'png',
                ],
            ];

            $qrCode = PostQrCode::create([
                'post_id' => $post->id,
                'name' => $request->name,
                'description' => $request->description,
                'code' => $code,
                'code_type' => $request->code_type,
                'expires_at' => $expiresAt,
                'max_uses' => $maxUses,
                'uses_count' => 0,
                'is_active' => true,
                'created_by' => auth()->id(),
                'metadata' => $metadata, // model cast handles encoding
            ]);

            $qrImage = $this->generateQrCodeImage($qrCode, $request);
            $path = $this->storeQrCodeImage($qrCode, $qrImage);

            $qrCode->update(['image_path' => $path]);
            DB::commit();

            Log::info('QR code generated (supervisor)', [
                'qr_code_id' => $qrCode->id, 'post_id' => $post->id, 'by' => auth()->id(),
            ]);

            return $this->success(
                $request,
                'QR code generated successfully.',
                ['qr_code_id' => $qrCode->id],
                route('security.posts.qr-codes.show', [
                    'securityPost' => $post->id,
                    'qrCode' => $qrCode->id,
                ])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('QR code generation failed: ' . $e->getMessage(), [
                'post_id' => $post->id, 'trace' => $e->getTraceAsString(),
            ]);
            return $this->failure($request, 'Failed to generate QR code: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // UPDATE STATUS / REGENERATE
    // ============================================================

    public function updateStatus(Request $request, $postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
            'reason' => 'required_if:is_active,false|nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        try {
            $qrCode = PostQrCode::where('post_id', $post->id)->findOrFail($qrCodeId);

            $metadata = $qrCode->metadata ?? [];
            $metadata['status_changes'][] = [
                'old_status' => (bool) $qrCode->is_active,
                'new_status' => $request->boolean('is_active'),
                'changed_at' => now()->toIso8601String(),
                'changed_by' => auth()->id(),
                'changed_by_name' => auth()->user()?->name,
                'reason' => $request->reason,
            ];

            $qrCode->update([
                'is_active' => $request->boolean('is_active'),
                'metadata' => $metadata,
            ]);

            Log::info('QR code status updated (supervisor)', [
                'qr_code_id' => $qrCode->id, 'is_active' => $request->boolean('is_active'),
                'by' => auth()->id(),
            ]);

            return $this->success($request, 'Status updated.', [
                'is_active' => (bool) $qrCode->fresh()->is_active,
            ]);
        } catch (\Throwable $e) {
            Log::error('Status update failed: ' . $e->getMessage());
            return $this->failure($request, 'Failed to update status.', 500);
        }
    }

    public function regenerate(Request $request, $postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        DB::beginTransaction();
        try {
            $qrCode = PostQrCode::where('post_id', $post->id)->findOrFail($qrCodeId);
            $newCode = $this->generateUniqueQrCode();

            $metadata = $qrCode->metadata ?? []; // model cast => array
            $metadata['previous_codes'][] = [
                'code' => $qrCode->code,
                'regenerated_at' => now()->toIso8601String(),
                'regenerated_by' => auth()->id(),
                'regenerated_by_name' => auth()->user()?->name ?? 'System',
                'reason' => $request->input('reason', 'Manual regeneration'),
                'ip_address' => $request->ip(),
            ];
            // Cap history
            if (count($metadata['previous_codes']) > 50) {
                $metadata['previous_codes'] = array_slice($metadata['previous_codes'], -50);
            }
            $metadata['last_regeneration'] = [
                'old_code' => $qrCode->code,
                'new_code' => $newCode,
                'regenerated_at' => now()->toIso8601String(),
                'regenerated_by' => auth()->id(),
            ];
            $metadata['regeneration_count'] = ($metadata['regeneration_count'] ?? 0) + 1;

            $qrCode->update([
                'code' => $newCode,
                'uses_count' => 0,
                'metadata' => $metadata,
            ]);

            $qrImage = $this->generateQrCodeImage($qrCode, $request);
            $path = $this->storeQrCodeImage($qrCode, $qrImage);
            $qrCode->update(['image_path' => $path]);

            DB::commit();
            $this->clearQrCodeCache($qrCode);

            Log::info('QR code regenerated (supervisor)', [
                'qr_code_id' => $qrCode->id, 'by' => auth()->id(),
            ]);

            return $this->success(
                $request,
                'QR code regenerated successfully.',
                [
                    'new_code' => $newCode,
                    'qr_code_id' => $qrCode->id,
                    'image_path' => $qrCode->image_path,
                ],
                route('security.posts.qr-codes.show', [
                    'securityPost' => $post->id, 'qrCode' => $qrCode->id,
                ])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Regenerate failed: ' . $e->getMessage(), [
                'qr_code_id' => $qrCodeId, 'trace' => $e->getTraceAsString(),
            ]);
            return $this->failure($request, 'Failed to regenerate: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // DESTROY / RESTORE / FORCE DELETE
    // ============================================================

    public function destroy($postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        try {
            $qrCode = PostQrCode::where('post_id', $post->id)->findOrFail($qrCodeId);

            $metadata = $qrCode->metadata ?? [];
            $metadata['deletions'][] = [
                'deleted_at' => now()->toIso8601String(),
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()?->name ?? 'System',
                'reason' => 'Moved to trash',
            ];
            $metadata['deletion_count'] = ($metadata['deletion_count'] ?? 0) + 1;

            $qrCode->update(['metadata' => $metadata]);
            $qrCode->delete();

            Log::info('QR code moved to trash (supervisor)', [
                'qr_code_id' => $qrCode->id, 'by' => auth()->id(),
            ]);

            return $this->success(
                request(),
                'QR code moved to trash.',
                [],
                route('security.posts.qr-codes.index', ['securityPost' => $post->id])
            );
        } catch (\Throwable $e) {
            Log::error('Delete failed: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to delete: ' . $e->getMessage(), 500);
        }
    }

    public function trash($postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $trashedQrCodes = PostQrCode::where('post_id', $post->id)
            ->onlyTrashed()->orderByDesc('deleted_at')->paginate(20);

        return view('security.posts.qr-codes.trash', compact('post', 'trashedQrCodes'));
    }

    public function restore($postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        DB::beginTransaction();
        try {
            $qrCode = PostQrCode::where('post_id', $post->id)
                ->onlyTrashed()->findOrFail($qrCodeId);

            $metadata = $qrCode->metadata ?? [];
            $metadata['restorations'][] = [
                'restored_at' => now()->toIso8601String(),
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()?->name ?? 'System',
            ];

            $qrCode->update(['metadata' => $metadata]);
            $qrCode->restore();

            DB::commit();

            Log::info('QR code restored (supervisor)', [
                'qr_code_id' => $qrCode->id, 'by' => auth()->id(),
            ]);

            return $this->success(
                request(),
                'QR code restored.',
                ['qr_code_id' => $qrCode->id],
                route('security.posts.qr-codes.show', [
                    'securityPost' => $post->id, 'qrCode' => $qrCode->id,
                ])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Restore failed: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to restore: ' . $e->getMessage(), 500);
        }
    }

    public function forceDelete($postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        DB::beginTransaction();
        try {
            // Must be trashed first
            if (PostQrCode::where('post_id', $post->id)->where('id', $qrCodeId)->exists()) {
                DB::rollBack();
                return $this->failure(
                    request(),
                    'Cannot permanently delete. Move to trash first.',
                    422
                );
            }

            $qrCode = PostQrCode::where('post_id', $post->id)
                ->onlyTrashed()->findOrFail($qrCodeId);

            if ($qrCode->image_path && Storage::disk('public')->exists($qrCode->image_path)) {
                Storage::disk('public')->delete($qrCode->image_path);
            }

            Log::warning('QR code permanently deleted (supervisor)', [
                'qr_code_id' => $qrCode->id, 'code' => $qrCode->code, 'by' => auth()->id(),
            ]);

            $qrCode->forceDelete();
            DB::commit();

            return $this->success(
                request(),
                'QR code permanently deleted.',
                [],
                route('security.posts.qr-codes.trash', ['securityPost' => $post->id])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Force delete failed: ' . $e->getMessage());
            return $this->failure(request(), 'Failed to delete: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // BULK
    // ============================================================

    public function bulkDestroy(Request $request, $postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $validator = Validator::make($request->all(), [
            'qr_code_ids' => 'required|array|min:1',
            'qr_code_ids.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $deleted = 0; $failed = [];
            foreach (PostQrCode::where('post_id', $post->id)
                ->whereIn('id', $request->qr_code_ids)->get() as $qrCode) {
                try {
                    $metadata = $qrCode->metadata ?? [];
                    $metadata['bulk_deletions'][] = [
                        'deleted_at' => now()->toIso8601String(),
                        'deleted_by' => auth()->id(),
                    ];
                    $qrCode->update(['metadata' => $metadata]);
                    $qrCode->delete();
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed[] = ['id' => $qrCode->id, 'reason' => $e->getMessage()];
                }
            }
            DB::commit();

            return $this->success($request, "{$deleted} QR code(s) moved to trash.", [
                'deleted_count' => $deleted,
                'failed_count' => count($failed),
                'failed_deletes' => $failed,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->failure($request, 'Failed to bulk delete: ' . $e->getMessage(), 500);
        }
    }

    public function bulkRestore(Request $request, $postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $validator = Validator::make($request->all(), [
            'qr_code_ids' => 'required|array|min:1',
            'qr_code_ids.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
        try {
            $restored = 0; $failed = [];
            foreach (PostQrCode::where('post_id', $post->id)->onlyTrashed()
                ->whereIn('id', $request->qr_code_ids)->get() as $qrCode) {
                try {
                    $metadata = $qrCode->metadata ?? [];
                    $metadata['restorations'][] = [
                        'restored_at' => now()->toIso8601String(),
                        'restored_by' => auth()->id(),
                    ];
                    $qrCode->update(['metadata' => $metadata]);
                    $qrCode->restore();
                    $restored++;
                } catch (\Throwable $e) {
                    $failed[] = ['id' => $qrCode->id, 'reason' => $e->getMessage()];
                }
            }
            DB::commit();

            return $this->success($request, "{$restored} QR code(s) restored.", [
                'restored_count' => $restored,
                'failed_count' => count($failed),
                'failed_restores' => $failed,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->failure($request, 'Failed to bulk restore: ' . $e->getMessage(), 500);
        }
    }

    public function emptyTrash($postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        DB::beginTransaction();
        try {
            $deleted = 0; $failed = [];
            foreach (PostQrCode::where('post_id', $post->id)->onlyTrashed()->get() as $qrCode) {
                try {
                    if ($qrCode->image_path && Storage::disk('public')->exists($qrCode->image_path)) {
                        Storage::disk('public')->delete($qrCode->image_path);
                    }
                    $qrCode->forceDelete();
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed[] = ['id' => $qrCode->id, 'reason' => $e->getMessage()];
                }
            }
            DB::commit();

            Log::info('QR code trash emptied (supervisor)', [
                'post_id' => $post->id, 'deleted' => $deleted, 'by' => auth()->id(),
            ]);

            $msg = "{$deleted} QR code(s) permanently deleted.";
            if ($failed) $msg .= ' ' . count($failed) . ' failed.';

            return $this->success($request, $msg, [
                'deleted_count' => $deleted,
                'failed_count' => count($failed),
            ], route('security.posts.qr-codes.trash', ['securityPost' => $post->id]));
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->failure($request, 'Failed to empty trash: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // DOWNLOAD
    // ============================================================

    public function download($postId, $qrCodeId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $qrCode = PostQrCode::where('post_id', $post->id)->findOrFail($qrCodeId);

        // Regenerate image if missing
        if (!$qrCode->image_path || !Storage::disk('public')->exists($qrCode->image_path)) {
            $image = $this->generateQrCodeImage($qrCode, null);
            $path = $this->storeQrCodeImage($qrCode, $image);
            $qrCode->update(['image_path' => $path]);
        }

        $format = request()->get('format', 'png');
        return $format === 'pdf'
            ? $this->downloadQrCodeAsPdf($qrCode, $post)
            : $this->downloadQrCodeAsPng($qrCode, $post);
    }

    private function downloadQrCodeAsPng($qrCode, $post)
    {
        $path = storage_path('app/public/' . $qrCode->image_path);
        if (!file_exists($path)) abort(404, 'QR image not found.');

        $qrImage = imagecreatefrompng($path);
        $qrW = imagesx($qrImage);
        $qrH = imagesy($qrImage);

        $canvasW = max(800, $qrW + 100);
        $canvasH = $qrH + 450;
        $canvas = imagecreatetruecolor($canvasW, $canvasH);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $primary = imagecolorallocate($canvas, 79, 70, 229);
        $success = imagecolorallocate($canvas, 16, 185, 129);
        $warning = imagecolorallocate($canvas, 245, 158, 11);
        $danger = imagecolorallocate($canvas, 239, 68, 68);
        $grayLight = imagecolorallocate($canvas, 243, 244, 246);
        $grayText = imagecolorallocate($canvas, 75, 85, 99);
        $border = imagecolorallocate($canvas, 209, 213, 219);

        imagefill($canvas, 0, 0, $white);
        imagerectangle($canvas, 0, 0, $canvasW - 1, $canvasH - 1, $border);

        $qrX = (int) (($canvasW - $qrW) / 2);
        $qrY = 30;
        imagecopy($canvas, $qrImage, $qrX, $qrY, 0, 0, $qrW, $qrH);

        $this->addCenteredText($canvas, 5, $primary, 'ATTENDANCE QR CODE', $canvasW / 2, $qrY + $qrH + 20);
        $this->addCenteredText($canvas, 4, $black, $post->name . ' (' . $post->code . ')', $canvasW / 2, $qrY + $qrH + 50);

        $instructionsY = $qrY + $qrH + 80;
        imagefilledrectangle($canvas, 20, $instructionsY, $canvasW - 20, $instructionsY + 280, $grayLight);

        $this->addText($canvas, 3, $primary, 'INSTRUCTIONS FOR SECURITY PERSONNEL', 40, $instructionsY + 30);
        $this->addText($canvas, 2, $success, 'CHECK-IN:', 40, $instructionsY + 60);
        $this->addText($canvas, 2, $black, '1. Open attendance app → CHECK-IN', 60, $instructionsY + 85);
        $this->addText($canvas, 2, $black, '2. Scan this QR code on arrival', 60, $instructionsY + 105);
        $this->addText($canvas, 2, $black, '3. Verify success message', 60, $instructionsY + 125);
        $this->addText($canvas, 2, $warning, 'CHECK-OUT:', 40, $instructionsY + 155);
        $this->addText($canvas, 2, $black, '1. Switch app to CHECK-OUT', 60, $instructionsY + 180);
        $this->addText($canvas, 2, $black, '2. Scan same QR code on exit', 60, $instructionsY + 200);
        $this->addText($canvas, 2, $danger, 'WARNING:', 40, $instructionsY + 230);
        $this->addText($canvas, 1, $grayText, 'Only scan at ' . $post->name . ' • Do not share', 60, $instructionsY + 255);

        $isPermanent = $qrCode->code_type === 'static' && $qrCode->expires_at === null;
        if ($isPermanent) {
            $this->addCenteredText($canvas, 2, $success, 'Permanent QR code — never expires', $canvasW / 2, $instructionsY + 300);
        } elseif ($qrCode->expires_at) {
            $exp = Carbon::parse($qrCode->expires_at);
            $color = $exp->isPast() ? $danger : $warning;
            $status = $exp->isPast() ? 'EXPIRED' : 'Valid until: ' . $exp->format('d M Y H:i');
            $this->addCenteredText($canvas, 2, $color, $status, $canvasW / 2, $instructionsY + 300);
        }

        $this->addCenteredText($canvas, 1, $grayText,
            'Code: ' . $qrCode->code . ' | Generated: ' . $qrCode->created_at->format('Y-m-d H:i'),
            $canvasW / 2, $canvasH - 20);

        ob_start();
        imagepng($canvas);
        $data = ob_get_clean();
        imagedestroy($qrImage);
        imagedestroy($canvas);

        $filename = 'attendance_qr_' . $post->code . '_' . date('Y-m-d') . '.png';
        return response($data)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function downloadQrCodeAsPdf($qrCode, $post)
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)
            && !app()->bound('PDF')) {
            return back()->with('error', 'PDF export not configured.');
        }

        $html = view('security.posts.qr-codes.print-instructions', compact('qrCode', 'post'))->render();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

        return $pdf->download('attendance_qr_' . $post->code . '_' . date('Y-m-d') . '.pdf');
    }

    // ============================================================
    // EXPORT
    // ============================================================

    public function export($postId)
    {
        $this->authorizeManage();
        $post = SecurityPost::findOrFail($postId);
        $this->assertPostAccessible($post);

        $qrCodes = PostQrCode::where('post_id', $post->id)->orderByDesc('created_at')->get();

        $hasLogs = DB::getSchemaBuilder()->hasTable('verification_logs');
        $logColumns = $hasLogs ? DB::getSchemaBuilder()->getColumnListing('verification_logs') : [];
        $refColumn = null;
        foreach (['qr_code_id', 'verification_code', 'code', 'qr_code'] as $c) {
            if (in_array($c, $logColumns)) { $refColumn = $c; break; }
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"qr_codes_{$post->code}_" . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($qrCodes, $post, $hasLogs, $refColumn) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, [
                'ID','Name','Code','Type','Status','Permanent','Uses',
                'Max Uses','Expires','Created','Created By','Last Used','Download URL',
            ]);
            foreach ($qrCodes as $qr) {
                $meta = is_array($qr->metadata) ? $qr->metadata : [];
                $isPerm = $qr->code_type === 'static' && $qr->expires_at === null;

                $lastUsed = null;
                if ($hasLogs && $refColumn) {
                    $col = $refColumn;
                    $val = $col === 'qr_code_id' ? $qr->id : $qr->code;
                    $lastUsed = DB::table('verification_logs')->where($col, $val)->max('created_at');
                }

                fputcsv($f, [
                    $qr->id, $qr->name, $qr->code, $qr->code_type,
                    $qr->is_active ? 'Active' : 'Inactive',
                    $isPerm ? 'Yes' : 'No',
                    $qr->uses_count, $qr->max_uses ?? 'Unlimited',
                    $qr->expires_at ? Carbon::parse($qr->expires_at)->toDateTimeString() : 'Never',
                    $qr->created_at->toDateTimeString(),
                    $meta['generated_by'] ?? 'System',
                    $lastUsed ? Carbon::parse($lastUsed)->toDateTimeString() : 'Never',
                    route('security.posts.qr-codes.download', [
                        'securityPost' => $post->id, 'qrCode' => $qr->id,
                    ]),
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ============================================================
    // INTERNALS — QR GENERATION
    // ============================================================

    private function generateUniqueQrCode(): string
    {
        $code = 'QR-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(8));
        while (PostQrCode::where('code', $code)->exists()) {
            $code = 'QR-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(8));
        }
        return $code;
    }

    private function resolveCodeConstraints(Request $request): array
    {
        $expiresAt = null;
        $maxUses = null;

        switch ($request->code_type) {
            case 'one_time':
                $maxUses = 1;
                break;
            case 'time_based':
                $expiresAt = $request->filled('expires_at')
                    ? Carbon::parse($request->expires_at) : null;
                $maxUses = $request->filled('max_uses') ? (int) $request->max_uses : null;
                break;
            case 'static':
                $maxUses = $request->filled('max_uses') ? (int) $request->max_uses : null;
                break;
        }
        return [$expiresAt, $maxUses];
    }

    private function generateQrCodeImage($qrCode, $request = null)
    {
        $metadata = is_array($qrCode->metadata) ? $qrCode->metadata : [];
        $config = $metadata['config'] ?? [
            'size' => (int) ($request->size ?? 300),
            'foreground_color' => $request->foreground_color ?? '#000000',
            'background_color' => $request->background_color ?? '#ffffff',
            'style' => $request->style ?? 'square',
            'format' => 'png',
        ];

        $qrDataArray = [
            't' => 'sc',
            'p' => $qrCode->post_id,
            'c' => $qrCode->code,
            'n' => substr($qrCode->name, 0, 20),
            'vf' => $qrCode->created_at->timestamp,
            'v' => '1',
        ];
        if ($qrCode->code_type === 'one_time') $qrDataArray['ot'] = 1;
        if ($qrCode->code_type === 'time_based' && $qrCode->expires_at) {
            $qrDataArray['ve'] = $qrCode->expires_at->timestamp;
        } elseif ($qrCode->code_type === 'static') {
            $qrDataArray['perm'] = 1;
        }

        $qrData = json_encode($qrDataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $fg = $this->hexToRgb($config['foreground_color']);
            $bg = $this->hexToRgb($config['background_color']);

            $version = $this->calculateOptimalVersion(strlen($qrData) * 8);
            $moduleCount = 4 * $version + 17;
            $margin = 4;
            $totalModules = $moduleCount + 2 * $margin;
            $moduleSize = max(1, (int) ($config['size'] / $totalModules));

            $options = new QROptions([
                'version' => $version,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_H,
                'scale' => $moduleSize,
                'imageBase64' => false,
                'bgColor' => [$bg['r'], $bg['g'], $bg['b']],
                'imageTransparent' => false,
                'drawLightModules' => true,
                'drawCircularModules' => in_array($config['style'], ['dot', 'round'], true),
                'circleRadius' => $config['style'] === 'dot' ? 0.4 : ($config['style'] === 'round' ? 0.5 : 0.0),
                'moduleValues' => [
                    'dark' => [$fg['r'], $fg['g'], $fg['b']],
                    'light' => [$bg['r'], $bg['g'], $bg['b']],
                ],
            ]);

            return (new QRCode($options))->render($qrData);
        } catch (\Throwable $e) {
            Log::error('QR generation failed, using fallback', ['error' => $e->getMessage()]);

            $options = new QROptions([
                'version' => 10,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_H,
                'scale' => 10,
                'imageBase64' => false,
            ]);
            return (new QRCode($options))->render($qrCode->post_id . '|' . $qrCode->code);
        }
    }

    private function calculateOptimalVersion(int $dataBits): int
    {
        $capacity = [
            1 => 80, 2 => 128, 3 => 208, 4 => 288, 5 => 368,
            6 => 480, 7 => 592, 8 => 688, 9 => 800, 10 => 912,
            11 => 1024, 12 => 1152, 13 => 1280, 14 => 1408, 15 => 1536,
            16 => 1664, 17 => 1792, 18 => 1920, 19 => 2048, 20 => 2176,
            21 => 2304, 22 => 2432, 23 => 2560, 24 => 2688, 25 => 2816,
            26 => 2944, 27 => 3072, 28 => 3200, 29 => 3328, 30 => 3456,
            31 => 3584, 32 => 3712, 33 => 3840, 34 => 3968, 35 => 4096,
            36 => 4224, 37 => 4352, 38 => 4480, 39 => 4608, 40 => 4736,
        ];
        foreach ($capacity as $v => $bits) if ($bits >= $dataBits) return $v;
        return 40;
    }

    private function storeQrCodeImage($qrCode, $image): string
    {
        $path = 'qr-codes/' . date('Y/m/d');
        $filename = 'qr_' . $qrCode->post_id . '_' . $qrCode->id . '_' . time() . '.png';
        $full = "{$path}/{$filename}";
        Storage::disk('public')->put($full, $image);
        return $full;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
        }
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function usageStats($qrCode): object
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('verification_logs')) {
                return (object) ['total_uses' => 0, 'unique_users' => 0, 'last_used_at' => null];
            }

            $cols = DB::getSchemaBuilder()->getColumnListing('verification_logs');
            $refCol = null;
            foreach (['qr_code_id', 'verification_code', 'code', 'qr_code'] as $c) {
                if (in_array($c, $cols)) { $refCol = $c; break; }
            }
            if (!$refCol) {
                return (object) ['total_uses' => 0, 'unique_users' => 0, 'last_used_at' => null];
            }

            $val = $refCol === 'qr_code_id' ? $qrCode->id : $qrCode->code;
            return DB::table('verification_logs')->where($refCol, $val)->select(
                DB::raw('COUNT(*) as total_uses'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('MAX(created_at) as last_used_at')
            )->first() ?: (object) ['total_uses' => 0, 'unique_users' => 0, 'last_used_at' => null];
        } catch (\Throwable $e) {
            Log::error('usageStats failed: ' . $e->getMessage());
            return (object) ['total_uses' => 0, 'unique_users' => 0, 'last_used_at' => null];
        }
    }

    private function clearQrCodeCache($qrCode): void
    {
        try {
            Cache::forget("qr_code_{$qrCode->id}");
            Cache::forget("qr_code_{$qrCode->code}");
            Cache::forget("qr_code_{$qrCode->id}_stats");
            Cache::forget("post_{$qrCode->post_id}_qr_codes");
        } catch (\Throwable $e) {
            Log::debug('Cache clear failed: ' . $e->getMessage());
        }
    }

    private function addCenteredText($image, $size, $color, $text, $centerX, $y): void
    {
        $approxWidth = strlen($text) * ($size * 6);
        $this->addText($image, $size, $color, $text, $centerX - ($approxWidth / 2), $y);
    }

    private function addText($image, $size, $color, $text, $x, $y): void
    {
        $lines = explode("\n", wordwrap($text, 60, "\n"));
        $lineH = $size * 4 + 4;
        foreach ($lines as $i => $line) {
            imagestring($image, $this->mapFontSize($size), (int) $x, (int) ($y + $i * $lineH), $line, $color);
        }
    }

    private function mapFontSize($size): int
    {
        if ($size <= 1) return 1;
        if ($size <= 2) return 2;
        if ($size <= 3) return 3;
        if ($size <= 4) return 4;
        return 5;
    }
}