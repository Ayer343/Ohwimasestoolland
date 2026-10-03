<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LandlordInvitation;
use App\Models\Property;
use App\Services\MultiChannelInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  // ✅ FIXED: Added proper Log facade import
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;  // ✅ ADDED: For authorization
use Illuminate\Support\Str;

class PropertyLandlordController extends Controller
{
    protected $multiChannelInvitationService;

    public function __construct(MultiChannelInvitationService $multiChannelInvitationService)
    {
        $this->multiChannelInvitationService = $multiChannelInvitationService;
    }

    /**
     * Find or create landlord by phone number
     * 
     * @param string $primaryPhone
     * @param string|null $name
     * @param string|null $email
     * @param array $allPhones
     * @return int|null
     */
    public function findOrCreateLandlordByPhone($primaryPhone, $name = null, $email = null, $allPhones = [])
    {
        // Clean the primary phone number
        $cleanPrimaryPhone = preg_replace('/[^0-9]/', '', $primaryPhone);
        
        // Try to find existing landlord by any phone format
        $landlord = User::findByAnyPhoneFormat($cleanPrimaryPhone);
        
        if ($landlord && $landlord->isLandlord()) {
            // ✅ FIXED: Pass proper parameters to updateLandlordPhones
            $this->updateLandlordPhones($landlord, $allPhones);
            
            Log::info('Found existing landlord', [
                'landlord_id' => $landlord->id,
                'name' => $landlord->name,
                'primary_phone' => $cleanPrimaryPhone
            ]);
            
            return $landlord->id;
        }

        // Create new landlord if name and phone are provided
        if ($name && $primaryPhone) {
            $landlordData = [
                'name' => $name,
                'phone' => $cleanPrimaryPhone,
                'email' => $email ?: ($cleanPrimaryPhone . '@propertyportal.com'),
                'type' => User::TYPE_LANDLORD,
                'password' => bcrypt(Str::random(12)),
                'email_verified_at' => null,
                'is_active' => false,
                'status' => User::STATUS_ACTIVE,
            ];

            $landlord = User::create($landlordData);

            // ✅ FIXED: Pass proper parameters to updateLandlordPhones
            $this->updateLandlordPhones($landlord, $allPhones);

            // ✅ FIXED: Using proper Log facade
            Log::info("Created new landlord with phones", [
                'landlord_id' => $landlord->id,
                'name' => $name,
                'primary_phone' => $cleanPrimaryPhone,
                'total_phones' => is_array($allPhones) ? count($allPhones) : 0
            ]);

            return $landlord->id;
        }

        throw new \Exception('Failed to find or create landlord. Please provide valid phone number and name.');
    }

    /**
     * Update landlord's additional phones
     * 
     * @param User $landlord
     * @param array|string $phones
     * @return void
     */
    private function updateLandlordPhones(User $landlord, $phones)
    {
        try {
            // ✅ FIXED: Using proper Log facade
            Log::info('Updating landlord phones', [
                'landlord_id' => $landlord->id,
                'phone_count' => is_array($phones) ? count($phones) : 1
            ]);

            // Check if landlord is actually a landlord
            if (!$landlord->isLandlord()) {
                Log::warning('Cannot update phones: User is not a landlord', [
                    'landlord_id' => $landlord->id,
                    'user_type' => $landlord->type
                ]);
                return;
            }

            // ✅ FIXED: Check if the phones method exists
            if (!method_exists($landlord, 'updatePhones')) {
                Log::warning('Cannot update phones: updatePhones method not available', [
                    'landlord_id' => $landlord->id
                ]);
                
                // Fallback: Try using the phones relationship if it exists
                if (method_exists($landlord, 'phones')) {
                    $this->updatePhonesViaRelationship($landlord, $phones);
                } else {
                    // Fallback: Just save the primary phone
                    $this->savePrimaryPhoneOnly($landlord, $phones);
                }
                return;
            }

            // Call the updatePhones method if it exists
            $landlord->updatePhones($phones);

            Log::info("Successfully updated landlord phones", [
                'landlord_id' => $landlord->id,
                'phones_count' => is_array($phones) ? count($phones) : 0
            ]);

        } catch (\Exception $e) {
            // ✅ FIXED: Using proper Log facade with error level
            Log::error('Error updating landlord phones: ' . $e->getMessage(), [
                'landlord_id' => $landlord->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw the exception - we want the registration to continue
            // Just log the error and continue
        }
    }

    /**
     * Fallback: Update phones via relationship
     * 
     * @param User $landlord
     * @param array|string $phones
     * @return void
     */
    private function updatePhonesViaRelationship(User $landlord, $phones)
    {
        try {
            // If phones is a string, convert to array
            if (!is_array($phones)) {
                $phones = $phones ? [$phones] : [];
            }
            
            // Filter out empty phones
            $phones = array_filter($phones, function($phone) {
                return !empty(trim($phone));
            });
            
            if (empty($phones)) {
                return;
            }
            
            // Get existing phone records
            $existingPhones = $landlord->phones()->get();
            
            // Delete existing additional phones (keep only the primary)
            foreach ($existingPhones as $existingPhone) {
                if ($existingPhone->phone !== $landlord->phone) {
                    $existingPhone->delete();
                }
            }
            
            // Add new phones (skip the primary)
            foreach ($phones as $phone) {
                if ($phone !== $landlord->phone) {
                    $landlord->phones()->create([
                        'phone' => $phone,
                        'is_primary' => false
                    ]);
                }
            }
            
            Log::info('Landlord phones updated via relationship', [
                'landlord_id' => $landlord->id,
                'phones_added' => count($phones)
            ]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to update phones via relationship: ' . $e->getMessage(), [
                'landlord_id' => $landlord->id
            ]);
        }
    }

    /**
     * Fallback: Save only the primary phone
     * 
     * @param User $landlord
     * @param array|string $phones
     * @return void
     */
    private function savePrimaryPhoneOnly(User $landlord, $phones)
    {
        try {
            // If phones is an array, get the first one as primary
            if (is_array($phones) && !empty($phones)) {
                $primaryPhone = $phones[0];
            } elseif (is_string($phones) && !empty($phones)) {
                $primaryPhone = $phones;
            } else {
                return;
            }
            
            // Clean the phone number
            $cleanPhone = preg_replace('/[^0-9]/', '', $primaryPhone);
            
            if (!empty($cleanPhone) && $landlord->phone !== $cleanPhone) {
                $landlord->phone = $cleanPhone;
                $landlord->save();
                
                Log::info('Primary phone updated (fallback method)', [
                    'landlord_id' => $landlord->id,
                    'phone' => $cleanPhone
                ]);
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to save primary phone (fallback): ' . $e->getMessage(), [
                'landlord_id' => $landlord->id
            ]);
        }
    }

    /**
     * Send landlord invitation
     * 
     * @param User $landlord
     * @param Property $property
     * @param Request $request
     * @return array
     */
    public function sendLandlordInvitation(User $landlord, Property $property, Request $request): array
    {
        try {
            $channels = $request->invitation_channels ?? ['email'];
            $availableChannels = $this->multiChannelInvitationService->getAvailableChannels($landlord);
            
            $channels = array_intersect($channels, $availableChannels);
            
            if (empty($channels)) {
                return [
                    'success' => false,
                    'message' => 'No available communication channels for this landlord.',
                    'available_channels' => $availableChannels
                ];
            }

            $invitation = LandlordInvitation::create([
                'property_id' => $property->id,
                'landlord_id' => $landlord->id,
                'invited_by' => auth()->id(),
                'channels' => $channels,
                'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                'custom_message' => $request->custom_message,
                'expires_at' => now()->addDays($request->expires_in_days ?? 7)
            ]);

            $results = [];
            $successCount = 0;

            foreach ($channels as $channel) {
                $result = $this->multiChannelInvitationService->sendMessage(
                    $landlord,
                    $this->generateLandlordInvitationMessage($landlord, $property, $invitation),
                    'landlord_registration',
                    [
                        'channel' => $channel,
                        'property_id' => $property->id,
                        'landlord_id' => $landlord->id,
                        'invitation_id' => $invitation->id,
                        'invitation_token' => $invitation->token,
                        'registration_pattern' => $property->registration_pattern,
                        'property_name' => $property->property_name,
                        'zone' => $property->zone,
                        'street_name' => $property->street_name,
                        'landlord' => $landlord,
                        'property' => $property,
                        'invitation' => $invitation,
                        'invitation_url' => $invitation->getInvitationUrl()
                    ]
                );

                $results[$channel] = $result;
                if ($result['success']) {
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                $invitation->markAsSent($channels);
                
                Log::info('Landlord invitation sent successfully', [
                    'invitation_id' => $invitation->id,
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'token' => $invitation->token,
                    'channels_successful' => array_keys(array_filter($results, fn($r) => $r['success']))
                ]);
            } else {
                $invitation->markAsFailed('All communication channels failed');
            }

            return [
                'success' => $successCount > 0,
                'message' => $successCount > 0 ? 
                    "Invitation sent via {$successCount} channel(s)" : 
                    "Failed to send invitation via any channel",
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl(),
                'results' => $results,
                'channels_successful' => array_keys(array_filter($results, fn($r) => $r['success']))
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send landlord invitation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send landlord invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate landlord invitation message
     * 
     * @param User $landlord
     * @param Property $property
     * @param LandlordInvitation $invitation
     * @return string
     */
    private function generateLandlordInvitationMessage(User $landlord, Property $property, LandlordInvitation $invitation): string
    {
        $propertyType = $property->propertyType->name ?? ($property->custom_property_type ?? 'Property');
        $registrationDate = $property->registration_date?->format('M j, Y') ?? 'Recently';

        $baseMessage = "Hello {$landlord->name}!\n\n" .
                      "Your {$propertyType} has been registered in our system:\n" .
                      "🏠 Property: {$property->property_name}\n" .
                      "📍 Location: {$property->street_name}, {$property->zone}\n" .
                      "🔢 Registration: {$property->registration_pattern}\n" .
                      "📅 Registered: {$registrationDate}\n\n" .
                      "To complete your registration and access your landlord portal, please set your password:\n" .
                      "{$invitation->getInvitationUrl()}\n\n" .
                      "This link expires in 7 days.\n\n" .
                      "Thank you for registering with us!";

        return $baseMessage;
    }

    /**
     * Resend landlord invitation
     * 
     * @param Request $request
     * @param Property $property
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function resendLandlordInvitation(Request $request, Property $property)
    {
        if (!Gate::allows('update', $property)) {
            if (auth()->user()->isFieldAgent() && $property->registered_by != auth()->id()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only resend invitations for properties you registered.'
                    ], 403);
                }
                return redirect()->back()->with('error', 'You can only resend invitations for properties you registered.');
            }
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to resend invitation for this property.'
                ], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized to resend invitation for this property.');
        }

        $landlord = $property->landlord;
        if (!$landlord) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No landlord associated with this property.'
                ], 404);
            }
            return redirect()->back()->with('error', 'No landlord associated with this property.');
        }

        $validator = Validator::make($request->all(), [
            'channels' => 'sometimes|array',
            'channels.*' => 'in:sms,email,whatsapp',
            'custom_message' => 'nullable|string|max:500',
            'expires_in_days' => 'nullable|integer|min:1|max:30'
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $invitation = LandlordInvitation::where('property_id', $property->id)
                ->where('landlord_id', $landlord->id)
                ->where('status', '!=', LandlordInvitation::STATUS_CANCELLED)
                ->latest()
                ->first();

            if ($invitation) {
                if ($invitation->isExpired() || $invitation->status === LandlordInvitation::STATUS_FAILED) {
                    $invitation = LandlordInvitation::create([
                        'property_id' => $property->id,
                        'landlord_id' => $landlord->id,
                        'invited_by' => auth()->id(),
                        'channels' => $request->channels ?? ['email'],
                        'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                        'custom_message' => $request->custom_message,
                        'expires_at' => now()->addDays($request->expires_in_days ?? 7)
                    ]);
                } else {
                    $updateData = [];
                    
                    if ($request->has('channels')) {
                        $updateData['channels'] = $request->channels;
                    }
                    
                    if ($request->has('custom_message')) {
                        $updateData['custom_message'] = $request->custom_message;
                    }
                    
                    if ($request->has('expires_in_days')) {
                        $updateData['expires_at'] = now()->addDays($request->expires_in_days);
                    }
                    
                    if (!empty($updateData)) {
                        $invitation->update($updateData);
                    }
                    
                    $invitation->resend($request->channels ?? []);
                }
            } else {
                $invitation = LandlordInvitation::create([
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'invited_by' => auth()->id(),
                    'channels' => $request->channels ?? ['email'],
                    'invitation_type' => LandlordInvitation::TYPE_REGISTRATION,
                    'custom_message' => $request->custom_message,
                    'expires_at' => now()->addDays($request->expires_in_days ?? 7)
                ]);
            }

            $invitationRequest = new Request([
                'invitation_channels' => $request->channels ?? ['email'],
                'custom_message' => $request->custom_message,
                'expires_in_days' => $request->expires_in_days ?? 7
            ]);

            $result = $this->sendLandlordInvitation($landlord, $property, $invitationRequest);

            DB::commit();

            $responseData = [
                'success' => $result['success'],
                'message' => $result['message'],
                'invitation_id' => $invitation->id,
                'token' => $invitation->token,
                'invitation_url' => $invitation->getInvitationUrl(),
                'expires_at' => $invitation->expires_at->format('Y-m-d H:i:s'),
            ];

            if ($request->expectsJson()) {
                return response()->json($responseData);
            }

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', 'Landlord invitation resent successfully via ' . 
                        implode(', ', $result['channels_successful'] ?? []) . '.')
                    ->with('invitation_url', $result['invitation_url'] ?? null);
            } else {
                return redirect()->back()
                    ->with('error', 'Failed to resend landlord invitation: ' . $result['message'])
                    ->with('invitation_result', $result);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Exception in resendLandlordInvitation: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = 'Failed to resend landlord invitation. Please try again.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'error' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', $errorMessage);
        }
    }

    /**
     * Get available invitation channels for a landlord
     * 
     * @param User $landlord
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function getLandlordChannels(User $landlord, Request $request)
    {
        $availableChannels = $this->multiChannelInvitationService->getAvailableChannels($landlord);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'available_channels' => $availableChannels,
                'landlord' => [
                    'id' => $landlord->id,
                    'name' => $landlord->name,
                    'phone' => $landlord->phone,
                    'email' => $landlord->email,
                    'has_phone' => !empty($landlord->phone),
                    'has_email' => !empty($landlord->email) && filter_var($landlord->email, FILTER_VALIDATE_EMAIL),
                ]
            ]);
        }

        return $availableChannels;
    }
}