<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandlordConstructionRegistration;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\RegistrationPlan;
use App\Models\RegistrationDocument;
use App\Models\ArchivedConstructionRegistration;
use App\Models\TenantInvitation;
use App\Notifications\ConstructionRegistrationStatusNotification;
use App\Notifications\RegistrationAssignedNotification;
use App\Notifications\RegistrationNoteAddedNotification;
use App\Notifications\TenantInvitationNotification;
use App\Http\Controllers\PropertyLandlordController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\LandlordConstructionRegistrationController;
use App\Services\PropertyRegistrationService;
use App\Services\TenantInvitationService;
use App\Services\LandlordInvitationService;
use App\Services\SmsService;
use App\Exports\ConstructionRegistrationsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminConstructionRegistrationController extends Controller
{
    protected $propertyLandlordController;
    protected $propertyRegistrationService;
    protected $propertyController;
    protected $landlordRegistrationController;
    protected $tenantInvitationService;
    protected $landlordInvitationService;
    protected $smsService;

    public function __construct(
        PropertyLandlordController $propertyLandlordController,
        PropertyRegistrationService $propertyRegistrationService,
        PropertyController $propertyController,
        LandlordConstructionRegistrationController $landlordRegistrationController,
        TenantInvitationService $tenantInvitationService,
        LandlordInvitationService $landlordInvitationService,
        SmsService $smsService
    ) {
        $this->propertyLandlordController = $propertyLandlordController;
        $this->propertyRegistrationService = $propertyRegistrationService;
        $this->propertyController = $propertyController;
        $this->landlordRegistrationController = $landlordRegistrationController;
        $this->tenantInvitationService = $tenantInvitationService;
        $this->landlordInvitationService = $landlordInvitationService;
        $this->smsService = $smsService;
    }

    /**
     * Display list of construction registrations
     */
    public function index(Request $request)
    {
        $query = LandlordConstructionRegistration::with(['landlord', 'reviewer', 'approvedProperty', 'assignedTo'])
            ->latest();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('property_name', 'like', "%{$search}%")
                  ->orWhere('plot_number', 'like', "%{$search}%")
                  ->orWhere('street_name', 'like', "%{$search}%")
                  ->orWhere('primary_phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('registration_type')) {
            $query->where('registration_type', $request->registration_type);
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->purpose);
        }

        if ($request->filled('has_tenants')) {
            $hasTenants = filter_var($request->has_tenants, FILTER_VALIDATE_BOOLEAN);
            $query->where('has_tenants', $hasTenants);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('zone')) {
            $query->where('zone', 'like', '%' . $request->zone . '%');
        }

        if ($request->filled('section')) {
            $query->where('section', 'like', '%' . $request->section . '%');
        }

        $registrations = $query->paginate(20)->withQueryString();

        // Get counts for dashboard
        $counts = [
            'total' => LandlordConstructionRegistration::count(),
            'construction' => LandlordConstructionRegistration::construction()->count(),
            'property_capture' => LandlordConstructionRegistration::propertyCapture()->count(),
            'both_purposes' => LandlordConstructionRegistration::bothPurposes()->count(),
            'pending' => LandlordConstructionRegistration::where('status', LandlordConstructionRegistration::STATUS_PENDING)->count(),
            'approved' => LandlordConstructionRegistration::where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count(),
            'rejected' => LandlordConstructionRegistration::where('status', LandlordConstructionRegistration::STATUS_REJECTED)->count(),
            'trashed' => LandlordConstructionRegistration::onlyTrashed()->count(),
            'overdue' => LandlordConstructionRegistration::where('status', LandlordConstructionRegistration::STATUS_PENDING)
                ->where('created_at', '<=', now()->subDays(7))
                ->count(),
        ];

        // Get unique zones and sections for filtering
        $zones = LandlordConstructionRegistration::distinct()->whereNotNull('zone')->pluck('zone');
        $sections = LandlordConstructionRegistration::distinct()->whereNotNull('section')->pluck('section');

        // Get admins for assignment filter
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        // Get registration plans for bulk approval
        $registrationPlans = RegistrationPlan::withCount('properties')
            ->whereIn('status', ['active', 'in_progress', 'assigned', 'draft', 'pending'])
            ->orWhereNull('status')
            ->orderBy('zone')
            ->orderBy('section')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function($plan) {
                $plan->registered_count = $plan->properties_count ?? 0;
                $plan->available_spots = $plan->estimated_houses - $plan->registered_count;
                $plan->is_available = $plan->available_spots > 0;
                $plan->occupancy_rate = $plan->estimated_houses > 0 
                    ? round(($plan->registered_count / $plan->estimated_houses) * 100, 1) 
                    : 0;
                $plan->display_name = $this->formatPlanDisplayName($plan);
                return $plan;
            });

        // Get property types for bulk approval
        $propertyTypes = PropertyType::active()
            ->orderBy('name')
            ->get();

        // Get all landlords (active only) for bulk approval
        $landlords = User::where('type', User::TYPE_LANDLORD)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'registrations' => $registrations,
                'counts' => $counts,
                'registration_plans' => $registrationPlans,
                'property_types' => $propertyTypes,
                'landlords' => $landlords,
                'admins' => $admins
            ]);
        }

        return view('admin.construction-registrations.index', compact(
            'registrations', 
            'counts', 
            'admins',
            'zones',
            'sections',
            'registrationPlans',
            'propertyTypes',
            'landlords'
        ));
    }

    /**
     * Show single registration details
     */
    public function show(LandlordConstructionRegistration $registration)
    {
        // Load all necessary relationships with proper ordering
        $registration->load([
            'landlord', 
            'reviewer', 
            'approvedProperty.registrationPlan', 
            'approvedProperty.propertyType',
            'assignedTo', 
            'documents' => function($query) {
                $query->latest();
            }, 
            'notes' => function($query) {
                $query->latest();
            },
            'activityLog' => function($query) {
                $query->latest()->limit(50);
            },
            'tenants' => function($query) {
                $query->with([
                    'approver', 
                    'user',
                    'invitations' => function($q) {
                        $q->latest();
                    }
                ])->latest();
            }
        ]);
        
        // Calculate tenant statistics
        $tenantStats = [
            'total' => $registration->tenants->count(),
            'approved' => $registration->tenants->where('status', 'approved')->count(),
            'pending' => $registration->tenants->where('status', 'pending')->count(),
            'rejected' => $registration->tenants->where('status', 'rejected')->count(),
            'with_accounts' => $registration->tenants->whereNotNull('user_id')->count(),
            'invitations_sent' => $registration->tenants->sum(function($tenant) {
                if ($tenant->relationLoaded('invitations') && $tenant->invitations) {
                    return $tenant->invitations->where('status', TenantInvitation::STATUS_SENT)->count();
                }
                return 0;
            }),
            'invitations_accepted' => $registration->tenants->sum(function($tenant) {
                if ($tenant->relationLoaded('invitations') && $tenant->invitations) {
                    return $tenant->invitations->where('status', TenantInvitation::STATUS_COMPLETED)->count();
                }
                return 0;
            }),
            'invitations_pending' => $registration->tenants->sum(function($tenant) {
                if ($tenant->relationLoaded('invitations') && $tenant->invitations) {
                    return $tenant->invitations->whereIn('status', [
                        TenantInvitation::STATUS_PENDING,
                        TenantInvitation::STATUS_SENT
                    ])->count();
                }
                return 0;
            }),
            'invitations_failed' => $registration->tenants->sum(function($tenant) {
                if ($tenant->relationLoaded('invitations') && $tenant->invitations) {
                    return $tenant->invitations->where('status', TenantInvitation::STATUS_FAILED)->count();
                }
                return 0;
            }),
            'invitations_expired' => $registration->tenants->sum(function($tenant) {
                if ($tenant->relationLoaded('invitations') && $tenant->invitations) {
                    return $tenant->invitations->filter(function($invitation) {
                        return $invitation->is_expired;
                    })->count();
                }
                return 0;
            }),
            'invitations_cancelled' => $registration->tenants->sum(function($tenant) {
                if ($tenant->relationLoaded('invitations') && $tenant->invitations) {
                    return $tenant->invitations->where('status', TenantInvitation::STATUS_CANCELLED)->count();
                }
                return 0;
            }),
        ];
        
        // Add detailed invitation information for each tenant
        $tenantInvitationDetails = $registration->tenants->map(function($tenant) {
            $latestInvitation = null;
            $invitationStats = [
                'total' => 0,
                'sent' => 0,
                'pending' => 0,
                'completed' => 0,
                'failed' => 0,
                'expired' => 0,
                'cancelled' => 0,
            ];
            
            if ($tenant->relationLoaded('invitations') && $tenant->invitations && $tenant->invitations->isNotEmpty()) {
                $latestInvitation = $tenant->invitations->first();
                
                $invitationStats = [
                    'total' => $tenant->invitations->count(),
                    'sent' => $tenant->invitations->where('status', TenantInvitation::STATUS_SENT)->count(),
                    'pending' => $tenant->invitations->whereIn('status', [
                        TenantInvitation::STATUS_PENDING,
                        TenantInvitation::STATUS_SENT
                    ])->count(),
                    'completed' => $tenant->invitations->where('status', TenantInvitation::STATUS_COMPLETED)->count(),
                    'failed' => $tenant->invitations->where('status', TenantInvitation::STATUS_FAILED)->count(),
                    'expired' => $tenant->invitations->filter(function($inv) {
                        return $inv->is_expired;
                    })->count(),
                    'cancelled' => $tenant->invitations->where('status', TenantInvitation::STATUS_CANCELLED)->count(),
                ];
            }
            
            return [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_status' => $tenant->status,
                'has_invitation' => $latestInvitation ? true : false,
                'invitation_stats' => $invitationStats,
                'invitation' => $latestInvitation ? [
                    'id' => $latestInvitation->id,
                    'status' => $latestInvitation->status,
                    'status_label' => ucfirst($latestInvitation->status),
                    'status_badge_class' => $this->getInvitationStatusBadgeClass($latestInvitation->status),
                    'sent_at' => $latestInvitation->sent_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $latestInvitation->expires_at?->format('Y-m-d H:i:s'),
                    'accepted_at' => $latestInvitation->completed_at?->format('Y-m-d H:i:s'),
                    'invitation_url' => $latestInvitation->invitation_url,
                    'is_expired' => $latestInvitation->is_expired,
                    'is_active' => $latestInvitation->is_active,
                    'channels' => $latestInvitation->channels,
                    'formatted_channels' => $latestInvitation->formatted_channels,
                    'sent_channels' => $latestInvitation->sent_channels,
                    'formatted_sent_channels' => $latestInvitation->formatted_sent_channels ?? 'None',
                    'failure_reason' => $latestInvitation->failure_reason,
                ] : null
            ];
        });
        
        // Get active property types
        $propertyTypes = PropertyType::active()
            ->orderBy('name')
            ->get();
        
        // Get registration plans with additional data for the dropdown
        $registrationPlans = RegistrationPlan::withCount('properties')
            ->whereIn('status', ['active', 'in_progress', 'assigned', 'draft', 'pending'])
            ->orWhereNull('status')
            ->orderBy('zone')
            ->orderBy('section')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function($plan) {
                $plan->registered_count = $plan->properties_count ?? 0;
                $plan->available_spots = $plan->estimated_houses - $plan->registered_count;
                $plan->is_available = $plan->available_spots > 0;
                $plan->occupancy_rate = $plan->estimated_houses > 0 
                    ? round(($plan->registered_count / $plan->estimated_houses) * 100, 1) 
                    : 0;
                $plan->display_name = $this->formatPlanDisplayName($plan);
                return $plan;
            });
        
        // Get all landlords (active only) WITH their properties loaded
        $landlords = User::where('type', User::TYPE_LANDLORD)
            ->where('status', User::STATUS_ACTIVE)
            ->with(['properties' => function($query) {
                $query->select('id', 'property_name', 'landlord_id');
            }])
            ->orderBy('name')
            ->get();
        
        // Get available admins for assignment
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();
        
        // Get statistics for the dashboard
        $stats = [
            'total_documents' => $registration->documents->count() + 
                                ($registration->land_ownership_document ? 1 : 0) + 
                                (is_array($registration->construction_documents) ? count($registration->construction_documents) : 0) + 
                                (is_array($registration->property_documents) ? count($registration->property_documents) : 0),
            'total_notes' => $registration->notes->count(),
            'days_pending' => $registration->created_at->diffInDays(now()),
            'document_categories' => [
                'ownership' => $registration->land_ownership_document ? 1 : 0,
                'construction' => is_array($registration->construction_documents) ? count($registration->construction_documents) : 0,
                'property' => is_array($registration->property_documents) ? count($registration->property_documents) : 0,
                'additional' => $registration->documents->count(),
            ],
            'tenant_stats' => $tenantStats
        ];
        
        // Check if registration has legacy tenant data
        $hasLegacyTenantData = $registration->tenant_data && 
                              is_array($registration->tenant_data) && 
                              count($registration->tenant_data) > 0;
        
        // Check if registration needs tenant migration
        $needsTenantMigration = $hasLegacyTenantData && $registration->tenants->count() === 0;
        
        // Check if registration is ready for approval
        $canApprove = $this->canApproveRegistration($registration);
        
        // Get available channels for the landlord
        $availableChannels = $this->getFormattedChannels($registration);
        
        // Get applicant name for JavaScript matching
        $applicantName = $registration->name;
        
        // Get property type info from registration
        $selectedPropertyType = null;
        $isCustomProperty = false;
        $customPropertyValue = null;
        
        if ($registration->isConstruction() && $registration->property_type) {
            if ($registration->property_type === 'other' && $registration->custom_property_type) {
                $isCustomProperty = true;
                $customPropertyValue = $registration->custom_property_type;
                $selectedPropertyType = PropertyType::where('slug', 'custom')->first();
                
                if (!$selectedPropertyType) {
                    $selectedPropertyType = PropertyType::where('name', 'LIKE', '%custom%')->first();
                }
            } else {
                $propertyTypeMap = [
                    'residential' => 'Resident',
                    'apartment' => 'Apartment',
                    'commercial' => 'Commercial',
                    'mixed' => 'Mixed Use',
                ];
                
                $typeName = $propertyTypeMap[$registration->property_type] ?? ucfirst($registration->property_type);
                $selectedPropertyType = PropertyType::where('name', 'LIKE', "%{$typeName}%")->first();
                
                if (!$selectedPropertyType) {
                    $alternativeMappings = [
                        'residential' => ['Resident', 'Residential', 'Home', 'House', 'Villa', 'Bungalow'],
                        'apartment' => ['Apartment', 'Flat', 'Condo', 'Condominium'],
                        'commercial' => ['Commercial', 'Shop', 'Office', 'Retail', 'Store', 'Business'],
                        'mixed' => ['Mixed', 'Mixed Use', 'Commercial/Residential', 'Mixed-Use'],
                    ];
                    
                    $alternatives = $alternativeMappings[$registration->property_type] ?? [];
                    foreach ($alternatives as $alt) {
                        $selectedPropertyType = PropertyType::where('name', 'LIKE', "%{$alt}%")->first();
                        if ($selectedPropertyType) break;
                    }
                }
                
                if (!$selectedPropertyType && $registration->property_type === 'residential') {
                    $selectedPropertyType = PropertyType::first();
                }
            }
        } elseif ($registration->isPropertyCapture() && $registration->existing_property_type) {
            if ($registration->existing_property_type === 'other' && $registration->existing_custom_property_type) {
                $isCustomProperty = true;
                $customPropertyValue = $registration->existing_custom_property_type;
                $selectedPropertyType = PropertyType::where('slug', 'custom')->first();
                
                if (!$selectedPropertyType) {
                    $selectedPropertyType = PropertyType::where('name', 'LIKE', '%custom%')->first();
                }
            } else {
                $propertyTypeMap = [
                    'residential' => 'Resident',
                    'apartment' => 'Apartment',
                    'commercial' => 'Commercial',
                    'mixed' => 'Mixed Use',
                ];
                
                $typeName = $propertyTypeMap[$registration->existing_property_type] ?? ucfirst($registration->existing_property_type);
                $selectedPropertyType = PropertyType::where('name', 'LIKE', "%{$typeName}%")->first();
                
                if (!$selectedPropertyType) {
                    $alternativeMappings = [
                        'residential' => ['Resident', 'Residential', 'Home', 'House', 'Villa', 'Bungalow'],
                        'apartment' => ['Apartment', 'Flat', 'Condo', 'Condominium'],
                        'commercial' => ['Commercial', 'Shop', 'Office', 'Retail', 'Store', 'Business'],
                        'mixed' => ['Mixed', 'Mixed Use', 'Commercial/Residential', 'Mixed-Use'],
                    ];
                    
                    $alternatives = $alternativeMappings[$registration->existing_property_type] ?? [];
                    foreach ($alternatives as $alt) {
                        $selectedPropertyType = PropertyType::where('name', 'LIKE', "%{$alt}%")->first();
                        if ($selectedPropertyType) {
                            \Log::info('Found property type via alternative mapping', [
                                'alternative' => $alt,
                                'id' => $selectedPropertyType->id,
                                'name' => $selectedPropertyType->name
                            ]);
                            break;
                        }
                    }
                }
                
                if (!$selectedPropertyType) {
                    $words = explode(' ', $registration->formatted_existing_property_type ?? '');
                    foreach ($words as $word) {
                        if (strlen($word) > 2) {
                            $selectedPropertyType = PropertyType::where('name', $word)->first();
                            if ($selectedPropertyType) break;
                        }
                    }
                }
                
                if (!$selectedPropertyType && $registration->existing_property_type === 'residential') {
                    $selectedPropertyType = PropertyType::first();
                    \Log::info('Using fallback property type', [
                        'id' => $selectedPropertyType->id,
                        'name' => $selectedPropertyType->name
                    ]);
                }
            }
        }
        
        \Log::info('Property Type Info', [
            'selected_property_type_id' => $selectedPropertyType?->id,
            'selected_property_type_name' => $selectedPropertyType?->name,
            'is_custom' => $isCustomProperty,
            'custom_value' => $customPropertyValue
        ]);
        
        return view('admin.construction-registrations.show', compact(
            'registration', 
            'propertyTypes', 
            'registrationPlans',
            'landlords',
            'admins',
            'stats',
            'canApprove',
            'availableChannels',
            'applicantName',
            'selectedPropertyType',
            'isCustomProperty',
            'customPropertyValue',
            'tenantStats',
            'tenantInvitationDetails',
            'hasLegacyTenantData',
            'needsTenantMigration'
        ));
    }

    /**
     * Helper method to get invitation status badge class
     */
    private function getInvitationStatusBadgeClass($status): string
    {
        return match($status) {
            TenantInvitation::STATUS_SENT => 'badge-info',
            TenantInvitation::STATUS_PENDING => 'badge-warning',
            TenantInvitation::STATUS_COMPLETED => 'badge-success',
            TenantInvitation::STATUS_EXPIRED => 'badge-secondary',
            TenantInvitation::STATUS_CANCELLED => 'badge-danger',
            TenantInvitation::STATUS_FAILED => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Format plan display name for dropdown
     */
    private function formatPlanDisplayName($plan)
    {
        $parts = [];
        
        if (!empty($plan->name)) {
            $parts[] = $plan->name;
        } else {
            $parts[] = 'Plan #' . $plan->id;
        }
        
        if (!empty($plan->zone)) {
            $parts[] = 'Zone: ' . $plan->zone;
        }
        
        if (!empty($plan->section)) {
            $parts[] = 'Section: ' . $plan->section;
        }
        
        if (!empty($plan->estimated_houses)) {
            $parts[] = 'Capacity: ' . $plan->estimated_houses;
        }
        
        if (isset($plan->available_spots)) {
            $availability = $plan->available_spots > 0 
                ? $plan->available_spots . ' spots available' 
                : 'FULL';
            $parts[] = $availability;
        }
        
        if (!empty($plan->status)) {
            $parts[] = '(' . ucfirst($plan->status) . ')';
        }
        
        return implode(' - ', $parts);
    }

    /**
 * Update registration status (review, approve, reject)
 * ✅ FIXED: Properly handles vacant land with construction details
 * ✅ FIXED: Ensures property status is set correctly on approval
 */
public function updateStatus(Request $request, LandlordConstructionRegistration $registration)
{
    // ========== DEBUGGING START ==========
    \Log::info('========== UPDATE STATUS METHOD CALLED ==========');
    \Log::info('Request Details:', [
        'method' => $request->method(),
        'url' => $request->fullUrl(),
        'registration_id' => $registration->id,
        'registration_status' => $registration->status,
        'user_id' => auth()->id(),
        'user_type' => auth()->user()?->type,
        'timestamp' => now()->toDateTimeString()
    ]);
    
    \Log::info('Request Input (all):', $request->all());
    \Log::info('Request Input (status):', ['status' => $request->input('status')]);
    \Log::info('Request Input (rejection specific):', [
        'rejection_reason' => $request->input('rejection_reason'),
        'notify_landlord' => $request->input('notify_landlord'),
        'notification_channels' => $request->input('notification_channels'),
        'custom_message' => $request->input('custom_message'),
    ]);
    // ========== DEBUGGING END ==========

    // Build rules based on status
    $rules = [
        'status' => 'required|in:pending,in_review,approved,rejected,needs_info',
        'admin_notes' => 'nullable|string|max:1000',
        'notify_landlord' => 'sometimes|boolean',
        'internal_notes' => 'nullable|string|max:1000',
    ];

    // Add rules based on status
    if ($request->status === LandlordConstructionRegistration::STATUS_APPROVED) {
        $rules = array_merge($rules, [
            'registration_plan_id' => 'required|exists:registration_plans,id',
            'zone' => 'required|string|max:100',
            'section' => 'nullable|string|max:100',
            'landlord_id' => 'required|exists:users,id',
            'send_invitation' => 'sometimes|boolean',
            'invitation_channels' => 'required_if:send_invitation,1|array',
            'invitation_channels.*' => 'in:sms,email,whatsapp',
            'custom_message' => 'nullable|string|max:500',
            'send_tenant_invitations' => 'sometimes|boolean',
            'tenant_invitation_channels' => 'required_if:send_tenant_invitations,1|array',
            'tenant_invitation_channels.*' => 'in:sms,email',
            'auto_approve_tenants' => 'sometimes|boolean',
        ]);

        // ============================================================
        // ✅ FIXED: Determine if this is vacant land with or without construction details
        // ============================================================
        $hasConstructionDetails = $registration->property_type || 
                                  $registration->construction_documents ||
                                  $registration->has_construction_details ||
                                  $registration->purpose === LandlordConstructionRegistration::PURPOSE_BOTH;
        
        $isVacantLandOnly = $registration->isConstruction() && 
                           !$hasConstructionDetails;

        // ============================================================
        // ✅ FIXED: Only require property_type_id if construction details exist
        // ============================================================
        if ($isVacantLandOnly) {
            // Vacant land with NO construction details - property type is NOT required
            $rules['property_type_id'] = 'nullable|exists:property_types,id';
            $rules['custom_property_type'] = 'nullable|string|max:100';
            
            \Log::info('Vacant land WITHOUT construction details - property type not required', [
                'registration_id' => $registration->id,
                'registration_name' => $registration->name,
                'property_name' => $registration->property_name
            ]);
            
        } elseif ($registration->isPropertyCapture() || 
                  ($registration->isConstruction() && $hasConstructionDetails)) {
            // Property capture OR construction with details - property type IS required
            $rules['property_type_id'] = 'required|exists:property_types,id';
            $rules['custom_property_type'] = [
                'nullable',
                'required_if:property_type_id,' . $this->getCustomTypeId(),
                'string',
                'max:100'
            ];
            
            \Log::info('Property with construction details - property type required', [
                'registration_id' => $registration->id,
                'registration_type' => $registration->registration_type,
                'has_property_type' => !empty($registration->property_type),
                'has_construction_documents' => !empty($registration->construction_documents),
                'has_construction_details' => $hasConstructionDetails
            ]);
            
        } else {
            // Fallback - property type is optional
            $rules['property_type_id'] = 'nullable|exists:property_types,id';
            $rules['custom_property_type'] = 'nullable|string|max:100';
            
            \Log::info('Property type set to optional (fallback)', [
                'registration_id' => $registration->id,
                'registration_type' => $registration->registration_type,
                'registration_purpose' => $registration->purpose
            ]);
        }

    } elseif ($request->status === LandlordConstructionRegistration::STATUS_REJECTED) {
        $rules['rejection_reason'] = 'required|string|max:500';
        $rules['notification_channels'] = 'sometimes|array';
        $rules['notification_channels.*'] = 'in:email,sms';
        $rules['custom_message'] = 'nullable|string|max:500';
    } elseif ($request->status === LandlordConstructionRegistration::STATUS_NEEDS_INFO) {
        $rules['info_requested'] = 'required|string|max:500';
    }

    $messages = [
        'registration_plan_id.required' => 'Please select a registration plan for the property.',
        'property_type_id.required' => 'Please select a property type.',
        'zone.required' => 'Zone is required when approving a registration.',
        'landlord_id.required' => 'Please select a landlord to assign the property to.',
        'invitation_channels.required_if' => 'Please select at least one invitation channel.',
        'tenant_invitation_channels.required_if' => 'Please select at least one channel for tenant invitations.',
        'rejection_reason.required' => 'Please provide a reason for rejection.',
        'info_requested.required' => 'Please specify what information is needed.',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
        \Log::warning('Validation failed:', [
            'errors' => $validator->errors()->toArray(),
            'input' => $request->all()
        ]);
        
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    DB::beginTransaction();
    \Log::info('Database transaction started');

    try {
        $oldStatus = $registration->status;
        \Log::info('Processing status update:', [
            'old_status' => $oldStatus,
            'new_status' => $request->status
        ]);
        
        // Update registration
        $registration->status = $request->status;
        $registration->admin_notes = $request->admin_notes;
        $registration->reviewed_at = now();
        $registration->reviewed_by = auth()->id();
        
        // Handle assignment if provided
        if ($request->filled('assigned_to')) {
            $registration->assigned_to = $request->assigned_to;
            $registration->assigned_at = now();
            \Log::info('Assigned to admin:', ['assigned_to' => $request->assigned_to]);
        }
        
        // Add internal notes if provided
        if ($request->filled('internal_notes')) {
            $registration->addInternalNote($request->internal_notes, auth()->id());
            \Log::info('Internal note added');
        }
        
        // Handle rejection reason
        if ($request->status === LandlordConstructionRegistration::STATUS_REJECTED) {
            $registration->rejection_reason = $request->rejection_reason;
            \Log::info('Rejection reason set:', ['reason' => $request->rejection_reason]);
        }
        
        // Handle info requested
        if ($request->status === LandlordConstructionRegistration::STATUS_NEEDS_INFO) {
            $registration->info_requested = $request->info_requested;
            \Log::info('Info requested set:', ['info' => $request->info_requested]);
        }
        
        // If approved, create the actual ?property
        $property = null;
        $autoApprovedTenants = [];
        if ($request->status === LandlordConstructionRegistration::STATUS_APPROVED) {
            \Log::info('Processing approval - checking plan availability');
            
            $plan = RegistrationPlan::withCount('properties')->find($request->registration_plan_id);
            \Log::info('Plan details:', [
                'plan_id' => $plan?->id,
                'plan_name' => $plan?->name,
                'estimated_houses' => $plan?->estimated_houses,
                'properties_count' => $plan?->properties_count,
                'available_spots' => $plan ? ($plan->estimated_houses - $plan->properties_count) : null
            ]);
            
            if ($plan && $plan->properties_count >= $plan->estimated_houses) {
                throw new \Exception('Selected registration plan has no available spots. Please choose another plan.');
            }
            
            \Log::info('Creating property from registration');
            $property = $this->createPropertyFromRegistration($request, $registration);
            \Log::info('Property created:', [
                'property_id' => $property->id,
                'property_name' => $property->property_name,
                'property_status' => $property->status,
                'construction_status' => $property->construction_status,
                'property_type_id' => $property->property_type_id,
            ]);
            
            // ============================================================
            // ✅ FIXED: Double-check and enforce correct status for construction
            // ============================================================
            $hasConstructionDetails = $registration->property_type || 
                                      $registration->construction_documents ||
                                      $registration->has_construction_details ||
                                      $registration->purpose === LandlordConstructionRegistration::PURPOSE_BOTH;
            
            if ($registration->isConstruction() && $hasConstructionDetails) {
                // Ensure property is marked as under construction
                if ($property->status !== Property::STATUS_UNDER_CONSTRUCTION) {
                    \Log::warning('Property status was not set correctly, correcting to under_construction...', [
                        'property_id' => $property->id,
                        'current_status' => $property->status,
                        'expected_status' => Property::STATUS_UNDER_CONSTRUCTION,
                    ]);
                    $property->status = Property::STATUS_UNDER_CONSTRUCTION;
                    $property->construction_status = $registration->property_status ?? 'under_construction';
                    $property->has_plans = $registration->has_plans ?? true;
                    $property->save();
                }
            } elseif ($registration->isConstruction() && !$hasConstructionDetails) {
                // Vacant land with no construction details
                if ($property->status !== Property::STATUS_VACANT) {
                    \Log::warning('Property status was not set correctly, correcting to vacant...', [
                        'property_id' => $property->id,
                        'current_status' => $property->status,
                        'expected_status' => Property::STATUS_VACANT,
                    ]);
                    $property->status = Property::STATUS_VACANT;
                    $property->construction_status = 'vacant';
                    $property->save();
                }
            }
            
            $registration->approved_property_id = $property->id;
            
            $registration->zone = $request->zone;
            $registration->section = $request->section;
            \Log::info('Zone/Section updated:', [
                'zone' => $request->zone,
                'section' => $request->section
            ]);
            
            if ($registration->has_tenants && $registration->tenants()->count() > 0) {
                $autoApprove = $request->boolean('auto_approve_tenants', true);
                
                if ($autoApprove) {
                    \Log::info('Auto-approving tenants for registration', [
                        'tenant_count' => $registration->tenants()->count()
                    ]);
                    
                    foreach ($registration->tenants as $tenant) {
                        if ($tenant->status === 'pending') {
                            $tenant->status = 'approved';
                            $tenant->approved_by = auth()->id();
                            $tenant->approved_at = now();
                            $tenant->save();
                            
                            $autoApprovedTenants[] = [
                                'id' => $tenant->id,
                                'name' => $tenant->name
                            ];
                            
                            \Log::info('Tenant auto-approved', [
                                'tenant_id' => $tenant->id,
                                'tenant_name' => $tenant->name
                            ]);
                        }
                    }
                    
                    $property->tenant_count = $registration->tenants()->count();
                    $property->active_tenant_count = $registration->tenants()->where('status', 'approved')->count();
                    $property->save();
                    
                    $registration->logActivity('tenants_auto_approved', 
                        count($autoApprovedTenants) . ' tenants were automatically approved during registration approval'
                    );
                }
            }
        }
        
        $registration->save();
        \Log::info('Registration saved successfully');

        DB::commit();
        \Log::info('Database transaction committed');

        // ========== SEND NOTIFICATIONS AFTER STATUS UPDATE ==========
        $notificationResult = null;
        
        // Send rejection notification if requested
        if ($request->status === LandlordConstructionRegistration::STATUS_REJECTED && $request->boolean('notify_landlord')) {
            \Log::info('Sending rejection notification to landlord (using registration contact info)', [
                'email' => $registration->email,
                'phone' => $registration->primary_phone,
                'channels' => $request->notification_channels ?? ['email']
            ]);
            
            $notificationResult = $this->sendRejectionNotification($registration, $request);
        }
        
        // Send approval notifications (these require a registered landlord account)
        if ($request->status === LandlordConstructionRegistration::STATUS_APPROVED && isset($property)) {
            \Log::info('Processing post-approval actions');
            
            if ($request->boolean('send_invitation')) {
                \Log::info('Sending landlord invitation');
                $this->sendLandlordInvitation($property, $request);
            }
            
            $approvedTenants = $registration->tenants()->where('status', 'approved')->get();
            if ($request->boolean('send_tenant_invitations') && $approvedTenants->count() > 0) {
                \Log::info('Sending tenant invitations to ' . $approvedTenants->count() . ' approved tenants');
                $this->sendTenantInvitations($registration, $property, $request, $approvedTenants);
            }
            
            if ($registration->landlord) {
                $message = "Your property registration has been approved! ";
                if ($registration->has_tenants) {
                    $approvedCount = $registration->tenants()->where('status', 'approved')->count();
                    $message .= "{$approvedCount} tenant(s) have been automatically approved and added to the property.";
                }
                
                // ✅ FIXED: Include construction status in the message
                if ($registration->isConstruction() && $property->status === Property::STATUS_UNDER_CONSTRUCTION) {
                    $message .= " Your property is marked as 'Under Construction' with estimated completion: " . 
                                ($property->estimated_completion ? $property->estimated_completion->format('M d, Y') : 'Not specified') . ".";
                }
                
                \Log::info('Sending approval notification to landlord', [
                    'landlord_id' => $registration->landlord_id,
                    'message' => $message
                ]);
                
                $registration->landlord->notify(
                    new ConstructionRegistrationStatusNotification($registration, 'approved', $message)
                );
            }
        } 
        // Send notification for other status changes if requested (requires registered landlord)
        elseif ($request->boolean('notify_landlord') && $registration->landlord && $request->status !== LandlordConstructionRegistration::STATUS_REJECTED) {
            \Log::info('Sending status notification to landlord', [
                'landlord_id' => $registration->landlord_id,
                'status' => $request->status
            ]);
            
            $registration->landlord->notify(
                new ConstructionRegistrationStatusNotification($registration, $request->status, $request->admin_notes)
            );
        }

        // Notify assigned admin if changed
        if ($request->filled('assigned_to') && $request->assigned_to != $oldStatus) {
            $admin = User::find($request->assigned_to);
            if ($admin) {
                \Log::info('Notifying assigned admin', [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name
                ]);
                $admin->notify(new RegistrationAssignedNotification($registration, auth()->user()));
            }
        }

        Log::info('Construction registration status updated', [
            'registration_id' => $registration->id,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'reviewed_by' => auth()->id(),
            'landlord_invitation_sent' => $request->boolean('send_invitation'),
            'tenant_invitations_sent' => $request->boolean('send_tenant_invitations') && $registration->has_tenants,
            'tenants_auto_approved' => count($autoApprovedTenants),
            'rejection_notification_sent' => $notificationResult ? $notificationResult['success'] : false,
            'rejection_channels_sent' => $notificationResult ? $notificationResult['channels_sent'] : [],
            'property_status' => $property ? $property->status : null,
            'construction_status' => $property ? $property->construction_status : null,
        ]);

        $message = 'Registration status updated successfully.';
        if ($request->status === LandlordConstructionRegistration::STATUS_APPROVED && isset($property)) {
            $message .= ' Property has been created with status: ' . $property->status . '.';
            if (count($autoApprovedTenants) > 0) {
                $message .= ' ' . count($autoApprovedTenants) . ' tenant(s) automatically approved.';
            }
            if ($request->boolean('send_invitation')) {
                $message .= ' Landlord invitation sent.';
            }
            if ($request->boolean('send_tenant_invitations') && $registration->tenants()->where('status', 'approved')->count() > 0) {
                $message .= ' Tenant invitations sent.';
            }
            // ✅ FIXED: Add construction details to success message
            if ($registration->isConstruction() && $property->status === Property::STATUS_UNDER_CONSTRUCTION) {
                $message .= ' Property marked as Under Construction.';
            }
        } elseif ($request->status === LandlordConstructionRegistration::STATUS_REJECTED && $notificationResult) {
            $message .= ' Rejection notification sent.';
            if ($notificationResult['success'] && count($notificationResult['channels_sent']) > 0) {
                $message .= ' Via: ' . implode(', ', $notificationResult['channels_sent']);
            } elseif (!$notificationResult['success']) {
                $message .= ' Notification failed to send.';
            }
        }

        \Log::info('Response message:', ['message' => $message]);

        if ($request->ajax() || $request->wantsJson()) {
            $response = [
                'success' => true,
                'message' => $message,
                'registration' => $registration->fresh(['approvedProperty', 'tenants']),
                'property' => $property ?? null,
                'auto_approved_tenants' => $autoApprovedTenants,
                'invitations_sent' => [
                    'landlord' => $request->boolean('send_invitation'),
                    'tenants' => $request->boolean('send_tenant_invitations') && $registration->has_tenants
                ],
                // ✅ FIXED: Add construction status to response
                'construction_status' => $property ? [
                    'status' => $property->status,
                    'construction_status' => $property->construction_status,
                    'has_plans' => $property->has_plans,
                    'estimated_completion' => $property->estimated_completion,
                    'is_under_construction' => $property->status === Property::STATUS_UNDER_CONSTRUCTION,
                ] : null,
            ];
            
            if ($request->status === LandlordConstructionRegistration::STATUS_REJECTED && $notificationResult) {
                $response['notification_sent'] = $notificationResult['success'];
                $response['notification_channels_sent'] = $notificationResult['channels_sent'];
                $response['notification_error'] = !$notificationResult['success'] ? $notificationResult['message'] : null;
            }
            
            \Log::info('JSON Response:', $response);
            return response()->json($response);
        }

        \Log::info('Redirecting to show page');
        return redirect()->route('admin.construction-registrations.show', $registration)
            ->with('success', $message);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('========== ERROR IN UPDATE STATUS ==========');
        \Log::error('Error message: ' . $e->getMessage());
        \Log::error('Error code: ' . $e->getCode());
        \Log::error('Error file: ' . $e->getFile() . ':' . $e->getLine());
        \Log::error('Stack trace: ' . $e->getTraceAsString());

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Failed to update status: ' . $e->getMessage())
            ->withInput();
    }
}

   /**
 * Send rejection notification to landlord via selected channels
 * This bypasses Laravel's notification system since landlord may not have an account yet
 */
private function sendRejectionNotification(LandlordConstructionRegistration $registration, Request $request): array
{
    $channels = $request->notification_channels ?? ['email'];
    $customMessage = $request->custom_message ?? null;
    $rejectionReason = $registration->rejection_reason;
    
    // Get landlord contact info from registration (not from user account)
    $landlordEmail = $registration->email;
    $landlordPhone = $registration->primary_phone;
    
    // Standardize phone number to international format if needed
    if ($landlordPhone) {
        $landlordPhone = $this->standardizePhoneNumber($landlordPhone);
    }
    
    $sentChannels = [];
    $failedChannels = [];
    
    foreach ($channels as $channel) {
        try {
            switch ($channel) {
                case 'email':
                    if ($landlordEmail) {
                        $sent = $this->sendRejectionEmail($landlordEmail, $registration, $rejectionReason, $customMessage);
                        if ($sent) {
                            $sentChannels[] = 'email';
                            Log::info('Rejection email sent to landlord', [
                                'email' => $landlordEmail,
                                'registration_id' => $registration->id
                            ]);
                        } else {
                            $failedChannels[] = 'email';
                        }
                    } else {
                        $failedChannels[] = 'email';
                        Log::warning('Cannot send rejection email: No email address provided', [
                            'registration_id' => $registration->id
                        ]);
                    }
                    break;
                    
                case 'sms':
                    if ($landlordPhone) {
                        // Send SMS directly using SMS service
                        $message = $this->formatRejectionSmsMessage($registration, $rejectionReason, $customMessage);
                        $smsResult = $this->smsService->sendWithDefaultProvider($landlordPhone, $message);
                        
                        if ($smsResult['success']) {
                            $sentChannels[] = 'sms';
                            Log::info('Rejection SMS sent to landlord', [
                                'phone' => $landlordPhone,
                                'original_phone' => $registration->primary_phone,
                                'registration_id' => $registration->id,
                                'message_id' => $smsResult['message_id'] ?? null
                            ]);
                        } else {
                            $failedChannels[] = 'sms';
                            Log::error('Failed to send rejection SMS', [
                                'phone' => $landlordPhone,
                                'original_phone' => $registration->primary_phone,
                                'error' => $smsResult['message'] ?? 'Unknown error',
                                'registration_id' => $registration->id
                            ]);
                        }
                    } else {
                        $failedChannels[] = 'sms';
                        Log::warning('Cannot send rejection SMS: No phone number provided', [
                            'registration_id' => $registration->id
                        ]);
                    }
                    break;
            }
        } catch (\Exception $e) {
            $failedChannels[] = $channel;
            Log::error('Failed to send rejection notification via ' . $channel, [
                'error' => $e->getMessage(),
                'registration_id' => $registration->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    // Log the notification activity
    $registration->logActivity('rejection_notification_sent', 
        "Rejection notification sent via " . implode(', ', $sentChannels) . 
        (count($failedChannels) > 0 ? " (Failed: " . implode(', ', $failedChannels) . ")" : "")
    );
    
    return [
        'success' => count($sentChannels) > 0,
        'channels_sent' => $sentChannels,
        'channels_failed' => $failedChannels,
        'message' => count($sentChannels) > 0 
            ? "Notification sent via " . implode(', ', $sentChannels)
            : "Failed to send notification via any channel"
    ];
}

/**
 * Standardize phone number to international format (+233XXXXXXXXX)
 */
private function standardizePhoneNumber(?string $phone): ?string
{
    if (empty($phone)) {
        return null;
    }
    
    // Remove any non-digit characters except +
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // Check if already in international format
    if (preg_match('/^\+233\d{9}$/', $phone)) {
        return $phone;
    }
    
    // Check if starts with 233 (without +)
    if (preg_match('/^233(\d{9})$/', $phone, $matches)) {
        return '+' . $phone;
    }
    
    // Check if starts with 0 (local Ghanaian format)
    if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
        return '+233' . $matches[1];
    }
    
    // Check if it's just 9 digits
    if (preg_match('/^(\d{9})$/', $phone, $matches)) {
        return '+233' . $matches[1];
    }
    
    // If all else fails, return as is (will likely fail)
    Log::warning('Unable to standardize phone number', [
        'original_phone' => $phone,
        'cleaned_phone' => $phone
    ]);
    
    return $phone;
}

    /**
     * Send rejection email directly (bypassing notification system)
     */
    private function sendRejectionEmail(string $to, LandlordConstructionRegistration $registration, string $rejectionReason, ?string $customMessage = null): bool
    {
        try {
            $subject = 'Update on Your Property Registration - ' . config('app.name', 'Property Registration System');
            
            $body = $this->buildRejectionEmailBody($registration, $rejectionReason, $customMessage);
            
            // Send using Laravel's Mail facade
            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send rejection email: ' . $e->getMessage(), [
                'to' => $to,
                'registration_id' => $registration->id
            ]);
            return false;
        }
    }

    /**
     * Build rejection email body content
     */
    private function buildRejectionEmailBody(LandlordConstructionRegistration $registration, string $rejectionReason, ?string $customMessage = null): string
    {
        $appName = config('app.name', 'Property Registration System');
        $appUrl = config('app.url', 'http://hilltop.test');
        
        $body = "Dear {$registration->name},\n\n";
        $body .= "Thank you for submitting your property registration to {$appName}.\n\n";
        $body .= "After careful review of your application, we regret to inform you that we are unable to approve your registration at this time.\n\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "REJECTION REASON:\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "{$rejectionReason}\n\n";
        
        if ($customMessage) {
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= "ADDITIONAL NOTES FROM ADMIN:\n";
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= "{$customMessage}\n\n";
        }
        
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "YOUR SUBMITTED DETAILS:\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "• Property Name: {$registration->property_name}\n";
        $body .= "• Plot Number: {$registration->plot_number}\n";
        $body .= "• Location: {$registration->street_name}\n";
        if ($registration->digital_address) {
            $body .= "• Digital Address: {$registration->digital_address}\n";
        }
        $body .= "• Registration Type: {$registration->registration_type_label}\n";
        $body .= "• Submission Date: " . ($registration->submitted_at ? $registration->submitted_at->format('F j, Y') : $registration->created_at->format('F j, Y')) . "\n\n";
        
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "NEXT STEPS:\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "1. Review the rejection reason above\n";
        $body .= "2. Make the necessary corrections to your application\n";
        $body .= "3. Contact our estate management office for clarification\n";
        $body .= "4. Submit a new registration with the required information\n\n";
        
        $body .= "You can submit a new registration by visiting: {$appUrl}/landlord/construction/create\n\n";
        
        $body .= "If you have any questions or need assistance, please contact our support team:\n";
        $body .= "• Email: " . config('mail.support_email', 'support@hilltop.com') . "\n";
        $body .= "• Phone: " . config('app.support_phone', '+233 123 456 789') . "\n\n";
        
        $body .= "Thank you for your understanding.\n\n";
        $body .= "Best regards,\n";
        $body .= "{$appName} Estate Management Team\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "This is an automated message. Please do not reply to this email.\n";
        
        return $body;
    }

    /**
     * Format rejection SMS message (160 character limit)
     */
    private function formatRejectionSmsMessage(LandlordConstructionRegistration $registration, string $rejectionReason, ?string $customMessage = null): string
    {
        $appName = config('app.name', 'Property Registration');
        
        // Use custom message if provided and short enough
        if ($customMessage && strlen($customMessage) <= 100) {
            $message = $customMessage;
        } else {
            $propertyInfo = $registration->property_name ?: 'Plot ' . $registration->plot_number;
            $shortReason = strlen($rejectionReason) > 75 ? substr($rejectionReason, 0, 72) . '...' : $rejectionReason;
            $message = "{$appName}: Your property '{$propertyInfo}' registration has been rejected. Reason: {$shortReason}";
        }
        
        // Add contact info if space allows
        if (strlen($message) < 130) {
            $message .= " Contact support for assistance.";
        }
        
        return $message;
    }

    /**
     * Send invitations to approved tenants
     */
    private function sendTenantInvitations(LandlordConstructionRegistration $registration, Property $property, Request $request, $tenants = null)
    {
        try {
            $tenants = $tenants ?? $registration->tenants()->where('status', 'approved')->get();
            
            if ($tenants->isEmpty()) {
                Log::warning('No approved tenants found to send invitations to', [
                    'registration_id' => $registration->id
                ]);
                return [
                    'success' => false,
                    'message' => 'No approved tenants found',
                    'success_count' => 0,
                    'failed_count' => 0
                ];
            }

            $channels = $request->tenant_invitation_channels ?? ['email', 'sms'];
            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($tenants as $tenant) {
                try {
                    $tenantUser = $this->findOrCreateTenantUser($tenant);
                    
                    if (!$tenantUser) {
                        $failedCount++;
                        Log::error('Failed to create/find tenant user', [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name
                        ]);
                        continue;
                    }
                    
                    if (!$tenant->user_id) {
                        $tenant->user_id = $tenantUser->id;
                        $tenant->save();
                        
                        Log::info('Linked tenant record to user', [
                            'tenant_id' => $tenant->id,
                            'user_id' => $tenantUser->id
                        ]);
                    }
                    
                    if (!$property->tenants()->where('user_id', $tenantUser->id)->exists()) {
                        $property->tenants()->attach($tenantUser->id, [
                            'added_by' => auth()->id(),
                            'added_at' => now(),
                            'notes' => $tenant->notes ?? null,
                            'status' => 'active'
                        ]);
                        
                        Log::info('Tenant attached to property', [
                            'property_id' => $property->id,
                            'tenant_user_id' => $tenantUser->id
                        ]);
                    }
                    
                    if (empty($tenantUser->email) && empty($tenantUser->phone)) {
                        Log::warning('Tenant has no contact information', [
                            'tenant_id' => $tenant->id,
                            'tenant_user_id' => $tenantUser->id
                        ]);
                        $failedCount++;
                        continue;
                    }
                    
                    $invitationResult = $this->tenantInvitationService->sendInvitation(
                        $tenantUser,
                        $property,
                        $channels
                    );
                    
                    if ($invitationResult['success'] ?? false) {
                        $successCount++;
                        
                        Log::info('Tenant invitation sent successfully via service', [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'tenant_user_id' => $tenantUser->id,
                            'invitation_id' => $invitationResult['invitation_id'] ?? null,
                            'channels_successful' => $invitationResult['channels_successful'] ?? []
                        ]);
                    } else {
                        $failedCount++;
                        
                        Log::error('Failed to send tenant invitation via service', [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'error' => $invitationResult['message'] ?? 'Unknown error'
                        ]);
                    }
                    
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'tenant_user_id' => $tenantUser->id,
                        'success' => $invitationResult['success'] ?? false,
                        'invitation_result' => $invitationResult
                    ];
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Exception while processing tenant invitation: ' . $e->getMessage(), [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $results[] = [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            Log::info('Tenant invitations processed', [
                'registration_id' => $registration->id,
                'property_id' => $property->id,
                'total_tenants' => $tenants->count(),
                'successful' => $successCount,
                'failed' => $failedCount
            ]);

            if (method_exists($registration, 'logActivity')) {
                $registration->logActivity('tenant_invitations_sent', 
                    "Sent {$successCount} tenant invitations out of {$tenants->count()} approved tenants using TenantInvitationService"
                );
            }

            return [
                'success' => $successCount > 0,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitations: ' . $e->getMessage(), [
                'registration_id' => $registration->id,
                'property_id' => $property->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Find or create tenant user account
     */
    private function findOrCreateTenantUser($tenant): ?User
    {
        try {
            if ($tenant->phone) {
                $tenantUser = User::findByAnyPhoneFormat($tenant->phone);
                if ($tenantUser) {
                    if (!$tenantUser->isTenant()) {
                        $tenantUser->update(['type' => User::TYPE_TENANT]);
                    }
                    return $tenantUser;
                }
            }
            
            if ($tenant->email) {
                $tenantUser = User::where('email', $tenant->email)->first();
                if ($tenantUser) {
                    if (!$tenantUser->isTenant()) {
                        $tenantUser->update(['type' => User::TYPE_TENANT]);
                    }
                    return $tenantUser;
                }
            }
            
            return User::create([
                'name' => $tenant->name,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'type' => User::TYPE_TENANT,
                'status' => User::STATUS_ACTIVE,
                'password' => bcrypt(Str::random(12)),
                'created_by' => auth()->id(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to find/create tenant user: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id ?? null,
                'tenant_name' => $tenant->name ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Send individual tenant invitation
     */
    private function sendTenantInvitation(TenantInvitation $invitation, $tenant, Property $property): array
    {
        try {
            $landlord = $property->landlord;
            
            if (!$landlord) {
                Log::warning('No landlord found for property', [
                    'property_id' => $property->id,
                    'tenant_id' => $tenant->id
                ]);
            }
            
            $message = $this->generateTenantInvitationMessage($tenant, $property, $landlord, $invitation);
            
            $results = [];
            $sentChannels = [];
            $successCount = 0;

            foreach ($invitation->channels as $channel) {
                $result = [
                    'channel' => $channel,
                    'success' => false,
                    'message' => null
                ];
                
                switch ($channel) {
                    case 'email':
                        if ($tenant->email) {
                            try {
                                Notification::route('mail', $tenant->email)
                                    ->notify(new \App\Notifications\TenantInvitationNotification(
                                        $invitation, 
                                        $tenant, 
                                        $property, 
                                        $landlord ?? $property->landlord ?? new \App\Models\User()
                                    ));
                                
                                $result['success'] = true;
                                $sentChannels[] = $channel;
                                $successCount++;
                                
                                Log::info('Tenant invitation email sent', [
                                    'tenant_id' => $tenant->id,
                                    'email' => $tenant->email,
                                    'invitation_id' => $invitation->id
                                ]);
                            } catch (\Exception $e) {
                                $result['success'] = false;
                                $result['message'] = $e->getMessage();
                                
                                Log::error('Failed to send tenant invitation email: ' . $e->getMessage(), [
                                    'tenant_id' => $tenant->id,
                                    'email' => $tenant->email,
                                    'invitation_id' => $invitation->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        } else {
                            $result['message'] = 'No email address available';
                            Log::warning('Cannot send email invitation - no email address', [
                                'tenant_id' => $tenant->id,
                                'invitation_id' => $invitation->id
                            ]);
                        }
                        break;
                        
                    case 'sms':
                        if ($tenant->phone) {
                            try {
                                Log::info('SMS would be sent to tenant', [
                                    'tenant_id' => $tenant->id,
                                    'phone' => $tenant->phone,
                                    'invitation_id' => $invitation->id,
                                    'message_length' => strlen($message)
                                ]);
                                
                                $result['success'] = true;
                                $sentChannels[] = $channel;
                                $successCount++;
                            } catch (\Exception $e) {
                                $result['success'] = false;
                                $result['message'] = $e->getMessage();
                                
                                Log::error('Failed to send SMS: ' . $e->getMessage(), [
                                    'tenant_id' => $tenant->id,
                                    'invitation_id' => $invitation->id
                                ]);
                            }
                        } else {
                            $result['message'] = 'No phone number available';
                            Log::warning('Cannot send SMS invitation - no phone number', [
                                'tenant_id' => $tenant->id,
                                'invitation_id' => $invitation->id
                            ]);
                        }
                        break;
                }
                
                $results[$channel] = $result;
            }

            if ($successCount > 0) {
                return [
                    'success' => true,
                    'message' => "Invitation sent via {$successCount} channel(s)",
                    'results' => $results,
                    'sent_channels' => $sentChannels
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send invitation via any channel',
                    'results' => $results,
                    'sent_channels' => $sentChannels
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to send tenant invitation: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'invitation_id' => $invitation->id,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invitation: ' . $e->getMessage(),
                'sent_channels' => []
            ];
        }
    }

    /**
     * Generate tenant invitation message
     */
    private function generateTenantInvitationMessage($tenant, Property $property, User $landlord, TenantInvitation $invitation): string
    {
        $message = "Hello {$tenant->name},\n\n";
        $message .= "You have been registered as a tenant at {$property->property_name} by your landlord, {$landlord->name}.\n\n";
        $message .= "Property Details:\n";
        $message .= "📍 Address: {$property->street_name}\n";
        if ($property->zone) $message .= "📍 Zone: {$property->zone}\n";
        if ($property->section) $message .= "📍 Section: {$property->section}\n\n";
        
        $message .= "To access your tenant portal and manage your tenancy, please click the link below:\n";
        $message .= "{$invitation->getInvitationUrl()}\n\n";
        
        $message .= "This link will expire on {$invitation->expires_at->format('F j, Y')}.\n\n";
        $message .= "If you have any questions, please contact your landlord or the estate management office.\n\n";
        $message .= "Thank you,\n";
        $message .= "Property Management Team";

        return $message;
    }

    /**
     * Get tenant invitation status for a registration
     */
    public function getTenantInvitationStatus(LandlordConstructionRegistration $registration)
    {
        try {
            $tenants = $registration->tenants()->with('invitations')->get();
            
            $data = [];
            foreach ($tenants as $tenant) {
                $latestInvitation = $tenant->invitations()->latest()->first();
                
                $data[] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_status' => $tenant->status,
                    'has_invitation' => $latestInvitation ? true : false,
                    'invitation' => $latestInvitation ? [
                        'id' => $latestInvitation->id,
                        'status' => $latestInvitation->status,
                        'status_label' => $latestInvitation->status,
                        'sent_at' => $latestInvitation->sent_at?->format('Y-m-d H:i:s'),
                        'expires_at' => $latestInvitation->expires_at->format('Y-m-d H:i:s'),
                        'accepted_at' => $latestInvitation->accepted_at?->format('Y-m-d H:i:s'),
                        'invitation_url' => $latestInvitation->getInvitationUrl(),
                        'is_expired' => $latestInvitation->isExpired(),
                        'is_active' => $latestInvitation->isActive()
                    ] : null
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'summary' => [
                    'total_tenants' => $tenants->count(),
                    'invitations_sent' => collect($data)->where('invitation.status', TenantInvitation::STATUS_SENT)->count(),
                    'invitations_accepted' => collect($data)->where('invitation.status', TenantInvitation::STATUS_ACCEPTED)->count(),
                    'invitations_failed' => collect($data)->where('invitation.status', TenantInvitation::STATUS_FAILED)->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tenant invitation status: ' . $e->getMessage(), [
                'registration_id' => $registration->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invitation status'
            ], 500);
        }
    }

    /**
     * Export registrations to CSV/Excel
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'sometimes|in:csv,xlsx',
            'status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'zone' => 'nullable|string',
            'section' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $format = $request->get('format', 'csv');
        $filename = 'construction-registrations-' . now()->format('Y-m-d-His') . '.' . $format;

        return Excel::download(
            new ConstructionRegistrationsExport($request->all()), 
            $filename
        );
    }

    /**
     * Get statistics overview
     */
    public function stats(Request $request)
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from) : now()->subMonths(12);
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to) : now();
        
        $monthlyStats = LandlordConstructionRegistration::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "in_review" THEN 1 ELSE 0 END) as in_review'),
                DB::raw('SUM(CASE WHEN status = "needs_info" THEN 1 ELSE 0 END) as needs_info'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled')
            )
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $query = LandlordConstructionRegistration::whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()]);
        
        $allRegistrations = $query->get();
        $totalRegistrations = $allRegistrations->count();

        $stats = [
            'total_registrations' => $totalRegistrations,
            'monthly_totals' => [
                'this_month' => $allRegistrations->whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
                'last_month' => $allRegistrations->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count(),
            ],
            
            'by_type' => [
                'construction' => $allRegistrations->where('registration_type', LandlordConstructionRegistration::TYPE_CONSTRUCTION)->count(),
                'property_capture' => $allRegistrations->where('registration_type', LandlordConstructionRegistration::TYPE_PROPERTY_CAPTURE)->count(),
            ],
            
            'by_purpose' => [
                'construction' => $allRegistrations->where('purpose', LandlordConstructionRegistration::PURPOSE_CONSTRUCTION)->count(),
                'permanent_registration' => $allRegistrations->where('purpose', LandlordConstructionRegistration::PURPOSE_PERMANENT_REGISTRATION)->count(),
                'both' => $allRegistrations->where('purpose', LandlordConstructionRegistration::PURPOSE_BOTH)->count(),
            ],
            
            'by_status' => [
                'pending' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_PENDING)->count(),
                'in_review' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_IN_REVIEW)->count(),
                'approved' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count(),
                'rejected' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_REJECTED)->count(),
                'needs_info' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_NEEDS_INFO)->count(),
                'cancelled' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_CANCELLED)->count(),
            ],
            
            'monthly_stats' => $monthlyStats,
            
            'with_tenants' => $allRegistrations->where('has_tenants', true)->count(),
            'total_tenants' => $allRegistrations->sum('tenant_count'),
            
            'avg_processing_time' => $this->calculateAverageProcessingTime($dateFrom, $dateTo),
            
            'property_type_stats' => $this->getPropertyTypeStats($dateFrom, $dateTo),
            'existing_property_type_stats' => $this->getExistingPropertyTypeStats($dateFrom, $dateTo),
            
            'zone_stats' => $this->getZoneStats($dateFrom, $dateTo),
            'section_stats' => $this->getSectionStats($dateFrom, $dateTo),
            
            'admin_performance' => $this->getAdminPerformanceStats($dateFrom, $dateTo),
            
            'recent_activity' => $this->getRecentActivity($dateFrom, $dateTo),
        ];

        $total = $stats['total_registrations'] > 0 ? $stats['total_registrations'] : 1;
        
        $stats['by_type']['construction_percentage'] = round(($stats['by_type']['construction'] / $total) * 100, 1);
        $stats['by_type']['property_percentage'] = round(($stats['by_type']['property_capture'] / $total) * 100, 1);
        
        $stats['by_purpose']['construction_percentage'] = $total > 0 ? round(($stats['by_purpose']['construction'] / $total) * 100, 1) : 0;
        $stats['by_purpose']['permanent_percentage'] = $total > 0 ? round(($stats['by_purpose']['permanent_registration'] / $total) * 100, 1) : 0;
        $stats['by_purpose']['both_percentage'] = $total > 0 ? round(($stats['by_purpose']['both'] / $total) * 100, 1) : 0;

        $dailyTrends = LandlordConstructionRegistration::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'daily_trends' => $dailyTrends
            ]);
        }

        return view('admin.construction-registrations.stats', compact(
            'stats',
            'monthlyStats',
            'dailyTrends'
        ));
    }

    /**
     * Calculate average processing time
     */
    private function calculateAverageProcessingTime($dateFrom, $dateTo): array
    {
        $processed = LandlordConstructionRegistration::whereNotNull('reviewed_at')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('status', [LandlordConstructionRegistration::STATUS_APPROVED, LandlordConstructionRegistration::STATUS_REJECTED])
            ->get();
        
        if ($processed->isEmpty()) {
            return ['hours' => 0, 'days' => 0];
        }

        $totalHours = $processed->sum(function($item) {
            return $item->created_at->diffInHours($item->reviewed_at);
        });

        $avgHours = $totalHours / $processed->count();
        
        return [
            'hours' => round($avgHours, 1),
            'days' => round($avgHours / 24, 1)
        ];
    }

    /**
     * Get property type statistics (for construction registrations)
     */
    private function getPropertyTypeStats($dateFrom, $dateTo): array
    {
        $registrations = LandlordConstructionRegistration::where('registration_type', LandlordConstructionRegistration::TYPE_CONSTRUCTION)
            ->whereNotNull('property_type')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $stats = [];
        
        foreach ($registrations as $reg) {
            if ($reg->property_type === 'other' && $reg->custom_property_type) {
                $key = $reg->custom_property_type;
                $stats[$key] = ($stats[$key] ?? 0) + 1;
            } else {
                $key = $reg->property_type ?: 'not_specified';
                $stats[$key] = ($stats[$key] ?? 0) + 1;
            }
        }
        
        arsort($stats);
        return $stats;
    }

    /**
     * Get existing property type statistics (for property capture)
     */
    private function getExistingPropertyTypeStats($dateFrom, $dateTo): array
    {
        $registrations = LandlordConstructionRegistration::where('registration_type', LandlordConstructionRegistration::TYPE_PROPERTY_CAPTURE)
            ->whereNotNull('existing_property_type')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $stats = [];
        
        foreach ($registrations as $reg) {
            if ($reg->existing_property_type === 'other' && $reg->existing_custom_property_type) {
                $key = $reg->existing_custom_property_type;
                $stats[$key] = ($stats[$key] ?? 0) + 1;
            } else {
                $key = $reg->existing_property_type ?: 'not_specified';
                $stats[$key] = ($stats[$key] ?? 0) + 1;
            }
        }
        
        arsort($stats);
        return $stats;
    }

    /**
     * Get zone statistics
     */
    private function getZoneStats($dateFrom, $dateTo): array
    {
        return LandlordConstructionRegistration::whereNotNull('zone')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get()
            ->groupBy('zone')
            ->map->count()
            ->sortDesc()
            ->toArray();
    }

    /**
     * Get section statistics
     */
    private function getSectionStats($dateFrom, $dateTo): array
    {
        return LandlordConstructionRegistration::whereNotNull('section')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get()
            ->groupBy('section')
            ->map->count()
            ->sortDesc()
            ->toArray();
    }

    /**
     * Get admin performance statistics
     */
    private function getAdminPerformanceStats($dateFrom, $dateTo): array
    {
        $admins = User::whereIn('type', [User::TYPE_ADMIN, User::TYPE_SUPER_ADMIN])
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        $stats = [];

        foreach ($admins as $admin) {
            $reviewed = LandlordConstructionRegistration::where('reviewed_by', $admin->id)
                ->whereBetween('reviewed_at', [$dateFrom, $dateTo])
                ->get();
            
            $approved = $reviewed->where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count();
            $rejected = $reviewed->where('status', LandlordConstructionRegistration::STATUS_REJECTED)->count();
            $needsInfo = $reviewed->where('status', LandlordConstructionRegistration::STATUS_NEEDS_INFO)->count();
            
            $totalHours = $reviewed->sum(function($item) {
                return $item->created_at->diffInHours($item->reviewed_at);
            });
            
            $avgTime = $reviewed->count() > 0 ? $totalHours / $reviewed->count() : 0;

            $assignedCount = LandlordConstructionRegistration::where('assigned_to', $admin->id)
                ->whereIn('status', [
                    LandlordConstructionRegistration::STATUS_PENDING, 
                    LandlordConstructionRegistration::STATUS_IN_REVIEW
                ])
                ->count();

            $stats[] = [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'approved' => $approved,
                'rejected' => $rejected,
                'needs_info' => $needsInfo,
                'total_reviewed' => $reviewed->count(),
                'avg_processing_time' => round($avgTime, 1),
                'assigned_count' => $assignedCount,
            ];
        }

        usort($stats, function($a, $b) {
            return $b['total_reviewed'] <=> $a['total_reviewed'];
        });

        return $stats;
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity($dateFrom, $dateTo): array
    {
        return LandlordConstructionRegistration::whereNotNull('reviewed_at')
            ->whereBetween('reviewed_at', [$dateFrom, $dateTo])
            ->with(['reviewer'])
            ->latest('reviewed_at')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'status' => $item->status,
                    'status_label' => $item->status_label,
                    'registration_type' => $item->registration_type,
                    'plot_number' => $item->plot_number,
                    'property_name' => $item->property_name,
                    'reviewed_at' => $item->reviewed_at,
                    'reviewed_at_formatted' => $item->reviewed_at->format('Y-m-d H:i:s'),
                    'reviewer_name' => $item->reviewer ? $item->reviewer->name : 'System',
                    'reviewer_id' => $item->reviewer_id,
                ];
            })
            ->toArray();
    }

    /**
     * Export statistics report
     */
    public function exportStats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:summary,detailed,performance,trends',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'registration_type' => 'required|in:all,construction,property_capture',
            'format' => 'required|in:csv,pdf',
            'include_charts' => 'sometimes|boolean',
            'include_trends' => 'sometimes|boolean',
            'include_admin_stats' => 'sometimes|boolean',
            'include_zone_stats' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);
            
            $stats = $this->gatherExportStatistics($request, $dateFrom, $dateTo);
            
            $stats['report'] = [
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user() ? auth()->user()->name : 'System',
                'date_range' => $dateFrom->format('M d, Y') . ' - ' . $dateTo->format('M d, Y'),
                'report_type' => ucfirst($request->report_type),
                'registration_type' => ucfirst(str_replace('_', ' ', $request->registration_type)),
                'filters' => $request->only(['include_charts', 'include_trends', 'include_admin_stats', 'include_zone_stats'])
            ];

            if ($request->format === 'csv') {
                return $this->generateCsvReport($stats, $request);
            } else {
                return $this->generatePdfReport($stats, $request);
            }

        } catch (\Exception $e) {
            Log::error('Failed to export statistics report: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate report. Please try again.'
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to generate report. Please try again.');
        }
    }

    /**
     * Gather statistics for export
     */
    private function gatherExportStatistics(Request $request, $dateFrom, $dateTo): array
    {
        $query = LandlordConstructionRegistration::whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->endOfDay()]);
        
        if ($request->registration_type !== 'all') {
            $query->where('registration_type', $request->registration_type);
        }

        $allRegistrations = $query->get();
        
        $monthlyStats = LandlordConstructionRegistration::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(CASE WHEN status = "in_review" THEN 1 ELSE 0 END) as in_review')
            )
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($request->registration_type !== 'all', function($q) use ($request) {
                $q->where('registration_type', $request->registration_type);
            })
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $stats = [
            'total_registrations' => $allRegistrations->count(),
            'monthly_totals' => [
                'this_month' => $allRegistrations->whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
            ],
            
            'by_type' => [
                'construction' => $allRegistrations->where('registration_type', LandlordConstructionRegistration::TYPE_CONSTRUCTION)->count(),
                'property_capture' => $allRegistrations->where('registration_type', LandlordConstructionRegistration::TYPE_PROPERTY_CAPTURE)->count(),
            ],
            
            'by_purpose' => [
                'construction' => $allRegistrations->where('purpose', LandlordConstructionRegistration::PURPOSE_CONSTRUCTION)->count(),
                'permanent_registration' => $allRegistrations->where('purpose', LandlordConstructionRegistration::PURPOSE_PERMANENT_REGISTRATION)->count(),
                'both' => $allRegistrations->where('purpose', LandlordConstructionRegistration::PURPOSE_BOTH)->count(),
            ],
            
            'by_status' => [
                'pending' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_PENDING)->count(),
                'in_review' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_IN_REVIEW)->count(),
                'approved' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_APPROVED)->count(),
                'rejected' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_REJECTED)->count(),
                'needs_info' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_NEEDS_INFO)->count(),
                'cancelled' => $allRegistrations->where('status', LandlordConstructionRegistration::STATUS_CANCELLED)->count(),
            ],
            
            'monthly_stats' => $monthlyStats,
            
            'with_tenants' => $allRegistrations->where('has_tenants', true)->count(),
            'total_tenants' => $allRegistrations->sum('tenant_count'),
            
            'avg_processing_time' => $this->calculateAverageProcessingTime($dateFrom, $dateTo),
            
            'property_type_stats' => $this->getPropertyTypeStats($dateFrom, $dateTo),
            'existing_property_type_stats' => $this->getExistingPropertyTypeStats($dateFrom, $dateTo),
            
            'zone_stats' => $this->getZoneStats($dateFrom, $dateTo),
            'section_stats' => $this->getSectionStats($dateFrom, $dateTo),
            
            'admin_performance' => $this->getAdminPerformanceStats($dateFrom, $dateTo),
            
            'recent_activity' => $this->getRecentActivity($dateFrom, $dateTo),
        ];

        $total = $stats['total_registrations'] > 0 ? $stats['total_registrations'] : 1;
        
        $stats['by_type']['construction_percentage'] = round(($stats['by_type']['construction'] / $total) * 100, 1);
        $stats['by_type']['property_percentage'] = round(($stats['by_type']['property_capture'] / $total) * 100, 1);
        
        $stats['by_purpose']['construction_percentage'] = round(($stats['by_purpose']['construction'] / $total) * 100, 1);
        $stats['by_purpose']['permanent_percentage'] = round(($stats['by_purpose']['permanent_registration'] / $total) * 100, 1);
        $stats['by_purpose']['both_percentage'] = round(($stats['by_purpose']['both'] / $total) * 100, 1);

        return $stats;
    }

    /**
     * Generate CSV report
     */
    private function generateCsvReport(array $stats, Request $request)
    {
        $filename = 'registration_stats_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function() use ($stats, $request) {
            $file = fopen('php://output', 'w');
            
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, ['REGISTRATION STATISTICS REPORT']);
            fputcsv($file, ['Generated:', $stats['report']['generated_at']]);
            fputcsv($file, ['Generated By:', $stats['report']['generated_by']]);
            fputcsv($file, ['Date Range:', $stats['report']['date_range']]);
            fputcsv($file, ['Report Type:', $stats['report']['report_type']]);
            fputcsv($file, ['Registration Type:', $stats['report']['registration_type']]);
            fputcsv($file, []);
            
            fputcsv($file, ['SUMMARY STATISTICS']);
            fputcsv($file, ['Total Registrations', $stats['total_registrations']]);
            fputcsv($file, ['']);
            
            fputcsv($file, ['BY REGISTRATION TYPE']);
            fputcsv($file, ['Type', 'Count', 'Percentage']);
            fputcsv($file, ['Construction', $stats['by_type']['construction'], $stats['by_type']['construction_percentage'] . '%']);
            fputcsv($file, ['Property Capture', $stats['by_type']['property_capture'], $stats['by_type']['property_percentage'] . '%']);
            fputcsv($file, ['']);
            
            fputcsv($file, ['BY PURPOSE']);
            fputcsv($file, ['Purpose', 'Count', 'Percentage']);
            fputcsv($file, ['Construction Only', $stats['by_purpose']['construction'], $stats['by_purpose']['construction_percentage'] . '%']);
            fputcsv($file, ['Permanent Registration', $stats['by_purpose']['permanent_registration'], $stats['by_purpose']['permanent_percentage'] . '%']);
            fputcsv($file, ['Both Purposes', $stats['by_purpose']['both'], $stats['by_purpose']['both_percentage'] . '%']);
            fputcsv($file, ['']);
            
            fputcsv($file, ['BY STATUS']);
            fputcsv($file, ['Status', 'Count']);
            foreach ($stats['by_status'] as $status => $count) {
                fputcsv($file, [ucfirst(str_replace('_', ' ', $status)), $count]);
            }
            fputcsv($file, []);
            
            if ($request->include_trends) {
                fputcsv($file, ['MONTHLY TRENDS']);
                fputcsv($file, ['Month', 'Total', 'Approved', 'Pending', 'Rejected', 'In Review']);
                foreach ($stats['monthly_stats'] as $month) {
                    $date = Carbon::create($month->year, $month->month, 1);
                    fputcsv($file, [
                        $date->format('M Y'),
                        $month->total,
                        $month->approved,
                        $month->pending,
                        $month->rejected,
                        $month->in_review
                    ]);
                }
                fputcsv($file, []);
            }
            
            if ($request->include_admin_stats && !empty($stats['admin_performance'])) {
                fputcsv($file, ['ADMIN PERFORMANCE']);
                fputcsv($file, ['Admin', 'Email', 'Approved', 'Rejected', 'Needs Info', 'Total Reviewed', 'Approval Rate', 'Avg Time (hrs)', 'Assigned']);
                foreach ($stats['admin_performance'] as $admin) {
                    $totalReviewed = ($admin['approved'] ?? 0) + ($admin['rejected'] ?? 0) + ($admin['needs_info'] ?? 0);
                    $approvalRate = $totalReviewed > 0 ? round(($admin['approved'] / $totalReviewed) * 100, 1) : 0;
                    fputcsv($file, [
                        $admin['name'],
                        $admin['email'],
                        $admin['approved'],
                        $admin['rejected'],
                        $admin['needs_info'] ?? 0,
                        $totalReviewed,
                        $approvalRate . '%',
                        $admin['avg_processing_time'],
                        $admin['assigned_count']
                    ]);
                }
                fputcsv($file, []);
            }
            
            if ($request->include_zone_stats && !empty($stats['zone_stats'])) {
                fputcsv($file, ['ZONE DISTRIBUTION']);
                fputcsv($file, ['Zone', 'Count']);
                foreach ($stats['zone_stats'] as $zone => $count) {
                    fputcsv($file, [$zone, $count]);
                }
                fputcsv($file, []);
            }
            
            if ($request->include_zone_stats && !empty($stats['section_stats'])) {
                fputcsv($file, ['SECTION DISTRIBUTION']);
                fputcsv($file, ['Section', 'Count']);
                foreach ($stats['section_stats'] as $section => $count) {
                    fputcsv($file, [$section, $count]);
                }
                fputcsv($file, []);
            }
            
            if (!empty($stats['property_type_stats'])) {
                fputcsv($file, ['PLANNED PROPERTY TYPES (CONSTRUCTION)']);
                fputcsv($file, ['Property Type', 'Count']);
                foreach ($stats['property_type_stats'] as $type => $count) {
                    fputcsv($file, [ucfirst(str_replace('_', ' ', $type)), $count]);
                }
                fputcsv($file, []);
            }
            
            if (!empty($stats['existing_property_type_stats'])) {
                fputcsv($file, ['EXISTING PROPERTY TYPES']);
                fputcsv($file, ['Property Type', 'Count']);
                foreach ($stats['existing_property_type_stats'] as $type => $count) {
                    fputcsv($file, [ucfirst(str_replace('_', ' ', $type)), $count]);
                }
                fputcsv($file, []);
            }
            
            fputcsv($file, ['RECENT ACTIVITY']);
            fputcsv($file, ['Name', 'Type', 'Plot Number', 'Property Name', 'Status', 'Reviewed By', 'Reviewed At']);
            foreach ($stats['recent_activity'] as $activity) {
                fputcsv($file, [
                    $activity['name'],
                    $activity['registration_type'],
                    $activity['plot_number'] ?? 'N/A',
                    $activity['property_name'] ?? 'N/A',
                    $activity['status_label'] ?? $activity['status'],
                    $activity['reviewer_name'] ?? 'System',
                    Carbon::parse($activity['reviewed_at'])->format('Y-m-d H:i:s')
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate PDF report
     */
    private function generatePdfReport(array $stats, Request $request)
    {
        $filename = 'registration_stats_' . now()->format('Y-m-d_His') . '.pdf';
        
        $pdf = Pdf::loadView('reports.registration-stats', [
            'stats' => $stats,
            'includeCharts' => $request->include_charts,
            'includeTrends' => $request->include_trends,
            'includeAdminStats' => $request->include_admin_stats,
            'includeZoneStats' => $request->include_zone_stats
        ]);
        
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download($filename);
    }

    /**
     * Get available channels for landlord
     */
    public function getLandlordChannels(LandlordConstructionRegistration $registration)
    {
        $channels = [];
        
        if ($registration->landlord) {
            $landlord = $registration->landlord;
            
            if ($landlord->email) {
                $channels[] = [
                    'id' => 'email', 
                    'name' => 'Email', 
                    'value' => $landlord->email,
                    'icon' => 'fa-envelope',
                    'color' => 'warning'
                ];
            }
            
            if ($landlord->phone) {
                $channels[] = [
                    'id' => 'sms', 
                    'name' => 'SMS', 
                    'value' => $landlord->phone,
                    'icon' => 'fa-phone',
                    'color' => 'primary'
                ];
            }
            
            if ($landlord->whatsapp_number) {
                $channels[] = [
                    'id' => 'whatsapp', 
                    'name' => 'WhatsApp', 
                    'value' => $landlord->whatsapp_number,
                    'icon' => 'fa-whatsapp',
                    'color' => 'success'
                ];
            }
        } else {
            if ($registration->email) {
                $channels[] = [
                    'id' => 'email',
                    'name' => 'Email',
                    'value' => $registration->email,
                    'icon' => 'fa-envelope',
                    'color' => 'warning',
                    'pending' => true,
                    'note' => 'Landlord account not yet created'
                ];
            }
            
            if ($registration->primary_phone) {
                $channels[] = [
                    'id' => 'sms',
                    'name' => 'SMS',
                    'value' => $registration->primary_phone,
                    'icon' => 'fa-phone',
                    'color' => 'primary',
                    'pending' => true,
                    'note' => 'Landlord account not yet created'
                ];
            }
        }

        return response()->json([
            'success' => true,
            'channels' => $channels
        ]);
    }

    /**
     * Assign registration to specific admin for review
     */
    public function assignToAdmin(Request $request, LandlordConstructionRegistration $registration)
    {
        $validator = Validator::make($request->all(), [
            'admin_id' => 'required|exists:users,id',
            'note' => 'nullable|string|max:500',
            'set_in_review' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $oldAssignee = $registration->assigned_to;
            
            $registration->assigned_to = $request->admin_id;
            $registration->assigned_at = now();
            
            if ($request->boolean('set_in_review') && $registration->status === LandlordConstructionRegistration::STATUS_PENDING) {
                $registration->status = LandlordConstructionRegistration::STATUS_IN_REVIEW;
            }
            
            $registration->save();

            if ($request->filled('note')) {
                $registration->addInternalNote($request->note, auth()->id());
            }

            $admin = User::find($request->admin_id);
            if ($admin) {
                $admin->notify(new RegistrationAssignedNotification($registration, auth()->user()));
            }

            DB::commit();

            Log::info('Registration assigned to admin', [
                'registration_id' => $registration->id,
                'old_assignee' => $oldAssignee,
                'new_assignee' => $request->admin_id,
                'assigned_by' => auth()->id()
            ]);

            return redirect()->route('admin.construction-registrations.show', $registration)
                ->with('success', 'Registration assigned successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to assign registration: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to assign registration. Please try again.');
        }
    }

    /**
     * Add note to registration
     */
    public function addNote(Request $request, LandlordConstructionRegistration $registration)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:1000',
            'is_internal' => 'sometimes|boolean',
            'notify_landlord' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $note = $registration->notes()->create([
                'user_id' => auth()->id(),
                'content' => $request->note,
                'is_internal' => $request->boolean('is_internal', true),
            ]);

            if (!$request->boolean('is_internal') && $request->boolean('notify_landlord') && $registration->landlord) {
                $registration->landlord->notify(new RegistrationNoteAddedNotification($registration, $note));
                
                Log::info('Landlord notified about note', [
                    'registration_id' => $registration->id,
                    'landlord_id' => $registration->landlord_id
                ]);
            }

            DB::commit();

            return redirect()->route('admin.construction-registrations.show', $registration)
                ->with('success', 'Note added successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to add note: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to add note. Please try again.');
        }
    }

    /**
     * Upload supporting document
     */
    public function uploadDocument(Request $request, LandlordConstructionRegistration $registration)
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'document_type' => 'required|in:deed,survey,id_proof,construction_plan,other',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $file = $request->file('document');
            $path = $file->store('registration-documents/' . $registration->id, 'public');

            $document = $registration->documents()->create([
                'filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'document_type' => $request->document_type,
                'description' => $request->description,
                'uploaded_by' => auth()->id(),
            ]);

            $registration->logActivity('document_uploaded', "Document uploaded: {$document->filename}");

            DB::commit();

            return redirect()->route('admin.construction-registrations.show', $registration)
                ->with('success', 'Document uploaded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to upload document: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to upload document. Please try again.');
        }
    }

    /**
     * Download document
     */
    public function downloadDocument(LandlordConstructionRegistration $registration, RegistrationDocument $document)
    {
        if ($document->registration_id !== $registration->id) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($document->file_path, $document->filename);
    }

    /**
     * Delete document
     */
    public function deleteDocument(LandlordConstructionRegistration $registration, RegistrationDocument $document)
    {
        if ($document->registration_id !== $registration->id) {
            abort(404);
        }

        DB::beginTransaction();

        try {
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            $document->delete();

            $registration->logActivity('document_deleted', "Document deleted: {$document->filename}");

            DB::commit();

            return redirect()->route('admin.construction-registrations.show', $registration)
                ->with('success', 'Document deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete document: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to delete document. Please try again.');
        }
    }

    /**
     * Soft delete registration (move to trash)
     */
    public function destroy(LandlordConstructionRegistration $registration)
    {
        if ($registration->status === LandlordConstructionRegistration::STATUS_APPROVED && $registration->approvedProperty) {
            return redirect()->route('admin.construction-registrations.show', $registration)
                ->with('error', 'Cannot delete an approved registration with an associated property. Consider deactivating instead.');
        }

        DB::beginTransaction();

        try {
            $registration->delete();

            Log::info('Registration moved to trash', [
                'registration_id' => $registration->id,
                'deleted_by' => auth()->id()
            ]);

            DB::commit();

            return redirect()->route('admin.construction-registrations.index')
                ->with('success', 'Registration moved to trash successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete registration: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to delete registration. Please try again.');
        }
    }

    /**
     * View trashed registrations
     */
    public function trash(Request $request)
    {
        $query = LandlordConstructionRegistration::onlyTrashed()
            ->with(['landlord', 'reviewer', 'approvedProperty', 'assignedTo'])
            ->latest('deleted_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('property_name', 'like', "%{$search}%")
                  ->orWhere('plot_number', 'like', "%{$search}%")
                  ->orWhere('primary_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('deleted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('deleted_at', '<=', $request->date_to);
        }

        $registrations = $query->paginate(20);

        return view('admin.construction-registrations.trash', compact('registrations'));
    }

    /**
     * Restore from trash
     */
    public function restore($id)
    {
        DB::beginTransaction();

        try {
            $registration = LandlordConstructionRegistration::onlyTrashed()->findOrFail($id);
            $registration->restore();

            $registration->logActivity('restored', 'Registration restored from trash');

            Log::info('Registration restored from trash', [
                'registration_id' => $registration->id,
                'restored_by' => auth()->id()
            ]);

            DB::commit();

            return redirect()->route('admin.construction-registrations.trash')
                ->with('success', 'Registration restored successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to restore registration: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to restore registration. Please try again.');
        }
    }

    /**
     * Permanently delete
     */
    public function forceDelete($id)
    {
        DB::beginTransaction();

        try {
            $registration = LandlordConstructionRegistration::onlyTrashed()->findOrFail($id);
            
            foreach ($registration->documents as $document) {
                if (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }
                $document->forceDelete();
            }
            
            $registration->notes()->forceDelete();
            $registration->tenants()->forceDelete();
            $registration->activityLog()->forceDelete();
            
            $registration->forceDelete();

            Log::info('Registration permanently deleted', [
                'registration_id' => $id,
                'deleted_by' => auth()->id()
            ]);

            DB::commit();

            return redirect()->route('admin.construction-registrations.trash')
                ->with('success', 'Registration permanently deleted.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to permanently delete registration: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to delete registration. Please try again.');
        }
    }

    /**
     * Restore all trashed registrations
     */
    public function restoreAll()
    {
        DB::beginTransaction();

        try {
            $count = LandlordConstructionRegistration::onlyTrashed()->count();
            $registrations = LandlordConstructionRegistration::onlyTrashed()->get();
            
            foreach ($registrations as $registration) {
                $registration->restore();
                $registration->logActivity('restored', 'Registration restored from trash (bulk restore)');
            }

            Log::info('All trashed registrations restored', [
                'count' => $count,
                'restored_by' => auth()->id()
            ]);

            DB::commit();

            return redirect()->route('admin.construction-registrations.trash')
                ->with('success', "{$count} registration(s) restored successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to restore all registrations: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to restore registrations. Please try again.');
        }
    }

    /**
     * Empty trash
     */
    public function emptyTrash()
    {
        DB::beginTransaction();

        try {
            $registrations = LandlordConstructionRegistration::onlyTrashed()->get();
            $count = 0;

            foreach ($registrations as $registration) {
                foreach ($registration->documents as $document) {
                    if (Storage::disk('public')->exists($document->file_path)) {
                        Storage::disk('public')->delete($document->file_path);
                    }
                    $document->forceDelete();
                }
                
                $registration->notes()->forceDelete();
                $registration->tenants()->forceDelete();
                $registration->activityLog()->forceDelete();
                
                $registration->forceDelete();
                $count++;
            }

            Log::info('Trash emptied', [
                'count' => $count,
                'emptied_by' => auth()->id()
            ]);

            DB::commit();

            return redirect()->route('admin.construction-registrations.trash')
                ->with('success', "Trash emptied successfully. {$count} registration(s) permanently deleted.");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to empty trash: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Failed to empty trash. Please try again.');
        }
    }

    /**
     * Bulk action on registrations
     */
    public function bulkAction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:review,approve,reject,delete,assign',
            'registration_ids' => 'required|array',
            'registration_ids.*' => 'exists:landlord_construction_registrations,id',
            'admin_notes' => 'nullable|string|max:1000',
            'admin_id' => 'required_if:action,assign|exists:users,id',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
            'notify_landlord' => 'sometimes|boolean',
            'notification_channels' => 'sometimes|array',
            'notification_channels.*' => 'in:email,sms',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            $registrations = LandlordConstructionRegistration::whereIn('id', $request->registration_ids)->get();
            $successCount = 0;
            $failedCount = 0;
            $errors = [];
            $notificationSummary = ['sent' => 0, 'failed' => 0];

            foreach ($registrations as $registration) {
                try {
                    switch ($request->action) {
                        case 'review':
                            if ($registration->status === LandlordConstructionRegistration::STATUS_PENDING) {
                                $registration->status = LandlordConstructionRegistration::STATUS_IN_REVIEW;
                                $registration->admin_notes = $request->admin_notes;
                                $registration->save();
                                $registration->logActivity('bulk_review', 'Marked as in review via bulk action');
                                $successCount++;
                            }
                            break;
                            
                        case 'reject':
                            if (!in_array($registration->status, [
                                LandlordConstructionRegistration::STATUS_APPROVED,
                                LandlordConstructionRegistration::STATUS_REJECTED,
                                LandlordConstructionRegistration::STATUS_CANCELLED
                            ])) {
                                $registration->status = LandlordConstructionRegistration::STATUS_REJECTED;
                                $registration->rejection_reason = $request->rejection_reason;
                                $registration->admin_notes = $request->admin_notes;
                                $registration->reviewed_at = now();
                                $registration->reviewed_by = auth()->id();
                                $registration->save();
                                
                                // Send notification for bulk rejection if requested
                                if ($request->boolean('notify_landlord')) {
                                    // Use the same direct sending method for bulk rejections
                                    $channels = $request->notification_channels ?? ['email'];
                                    $result = $this->sendBulkRejectionNotification($registration, $request->rejection_reason, $channels);
                                    if ($result['success']) {
                                        $notificationSummary['sent']++;
                                    } else {
                                        $notificationSummary['failed']++;
                                    }
                                }
                                
                                $registration->logActivity('bulk_reject', 'Rejected via bulk action');
                                $successCount++;
                            }
                            break;
                            
                        case 'delete':
                            if ($registration->status !== LandlordConstructionRegistration::STATUS_APPROVED) {
                                $registration->delete();
                                $successCount++;
                            }
                            break;
                            
                        case 'assign':
                            if ($request->admin_id) {
                                $registration->assigned_to = $request->admin_id;
                                $registration->assigned_at = now();
                                $registration->save();
                                $registration->logActivity('bulk_assign', "Assigned to admin ID: {$request->admin_id} via bulk action");
                                $successCount++;
                            }
                            break;
                            
                        case 'approve':
                            $failedCount++;
                            $errors[] = "Registration #{$registration->id}: Approve action must be handled individually";
                            break;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "Registration #{$registration->id}: " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "{$successCount} registration(s) updated successfully.";
            if ($failedCount > 0) {
                $message .= " {$failedCount} registration(s) failed.";
            }
            if ($request->action === 'reject' && $notificationSummary['sent'] > 0) {
                $message .= " {$notificationSummary['sent']} notification(s) sent.";
            }

            if (!empty($errors)) {
                Log::warning('Bulk action partial failures', ['errors' => $errors]);
            }

            return redirect()->back()
                ->with('success', $message)
                ->with('errors', $errors);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk action failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Bulk action failed. Please try again.');
        }
    }

    /**
 * Send bulk rejection notification (direct sending, no user account needed)
 */
private function sendBulkRejectionNotification(LandlordConstructionRegistration $registration, string $reason, array $channels): array
{
    $sentChannels = [];
    $failedChannels = [];
    
    // Use registration contact info directly
    $landlordEmail = $registration->email;
    $landlordPhone = $registration->primary_phone;
    
    // Standardize phone number to international format if needed
    if ($landlordPhone) {
        $landlordPhone = $this->standardizePhoneNumber($landlordPhone);
    }
    
    try {
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    if ($landlordEmail) {
                        $sent = $this->sendRejectionEmail($landlordEmail, $registration, $reason, null);
                        if ($sent) {
                            $sentChannels[] = 'email';
                            Log::info('Bulk rejection email sent', [
                                'email' => $landlordEmail,
                                'registration_id' => $registration->id
                            ]);
                        } else {
                            $failedChannels[] = 'email';
                        }
                    } else {
                        $failedChannels[] = 'email';
                        Log::warning('Bulk rejection: No email address provided', [
                            'registration_id' => $registration->id
                        ]);
                    }
                    break;
                case 'sms':
                    if ($landlordPhone) {
                        $message = $this->formatRejectionSmsMessage($registration, $reason, null);
                        $smsResult = $this->smsService->sendWithDefaultProvider($landlordPhone, $message);
                        if ($smsResult['success']) {
                            $sentChannels[] = 'sms';
                            Log::info('Bulk rejection SMS sent', [
                                'phone' => $landlordPhone,
                                'original_phone' => $registration->primary_phone,
                                'registration_id' => $registration->id
                            ]);
                        } else {
                            $failedChannels[] = 'sms';
                            Log::error('Bulk rejection SMS failed', [
                                'phone' => $landlordPhone,
                                'original_phone' => $registration->primary_phone,
                                'error' => $smsResult['message'] ?? 'Unknown error'
                            ]);
                        }
                    } else {
                        $failedChannels[] = 'sms';
                        Log::warning('Bulk rejection: No phone number provided', [
                            'registration_id' => $registration->id
                        ]);
                    }
                    break;
            }
        }
        
        return [
            'success' => count($sentChannels) > 0,
            'channels_sent' => $sentChannels,
            'channels_failed' => $failedChannels
        ];
        
    } catch (\Exception $e) {
        Log::error('Failed to send bulk rejection notification', [
            'registration_id' => $registration->id,
            'error' => $e->getMessage()
        ]);
        
        return [
            'success' => false,
            'channels_sent' => [],
            'channels_failed' => $channels,
            'error' => $e->getMessage()
        ];
    }
}

 /**
 * Create property from approved registration and link tenants
 * ✅ FIXED: Properly handles vacant land with construction details
 * ✅ FIXED: Sets status = 'under_construction' for vacant land with construction details
 * ✅ FIXED: Sets status = 'vacant' for vacant land without construction details
 * ✅ FIXED: Ensures construction_status is properly set on the property
 * ✅ FIXED: Ensures has_plans and estimated_completion are properly set
 */
private function createPropertyFromRegistration(Request $request, LandlordConstructionRegistration $registration)
{
    DB::beginTransaction();
    
    try {
        // Determine property name first (for logging purposes)
        $propertyName = $registration->property_name;
        
        $existingProperty = Property::where('landlord_id', $request->landlord_id)
            ->where('property_name', $registration->property_name)
            ->first();
        
        if ($existingProperty) {
            if ($existingProperty->trashed()) {
                $existingProperty->restore();
                Log::info('Restored soft-deleted property', [
                    'property_id' => $existingProperty->id,
                    'property_name' => $existingProperty->property_name,
                    'landlord_id' => $request->landlord_id
                ]);
                
                // ============================================================
                // ✅ FIXED: Check if this is vacant land WITHOUT construction details
                // ============================================================
                $hasConstructionDetails = $registration->property_type || 
                                          $registration->construction_documents ||
                                          $registration->has_construction_details ||
                                          $registration->purpose === LandlordConstructionRegistration::PURPOSE_BOTH;
                
                $isVacantLandOnly = $registration->isConstruction() && 
                                   !$hasConstructionDetails;
                
                // Only set property type if construction details exist
                $propertyTypeId = $isVacantLandOnly ? null : $request->property_type_id;
                $customPropertyType = $isVacantLandOnly ? null : $request->custom_property_type;
                
                // ✅ FIXED: Determine status based on construction details
                if ($isVacantLandOnly) {
                    $propertyStatus = Property::STATUS_VACANT;
                    $constructionStatus = 'vacant';
                } elseif ($registration->isConstruction() && $hasConstructionDetails) {
                    // ✅ FIXED: Vacant land WITH construction details = UNDER CONSTRUCTION
                    $propertyStatus = Property::STATUS_UNDER_CONSTRUCTION;
                    $constructionStatus = $registration->property_status ?? 'under_construction';
                } else {
                    $propertyStatus = $this->determinePropertyStatus($registration);
                    $constructionStatus = $registration->isConstruction() ? $registration->property_status : null;
                }
                
                $existingProperty->update([
                    'registration_plan_id' => $request->registration_plan_id,
                    'property_type_id' => $propertyTypeId,
                    'custom_property_type' => $customPropertyType,
                    'house_number' => $registration->plot_number,
                    'street_name' => $registration->street_name,
                    'block_number' => $registration->plot_number,
                    'digital_address' => $registration->digital_address,
                    'zone' => $request->zone,
                    'section' => $request->section,
                    'description' => $registration->land_description,
                    'status' => $propertyStatus,
                    'is_rented' => $registration->has_tenants ?? false,
                    'bedrooms' => $isVacantLandOnly ? null : ($registration->estimated_bedrooms ?? $registration->existing_bedrooms),
                    'bathrooms' => $isVacantLandOnly ? null : ($registration->existing_bathrooms ?? null),
                    'construction_status' => $constructionStatus,
                    'estimated_completion' => $isVacantLandOnly ? null : $registration->estimated_completion,
                    'year_built' => $registration->year_built,
                    // ✅ FIXED: Also set has_plans on existing property
                    'has_plans' => $isVacantLandOnly ? false : ($registration->has_plans ?? false),
                ]);
                
                DB::commit();
                return $existingProperty;
            }
            
            $counter = 1;
            $newPropertyName = $registration->property_name . ' (' . $counter . ')';
            
            while (Property::where('landlord_id', $request->landlord_id)
                ->where('property_name', $newPropertyName)
                ->exists()) {
                $counter++;
                $newPropertyName = $registration->property_name . ' (' . $counter . ')';
            }
            
            Log::info('Property name already exists, using unique name', [
                'original_name' => $registration->property_name,
                'new_name' => $newPropertyName,
                'landlord_id' => $request->landlord_id
            ]);
            
            $propertyName = $newPropertyName;
        } else {
            $propertyName = $registration->property_name;
        }
        
        $registrationPattern = $this->generateRegistrationPattern($registration, $request);
        
        // ============================================================
        // ✅ FIXED: Check if this is vacant land with or without construction details
        // ============================================================
        $hasConstructionDetails = $registration->property_type || 
                                  $registration->construction_documents ||
                                  $registration->has_construction_details ||
                                  $registration->purpose === LandlordConstructionRegistration::PURPOSE_BOTH;
        
        $isVacantLandOnly = $registration->isConstruction() && 
                           !$hasConstructionDetails;
        
        Log::info('Registration construction details check', [
            'registration_id' => $registration->id,
            'property_name' => $registration->property_name,
            'registration_type' => $registration->registration_type,
            'purpose' => $registration->purpose,
            'has_property_type' => !empty($registration->property_type),
            'has_construction_documents' => !empty($registration->construction_documents),
            'has_construction_details' => $registration->has_construction_details ?? false,
            'is_vacant_land_only' => $isVacantLandOnly,
            'has_construction_details_final' => $hasConstructionDetails,
        ]);
        
        // ============================================================
        // ✅ FIXED: Determine property status based on construction details
        // ============================================================
        if ($isVacantLandOnly) {
            // Vacant land with NO construction details - set everything to NULL
            $propertyTypeId = null;
            $customPropertyType = null;
            $constructionStatus = 'vacant';
            $estimatedCompletion = null;
            $bedrooms = null;
            $bathrooms = null;
            $propertyStatus = Property::STATUS_VACANT;
            $hasPlans = false;
            
            Log::info('Processing vacant land WITHOUT construction details', [
                'registration_id' => $registration->id,
                'property_name' => $propertyName,
                'setting_property_type_id_to_null' => true,
                'status' => $propertyStatus
            ]);
            
        } elseif ($registration->isConstruction() && $hasConstructionDetails) {
            // ✅ FIXED: Vacant land WITH construction details = UNDER CONSTRUCTION
            $propertyTypeId = $request->property_type_id;
            $customPropertyType = $request->custom_property_type;
            $constructionStatus = $registration->property_status ?? 'under_construction';
            $estimatedCompletion = $registration->estimated_completion;
            $bedrooms = $registration->estimated_bedrooms;
            $bathrooms = null;
            $propertyStatus = Property::STATUS_UNDER_CONSTRUCTION;
            $hasPlans = $registration->has_plans ?? true;
            
            Log::info('✅ Processing vacant land WITH construction details - setting status to UNDER CONSTRUCTION', [
                'registration_id' => $registration->id,
                'property_name' => $propertyName,
                'property_type_id' => $propertyTypeId,
                'construction_status' => $constructionStatus,
                'status' => $propertyStatus,
                'purpose' => $registration->purpose,
                'has_plans' => $hasPlans,
            ]);
            
        } elseif ($registration->isPropertyCapture()) {
            // Existing property - use determinePropertyStatus
            $propertyTypeId = $request->property_type_id;
            $customPropertyType = $request->custom_property_type;
            $constructionStatus = null;
            $estimatedCompletion = null;
            $bedrooms = $registration->existing_bedrooms;
            $bathrooms = $registration->existing_bathrooms;
            $propertyStatus = $this->determinePropertyStatus($registration);
            $hasPlans = false;
            
            Log::info('Processing existing property (property capture)', [
                'registration_id' => $registration->id,
                'property_name' => $propertyName,
                'property_type_id' => $propertyTypeId,
                'status' => $propertyStatus,
            ]);
            
        } else {
            // Fallback - use determinePropertyStatus
            $propertyTypeId = $request->property_type_id;
            $customPropertyType = $request->custom_property_type;
            $constructionStatus = $registration->isConstruction() ? $registration->property_status : null;
            $estimatedCompletion = $registration->estimated_completion;
            $bedrooms = $registration->estimated_bedrooms ?? $registration->existing_bedrooms;
            $bathrooms = $registration->existing_bathrooms ?? null;
            $propertyStatus = $this->determinePropertyStatus($registration);
            $hasPlans = $registration->has_plans ?? false;
            
            Log::info('Processing property (fallback)', [
                'registration_id' => $registration->id,
                'property_name' => $propertyName,
                'property_type_id' => $propertyTypeId,
                'status' => $propertyStatus,
            ]);
        }
        
        // ============================================================
        // ✅ FIXED: Build property data with correct status
        // ============================================================
        $propertyData = [
            'registration_plan_id' => $request->registration_plan_id,
            'property_type_id' => $propertyTypeId,
            'landlord_id' => $request->landlord_id,
            'created_by' => auth()->id(),
            'property_name' => $propertyName,
            'registration_pattern' => $registrationPattern,
            'house_number' => $registration->plot_number,
            'street_name' => $registration->street_name,
            'block_number' => $registration->plot_number,
            'digital_address' => $registration->digital_address,
            'zone' => $request->zone,
            'section' => $request->section,
            'description' => $registration->land_description,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'status' => $propertyStatus,  // ✅ CRITICAL FIX: This must be set!
            'construction_status' => $constructionStatus,  // ✅ FIXED: This must be set!
            'has_plans' => $hasPlans,  // ✅ FIXED: This must be set!
            'estimated_completion' => $estimatedCompletion,
            'year_built' => $registration->year_built,
            'registration_date' => now(),
            'last_inspection_date' => null,
            'is_rented' => $registration->has_tenants ?? false,
            'tenant_count' => 0,
            'active_tenant_count' => 0,
            'custom_property_type' => $customPropertyType,
            'is_global_sequence' => false,
            'sequence_position' => null,
            'is_field_agent_registered' => false,
            'field_agent_registered_at' => null,
            'registered_by' => null,
            'source_registration_id' => $registration->id,
        ];

        Log::info('Creating property from registration:', [
            'registration_id' => $registration->id,
            'is_vacant_land_only' => $isVacantLandOnly,
            'has_construction_details' => $hasConstructionDetails,
            'property_name' => $propertyData['property_name'],
            'status' => $propertyData['status'],
            'property_type_id' => $propertyData['property_type_id'],
            'construction_status' => $propertyData['construction_status'],
            'has_plans' => $propertyData['has_plans'],
            'bedrooms' => $propertyData['bedrooms'],
            'estimated_completion' => $propertyData['estimated_completion'],
        ]);

        $propertyRequest = new Request($propertyData);
        $propertyRequest->setUserResolver($request->getUserResolver());

        $result = $this->propertyRegistrationService->createProperty(
            $propertyRequest->all(),
            auth()->user()
        );

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Failed to create property from registration');
        }

        $property = $result['site_allocation'];

        // ============================================================
        // ✅ FIXED: Double-check and enforce all fields if needed
        // ============================================================
        if ($registration->isConstruction() && $hasConstructionDetails) {
            $needsUpdate = false;
            $updateData = [];
            
            // ✅ FIXED: Ensure property status is 'under_construction'
            if ($property->status !== Property::STATUS_UNDER_CONSTRUCTION) {
                Log::warning('Property status was not set correctly, correcting...', [
                    'property_id' => $property->id,
                    'current_status' => $property->status,
                    'expected_status' => Property::STATUS_UNDER_CONSTRUCTION,
                ]);
                $updateData['status'] = Property::STATUS_UNDER_CONSTRUCTION;
                $needsUpdate = true;
            }
            
            // ✅ FIXED: Ensure construction_status is set
            if (empty($property->construction_status) || $property->construction_status === 'vacant') {
                Log::warning('Property construction_status was not set correctly, correcting...', [
                    'property_id' => $property->id,
                    'current_construction_status' => $property->construction_status,
                    'expected_construction_status' => $registration->property_status ?? 'under_construction',
                ]);
                $updateData['construction_status'] = $registration->property_status ?? 'under_construction';
                $needsUpdate = true;
            }
            
            // ✅ FIXED: Ensure has_plans is set
            if (!$property->has_plans && ($registration->has_plans || $registration->property_type)) {
                Log::warning('Property has_plans was not set correctly, correcting...', [
                    'property_id' => $property->id,
                    'current_has_plans' => $property->has_plans,
                    'expected_has_plans' => $registration->has_plans ?? true,
                ]);
                $updateData['has_plans'] = $registration->has_plans ?? true;
                $needsUpdate = true;
            }
            
            // ✅ FIXED: Ensure estimated_completion is set if provided
            if ($registration->estimated_completion && empty($property->estimated_completion)) {
                Log::warning('Property estimated_completion was not set correctly, correcting...', [
                    'property_id' => $property->id,
                    'current_estimated_completion' => $property->estimated_completion,
                    'expected_estimated_completion' => $registration->estimated_completion,
                ]);
                $updateData['estimated_completion'] = $registration->estimated_completion;
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $property->update($updateData);
                Log::info('✅ Property fields corrected', [
                    'property_id' => $property->id,
                    'updates' => $updateData,
                ]);
            }
            
        } elseif ($registration->isConstruction() && !$hasConstructionDetails) {
            // Vacant land with no construction details
            $needsUpdate = false;
            $updateData = [];
            
            if ($property->status !== Property::STATUS_VACANT) {
                Log::warning('Property status was not set correctly, correcting to vacant...', [
                    'property_id' => $property->id,
                    'current_status' => $property->status,
                    'expected_status' => Property::STATUS_VACANT,
                ]);
                $updateData['status'] = Property::STATUS_VACANT;
                $needsUpdate = true;
            }
            
            if (!empty($property->construction_status) && $property->construction_status !== 'vacant') {
                Log::warning('Property construction_status should be vacant, correcting...', [
                    'property_id' => $property->id,
                    'current_construction_status' => $property->construction_status,
                    'expected_construction_status' => 'vacant',
                ]);
                $updateData['construction_status'] = 'vacant';
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $property->update($updateData);
                Log::info('✅ Property fields corrected to vacant', [
                    'property_id' => $property->id,
                    'updates' => $updateData,
                ]);
            }
        }

        if ($registration->has_tenants && $registration->tenants()->count() > 0) {
            $tenantCount = 0;
            $activeTenantCount = 0;
            
            foreach ($registration->tenants as $tenant) {
                $tenant->property_id = $property->id;
                $tenant->save();
                
                $tenantCount++;
                if ($tenant->status === 'active' || $tenant->status === 'approved') {
                    $activeTenantCount++;
                }
                
                Log::info('Tenant linked to property', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'property_id' => $property->id,
                    'property_name' => $property->property_name
                ]);
            }
            
            $property->tenant_count = $tenantCount;
            $property->active_tenant_count = $activeTenantCount;
            $property->save();
            
            Log::info('Property tenant counts updated', [
                'property_id' => $property->id,
                'tenant_count' => $tenantCount,
                'active_tenant_count' => $activeTenantCount
            ]);
        }

        // ✅ FIXED: Log final property state
        Log::info('✅ Property creation completed', [
            'property_id' => $property->id,
            'property_name' => $property->property_name,
            'status' => $property->status,
            'construction_status' => $property->construction_status,
            'has_plans' => $property->has_plans,
            'estimated_completion' => $property->estimated_completion,
            'property_type_id' => $property->property_type_id,
            'is_vacant_land_only' => $isVacantLandOnly,
            'has_construction_details' => $hasConstructionDetails,
        ]);

        $registration->logActivity('property_created', 
            "Property created with ID: {$property->id} with status '{$property->status}' and {$property->tenant_count} tenants linked" .
            ($isVacantLandOnly ? " (Vacant land - no construction details)" : 
             ($hasConstructionDetails ? " (With construction details - status: {$property->status}, construction_status: {$property->construction_status})" : ""))
        );

        DB::commit();

        return $property;

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to create property and link tenants: ' . $e->getMessage(), [
            'registration_id' => $registration->id,
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    /**
     * Determine the correct property status based on registration type
     */
    private function determinePropertyStatus(LandlordConstructionRegistration $registration): string
    {
        if ($registration->isPropertyCapture()) {
            switch ($registration->existing_property_status) {
                case 'active':
                case 'occupied':
                    return Property::STATUS_ACTIVE;
                case 'under_maintenance':
                    return Property::STATUS_UNDER_MAINTENANCE;
                case 'inactive':
                    return Property::STATUS_INACTIVE;
                case 'vacant':
                    return Property::STATUS_VACANT;
                default:
                    Log::info('Existing property with unknown status, defaulting to active', [
                        'registration_id' => $registration->id,
                        'existing_status' => $registration->existing_property_status
                    ]);
                    return Property::STATUS_ACTIVE;
            }
        }
        
        if ($registration->isConstruction()) {
            switch ($registration->property_status) {
                case 'under_construction':
                    return Property::STATUS_UNDER_CONSTRUCTION;
                case 'completed':
                case 'active':
                    return Property::STATUS_ACTIVE;
                case 'inactive':
                    return Property::STATUS_INACTIVE;
                case 'vacant':
                default:
                    return Property::STATUS_VACANT;
            }
        }
        
        if ($registration->purpose === LandlordConstructionRegistration::PURPOSE_BOTH) {
            if ($registration->property_status === 'under_construction') {
                return Property::STATUS_UNDER_CONSTRUCTION;
            } elseif ($registration->property_status === 'completed' || $registration->property_status === 'active') {
                return Property::STATUS_ACTIVE;
            } else {
                return Property::STATUS_VACANT;
            }
        }
        
        Log::warning('Could not determine property status, defaulting to vacant', [
            'registration_id' => $registration->id,
            'registration_type' => $registration->registration_type,
            'purpose' => $registration->purpose
        ]);
        return Property::STATUS_VACANT;
    }

    /**
     * Generate a unique registration pattern for the property
     */
    private function generateRegistrationPattern(LandlordConstructionRegistration $registration, Request $request): string
    {
        $plan = RegistrationPlan::find($request->registration_plan_id);
        $propertyCount = Property::where('registration_plan_id', $request->registration_plan_id)->count();
        $sequence = str_pad($propertyCount + 1, 4, '0', STR_PAD_LEFT);
        $zoneCode = substr($request->zone, 0, 3);
        $sectionCode = $request->section ? substr($request->section, 0, 3) : 'GEN';
        
        return strtoupper($zoneCode) . '-' . strtoupper($sectionCode) . '-' . $sequence;
    }

    /**
     * Update tenant counts for a property
     */
    private function updatePropertyTenantCounts(Property $property)
    {
        $property->tenant_count = $property->tenants()->count();
        $property->active_tenant_count = $property->tenants()
            ->whereIn('status', ['active', 'approved'])
            ->count();
        $property->save();
        
        Log::info('Property tenant counts updated', [
            'property_id' => $property->id,
            'tenant_count' => $property->tenant_count,
            'active_tenant_count' => $property->active_tenant_count
        ]);
    }

    /**
     * Send landlord invitation for approved property
     */
    private function sendLandlordInvitation(Property $property, Request $request)
    {
        try {
            $landlord = $property->landlord;
            
            if (!$landlord) {
                Log::error('Cannot send landlord invitation: No landlord found for property', [
                    'property_id' => $property->id
                ]);
                return [
                    'success' => false,
                    'message' => 'No landlord found for this property'
                ];
            }
            
            $invitationData = [
                'invitation_channels' => $request->invitation_channels ?? ['email'],
                'custom_message' => $request->custom_message ?? "Your property has been successfully registered. Please use the following link to access your property dashboard.",
                'expires_in_days' => 7
            ];
            
            $validationErrors = $this->landlordInvitationService->validateInvitationData($invitationData);
            if (!empty($validationErrors)) {
                Log::warning('Landlord invitation validation failed', [
                    'errors' => $validationErrors,
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid invitation data: ' . implode(', ', $validationErrors)
                ];
            }
            
            $canReceive = $this->landlordInvitationService->canReceiveInvitation($landlord);
            if (!$canReceive['can_receive']) {
                Log::warning('Landlord cannot receive invitations', [
                    'landlord_id' => $landlord->id,
                    'property_id' => $property->id,
                    'available_channels' => $canReceive['available_channels']
                ]);
                return [
                    'success' => false,
                    'message' => 'Landlord has no available communication channels',
                    'details' => $canReceive
                ];
            }
            
            if (in_array('sms', $invitationData['invitation_channels'])) {
                $smsPrecheck = $this->landlordInvitationService->precheckSmsInvitation($landlord);
                if (!$smsPrecheck['can_send']) {
                    Log::warning('SMS pre-check failed for landlord invitation', [
                        'landlord_id' => $landlord->id,
                        'precheck_result' => $smsPrecheck
                    ]);
                    $invitationData['invitation_channels'] = array_diff($invitationData['invitation_channels'], ['sms']);
                    
                    if (empty($invitationData['invitation_channels'])) {
                        return [
                            'success' => false,
                            'message' => 'SMS is not available and no other channels selected',
                            'precheck' => $smsPrecheck
                        ];
                    }
                }
            }
            
            $result = $this->landlordInvitationService->sendInvitation(
                $landlord,
                $property,
                $invitationData
            );
            
            if ($result['success']) {
                Log::info('Landlord invitation sent successfully via service', [
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'invitation_id' => $result['invitation_id'] ?? null,
                    'channels_successful' => $result['channels_successful'] ?? [],
                    'token' => $result['token'] ?? null
                ]);
                
                if (method_exists($property, 'logActivity')) {
                    $property->logActivity('invitation_sent', 
                        "Invitation sent to landlord via " . implode(', ', $result['channels_successful'] ?? [])
                    );
                }
            } else {
                Log::warning('Failed to send landlord invitation via service', [
                    'property_id' => $property->id,
                    'landlord_id' => $landlord->id,
                    'result' => $result
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Exception in sendLandlordInvitation: ' . $e->getMessage(), [
                'property_id' => $property->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send landlord invitation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get custom property type ID
     */
    private function getCustomTypeId()
    {
        $customType = PropertyType::where('slug', 'custom')->first();
        return $customType ? $customType->id : null;
    }

    /**
     * Check if registration can be approved
     */
    private function canApproveRegistration(LandlordConstructionRegistration $registration): array
    {
        $checks = [
            'has_land_ownership_document' => !empty($registration->land_ownership_document),
            'has_landlord_account' => !empty($registration->landlord_id),
            'has_plot_number' => !empty($registration->plot_number),
            'has_property_name' => !empty($registration->property_name),
            'has_contact_info' => !empty($registration->primary_phone) || !empty($registration->email),
        ];
        
        if ($registration->isConstruction()) {
            $checks['has_property_type'] = !empty($registration->property_type);
            $checks['has_property_status'] = !empty($registration->property_status);
        }
        
        if ($registration->isPropertyCapture()) {
            $checks['has_existing_property_type'] = !empty($registration->existing_property_type);
            $checks['has_existing_property_status'] = !empty($registration->existing_property_status);
        }
        
        $allPassed = !in_array(false, $checks, true);
        $missingItems = array_keys(array_filter($checks, function($value) {
            return $value === false;
        }));
        
        return [
            'can_approve' => $allPassed,
            'checks' => $checks,
            'missing_items' => $missingItems,
            'message' => $allPassed ? 'Registration is ready for approval' : 'Missing required information: ' . implode(', ', str_replace('_', ' ', $missingItems))
        ];
    }

    /**
     * Get formatted channels for landlord communication
     */
    private function getFormattedChannels(LandlordConstructionRegistration $registration): array
    {
        $channels = [];
        
        if ($registration->landlord) {
            $landlord = $registration->landlord;
            
            if ($landlord->email) {
                $channels[] = [
                    'type' => 'email',
                    'value' => $landlord->email,
                    'icon' => 'fa-envelope',
                    'color' => 'warning',
                    'available' => true
                ];
            }
            
            if ($landlord->phone) {
                $channels[] = [
                    'type' => 'sms',
                    'value' => $landlord->phone,
                    'icon' => 'fa-phone',
                    'color' => 'primary',
                    'available' => true
                ];
            }
            
            if ($landlord->whatsapp_number) {
                $channels[] = [
                    'type' => 'whatsapp',
                    'value' => $landlord->whatsapp_number,
                    'icon' => 'fa-whatsapp',
                    'color' => 'success',
                    'available' => true
                ];
            }
        } else {
            if ($registration->email) {
                $channels[] = [
                    'type' => 'email',
                    'value' => $registration->email,
                    'icon' => 'fa-envelope',
                    'color' => 'warning',
                    'available' => true,
                    'pending' => true,
                    'note' => 'Landlord account will be created'
                ];
            }
            
            if ($registration->primary_phone) {
                $channels[] = [
                    'type' => 'sms',
                    'value' => $registration->primary_phone,
                    'icon' => 'fa-phone',
                    'color' => 'primary',
                    'available' => true,
                    'pending' => true,
                    'note' => 'Landlord account will be created'
                ];
            }
        }
        
        return $channels;
    }

   /**
 * Get archived registrations
 * ✅ FIXED: Uses cleanJsonResponse() for proper JSON response
 */
public function getArchivedRegistrations(Request $request)
{
    $query = LandlordConstructionRegistration::where('is_archived', true)
        ->with(['reviewer', 'approvedProperty', 'archivedBy']);
    
    // Filter by year
    if ($request->filled('year')) {
        $query->where('archive_year', $request->year);
    }
    
    // Filter by status
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }
    
    // Filter by registration type
    if ($request->filled('registration_type')) {
        $query->where('registration_type', $request->registration_type);
    }
    
    // Search
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('property_name', 'like', "%{$search}%")
              ->orWhere('plot_number', 'like', "%{$search}%")
              ->orWhere('primary_phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }
    
    $registrations = $query->orderBy('archived_at', 'desc')
        ->paginate(20)
        ->withQueryString();
    
    // Get archive years for filter
    $archiveYears = LandlordConstructionRegistration::where('is_archived', true)
        ->distinct()
        ->pluck('archive_year')
        ->sortDesc()
        ->values();
    
    // Get counts by year
    $yearlyCounts = LandlordConstructionRegistration::where('is_archived', true)
        ->select('archive_year', DB::raw('count(*) as total'))
        ->groupBy('archive_year')
        ->orderBy('archive_year', 'desc')
        ->get()
        ->pluck('total', 'archive_year');
    
    // Get counts by status for stats
    $statusCounts = LandlordConstructionRegistration::where('is_archived', true)
        ->select('status', DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get()
        ->pluck('count', 'status');
    
    // Get counts by type for stats
    $typeCounts = LandlordConstructionRegistration::where('is_archived', true)
        ->select('registration_type', DB::raw('count(*) as count'))
        ->groupBy('registration_type')
        ->get()
        ->pluck('count', 'registration_type');
    
    if ($request->expectsJson()) {
        return $this->cleanJsonResponse([
            'success' => true,
            'data' => $registrations->items(),
            'registrations' => $registrations,
            'pagination' => [
                'current_page' => $registrations->currentPage(),
                'last_page' => $registrations->lastPage(),
                'per_page' => $registrations->perPage(),
                'total' => $registrations->total(),
                'from' => $registrations->firstItem(),
                'to' => $registrations->lastItem(),
            ],
            'archive_years' => $archiveYears,
            'yearly_counts' => $yearlyCounts,
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts
        ]);
    }
    
    return view('admin.construction-registrations.archived', compact(
        'registrations', 
        'archiveYears', 
        'yearlyCounts', 
        'statusCounts', 
        'typeCounts'
    ));
}

/**
 * Restore archived registrations
 * ✅ FIXED: Uses cleanJsonResponse() for proper JSON response
 */
public function restoreArchived(Request $request, $id = null)
{
    if ($id) {
        // Restore single registration
        $registration = LandlordConstructionRegistration::where('is_archived', true)
            ->where('id', $id)
            ->firstOrFail();
        
        DB::beginTransaction();
        
        try {
            $registration->update([
                'is_archived' => false,
                'archived_at' => null,
                'archive_reason' => null,
                'archived_by' => null,
            ]);
            
            DB::commit();
            
            Log::info("Registration restored from archive", [
                'registration_id' => $registration->id,
                'restored_by' => auth()->id(),
                'restored_by_name' => auth()->user()->name
            ]);
            
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => 'Registration restored successfully'
                ]);
            }
            
            return redirect()->route('admin.construction-registrations.archived')
                ->with('success', 'Registration restored successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to restore registration from archive', [
                'registration_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to restore registration: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to restore registration: ' . $e->getMessage());
        }
    } else {
        // Bulk restore by year or IDs
        $validator = Validator::make($request->all(), [
            'year' => 'nullable|integer',
            'registration_ids' => 'nullable|array',
            'registration_ids.*' => 'integer|exists:landlord_construction_registrations,id'
        ]);
        
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator);
        }
        
        $query = LandlordConstructionRegistration::where('is_archived', true);
        
        if ($request->filled('registration_ids')) {
            $query->whereIn('id', $request->registration_ids);
        } elseif ($request->filled('year')) {
            $query->where('archive_year', $request->year);
        } else {
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Please specify either year or registration_ids'
                ], 422);
            }
            return redirect()->back()->with('error', 'Please specify either year or select registrations to restore');
        }
        
        $count = $query->count();
        
        if ($count === 0) {
            $message = 'No archived registrations found to restore';
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => $message
                ], 404);
            }
            return redirect()->back()->with('info', $message);
        }
        
        DB::beginTransaction();
        
        try {
            $query->update([
                'is_archived' => false,
                'archived_at' => null,
                'archive_reason' => null,
                'archived_by' => null,
            ]);
            
            DB::commit();
            
            $message = "Successfully restored {$count} registration(s)";
            
            Log::info("Bulk restore from archive", [
                'count' => $count,
                'year' => $request->year,
                'registration_ids' => $request->registration_ids,
                'restored_by' => auth()->id()
            ]);
            
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => true,
                    'message' => $message,
                    'count' => $count
                ]);
            }
            
            return redirect()->route('admin.construction-registrations.archived')
                ->with('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to bulk restore registrations from archive', [
                'error' => $e->getMessage(),
                'year' => $request->year,
                'registration_ids' => $request->registration_ids
            ]);
            
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Failed to restore registrations: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to restore registrations: ' . $e->getMessage());
        }
    }
}

/**
 * Get archive statistics
 */
public function getArchiveStats()
{
    try {
        $totalArchived = LandlordConstructionRegistration::where('is_archived', true)->count();
        
        $years = LandlordConstructionRegistration::where('is_archived', true)
            ->select('archive_year', DB::raw('count(*) as count'))
            ->groupBy('archive_year')
            ->orderBy('archive_year', 'desc')
            ->get();
        
        $byStatus = LandlordConstructionRegistration::where('is_archived', true)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        
        $byType = LandlordConstructionRegistration::where('is_archived', true)
            ->select('registration_type', DB::raw('count(*) as count'))
            ->groupBy('registration_type')
            ->get();
        
        $latestArchive = LandlordConstructionRegistration::where('is_archived', true)
            ->latest('archived_at')
            ->first();
        
        $oldestArchive = LandlordConstructionRegistration::where('is_archived', true)
            ->oldest('archived_at')
            ->first();
        
        // Calculate total archive size (estimate based on record count)
        $avgRecordSize = 2048; // Approximate size in bytes per record
        $estimatedSizeBytes = $totalArchived * $avgRecordSize;
        $estimatedSizeMB = round($estimatedSizeBytes / (1024 * 1024), 2);
        
        $stats = [
            'success' => true,
            'total_archived' => $totalArchived,
            'years' => $years,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'latest_archive' => $latestArchive ? [
                'id' => $latestArchive->id,
                'name' => $latestArchive->name,
                'property_name' => $latestArchive->property_name,
                'archived_at' => $latestArchive->archived_at ? $latestArchive->archived_at->format('Y-m-d H:i:s') : null,
                'archive_year' => $latestArchive->archive_year
            ] : null,
            'oldest_archive' => $oldestArchive ? [
                'id' => $oldestArchive->id,
                'name' => $oldestArchive->name,
                'property_name' => $oldestArchive->property_name,
                'archived_at' => $oldestArchive->archived_at ? $oldestArchive->archived_at->format('Y-m-d H:i:s') : null,
                'archive_year' => $oldestArchive->archive_year
            ] : null,
            'estimated_storage_mb' => $estimatedSizeMB,
            'archived_percentage' => $totalArchived > 0 ? round(($totalArchived / LandlordConstructionRegistration::count()) * 100, 2) : 0
        ];
        
        return response()->json($stats);
        
    } catch (\Exception $e) {
        Log::error('Failed to get archive stats: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve archive statistics: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Archive registrations by year or specific IDs
 * ✅ FIXED: Uses cleanJsonResponse() for proper JSON response
 */
public function archiveRegistrations(Request $request)
{
    $validator = Validator::make($request->all(), [
        'year' => 'required_without:registration_ids|integer|min:2000|max:' . (date('Y') + 1),
        'registration_ids' => 'required_without:year|array',
        'registration_ids.*' => 'exists:landlord_construction_registrations,id',
        'dry_run' => 'sometimes|boolean',
        'reason' => 'nullable|string|max:500',
    ]);

    if ($validator->fails()) {
        return $this->cleanJsonResponse([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $dryRun = $request->boolean('dry_run', false);
    
    try {
        $query = LandlordConstructionRegistration::where('is_archived', false);
        
        if ($request->filled('registration_ids')) {
            $query->whereIn('id', $request->registration_ids);
        } elseif ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        } else {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Please specify either year or registration IDs'
            ], 422);
        }
        
        $registrations = $query->get();
        
        if ($registrations->isEmpty()) {
            return $this->cleanJsonResponse([
                'success' => true,
                'count' => 0,
                'message' => 'No registrations found to archive'
            ]);
        }
        
        // For dry run, just return preview
        if ($dryRun) {
            $preview = $registrations->map(function($reg) {
                return [
                    'id' => $reg->id,
                    'name' => $reg->name,
                    'property_name' => $reg->property_name,
                    'type' => $reg->registration_type_label,
                    'status' => $reg->status_label,
                    'created_at' => $reg->created_at->format('Y-m-d')
                ];
            });
            
            return $this->cleanJsonResponse([
                'success' => true,
                'dry_run' => true,
                'count' => $registrations->count(),
                'preview' => $preview
            ]);
        }
        
        // Perform the archive
        DB::beginTransaction();
        
        $archivedCount = 0;
        $failedCount = 0;
        $failedIds = [];
        
        foreach ($registrations as $registration) {
            try {
                $registration->update([
                    'is_archived' => true,
                    'archived_at' => now(),
                    'archive_year' => $request->year ?? $registration->created_at->year,
                    'archived_by' => auth()->id(),
                    'archive_reason' => $request->reason ?? 'Bulk archive operation'
                ]);
                
                $registration->logActivity('archived', 
                    "Registration archived by " . auth()->user()->name . 
                    " - Year: " . ($request->year ?? $registration->created_at->year)
                );
                
                $archivedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $failedIds[] = $registration->id;
                Log::error('Failed to archive registration', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        DB::commit();
        
        Log::info('Registrations archived', [
            'count' => $archivedCount,
            'failed' => $failedCount,
            'year' => $request->year,
            'registration_ids' => $request->registration_ids,
            'archived_by' => auth()->id()
        ]);
        
        $message = "Successfully archived {$archivedCount} registration(s)";
        if ($failedCount > 0) {
            $message .= " with {$failedCount} failure(s)";
        }
        
        return $this->cleanJsonResponse([
            'success' => true,
            'count' => $archivedCount,
            'failed_count' => $failedCount,
            'failed_ids' => $failedIds,
            'message' => $message
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to archive registrations: ' . $e->getMessage(), [
            'year' => $request->year,
            'registration_ids' => $request->registration_ids,
            'trace' => $e->getTraceAsString()
        ]);
        
        return $this->cleanJsonResponse([
            'success' => false,
            'message' => 'Failed to archive registrations: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Export archived registrations to CSV, Excel, or PDF
 */
public function exportArchived(Request $request)
{
    $validator = Validator::make($request->all(), [
        'format' => 'required|in:csv,excel,pdf',
        'export_type' => 'required|in:filtered,selected,all',
        'fields' => 'nullable|string',
        'ids' => 'nullable|string',
        'search' => 'nullable|string',
        'year' => 'nullable|integer',
        'status' => 'nullable|string',
        'registration_type' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        return redirect()->back()->withErrors($validator);
    }

    $format = $request->format;
    $exportType = $request->export_type;
    $selectedFields = $request->filled('fields') ? explode(',', $request->fields) : ['applicant_info', 'property_details', 'contact_info', 'status_info', 'archive_metadata'];
    
    // Build query based on export type
    $query = ArchivedConstructionRegistration::query();
    
    if ($exportType === 'selected' && $request->filled('ids')) {
        $ids = explode(',', $request->ids);
        $query->whereIn('id', $ids);
    } elseif ($exportType === 'filtered') {
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('property_name', 'like', "%{$search}%")
                  ->orWhere('plot_number', 'like', "%{$search}%")
                  ->orWhere('primary_phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('year')) {
            $query->where('archive_year', $request->year);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('registration_type')) {
            $query->where('registration_type', $request->registration_type);
        }
    }
    // else 'all' - no additional filters
    
    $registrations = $query->orderBy('archived_at', 'desc')->get();
    
    if ($registrations->isEmpty()) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'No archived registrations found to export'
            ], 404);
        }
        return redirect()->back()->with('error', 'No archived registrations found to export');
    }
    
    // Generate filename
    $timestamp = now()->format('Y-m-d-His');
    $filename = "archived-registrations-{$timestamp}";
    
    // Handle different export formats
    switch ($format) {
        case 'csv':
            return $this->exportArchivedToCsv($registrations, $selectedFields, $filename);
        case 'excel':
            return $this->exportArchivedToExcel($registrations, $selectedFields, $filename);
        case 'pdf':
            return $this->exportArchivedToPdf($registrations, $selectedFields, $filename, $request);
        default:
            return $this->exportArchivedToCsv($registrations, $selectedFields, $filename);
    }
}

/**
 * Export archived registrations to CSV
 */
private function exportArchivedToCsv($registrations, array $selectedFields, string $filename)
{
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        'Pragma' => 'no-cache',
        'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        'Expires' => '0',
    ];

    $callback = function() use ($registrations, $selectedFields) {
        $file = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Build headers based on selected fields
        $headers = $this->buildCsvHeaders($selectedFields);
        fputcsv($file, $headers);
        
        // Add data rows
        foreach ($registrations as $registration) {
            $row = $this->buildCsvRow($registration, $selectedFields);
            fputcsv($file, $row);
        }
        
        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
}

/**
 * Build CSV headers based on selected fields
 */
private function buildCsvHeaders(array $selectedFields): array
{
    $headers = [];
    
    $fieldMap = [
        'applicant_info' => ['Applicant Name', 'Applicant ID'],
        'property_details' => ['Property Name', 'Plot Number', 'Street Name', 'Digital Address', 'Zone', 'Section', 'Property Type', 'Registration Type'],
        'contact_info' => ['Email', 'Primary Phone', 'Additional Phones'],
        'status_info' => ['Status', 'Status Label', 'Submitted At', 'Reviewed At', 'Reviewer'],
        'archive_metadata' => ['Archived At', 'Archive Year', 'Archived By', 'Archive Reason'],
        'tenant_info' => ['Has Tenants', 'Tenant Count'],
    ];
    
    foreach ($selectedFields as $field) {
        if (isset($fieldMap[$field])) {
            $headers = array_merge($headers, $fieldMap[$field]);
        }
    }
    
    return $headers;
}

/**
 * Build CSV row data
 */
private function buildCsvRow($registration, array $selectedFields): array
{
    $row = [];
    
    foreach ($selectedFields as $field) {
        switch ($field) {
            case 'applicant_info':
                $row[] = $registration->name;
                $row[] = $registration->id;
                break;
            case 'property_details':
                $row[] = $registration->property_name ?? 'N/A';
                $row[] = $registration->plot_number ?? 'N/A';
                $row[] = $registration->street_name ?? 'N/A';
                $row[] = $registration->digital_address ?? 'N/A';
                $row[] = $registration->zone ?? 'N/A';
                $row[] = $registration->section ?? 'N/A';
                $row[] = $registration->formatted_property_type ?? $registration->property_type ?? 'N/A';
                $row[] = $registration->registration_type_label ?? $registration->registration_type;
                break;
            case 'contact_info':
                $row[] = $registration->email ?? 'N/A';
                $row[] = $registration->primary_phone ?? 'N/A';
                $row[] = is_array($registration->additional_phones) ? implode(';', $registration->additional_phones) : 'N/A';
                break;
            case 'status_info':
                $row[] = $registration->status;
                $row[] = $registration->status_label ?? ucfirst($registration->status);
                $row[] = $registration->submitted_at?->format('Y-m-d H:i:s') ?? 'N/A';
                $row[] = $registration->reviewed_at?->format('Y-m-d H:i:s') ?? 'N/A';
                $row[] = $registration->reviewer?->name ?? 'N/A';
                break;
            case 'archive_metadata':
                $row[] = $registration->archived_at?->format('Y-m-d H:i:s') ?? 'N/A';
                $row[] = $registration->archive_year ?? 'N/A';
                $row[] = $registration->archivedBy?->name ?? 'System';
                $row[] = $registration->archive_reason ?? 'Year-end archiving';
                break;
            case 'tenant_info':
                $row[] = $registration->has_tenants ? 'Yes' : 'No';
                $row[] = $registration->tenant_count ?? 0;
                break;
        }
    }
    
    return $row;
}

/**
 * Export archived registrations to Excel (using Maatwebsite Excel)
 */
private function exportArchivedToExcel($registrations, array $selectedFields, string $filename)
{
    // Create a temporary view or use Excel facade
    $exportData = $registrations->map(function($registration) use ($selectedFields) {
        $row = [];
        
        foreach ($selectedFields as $field) {
            switch ($field) {
                case 'applicant_info':
                    $row['Applicant Name'] = $registration->name;
                    $row['Applicant ID'] = $registration->id;
                    break;
                case 'property_details':
                    $row['Property Name'] = $registration->property_name ?? 'N/A';
                    $row['Plot Number'] = $registration->plot_number ?? 'N/A';
                    $row['Street Name'] = $registration->street_name ?? 'N/A';
                    $row['Digital Address'] = $registration->digital_address ?? 'N/A';
                    $row['Zone'] = $registration->zone ?? 'N/A';
                    $row['Section'] = $registration->section ?? 'N/A';
                    $row['Property Type'] = $registration->formatted_property_type ?? $registration->property_type ?? 'N/A';
                    $row['Registration Type'] = $registration->registration_type_label ?? $registration->registration_type;
                    break;
                case 'contact_info':
                    $row['Email'] = $registration->email ?? 'N/A';
                    $row['Primary Phone'] = $registration->primary_phone ?? 'N/A';
                    $row['Additional Phones'] = is_array($registration->additional_phones) ? implode(';', $registration->additional_phones) : 'N/A';
                    break;
                case 'status_info':
                    $row['Status'] = $registration->status;
                    $row['Status Label'] = $registration->status_label ?? ucfirst($registration->status);
                    $row['Submitted At'] = $registration->submitted_at?->format('Y-m-d H:i:s') ?? 'N/A';
                    $row['Reviewed At'] = $registration->reviewed_at?->format('Y-m-d H:i:s') ?? 'N/A';
                    $row['Reviewer'] = $registration->reviewer?->name ?? 'N/A';
                    break;
                case 'archive_metadata':
                    $row['Archived At'] = $registration->archived_at?->format('Y-m-d H:i:s') ?? 'N/A';
                    $row['Archive Year'] = $registration->archive_year ?? 'N/A';
                    $row['Archived By'] = $registration->archivedBy?->name ?? 'System';
                    $row['Archive Reason'] = $registration->archive_reason ?? 'Year-end archiving';
                    break;
                case 'tenant_info':
                    $row['Has Tenants'] = $registration->has_tenants ? 'Yes' : 'No';
                    $row['Tenant Count'] = $registration->tenant_count ?? 0;
                    break;
            }
        }
        
        return $row;
    });
    
    return Excel::download(new class($exportData) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
        private $data;
        
        public function __construct($data)
        {
            $this->data = $data;
        }
        
        public function collection()
        {
            return collect($this->data);
        }
        
        public function headings(): array
        {
            if ($this->data->isEmpty()) {
                return [];
            }
            return array_keys($this->data->first());
        }
    }, "{$filename}.xlsx");
}

/**
 * Export archived registrations to PDF
 */
private function exportArchivedToPdf($registrations, array $selectedFields, string $filename, Request $request)
{
    $summary = [
        'total_records' => $registrations->count(),
        'export_date' => now()->format('Y-m-d H:i:s'),
        'exported_by' => auth()->user()->name,
        'date_range' => $request->filled('year') ? "Year: {$request->year}" : 'All Years',
        'status_filter' => $request->filled('status') ? ucfirst($request->status) : 'All',
        'type_filter' => $request->filled('registration_type') ? ucfirst(str_replace('_', ' ', $request->registration_type)) : 'All',
    ];
    
    // Calculate statistics
    $stats = [
        'by_status' => $registrations->groupBy('status')->map->count(),
        'by_year' => $registrations->groupBy('archive_year')->map->count(),
        'by_type' => $registrations->groupBy('registration_type')->map->count(),
        'total_with_tenants' => $registrations->where('has_tenants', true)->count(),
    ];
    
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.archived-registrations-pdf', [
        'registrations' => $registrations,
        'selectedFields' => $selectedFields,
        'summary' => $summary,
        'stats' => $stats,
        'generated_at' => now()->format('Y-m-d H:i:s')
    ]);
    
    $pdf->setPaper('A4', 'landscape');
    
    return $pdf->download("{$filename}.pdf");
}

/**
 * Permanently delete a single archived registration
 * ✅ FIXED: Uses cleanJsonResponse() for proper JSON response
 */
public function permanentDelete($id, Request $request)
{
    try {
        // First, check if this is an archived registration ID
        $registration = ArchivedConstructionRegistration::find($id);
        
        if (!$registration) {
            // If not found in archive, try to find in main table with is_archived flag
            $mainRegistration = LandlordConstructionRegistration::where('id', $id)
                ->where('is_archived', true)
                ->first();
            
            if ($mainRegistration) {
                // This is a registration that was archived using the is_archived flag
                // Move it to the archive table first, then delete
                DB::beginTransaction();
                
                try {
                    // Create archive record (simplified - use the same logic as before)
                    $archived = $this->moveToArchiveTable($mainRegistration);
                    
                    // Delete the archive record we just created (permanent delete)
                    $archived->delete();
                    
                    // Now delete the main registration
                    $mainRegistration->forceDelete();
                    
                    DB::commit();
                    
                    if ($request->expectsJson()) {
                        return $this->cleanJsonResponse([
                            'success' => true,
                            'message' => 'Registration permanently deleted'
                        ]);
                    }
                    
                    return redirect()->back()->with('success', 'Registration permanently deleted');
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
            
            // Not found in either table
            if ($request->expectsJson()) {
                return $this->cleanJsonResponse([
                    'success' => false,
                    'message' => 'Registration not found in archive'
                ], 404);
            }
            
            return redirect()->back()->with('error', 'Registration not found in archive');
        }
        
        // Found in archive table - proceed with permanent delete
        DB::beginTransaction();
        
        Log::info("Permanently deleting archived registration", [
            'registration_id' => $id,
            'name' => $registration->name,
            'property_name' => $registration->property_name,
            'deleted_by' => auth()->id(),
            'archive_year' => $registration->archive_year
        ]);
        
        $registration->delete(); // Permanent delete since this is the archive table
        
        DB::commit();
        
        if ($request->expectsJson()) {
            return $this->cleanJsonResponse([
                'success' => true,
                'message' => 'Registration permanently deleted'
            ]);
        }
        
        return redirect()->back()->with('success', 'Registration permanently deleted');
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Failed to permanently delete archived registration: " . $e->getMessage(), [
            'id' => $id,
            'trace' => $e->getTraceAsString()
        ]);
        
        if ($request->expectsJson()) {
            return $this->cleanJsonResponse([
                'success' => false,
                'message' => 'Failed to delete registration: ' . $e->getMessage()
            ], 500);
        }
        
        return redirect()->back()->with('error', 'Failed to delete registration: ' . $e->getMessage());
    }
}

/**
 * Permanently delete multiple archived registrations (bulk)
 */
public function permanentDeleteBulk(Request $request)
{
    $validator = Validator::make($request->all(), [
        'year' => 'required_without:registration_ids|integer',
        'registration_ids' => 'required_without:year|array',
        'registration_ids.*' => 'integer',
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }
    
    DB::beginTransaction();
    
    try {
        $deletedCount = 0;
        $notFoundCount = 0;
        
        if ($request->filled('registration_ids')) {
            foreach ($request->registration_ids as $id) {
                // Try to find in archive table first
                $registration = ArchivedConstructionRegistration::find($id);
                
                if (!$registration) {
                    // Try to find in main table with is_archived flag
                    $mainRegistration = LandlordConstructionRegistration::where('id', $id)
                        ->where('is_archived', true)
                        ->first();
                    
                    if ($mainRegistration) {
                        // Move to archive then delete
                        $archived = ArchivedConstructionRegistration::create([
                            'registration_type' => $mainRegistration->registration_type,
                            'purpose' => $mainRegistration->purpose,
                            'name' => $mainRegistration->name,
                            'email' => $mainRegistration->email,
                            'primary_phone' => $mainRegistration->primary_phone,
                            'additional_phones' => $mainRegistration->additional_phones,
                            'landlord_id' => $mainRegistration->landlord_id,
                            'property_name' => $mainRegistration->property_name,
                            'plot_number' => $mainRegistration->plot_number,
                            'street_name' => $mainRegistration->street_name,
                            'digital_address' => $mainRegistration->digital_address,
                            'land_description' => $mainRegistration->land_description,
                            'land_ownership_document' => $mainRegistration->land_ownership_document,
                            'zone' => $mainRegistration->zone,
                            'section' => $mainRegistration->section,
                            'property_type' => $mainRegistration->property_type,
                            'custom_property_type' => $mainRegistration->custom_property_type,
                            'property_status' => $mainRegistration->property_status,
                            'estimated_bedrooms' => $mainRegistration->estimated_bedrooms,
                            'has_plans' => $mainRegistration->has_plans,
                            'estimated_completion' => $mainRegistration->estimated_completion,
                            'construction_documents' => $mainRegistration->construction_documents,
                            'existing_property_type' => $mainRegistration->existing_property_type,
                            'existing_custom_property_type' => $mainRegistration->existing_custom_property_type,
                            'existing_property_status' => $mainRegistration->existing_property_status,
                            'existing_bedrooms' => $mainRegistration->existing_bedrooms,
                            'existing_bathrooms' => $mainRegistration->existing_bathrooms,
                            'year_built' => $mainRegistration->year_built,
                            'property_photos' => $mainRegistration->property_photos,
                            'property_documents' => $mainRegistration->property_documents,
                            'has_tenants' => $mainRegistration->has_tenants,
                            'tenant_count' => $mainRegistration->tenant_count,
                            'tenant_data' => $mainRegistration->tenant_data,
                            'status' => $mainRegistration->status,
                            'access_token' => $mainRegistration->access_token,
                            'submitted_at' => $mainRegistration->submitted_at,
                            'reviewed_at' => $mainRegistration->reviewed_at,
                            'reviewed_by' => $mainRegistration->reviewed_by,
                            'approved_property_id' => $mainRegistration->approved_property_id,
                            'cancelled_at' => $mainRegistration->cancelled_at,
                            'rejection_reason' => $mainRegistration->rejection_reason,
                            'admin_notes' => $mainRegistration->admin_notes,
                            'info_requested' => $mainRegistration->info_requested,
                            'assigned_to' => $mainRegistration->assigned_to,
                            'assigned_at' => $mainRegistration->assigned_at,
                            'archived_at' => $mainRegistration->archived_at ?? now(),
                            'archive_year' => $mainRegistration->archive_year ?? now()->year,
                            'archive_reason' => $mainRegistration->archive_reason ?? 'Permanent deletion',
                            'archived_by' => auth()->id(),
                            'original_id' => $mainRegistration->id,
                            'original_created_at' => $mainRegistration->created_at,
                            'original_updated_at' => $mainRegistration->updated_at,
                            'original_deleted_at' => $mainRegistration->deleted_at,
                        ]);
                        
                        $archived->delete();
                        $mainRegistration->forceDelete();
                        $deletedCount++;
                    } else {
                        $notFoundCount++;
                        Log::warning("Registration not found for permanent delete", ['id' => $id]);
                    }
                } else {
                    // Direct delete from archive table
                    $registration->delete();
                    $deletedCount++;
                }
            }
        } elseif ($request->filled('year')) {
            // Delete by year from archive table
            $deletedCount = ArchivedConstructionRegistration::where('archive_year', $request->year)->delete();
            
            // Also handle main table registrations with is_archived flag for that year
            $mainRegistrations = LandlordConstructionRegistration::where('is_archived', true)
                ->where('archive_year', $request->year)
                ->get();
            
            foreach ($mainRegistrations as $mainReg) {
                // Move to archive then delete
                $archived = ArchivedConstructionRegistration::create([
                    'registration_type' => $mainReg->registration_type,
                    'purpose' => $mainReg->purpose,
                    'name' => $mainReg->name,
                    'email' => $mainReg->email,
                    'primary_phone' => $mainReg->primary_phone,
                    'additional_phones' => $mainReg->additional_phones,
                    'landlord_id' => $mainReg->landlord_id,
                    'property_name' => $mainReg->property_name,
                    'plot_number' => $mainReg->plot_number,
                    'street_name' => $mainReg->street_name,
                    'digital_address' => $mainReg->digital_address,
                    'land_description' => $mainReg->land_description,
                    'land_ownership_document' => $mainReg->land_ownership_document,
                    'zone' => $mainReg->zone,
                    'section' => $mainReg->section,
                    'property_type' => $mainReg->property_type,
                    'custom_property_type' => $mainReg->custom_property_type,
                    'property_status' => $mainReg->property_status,
                    'estimated_bedrooms' => $mainReg->estimated_bedrooms,
                    'has_plans' => $mainReg->has_plans,
                    'estimated_completion' => $mainReg->estimated_completion,
                    'construction_documents' => $mainReg->construction_documents,
                    'existing_property_type' => $mainReg->existing_property_type,
                    'existing_custom_property_type' => $mainReg->existing_custom_property_type,
                    'existing_property_status' => $mainReg->existing_property_status,
                    'existing_bedrooms' => $mainReg->existing_bedrooms,
                    'existing_bathrooms' => $mainReg->existing_bathrooms,
                    'year_built' => $mainReg->year_built,
                    'property_photos' => $mainReg->property_photos,
                    'property_documents' => $mainReg->property_documents,
                    'has_tenants' => $mainReg->has_tenants,
                    'tenant_count' => $mainReg->tenant_count,
                    'tenant_data' => $mainReg->tenant_data,
                    'status' => $mainReg->status,
                    'access_token' => $mainReg->access_token,
                    'submitted_at' => $mainReg->submitted_at,
                    'reviewed_at' => $mainReg->reviewed_at,
                    'reviewed_by' => $mainReg->reviewed_by,
                    'approved_property_id' => $mainReg->approved_property_id,
                    'cancelled_at' => $mainReg->cancelled_at,
                    'rejection_reason' => $mainReg->rejection_reason,
                    'admin_notes' => $mainReg->admin_notes,
                    'info_requested' => $mainReg->info_requested,
                    'assigned_to' => $mainReg->assigned_to,
                    'assigned_at' => $mainReg->assigned_at,
                    'archived_at' => $mainReg->archived_at ?? now(),
                    'archive_year' => $mainReg->archive_year ?? $request->year,
                    'archive_reason' => $mainReg->archive_reason ?? 'Permanent deletion',
                    'archived_by' => auth()->id(),
                    'original_id' => $mainReg->id,
                    'original_created_at' => $mainReg->created_at,
                    'original_updated_at' => $mainReg->updated_at,
                    'original_deleted_at' => $mainReg->deleted_at,
                ]);
                
                $archived->delete();
                $mainReg->forceDelete();
                $deletedCount++;
            }
        }
        
        DB::commit();
        
        $message = "Successfully deleted {$deletedCount} registration(s) permanently";
        if ($notFoundCount > 0) {
            $message .= " ({$notFoundCount} not found)";
        }
        
        Log::info("Bulk permanent delete completed", [
            'deleted_count' => $deletedCount,
            'not_found_count' => $notFoundCount,
            'year' => $request->year,
            'registration_ids' => $request->registration_ids,
            'deleted_by' => auth()->id()
        ]);
        
        return response()->json([
            'success' => true,
            'count' => $deletedCount,
            'not_found' => $notFoundCount,
            'message' => $message
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Failed to bulk delete archived registrations: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete registrations: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Bulk approve registrations
 */
public function bulkApprove(Request $request)
{
    $validator = Validator::make($request->all(), [
        'registration_ids' => 'required|array',
        'registration_ids.*' => 'exists:landlord_construction_registrations,id',
        'registration_plan_id' => 'required|exists:registration_plans,id',
        'zone' => 'nullable|string|max:100',
        'section' => 'nullable|string|max:100',
        'landlord_id' => 'required|exists:users,id',
        'property_type_id' => 'nullable|exists:property_types,id',
        'custom_property_type' => 'nullable|string|max:100',
        'send_invitation' => 'sometimes|boolean',
        'invitation_channels' => 'sometimes|array',
        'auto_approve_tenants' => 'sometimes|boolean',
        'send_tenant_invitations' => 'sometimes|boolean',
        'tenant_invitation_channels' => 'sometimes|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        $registrations = LandlordConstructionRegistration::whereIn('id', $request->registration_ids)
            ->where('status', '!=', LandlordConstructionRegistration::STATUS_APPROVED)
            ->get();

        if ($registrations->isEmpty()) {
            throw new \Exception('No registrations found to approve');
        }

        // Check plan availability
        $plan = RegistrationPlan::withCount('properties')->find($request->registration_plan_id);
        $availableSpots = $plan->estimated_houses - $plan->properties_count;

        if ($availableSpots < $registrations->count()) {
            throw new \Exception("Selected plan only has {$availableSpots} spots available, but you selected {$registrations->count()} registrations.");
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        $createdProperties = [];
        $autoApprovedTenants = [];
        $invitationResults = [];

        foreach ($registrations as $registration) {
            try {
                // ============================================================
                // FIX: Determine if this is vacant land WITHOUT construction details
                // ============================================================
                $isVacantLandOnly = $registration->isConstruction() && 
                                    !$registration->property_type && 
                                    !$registration->construction_documents;

                // Prepare base property data
                $propertyData = [
                    'registration_plan_id' => $request->registration_plan_id,
                    'landlord_id' => $request->landlord_id,
                    'created_by' => auth()->id(),
                    'property_name' => $registration->property_name,
                    'house_number' => $registration->plot_number,
                    'street_name' => $registration->street_name,
                    'block_number' => $registration->plot_number,
                    'digital_address' => $registration->digital_address,
                    'zone' => $request->zone ?? $registration->zone,
                    'section' => $request->section ?? $registration->section,
                    'description' => $registration->land_description,
                    'is_rented' => $registration->has_tenants ?? false,
                    'source_registration_id' => $registration->id,
                ];

                // ============================================================
                // FIX: Handle property type based on registration type and vacant land status
                // ============================================================
                if ($isVacantLandOnly) {
                    // Vacant land with NO construction details - set everything to NULL
                    $propertyData['property_type_id'] = null;
                    $propertyData['custom_property_type'] = null;
                    $propertyData['status'] = Property::STATUS_VACANT;
                    $propertyData['construction_status'] = 'vacant';
                    $propertyData['bedrooms'] = null;
                    $propertyData['bathrooms'] = null;
                    $propertyData['estimated_completion'] = null;
                    $propertyData['year_built'] = null;
                    
                    Log::info('Bulk approve - Vacant land WITHOUT construction details', [
                        'registration_id' => $registration->id,
                        'property_name' => $registration->property_name
                    ]);
                    
                } elseif ($registration->isConstruction()) {
                    // Construction with details - property type required
                    if (!$request->property_type_id && !$request->custom_property_type) {
                        // Try to use the property type from registration
                        $propertyType = $this->findPropertyTypeFromRegistration($registration);
                        $propertyData['property_type_id'] = $propertyType ? $propertyType->id : null;
                        $propertyData['custom_property_type'] = $propertyType && $propertyType->slug === 'custom' 
                            ? $registration->custom_property_type 
                            : null;
                    } else {
                        $propertyData['property_type_id'] = $request->property_type_id;
                        $propertyData['custom_property_type'] = $request->custom_property_type;
                    }
                    
                    $propertyData['status'] = $this->determinePropertyStatus($registration);
                    $propertyData['construction_status'] = $registration->property_status ?? 'under_construction';
                    $propertyData['estimated_completion'] = $registration->estimated_completion;
                    $propertyData['bedrooms'] = $registration->estimated_bedrooms;
                    $propertyData['bathrooms'] = null;
                    $propertyData['year_built'] = null;
                    
                } elseif ($registration->isPropertyCapture()) {
                    // Property capture - use existing property details
                    if (!$request->property_type_id && !$request->custom_property_type) {
                        $propertyType = $this->findPropertyTypeFromRegistration($registration);
                        $propertyData['property_type_id'] = $propertyType ? $propertyType->id : null;
                        $propertyData['custom_property_type'] = $propertyType && $propertyType->slug === 'custom' 
                            ? $registration->existing_custom_property_type 
                            : null;
                    } else {
                        $propertyData['property_type_id'] = $request->property_type_id;
                        $propertyData['custom_property_type'] = $request->custom_property_type;
                    }
                    
                    $propertyData['status'] = $this->determinePropertyStatus($registration);
                    $propertyData['bedrooms'] = $registration->existing_bedrooms;
                    $propertyData['bathrooms'] = $registration->existing_bathrooms;
                    $propertyData['year_built'] = $registration->year_built;
                    $propertyData['construction_status'] = null;
                    $propertyData['estimated_completion'] = null;
                }

                // Log the property data being created
                Log::info('Bulk approve - Creating property with data:', [
                    'registration_id' => $registration->id,
                    'is_vacant_land_only' => $isVacantLandOnly ?? false,
                    'property_name' => $propertyData['property_name'],
                    'status' => $propertyData['status'],
                    'property_type_id' => $propertyData['property_type_id'],
                    'construction_status' => $propertyData['construction_status'] ?? 'N/A',
                ]);

                // Create property
                $property = Property::create($propertyData);
                $createdProperties[] = [
                    'id' => $property->id,
                    'name' => $property->property_name,
                    'registration_id' => $registration->id
                ];

                // Update registration
                $registration->status = LandlordConstructionRegistration::STATUS_APPROVED;
                $registration->approved_property_id = $property->id;
                $registration->reviewed_at = now();
                $registration->reviewed_by = auth()->id();
                $registration->zone = $request->zone ?? $registration->zone;
                $registration->section = $request->section ?? $registration->section;
                $registration->save();

                // Handle tenants
                $tenantCount = 0;
                $activeTenantCount = 0;

                if ($registration->has_tenants && $registration->tenants()->count() > 0) {
                    $autoApprove = $request->boolean('auto_approve_tenants', true);
                    
                    foreach ($registration->tenants as $tenant) {
                        $tenant->property_id = $property->id;
                        
                        if ($autoApprove && $tenant->status === 'pending') {
                            $tenant->status = 'approved';
                            $tenant->approved_by = auth()->id();
                            $tenant->approved_at = now();
                            $autoApprovedTenants[] = [
                                'id' => $tenant->id,
                                'name' => $tenant->name,
                                'registration_id' => $registration->id
                            ];
                        }
                        
                        $tenant->save();
                        $tenantCount++;
                        
                        if ($tenant->status === 'approved' || $tenant->status === 'active') {
                            $activeTenantCount++;
                        }
                    }
                    
                    $property->tenant_count = $tenantCount;
                    $property->active_tenant_count = $activeTenantCount;
                    $property->save();
                }

                $successCount++;

                Log::info('Registration bulk approved', [
                    'registration_id' => $registration->id,
                    'property_id' => $property->id,
                    'landlord_id' => $request->landlord_id,
                    'approved_by' => auth()->id(),
                    'is_vacant_land_only' => $isVacantLandOnly ?? false
                ]);

            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Registration #{$registration->id} ({$registration->name}): " . $e->getMessage();
                Log::error('Bulk approval failed for registration', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send landlord invitations if requested
        if ($request->boolean('send_invitation') && $successCount > 0) {
            foreach ($createdProperties as $propertyData) {
                $property = Property::find($propertyData['id']);
                if ($property && $property->landlord) {
                    try {
                        $invitationResult = $this->sendBulkLandlordInvitation($property, $request);
                        $invitationResults[] = [
                            'property_id' => $property->id,
                            'success' => $invitationResult['success'],
                            'channels' => $invitationResult['channels_sent'] ?? []
                        ];
                    } catch (\Exception $e) {
                        Log::error('Failed to send landlord invitation for bulk approval', [
                            'property_id' => $property->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        // Send tenant invitations if requested
        if ($request->boolean('send_tenant_invitations') && $successCount > 0) {
            foreach ($registrations as $registration) {
                if ($registration->tenants()->where('status', 'approved')->count() > 0) {
                    try {
                        $this->sendBulkTenantInvitations($registration, $request);
                    } catch (\Exception $e) {
                        Log::error('Failed to send tenant invitations for bulk approval', [
                            'registration_id' => $registration->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        DB::commit();

        $message = "Successfully approved {$successCount} registration(s)";
        if ($failedCount > 0) {
            $message .= " with {$failedCount} failure(s)";
        }
        if (count($autoApprovedTenants) > 0) {
            $message .= ". " . count($autoApprovedTenants) . " tenant(s) auto-approved.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'created_properties' => $createdProperties,
            'auto_approved_tenants' => $autoApprovedTenants,
            'invitation_results' => $invitationResults,
            'total_approved' => $successCount,
            'total_auto_approved_tenants' => count($autoApprovedTenants)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Bulk approval failed: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Bulk approval failed: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Find property type from registration data
 */
private function findPropertyTypeFromRegistration(LandlordConstructionRegistration $registration): ?PropertyType
{
    $typeName = $registration->isConstruction() 
        ? $registration->property_type 
        : $registration->existing_property_type;
    
    if ($typeName === 'other') {
        return PropertyType::where('slug', 'custom')->first();
    }
    
    $mapping = [
        'residential' => ['Resident', 'Residential', 'Home', 'House', 'Villa', 'Bungalow'],
        'apartment' => ['Apartment', 'Flat', 'Condo', 'Condominium'],
        'commercial' => ['Commercial', 'Shop', 'Office', 'Retail', 'Store', 'Business'],
        'mixed' => ['Mixed', 'Mixed Use', 'Commercial/Residential', 'Mixed-Use'],
    ];
    
    $alternatives = $mapping[$typeName] ?? [ucfirst($typeName)];
    
    foreach ($alternatives as $alt) {
        $type = PropertyType::where('name', 'LIKE', "%{$alt}%")->first();
        if ($type) return $type;
    }
    
    return null;
}

/**
 * Send bulk landlord invitation
 */
private function sendBulkLandlordInvitation(Property $property, Request $request): array
{
    $landlord = $property->landlord;
    
    if (!$landlord) {
        return ['success' => false, 'channels_sent' => []];
    }
    
    $channels = $request->invitation_channels ?? ['email'];
    $sentChannels = [];
    
    foreach ($channels as $channel) {
        try {
            switch ($channel) {
                case 'email':
                    if ($landlord->email) {
                        $landlord->notify(new \App\Notifications\PropertyRegisteredNotification($property));
                        $sentChannels[] = 'email';
                    }
                    break;
                case 'sms':
                    if ($landlord->phone) {
                        $this->smsService->sendWithDefaultProvider(
                            $landlord->phone,
                            "Your property '{$property->property_name}' has been successfully registered. Login to view details."
                        );
                        $sentChannels[] = 'sms';
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Bulk landlord invitation failed for channel: ' . $channel, [
                'property_id' => $property->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    return [
        'success' => count($sentChannels) > 0,
        'channels_sent' => $sentChannels
    ];
}

/**
 * Send bulk tenant invitations
 */
private function sendBulkTenantInvitations(LandlordConstructionRegistration $registration, Request $request): array
{
    $property = $registration->approvedProperty;
    $tenants = $registration->tenants()->where('status', 'approved')->get();
    $channels = $request->tenant_invitation_channels ?? ['email', 'sms'];
    
    $results = [];
    $successCount = 0;
    
    foreach ($tenants as $tenant) {
        try {
            // Create tenant user if needed
            $tenantUser = $this->findOrCreateTenantUser($tenant);
            
            if (!$tenantUser) {
                $results[] = ['tenant_id' => $tenant->id, 'success' => false, 'error' => 'Failed to create user'];
                continue;
            }
            
            // Send invitation
            $result = $this->tenantInvitationService->sendInvitation($tenantUser, $property, $channels);
            
            if ($result['success']) {
                $successCount++;
            }
            
            $results[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'success' => $result['success']
            ];
            
        } catch (\Exception $e) {
            Log::error('Bulk tenant invitation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
            
            $results[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return [
        'success' => $successCount > 0,
        'success_count' => $successCount,
        'total' => $tenants->count(),
        'results' => $results
    ];
}

/**
 * Admin: View potential duplicates
 */
public function viewDuplicates()
{
    $registrations = LandlordConstructionRegistration::whereIn('status', [
        LandlordConstructionRegistration::STATUS_PENDING,
        LandlordConstructionRegistration::STATUS_IN_REVIEW,
        LandlordConstructionRegistration::STATUS_NEEDS_INFO
    ])->orderBy('created_at', 'desc')->get();
    
    // Initialize as Collection, not array
    $duplicates = collect(); // ← Use collect() helper
    
    $totalDuplicates = 0;
    $pendingDuplicates = 0;
    $resolvedDuplicates = 0;
    
    foreach ($registrations as $registration) {
        $potentialDuplicates = LandlordConstructionRegistration::where('id', '!=', $registration->id)
            ->where('plot_number', $registration->plot_number)
            ->where('property_name', $registration->property_name)
            ->whereIn('status', [
                LandlordConstructionRegistration::STATUS_PENDING,
                LandlordConstructionRegistration::STATUS_APPROVED,
                LandlordConstructionRegistration::STATUS_IN_REVIEW,
                LandlordConstructionRegistration::STATUS_NEEDS_INFO
            ])->get();
        
        if ($potentialDuplicates->isNotEmpty()) {
            // Push as Collection element
            $duplicates->push([
                'registration' => $registration,
                'duplicates' => $potentialDuplicates
            ]);
            $totalDuplicates += $potentialDuplicates->count();
            $pendingDuplicates++;
        }
    }
    
    return view('admin.registrations.duplicates', compact(
        'duplicates', 
        'totalDuplicates', 
        'pendingDuplicates', 
        'resolvedDuplicates'
    ));
}

/**
 * Helper method to return clean JSON response
 * Prevents BOM and extra output from corrupting the JSON
 * 
 * @param mixed $data
 * @param int $status
 * @return \Illuminate\Http\JsonResponse
 */
protected function cleanJsonResponse($data, $status = 200)
{
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Remove BOM if present in the data
    $jsonString = json_encode($data);
    if (strpos($jsonString, "\xEF\xBB\xBF") !== false) {
        $jsonString = str_replace("\xEF\xBB\xBF", '', $jsonString);
        $data = json_decode($jsonString, true);
    }
    
    return response()->json($data, $status);
}

/**
 * Move a registration to the archive table
 * 
 * @param LandlordConstructionRegistration $registration
 * @return ArchivedConstructionRegistration
 */
private function moveToArchiveTable(LandlordConstructionRegistration $registration): ArchivedConstructionRegistration
{
    return ArchivedConstructionRegistration::create([
        'registration_type' => $registration->registration_type,
        'purpose' => $registration->purpose,
        'name' => $registration->name,
        'email' => $registration->email,
        'primary_phone' => $registration->primary_phone,
        'additional_phones' => $registration->additional_phones,
        'landlord_id' => $registration->landlord_id,
        'property_name' => $registration->property_name,
        'plot_number' => $registration->plot_number,
        'street_name' => $registration->street_name,
        'digital_address' => $registration->digital_address,
        'land_description' => $registration->land_description,
        'land_ownership_document' => $registration->land_ownership_document,
        'zone' => $registration->zone,
        'section' => $registration->section,
        'property_type' => $registration->property_type,
        'custom_property_type' => $registration->custom_property_type,
        'property_status' => $registration->property_status,
        'estimated_bedrooms' => $registration->estimated_bedrooms,
        'has_plans' => $registration->has_plans,
        'estimated_completion' => $registration->estimated_completion,
        'construction_documents' => $registration->construction_documents,
        'existing_property_type' => $registration->existing_property_type,
        'existing_custom_property_type' => $registration->existing_custom_property_type,
        'existing_property_status' => $registration->existing_property_status,
        'existing_bedrooms' => $registration->existing_bedrooms,
        'existing_bathrooms' => $registration->existing_bathrooms,
        'year_built' => $registration->year_built,
        'property_photos' => $registration->property_photos,
        'property_documents' => $registration->property_documents,
        'has_tenants' => $registration->has_tenants,
        'tenant_count' => $registration->tenant_count,
        'tenant_data' => $registration->tenant_data,
        'status' => $registration->status,
        'access_token' => $registration->access_token,
        'submitted_at' => $registration->submitted_at,
        'reviewed_at' => $registration->reviewed_at,
        'reviewed_by' => $registration->reviewed_by,
        'approved_property_id' => $registration->approved_property_id,
        'cancelled_at' => $registration->cancelled_at,
        'rejection_reason' => $registration->rejection_reason,
        'admin_notes' => $registration->admin_notes,
        'info_requested' => $registration->info_requested,
        'assigned_to' => $registration->assigned_to,
        'assigned_at' => $registration->assigned_at,
        'archived_at' => $registration->archived_at ?? now(),
        'archive_year' => $registration->archive_year ?? now()->year,
        'archive_reason' => $registration->archive_reason ?? 'Archive migration',
        'archived_by' => auth()->id(),
        'original_id' => $registration->id,
        'original_created_at' => $registration->created_at,
        'original_updated_at' => $registration->updated_at,
        'original_deleted_at' => $registration->deleted_at,
    ]);
}

}