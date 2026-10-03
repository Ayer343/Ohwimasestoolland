<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\LiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LiveChatController extends Controller
{
    public function __construct(private LiveChatService $chat) {}

    /* ============================================================
       INBOX
       ============================================================ */

    /**
     * Inbox view — shows the list of conversations the user can see.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $conversations = Conversation::forUser($user->id)
            ->with([
                'initiator:id,name,type',
                'assignee:id,name',
                'latestMessage',
            ])
            ->orderByDesc('last_message_at')
            ->paginate(25);

        return view('live-chat.index', [
            'conversations' => $conversations,
            'user'          => $user,
            'isAgent'       => $this->isAgent($user),
        ]);
    }

    /* ============================================================
       START / RESUME
       ============================================================ */

    /**
     * Start (or reuse) a conversation between the current user
     * and the eligible responder pool.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'nullable|string|max:150',
        ]);

        $user         = Auth::user();
        $conversation = $this->chat->startConversation($user, $data['subject'] ?? null);

        return response()->json([
            'success'      => true,
            'conversation' => $conversation->only(['id', 'subject', 'type', 'status']),
            'redirect_url' => route('live-chat.show', $conversation),
        ]);
    }

    /* ============================================================
       SHOW
       ============================================================ */

    /**
     * Display a single conversation.
     */
    public function show(Conversation $conversation): View
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        $messages = $conversation->messages()
            ->with('user:id,name,type')
            ->orderBy('created_at')
            ->paginate(50);

        // Mark conversation as read for the current user
        $this->chat->markAsRead($conversation, $user);

        return view('live-chat.show', [
            'conversation' => $conversation,
            'messages'     => $messages,
        ]);
    }

    /* ============================================================
       SEND MESSAGE
       ============================================================ */

    /**
     * Send a message in a conversation.
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        $data = $request->validate([
            'body'        => 'required|string|max:5000',
            'attachments' => 'nullable|array',
        ]);

        $message = $this->chat->sendMessage(
            $conversation,
            $user,
            $data['body'],
            $data['attachments'] ?? []
        );

        return response()->json([
            'success' => true,
            'message' => [
                'id'         => $message->id,
                'body'       => $message->body,
                'user_id'    => $message->user_id,
                'user_name'  => $user->name,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /* ============================================================
       MARK AS READ
       ============================================================ */

    /**
     * Reset the participant's unread cursor to the latest message.
     */
    public function markRead(Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);
        $this->chat->markAsRead($conversation, $user);

        return response()->json(['success' => true]);
    }

    /* ============================================================
       CLOSE
       ============================================================ */

    /**
     * Mark the conversation as closed. Keeps all data — the thread
     * becomes read-only.
     */
    public function close(Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        $conversation->update([
            'status' => Conversation::STATUS_CLOSED,
        ]);

        return response()->json(['success' => true]);
    }

    /* ============================================================
       AGENT INBOX
       ============================================================ */

    /**
     * Agent inbox — lists all open conversations, unassigned first.
     */
    public function agentInbox(): View
    {
        $user = Auth::user();
        abort_unless($this->isAgent($user), 403);

        $conversations = Conversation::open()
            ->with([
                'initiator:id,name,type',
                'assignee:id,name',
            ])
            ->orderByRaw('CASE WHEN assigned_to IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return view('live-chat.agent-inbox', [
            'conversations' => $conversations,
        ]);
    }

    /* ============================================================
       ASSIGN
       ============================================================ */

    /**
     * Assign a conversation to the current agent, or to a specific
     * agent if `user_id` is provided.
     */
    public function assign(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->isAgent($user), 403);

        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $conversation->update([
            'assigned_to' => $data['user_id'] ?? $user->id,
            'status'      => Conversation::STATUS_ASSIGNED,
        ]);

        return response()->json(['success' => true]);
    }

    /* ============================================================
       DELETE — soft delete for everyone
       ============================================================ */

    /**
     * Soft-delete a conversation and all its messages.
     *
     * Permitted for:
     *   - The conversation initiator
     *   - The assigned agent
     *   - Any agent (admin / super-admin / developer)
     *
     * Other participants only see the 403.
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        $isInitiator = $conversation->initiator_id === $user->id;
        $isAssignee  = $conversation->assigned_to === $user->id;
        $isAgent     = $this->isAgent($user);

        if (!$isInitiator && !$isAssignee && !$isAgent) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete this conversation.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($conversation) {
                // Soft-delete messages first, then the conversation
                $conversation->messages()->delete();
                $conversation->delete();
            });

            Log::info('Live chat conversation deleted', [
                'conversation_id' => $conversation->id,
                'deleted_by'      => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete live chat conversation', [
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation.',
            ], 500);
        }
    }

    /* ============================================================
       CLEAR — remove from my inbox only
       ============================================================ */

    /**
     * Remove a conversation from the current user's inbox by
     * deleting their `conversation_participants` row.
     *
     * The conversation stays intact for other participants.
     */
    public function clear(Request $request, Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        try {
            $deleted = $conversation->participants()
                ->where('user_id', $user->id)
                ->delete();

            Log::info('Live chat conversation cleared for user', [
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'rows_deleted'    => $deleted,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversation removed from your inbox.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to clear live chat conversation', [
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear conversation.',
            ], 500);
        }
    }

    /* ============================================================
       PURGE — bulk permanent delete (agent only)
       ============================================================ */

    /**
     * Permanently delete closed conversations older than N days.
     * Agents only. Irreversible.
     */
    public function purgeClosed(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->isAgent($user), 403);

        $data = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);
        $days = $data['days'] ?? 30;

        try {
            $cutoff = now()->subDays($days);

            $query = Conversation::onlyTrashed()
                ->where('status', Conversation::STATUS_CLOSED)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_message_at')
                      ->orWhere('last_message_at', '<', $cutoff);
                });

            $count = $query->count();

            $query->get()->each(function (Conversation $c) {
                $c->messages()->forceDelete();
                $c->participants()->delete();
                $c->forceDelete();
            });

            Log::info('Live chat purge completed', [
                'purged_by'      => $user->id,
                'days'           => $days,
                'count'          => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Purged {$count} closed conversation(s) older than {$days} day(s).",
                'count'   => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to purge live chat conversations', [
                'user_id' => $user->id,
                'days'    => $days,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to purge conversations.',
            ], 500);
        }
    }

    /* ============================================================
       MESSAGES JSON — polling feed
       ============================================================ */

    /**
     * JSON message feed for the polling widget.
     * Also marks the conversation as read for the requesting user.
     */
    public function messagesJson(Conversation $conversation, Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        $after = (int) $request->query('after', 0);

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'user_id'    => $m->user_id,
                'user_name'  => optional($m->user)->name,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        // Mark as read on every poll — cheap single UPDATE
        $this->chat->markAsRead($conversation, $user);

        return response()->json([
            'success'  => true,
            'messages' => $messages,
        ]);
    }

    /* ============================================================
       UNREAD COUNT
       ============================================================ */

    /**
     * Total unread count across all of the current user's conversations.
     *
     * An unread message is one that:
     *   - belongs to a conversation the user participates in
     *   - was NOT sent by the user
     *   - has id > the user's `last_read_message_id`
     *   - belongs to an open (not closed) conversation
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();

        $count = Message::query()
            ->whereHas('conversation.participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where(function ($sub) {
                      $sub->whereNull('last_read_message_id')
                          ->orWhereColumn(
                              'messages.id',
                              '>',
                              'conversation_participants.last_read_message_id'
                          );
                  });
            })
            ->where('messages.user_id', '!=', $user->id)
            ->whereHas('conversation', fn ($q) => $q->open())
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /* ============================================================
       TYPING
       ============================================================ */

    /**
     * Broadcast a "user is typing" event to the other participants.
     *
     * If broadcasting is not configured, this is a silent no-op.
     * The route still returns 200 so the frontend never breaks.
     */
    public function typing(Conversation $conversation): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeView($conversation, $user);

        try {
            broadcast(new \App\Events\LiveChat\UserTyping($conversation, $user))
                ->toOthers();
        } catch (\Throwable $e) {
            Log::debug('Typing broadcast skipped', [
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'reason'          => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /* ============================================================
       HELPERS
       ============================================================ */

    /**
     * Is the given user an agent (admin / super-admin / developer)?
     */
    private function isAgent($user): bool
    {
        return in_array($user->type, [
            \App\Models\User::TYPE_ADMIN,
            \App\Models\User::TYPE_SUPER_ADMIN,
            \App\Models\User::TYPE_DEVELOPER,
        ], true);
    }

    /**
     * Ensure the given user is a participant of the conversation.
     * Throws 403 otherwise.
     */
    private function authorizeView(Conversation $conversation, $user): void
    {
        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403,
            'You are not a participant of this conversation.'
        );
    }
}