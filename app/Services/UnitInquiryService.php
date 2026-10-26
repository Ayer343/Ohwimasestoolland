<?php

namespace App\Services;

use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use App\Models\UnitInquiry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UnitInquiryService
{
    /**
     * NOTE: SystemSetting is intentionally NOT injected.
     * It's an Eloquent model with a memoized singleton accessor —
     * injecting it via the container would give you an empty,
     * unsaved instance. Call settings() wherever you need it.
     */
    public function __construct(
        private SmsService $smsService,
        private WhatsAppService $whatsAppService
    ) {}

    private function settings(): SystemSetting
    {
        return SystemSetting::getSettings();
    }

    /**
     * Create an inquiry and attempt delivery on every available channel.
     *
     * @return array{ success: bool, inquiry: UnitInquiry|null, message: string, channels: array }
     */
    public function createAndDispatch(array $data, Request $request): array
    {
        $unit = PropertyUnit::with(['property.landlord'])
            ->withoutTrashed()
            ->where('is_archived', false)
            ->whereHas('property', fn ($q) => $q->whereNull('deleted_at')->where('is_archived', false))
            ->findOrFail($data['property_unit_id']);

        $landlord = $unit->property?->landlord;
        if (!$landlord) {
            return [
                'success'  => false,
                'inquiry'  => null,
                'message'  => 'This listing is temporarily unavailable.',
                'channels' => [],
            ];
        }

        // ── Persist first — the visitor's message is never lost, even if
        //    every delivery channel fails.
        $inquiry = UnitInquiry::create([
            'property_unit_id'   => $unit->id,
            'property_id'        => $unit->property_id,
            'landlord_id'        => $landlord->id,
            'user_id'            => auth()->id(),
            'name'               => $data['name'],
            'phone'              => $data['phone'],
            'email'              => $data['email'] ?? null,
            'message'            => $data['message'],
            'channels_requested' => $data['channels'] ?? [],
            'ip_address'         => $request->ip(),
            'user_agent'         => substr((string) $request->userAgent(), 0, 255),
            'status'             => UnitInquiry::STATUS_NEW,
        ]);

        $sent   = [];
        $failed = [];

        // ── Email (primary delivery channel — the landlord's inbox)
        if (!empty($landlord->email)) {
            try {
                Mail::to($landlord->email)
                    ->send(new \App\Mail\UnitInquiryReceived($inquiry, $unit, $landlord));
                $sent[] = 'email';
            } catch (\Throwable $e) {
                $failed[] = 'email';
                Log::warning('UnitInquiry: email send failed', [
                    'inquiry_id' => $inquiry->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        } else {
            $failed[] = 'email (no address)';
        }

        // ── SMS (only if the landlord has a phone and the service is ready)
        if (!empty($landlord->phone)) {
            try {
                $smsStatus = $this->smsService->getSystemStatus();
                $smsReady  = (bool) (
                    $smsStatus['system_ready']
                    ?? $smsStatus['can_send_sms']
                    ?? $smsStatus['ready']
                    ?? false
                );

                if ($smsReady) {
                    $defaultProvider = $this->smsService->getDefaultProvider() ?? 'arkesel';

                    $result = $this->smsService->sendSMS(
                        $defaultProvider,
                        $landlord->phone,
                        $this->buildSmsMessage($inquiry, $unit, $landlord),
                        [
                            'is_test'     => false,
                            'category'    => 'unit_inquiry',
                            'inquiry_id'  => $inquiry->id,
                            'property_id' => $unit->property_id,
                            'unit_id'     => $unit->id,
                        ]
                    );

                    if (!empty($result['success'])) {
                        $sent[] = 'sms';
                    } else {
                        $failed[] = 'sms';
                    }
                } else {
                    $failed[] = 'sms (not configured)';
                }
            } catch (\Throwable $e) {
                $failed[] = 'sms';
                Log::warning('UnitInquiry: SMS send failed', [
                    'inquiry_id' => $inquiry->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // ── WhatsApp (only if the landlord has a phone and WA is ready)
        if (!empty($landlord->phone) && class_exists(WhatsAppService::class)) {
            try {
                $waStatus = $this->whatsAppService->getSystemStatus();
                $waReady  = (bool) (
                    $waStatus['system_ready']
                    ?? $waStatus['can_send']
                    ?? $waStatus['configured']
                    ?? false
                );

                if ($waReady) {
                    $waResult = $this->whatsAppService->send(
                        $landlord->phone,
                        $this->buildWhatsAppMessage($inquiry, $unit, $landlord)
                    );

                    if (!empty($waResult['success'])) {
                        $sent[] = 'whatsapp';
                    } else {
                        $failed[] = 'whatsapp';
                    }
                } else {
                    $failed[] = 'whatsapp (not configured)';
                }
            } catch (\Throwable $e) {
                $failed[] = 'whatsapp';
                Log::warning('UnitInquiry: WhatsApp send failed', [
                    'inquiry_id' => $inquiry->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $inquiry->update([
            'channels_sent'   => $sent,
            'channels_failed' => $failed,
        ]);

        // Guest confirmation (only when the guest provided an email)
        if (!empty($inquiry->email)) {
            try {
                Mail::to($inquiry->email)
                    ->send(new \App\Mail\UnitInquiryConfirmation($inquiry, $unit, $landlord));
            } catch (\Throwable $e) {
                Log::warning('UnitInquiry: guest confirmation failed', [
                    'inquiry_id' => $inquiry->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if (empty($sent)) {
            Log::warning('UnitInquiry: no channels delivered', [
                'inquiry_id' => $inquiry->id,
                'failed'     => $failed,
            ]);

            return [
                'success'  => false,
                'inquiry'  => $inquiry,
                'message'  => 'Your inquiry was saved but we could not reach the landlord. Support has been notified.',
                'channels' => ['sent' => $sent, 'failed' => $failed],
            ];
        }

        return [
            'success'  => true,
            'inquiry'  => $inquiry,
            'message'  => 'Your inquiry has been sent to the landlord.',
            'channels' => ['sent' => $sent, 'failed' => $failed],
        ];
    }

    private function buildSmsMessage(UnitInquiry $inquiry, PropertyUnit $unit, User $landlord): string
    {
        $unitLabel = $unit->unit_name ?? $unit->unit_number ?? 'Unit #' . $unit->id;
        $shortMsg  = Str::limit($inquiry->message, 120);

        return "New inquiry for {$unitLabel}\n\n"
             . "From: {$inquiry->name}\n"
             . "Phone: {$inquiry->phone}\n"
             . "Message: {$shortMsg}\n\n"
             . 'Reply: ' . route('landlord.inquiries.show', $inquiry->uuid);
    }

    private function buildWhatsAppMessage(UnitInquiry $inquiry, PropertyUnit $unit, User $landlord): string
    {
        $unitLabel = $unit->unit_name ?? $unit->unit_number ?? 'Unit #' . $unit->id;
        $shortMsg  = Str::limit($inquiry->message, 400);

        return "🏠 *New Inquiry — {$unitLabel}*\n\n"
             . "*From:* {$inquiry->name}\n"
             . "*Phone:* {$inquiry->phone}\n"
             . ($inquiry->email ? "*Email:* {$inquiry->email}\n" : '')
             . "*Message:*\n{$shortMsg}\n\n"
             . '🔗 *Reply:* ' . route('landlord.inquiries.show', $inquiry->uuid);
    }
}