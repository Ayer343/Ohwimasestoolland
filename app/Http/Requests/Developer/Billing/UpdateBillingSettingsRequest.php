<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingSettingsRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'monthly_billing_amount' => 'required|numeric|min:0|max:100000',
            'billing_currency' => 'required|string|in:GHS,USD,EUR',
            'billing_cycle' => 'required|string|in:monthly,quarterly,yearly,custom',
            'billing_start_date' => 'required|date|after_or_equal:today',
            'payment_method' => 'required|in:mtn,telecel,airteltigo,bank_transfer,paystack',
            'payment_mobile_number' => 'nullable|required_if:payment_method,mtn,telecel,airteltigo|string|max:20|regex:/^(0)[0-9]{9}$/',
            'payment_account_name' => 'nullable|required_if:payment_method,bank_transfer|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'payment_account_number' => 'nullable|required_if:payment_method,bank_transfer|string|max:50|regex:/^[0-9]+$/',
            'payment_bank_name' => 'nullable|required_if:payment_method,bank_transfer|string|max:255',
            'payment_bank_branch' => 'nullable|string|max:255',
            'auto_generate_invoices' => 'nullable|boolean',
            'invoice_due_days' => 'nullable|integer|min:1|max:90',
            'send_payment_reminders' => 'nullable|boolean',
            'reminder_days_before' => 'nullable|array',
            'reminder_days_before.*' => 'integer|min:1|max:30',
        ];
    }

    public function messages()
    {
        return [
            'payment_mobile_number.regex' => 'Please provide a valid Ghanaian mobile number',
            'payment_account_name.regex' => 'Account name can only contain letters and spaces',
            'payment_account_number.regex' => 'Account number can only contain numbers',
            'invoice_due_days.min' => 'Invoice due days must be at least 1 day',
            'invoice_due_days.max' => 'Invoice due days cannot exceed 90 days',
        ];
    }
}