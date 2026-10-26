<?php

namespace App\Http\Controllers;

use App\Models\PropertyUnit;
use App\Models\UnitInquiry;
use App\Services\UnitInquiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class UnitInquiryController extends Controller
{
    public function __construct(private UnitInquiryService $inquiryService) {}

    /**
     * Handle a guest inquiry submission for a specific unit.
     */
    public function store(Request $request, int $unitId)
    {
        // ── 1. Basic validation
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:100',
            'phone'   => 'required|string|max:20',
            'email'   => 'nullable|email|max:150',
            'message' => 'required|string|min:10|max:2000',
            // ✅ Honeypot: a hidden field that should be empty.
            'website' => 'nullable|string|max:0',
        ], [
            'phone.required'   => 'Please provide a phone number so the landlord can reach you.',
            'message.required' => 'Please write a message.',
            'message.min'      => 'Please write at least 10 characters.',
            'website.max'      => 'Spam detected.',
        ]);

        // ── 2. Honeypot check — if the "website" field has a value, it's a bot.
        if ($request->filled('website')) {
            Log::info('UnitInquiry: honeypot tripped', ['ip' => $request->ip()]);
            // Silently pretend to succeed so the bot doesn't retry.
            return back()->with('success', 'Your inquiry has been sent.');
        }

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // ── 3. Rate limiting — 3 inquiries per hour per IP
        $rateKey = 'unit-inquiry:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return back()->with(
                'error',
                "Too many inquiries from your IP. Please try again in {$seconds} seconds."
            );
        }

        // ── 4. Verify the unit is publicly visible
        $unit = PropertyUnit::withoutTrashed()
            ->where('is_archived', false)
            ->where('id', $unitId)
            ->whereHas('property', fn ($q) => $q->whereNull('deleted_at')->where('is_archived', false))
            ->first();

        if (!$unit) {
            abort(404);
        }

        // ── 5. Dispatch
        try {
            $result = $this->inquiryService->createAndDispatch([
                'property_unit_id' => $unit->id,
                'name'             => $validator->validated()['name'],
                'phone'            => $validator->validated()['phone'],
                'email'            => $validator->validated()['email'] ?? null,
                'message'          => $validator->validated()['message'],
                'channels'         => ['sms', 'email', 'whatsapp'],
            ], $request);

            RateLimiter::hit($rateKey, 3600);

            if (!$result['success']) {
                return back()
                    ->with('error', $result['message'])
                    ->withInput();
            }

            return back()->with('success', $result['message']);

        } catch (\Throwable $e) {
            Log::error('UnitInquiry: dispatch threw', [
                'unit_id' => $unit->id,
                'error'   => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'We could not send your inquiry. Please try again or contact support.')
                ->withInput();
        }
    }
}