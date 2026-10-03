<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\WorkerBadge;
use App\Services\BadgeService;
use Illuminate\Http\Request;

class BadgeVerificationController extends Controller
{
    protected $badgeService;

    public function __construct(BadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

    /**
     * Public page for verifying worker badges
     * 
     * @param string $code The QR code identifier
     * @return \Illuminate\View\View
     */
    public function verify($code)
    {
        // Get badge info for the view
        $badge = WorkerBadge::where('qr_code', $code)->first();
        
        if (!$badge) {
            abort(404, 'Invalid badge code');
        }

        // Show verification page with the code and badge info
        return view('public.verify-badge', compact('code', 'badge'));
    }

    /**
     * API endpoint for QR code verification
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyBadge(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'post_id' => 'nullable|exists:security_posts,id',
        ]);

        // Rate limiting (optional)
        $result = $this->badgeService->verifyBadge(
            $request->code,
            $request->post_id,
            auth()->id()
        );

        return response()->json($result);
    }

    /**
     * Get badge info (public preview)
     * 
     * @param string $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBadgeInfo($code)
    {
        $badge = WorkerBadge::where('qr_code', $code)->first();

        if (!$badge) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid badge'
            ], 404);
        }

        // Return limited info for public view
        return response()->json([
            'success' => true,
            'badge' => [
                'worker_name' => $badge->worker->full_name,
                'worker_photo' => $badge->photo_path ? asset($badge->photo_path) : null,
                'trade' => $badge->worker->trade,
                'contract_number' => $badge->contract->contract_number,
                'valid_from' => $badge->valid_from ? $badge->valid_from->format('d M Y') : null,
                'valid_until' => $badge->valid_until ? $badge->valid_until->format('d M Y') : null,
                'status' => $badge->status,
                'verification_url' => $badge->getVerificationUrl(),
            ]
        ]);
    }

    /**
     * Bulk verify multiple badges (for security posts)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkVerifyBadges(Request $request)
    {
        $request->validate([
            'codes' => 'required|array',
            'codes.*' => 'required|string',
            'post_id' => 'nullable|exists:security_posts,id',
        ]);

        $results = [];
        $verified = 0;
        $failed = 0;

        foreach ($request->codes as $code) {
            $result = $this->badgeService->verifyBadge(
                $code,
                $request->post_id,
                auth()->id()
            );

            if ($result['success']) {
                $verified++;
            } else {
                $failed++;
            }

            $results[] = $result;
        }

        return response()->json([
            'success' => true,
            'verified' => $verified,
            'failed' => $failed,
            'results' => $results,
            'message' => "Verified {$verified} badges, {$failed} failed."
        ]);
    }
}