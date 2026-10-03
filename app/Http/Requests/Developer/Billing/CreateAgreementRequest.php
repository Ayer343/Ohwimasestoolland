<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User;

class CreateAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return auth()->user()->type === User::TYPE_DEVELOPER;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules()
    {
        return [
            // =============================================
            // PRIMARY SUPER ADMIN FIELD - ADDED (REQUIRED)
            // =============================================
            'primary_super_admin_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $superAdmin = User::find($value);
                    if (!$superAdmin || $superAdmin->type !== User::TYPE_SUPER_ADMIN) {
                        $fail('The selected primary super admin is not valid or is not a super admin.');
                    }
                    if ($superAdmin && $superAdmin->status !== User::STATUS_ACTIVE) {
                        $fail('The selected super admin is not active.');
                    }
                },
            ],
            
            // Billing Contact Details (for Primary Super Admin) - Optional
            'billing_contact_name' => 'nullable|string|max:255',
            'billing_contact_email' => 'nullable|email|max:255',
            'billing_contact_phone' => 'nullable|string|max:20',
            
            // Agreement Details
            'amount' => 'required|numeric|min:100|max:100000',
            'currency' => 'required|string|size:3|in:GHS,USD,EUR,GBP',
            'billing_frequency' => 'required|in:one_time,weekly,monthly,quarterly,yearly',
            'description' => 'required|string|min:10|max:500',
            'notes' => 'nullable|string|max:1000',
            'start_date' => 'required|date|after_or_equal:today',
            
            // Options
            'generate_pdf' => 'nullable|boolean',
            'auto_send_for_signature' => 'nullable|boolean',
            
            // Payment Method - Updated with mobile money networks
            'payment_method' => 'nullable|in:mtn,telecel,airteltigo,bank_transfer,paystack,cash,check,mobile_money',
            
            // Mobile Money Fields (for mobile money payments)
            'payment_mobile_number' => 'nullable|required_if:payment_method,mtn,telecel,airteltigo,mobile_money|string|max:20|regex:/^(0)[0-9]{9}$/',
            'mobile_money_network' => 'nullable|required_if:payment_method,mtn,telecel,airteltigo,mobile_money|in:mtn,telecel,airteltigo,vodafone',
            
            // Bank Transfer Fields
            'payment_account_name' => 'nullable|required_if:payment_method,bank_transfer|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'payment_account_number' => 'nullable|required_if:payment_method,bank_transfer|string|max:50|regex:/^[0-9]+$/',
            'payment_bank_name' => 'nullable|required_if:payment_method,bank_transfer|string|max:255',
            'payment_bank_branch' => 'nullable|string|max:255',
            
            // Mobile Money specific field aliases (for compatibility with blade template)
            'payment_account_name_mm' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            // Primary Super Admin messages
            'primary_super_admin_id.required' => 'Please select a Primary Super Admin for billing invoices.',
            'primary_super_admin_id.exists' => 'The selected Primary Super Admin does not exist.',
            
            // Amount messages
            'amount.required' => 'Please enter an agreement amount.',
            'amount.min' => 'Minimum billing amount is GHS 100.',
            'amount.max' => 'Maximum billing amount is GHS 100,000.',
            'amount.numeric' => 'Amount must be a valid number.',
            
            // Currency messages
            'currency.required' => 'Please select a currency.',
            'currency.in' => 'Invalid currency selected.',
            
            // Billing Frequency messages
            'billing_frequency.required' => 'Please select a billing frequency.',
            'billing_frequency.in' => 'Invalid billing frequency selected.',
            
            // Description messages
            'description.required' => 'Please provide a description for the agreement.',
            'description.min' => 'Description must be at least 10 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
            
            // Start Date messages
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            
            // Mobile Number messages
            'payment_mobile_number.regex' => 'Please provide a valid Ghanaian mobile number (e.g., 024XXXXXXX).',
            'payment_mobile_number.required_if' => 'Mobile number is required for mobile money payments.',
            
            // Account Name messages
            'payment_account_name.regex' => 'Account name can only contain letters and spaces.',
            'payment_account_name.required_if' => 'Account name is required for bank transfer payments.',
            
            // Account Number messages
            'payment_account_number.regex' => 'Account number can only contain numbers.',
            'payment_account_number.required_if' => 'Account number is required for bank transfer payments.',
            
            // Bank Name messages
            'payment_bank_name.required_if' => 'Bank name is required for bank transfer payments.',
            
            // Mobile Money Network messages
            'mobile_money_network.required_if' => 'Mobile money network is required for mobile money payments.',
            'mobile_money_network.in' => 'Invalid mobile money network selected.',
            
            // Billing Contact messages
            'billing_contact_email.email' => 'Please provide a valid email address for the billing contact.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes()
    {
        return [
            'primary_super_admin_id' => 'Primary Super Admin',
            'billing_contact_name' => 'Billing Contact Name',
            'billing_contact_email' => 'Billing Contact Email',
            'billing_contact_phone' => 'Billing Contact Phone',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'billing_frequency' => 'Billing Frequency',
            'description' => 'Description',
            'notes' => 'Notes',
            'start_date' => 'Start Date',
            'payment_method' => 'Payment Method',
            'payment_mobile_number' => 'Mobile Money Number',
            'mobile_money_network' => 'Mobile Money Network',
            'payment_account_name' => 'Account Name',
            'payment_account_number' => 'Account Number',
            'payment_bank_name' => 'Bank Name',
            'payment_bank_branch' => 'Bank Branch',
            'payment_account_name_mm' => 'Account Name',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // If mobile_money is selected as payment method, normalize it to the specific network if needed
        if ($this->payment_method === 'mobile_money' && !$this->mobile_money_network) {
            // If using the generic mobile_money, we'll treat it as MTN by default
            // But we still need to validate mobile number
            $this->merge([
                'mobile_money_network' => $this->mobile_money_network ?? 'mtn',
            ]);
        }
        
        // If using specific network (mtn, telecel, airteltigo), treat as mobile money
        if (in_array($this->payment_method, ['mtn', 'telecel', 'airteltigo'])) {
            $this->merge([
                'mobile_money_network' => $this->payment_method,
                'payment_method' => 'mobile_money',
            ]);
        }
        
        // Clean phone number by removing spaces and special characters
        if ($this->payment_mobile_number) {
            $cleanedNumber = preg_replace('/[^0-9]/', '', $this->payment_mobile_number);
            // Ensure it starts with 0 and is 10 digits
            if (strlen($cleanedNumber) === 10 && substr($cleanedNumber, 0, 1) === '0') {
                $this->merge([
                    'payment_mobile_number' => $cleanedNumber,
                ]);
            }
        }
        
        // Clean account number by removing spaces
        if ($this->payment_account_number) {
            $this->merge([
                'payment_account_number' => preg_replace('/[^0-9]/', '', $this->payment_account_number),
            ]);
        }
        
        // Clean account name by removing extra spaces
        if ($this->payment_account_name) {
            $this->merge([
                'payment_account_name' => preg_replace('/\s+/', ' ', trim($this->payment_account_name)),
            ]);
        }
        
        // Handle mobile money account name if provided via payment_account_name_mm
        if ($this->payment_account_name_mm && !$this->payment_account_name) {
            $this->merge([
                'payment_account_name' => $this->payment_account_name_mm,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request after preparation.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function after()
    {
        return [
            function ($validator) {
                // Additional custom validation can go here
            },
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        
        // Check if it's an AJAX request
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors->toArray()
            ], 422));
        }
        
        // Store errors in session flash and redirect back
        session()->flash('error', 'Please fix the following errors: ' . $errors->first());
        
        parent::failedValidation($validator);
    }
}