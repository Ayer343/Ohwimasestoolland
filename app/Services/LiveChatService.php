<?php
// app/Services/LiveChatService.php

namespace App\Services;

use App\Events\LiveChat\ConversationUpdated;
use App\Events\LiveChat\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveChatService
{
    /* ============================================================
       RESPONDER ROUTING
       ============================================================ */

    /**
     * Determine the responder pool for a given initiator.
     *
     * Rules:
     *   • Regular users (landlord, tenant, field agent, security,
     *     sanitation, contractor)  →  Admins + Super Admins
     *   • Admins + Super Admins     →  Developers
     *   • Developers                →  Developers + Admins + Super Admins
     *
     * ⭐ CHANGED: Developers can now escalate UP to Admins/Super Admins
     * as well as reach other Developers. This makes the routing fully
     * bidirectional — the top of the chain is no longer a dead end.
     */
    public function responderTypesFor(User $initiator): array
    {
        return match ($initiator->type) {
            User::TYPE_LANDLORD,
            User::TYPE_TENANT,
            User::TYPE_FIELD_AGENT,
            User::TYPE_SECURITY_PERSONNEL,
            User::TYPE_SANITATION_PERSONNEL,
            User::TYPE_CONTRACTOR           => [
                User::TYPE_ADMIN,
                User::TYPE_SUPER_ADMIN,
            ],

            User::TYPE_ADMIN,
            User::TYPE_SUPER_ADMIN          => [
                User::TYPE_DEVELOPER,
            ],

            // ⭐ CHANGED: developers can reach other developers AND
            // admins/super admins for coordination & escalation.
            User::TYPE_DEVELOPER            => [
                User::TYPE_DEVELOPER,
                User::TYPE_ADMIN,
                User::TYPE_SUPER_ADMIN,
            ],

            default                         => [
                User::TYPE_SUPER_ADMIN,
            ],
        };
    }

    /* ============================================================
       START / RESUME
       ============================================================ */

    /**
     * Create (or reuse) a conversation.
     *
     * If a specific $recipient is provided, only that user is attached
     * as a responder. Otherwise, the full responder pool for the
     * initiator's role is attached.
     */
    public function startConversation(
        User $initiator,
        ?string $subject = null,
        ?User $recipient = null
    ): Conversation {
        // Reuse if there's already an open conversation for this user
        $existing = Conversation::forUser($initiator->id)
            ->open()
            ->where('initiator_id', $initiator->id)
            ->latest('last_message_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($initiator, $subject, $recipient) {
            $type = in_array($initiator->type, [
                User::TYPE_ADMIN,
                User::TYPE_SUPER_ADMIN,
                User::TYPE_DEVELOPER,
            ], true)
                ? Conversation::TYPE_ESCALATION
                : Conversation::TYPE_SUPPORT;

            $conversation = Conversation::create([
                'subject'      => $subject,
                'type'         => $type,
                'status'       => Conversation::STATUS_OPEN,
                'priority'     => Conversation::PRIORITY_NORMAL,
                'initiator_id' => $initiator->id,
            ]);

            // Initiator participant
            $conversation->participants()->create([
                'user_id' => $initiator->id,
                'role'    => 'initiator',
            ]);

            // ---------- Attach responders ----------
            if ($recipient && $recipient->id !== $initiator->id) {
                // Specific recipient — attach only that user
                $conversation->participants()->firstOrCreate(
                    ['user_id' => $recipient->id],
                    ['role'    => 'responder']
                );
            } else {
                // Pool — attach everyone eligible
                $responderTypes = $this->responderTypesFor($initiator);
                $responders = User::whereIn('type', $responderTypes)
                    ->where('status', User::STATUS_ACTIVE)
                    ->where('id', '!=', $initiator->id)   // don't duplicate initiator
                    ->get();

                foreach ($responders as $responder) {
                    $conversation->participants()->firstOrCreate(
                        ['user_id' => $responder->id],
                        ['role'    => 'responder']
                    );
                }
            }

            return $conversation;
        });
    }

    /* ============================================================
       SEND MESSAGE
       ============================================================ */

    public function sendMessage(
        Conversation $conversation,
        User $sender,
        string $body,
        array $attachments = []
    ): Message {
        return DB::transaction(function () use ($conversation, $sender, $body, $attachments) {
            $message = $conversation->messages()->create([
                'user_id'     => $sender->id,
                'body'        => $body,
                'type'        => $attachments ? 'file' : 'text',
                'attachments' => $attachments ?: null,
            ]);

            $conversation->update([
                'last_message_at'      => $message->created_at,
                'last_message_preview' => mb_substr(strip_tags($body), 0, 180),
            ]);

            // Auto-assign the responder who first replies
            if ($conversation->assigned_to === null && $sender->id !== $conversation->initiator_id) {
                $conversation->update([
                    'assigned_to' => $sender->id,
                    'status'      => Conversation::STATUS_ASSIGNED,
                ]);

                // ⭐ Insert a system message so the initiator knows
                // who joined the conversation.
                $conversation->messages()->create([
                    'user_id' => null,
                    'body'    => "{$sender->name} ({$sender->type_name}) joined the conversation.",
                    'type'    => 'system',
                ]);
            }

            // Mark as read for the sender
            $conversation->participants()
                ->where('user_id', $sender->id)
                ->update([
                    'last_read_at'         => now(),
                    'last_read_message_id' => $message->id,
                ]);

            try {
                event(new MessageSent($message));
                event(new ConversationUpdated($conversation));
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed for chat message', [
                    'message_id' => $message->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return $message;
        });
    }

    /* ============================================================
       MARK AS READ
       ============================================================ */

    public function markAsRead(Conversation $conversation, User $user): void
{
    // Get the latest message id in the conversation
    $latestId = $conversation->messages()->max('id');

    // Find the participant row for this user
    $participant = $conversation->participants()
        ->where('user_id', $user->id)
        ->first();

    if (!$participant) {
        return;
    }

    // ⭐ Only update + broadcast if the cursor actually moves
    if ((int) $participant->last_read_message_id === (int) $latestId) {
        return;
    }

    $participant->update([
        'last_read_at'         => now(),
        'last_read_message_id' => $latestId,
    ]);

    // Broadcast only when the read cursor actually advances
    event(new ConversationUpdated($conversation));
}

}