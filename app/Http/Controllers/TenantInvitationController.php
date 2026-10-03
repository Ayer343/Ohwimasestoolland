<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use App\Mail\TenantInvitationMail;
use Illuminate\Support\Facades\Mail;

class TenantInvitationController extends Controller
{
    protected $smsService;
    protected $emailService;
    protected $whatsappService;

    public function __construct(
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService
    ) {
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Show tenant invitation acceptance form
     */
    public function accept($token)
    {
        $invitation = TenantInvitation::where('token', $token)
            ->with(['user', 'property', 'property.landlord'])
            ->first();

        if (!$invitation) {
            return redirect()->route('tenant.invitation.invalid');
        }

        if ($invitation->is_expired) {
            return redirect()->route('tenant.invitation.expired');
        }

        if ($invitation->status === TenantInvitation::STATUS_COMPLETED) {
            return redirect()->route('tenant.invitation.success');
        }

        return view('tenant.invitation.accept', compact('invitation'));
    }

    /**
     * Process tenant invitation acceptance
     */
    public function processAcceptance(Request $request, $token)
    {
        $invitation = TenantInvitation::where('token', $token)
            ->with(['user', 'property'])
            ->first();

        if (!$invitation || $invitation->is_expired) {
            return redirect()->route('tenant.invitation.invalid');
        }

        // Get the user
        $user = $invitation->user;
        
        if (!$user) {
            return redirect()->route('tenant.invitation.invalid')
                ->with('error', 'User account not found for this invitation.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:15|unique:users,phone,' . $user->id,
            'gender' => 'required|in:male,female,other',
            'agree_terms' => 'required|accepted',
        ], [
            'email.unique' => 'This email is already registered. Please use a different email or login.',
            'phone.unique' => 'This phone number is already registered.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Update user information
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'status' => User::STATUS_ACTIVE,
                'is_registered' => true,
                'registered_at' => now(),
            ]);

            // Mark invitation as completed
            $invitation->markAsCompleted();

            DB::commit();

            // Auto-login the tenant
            auth()->login($user);

            // Log successful registration
            Log::info('Tenant completed registration via invitation', [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'property_id' => $invitation->property_id
            ]);

            return redirect()->route('tenant.invitation.success')
                ->with('success', 'Registration completed successfully! You can now access your tenant dashboard.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process tenant invitation: ' . $e->getMessage(), [
                'invitation_id' => $invitation->id ?? null,
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to complete registration. Please try again.')
                ->withInput();
        }
    }

    /**
     * Show tenant registration completion form (alternative route)
     */
    public function completeRegistration($token)
    {
        $invitation = TenantInvitation::where('token', $token)
            ->with(['user', 'property', 'property.landlord'])
            ->first();

        if (!$invitation) {
            return redirect()->route('tenant.invitation.invalid');
        }

        if ($invitation->is_expired) {
            return redirect()->route('tenant.invitation.expired');
        }

        if ($invitation->status === TenantInvitation::STATUS_COMPLETED) {
            return redirect()->route('tenant.invitation.success');
        }

        return view('tenant.registration.complete', compact('invitation'));
    }

    /**
     * Submit tenant registration completion
     */
    public function submitRegistration(Request $request, $token)
    {
        return $this->processAcceptance($request, $token);
    }

    /**
     * Show expired invitation page
     */
    public function expired()
    {
        return view('tenant.invitation.expired');
    }

    /**
     * Show invalid invitation page
     */
    public function invalid()
    {
        return view('tenant.invitation.invalid');
    }

    /**
     * Show success page
     */
    public function success()
    {
        if (!auth()->check() || !auth()->user()->isTenant()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $invitation = TenantInvitation::where('user_id', $user->id)
            ->where('status', TenantInvitation::STATUS_COMPLETED)
            ->with('property')
            ->latest()
            ->first();

        return view('tenant.invitation.success', compact('user', 'invitation'));
    }

    /**
     * Send tenant invitation (admin only) - USING USER MODEL
     */
    public function sendInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'property_id' => 'required|exists:properties,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,email,whatsapp',
            'custom_message' => 'nullable|string|max:500',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::findOrFail($request->user_id);
            $property = Property::findOrFail($request->property_id);

            // Check if user is a tenant
            if (!$user->isTenant()) {
                throw new \Exception('Selected user is not a tenant.');
            }

            // Check if user is already associated with this property
            $existingAssociation = $property->tenants()->where('user_id', $user->id)->exists();
            if (!$existingAssociation) {
                throw new \Exception('This user is not associated with the specified property.');
            }

            // Create invitation
            $invitation = TenantInvitation::create([
                'user_id' => $user->id,
                'property_id' => $property->id,
                'invited_by' => auth()->id(),
                'channels' => $request->channels,
                'custom_message' => $request->custom_message,
                'status' => TenantInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays($request->expires_in_days ?? 7),
            ]);

            // Send invitation
            $result = $this->sendTenantInvitation($user, $property, $invitation, $request->channels);

            DB::commit();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'invitation' => $invitation->fresh(['user', 'property']),
                'invitation_url' => $invitation->getInvitationUrl(),
                'token' => $invitation->token
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send tenant invitation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send tenant invitation to a user
     */
    private function sendTenantInvitation(User $user, Property $property, TenantInvitation $invitation, array $channels): array
    {
        try {
            $results = [];
            $successCount = 0;
            $channelsSuccessful = [];
            $channelsFailed = [];

            // Process each channel
            foreach ($channels as $channel) {
                try {
                    $result = $this->sendTenantInvitationViaChannel($user, $property, $invitation, $channel);
                    
                    $results[$channel] = $result;
                    
                    if ($result['success']) {
                        $successCount++;
                        $channelsSuccessful[] = $channel;
                        
                        Log::info("Tenant invitation sent via {$channel}", [
                            'user_id' => $user->id,
                            'invitation_id' => $invitation->id,
                            'property_id' => $property->id
                        ]);
                    } else {
                        $channelsFailed[] = [
                            'channel' => $channel,
                            'error' => $result['message']
                        ];
                        
                        Log::warning("Failed to send tenant invitation via {$channel}", [
                            'user_id' => $user->id,
                            'invitation_id' => $invitation->id,
                            'error' => $result['message']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Error sending tenant invitation via {$channel}: " . $e->getMessage());
                    
                    $results[$channel] = [
                        'success' => false,
                        'message' => "Failed to send via {$channel}: " . $e->getMessage()
                    ];
                    
                    $channelsFailed[] = [
                        'channel' => $channel,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Update invitation status based on results
            if ($successCount > 0) {
                $invitation->markAsSent($channelsSuccessful);
                
                Log::info('Tenant invitation sent successfully', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $user->id,
                    'property_id' => $property->id,
                    'channels_successful' => $channelsSuccessful,
                    'token' => $invitation->token
                ]);
            } else {
                $invitation->markAsFailed('All communication channels failed');
                
                Log::error('All tenant invitation channels failed', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $user->id,
                    'channels_attempted' => $channels,
                    'results' => $results
                ]);
            }

            return [
                'success' => $successCount > 0,
                'message' => $successCount > 0 ? 
                    "Invitation sent via " . implode(', ', $channelsSuccessful) : 
                    "Failed to send invitation via any channel",
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl(),
                'channels_successful' => $channelsSuccessful,
                'channels_failed' => $channelsFailed,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'property_id' => $property->id,
                'invitation_id' => $invitation->id ?? null
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send tenant invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send tenant invitation via specific channel
     */
    private function sendTenantInvitationViaChannel(User $user, Property $property, TenantInvitation $invitation, string $channel): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendTenantEmailInvitation($user, $property, $invitation);
            case 'sms':
                return $this->sendTenantSmsInvitation($user, $property, $invitation);
            case 'whatsapp':
                return $this->sendTenantWhatsAppInvitation($user, $property, $invitation);
            default:
                return [
                    'success' => false,
                    'message' => "Unknown channel: {$channel}"
                ];
        }
    }

    /**
     * Send tenant email invitation
     */
    private function sendTenantEmailInvitation(User $user, Property $property, TenantInvitation $invitation): array
    {
        try {
            if (empty($user->email) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Tenant does not have a valid email address'
                ];
            }
            
            $mailData = [
                'user' => $user,
                'property' => $property,
                'invitation' => $invitation
            ];
            
            Mail::to($user->email)
                ->send(new \App\Mail\TenantInvitationMail($mailData));
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send tenant email invitation: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send tenant SMS invitation
     */
    private function sendTenantSmsInvitation(User $user, Property $property, TenantInvitation $invitation): array
    {
        try {
            // Check SMS service
            $smsStatus = $this->smsService->getSystemStatus();
            
            if (!$smsStatus['system_ready']) {
                return [
                    'success' => false,
                    'message' => 'SMS service is not ready'
                ];
            }
            
            if (empty($user->phone) || !User::isValidPhoneNumber($user->phone)) {
                return [
                    'success' => false,
                    'message' => 'Tenant does not have a valid phone number'
                ];
            }
            
            // Generate SMS message
            $landlordName = $property->landlord->name ?? 'N/A';
            $message = "Hello {$user->name},\n\n" .
              "You've been invited to register as a tenant at:\n" .
              "📍 {$property->property_name}\n" .
              "🏘️ {$property->street_name}, {$property->zone}\n" .
              "📞 Landlord: {$landlordName}\n\n" .
              "Complete your registration:\n" .
              $invitation->getInvitationUrl() . "\n\n" .
              "Link expires: " . $invitation->expires_at->format('M j, Y');
            
            // Send SMS
            $smsResult = $this->smsService->sendSMS(
                $this->smsService->getDefaultProvider() ?? 'arkesel',
                $user->phone,
                $message,
                [
                    'category' => 'tenant_invitation',
                    'user_id' => $user->id,
                    'property_id' => $property->id,
                    'invitation_id' => $invitation->id
                ]
            );
            
            return [
                'success' => $smsResult['success'] ?? false,
                'message' => $smsResult['message'] ?? 'SMS sending completed'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send tenant SMS invitation: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send tenant WhatsApp invitation
     */
    private function sendTenantWhatsAppInvitation(User $user, Property $property, TenantInvitation $invitation): array
    {
        try {
            if (empty($user->phone)) {
                return [
                    'success' => false,
                    'message' => 'Tenant does not have a phone number'
                ];
            }
            
            $landlordName = $property->landlord->name ?? 'N/A';
            
            $message = "🏠 *Tenant Registration Invitation*\n\n" .
                      "Hello {$user->name},\n\n" .
                      "You've been invited to register as a tenant at:\n" .
                      "📍 *Property:* {$property->property_name}\n" .
                      "🏘️ *Address:* {$property->street_name}, {$property->zone}\n" .
                      "👨‍💼 *Landlord:* {$landlordName}\n\n" .
                      "Complete your registration here:\n" .
                      $invitation->getInvitationUrl() . "\n\n" .
                      "⏰ *Link expires:* {$invitation->expires_at->format('F j, Y')}\n\n" .
                      "Thank you!";
        
            // Try to send via WhatsApp service
            if (method_exists($this->whatsappService, 'send')) {
                $result = $this->whatsappService->send($user->phone, $message);
            } else if (method_exists($this->whatsappService, 'sendMessage')) {
                $result = $this->whatsappService->sendMessage($user->phone, $message);
            } else {
                // Fallback to SMS
                return $this->sendTenantSmsInvitation($user, $property, $invitation);
            }
            
            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? ($result['success'] ? 'WhatsApp message sent' : 'Failed to send WhatsApp')
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to send tenant WhatsApp invitation: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Resend tenant invitation
     */
    public function resend(Request $request, TenantInvitation $tenantInvitation)
    {
        $validator = Validator::make($request->all(), [
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,email,whatsapp',
            'custom_message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update invitation with new channels/message
            $updateData = ['channels' => $request->channels];
            
            if ($request->filled('custom_message')) {
                $updateData['custom_message'] = $request->custom_message;
            }
            
            $tenantInvitation->update($updateData);
            
            // Mark as pending for resend
            $tenantInvitation->resend($request->channels);

            // Send invitation
            $result = $this->sendTenantInvitation(
                $tenantInvitation->user, 
                $tenantInvitation->property, 
                $tenantInvitation,
                $request->channels
            );

            DB::commit();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'invitation_url' => $tenantInvitation->getInvitationUrl(),
                'token' => $tenantInvitation->token
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resend tenant invitation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel tenant invitation
     */
    public function cancel(Request $request, TenantInvitation $tenantInvitation)
    {
        try {
            $tenantInvitation->update([
                'status' => TenantInvitation::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);

            Log::info('Tenant invitation cancelled', [
                'invitation_id' => $tenantInvitation->id,
                'user_id' => $tenantInvitation->user_id,
                'cancelled_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitation cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel tenant invitation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get property tenant invitations
     */
    public function getPropertyInvitations($propertyId)
    {
        $invitations = TenantInvitation::where('property_id', $propertyId)
            ->with(['user', 'property', 'invitedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'invitations' => $invitations
        ]);
    }

    /**
     * Get user tenant invitations
     */
    public function getUserInvitations($userId)
    {
        $invitations = TenantInvitation::where('user_id', $userId)
            ->with(['property', 'property.landlord', 'invitedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'invitations' => $invitations
        ]);
    }

    /**
     * Get invitation statistics
     */
    public function statistics()
    {
        $stats = [
            'total_invitations' => TenantInvitation::count(),
            'pending_invitations' => TenantInvitation::where('status', TenantInvitation::STATUS_PENDING)->count(),
            'sent_invitations' => TenantInvitation::where('status', TenantInvitation::STATUS_SENT)->count(),
            'completed_invitations' => TenantInvitation::where('status', TenantInvitation::STATUS_COMPLETED)->count(),
            'expired_invitations' => TenantInvitation::where('status', TenantInvitation::STATUS_EXPIRED)->count(),
            'cancelled_invitations' => TenantInvitation::where('status', TenantInvitation::STATUS_CANCELLED)->count(),
            'failed_invitations' => TenantInvitation::where('status', TenantInvitation::STATUS_FAILED)->count(),
            'recent_invitations' => TenantInvitation::with(['user', 'property'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Get invitation details
     */
    public function show($id)
    {
        $invitation = TenantInvitation::with(['user', 'property', 'property.landlord', 'invitedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'invitation' => $invitation
        ]);
    }

    /**
     * Test invitation delivery (admin only)
     */
    public function testDelivery(Request $request, TenantInvitation $tenantInvitation)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:sms,email,whatsapp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->sendTenantInvitationViaChannel(
                $tenantInvitation->user,
                $tenantInvitation->property,
                $tenantInvitation,
                $request->channel
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'test_result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}