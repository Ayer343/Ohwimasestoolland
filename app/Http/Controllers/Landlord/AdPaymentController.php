<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdInvoice;
use App\Models\SystemSetting;
use App\Services\AdCampaignService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdPaymentController extends Controller
{
    public function __construct(
        private AdCampaignService $adService,
        private PaymentService $paymentService
    ) {}

    /**
     * Display the payment page for a campaign.
     */
    public function pay(string $uuid)
    {
        $landlord = auth()->user();

        $campaign = AdCampaign::where('uuid', $uuid)
            ->forLandlord($landlord->id)
            ->with(['invoices', 'unit.property'])
            ->firstOrFail();

        $invoice = $campaign->invoices()
            ->where('status', AdInvoice::STATUS_PENDING)
            ->first();

        if (!$invoice) {
            return redirect()->route('landlord.ads.show', $uuid)
                ->with('info', 'No pending invoice for this campaign.');
        }

        return view('landlord.ads.pay', [
            'campaign'       => $campaign,
            'invoice'        => $invoice,
            'systemSettings' => SystemSetting::getSettings(),
        ]);
    }

    /**
     * ✅ NEW: Initiate payment with the selected gateway.
     *
     * This is what was missing. Without it, POST /landlord/ads/{uuid}/pay
     * (route name `landlord.ads.pay.process`) hit `pay()` — which just
     * re-rendered the page. That's why clicking "Pay" looked like
     * nothing happened.
     */
    public function payProcess(Request $request, string $uuid)
    {
        $landlord = auth()->user();

        $campaign = AdCampaign::where('uuid', $uuid)
            ->forLandlord($landlord->id)
            ->with(['invoices', 'unit.property', 'landlord'])
            ->firstOrFail();

        $invoice = $campaign->invoices()
            ->where('status', AdInvoice::STATUS_PENDING)
            ->first();

        if (!$invoice) {
            return redirect()->route('landlord.ads.show', $uuid)
                ->with('info', 'No pending invoice for this campaign.');
        }

        // Validate the selected gateway against the enabled providers.
        $validator = Validator::make($request->all(), [
            'gateway' => ['required', 'string', 'in:paystack,flutterwave,hubtel,expresspay'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $gateway = $request->input('gateway');
        $settings = SystemSetting::getSettings();

        // Server-side guard: the gateway must actually be enabled in settings.
        $isEnabled = method_exists($settings, 'isPaymentMethodEnabled')
            ? $settings->isPaymentMethodEnabled($gateway)
            : false;

        if (!$isEnabled) {
            return back()->with('error', "The {$gateway} payment method is not available. Please choose another.");
        }

        // Check configuration at the PaymentService level too.
        try {
            $configurationStatus = $this->paymentService->checkPaymentMethodConfiguration();
            if (
                empty($configurationStatus[$gateway]['enabled']) ||
                empty($configurationStatus[$gateway]['configured'])
            ) {
                return back()->with('error', "The {$gateway} gateway is not properly configured. Please contact support.");
            }
        } catch (\Throwable $e) {
            Log::error('Ad payment: failed to check gateway configuration', [
                'gateway' => $gateway,
                'error'   => $e->getMessage(),
            ]);
            return back()->with('error', 'Unable to verify payment gateway configuration.');
        }

        // Amount is what's on the invoice, expressed in the system currency.
        $amount         = (float) $invoice->amount;
        $currency       = $invoice->currency ?: $settings->currency_code ?: 'GHS';
        $unitLabel      = $campaign->unit->unit_name
                        ?? $campaign->unit->unit_number
                        ?? 'Unit #' . $campaign->property_unit_id;
        $description    = "Ad campaign — {$campaign->plan_name} ({$unitLabel})";

        // Persist a stable reference on the invoice before we call the
        // gateway, so even a partial failure lets us reconcile.
        $reference = $invoice->gateway_reference ?: ('AD-' . strtoupper(Str::random(12)));
        if (!$invoice->gateway_reference) {
            $invoice->gateway_reference = $reference;
            $invoice->gateway           = $gateway;
            $invoice->save();
        }

        // Build the payload expected by PaymentService::processPayment.
        $paymentData = [
            'amount'           => $amount,
            'currency'         => $currency,
            'description'      => $description,
            'payment_provider' => $gateway,
            'payment_type'     => 'ad_campaign',
            'reference'        => $reference,
            'property_id'      => null,  // Ad payments don't attach to a property
            'metadata'         => [
                'invoice_number' => $invoice->invoice_number,
                'invoice_id'     => $invoice->id,
                'campaign_id'    => $campaign->id,
                'campaign_uuid'  => $campaign->uuid,
                'landlord_id'    => $landlord->id,
                'plan_name'      => $campaign->plan_name,
                'unit_label'     => $unitLabel,
                'formatted_amount' => $settings->formatAmount($amount),
                'callback_url'   => route('ads.payment.callback', ['gateway' => $gateway]),
                'is_ad_payment'  => true,
                'ad_campaign_id'   => $campaign->id,
            ],
        ];

        // Provider-specific required fields.
        if ($gateway === 'paystack') {
            $email = $request->input('email') ?: $landlord->email;
            if (!$email) {
                return back()->with('error', 'Your account does not have an email address. Please update your profile before paying.');
            }
            $paymentData['email'] = $email;
        }

        if (in_array($gateway, ['flutterwave', 'hubtel', 'expresspay'], true)) {
            $phone = $request->input('phone_number') ?: $landlord->phone;
            if (!$phone) {
                return back()->with('error', 'A phone number is required for this payment method. Please update your profile.');
            }
            $paymentData['phone_number'] = $phone;
        }

        // Hand off to PaymentService. This is the step that actually
        // creates the Paystack transaction and returns a redirect URL.
        try {
            $result = $this->paymentService->processPayment($paymentData, $landlord);
        } catch (\Throwable $e) {
            Log::error('Ad payment: PaymentService::processPayment threw', [
                'gateway'    => $gateway,
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Payment could not be initiated: ' . $e->getMessage());
        }

        if (empty($result['success'])) {
            Log::warning('Ad payment: processPayment failed', [
                'gateway'    => $gateway,
                'invoice_id' => $invoice->id,
                'message'    => $result['message'] ?? 'Unknown failure',
            ]);
            return back()->with('error', $result['message'] ?? 'Payment could not be initiated. Please try again.');
        }

        // Persist the gateway's transaction reference for later reconciliation.
        if (!empty($result['transaction_id'])) {
            $invoice->gateway_reference = $result['transaction_id'];
            $invoice->gateway           = $gateway;
            $invoice->save();
        }

        Log::info('Ad payment initiated', [
            'gateway'        => $gateway,
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'campaign_id'    => $campaign->id,
            'amount'         => $amount,
            'transaction_id' => $result['transaction_id'] ?? null,
            'has_redirect'   => !empty($result['redirect_url']),
        ]);

        // Send the user to the gateway checkout.
        if (!empty($result['redirect_url'])) {
            return redirect()->away($result['redirect_url']);
        }

        // Some gateways (mobile money OTP flows) return a local
        // confirmation page instead of a redirect.
        return redirect()->route('landlord.ads.show', $campaign->uuid)
            ->with('success', $result['message'] ?? 'Payment initiated. Complete the verification on your phone.');
    }

    /**
     * Gateway callback — this is where Paystack redirects after checkout.
     */
    public function callback(Request $request, string $gateway)
    {
        $reference = $request->input('reference')
            ?? $request->input('trxref')
            ?? $request->query('reference')
            ?? $request->query('trxref');

        if (!$reference) {
            Log::warning('Ad payment callback: missing reference', [
                'gateway' => $gateway,
                'query'   => $request->query(),
            ]);
            return redirect()->route('landlord.ads.index')
                ->with('error', 'Missing payment reference from the gateway.');
        }

        try {
            $result = $this->paymentService->verifyPayment($gateway, $reference);

            if (empty($result['success'])) {
                Log::warning('Ad payment callback: verification failed', [
                    'gateway'   => $gateway,
                    'reference' => $reference,
                    'message'   => $result['message'] ?? null,
                ]);
                return redirect()->route('landlord.ads.index')
                    ->with('error', 'Payment verification failed: ' . ($result['message'] ?? 'unknown error'));
            }

            // Locate the invoice. Prefer the stored gateway_reference,
            // fall back to invoice_number from metadata.
            $invoice = AdInvoice::where('gateway_reference', $reference)->first();

            if (!$invoice) {
                $meta = $result['metadata'] ?? [];
                if (!empty($meta['invoice_number'])) {
                    $invoice = AdInvoice::where('invoice_number', $meta['invoice_number'])->first();
                }
            }

            if (!$invoice) {
                Log::error('Ad payment callback: invoice not found', [
                    'gateway'   => $gateway,
                    'reference' => $reference,
                    'metadata'  => $result['metadata'] ?? [],
                ]);
                return redirect()->route('landlord.ads.index')
                    ->with('error', 'Payment received but the invoice could not be located. Please contact support.');
            }

            // Only flip the invoice if it's still pending — this makes
            // callback delivery idempotent (gateways sometimes retry).
            if ($invoice->status === AdInvoice::STATUS_PENDING) {
                $this->adService->markInvoicePaid(
                    $invoice,
                    $gateway,
                    $reference,
                    $result['data'] ?? []
                );

                Log::info('Ad payment callback: invoice marked paid', [
                    'gateway'    => $gateway,
                    'reference'  => $reference,
                    'invoice_id' => $invoice->id,
                ]);
            }

            $campaignUuid = $invoice->campaign->uuid ?? null;
            if ($campaignUuid) {
                return redirect()->route('landlord.ads.show', $campaignUuid)
                    ->with('success', 'Payment confirmed. Your ad is now live!');
            }

            return redirect()->route('landlord.ads.index')
                ->with('success', 'Payment confirmed. Your campaign is now active.');

        } catch (\Throwable $e) {
            Log::error('Ad payment callback threw', [
                'gateway'   => $gateway,
                'reference' => $reference,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()->route('landlord.ads.index')
                ->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }
}