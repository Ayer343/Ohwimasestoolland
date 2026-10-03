<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Services\WebhookService;
use Illuminate\Http\Request;

class TransferWebhookController extends Controller
{
    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Get webhook logs for a transfer
     */
    public function index(Property $property, PropertyOwnershipTransfer $transfer, Request $request)
    {
        $this->authorize('manage-webhooks', $transfer);

        $webhooks = $transfer->webhookLogs()->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'webhooks' => $webhooks,
            'transfer' => $transfer->only(['id', 'status', 'document_reference'])
        ]);
    }

    /**
     * Retry a failed webhook
     */
    public function retry(Request $request, Property $property, PropertyOwnershipTransfer $transfer, $webhookId)
    {
        $this->authorize('manage-webhooks', $transfer);

        if (!$this->webhookService) {
            return $this->errorResponse($request, 'Webhook service not available.');
        }

        $result = $this->webhookService->retryWebhook($webhookId);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'webhook' => $result['webhook'] ?? null
        ]);
    }

    /**
     * Verify digital signature
     */
    public function verifySignature(Property $property, PropertyOwnershipTransfer $transfer, Request $request)
    {
        $digitalSignatureService = app(\App\Services\DigitalSignatureService::class);
        
        if (!$digitalSignatureService) {
            return response()->json([
                'success' => false,
                'message' => 'Digital signature service not available.'
            ], 501);
        }

        $signatureData = $transfer->metadata['digital_signature'] ?? null;
        
        if (!$signatureData) {
            return response()->json([
                'success' => false,
                'message' => 'No digital signature found for this transfer.'
            ], 404);
        }

        $verificationResult = $digitalSignatureService->verifySignature($signatureData);

        $transfer->update([
            'metadata->digital_signature_verified' => $verificationResult['valid'],
            'metadata->digital_signature_verification_date' => now()->toISOString(),
            'metadata->digital_signature_verification_details' => $verificationResult
        ]);

        return response()->json([
            'success' => true,
            'verified' => $verificationResult['valid'],
            'details' => $verificationResult,
            'transfer' => $transfer->fresh()
        ]);
    }

    /**
     * Error response
     */
    private function errorResponse(Request $request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message
            ], 500);
        }
        return redirect()->back()->with('error', $message);
    }
}