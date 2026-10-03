<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\NewTestimonialSubmitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TestimonialController extends Controller
{
    /**
     * Display approved testimonials for homepage (API endpoint)
     * 
     * @param int $limit
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovedTestimonials($limit = 6)
    {
        try {
            $testimonials = Testimonial::approved()
                ->with('user')
                ->ordered()
                ->limit(min($limit, 20))
                ->get();
            
            $formattedTestimonials = $testimonials->map(function($testimonial) {
                return [
                    'id' => $testimonial->id,
                    'name' => $testimonial->name,
                    'email' => $testimonial->email,
                    'role' => $testimonial->role,
                    'content' => $testimonial->content,
                    'rating' => $testimonial->rating,
                    'avatar_url' => $testimonial->avatar_url,
                    'property_location' => $testimonial->property_location,
                    'created_at' => $testimonial->created_at->format('M j, Y'),
                    'is_featured' => $testimonial->is_featured,
                    'user' => $testimonial->user ? [
                        'id' => $testimonial->user->id,
                        'name' => $testimonial->user->name,
                        'type' => $testimonial->user->getTypeName(),
                        'avatar_url' => $testimonial->user->avatar_url,
                        'role_name' => $testimonial->user->getRoleName(),
                    ] : null,
                ];
            });
            
            $stats = [
                'total' => Testimonial::approved()->count(),
                'average_rating' => round(Testimonial::approved()->avg('rating') ?? 0, 1),
                'total_ratings' => Testimonial::approved()->whereNotNull('rating')->count(),
                'five_star_count' => Testimonial::approved()->where('rating', 5)->count(),
                'four_star_count' => Testimonial::approved()->where('rating', 4)->count(),
                'three_star_count' => Testimonial::approved()->where('rating', 3)->count(),
                'two_star_count' => Testimonial::approved()->where('rating', 2)->count(),
                'one_star_count' => Testimonial::approved()->where('rating', 1)->count(),
            ];
            
            return response()->json([
                'success' => true,
                'testimonials' => $formattedTestimonials,
                'stats' => $stats,
                'count' => $formattedTestimonials->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch testimonials: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load testimonials',
                'testimonials' => [],
                'stats' => []
            ], 500);
        }
    }

    /**
     * Submit a new testimonial (Public endpoint - used by homepage modal)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'role' => 'nullable|string|max:100',
                'content' => 'required|string|min:20|max:2000',
                'rating' => 'nullable|integer|min:1|max:5',
                'property_location' => 'nullable|string|max:255',
                'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $this->uploadAvatar($request->file('avatar'));
            }

            $testimonialData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'content' => $request->content,
                'rating' => $request->rating ?? 5,
                'avatar' => $avatarPath,
                'property_location' => $request->property_location,
                'is_approved' => false,
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'submitted_at' => now()->toISOString(),
                    'submission_method' => 'web_form'
                ]
            ];
            
            if (auth()->check()) {
                $user = auth()->user();
                $testimonialData['user_id'] = $user->id;
                
                if (empty($testimonialData['email'])) {
                    $testimonialData['email'] = $user->email;
                }
                if (empty($testimonialData['name'])) {
                    $testimonialData['name'] = $user->name;
                }
                if (empty($testimonialData['role'])) {
                    $testimonialData['role'] = $user->getRoleName();
                }
                
                $testimonialData['metadata']['user_authenticated'] = true;
                $testimonialData['metadata']['user_type'] = $user->type;
                $testimonialData['metadata']['user_type_name'] = $user->getTypeName();
            } else {
                $testimonialData['metadata']['user_authenticated'] = false;
            }

            $testimonial = Testimonial::create($testimonialData);
            
            DB::commit();

            $this->notifyAdmin($testimonial);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback! Your testimonial will be reviewed and published soon.',
                'testimonial' => [
                    'id' => $testimonial->id,
                    'name' => $testimonial->name,
                    'rating' => $testimonial->rating,
                    'created_at' => $testimonial->created_at->format('M j, Y')
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit testimonial: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit testimonial. Please try again later.'
            ], 500);
        }
    }

    /**
     * ============================================
     * DASHBOARD METHODS (For Authenticated Users)
     * ============================================
     */

    /**
     * Display user's own testimonials (Dashboard)
     * 
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function userTestimonials()
    {
        $user = auth()->user();
        
        $testimonials = Testimonial::where('user_id', $user->id)
            ->with(['user', 'approver'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        $stats = [
            'total' => Testimonial::where('user_id', $user->id)->count(),
            'approved' => Testimonial::where('user_id', $user->id)->where('is_approved', true)->count(),
            'pending' => Testimonial::where('user_id', $user->id)->where('is_approved', false)->count(),
            'avg_rating' => round(Testimonial::where('user_id', $user->id)
                ->where('is_approved', true)
                ->avg('rating') ?? 0, 1),
        ];
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'testimonials' => $testimonials,
                'stats' => $stats
            ]);
        }
        
        return view('dashboard.testimonials.index', compact('testimonials', 'stats'));
    }

    /**
     * Show a specific testimonial (Dashboard)
     * 
     * @param int $id
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function showUserTestimonial($id)
    {
        $testimonial = Testimonial::where('user_id', auth()->id())
            ->findOrFail($id);
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'testimonial' => [
                    'id' => $testimonial->id,
                    'name' => $testimonial->name,
                    'rating' => $testimonial->rating,
                    'content' => $testimonial->content,
                    'role' => $testimonial->role,
                    'created_at' => $testimonial->created_at->format('M d, Y'),
                    'is_approved' => $testimonial->is_approved,
                    'is_featured' => $testimonial->is_featured,
                ]
            ]);
        }
        
        return view('dashboard.testimonials.show', compact('testimonial'));
    }

    /**
     * Store a new testimonial from dashboard (Authenticated users)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFromDashboard(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|min:20|max:2000',
                'rating' => 'required|integer|min:1|max:5',
                'property_location' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            $user = auth()->user();
            
            $testimonialData = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleName(),
                'content' => $request->content,
                'rating' => $request->rating,
                'property_location' => $request->property_location,
                'is_approved' => false,
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'submitted_at' => now()->toISOString(),
                    'submission_method' => 'dashboard',
                    'user_authenticated' => true,
                    'user_type' => $user->type,
                    'user_type_name' => $user->getTypeName(),
                    'user_id' => $user->id
                ]
            ];

            $testimonial = Testimonial::create($testimonialData);
            
            DB::commit();

            $this->notifyAdmin($testimonial);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback! Your testimonial will be reviewed and published soon.',
                'testimonial' => [
                    'id' => $testimonial->id,
                    'name' => $testimonial->name,
                    'rating' => $testimonial->rating,
                    'created_at' => $testimonial->created_at->format('M j, Y')
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit testimonial from dashboard: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit testimonial. Please try again later.'
            ], 500);
        }
    }

    /**
     * Update a testimonial (Dashboard - only if not approved)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUserTestimonial(Request $request, $id)
    {
        try {
            $testimonial = Testimonial::where('user_id', auth()->id())
                ->where('is_approved', false)
                ->findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|min:20|max:2000',
                'rating' => 'required|integer|min:1|max:5',
                'property_location' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $testimonial->update([
                'content' => $request->content,
                'rating' => $request->rating,
                'property_location' => $request->property_location,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonial updated successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update testimonial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update testimonial.'
            ], 500);
        }
    }

    /**
     * Delete a testimonial (Dashboard - only if not approved)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyUserTestimonial($id)
    {
        try {
            $testimonial = Testimonial::where('user_id', auth()->id())
                ->where('is_approved', false)
                ->findOrFail($id);
            
            $testimonial->delete(); // Soft delete
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonial deleted successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete testimonial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete testimonial. Only pending testimonials can be deleted.'
            ], 500);
        }
    }

    /**
     * Get user's testimonial statistics (Dashboard)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserStatistics()
    {
        try {
            $user = auth()->user();
            
            $stats = [
                'total' => Testimonial::where('user_id', $user->id)->count(),
                'approved' => Testimonial::where('user_id', $user->id)->where('is_approved', true)->count(),
                'pending' => Testimonial::where('user_id', $user->id)->where('is_approved', false)->count(),
                'featured' => Testimonial::where('user_id', $user->id)->where('is_featured', true)->count(),
                'average_rating' => round(Testimonial::where('user_id', $user->id)
                    ->where('is_approved', true)
                    ->avg('rating') ?? 0, 1),
                'rating_distribution' => [
                    5 => Testimonial::where('user_id', $user->id)->where('rating', 5)->count(),
                    4 => Testimonial::where('user_id', $user->id)->where('rating', 4)->count(),
                    3 => Testimonial::where('user_id', $user->id)->where('rating', 3)->count(),
                    2 => Testimonial::where('user_id', $user->id)->where('rating', 2)->count(),
                    1 => Testimonial::where('user_id', $user->id)->where('rating', 1)->count(),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get user statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics'
            ], 500);
        }
    }

    /**
     * ============================================
     * ADMIN METHODS (Existing)
     * ============================================
     */

    /**
     * Upload avatar image
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @return string|null
     */
    private function uploadAvatar($file)
    {
        try {
            $filename = 'testimonial_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('testimonials/avatars', $filename, 'public');
            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to upload avatar: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Admin: List all testimonials with filters
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = Testimonial::with(['user', 'approver']);
        
        if ($request->has('status')) {
            if ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'pending') {
                $query->where('is_approved', false);
            }
        }
        
        if ($request->has('featured')) {
            $query->where('is_featured', $request->featured === 'true');
        }
        
        if ($request->has('rating') && $request->rating) {
            $query->where('rating', $request->rating);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }
        
        $perPage = $request->input('per_page', 20);
        $testimonials = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        $stats = [
            'total' => Testimonial::count(),
            'approved' => Testimonial::where('is_approved', true)->count(),
            'pending' => Testimonial::where('is_approved', false)->count(),
            'featured' => Testimonial::where('is_featured', true)->count(),
            'average_rating' => round(Testimonial::where('is_approved', true)->avg('rating') ?? 0, 1),
            'total_ratings' => Testimonial::where('is_approved', true)->count(),
            'trashed' => Testimonial::onlyTrashed()->count(),
        ];
        
        return view('admin.testimonials.index', compact('testimonials', 'stats'));
    }

    /**
     * Admin: Show single testimonial details
     * 
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $testimonial = Testimonial::with(['user', 'approver'])->findOrFail($id);
        return view('admin.testimonials.show', compact('testimonial'));
    }

    /**
     * Admin: Approve testimonial (AJAX)
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id, Request $request)
    {
        try {
            $testimonial = Testimonial::findOrFail($id);
            
            $updateData = [
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => auth()->id()
            ];
            
            if ($request->has('feature') && $request->feature == 1) {
                $updateData['is_featured'] = true;
            }
            
            if ($request->has('admin_notes') && $request->admin_notes) {
                $metadata = $testimonial->metadata ?? [];
                $metadata['admin_notes'] = $request->admin_notes;
                $metadata['approved_notes'] = $request->admin_notes;
                $updateData['metadata'] = $metadata;
            }
            
            $testimonial->update($updateData);
            
            $this->notifyUserApproval($testimonial);
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonial approved successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to approve testimonial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve testimonial.'
            ], 500);
        }
    }

    /**
     * Admin: Reject testimonial (soft delete) - AJAX
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject($id, Request $request)
    {
        try {
            $testimonial = Testimonial::findOrFail($id);
            
            $reason = $request->input('rejection_reason', 'No specific reason provided');
            $metadata = $testimonial->metadata ?? [];
            $metadata['rejected_at'] = now()->toISOString();
            $metadata['rejected_by'] = auth()->id();
            $metadata['rejection_reason'] = $reason;
            $testimonial->metadata = $metadata;
            $testimonial->save();
            
            $testimonial->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonial rejected and moved to trash.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to reject testimonial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject testimonial.'
            ], 500);
        }
    }

    /**
     * Admin: Delete testimonial permanently (AJAX)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $testimonial = Testimonial::withTrashed()->findOrFail($id);
            
            if ($testimonial->avatar && Storage::disk('public')->exists($testimonial->avatar)) {
                Storage::disk('public')->delete($testimonial->avatar);
            }
            
            $testimonial->forceDelete();
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonial permanently deleted.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete testimonial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete testimonial.'
            ], 500);
        }
    }

    /**
     * Admin: Toggle featured status (AJAX)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleFeatured($id)
    {
        try {
            $testimonial = Testimonial::findOrFail($id);
            $testimonial->update(['is_featured' => !$testimonial->is_featured]);
            
            $status = $testimonial->is_featured ? 'featured' : 'unfeatured';
            return response()->json([
                'success' => true,
                'message' => "Testimonial {$status} successfully.",
                'is_featured' => $testimonial->is_featured
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to toggle featured status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update featured status.'
            ], 500);
        }
    }

    /**
     * Admin: Update display order (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrder(Request $request)
    {
        try {
            foreach ($request->orders as $order) {
                Testimonial::where('id', $order['id'])->update(['display_order' => $order['position']]);
            }
            
            return response()->json(['success' => true, 'message' => 'Order updated successfully.']);
            
        } catch (\Exception $e) {
            Log::error('Failed to update order: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update order.'], 500);
        }
    }

    /**
     * Admin: Bulk approve testimonials (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkApprove(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            $updateData = [
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => auth()->id()
            ];
            
            if ($request->has('feature') && $request->feature == 1) {
                $updateData['is_featured'] = true;
            }
            
            Testimonial::whereIn('id', $ids)->update($updateData);
            
            if ($request->has('admin_notes') && $request->admin_notes) {
                foreach ($ids as $id) {
                    $testimonial = Testimonial::find($id);
                    if ($testimonial) {
                        $metadata = $testimonial->metadata ?? [];
                        $metadata['admin_notes'] = $request->admin_notes;
                        $metadata['bulk_approved_notes'] = $request->admin_notes;
                        $metadata['bulk_approved_at'] = now()->toISOString();
                        $metadata['bulk_approved_by'] = auth()->id();
                        $testimonial->metadata = $metadata;
                        $testimonial->save();
                    }
                }
            }
            
            Log::info('Bulk approve testimonials', [
                'admin_id' => auth()->id(),
                'count' => count($ids),
                'testimonial_ids' => $ids
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' testimonials approved successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk approve: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve testimonials.'
            ], 500);
        }
    }

    /**
     * Admin: Bulk reject testimonials (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkReject(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            $rejectionReason = $request->input('rejection_reason', 'Bulk rejection by admin');
            $allowResubmission = $request->input('allow_resubmission', false);
            $resubmissionDays = $request->input('resubmission_days', 7);
            
            foreach ($ids as $id) {
                $testimonial = Testimonial::find($id);
                if ($testimonial) {
                    $metadata = $testimonial->metadata ?? [];
                    $metadata['rejected_at'] = now()->toISOString();
                    $metadata['rejected_by'] = auth()->id();
                    $metadata['rejection_reason'] = $rejectionReason;
                    $metadata['bulk_rejected'] = true;
                    
                    if ($allowResubmission) {
                        $metadata['allow_resubmission_until'] = now()->addDays($resubmissionDays)->toISOString();
                        $metadata['resubmission_days'] = $resubmissionDays;
                    }
                    
                    $testimonial->metadata = $metadata;
                    $testimonial->save();
                    $testimonial->delete();
                }
            }
            
            Log::info('Bulk reject testimonials', [
                'admin_id' => auth()->id(),
                'count' => count($ids),
                'testimonial_ids' => $ids,
                'reason' => $rejectionReason
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' testimonials rejected and moved to trash.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk reject: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject testimonials.'
            ], 500);
        }
    }

    /**
     * Admin: Bulk feature testimonials (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkFeature(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            Testimonial::whereIn('id', $ids)->update(['is_featured' => true]);
            
            Log::info('Bulk feature testimonials', [
                'admin_id' => auth()->id(),
                'count' => count($ids),
                'testimonial_ids' => $ids
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' testimonials featured successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk feature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to feature testimonials.'
            ], 500);
        }
    }

    /**
     * Admin: Bulk unfeature testimonials (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUnfeature(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            Testimonial::whereIn('id', $ids)->update(['is_featured' => false]);
            
            Log::info('Bulk unfeature testimonials', [
                'admin_id' => auth()->id(),
                'count' => count($ids),
                'testimonial_ids' => $ids
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' testimonials unfeatured successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk unfeature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unfeature testimonials.'
            ], 500);
        }
    }

    /**
     * Admin: Bulk soft delete (move to trash) - AJAX
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkSoftDelete(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            $deletionReason = $request->input('deletion_reason', 'Bulk move to trash by admin');
            
            foreach ($ids as $id) {
                $testimonial = Testimonial::find($id);
                if ($testimonial) {
                    $metadata = $testimonial->metadata ?? [];
                    $metadata['deleted_at'] = now()->toISOString();
                    $metadata['deleted_by'] = auth()->id();
                    $metadata['deletion_reason'] = $deletionReason;
                    $metadata['bulk_deleted'] = true;
                    $testimonial->metadata = $metadata;
                    $testimonial->save();
                    $testimonial->delete();
                }
            }
            
            Log::info('Bulk soft delete testimonials', [
                'admin_id' => auth()->id(),
                'count' => count($ids),
                'testimonial_ids' => $ids,
                'reason' => $deletionReason
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' testimonials moved to trash.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk soft delete: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to move testimonials to trash.'
            ], 500);
        }
    }

    /**
     * Admin: Bulk actions on testimonials (Legacy)
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkAction(Request $request)
    {
        try {
            $action = $request->action;
            $ids = $request->ids;
            
            if (empty($ids)) {
                return redirect()->back()->with('error', 'No testimonials selected.');
            }
            
            switch ($action) {
                case 'approve':
                    Testimonial::whereIn('id', $ids)->update([
                        'is_approved' => true,
                        'approved_at' => now(),
                        'approved_by' => auth()->id()
                    ]);
                    $message = count($ids) . ' testimonials approved successfully.';
                    break;
                    
                case 'reject':
                    Testimonial::whereIn('id', $ids)->delete();
                    $message = count($ids) . ' testimonials rejected and moved to trash.';
                    break;
                    
                case 'feature':
                    Testimonial::whereIn('id', $ids)->update(['is_featured' => true]);
                    $message = count($ids) . ' testimonials featured successfully.';
                    break;
                    
                case 'unfeature':
                    Testimonial::whereIn('id', $ids)->update(['is_featured' => false]);
                    $message = count($ids) . ' testimonials unfeatured successfully.';
                    break;
                    
                case 'delete':
                    $testimonials = Testimonial::whereIn('id', $ids)->get();
                    foreach ($testimonials as $testimonial) {
                        if ($testimonial->avatar && Storage::disk('public')->exists($testimonial->avatar)) {
                            Storage::disk('public')->delete($testimonial->avatar);
                        }
                    }
                    Testimonial::whereIn('id', $ids)->forceDelete();
                    $message = count($ids) . ' testimonials permanently deleted.';
                    break;
                    
                default:
                    return redirect()->back()->with('error', 'Invalid bulk action.');
            }
            
            return redirect()->back()->with('success', $message);
            
        } catch (\Exception $e) {
            Log::error('Failed to perform bulk action: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to perform bulk action.');
        }
    }

    /**
     * Admin: View trashed testimonials
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function trashed(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $testimonials = Testimonial::onlyTrashed()
            ->with(['user', 'approver'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);
        
        return view('admin.testimonials.trashed', compact('testimonials'));
    }

    /**
     * Admin: Restore trashed testimonial (AJAX)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            $testimonial = Testimonial::onlyTrashed()->findOrFail($id);
            $testimonial->restore();
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonial restored successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to restore testimonial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore testimonial.'
            ], 500);
        }
    }

    /**
     * Admin: Bulk restore trashed testimonials (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkRestore(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            Testimonial::onlyTrashed()->whereIn('id', $ids)->restore();
            
            Log::info('Bulk restore testimonials', [
                'admin_id' => auth()->id(),
                'count' => count($ids),
                'testimonial_ids' => $ids
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' testimonials restored successfully.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk restore: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore testimonials.'
            ], 500);
        }
    }

    /**
     * Admin: Empty entire trash (permanently delete all trashed testimonials)
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function emptyTrash()
    {
        try {
            $testimonials = Testimonial::onlyTrashed()->get();
            $count = $testimonials->count();
            
            foreach ($testimonials as $testimonial) {
                if ($testimonial->avatar && Storage::disk('public')->exists($testimonial->avatar)) {
                    Storage::disk('public')->delete($testimonial->avatar);
                }
                $testimonial->forceDelete();
            }
            
            Log::info('Empty trash completed', [
                'admin_id' => auth()->id(),
                'admin_name' => auth()->user()->name,
                'deleted_count' => $count
            ]);
            
            return redirect()->back()->with('success', "Successfully emptied trash. {$count} testimonial(s) permanently deleted.");
            
        } catch (\Exception $e) {
            Log::error('Failed to empty trash: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to empty trash. Please try again.');
        }
    }

    /**
     * Admin: Bulk permanently delete selected trashed testimonials (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkPermanentDelete(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No testimonials selected.'
                ], 422);
            }
            
            $testimonials = Testimonial::onlyTrashed()->whereIn('id', $ids)->get();
            $deletedCount = 0;
            
            foreach ($testimonials as $testimonial) {
                if ($testimonial->avatar && Storage::disk('public')->exists($testimonial->avatar)) {
                    Storage::disk('public')->delete($testimonial->avatar);
                }
                $testimonial->forceDelete();
                $deletedCount++;
            }
            
            Log::info('Bulk permanent delete completed', [
                'admin_id' => auth()->id(),
                'admin_name' => auth()->user()->name,
                'deleted_count' => $deletedCount,
                'testimonial_ids' => $ids
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} testimonial(s) permanently deleted.",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk permanent delete: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete testimonials. Please try again.'
            ], 500);
        }
    }

    /**
     * Admin: Export testimonials to CSV
     * 
     * @param string $format
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function export($format, Request $request)
    {
        try {
            $query = Testimonial::with(['user', 'approver']);
            
            if ($request->has('status')) {
                if ($request->status === 'approved') {
                    $query->where('is_approved', true);
                } elseif ($request->status === 'pending') {
                    $query->where('is_approved', false);
                }
            }
            
            if ($request->has('featured')) {
                $query->where('is_featured', $request->featured === 'true');
            }
            
            if ($request->has('rating') && $request->rating) {
                $query->where('rating', $request->rating);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%")
                      ->orWhere('role', 'like', "%{$search}%");
                });
            }
            
            $testimonials = $query->orderBy('created_at', 'desc')->get();
            
            if ($format === 'csv') {
                $filename = 'testimonials_export_' . date('Y-m-d_His') . '.csv';
                $handle = fopen('php://temp', 'w');
                
                fputcsv($handle, [
                    'ID', 'Name', 'Email', 'Role', 'Rating', 'Content', 
                    'Property Location', 'Status', 'Featured', 'Created At', 
                    'Approved At', 'IP Address', 'User Agent', 'User Type'
                ]);
                
                foreach ($testimonials as $testimonial) {
                    fputcsv($handle, [
                        $testimonial->id,
                        $testimonial->name,
                        $testimonial->email,
                        $testimonial->role,
                        $testimonial->rating,
                        $testimonial->content,
                        $testimonial->property_location,
                        $testimonial->is_approved ? 'Approved' : 'Pending',
                        $testimonial->is_featured ? 'Yes' : 'No',
                        $testimonial->created_at,
                        $testimonial->approved_at,
                        $testimonial->metadata['ip_address'] ?? 'N/A',
                        $testimonial->metadata['user_agent'] ?? 'N/A',
                        $testimonial->user_id ? 'Registered' : 'Guest'
                    ]);
                }
                
                rewind($handle);
                $csvContent = stream_get_contents($handle);
                fclose($handle);
                
                return response($csvContent, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Unsupported export format.'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Failed to export testimonials: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export testimonials.'
            ], 500);
        }
    }

    /**
     * Get testimonial statistics for admin dashboard
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics()
    {
        try {
            $stats = [
                'total' => Testimonial::count(),
                'approved' => Testimonial::where('is_approved', true)->count(),
                'pending' => Testimonial::where('is_approved', false)->count(),
                'featured' => Testimonial::where('is_featured', true)->count(),
                'trashed' => Testimonial::onlyTrashed()->count(),
                'average_rating' => round(Testimonial::where('is_approved', true)->avg('rating') ?? 0, 1),
                'rating_distribution' => [
                    5 => Testimonial::where('rating', 5)->count(),
                    4 => Testimonial::where('rating', 4)->count(),
                    3 => Testimonial::where('rating', 3)->count(),
                    2 => Testimonial::where('rating', 2)->count(),
                    1 => Testimonial::where('rating', 1)->count(),
                ],
                'submissions_by_month' => Testimonial::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                    ->groupBy('month')
                    ->orderBy('month', 'desc')
                    ->limit(6)
                    ->get(),
                'authenticated_vs_guest' => [
                    'authenticated' => Testimonial::whereNotNull('user_id')->count(),
                    'guest' => Testimonial::whereNull('user_id')->count(),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics'
            ], 500);
        }
    }

    /**
     * Notify admin about new testimonial (in-app only)
     * 
     * @param Testimonial $testimonial
     * @return void
     */
    private function notifyAdmin($testimonial)
    {
        try {
            $admins = User::whereIn('type', [
                User::TYPE_SUPER_ADMIN,
                User::TYPE_ADMIN,
                User::TYPE_DEVELOPER
            ])->where('status', User::STATUS_ACTIVE)->get();
            
            Log::info('Found admins to notify for testimonial:', [
                'testimonial_id' => $testimonial->id,
                'admin_count' => $admins->count(),
                'admin_ids' => $admins->pluck('id')->toArray()
            ]);
            
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewTestimonialSubmitted($testimonial));
                
                $notificationCount = \DB::table('notifications')
                    ->where('type', 'App\Notifications\NewTestimonialSubmitted')
                    ->where('created_at', '>=', now()->subMinutes(1))
                    ->count();
                
                Log::info('New testimonial notification sent to admins', [
                    'testimonial_id' => $testimonial->id,
                    'admin_count' => $admins->count(),
                    'name' => $testimonial->name,
                    'rating' => $testimonial->rating,
                    'notifications_created' => $notificationCount
                ]);
            } else {
                Log::warning('No active admins found to notify for testimonial', [
                    'testimonial_id' => $testimonial->id,
                    'name' => $testimonial->name
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification for testimonial: ' . $e->getMessage(), [
                'testimonial_id' => $testimonial->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Notify user that their testimonial was approved
     * 
     * @param Testimonial $testimonial
     * @return void
     */
    private function notifyUserApproval($testimonial)
    {
        try {
            if ($testimonial->email) {
                Log::info('Testimonial approval notification sent', [
                    'testimonial_id' => $testimonial->id,
                    'email' => $testimonial->email
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send user approval notification: ' . $e->getMessage());
        }
    }
}