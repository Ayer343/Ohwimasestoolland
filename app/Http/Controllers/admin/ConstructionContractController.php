<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConstructionContract;
use App\Models\ConstructionMilestone;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use App\Services\MultiChannelInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Notifications\ConstructionContractApproved;
use App\Notifications\ConstructionContractRejected;
use App\Notifications\ConstructionContractStatusChanged;
use App\Notifications\ContractorInvitationSent;
use Carbon\Carbon;

class ConstructionContractController extends Controller
{
    protected UserInvitationService $invitationService;
    protected MultiChannelInvitationService $multiChannelService;

    public function __construct(
        UserInvitationService $invitationService,
        MultiChannelInvitationService $multiChannelService
    ) {
        $this->invitationService = $invitationService;
        $this->multiChannelService = $multiChannelService;
    }

    /**
     * Display a listing of all construction contracts
     */
    public function index(Request $request)
    {
        $query = ConstructionContract::with(['landlord', 'property']);

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('contract_number', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%")
                  ->orWhere('contractor_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('landlord', function($landlordQuery) use ($search) {
                      $landlordQuery->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('property', function($propertyQuery) use ($search) {
                      $propertyQuery->where('property_name', 'LIKE', "%{$search}%")
                                    ->orWhere('digital_address', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('contractor_type') && $request->contractor_type) {
            $query->where('contractor_type', $request->contractor_type);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $allowedSorts = ['created_at', 'contract_number', 'title', 'contract_amount', 'status'];
        
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $contracts = $query->paginate(20)->withQueryString();

        // Statistics for dashboard
        $stats = [
            'total' => ConstructionContract::count(),
            'pending_approval' => ConstructionContract::where('status', ConstructionContract::STATUS_PENDING_APPROVAL)->count(),
            'approved' => ConstructionContract::where('status', ConstructionContract::STATUS_APPROVED)->count(),
            'in_progress' => ConstructionContract::where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
            'completed' => ConstructionContract::where('status', ConstructionContract::STATUS_COMPLETED)->count(),
            'cancelled' => ConstructionContract::where('status', ConstructionContract::STATUS_CANCELLED)->count(),
            'on_hold' => ConstructionContract::where('status', ConstructionContract::STATUS_ON_HOLD)->count(),
            'under_review' => ConstructionContract::where('status', ConstructionContract::STATUS_UNDER_REVIEW)->count(),
            'total_amount' => ConstructionContract::sum('contract_amount'),
            'overdue' => ConstructionContract::where('status', '!=', ConstructionContract::STATUS_COMPLETED)
                ->where('status', '!=', ConstructionContract::STATUS_CANCELLED)
                ->where('estimated_completion_date', '<', now())
                ->count(),
        ];

        // Get unique statuses for filter dropdown
        $statuses = ConstructionContract::select('status')
            ->distinct()
            ->pluck('status')
            ->toArray();

        return view('admin.construction.contracts.index', compact('contracts', 'stats', 'statuses'));
    }

    /**
     * Display a specific construction contract with all details
     */
    public function show(ConstructionContract $contract)
    {
        $contract->load([
            'landlord',
            'property',
            'approvedBy',
            'milestones',
            'activityLogs' => function($query) {
                $query->with('user')->latest()->limit(50);
            },
            'notifications' => function($query) {
                $query->latest()->limit(20);
            },
            'contractorUser' // Load contractor user relationship
        ]);

        // Calculate progress
        $progress = $contract->progress_percentage;

        // Get milestone statistics
        $milestoneStats = [
            'total' => $contract->milestones()->count(),
            'pending' => $contract->milestones()->where('status', 'pending')->count(),
            'in_progress' => $contract->milestones()->where('status', 'in_progress')->count(),
            'completed' => $contract->milestones()->where('status', 'completed')->count(),
            'delayed' => $contract->milestones()->where('status', 'delayed')->count(),
        ];

        // Get activity timeline
        $timeline = $contract->activityLogs->take(20);

        // Check if contractor invitation has been sent
        $invitationSent = $contract->contractor_invitation_sent_at !== null;
        $invitationAccepted = $contract->contractor_invitation_accepted_at !== null;

        return view('admin.construction.contracts.show', compact(
            'contract',
            'progress',
            'milestoneStats',
            'timeline',
            'invitationSent',
            'invitationAccepted'
        ));
    }

    /**
     * Approve a construction contract and send invitation to contractor
     */
    public function approve(Request $request, ConstructionContract $contract)
    {
        if (!$contract->canBeApproved()) {
            return redirect()->back()
                ->with('error', 'This contract cannot be approved in its current state.');
        }

        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:500',
            'send_invitation_to_contractor' => 'sometimes|boolean',
            'invitation_channels' => 'nullable|array|in:sms,email,whatsapp',
            'invitation_type' => 'nullable|in:welcome,registration,account_setup,password_setup',
            'contractor_email' => 'nullable|email|max:255',
            'contractor_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Update contract status
            $contract->status = ConstructionContract::STATUS_APPROVED;
            $contract->approved_by = auth()->id();
            $contract->approved_at = now();
            
            if ($request->has('admin_notes')) {
                $contract->admin_notes = $request->admin_notes;
            }

            // Update contractor contact info if provided
            if ($request->has('contractor_email') && $request->contractor_email) {
                $contract->contractor_email = $request->contractor_email;
            }
            if ($request->has('contractor_phone') && $request->contractor_phone) {
                $contract->contractor_phone = $request->contractor_phone;
            }

            $contract->save();

            // Log activity
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('approved', 'Contract approved by admin');
            }

            // Send invitation to contractor if requested
            $invitationResult = null;
            $contractorUser = null;

            if ($request->boolean('send_invitation_to_contractor', true)) {
                $invitationResult = $this->sendContractorInvitation($contract, $request);
                
                if ($invitationResult['success']) {
                    $contractorUser = $invitationResult['user'] ?? null;
                    
                    // Update contract with invitation tracking
                    $contract->contractor_invitation_sent_at = now();
                    $contract->contractor_invitation_channels = json_encode($request->input('invitation_channels', ['email']));
                    $contract->contractor_invitation_type = $request->input('invitation_type', 'welcome');
                    
                    if ($contractorUser) {
                        $contract->contractor_user_id = $contractorUser->id;
                    }
                    
                    $contract->save();

                    Log::info('Contractor invitation sent successfully', [
                        'contract_id' => $contract->id,
                        'contractor_email' => $contract->contractor_email,
                        'channels' => $request->input('invitation_channels', ['email']),
                        'contractor_user_id' => $contractorUser?->id
                    ]);
                } else {
                    // Log warning but don't fail the approval
                    Log::warning('Contractor invitation failed but contract was approved', [
                        'contract_id' => $contract->id,
                        'error' => $invitationResult['message'] ?? 'Unknown error'
                    ]);
                }
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'approved', [
                'admin_notes' => $request->admin_notes,
                'invitation_sent' => $request->boolean('send_invitation_to_contractor', true) && ($invitationResult['success'] ?? false)
            ]);

            DB::commit();

            $successMessage = 'Contract approved successfully!';
            if ($invitationResult && $invitationResult['success']) {
                $channels = implode(', ', $invitationResult['channels_successful'] ?? ['email']);
                $successMessage .= " Invitation sent to contractor via {$channels}.";
            } elseif ($request->boolean('send_invitation_to_contractor', true)) {
                $successMessage .= " Note: Could not send contractor invitation. Please manually send it.";
            }

            return redirect()->route('admin.construction.contracts.show', $contract)
                ->with('success', $successMessage)
                ->with('invitation_result', $invitationResult);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to approve contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'admin_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to approve contract: ' . $e->getMessage());
        }
    }

    /**
     * Send invitation to contractor
     */
    private function sendContractorInvitation(ConstructionContract $contract, Request $request): array
    {
        try {
            // Determine contact info - use provided or fallback to contract data
            $email = $request->input('contractor_email') ?? $contract->contractor_email;
            $phone = $request->input('contractor_phone') ?? $contract->contractor_phone;
            $name = $contract->contractor_name;

            // Validate we have at least one contact method
            if (empty($email) && empty($phone)) {
                return [
                    'success' => false,
                    'message' => 'No email or phone provided for contractor invitation.'
                ];
            }

            // Check if contractor user already exists
            $contractorUser = null;
            
            if ($email) {
                $contractorUser = User::where('email', $email)->first();
            }
            
            if (!$contractorUser && $phone) {
                $contractorUser = User::where('phone', $phone)->first();
            }

            // Create contractor user if doesn't exist
            if (!$contractorUser) {
                $contractorUser = $this->createContractorUser($contract, $email, $phone, $name);
            }

            if (!$contractorUser) {
                return [
                    'success' => false,
                    'message' => 'Failed to create or find contractor user.'
                ];
            }

            // Determine invitation channels
            $channels = $request->input('invitation_channels', ['email']);
            
            // Validate channels based on available contact info
            $availableChannels = [];
            if ($contractorUser->email) {
                $availableChannels[] = 'email';
            }
            if ($contractorUser->phone) {
                $availableChannels[] = 'sms';
                $availableChannels[] = 'whatsapp';
            }

            // Filter channels to only available ones
            $channels = array_intersect($channels, $availableChannels);
            if (empty($channels)) {
                // Fallback to whatever is available
                $channels = $availableChannels;
            }

            if (empty($channels)) {
                return [
                    'success' => false,
                    'message' => 'No valid contact channels available for contractor.'
                ];
            }

            // Prepare invitation data
            $invitationData = [
                'invitation_channels' => $channels,
                'invitation_type' => $request->input('invitation_type', 'welcome'),
                'custom_message' => $this->buildContractorInvitationMessage($contract),
                'expires_in_days' => config('invitation.default_expiry_days', 7),
                'template' => 'contractor_welcome',
                'metadata' => [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'invited_by' => auth()->id(),
                    'invited_by_name' => auth()->user()->name,
                    'role' => 'contractor'
                ]
            ];

            // Send invitation
            $result = $this->invitationService->sendInvitation($contractorUser, $invitationData);

            // Log the invitation
            if ($result['success']) {
                // Create an invitation record linked to the contract
                $invitation = UserInvitation::create([
                    'user_id' => $contractorUser->id,
                    'contract_id' => $contract->id,
                    'invited_by' => auth()->id(),
                    'token' => Str::random(64),
                    'channels' => json_encode($channels),
                    'invitation_type' => 'contractor',
                    'expires_at' => now()->addDays($invitationData['expires_in_days']),
                    'sent_at' => now(),
                    'status' => 'sent',
                    'metadata' => json_encode([
                        'contract_id' => $contract->id,
                        'contract_number' => $contract->contract_number,
                        'role' => 'contractor'
                    ])
                ]);

                // Send additional notification to admin about successful invitation
                $this->notifyAdminAboutInvitation($contract, $contractorUser, $channels);

                return [
                    'success' => true,
                    'user' => $contractorUser,
                    'invitation' => $invitation,
                    'channels_successful' => $result['channels_successful'] ?? $channels,
                    'failed_channels' => $result['failed_channels'] ?? [],
                    'message' => 'Invitation sent successfully'
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to send invitation'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send contractor invitation: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new contractor user
     */
    private function createContractorUser(ConstructionContract $contract, ?string $email, ?string $phone, string $name): ?User
    {
        try {
            // Check if user exists with this email or phone
            if ($email) {
                $existing = User::where('email', $email)->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($phone) {
                $existing = User::where('phone', $phone)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // Generate username
            $username = $this->generateUniqueUsername($name);

            // Create the user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'username' => $username,
                'type' => User::TYPE_CONTRACTOR, // Make sure this constant exists
                'status' => User::STATUS_PENDING,
                'can_login' => false, // Will be enabled after setting password
                'api_access' => false,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'metadata' => [
                    'created_via' => 'contract_approval',
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'created_by' => auth()->id(),
                    'created_by_name' => auth()->user()->name,
                    'source' => 'construction_contract',
                    'account_setup_required' => true
                ]
            ]);

            // Assign contractor role if it exists
            $contractorRole = \App\Models\Role::where('slug', 'contractor')->first();
            if ($contractorRole) {
                $user->roles()->attach($contractorRole, [
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'is_active' => true,
                    'status' => 'active'
                ]);
            }

            Log::info('Contractor user created', [
                'user_id' => $user->id,
                'contract_id' => $contract->id,
                'email' => $email,
                'phone' => $phone
            ]);

            return $user;

        } catch (\Exception $e) {
            Log::error('Failed to create contractor user: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'email' => $email,
                'phone' => $phone
            ]);
            return null;
        }
    }

    /**
     * Generate a unique username for contractor
     */
    private function generateUniqueUsername(string $name): string
    {
        $base = 'contractor_' . Str::slug($name, '_');
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Build custom invitation message for contractor
     */
    private function buildContractorInvitationMessage(ConstructionContract $contract): string
    {
        $message = "Dear " . $contract->contractor_name . ",\n\n";
        $message .= "You have been invited to join the " . config('app.name', 'Property Management System') . " as a contractor for a construction project.\n\n";
        $message .= "Project Details:\n";
        $message .= "• Contract Number: " . $contract->contract_number . "\n";
        $message .= "• Title: " . $contract->title . "\n";
        $message .= "• Property: " . ($contract->property->property_name ?? 'N/A') . "\n";
        $message .= "• Contract Amount: " . number_format($contract->contract_amount, 2) . "\n";
        $message .= "• Landlord: " . ($contract->landlord->name ?? 'N/A') . "\n\n";
        $message .= "Please click the link below to set up your password and access the system:\n\n";
        $message .= "Once you set up your password, you will be able to:\n";
        $message .= "• View contract details\n";
        $message .= "• Update work progress\n";
        $message .= "• Submit milestone updates\n";
        $message .= "• Communicate with the landlord\n\n";
        $message .= "This invitation will expire in 7 days.\n\n";
        $message .= "Thank you for working with us!\n\n";
        $message .= "Best regards,\n";
        $message .= config('app.name', 'Property Management System') . " Team";

        return $message;
    }

    /**
     * Resend invitation to contractor
     */
    public function resendContractorInvitation(Request $request, ConstructionContract $contract)
    {
        $validator = Validator::make($request->all(), [
            'channels' => 'nullable|array|in:sms,email,whatsapp',
            'contractor_email' => 'nullable|email|max:255',
            'contractor_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Update contractor contact info if provided
            if ($request->has('contractor_email') && $request->contractor_email) {
                $contract->contractor_email = $request->contractor_email;
            }
            if ($request->has('contractor_phone') && $request->contractor_phone) {
                $contract->contractor_phone = $request->contractor_phone;
            }
            $contract->save();

            // Get or create contractor ?user
            $contractorUser = null;
            if ($contract->contractor_user_id) {
                $contractorUser = User::find($contract->contractor_user_id);
            }

            if (!$contractorUser && $contract->contractor_email) {
                $contractorUser = User::where('email', $contract->contractor_email)->first();
            }

            if (!$contractorUser && $contract->contractor_phone) {
                $contractorUser = User::where('phone', $contract->contractor_phone)->first();
            }

            if (!$contractorUser) {
                // Create new contractor user
                $contractorUser = $this->createContractorUser(
                    $contract,
                    $contract->contractor_email,
                    $contract->contractor_phone,
                    $contract->contractor_name
                );
            }

            if (!$contractorUser) {
                $errorMessage = 'Could not find or create contractor user. Please provide valid contact information.';
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage
                    ], 422);
                }
                return redirect()->back()->with('error', $errorMessage);
            }

            // Determine channels
            $channels = $request->input('channels', ['email']);
            $availableChannels = [];
            if ($contractorUser->email) {
                $availableChannels[] = 'email';
            }
            if ($contractorUser->phone) {
                $availableChannels[] = 'sms';
                $availableChannels[] = 'whatsapp';
            }
            $channels = array_intersect($channels, $availableChannels);
            if (empty($channels)) {
                $channels = $availableChannels;
            }

            // Resend invitation
            $result = $this->invitationService->resendInvitation($contractorUser, [
                'channels' => $channels,
                'invitation_type' => 'contractor',
                'custom_message' => $this->buildContractorInvitationMessage($contract),
                'expires_in_days' => config('invitation.default_expiry_days', 7)
            ]);

            if ($result['success']) {
                $contract->contractor_invitation_sent_at = now();
                $contract->contractor_invitation_channels = json_encode($channels);
                $contract->save();

                Log::info('Contractor invitation resent', [
                    'contract_id' => $contract->id,
                    'contractor_user_id' => $contractorUser->id,
                    'channels' => $channels
                ]);

                $successMessage = 'Invitation resent successfully via ' . implode(', ', $channels);
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $successMessage,
                        'channels_successful' => $result['channels_successful'] ?? $channels,
                        'failed_channels' => $result['failed_channels'] ?? []
                    ]);
                }
                
                return redirect()->back()->with('success', $successMessage);
            }

            $errorMessage = $result['message'] ?? 'Failed to resend invitation';
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            }
            
            return redirect()->back()->with('error', $errorMessage);

        } catch (\Exception $e) {
            Log::error('Failed to resend contractor invitation: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'error_trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resend invitation: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to resend invitation: ' . $e->getMessage());
        }
    }

    /**
     * Get contractor invitation status
     */
    public function getInvitationStatus(ConstructionContract $contract)
    {
        try {
            $status = [
                'contract_id' => $contract->id,
                'contractor_email' => $contract->contractor_email,
                'contractor_phone' => $contract->contractor_phone,
                'invitation_sent' => $contract->contractor_invitation_sent_at !== null,
                'invitation_sent_at' => $contract->contractor_invitation_sent_at?->toISOString(),
                'invitation_accepted' => $contract->contractor_invitation_accepted_at !== null,
                'invitation_accepted_at' => $contract->contractor_invitation_accepted_at?->toISOString(),
                'channels' => json_decode($contract->contractor_invitation_channels ?? '[]', true),
                'has_contractor_user' => $contract->contractor_user_id !== null,
            ];

            if ($contract->contractor_user_id) {
                $contractorUser = User::find($contract->contractor_user_id);
                if ($contractorUser) {
                    $status['contractor_user'] = [
                        'id' => $contractorUser->id,
                        'name' => $contractorUser->name,
                        'email' => $contractorUser->email,
                        'phone' => $contractorUser->phone,
                        'status' => $contractorUser->status,
                        'has_password' => !empty($contractorUser->password),
                        'last_login' => $contractorUser->last_login_at?->toISOString(),
                    ];
                }
            }

            // Get latest invitation record
            $latestInvitation = UserInvitation::where('contract_id', $contract->id)
                ->where('invitation_type', 'contractor')
                ->latest()
                ->first();

            if ($latestInvitation) {
                $status['latest_invitation'] = [
                    'id' => $latestInvitation->id,
                    'status' => $latestInvitation->status,
                    'sent_at' => $latestInvitation->sent_at?->toISOString(),
                    'expires_at' => $latestInvitation->expires_at?->toISOString(),
                    'accepted_at' => $latestInvitation->accepted_at?->toISOString(),
                ];
            }

            return response()->json([
                'success' => true,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invitation status: ' . $e->getMessage(), [
                'contract_id' => $contract->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get invitation status'
            ], 500);
        }
    }

   /**
 * Notify admin about successful contractor invitation
 */
private function notifyAdminAboutInvitation(ConstructionContract $contract, User $contractorUser, array $channels): void
{
    try {
        // Check if the notification class exists
        if (!class_exists('App\Notifications\ContractorInvitationSent')) {
            Log::warning('ContractorInvitationSent notification class not found', [
                'contract_id' => $contract->id,
                'contractor_user_id' => $contractorUser->id
            ]);
            return;
        }

        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        if ($admins->isEmpty()) {
            Log::info('No active admins found to notify about contractor invitation', [
                'contract_id' => $contract->id
            ]);
            return;
        }

        foreach ($admins as $admin) {
            try {
                $admin->notify(new ContractorInvitationSent($contract, $contractorUser, $channels));
            } catch (\Exception $e) {
                Log::warning('Failed to notify individual admin about contractor invitation', [
                    'admin_id' => $admin->id,
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Admins notified about contractor invitation', [
            'contract_id' => $contract->id,
            'contractor_user_id' => $contractorUser->id,
            'admin_count' => $admins->count()
        ]);

    } catch (\Exception $e) {
        Log::warning('Failed to notify admins about contractor invitation: ' . $e->getMessage(), [
            'contract_id' => $contract->id,
            'contractor_user_id' => $contractorUser->id,
            'error_trace' => $e->getTraceAsString()
        ]);
    }
}

    /**
     * Reject a construction contract
     */
    public function reject(Request $request, ConstructionContract $contract)
    {
        if (!$contract->canBeApproved()) {
            return redirect()->back()
                ->with('error', 'This contract cannot be rejected in its current state.');
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $contract->status = ConstructionContract::STATUS_CANCELLED;
            $contract->rejection_reason = $request->rejection_reason;
            $contract->admin_notes = $request->rejection_reason;
            $contract->approved_by = auth()->id();
            $contract->approved_at = now();

            $contract->save();

            // Log activity
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('rejected', "Contract rejected. Reason: {$request->rejection_reason}");
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'rejected', [
                'reason' => $request->rejection_reason
            ]);

            DB::commit();

            return redirect()->route('admin.construction.contracts.show', $contract)
                ->with('warning', 'Contract rejected. The landlord has been notified of the reason.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to reject contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id,
                'admin_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to reject contract. Please try again.');
        }
    }

    /**
     * Start work on a contract
     */
    public function startWork(ConstructionContract $contract)
    {
        if (!$contract->canBeStarted()) {
            return redirect()->back()
                ->with('error', 'This contract cannot be started in its current state.');
        }

        DB::beginTransaction();

        try {
            $contract->status = ConstructionContract::STATUS_IN_PROGRESS;
            $contract->save();

            // Log activity
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('started', 'Work started on contract');
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'started');

            DB::commit();

            return redirect()->route('admin.construction.contracts.show', $contract)
                ->with('success', 'Work started on contract successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to start work on contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to start work. Please try again.');
        }
    }

    /**
     * Pause work on a contract
     */
    public function pauseWork(Request $request, ConstructionContract $contract)
    {
        if ($contract->status !== ConstructionContract::STATUS_IN_PROGRESS) {
            return redirect()->back()
                ->with('error', 'Only contracts in progress can be paused.');
        }

        $validator = Validator::make($request->all(), [
            'pause_reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $contract->status = ConstructionContract::STATUS_ON_HOLD;
            $contract->admin_notes = ($contract->admin_notes ? $contract->admin_notes . "\n" : '') 
                . "Paused: " . $request->pause_reason;
            $contract->save();

            // Log activity
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('paused', "Contract paused. Reason: {$request->pause_reason}");
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'paused', [
                'reason' => $request->pause_reason
            ]);

            DB::commit();

            return redirect()->route('admin.construction.contracts.show', $contract)
                ->with('warning', 'Contract paused successfully. Landlord has been notified.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to pause contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to pause contract. Please try again.');
        }
    }

    /**
     * Resume work on a paused contract
     */
    public function resumeWork(ConstructionContract $contract)
    {
        if ($contract->status !== ConstructionContract::STATUS_ON_HOLD) {
            return redirect()->back()
                ->with('error', 'Only paused contracts can be resumed.');
        }

        DB::beginTransaction();

        try {
            $contract->status = ConstructionContract::STATUS_IN_PROGRESS;
            $contract->admin_notes = ($contract->admin_notes ? $contract->admin_notes . "\n" : '') 
                . "Resumed: " . now()->toDateTimeString();
            $contract->save();

            // Log activity
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('resumed', 'Contract resumed');
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'resumed');

            DB::commit();

            return redirect()->route('admin.construction.contracts.show', $contract)
                ->with('success', 'Contract resumed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to resume contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to resume contract. Please try again.');
        }
    }

    /**
     * Complete a contract
     */
    public function complete(Request $request, ConstructionContract $contract)
    {
        if (!$contract->canBeCompleted()) {
            return redirect()->back()
                ->with('error', 'This contract cannot be completed in its current state.');
        }

        $validator = Validator::make($request->all(), [
            'completion_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $contract->status = ConstructionContract::STATUS_COMPLETED;
            if ($request->has('completion_notes')) {
                $contract->admin_notes = ($contract->admin_notes ? $contract->admin_notes . "\n" : '') 
                    . "Completed: " . $request->completion_notes;
            }
            $contract->save();

            // Log activity
            if (method_exists($contract, 'logActivity')) {
                $contract->logActivity('completed', 'Contract completed');
            }

            // Notify landlord
            $this->notifyLandlord($contract, 'completed', [
                'notes' => $request->completion_notes
            ]);

            DB::commit();

            return redirect()->route('admin.construction.contracts.show', $contract)
                ->with('success', 'Contract marked as completed!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to complete contract: ' . $e->getMessage(), [
                'contract_id' => $contract->id
            ]);

            return redirect()->back()
                ->with('error', 'Failed to complete contract. Please try again.');
        }
    }

    /**
 * Notify landlord about contract status changes
 */
private function notifyLandlord(ConstructionContract $contract, string $action, array $data = [])
{
    try {
        $landlord = $contract->landlord;
        
        if (!$landlord) {
            Log::warning('Landlord not found for contract notification', [
                'contract_id' => $contract->id
            ]);
            return;
        }

        // ✅ Check if notification classes exist
        $notification = null;
        
        switch ($action) {
            case 'approved':
                if (class_exists('App\Notifications\ConstructionContractApproved')) {
                    $notification = new ConstructionContractApproved($contract, $data['admin_notes'] ?? null);
                }
                break;
            case 'rejected':
                if (class_exists('App\Notifications\ConstructionContractRejected')) {
                    $notification = new ConstructionContractRejected($contract, $data['reason'] ?? 'No reason provided');
                }
                break;
            default:
                if (class_exists('App\Notifications\ConstructionContractStatusChanged')) {
                    $notification = new ConstructionContractStatusChanged($contract, $action, $data);
                }
                break;
        }

        if ($notification) {
            $landlord->notify($notification);
            Log::info('Landlord notified about contract action', [
                'contract_id' => $contract->id,
                'landlord_id' => $landlord->id,
                'action' => $action
            ]);
        } else {
            Log::warning('Notification class not found for action', [
                'contract_id' => $contract->id,
                'action' => $action
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Failed to notify landlord: ' . $e->getMessage(), [
            'contract_id' => $contract->id,
            'action' => $action,
            'error_trace' => $e->getTraceAsString()
        ]);
    }
}

    /**
     * Get contract status counts for AJAX dashboard widgets
     */
    public function getStatusCounts()
    {
        $counts = [
            'pending' => ConstructionContract::where('status', ConstructionContract::STATUS_PENDING_APPROVAL)->count(),
            'approved' => ConstructionContract::where('status', ConstructionContract::STATUS_APPROVED)->count(),
            'in_progress' => ConstructionContract::where('status', ConstructionContract::STATUS_IN_PROGRESS)->count(),
            'completed' => ConstructionContract::where('status', ConstructionContract::STATUS_COMPLETED)->count(),
            'cancelled' => ConstructionContract::where('status', ConstructionContract::STATUS_CANCELLED)->count(),
            'on_hold' => ConstructionContract::where('status', ConstructionContract::STATUS_ON_HOLD)->count(),
            'overdue' => ConstructionContract::where('status', '!=', ConstructionContract::STATUS_COMPLETED)
                ->where('status', '!=', ConstructionContract::STATUS_CANCELLED)
                ->where('estimated_completion_date', '<', now())
                ->count(),
        ];

        return response()->json($counts);
    }
}