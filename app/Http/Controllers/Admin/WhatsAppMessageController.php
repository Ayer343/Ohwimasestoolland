<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * List WhatsApp messages
     */
    public function index(Request $request): View
    {
        $query = WhatsAppMessage::query()
            ->with(['sender', 'recipient'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('message_type', $request->type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                  ->orWhere('from', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $messages = $query->paginate(20);

        // Get statistics
        $statistics = [
            'total' => WhatsAppMessage::count(),
            'sent' => WhatsAppMessage::where('status', 'sent')->count(),
            'delivered' => WhatsAppMessage::where('status', 'delivered')->count(),
            'read' => WhatsAppMessage::where('status', 'read')->count(),
            'failed' => WhatsAppMessage::where('status', 'failed')->count(),
        ];

        return view('admin.whatsapp.messages.index', compact('messages', 'statistics'));
    }

    /**
     * Compose WhatsApp message
     */
    public function compose(): View
    {
        $messageTypes = $this->whatsappService->getSupportedMessageTypes();
        $templates = $this->getMessageTemplates();
        $status = $this->whatsappService->getQuickStatus();

        return view('admin.whatsapp.messages.compose', compact('messageTypes', 'templates', 'status'));
    }

    /**
     * Send WhatsApp message
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'message' => 'required|string|max:4096',
            'message_type' => 'required|in:text,template,media,interactive',
            'template' => 'required_if:message_type,template|nullable|string',
            'media_url' => 'required_if:message_type,media|nullable|url',
            'caption' => 'nullable|string|max:1000',
            'parameters' => 'nullable|array',
            'schedule_at' => 'nullable|date|after:now',
            'priority' => 'nullable|in:high,normal,low'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $options = [
                'message_type' => $request->message_type,
                'template' => $request->template,
                'media_url' => $request->media_url,
                'caption' => $request->caption,
                'parameters' => $request->parameters ?? [],
                'priority' => $request->priority ?? 'normal',
                'scheduled_at' => $request->schedule_at,
            ];

            $result = $this->whatsappService->sendMessage(
                $request->to,
                $request->message,
                $options
            );

            $this->logMessage($request->to, $request->message, $options, $result);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully!',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send message',
                    'error_code' => $result['error_code'] ?? null
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show message details
     */
    public function show($id): View
    {
        $message = WhatsAppMessage::with(['sender', 'recipient', 'logs'])->findOrFail($id);
        
        return view('admin.whatsapp.messages.show', compact('message'));
    }

    /**
     * Delete message
     */
    public function destroy($id): JsonResponse
    {
        try {
            $message = WhatsAppMessage::findOrFail($id);
            $message->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deleting WhatsApp message: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend failed message
     */
    public function resend($id): JsonResponse
    {
        try {
            $message = WhatsAppMessage::findOrFail($id);
            
            if ($message->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only failed messages can be resent'
                ], 400);
            }

            $result = $this->whatsappService->sendMessage(
                $message->to,
                $message->message,
                [
                    'message_type' => $message->message_type,
                    'template' => $message->template,
                    'media_url' => $message->media_url,
                    'caption' => $message->caption,
                    'parameters' => $message->parameters ?? []
                ]
            );

            $message->update([
                'status' => $result['success'] ? 'sent' : 'failed',
                'message_id' => $result['message_id'] ?? $message->message_id,
                'last_attempt' => now(),
                'attempt_count' => $message->attempt_count + 1
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message resent successfully!',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to resend message'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error resending WhatsApp message: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test WhatsApp message
     */
    public function sendTest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:twilio,vonage,custom,360dialog,wati',
            'phone_number' => 'required|string|max:20',
            'message' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->whatsappService->sendTestMessage(
                $request->provider,
                $request->phone_number,
                $request->message
            );

            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error sending test WhatsApp message: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API endpoint for AJAX sending
     */
    public function apiSend(Request $request): JsonResponse
    {
        return $this->send($request);
    }

    /**
     * API endpoint for recent messages
     */
    public function apiRecent(Request $request): JsonResponse
    {
        $limit = $request->limit ?? 10;
        
        $messages = WhatsAppMessage::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    /**
     * Log message
     */
    protected function logMessage($to, $message, $options, $result)
    {
        try {
            WhatsAppMessage::create([
                'to' => $to,
                'from' => config('whatsapp.twilio_whatsapp_from', config('whatsapp.vonage_whatsapp_from', '')),
                'message' => $message,
                'message_type' => $options['message_type'] ?? 'text',
                'template' => $options['template'] ?? null,
                'media_url' => $options['media_url'] ?? null,
                'caption' => $options['caption'] ?? null,
                'parameters' => $options['parameters'] ?? [],
                'provider' => $result['provider'] ?? 'unknown',
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['success'] ? 'sent' : 'failed',
                'response' => $result,
                'created_by' => auth()->id()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log WhatsApp message: ' . $e->getMessage());
        }
    }

    /**
     * Get message templates
     */
    protected function getMessageTemplates(): array
    {
        return [
            'payment_reminder' => '💰 Payment Reminder',
            'invoice_notification' => '📄 Invoice Notification',
            'property_inquiry' => '🏠 Property Inquiry',
            'maintenance_update' => '🔧 Maintenance Update',
            'booking_confirmation' => '✅ Booking Confirmation',
            'general_notification' => '📢 General Notification',
        ];
    }
}