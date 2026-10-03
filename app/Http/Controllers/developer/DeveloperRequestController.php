<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Jobs\SendSuperAdminPaymentRequestJob;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class DeveloperRequestController extends Controller
{
    use AuditLogger;

    /**
     * Create payment request for Super Admin
     */
    public function createSuperAdminPaymentRequest(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        $key = 'super_admin_request:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "Too many payment requests. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key);
        
        $validator = Validator::make($request->all(), [
            'super_admin_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if (!$user || !$user->isSuperAdmin()) {
                        $fail('Selected user is not a Super Admin');
                    }
                }
            ],
            'amount' => 'required|numeric|min:100|max:50000',
            'description' => 'required|string|max:500',
            'due_date' => 'required|date|after_or_equal:today|before_or_equal:+60 days',
            'payment_method' => 'required|in:bank_transfer,mobile_money,cash',
            'category' => 'required|in:hosting,maintenance,upgrade,emergency,other'
        ], [
            'amount.min' => 'Minimum payment request amount is GHS 100',
            'amount.max' => 'Maximum payment request amount is GHS 50,000',
            'due_date.before_or_equal' => 'Due date cannot be more than 60 days in the future'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the payment request errors');
        }
        
        DB::beginTransaction();
        
        try {
            $settings = DeveloperSetting::first();
            
            if (!Gate::allows('create-super-admin-request', $settings)) {
                abort(403, 'Unauthorized to create payment requests');
            }
            
            $data = $validator->validated();
            $superAdmin = User::findOrFail($data['super_admin_id']);
            $data['description'] = $this->sanitizeInput($data['description']);
            
            $paymentRequest = AdminBillingRecord::create([
                'developer_setting_id' => $settings->id,
                'super_admin_id' => $superAdmin->id,
                'invoice_number' => 'SA-REQ-' . Str::upper(uniqid()),
                'amount' => $data['amount'],
                'currency' => $settings->billing_currency ?? 'GHS',
                'description' => $data['description'],
                'due_date' => $data['due_date'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $data['payment_method'],
                'category' => $data['category'],
                'requested_by' => auth()->id(),
                'requested_at' => now(),
                'metadata' => [
                    'request_type' => 'developer_request',
                    'developer_name' => $settings->developer_name,
                    'developer_email' => $settings->developer_email,
                    'ip_address' => request()->ip()
                ]
            ]);
            
            SendSuperAdminPaymentRequestJob::dispatch($paymentRequest, $superAdmin);
            $this->notifyDeveloperAboutPaymentRequest($paymentRequest);
            
            DB::commit();
            
            $this->logAudit('payment_request_created', 'Super Admin payment request created', [
                'request_id' => $paymentRequest->id,
                'super_admin_id' => $superAdmin->id,
                'amount' => $data['amount'],
                'category' => $data['category']
            ]);
            
            return redirect()->route('developer.billing.dashboard')
                ->with('success', 'Payment request sent to Super Admin successfully!')
                ->with('payment_request_id', $paymentRequest->id);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create Super Admin payment request: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $this->sanitizeLogData($request->all())
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to create payment request: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Send payment reminder to Super Admin
     */
    public function sendPaymentReminderToSuperAdmin($paymentId)
    {
        $key = 'super_admin_reminder:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "Too many reminder requests. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key);
        
        try {
            $payment = AdminBillingRecord::findOrFail($paymentId);
            
            if (!Gate::allows('send-super-admin-reminder', $payment)) {
                abort(403, 'Unauthorized to send reminder for this payment');
            }
            
            $settings = DeveloperSetting::first();
            
            $lastReminder = DB::table('payment_reminders')
                ->where('admin_billing_record_id', $paymentId)
                ->orderBy('sent_at', 'desc')
                ->first();
            
            if ($lastReminder && $lastReminder->sent_at > now()->subHours(24)) {
                return redirect()->back()
                    ->with('warning', 'A reminder was already sent in the last 24 hours');
            }
            
            $superAdmin = User::find($payment->super_admin_id);
            
            if (!$superAdmin) {
                throw new \Exception('Super Admin not found');
            }
            
            SendSuperAdminPaymentRequestJob::dispatch($payment, $superAdmin, true);
            
            DB::table('payment_reminders')->insert([
                'admin_billing_record_id' => $paymentId,
                'sent_to' => $superAdmin->email,
                'sent_by' => auth()->id(),
                'reminder_type' => 'super_admin',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->logAudit('super_admin_reminder_sent', 'Payment reminder sent to Super Admin', [
                'payment_id' => $paymentId,
                'super_admin_id' => $superAdmin->id,
                'amount' => $payment->amount
            ]);
            
            return redirect()->back()
                ->with('success', 'Payment reminder sent to Super Admin successfully!')
                ->with('reminder_sent', true);
                
        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder to Super Admin: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }
    }

    /**
     * Cancel Super Admin payment request
     */
    public function cancelSuperAdminPaymentRequest($paymentId)
    {
        try {
            $payment = AdminBillingRecord::findOrFail($paymentId);
            
            if (!Gate::allows('cancel-super-admin-request', $payment)) {
                abort(403, 'Unauthorized to cancel this payment request');
            }
            
            if (!in_array($payment->status, ['pending', 'overdue'])) {
                return redirect()->back()
                    ->with('error', 'Only pending or overdue payments can be cancelled');
            }
            
            $payment->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => 'Cancelled by developer'
            ]);
            
            $this->notifySuperAdminAboutPaymentCancellation($payment);
            
            $this->logAudit('super_admin_request_cancelled', 'Super Admin payment request cancelled', [
                'payment_id' => $paymentId,
                'amount' => $payment->amount,
                'super_admin_id' => $payment->super_admin_id
            ]);
            
            return redirect()->route('developer.billing.super-admin-payments')
                ->with('success', 'Payment request cancelled successfully!');
                
        } catch (\Exception $e) {
            Log::error('Failed to cancel Super Admin payment request: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cancel payment request: ' . $e->getMessage());
        }
    }

    /**
     * Mark Super Admin payment as received
     */
    public function markSuperAdminPaymentReceived(Request $request, $paymentId)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        $validator = Validator::make($request->all(), [
            'received_amount' => 'required|numeric|min:100',
            'received_date' => 'required|date|before_or_equal:today|after_or_equal:-30 days',
            'transaction_reference' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('payment_transactions', 'transaction_reference')
            ],
            'payment_method' => 'nullable|in:bank_transfer,mobile_money,cash',
            'notes' => 'nullable|string|max:500'
        ], [
            'received_amount.min' => 'Minimum received amount is GHS 100',
            'received_date.after_or_equal' => 'Received date cannot be older than 30 days',
            'transaction_reference.unique' => 'This transaction reference has already been used'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide valid payment details');
        }
        
        DB::beginTransaction();
        
        try {
            $payment = AdminBillingRecord::findOrFail($paymentId);
            
            if (!Gate::allows('mark-super-admin-payment-received', $payment)) {
                abort(403, 'Unauthorized to mark this payment as received');
            }
            
            $data = $validator->validated();
            $data['notes'] = $this->sanitizeInput($data['notes'] ?? '');
            
            $amountDue = $payment->amount;
            $amountReceived = $data['received_amount'];
            
            if ($amountReceived >= $amountDue) {
                $paymentStatus = 'paid';
                $receivedAmount = $amountDue;
            } else {
                $paymentStatus = 'partial';
                $receivedAmount = $amountReceived;
            }
            
            $payment->update([
                'payment_status' => $paymentStatus,
                'amount_received' => $receivedAmount,
                'received_date' => $data['received_date'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => auth()->id(),
                'payment_notes' => $data['notes'],
                'metadata' => array_merge($payment->metadata ?? [], [
                    'marked_received_by' => auth()->user()->email,
                    'marked_received_at' => now()->toISOString(),
                    'verification' => 'developer_confirmed',
                    'ip_address' => request()->ip()
                ])
            ]);
            
            DB::table('payment_transactions')->insert([
                'admin_billing_record_id' => $payment->id,
                'amount' => $receivedAmount,
                'currency' => $payment->currency,
                'transaction_type' => 'receipt',
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'notes' => $data['notes'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->notifySuperAdminAboutPaymentConfirmation($payment);
            
            DB::commit();
            
            $this->logAudit('super_admin_payment_received', 'Super Admin payment marked as received', [
                'payment_id' => $paymentId,
                'amount_received' => $receivedAmount,
                'total_amount' => $amountDue,
                'payment_status' => $paymentStatus
            ]);
            
            return redirect()->route('developer.billing.super-admin-payments')
                ->with('success', 'Payment marked as received successfully!')
                ->with('payment_status', $paymentStatus);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to mark Super Admin payment as received: ' . $e->getMessage(), [
                'exception' => $e,
                'payment_id' => $paymentId
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to mark payment as received: ' . $e->getMessage())
                ->withInput();
        }
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    /**
     * Sanitize input
     */
    private function sanitizeInput($value)
    {
        if (is_string($value)) {
            return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    /**
     * Sanitize log data
     */
    private function sanitizeLogData(array $data)
    {
        $sensitiveFields = [
            'password',
            'secret',
            'token',
            'key',
            'credit_card',
            'cvv',
            'ssn',
            'developer_smtp_password',
            'developer_secret_key',
            'payment_mobile_number',
            'payment_account_number',
            'payment_account_name'
        ];
        
        $sanitized = $data;
        
        foreach ($sanitized as $key => $value) {
            foreach ($sensitiveFields as $sensitive) {
                if (stripos($key, $sensitive) !== false && !empty($value)) {
                    $sanitized[$key] = '[REDACTED]';
                }
            }
        }
        
        return $sanitized;
    }
}