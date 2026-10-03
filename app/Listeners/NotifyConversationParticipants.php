<?php

namespace App\Listeners;

use App\Events\LiveChat\MessageSent;
use App\Notifications\NewLiveChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyConversationParticipants implements ShouldQueue
{
    /**
     * Notify every other participant of the conversation about the new message.
     * Skip the sender.
     */
    public function handle(MessageSent $event): void
    {
        try {
            $message      = $event->message;
            $conversation = $message->conversation;

            if (!$conversation) {
                return;
            }

            $recipients = $conversation->participants()
                ->where('user_id', '!=', $message->user_id)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            foreach ($recipients as $user) {
                $user->notify(new NewLiveChatMessage($message));
            }
        } catch (\Throwable $e) {
            Log::warning('NotifyConversationParticipants failed', [
                'message_id' => $event->message->id ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}