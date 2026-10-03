<?php

namespace App\Http\Controllers;

use App\Notifications\GeneralNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    /**
     * Maximum number of notifications per page
     */
    const MAX_PER_PAGE = 100;

    /**
     * Default notifications per page
     */
    const DEFAULT_PER_PAGE = 20;

    /**
     * Rate limit key prefix
     */
    const RATE_LIMIT_PREFIX = 'notification_action:';

    /**
     * Helper method to return clean JSON response.
     * Prevents BOM and stray output from corrupting the JSON body.
     */
    protected function cleanJsonResponse($data, $status = 200)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $jsonString = json_encode($data);

        // ✅ FIX: strip a leading BOM if json_encode somehow included one
        if ($jsonString !== false && str_starts_with($jsonString, "\xEF\xBB\xBF")) {
            $jsonString = substr($jsonString, 3);
        }

        return response($jsonString, $status, [
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Get all notifications for the authenticated user (role-aware)
     */
    public function index(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request, redirect()->route('login'));
        }

        $currentRole = $this->resolveCurrentRole($request, $user);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:' . self::MAX_PER_PAGE,
            'category' => 'nullable|string|max:255',
            'priority' => 'nullable|integer|min:0|max:3',
            'read' => 'nullable|in:true,false,all',
            'search' => 'nullable|string|max:500',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'role' => 'nullable|string',
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $notificationsQuery = $this->getRoleBasedNotifications($user, $currentRole);

        $notifications = $notificationsQuery
            ->when($request->has('category'), fn (Builder $q) => $this->applyCategoryFilter($q, $request->category))
            ->when($request->has('priority'), fn (Builder $q) => $this->applyPriorityFilter($q, $request->priority))
            ->when(
                $request->has('read') && $request->read !== 'all',
                fn (Builder $q) => $request->read === 'true'
                    ? $q->whereNotNull('read_at')
                    : $q->whereNull('read_at')
            )
            ->when($request->has('search'), fn (Builder $q) => $this->applySearchFilter($q, $request->search))
            ->when($request->has('from_date'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->has('to_date'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $stats = $this->getRoleBasedNotificationStats($user, $currentRole);

        // Auto-mark seen only for browser (non-JSON) page views of the index
        if (!$request->expectsJson() && $request->routeIs('notifications.index')) {
            $this->markAllAsSeen($user);
        }

        $formattedNotifications = $notifications->map(
            fn ($n) => $this->formatNotification($n, $currentRole)
        );

        if ($request->expectsJson()) {
            return $this->cleanJsonResponse([
                'success' => true,
                'data' => [
                    'notifications' => $formattedNotifications,
                ],
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'last_page' => $notifications->lastPage(),
                    'current_role' => $currentRole,
                ],
                'stats' => $stats,
            ]);
        }

        return view('notifications.index', compact(
            'notifications',
            'formattedNotifications',
            'stats',
            'currentRole'
        ));
    }

    /**
     * Role-based notifications query (returns a plain Builder).
     *
     * ✅ FIX: re-uses the base query so caller can chain filters, updates, deletes.
     *         If no role resolved, logs a warning and returns all notifications
     *         (rather than hiding everything).
     */
    private function getRoleBasedNotifications($user, ?string $roleSlug): Builder
    {
        if (!$roleSlug) {
            $roleSlug = $this->inferUserRole($user);
        }

        // ✅ FIX: always start from a fresh query builder
        $query = $user->notifications()->getQuery();

        if (!$roleSlug) {
            Log::warning('NotificationController: no role resolved, showing all notifications', [
                'user_id' => $user->id,
            ]);

            return $query;
        }

        return $query->where(function ($q) use ($roleSlug) {
            $q->whereNull('data->roles')
              ->orWhereJsonContains('data->roles', 'all')
              ->orWhereJsonContains('data->roles', $roleSlug);
        });
    }

    /**
     * Role-based notification stats
     */
    private function getRoleBasedNotificationStats($user, ?string $roleSlug): array
    {
        $query = $this->getRoleBasedNotifications($user, $roleSlug);

        return [
            'total' => (clone $query)->count(),
            'unread' => (clone $query)->whereNull('read_at')->count(),
            'read' => (clone $query)->whereNotNull('read_at')->count(),
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'by_category' => $this->getRoleBasedCategoryStats($user, $roleSlug),
            'by_priority' => $this->getRoleBasedPriorityStats($user, $roleSlug),
        ];
    }

    /**
     * Role-based category stats (column or JSON fallback)
     */
    private function getRoleBasedCategoryStats($user, ?string $roleSlug): array
    {
        $query = $this->getRoleBasedNotifications($user, $roleSlug);

        try {
            if (Schema::hasColumn('notifications', 'category')) {
                return (clone $query)
                    ->select('category', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('category')
                    ->groupBy('category')
                    ->orderByDesc('count')
                    ->get()
                    ->pluck('count', 'category')
                    ->toArray();
            }

            $raw = (clone $query)
                ->select(DB::raw("data->>'$.category' as category"), DB::raw('COUNT(*) as count'))
                ->whereNotNull(DB::raw("data->>'$.category'"))
                ->groupBy(DB::raw("data->>'$.category'"))
                ->orderByDesc('count')
                ->get();

            $categories = [];
            foreach ($raw as $item) {
                if ($item->category) {
                    $categories[$item->category] = (int) $item->count;
                }
            }

            return $categories;
        } catch (\Throwable $e) {
            Log::error('Error getting role-based category stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Role-based priority stats (column or JSON fallback)
     */
    private function getRoleBasedPriorityStats($user, ?string $roleSlug): array
    {
        $query = $this->getRoleBasedNotifications($user, $roleSlug);

        try {
            if (Schema::hasColumn('notifications', 'priority')) {
                return (clone $query)
                    ->select('priority', DB::raw('COUNT(*) as count'))
                    ->groupBy('priority')
                    ->orderByDesc('priority')
                    ->get()
                    ->pluck('count', 'priority')
                    ->mapWithKeys(fn ($count, $priority) => ["priority_{$priority}" => (int) $count])
                    ->toArray();
            }

            $raw = (clone $query)
                ->select(DB::raw("data->>'$.priority' as priority"), DB::raw('COUNT(*) as count'))
                ->whereNotNull(DB::raw("data->>'$.priority'"))
                ->groupBy(DB::raw("data->>'$.priority'"))
                ->orderByDesc('priority')
                ->get();

            $priorities = [];
            foreach ($raw as $item) {
                if ($item->priority !== null) {
                    $priorities["priority_{$item->priority}"] = (int) $item->count;
                }
            }

            return $priorities;
        } catch (\Throwable $e) {
            Log::error('Error getting role-based priority stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Mark role-based notifications as read
     */
    private function markRoleBasedNotificationsAsRead($user, ?string $roleSlug): int
    {
        return $this->getRoleBasedNotifications($user, $roleSlug)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
    }

    /**
     * Clear role-based notifications
     */
    private function clearRoleBasedNotifications($user, ?string $roleSlug): int
    {
        return $this->getRoleBasedNotifications($user, $roleSlug)->delete();
    }

    /**
     * GET /api/notifications/count  →  unread + unseen counts (role-aware)
     */
    public function unreadCount(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->cleanJsonResponse([
                'success' => false,
                'unread_count' => 0,
                'unseen_count' => 0,
                'total' => 0,
                'has_unread' => false,
                'has_unseen' => false,
                'current_role' => null,
            ], 401);
        }

        $currentRole = $this->resolveCurrentRole($request, $user);
        $query = $this->getRoleBasedNotifications($user, $currentRole);

        $unreadCount = (clone $query)->whereNull('read_at')->count();

        $unseenCount = 0;
        if (Schema::hasColumn('notifications', 'seen_at')) {
            $unseenCount = (clone $query)->whereNull('seen_at')->count();
        }

        return $this->cleanJsonResponse([
            'success' => true,
            'unread_count' => (int) $unreadCount,
            'unseen_count' => (int) $unseenCount,
            'total' => (int) $query->count(),
            'has_unread' => $unreadCount > 0,
            'has_unseen' => $unseenCount > 0,
            'current_role' => $currentRole,
        ]);
    }

    /**
     * GET /api/notifications/recent  →  dropdown payload (role-aware)
     *
     * ✅ FIX: response shape matches index() — nested under `data.notifications`,
     *         plus flat `notifications` for back-compat with older JS.
     * ✅ FIX: 401 now returns JSON (never an HTML redirect).
     */
    public function recent(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->cleanJsonResponse([
                'success' => false,
                'data' => ['notifications' => []],
                'notifications' => [],
                'meta' => ['has_more' => false, 'total_unread' => 0, 'current_role' => null],
                'has_more' => false,
                'total_unread' => 0,
                'message' => __('Unauthorized. Please log in.'),
            ], 401);
        }

        $currentRole = $this->resolveCurrentRole($request, $user);
        $limit = max(1, min((int) ($request->query('limit', 10)), 50));

        $notifications = $this->getRoleBasedNotifications($user, $currentRole)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => $this->formatNotification($n, $currentRole))
            ->values();

        $unreadCount = $this->getRoleBasedNotifications($user, $currentRole)
            ->whereNull('read_at')
            ->count();

        return $this->cleanJsonResponse([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
            ],
            'meta' => [
                'has_more' => $notifications->count() === $limit,
                'total_unread' => (int) $unreadCount,
                'current_role' => $currentRole,
            ],
            // Back-compat flat keys
            'notifications' => $notifications,
            'has_more' => $notifications->count() === $limit,
            'total_unread' => (int) $unreadCount,
            'current_role' => $currentRole,
        ]);
    }

    /**
     * Show a single notification (auto-marks as read)
     */
    public function show(Request $request, string $id)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request, redirect()->route('login'));
        }

        $currentRole = $this->resolveCurrentRole($request, $user);

        $notification = $this->getRoleBasedNotifications($user, $currentRole)
            ->where('id', $id)
            ->firstOrFail();

        if (is_null($notification->read_at)) {
            $this->rateLimit('mark_read', $user->id);
            $notification->markAsRead();

            Log::info('Notification auto-marked as read on view', [
                'user_id' => $user->id,
                'notification_id' => $id,
                'type' => $notification->type,
                'role' => $currentRole,
            ]);
        }

        if ($request->expectsJson()) {
            return $this->cleanJsonResponse([
                'success' => true,
                'notification' => $this->formatNotification($notification, $currentRole),
            ]);
        }

        $indexRoute = $this->getNotificationsRouteForUser($user, $currentRole);

        return redirect()->route($indexRoute)
            ->with('success', __('Notification marked as read'));
    }

    /**
     * Mark one notification as read (role-aware)
     */
    public function markAsRead(Request $request, string $id)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('mark_read', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);

        $notification = $this->getRoleBasedNotifications($user, $currentRole)
            ->where('id', $id)
            ->firstOrFail();

        if (is_null($notification->read_at)) {
            $notification->markAsRead();

            Log::info('Notification manually marked as read', [
                'user_id' => $user->id,
                'notification_id' => $id,
                'type' => $notification->type,
                'role' => $currentRole,
            ]);
        }

        return $this->cleanJsonResponse([
            'success' => true,
            'message' => __('Notification marked as read'),
            'notification' => $this->formatNotification($notification, $currentRole),
        ]);
    }

    /**
     * Mark all notifications as read (role-aware)
     */
    public function markAllAsRead(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('mark_all_read', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);
        $count = $this->markRoleBasedNotificationsAsRead($user, $currentRole);

        Log::info('All notifications marked as read', [
            'user_id' => $user->id,
            'count' => $count,
            'role' => $currentRole,
        ]);

        $message = $count === 1
            ? __('Marked :count notification as read', ['count' => $count])
            : __('Marked :count notifications as read', ['count' => $count]);

        return $this->cleanJsonResponse([
            'success' => true,
            'count' => (int) $count,
            'message' => $message,
            'role' => $currentRole,
        ]);
    }

    /**
     * Mark all notifications as seen (API endpoint) — role-aware
     */
    public function markAllAsSeenApi(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('mark_all_seen', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);
        $query = $this->getRoleBasedNotifications($user, $currentRole);

        $count = 0;
        $hasColumn = Schema::hasColumn('notifications', 'seen_at');

        if ($hasColumn) {
            $count = (clone $query)
                ->whereNull('seen_at')
                ->update(['seen_at' => now()]);
        }

        $message = $count > 0
            ? __('Marked :count notifications as seen', ['count' => $count])
            : ($hasColumn ? __('No unseen notifications to mark') : __('Seen tracking not available'));

        return $this->cleanJsonResponse([
            'success' => true,
            'count' => (int) $count,
            'has_seen_at_column' => $hasColumn,
            'message' => $message,
            'role' => $currentRole,
        ]);
    }

    /**
     * Mark notification as unread (role-aware)
     */
    public function markAsUnread(Request $request, string $id)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('mark_unread', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);

        $notification = $this->getRoleBasedNotifications($user, $currentRole)
            ->where('id', $id)
            ->firstOrFail();

        if (!is_null($notification->read_at)) {
            $notification->update(['read_at' => null]);

            Log::info('Notification marked as unread', [
                'user_id' => $user->id,
                'notification_id' => $id,
                'role' => $currentRole,
            ]);
        }

        return $this->cleanJsonResponse([
            'success' => true,
            'message' => __('Notification marked as unread'),
            'notification' => $this->formatNotification($notification, $currentRole),
        ]);
    }

    /**
     * Delete a single notification (role-aware)
     */
    public function destroy(Request $request, string $id)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('delete', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);

        $notification = $this->getRoleBasedNotifications($user, $currentRole)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        Log::info('Notification deleted', [
            'user_id' => $user->id,
            'notification_id' => $id,
            'role' => $currentRole,
        ]);

        return $this->cleanJsonResponse([
            'success' => true,
            'message' => __('Notification deleted successfully'),
            'id' => $id,
            'role' => $currentRole,
        ]);
    }

    /**
     * Bulk delete notifications (role-aware)
     */
    public function bulkDestroy(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        // ✅ FIX: use Laravel's `uuid` rule (stricter than size:36)
        $validated = $request->validate([
            'notification_ids' => 'required|array|min:1|max:100',
            'notification_ids.*' => 'required|uuid',
        ]);

        $this->rateLimit('bulk_delete', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);
        $notificationIds = $validated['notification_ids'];

        $count = $this->getRoleBasedNotifications($user, $currentRole)
            ->whereIn('id', $notificationIds)
            ->delete();

        Log::info('Notifications bulk deleted', [
            'user_id' => $user->id,
            'requested_count' => count($notificationIds),
            'deleted_count' => $count,
            'role' => $currentRole,
        ]);

        return $this->cleanJsonResponse([
            'success' => true,
            'count' => (int) $count,
            'message' => __('Deleted :count notifications', ['count' => $count]),
            'role' => $currentRole,
        ]);
    }

    /**
     * Delete all notifications (role-aware)
     */
    public function clearAll(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('clear_all', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);
        $count = $this->clearRoleBasedNotifications($user, $currentRole);

        Log::info('All notifications cleared', [
            'user_id' => $user->id,
            'count' => $count,
            'role' => $currentRole,
        ]);

        return $this->cleanJsonResponse([
            'success' => true,
            'count' => (int) $count,
            'message' => __('Cleared :count notifications', ['count' => $count]),
            'role' => $currentRole,
        ]);
    }

    /**
     * Delete read notifications (role-aware)
     */
    public function clearRead(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('clear_read', $user->id);

        $currentRole = $this->resolveCurrentRole($request, $user);

        $count = $this->getRoleBasedNotifications($user, $currentRole)
            ->whereNotNull('read_at')
            ->delete();

        Log::info('Read notifications cleared', [
            'user_id' => $user->id,
            'count' => $count,
            'role' => $currentRole,
        ]);

        return $this->cleanJsonResponse([
            'success' => true,
            'count' => (int) $count,
            'message' => __('Cleared :count read notifications', ['count' => $count]),
            'role' => $currentRole,
        ]);
    }

    /**
     * Get notification categories with counts (role-aware)
     */
    public function categories(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->cleanJsonResponse([
                'success' => false,
                'categories' => [],
            ], 401);
        }

        $currentRole = $this->resolveCurrentRole($request, $user);
        $categories = $this->getRoleBasedCategoryStats($user, $currentRole);

        $query = $this->getRoleBasedNotifications($user, $currentRole);
        $uncategorizedCount = (clone $query)
            ->when(
                Schema::hasColumn('notifications', 'category'),
                fn (Builder $q) => $q->whereNull('category'),
                fn (Builder $q) => $q->whereNull(DB::raw("data->>'$.category'"))
            )
            ->count();

        if ($uncategorizedCount > 0) {
            $categories['uncategorized'] = $uncategorizedCount;
        }

        return $this->cleanJsonResponse([
            'success' => true,
            'categories' => $categories,
            'total_categories' => count($categories),
            'current_role' => $currentRole,
        ]);
    }

    /**
     * Get notifications statistics (role-aware)
     */
    public function stats(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->cleanJsonResponse([
                'success' => false,
                'stats' => [
                    'total' => 0,
                    'unread' => 0,
                    'unseen' => 0,
                    'today' => 0,
                    'this_week' => 0,
                    'this_month' => 0,
                    'read_ratio' => 0.0,
                    'by_category' => [],
                    'by_priority' => [],
                ],
            ], 401);
        }

        $currentRole = $this->resolveCurrentRole($request, $user);
        $query = $this->getRoleBasedNotifications($user, $currentRole);

        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $stats = [
            'total' => (int) $query->count(),
            'unread' => (int) (clone $query)->whereNull('read_at')->count(),
            'unseen' => Schema::hasColumn('notifications', 'seen_at')
                ? (int) (clone $query)->whereNull('seen_at')->count()
                : 0,
            'today' => (int) (clone $query)->whereDate('created_at', $today)->count(),
            'this_week' => (int) (clone $query)->where('created_at', '>=', $weekStart)->count(),
            'this_month' => (int) (clone $query)->where('created_at', '>=', $monthStart)->count(),
            'read_ratio' => $this->calculateReadRatio($user, $currentRole),
            'by_category' => $this->getRoleBasedCategoryStats($user, $currentRole),
            'by_priority' => $this->getRoleBasedPriorityStats($user, $currentRole),
        ];

        return $this->cleanJsonResponse([
            'success' => true,
            'stats' => $stats,
            'current_role' => $currentRole,
        ]);
    }

    /**
     * Calculate read ratio percentage (role-aware)
     */
    private function calculateReadRatio($user, ?string $roleSlug): float
    {
        $query = $this->getRoleBasedNotifications($user, $roleSlug);
        $total = $query->count();

        if ($total === 0) {
            return 100.0;
        }

        $read = (clone $query)->whereNotNull('read_at')->count();

        return round(($read / $total) * 100, 2);
    }

    /**
     * Get notifications by category (role-aware)
     */
    public function byCategory(Request $request, string $category)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $currentRole = $this->resolveCurrentRole($request, $user);
        $perPage = min((int) ($request->query('per_page', self::DEFAULT_PER_PAGE)), self::MAX_PER_PAGE);

        $query = $this->getRoleBasedNotifications($user, $currentRole);

        $notifications = $query
            ->when(
                Schema::hasColumn('notifications', 'category'),
                fn (Builder $q) => $q->where('category', $category),
                fn (Builder $q) => $q->where('data->category', $category)
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->cleanJsonResponse([
            'success' => true,
            'category' => $category,
            'current_role' => $currentRole,
            'data' => [
                'notifications' => $notifications->map(
                    fn ($n) => $this->formatNotification($n, $currentRole)
                ),
            ],
            'meta' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Send a test notification (role-aware)
     */
    public function test(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('test', $user->id, 5);

        $currentRole = $this->resolveCurrentRole($request, $user);

        try {
            $user->notify(new GeneralNotification(
                title: '🔔 Test Notification',
                message: 'This is a test notification from the notification system. Time: ' . now()->format('H:i:s'),
                icon: 'fas fa-bell text-primary',
                category: 'test',
                priority: 1,
                actionUrl: route('notifications.index'),
                data: [
                    'type' => 'test',
                    'source' => 'notification_controller',
                    'timestamp' => now()->toISOString(),
                    'user_agent' => $request->userAgent(),
                ],
                roles: $currentRole ?: 'all'
            ));

            Log::info('Test notification sent', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'role' => $currentRole,
            ]);

            return $this->cleanJsonResponse([
                'success' => true,
                'message' => __('Test notification sent successfully!'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Test notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->cleanJsonResponse([
                'success' => false,
                'message' => __('Failed to send test notification: :error', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Export notifications (role-aware)
     */
    public function export(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $this->rateLimit('export', $user->id, 3);

        $format = $request->query('format', 'json');

        if (!in_array($format, ['json', 'csv', 'excel'], true)) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => __('Unsupported export format'),
            ], 400);
        }

        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'category' => 'nullable|string',
        ]);

        $currentRole = $this->resolveCurrentRole($request, $user);

        $query = $this->getRoleBasedNotifications($user, $currentRole)
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->when($request->filled('category'), fn ($q) => $this->applyCategoryFilter($q, $request->category))
            ->orderByDesc('created_at');

        $notifications = $query->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'message' => $n->data['message'] ?? '',
                'category' => $n->data['category'] ?? ($n->category ?? null),
                'priority' => $n->data['priority'] ?? ($n->priority ?? 0),
                'read_at' => $n->read_at?->toDateTimeString(),
                'created_at' => $n->created_at?->toDateTimeString(),
                'action_url' => $n->data['action_url'] ?? null,
            ];
        });

        if ($format === 'csv') {
            return $this->exportToCsv($notifications);
        }

        if ($format === 'excel') {
            return $this->exportToExcel($notifications);
        }

        return $this->cleanJsonResponse([
            'success' => true,
            'count' => $notifications->count(),
            'data' => $notifications,
            'current_role' => $currentRole,
        ]);
    }

    /**
     * Get notification preferences
     */
    public function preferences(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $preferences = $user->notification_preferences ?? [
            'email_enabled' => true,
            'push_enabled' => true,
            'digest_frequency' => 'daily',
            'categories' => [],
        ];

        return $this->cleanJsonResponse([
            'success' => true,
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        $user = $this->authenticateUser($request);
        if ($user === null) {
            return $this->unauthorizedResponse($request);
        }

        $validated = $request->validate([
            'email_enabled' => 'sometimes|boolean',
            'push_enabled' => 'sometimes|boolean',
            'digest_frequency' => 'sometimes|in:never,daily,weekly',
            'categories' => 'sometimes|array',
            'categories.*' => 'string|max:255',
        ]);

        $preferences = $user->notification_preferences ?? [];
        $user->notification_preferences = array_merge($preferences, $validated);
        $user->save();

        Log::info('Notification preferences updated', [
            'user_id' => $user->id,
            'changes' => array_keys($validated),
        ]);

        return $this->cleanJsonResponse([
            'success' => true,
            'message' => __('Notification preferences updated'),
            'preferences' => $user->notification_preferences,
        ]);
    }

    // ========== PRIVATE HELPERS ==========

    private function authenticateUser(Request $request)
    {
        if (!auth()->check()) {
            return null;
        }

        return $request->user();
    }

    /**
     * ✅ FIX: any notifications/* or api/notifications/* route, or an AJAX call,
     *         always gets a JSON 401 — never an HTML redirect.
     */
    private function unauthorizedResponse(Request $request, $redirect = null)
    {
        $wantsJson = $request->expectsJson()
            || $request->ajax()
            || $request->is('notifications/*')
            || $request->is('api/notifications/*')
            || $request->is('api/*');

        if ($wantsJson) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => __('Unauthorized. Please log in.'),
                'error_code' => 'UNAUTHORIZED',
            ], 401);
        }

        return $redirect ?? redirect()->route('login')->with('error', __('Please log in to continue.'));
    }

    /**
     * Resolve the current role for notification filtering.
     * Priority: request → session → inferred from user → persist to session.
     */
    private function resolveCurrentRole(Request $request, $user): ?string
    {
        // 1. Explicit `?role=` query param
        if ($request->filled('role')) {
            $role = (string) $request->query('role');
            session(['selected_role' => $role]);
            return $role;
        }

        // 2. Session role
        $sessionRole = session('selected_role');
        if ($sessionRole) {
            return $sessionRole;
        }

        // 3. Infer from user + persist
        $inferred = $this->inferUserRole($user);
        if ($inferred) {
            session(['selected_role' => $inferred]);
        }

        return $inferred;
    }

    /**
     * Infer the primary role slug for a user.
     */
    private function inferUserRole($user): ?string
    {
        if (!$user) {
            return null;
        }

        if (!empty($user->role_name)) {
            return (string) $user->role_name;
        }

        if (!empty($user->role_slugs) && is_array($user->role_slugs)) {
            return $user->role_slugs[0] ?? null;
        }

        $checks = [
            'isSuperAdmin'          => 'super-admin',
            'isAdmin'               => 'admin',
            'isLandlord'            => 'landlord',
            'isSanitationPersonnel' => 'sanitation-personnel',
            'isFieldAgent'          => 'field-agent',
            'isTenant'              => 'tenant',
            'isDeveloper'           => 'developer',
            'isSecurityPersonnel'   => 'security-personnel',
        ];

        foreach ($checks as $method => $slug) {
            if (method_exists($user, $method) && $user->{$method}()) {
                return $slug;
            }
        }

        if (!empty($user->type_name)) {
            return strtolower(str_replace(' ', '-', (string) $user->type_name));
        }

        return null;
    }

    /**
     * Rate limit a sensitive action.
     */
    private function rateLimit(string $action, $userId, int $maxAttempts = 10): void
    {
        $key = self::RATE_LIMIT_PREFIX . $action . ':' . $userId;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'action' => __('Too many attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Category filter — column if it exists, else JSON.
     */
    private function applyCategoryFilter(Builder $query, string $category): Builder
    {
        if (Schema::hasColumn('notifications', 'category')) {
            return $query->where('category', $category);
        }

        return $query->where('data->category', $category);
    }

    /**
     * Priority filter — column if it exists, else JSON.
     */
    private function applyPriorityFilter(Builder $query, $priority): Builder
    {
        if (Schema::hasColumn('notifications', 'priority')) {
            return $query->where('priority', $priority);
        }

        return $query->where('data->priority', $priority);
    }

    /**
     * Search filter — matches title or message inside the JSON payload.
     */
    private function applySearchFilter(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('data->title', 'like', "%{$search}%")
              ->orWhere('data->message', 'like', "%{$search}%");
        });
    }

    /**
     * Internal: mark every notification as seen (page-view side effect).
     */
    private function markAllAsSeen($user): int
    {
        try {
            if (Schema::hasColumn('notifications', 'seen_at')) {
                return $user->notifications()
                    ->whereNull('seen_at')
                    ->update(['seen_at' => now()]);
            }

            return 0;
        } catch (\Throwable $e) {
            Log::error('Error marking notifications as seen', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Pick the notifications index route for a user.
     *
     * ✅ FIX: default now matches the base `notifications.index` group.
     */
    private function getNotificationsRouteForUser($user, ?string $currentRole = null): string
    {
        // Try role-specific route first, but only if it's registered.
        $candidate = match (true) {
            method_exists($user, 'isLandlord')          && $user->isLandlord()          => 'landlord.notifications.index',
            method_exists($user, 'isAdmin')             && $user->isAdmin()             => 'admin.notifications.index',
            method_exists($user, 'isSuperAdmin')        && $user->isSuperAdmin()        => 'admin.notifications.index',
            method_exists($user, 'isDeveloper')         && $user->isDeveloper()         => 'developer.notifications.index',
            method_exists($user, 'isFieldAgent')        && $user->isFieldAgent()        => 'field-agent.notifications.index',
            method_exists($user, 'isTenant')            && $user->isTenant()            => 'tenant.notifications.index',
            method_exists($user, 'isSecurityPersonnel') && $user->isSecurityPersonnel() => 'security.notifications.index',
            default => 'notifications.index',
        };

        if (\Illuminate\Support\Facades\Route::has($candidate)) {
            return $candidate;
        }

        return 'notifications.index';
    }

    /**
     * Format a DatabaseNotification for API output.
     */
    private function formatNotification(DatabaseNotification $notification, ?string $currentRole = null): array
    {
        $data = $notification->data;

        $category = $data['category'] ?? null;
        $priority = $data['priority'] ?? 0;
        $targetedRoles = $data['roles'] ?? null;

        if (Schema::hasColumn('notifications', 'category') && $notification->category) {
            $category = $notification->category;
        }

        if (Schema::hasColumn('notifications', 'priority') && $notification->priority !== null) {
            $priority = (int) $notification->priority;
        }

        // ✅ FIX: read seen_at defensively — column may not exist
        $seenAt = Schema::hasColumn('notifications', 'seen_at')
            ? ($notification->seen_at ?? null)
            : null;

        $createdAt = $notification->created_at;

        return [
            'id' => $notification->id,
            'type' => class_basename($notification->type),
            'data' => $data,
            'title' => $data['title'] ?? __('Notification'),
            'message' => $data['message'] ?? '',
            'icon' => $data['icon'] ?? 'fas fa-bell',
            'category' => $category,
            'priority' => $priority,
            'priority_level' => $this->getPriorityLabel($priority),
            'action_url' => $data['action_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'targeted_roles' => $targetedRoles,
            'is_relevant_for_current_role' => $this->isNotificationRelevantForRole($targetedRoles, $currentRole),
            'read_at' => $notification->read_at instanceof \DateTimeInterface
                ? $notification->read_at->toISOString()
                : ($notification->read_at ?: null),
            'seen_at' => $seenAt instanceof \DateTimeInterface
                ? $seenAt->toISOString()
                : ($seenAt ?: null),
            'created_at' => $createdAt instanceof \DateTimeInterface
                ? $createdAt->toISOString()
                : (is_string($createdAt) ? $createdAt : null),
            'time_ago' => $createdAt instanceof \DateTimeInterface
                ? $createdAt->diffForHumans()
                : (is_string($createdAt)
                    ? \Carbon\Carbon::parse($createdAt)->diffForHumans()
                    : ''),
            'is_read' => !is_null($notification->read_at),
            'is_unread' => is_null($notification->read_at),
            'is_seen' => !is_null($seenAt),
            'can_undo' => $createdAt instanceof \DateTimeInterface
                ? $createdAt->diffInMinutes(now()) < 5
                : (is_string($createdAt)
                    ? \Carbon\Carbon::parse($createdAt)->diffInMinutes(now()) < 5
                    : false),
        ];
    }

    /**
     * Is the notification relevant for the given role?
     */
    private function isNotificationRelevantForRole($targetedRoles, ?string $currentRole): bool
    {
        if (!$targetedRoles) {
            return true;
        }

        if ($targetedRoles === 'all' || (is_array($targetedRoles) && in_array('all', $targetedRoles, true))) {
            return true;
        }

        if (!$currentRole) {
            return true;
        }

        if (is_string($targetedRoles)) {
            return $targetedRoles === $currentRole;
        }

        return in_array($currentRole, (array) $targetedRoles, true);
    }

    /**
     * Human label for a priority value.
     */
    private function getPriorityLabel(int $priority): string
    {
        return match ($priority) {
            3 => __('High'),
            2 => __('Medium'),
            1 => __('Low'),
            default => __('Normal'),
        };
    }

    /**
     * Stream CSV export.
     */
    private function exportToCsv($notifications)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="notifications_' . date('Y-m-d_His') . '.csv"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        $callback = function () use ($notifications) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens non-ASCII correctly
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, ['ID', 'Title', 'Message', 'Category', 'Priority', 'Read At', 'Created At', 'URL']);

            foreach ($notifications as $n) {
                fputcsv($file, [
                    $n['id'],
                    $n['title'],
                    $n['message'],
                    $n['category'],
                    $n['priority'],
                    $n['read_at'] ?? 'Not read',
                    $n['created_at'],
                    $n['action_url'] ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Stream Excel-compatible export.
     */
    private function exportToExcel($notifications)
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="notifications_' . date('Y-m-d_His') . '.xls"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        $callback = function () use ($notifications) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Notification ID', 'Title', 'Message', 'Category',
                'Priority', 'Read Status', 'Read At', 'Created At', 'Action URL',
            ]);

            foreach ($notifications as $n) {
                fputcsv($file, [
                    $n['id'],
                    $n['title'],
                    $n['message'],
                    $n['category'],
                    $n['priority'],
                    $n['read_at'] ? 'Read' : 'Unread',
                    $n['read_at'] ?? 'Not read',
                    $n['created_at'],
                    $n['action_url'] ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}