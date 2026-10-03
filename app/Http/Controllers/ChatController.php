<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ChatHelpTopicProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ChatController extends Controller
{
    protected ChatService $chatService;
    protected ChatHelpTopicProvider $helpTopicProvider;

    public function __construct(
        ChatService $chatService,
        ChatHelpTopicProvider $helpTopicProvider
    ) {
        // ❌ REMOVED: $this->middleware('auth'); — deprecated in Laravel 11+
        // Auth is now enforced at the route level (see routes/web.php).
        // Only `send` and `topics` are public; the rest stay behind `auth`.
        $this->chatService = $chatService;
        $this->helpTopicProvider = $helpTopicProvider;
    }

    /* ============================================================
       HELPERS
       ============================================================ */

    /**
     * Get (or lazily create) a stable guest token for this browser session.
     *
     * This allows unauthenticated visitors to have their messages persisted
     * and later retrieved/cleared within the same session, without needing
     * an account.
     */
    private function guestToken(Request $request): string
    {
        if (!$request->session()->has('chat_guest_token')) {
            $request->session()->put(
                'chat_guest_token',
                bin2hex(random_bytes(16))   // 32 hex chars
            );
        }

        return (string) $request->session()->get('chat_guest_token');
    }

    /**
     * Resolve the correct view for the authenticated user's role.
     *
     * Falls back to the generic chat view if the role-specific one is missing,
     * so adding a new user type never breaks the chat page.
     */
    private function getViewPath(User $user): string
    {
        $candidates = match ($user->type) {
            User::TYPE_SUPER_ADMIN           => ['super-admin.chat.index', 'chat.index'],
            User::TYPE_ADMIN                 => ['admin.chat.index', 'chat.index'],
            User::TYPE_LANDLORD              => ['landlord.chat.index', 'chat.index'],
            User::TYPE_TENANT                => ['tenant.chat.index', 'chat.index'],
            User::TYPE_FIELD_AGENT           => ['field-agent.chat.index', 'chat.index'],
            User::TYPE_DEVELOPER             => ['developer.chat.index', 'chat.index'],
            User::TYPE_SECURITY_PERSONNEL    => ['security.chat.index', 'chat.index'],
            User::TYPE_CONTRACTOR            => ['contractor.chat.index', 'chat.index'],
            User::TYPE_SANITATION_PERSONNEL  => ['sanitation.chat.index', 'chat.index'],
            default                          => ['chat.index'],
        };

        foreach ($candidates as $view) {
            if (view()->exists($view)) {
                return $view;
            }
        }

        return 'chat.index';
    }

    /**
     * Map a user type ID to a friendly role name.
     */
    private function getRoleName(int $typeId): string
    {
        return match ($typeId) {
            User::TYPE_SUPER_ADMIN          => 'Super Administrator',
            User::TYPE_ADMIN                => 'Administrator',
            User::TYPE_LANDLORD             => 'Landlord',
            User::TYPE_TENANT               => 'Tenant',
            User::TYPE_FIELD_AGENT          => 'Field Agent',
            User::TYPE_DEVELOPER            => 'Developer',
            User::TYPE_SECURITY_PERSONNEL   => 'Security Personnel',
            User::TYPE_CONTRACTOR           => 'Contractor',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
            default                         => 'User',
        };
    }

    /* ============================================================
       INDEX — full-page chat view (auth-only)
       ============================================================ */

    /**
     * Display the chat interface with the appropriate layout.
     *
     * If a guest somehow reaches this route, they get the generic
     * guest chat view with guest topics + their guest history.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        // ---------- Authenticated user ----------
        if ($user) {
            $recentMessages = ChatMessage::forUser($user->id)
                ->latestFirst()
                ->take(20)
                ->get()
                ->reverse();

            return view($this->getViewPath($user), [
                'recentMessages'  => $recentMessages,
                'quickHelpTopics' => $this->helpTopicProvider->forUser($user),
                'userRoleName'    => $this->getRoleName($user->type),
                'userRoleId'      => $user->type,
            ]);
        }

        // ---------- Guest fallback ----------
        $guestToken = $this->guestToken($request);

        $recentMessages = ChatMessage::forGuest($guestToken)
            ->latestFirst()
            ->take(20)
            ->get()
            ->reverse();

        return view('chat.index', [
            'recentMessages'  => $recentMessages,
            'quickHelpTopics' => $this->helpTopicProvider->forUser(null),
            'userRoleName'    => 'Guest',
            'userRoleId'      => null,
        ]);
    }

    /* ============================================================
       SEND MESSAGE — works for guests + authenticated users
       ============================================================ */

    /**
     * Send a message and get a bot response.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user       = Auth::user();
        $guestToken = $user ? null : $this->guestToken($request);

        try {
            $response = $this->chatService->processMessage(
                $user?->id,
                $request->input('message'),
                $user?->type,
                $guestToken
            );

            return response()->json([
                'success'      => true,
                'response'     => $response,
                'user_message' => $request->input('message'),
                'is_guest'     => !$user,
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat message processing error', [
                'user_id'   => $user?->id,
                'is_guest'  => !$user,
                'guest'     => $guestToken,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            return response()->json([
                'success'  => false,
                'response' => 'Sorry, I encountered an error while processing your message. Please try again in a moment.',
            ], 500);
        }
    }

    /* ============================================================
       HISTORY — for guests + authenticated users
       ============================================================ */

    /**
     * Get chat history for the current session (user OR guest).
     */
    public function getMessages(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = $user
                ? ChatMessage::forUser($user->id)
                : ChatMessage::forGuest($this->guestToken($request));

            $messages = $query
                ->oldestFirst()
                ->get(['message', 'response', 'is_bot', 'created_at']);

            return response()->json([
                'success'  => true,
                'messages' => $messages,
                'is_guest' => !$user,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching chat messages: ' . $e->getMessage());

            return response()->json([
                'success'  => false,
                'messages' => [],
            ], 500);
        }
    }

    /* ============================================================
       CLEAR — for guests + authenticated users
       ============================================================ */

    /**
     * Clear chat history for the current session.
     */
    public function clearHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $deleted = $user
                ? ChatMessage::forUser($user->id)->delete()
                : ChatMessage::forGuest($this->guestToken($request))->delete();

            return response()->json([
                'success'       => true,
                'message'       => 'Chat history cleared successfully.',
                'deleted_count' => (int) $deleted,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error clearing chat history: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear chat history. Please try again.',
            ], 500);
        }
    }

    /* ============================================================
       QUICK TOPICS — works for guests + authenticated users
       ============================================================ */

    /**
     * Get quick help topics for the current visitor.
     */
    public function getQuickHelpTopics(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'topics'  => $this->helpTopicProvider->forUser(Auth::user()),
            'is_guest' => !Auth::check(),
        ]);
    }

    /* ============================================================
       ROLE INFO — works for guests + authenticated users
       ============================================================ */

    /**
     * Get role information for the chatbot UI header.
     */
    public function getUserRoleInfo(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success'   => true,
                'role'      => null,
                'role_name' => 'Guest',
                'user_name' => 'Guest',
                'is_guest'  => true,
            ]);
        }

        return response()->json([
            'success'   => true,
            'role'      => $user->type,
            'role_name' => $this->getRoleName($user->type),
            'user_name' => $user->name,
            'is_guest'  => false,
        ]);
    }
}