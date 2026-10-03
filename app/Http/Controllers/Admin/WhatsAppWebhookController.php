<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Handle Twilio webhook
     */
    public function handleTwilio(Request $request): JsonResponse
    {
        try {
            Log::info('Twilio WhatsApp webhook received', $request->all());

            $data = $request->all();
            
            // Process incoming message
            if (isset($data['SmsStatus'])) {
                // Status update
                $this->handleStatusUpdate('twilio', $data);
            } elseif (isset($data['Body']) && isset($data['From'])) {
                // Incoming message
                $this->handleIncomingMessage('twilio', $data);
            }

            return response()->json(['status' => 'ok']);
            
        } catch (\Exception $e) {
            Log::error('Twilio webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Vonage webhook
     */
    public function handleVonage(Request $request): JsonResponse
    {
        try {
            Log::info('Vonage WhatsApp webhook received', $request->all());

            $data = $request->all();
            
            // Process incoming message
            if (isset($data['status'])) {
                // Status update
                $this->handleStatusUpdate('vonage', $data);
            } elseif (isset($data['text']) && isset($data['from'])) {
                // Incoming message
                $this->handleIncomingMessage('vonage', $data);
            }

            return response()->json(['status' => 'ok']);
            
        } catch (\Exception $e) {
            Log::error('Vonage webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle 360Dialog webhook
     */
    public function handle360Dialog(Request $request): JsonResponse
    {
        try {
            Log::info('360Dialog WhatsApp webhook received', $request->all());

            $data = $request->all();
            
            // Process incoming message
            if (isset($data['status'])) {
                // Status update
                $this->handleStatusUpdate('360dialog', $data);
            } elseif (isset($data['message']) && isset($data['from'])) {
                // Incoming message
                $this->handleIncomingMessage('360dialog', $data);
            }

            return response()->json(['status' => 'ok']);
            
        } catch (\Exception $e) {
            Log::error('360Dialog webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle WATI webhook
     */
    public function handleWati(Request $request): JsonResponse
    {
        try {
            Log::info('WATI WhatsApp webhook received', $request->all());

            $data = $request->all();
            
            // Process incoming message
            if (isset($data['status'])) {
                // Status update
                $this->handleStatusUpdate('wati', $data);
            } elseif (isset($data['message']) && isset($data['from'])) {
                // Incoming message
                $this->handleIncomingMessage('wati', $data);
            }

            return response()->json(['status' => 'ok']);
            
        } catch (\Exception $e) {
            Log::error('WATI webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Custom webhook
     */
    public function handleCustom(Request $request): JsonResponse
    {
        try {
            Log::info('Custom WhatsApp webhook received', $request->all());

            $data = $request->all();
            
            // Process incoming message
            if (isset($data['status'])) {
                // Status update
                $this->handleStatusUpdate('custom', $data);
            } elseif (isset($data['message']) && isset($data['from'])) {
                // Incoming message
                $this->handleIncomingMessage('custom', $data);
            }

            return response()->json(['status' => 'ok']);
            
        } catch (\Exception $e) {
            Log::error('Custom webhook error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generic webhook handler
     */
    public function handle(Request $request, $provider): JsonResponse
    {
        try {
            Log::info("WhatsApp webhook received for provider: {$provider}", $request->all());

            $data = $request->all();
            
            // Process based on provider
            switch ($provider) {
                case 'twilio':
                    return $this->handleTwilio($request);
                case 'vonage':
                    return $this->handleVonage($request);
                case '360dialog':
                    return $this->handle360Dialog($request);
                case 'wati':
                    return $this->handleWati($request);
                case 'custom':
                    return $this->handleCustom($request);
                default:
                    return response()->json(['error' => 'Unsupported provider'], 400);
            }

        } catch (\Exception $e) {
            Log::error("Webhook error for provider {$provider}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify webhook
     */
    public function verify(Request $request): JsonResponse
    {
        // For Twilio webhook verification
        if ($request->has('hub.challenge')) {
            return response($request->input('hub.challenge'), 200);
        }

        // For other providers
        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle incoming message
     */
    protected function handleIncomingMessage(string $provider, array $data): void
    {
        try {
            $message = [
                'provider' => $provider,
                'from' => $data['from'] ?? $data['From'] ?? null,
                'to' => $data['to'] ?? $data['To'] ?? null,
                'message' => $data['text'] ?? $data['Body'] ?? $data['message'] ?? null,
                'message_type' => $data['type'] ?? 'text',
                'received_at' => now(),
                'is_incoming' => true
            ];

            // Store in database
            WhatsAppMessage::create([
                'to' => $message['to'],
                'from' => $message['from'],
                'message' => $message['message'],
                'message_type' => $message['message_type'],
                'provider' => $provider,
                'status' => 'received',
                'is_incoming' => true,
                'response' => $data
            ]);

            WhatsAppLog::create([
                'provider' => $provider,
                'to' => $message['to'],
                'from' => $message['from'],
                'message' => $message['message'],
                'type' => 'incoming',
                'status' => 'received',
                'response' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling incoming WhatsApp message: ' . $e->getMessage());
        }
    }

    /**
     * Handle status update
     */
    protected function handleStatusUpdate(string $provider, array $data): void
    {
        try {
            $status = $data['status'] ?? $data['SmsStatus'] ?? null;
            $messageId = $data['message_id'] ?? $data['MessageSid'] ?? $data['id'] ?? null;

            if ($messageId) {
                // Update message status
                $message = WhatsAppMessage::where('message_id', $messageId)->first();
                if ($message) {
                    $message->update(['status' => $status]);
                }

                // Log status update
                WhatsAppLog::create([
                    'provider' => $provider,
                    'message_id' => $messageId,
                    'type' => 'status_update',
                    'status' => $status,
                    'response' => $data
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling WhatsApp status update: ' . $e->getMessage());
        }
    }
}