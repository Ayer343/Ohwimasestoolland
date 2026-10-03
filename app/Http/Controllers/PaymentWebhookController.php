<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, $provider)
    {
        Log::info("Webhook received for: {$provider}", $request->all());
        return response()->json(['status' => 'received'], 200);
    }

    public function handlePaystack(Request $request)
    {
        Log::info('Paystack webhook received', $request->all());
        return response()->json(['status' => 'received'], 200);
    }

    public function handleExpressPay(Request $request)
    {
        Log::info('ExpressPay webhook received', $request->all());
        return response()->json(['status' => 'received'], 200);
    }

    public function handleHubtel(Request $request)
    {
        Log::info('Hubtel webhook received', $request->all());
        return response()->json(['status' => 'received'], 200);
    }

    public function handleFlutterwave(Request $request)
    {
        Log::info('Flutterwave webhook received', $request->all());
        return response()->json(['status' => 'received'], 200);
    }
}