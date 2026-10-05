<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Invoice;
use App\Models\TenantInvoice;
use App\Models\Payment;
use App\Models\ConstructionContract;
use App\Models\LandlordConstructionRegistration;
use App\Models\SecurityPost;
use App\Models\SecuritySchedule;
use App\Models\SecurityShift;
use App\Models\SecuritySupervisorAssignment;
use App\Models\SecurityReport;
use App\Models\PropertyOwnershipTransfer;
use App\Models\RegistrationPlan;
use App\Models\AdminBillingRecord;

// ✅ NEW: WhatsApp models
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppLog;


class SearchController extends Controller
{
    // =========================================================================
    // PERMISSION MATRIX
    // =========================================================================

    /**
     * Shared categories: SA (0), Admin (1), Developer (5) all see these.
     */
    private const SHARED_CATEGORIES = [
        'users',
        'registration-plans',
        'construction-registrations',
        'construction-contracts',
        'properties',
        'property-units',
        'payments',
        'invoices',
        'tenant-invoices',
        'security-posts',
        'security-shifts',
        'security-schedules',
        'security-supervisor-assignments',
        'security-reports',
        'ownership-transfers',
        // ✅ NEW: WhatsApp categories
        'whatsapp-messages',
        'whatsapp-templates',
        'whatsapp-logs',
    ];

    /**
     * Super Admin ONLY categories.
     */
    private const SUPER_ADMIN_CATEGORIES = [
        'system-settings',
        'payment-providers',
        'sms-providers',
        'whatsapp-providers',
        'superadmin-billing',
    ];

    /**
     * User types allowed to hit this controller.
     */
    private const ALLOWED_TYPES = [
        User::TYPE_SUPER_ADMIN,   // 0
        User::TYPE_ADMIN,         // 1
        User::TYPE_DEVELOPER,     // 5
    ];

    private function allowedCategories(User $user): array
    {
        $cats = self::SHARED_CATEGORIES;

        if ($user->type === User::TYPE_SUPER_ADMIN) {
            $cats = array_merge($cats, self::SUPER_ADMIN_CATEGORIES);
        }

        return $cats;
    }

    private function assertCategoryAllowed(User $user, string $category): void
    {
        if ($category === 'all') {
            return;
        }
        if (!in_array($category, $this->allowedCategories($user), true)) {
            abort(403, 'You do not have permission to search this category.');
        }
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    private function likeTerm(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    private function prefixTerm(string $term): string
    {
        return addcslashes($term, '%_\\') . '%';
    }

    private function safeRoute(string $name, $params = null, string $fallback = '#'): string
    {
        try {
            return Route::has($name) ? route($name, $params) : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * ✅ NEW: Wraps a section builder in a try/catch so a single missing
     * table or broken query doesn't 500 the whole search page.
     * Returns null on failure (caller should skip adding the section).
     */
    private function safeSection(string $label, callable $builder)
    {
        try {
            return $builder();
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                '[SearchController] Section "%s" failed: %s',
                $label,
                $e->getMessage()
            ));
            return null;
        }
    }

    // =========================================================================
    // SUGGEST (AJAX autocomplete — top 5 per category)
    // =========================================================================

    public function suggest(Request $request)
    {
        $request->validate([
            'q'        => 'required|string|min:2|max:100',
            'category' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        if (!in_array($user->type, self::ALLOWED_TYPES, true)) {
            abort(403);
        }

        $term = trim($request->input('q'));
        $cat  = $request->input('category', 'all');

        $this->assertCategoryAllowed($user, $cat);

        $prefix = $this->prefixTerm($term);
        $like   = $this->likeTerm($term);
        $limit  = 5;

        // Cache for 30 seconds — prevents hammering the DB on every keystroke.
        $cacheKey = sprintf(
            'search:suggest:%d:%s:%s',
            $user->type,
            $cat,
            md5($term)
        );

        $payload = Cache::remember($cacheKey, 30, function () use ($cat, $prefix, $like, $limit, $user) {
            $results = [];

            if ($cat === 'all' || $cat === 'users') {
                $results['users'] = $this->searchUsers($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'registration-plans') {
                $results['registration_plans'] = $this->searchRegistrationPlans($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'construction-registrations') {
                $results['construction_registrations'] = $this->searchConstructionRegistrations($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'construction-contracts') {
                $results['construction_contracts'] = $this->searchConstructionContracts($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'properties') {
                $results['properties'] = $this->searchProperties($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'property-units') {
                $results['property_units'] = $this->searchPropertyUnits($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'payments') {
                $results['payments'] = $this->searchPayments($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'invoices') {
                $results['invoices'] = $this->searchInvoices($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'tenant-invoices') {
                $results['tenant_invoices'] = $this->searchTenantInvoices($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'security-posts') {
                $results['security_posts'] = $this->searchSecurityPosts($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'security-shifts') {
                $results['security_shifts'] = $this->searchSecurityShifts($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'security-schedules') {
                $results['security_schedules'] = $this->searchSecuritySchedules($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'security-supervisor-assignments') {
                $results['supervisor_assignments'] = $this->searchSupervisorAssignments($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'security-reports') {
                $results['security_reports'] = $this->searchSecurityReports($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'ownership-transfers') {
                $results['ownership_transfers'] = $this->searchOwnershipTransfers($prefix, $like, $limit);
            }

            // ---- ✅ NEW: WHATSAPP ----
            if ($cat === 'all' || $cat === 'whatsapp-messages') {
                $results['whatsapp_messages'] = $this->searchWhatsAppMessages($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'whatsapp-templates') {
                $results['whatsapp_templates'] = $this->searchWhatsAppTemplates($prefix, $like, $limit);
            }

            if ($cat === 'all' || $cat === 'whatsapp-logs') {
                $results['whatsapp_logs'] = $this->searchWhatsAppLogs($prefix, $like, $limit);
            }

            // ---- SUPER ADMIN ONLY ----
            if ($user->type === User::TYPE_SUPER_ADMIN) {

                if ($cat === 'all' || $cat === 'superadmin-billing') {
                    $results['superadmin_billing'] = $this->searchSuperAdminBilling($user->id, $prefix, $like, $limit);
                }

                if ($cat === 'all' || $cat === 'system-settings') {
                    $results['system_settings'] = [[
                        'id'    => 'system-settings',
                        'label' => 'System Settings',
                        'sub'   => 'Manage global configuration',
                        'url'   => $this->safeRoute('admin.system-settings.index'),
                    ]];
                }

                if ($cat === 'all' || $cat === 'payment-providers') {
                    $results['payment_providers'] = [[
                        'id'    => 'payment-providers',
                        'label' => 'Payment Providers',
                        'sub'   => 'Configure gateway credentials',
                        'url'   => $this->safeRoute('admin.payment-providers.index'),
                    ]];
                }

                if ($cat === 'all' || $cat === 'sms-providers') {
                    $results['sms_providers'] = [[
                        'id'    => 'sms-providers',
                        'label' => 'SMS Providers',
                        'sub'   => 'Configure SMS gateways',
                        'url'   => $this->safeRoute('admin.sms-providers.index'),
                    ]];
                }

                if ($cat === 'all' || $cat === 'whatsapp-providers') {
                    $results['whatsapp_providers'] = [[
                        'id'    => 'whatsapp-providers',
                        'label' => 'WhatsApp Providers',
                        'sub'   => 'Configure WhatsApp Business',
                        'url'   => $this->safeRoute('admin.whatsapp-providers.index'),
                    ]];
                }
            }

            return array_filter($results, fn ($items) => !empty($items));
        });

        return response()->json([
            'success'  => true,
            'term'     => $term,
            'category' => $cat,
            'results'  => $payload,
        ]);
    }

    // =========================================================================
    // FULL RESULTS PAGE (paginated)
    // =========================================================================

    public function index(Request $request)
    {
        $request->validate([
            'q'        => 'required|string|min:2|max:100',
            'category' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        if (!in_array($user->type, self::ALLOWED_TYPES, true)) {
            abort(403);
        }

        $term = trim($request->input('q'));
        $cat  = $request->input('category', 'all');

        $this->assertCategoryAllowed($user, $cat);

        $prefix  = $this->prefixTerm($term);
        $like    = $this->likeTerm($term);
        $perPage = 20;

        $sections = [];

        // ---- USERS ----
        if ($cat === 'all' || $cat === 'users') {
            $section = $this->safeSection('users', fn () => [
                'title' => 'Users',
                'icon'  => 'fa-users',
                'items' => User::query()
                    ->select('id', 'name', 'email', 'phone', 'type', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('name', 'LIKE', $prefix)
                          ->orWhere('email', 'LIKE', $prefix)
                          ->orWhere('name', 'LIKE', $like)
                          ->orWhere('email', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'users_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.users',
            ]);
            if ($section) $sections['users'] = $section;
        }

        // ---- REGISTRATION PLANS ----
        if ($cat === 'all' || $cat === 'registration-plans') {
            $section = $this->safeSection('registration_plans', fn () => [
                'title' => 'Registration Plans',
                'icon'  => 'fa-map-marked-alt',
                'items' => RegistrationPlan::query()
                    ->select('id', 'plan_code', 'zone', 'section', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('plan_code', 'LIKE', $prefix)
                          ->orWhere('zone',      'LIKE', $prefix)
                          ->orWhere('section',   'LIKE', $prefix)
                          ->orWhere('plan_code', 'LIKE', $like)
                          ->orWhere('zone',      'LIKE', $like)
                          ->orWhere('section',   'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'plans_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.registration-plans',
            ]);
            if ($section) $sections['registration_plans'] = $section;
        }

        // ---- CONSTRUCTION REGISTRATIONS ----
        if ($cat === 'all' || $cat === 'construction-registrations') {
            $section = $this->safeSection('construction_registrations', fn () => [
                'title' => 'Construction Registrations',
                'icon'  => 'fa-hard-hat',
                'items' => LandlordConstructionRegistration::query()
                    ->select('id', 'submission_hash', 'property_name', 'name', 'email', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('submission_hash', 'LIKE', $prefix)
                          ->orWhere('property_name', 'LIKE', $prefix)
                          ->orWhere('name',          'LIKE', $prefix)
                          ->orWhere('email',         'LIKE', $prefix)
                          ->orWhere('property_name', 'LIKE', $like)
                          ->orWhere('name',          'LIKE', $like)
                          ->orWhere('email',         'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'creg_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.construction-registrations',
            ]);
            if ($section) $sections['construction_registrations'] = $section;
        }

        // ---- CONSTRUCTION CONTRACTS ----
        if ($cat === 'all' || $cat === 'construction-contracts') {
            $section = $this->safeSection('construction_contracts', fn () => [
                'title' => 'Construction Contracts',
                'icon'  => 'fa-file-contract',
                'items' => ConstructionContract::query()
                    ->select('id', 'contract_number', 'title', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('contract_number', 'LIKE', $prefix)
                          ->orWhere('title',         'LIKE', $prefix)
                          ->orWhere('contract_number', 'LIKE', $like)
                          ->orWhere('title',         'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'cct_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.construction-contracts',
            ]);
            if ($section) $sections['construction_contracts'] = $section;
        }

        // ---- PROPERTIES ----
        if ($cat === 'all' || $cat === 'properties') {
            $section = $this->safeSection('properties', fn () => [
                'title' => 'Properties',
                'icon'  => 'fa-building',
                'items' => Property::query()
                    ->select('id', 'property_name', 'street_name', 'digital_address', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('property_name',        'LIKE', $prefix)
                          ->orWhere('digital_address',    'LIKE', $prefix)
                          ->orWhere('registration_pattern','LIKE', $prefix)
                          ->orWhere('property_name',      'LIKE', $like)
                          ->orWhere('digital_address',    'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'properties_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.properties',
            ]);
            if ($section) $sections['properties'] = $section;
        }

        // ---- PROPERTY UNITS ----
        if ($cat === 'all' || $cat === 'property-units') {
            $section = $this->safeSection('property_units', fn () => [
                'title' => 'Property Units',
                'icon'  => 'fa-door-closed',
                'items' => PropertyUnit::query()
                    ->select('id', 'unit_number', 'property_id', 'status', 'created_at')
                    ->with('property:id,property_name')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('unit_number', 'LIKE', $prefix)
                          ->orWhere('unit_number', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'units_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.property-units',
            ]);
            if ($section) $sections['property_units'] = $section;
        }

        // ---- PAYMENTS ----
        if ($cat === 'all' || $cat === 'payments') {
            $section = $this->safeSection('payments', fn () => [
                'title' => 'Payments',
                'icon'  => 'fa-money-bill-wave',
                'items' => Payment::query()
                    ->select('id', 'transaction_reference', 'amount', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('transaction_reference', 'LIKE', $prefix)
                          ->orWhere('transaction_reference', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'payments_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.payments',
            ]);
            if ($section) $sections['payments'] = $section;
        }

        // ---- LANDLORD INVOICES ----
        if ($cat === 'all' || $cat === 'invoices') {
            $section = $this->safeSection('invoices', fn () => [
                'title' => 'Landlord Invoices',
                'icon'  => 'fa-file-invoice',
                'items' => Invoice::query()
                    ->select('id', 'invoice_number', 'amount', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('invoice_number', 'LIKE', $prefix)
                          ->orWhere('invoice_number', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'invoices_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.invoices',
            ]);
            if ($section) $sections['invoices'] = $section;
        }

        // ---- TENANT INVOICES ----
        if ($cat === 'all' || $cat === 'tenant-invoices') {
            $section = $this->safeSection('tenant_invoices', fn () => [
                'title' => 'Tenant Invoices',
                'icon'  => 'fa-file-invoice-dollar',
                'items' => TenantInvoice::query()
                    ->select('id', 'invoice_number', 'total_amount', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('invoice_number', 'LIKE', $prefix)
                          ->orWhere('invoice_number', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'tinvoices_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.tenant-invoices',
            ]);
            if ($section) $sections['tenant_invoices'] = $section;
        }

        // ---- SECURITY POSTS ----
        if ($cat === 'all' || $cat === 'security-posts') {
            $section = $this->safeSection('security_posts', fn () => [
                'title' => 'Security Posts',
                'icon'  => 'fa-map-marker-alt',
                'items' => SecurityPost::query()
                    ->select('id', 'name', 'code', 'type', 'is_active', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('name', 'LIKE', $prefix)
                          ->orWhere('code', 'LIKE', $prefix)
                          ->orWhere('name', 'LIKE', $like)
                          ->orWhere('code', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'posts_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.security-posts',
            ]);
            if ($section) $sections['security_posts'] = $section;
        }

        // ---- SECURITY SHIFTS ----
        if ($cat === 'all' || $cat === 'security-shifts') {
            $section = $this->safeSection('security_shifts', fn () => [
                'title' => 'Security Shifts',
                'icon'  => 'fa-clock',
                'items' => SecurityShift::query()
                    ->select('id', 'name', 'start_time', 'end_time', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('name', 'LIKE', $prefix)
                          ->orWhere('name', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'shifts_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.security-shifts',
            ]);
            if ($section) $sections['security_shifts'] = $section;
        }

        // ---- SECURITY SCHEDULES ----
        if ($cat === 'all' || $cat === 'security-schedules') {
            $section = $this->safeSection('security_schedules', fn () => [
                'title' => 'Security Schedules',
                'icon'  => 'fa-calendar-alt',
                'items' => SecuritySchedule::query()
                    ->select('id', 'assignment_date', 'status', 'security_post_id', 'security_user_id')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('status', 'LIKE', $prefix)
                          ->orWhere('status', 'LIKE', $like);
                    })
                    ->orderByDesc('assignment_date')
                    ->paginate($perPage, ['*'], 'schedules_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.security-schedules',
            ]);
            if ($section) $sections['security_schedules'] = $section;
        }

        // ---- SUPERVISOR ASSIGNMENTS ----
        if ($cat === 'all' || $cat === 'security-supervisor-assignments') {
            $section = $this->safeSection('supervisor_assignments', fn () => [
                'title' => 'Supervisor Assignments',
                'icon'  => 'fa-user-shield',
                'items' => SecuritySupervisorAssignment::query()
                    ->select('id', 'user_id', 'security_post_id', 'is_active', 'approval_status', 'supervisor_type', 'start_date', 'end_date')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('approval_status', 'LIKE', $prefix)
                          ->orWhere('supervisor_type', 'LIKE', $prefix)
                          ->orWhere('approval_status', 'LIKE', $like)
                          ->orWhere('supervisor_type', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'sup_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.supervisor-assignments',
            ]);
            if ($section) $sections['supervisor_assignments'] = $section;
        }

        // ---- SECURITY REPORTS ----
        if ($cat === 'all' || $cat === 'security-reports') {
            $section = $this->safeSection('security_reports', fn () => [
                'title' => 'Security Reports',
                'icon'  => 'fa-clipboard-list',
                'items' => SecurityReport::query()
                    ->select('id', 'title', 'report_type', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('title', 'LIKE', $prefix)
                          ->orWhere('title', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'reports_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.security-reports',
            ]);
            if ($section) $sections['security_reports'] = $section;
        }

        // ---- OWNERSHIP TRANSFERS ----
        if ($cat === 'all' || $cat === 'ownership-transfers') {
            $section = $this->safeSection('ownership_transfers', fn () => [
                'title' => 'Ownership Transfers',
                'icon'  => 'fa-exchange-alt',
                'items' => PropertyOwnershipTransfer::query()
                    ->select('id', 'document_reference', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('document_reference', 'LIKE', $prefix)
                          ->orWhere('document_reference', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'transfers_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.ownership-transfers',
            ]);
            if ($section) $sections['ownership_transfers'] = $section;
        }

        // =====================================================================
        // ✅ NEW: WHATSAPP SECTIONS
        // =====================================================================

        // ---- WHATSAPP MESSAGES ----
        if ($cat === 'all' || $cat === 'whatsapp-messages') {
            $section = $this->safeSection('whatsapp_messages', fn () => [
                'title' => 'WhatsApp Messages',
                'icon'  => 'fab fa-whatsapp',
                'items' => WhatsAppMessage::query()
                    ->select('id', 'to', 'from', 'message', 'status', 'provider', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('to',      'LIKE', $prefix)
                          ->orWhere('from',    'LIKE', $prefix)
                          ->orWhere('message', 'LIKE', $prefix)
                          ->orWhere('to',      'LIKE', $like)
                          ->orWhere('from',    'LIKE', $like)
                          ->orWhere('message', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'wa_msg_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.whatsapp-messages',
            ]);
            if ($section) $sections['whatsapp_messages'] = $section;
        }

        // ---- WHATSAPP TEMPLATES ----
        if ($cat === 'all' || $cat === 'whatsapp-templates') {
            $section = $this->safeSection('whatsapp_templates', fn () => [
                'title' => 'WhatsApp Templates',
                'icon'  => 'fab fa-whatsapp',
                'items' => WhatsAppTemplate::query()
                    ->select('id', 'name', 'description', 'content', 'category', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('name',        'LIKE', $prefix)
                          ->orWhere('description', 'LIKE', $prefix)
                          ->orWhere('content',   'LIKE', $prefix)
                          ->orWhere('name',        'LIKE', $like)
                          ->orWhere('description', 'LIKE', $like)
                          ->orWhere('content',   'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'wa_tpl_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.whatsapp-templates',
            ]);
            if ($section) $sections['whatsapp_templates'] = $section;
        }

        // ---- WHATSAPP LOGS ----
        if ($cat === 'all' || $cat === 'whatsapp-logs') {
            $section = $this->safeSection('whatsapp_logs', fn () => [
                'title' => 'WhatsApp Logs',
                'icon'  => 'fab fa-whatsapp',
                'items' => WhatsAppLog::query()
                    ->select('id', 'to', 'from', 'message', 'provider', 'message_id', 'status', 'created_at')
                    ->where(function ($q) use ($prefix, $like) {
                        $q->where('to',         'LIKE', $prefix)
                          ->orWhere('message',    'LIKE', $prefix)
                          ->orWhere('provider',   'LIKE', $prefix)
                          ->orWhere('message_id', 'LIKE', $prefix)
                          ->orWhere('to',         'LIKE', $like)
                          ->orWhere('message',    'LIKE', $like)
                          ->orWhere('provider',   'LIKE', $like)
                          ->orWhere('message_id', 'LIKE', $like);
                    })
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'wa_log_page')
                    ->withQueryString(),
                'view' => 'admin.search.partials.whatsapp-logs',
            ]);
            if ($section) $sections['whatsapp_logs'] = $section;
        }

        // =====================================================================
        // SUPER ADMIN ONLY SECTIONS
        // =====================================================================
        if ($user->type === User::TYPE_SUPER_ADMIN) {

            if ($cat === 'all' || $cat === 'superadmin-billing') {
                $section = $this->safeSection('superadmin_billing', fn () => [
                    'title' => 'My Billing Agreements',
                    'icon'  => 'fa-file-invoice-dollar',
                    'items' => AdminBillingRecord::query()
                        ->select('id', 'agreement_number', 'description', 'amount', 'status', 'created_at')
                        ->where('super_admin_id', $user->id)
                        ->where(function ($q) use ($prefix, $like) {
                            $q->where('agreement_number', 'LIKE', $prefix)
                              ->orWhere('description',    'LIKE', $prefix)
                              ->orWhere('agreement_number','LIKE', $like)
                              ->orWhere('description',    'LIKE', $like);
                        })
                        ->orderByDesc('id')
                        ->paginate($perPage, ['*'], 'billing_page')
                        ->withQueryString(),
                    'view' => 'admin.search.partials.superadmin-billing',
                ]);
                if ($section) $sections['superadmin_billing'] = $section;
            }

            $sections['quick_links'] = [
                'title' => 'Configuration',
                'icon'  => 'fa-cog',
                'items' => collect([
                    [
                        'label' => 'System Settings',
                        'sub'   => 'Global configuration',
                        'url'   => $this->safeRoute('admin.system-settings.index'),
                        'icon'  => 'fa-cog',
                    ],
                    [
                        'label' => 'Payment Providers',
                        'sub'   => 'Configure gateways',
                        'url'   => $this->safeRoute('admin.payment-providers.index'),
                        'icon'  => 'fa-credit-card',
                    ],
                    [
                        'label' => 'SMS Providers',
                        'sub'   => 'SMS gateway config',
                        'url'   => $this->safeRoute('admin.sms-providers.index'),
                        'icon'  => 'fa-sms',
                    ],
                    [
                        'label' => 'WhatsApp Providers',
                        'sub'   => 'WhatsApp config',
                        'url'   => $this->safeRoute('admin.whatsapp-providers.index'),
                        'icon'  => 'fa-whatsapp',
                    ],
                ])->filter(fn ($i) => $i['url'] !== '#')->values(),
                'view' => 'admin.search.partials.quick-links',
            ];
        }

        // Total hit count across all sections
        $totalHits = 0;
        foreach ($sections as $s) {
            if (method_exists($s['items'], 'total')) {
                $totalHits += $s['items']->total();
            } else {
                $totalHits += $s['items']->count();
            }
        }

        return view('admin.search.index', [
            'term'      => $term,
            'category'  => $cat,
            'sections'  => $sections,
            'totalHits' => $totalHits,
            'userType'  => $user->type,
        ]);
    }

    // =========================================================================
    // SECTION SEARCHERS — used by suggest()
    // =========================================================================

    private function searchUsers(string $prefix, string $like, int $limit): array
    {
        return User::query()
            ->select('id', 'name', 'email', 'type')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('name', 'LIKE', $prefix)
                  ->orWhere('email', 'LIKE', $prefix)
                  ->orWhere('name', 'LIKE', $like)
                  ->orWhere('email', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($u) => [
                'id'    => $u->id,
                'label' => $u->name,
                'sub'   => $u->email,
                'url'   => $this->safeRoute('admin.users.show', $u->id),
            ])->toArray();
    }

    private function searchRegistrationPlans(string $prefix, string $like, int $limit): array
    {
        return RegistrationPlan::query()
            ->select('id', 'plan_code', 'zone', 'section', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('plan_code', 'LIKE', $prefix)
                  ->orWhere('zone',      'LIKE', $prefix)
                  ->orWhere('section',   'LIKE', $prefix)
                  ->orWhere('plan_code', 'LIKE', $like)
                  ->orWhere('zone',      'LIKE', $like)
                  ->orWhere('section',   'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'label' => $p->plan_code ?: ('Plan #' . $p->id),
                'sub'   => trim(
                    ($p->zone ?? '') . ' / ' . ($p->section ?? '') . ' · ' . ($p->status ?? ''),
                    ' /·'
                ),
                'url'   => $this->safeRoute('registration-plans.show', $p->id),
            ])->toArray();
    }

    private function searchConstructionRegistrations(string $prefix, string $like, int $limit): array
    {
        return LandlordConstructionRegistration::query()
            ->select('id', 'submission_hash', 'property_name', 'name', 'email', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('submission_hash', 'LIKE', $prefix)
                  ->orWhere('property_name', 'LIKE', $prefix)
                  ->orWhere('name',          'LIKE', $prefix)
                  ->orWhere('email',         'LIKE', $prefix)
                  ->orWhere('property_name', 'LIKE', $like)
                  ->orWhere('name',          'LIKE', $like)
                  ->orWhere('email',         'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id'    => $r->id,
                'label' => $r->property_name ?: ($r->name ?: 'Registration #' . $r->id),
                'sub'   => trim(($r->name ? $r->name . ' · ' : '') . ($r->status ?? '—'), ' ·'),
                'url'   => $this->safeRoute('admin.construction-registrations.show', $r->id),
            ])->toArray();
    }

    private function searchConstructionContracts(string $prefix, string $like, int $limit): array
    {
        return ConstructionContract::query()
            ->select('id', 'contract_number', 'title', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('contract_number', 'LIKE', $prefix)
                  ->orWhere('title',         'LIKE', $prefix)
                  ->orWhere('contract_number', 'LIKE', $like)
                  ->orWhere('title',         'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'label' => $c->contract_number,
                'sub'   => $c->title . ' · ' . $c->status,
                'url'   => $this->safeRoute('admin.construction.contracts.show', $c->id),
            ])->toArray();
    }

    private function searchProperties(string $prefix, string $like, int $limit): array
    {
        return Property::query()
            ->select('id', 'property_name', 'street_name', 'digital_address')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('property_name',        'LIKE', $prefix)
                  ->orWhere('digital_address',    'LIKE', $prefix)
                  ->orWhere('registration_pattern','LIKE', $prefix)
                  ->orWhere('property_name',      'LIKE', $like)
                  ->orWhere('digital_address',    'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'label' => $p->property_name,
                'sub'   => $p->digital_address ?? $p->street_name,
                'url'   => $this->safeRoute('properties.show', $p->id),
            ])->toArray();
    }

    private function searchPropertyUnits(string $prefix, string $like, int $limit): array
    {
        return PropertyUnit::query()
            ->select('id', 'unit_number', 'property_id')
            ->with('property:id,property_name')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('unit_number', 'LIKE', $prefix)
                  ->orWhere('unit_number', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($u) => [
                'id'    => $u->id,
                'label' => 'Unit ' . $u->unit_number,
                'sub'   => $u->property->property_name ?? null,
                'url'   => $this->safeRoute('admin.property-units.show', $u->id),
            ])->toArray();
    }

    private function searchPayments(string $prefix, string $like, int $limit): array
    {
        return Payment::query()
            ->select('id', 'transaction_reference', 'amount', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('transaction_reference', 'LIKE', $prefix)
                  ->orWhere('transaction_reference', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'label' => $p->transaction_reference,
                'sub'   => number_format((float) $p->amount, 2) . ' · ' . $p->status,
                'url'   => $this->safeRoute('admin.payments.show', $p->id),
            ])->toArray();
    }

    private function searchInvoices(string $prefix, string $like, int $limit): array
    {
        return Invoice::query()
            ->select('id', 'invoice_number', 'amount', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('invoice_number', 'LIKE', $prefix)
                  ->orWhere('invoice_number', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($i) => [
                'id'    => $i->id,
                'label' => $i->invoice_number,
                'sub'   => number_format((float) $i->amount, 2) . ' · ' . $i->status,
                'url'   => $this->safeRoute('invoices.show', $i->id),
            ])->toArray();
    }

    private function searchTenantInvoices(string $prefix, string $like, int $limit): array
    {
        return TenantInvoice::query()
            ->select('id', 'invoice_number', 'total_amount', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('invoice_number', 'LIKE', $prefix)
                  ->orWhere('invoice_number', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($i) => [
                'id'    => $i->id,
                'label' => $i->invoice_number,
                'sub'   => number_format((float) $i->total_amount, 2) . ' · ' . $i->status,
                'url'   => $this->safeRoute('admin.tenant-invoices.show', $i->id),
            ])->toArray();
    }

    private function searchSecurityPosts(string $prefix, string $like, int $limit): array
    {
        return SecurityPost::query()
            ->select('id', 'name', 'code', 'type', 'is_active')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('name', 'LIKE', $prefix)
                  ->orWhere('code', 'LIKE', $prefix)
                  ->orWhere('name', 'LIKE', $like)
                  ->orWhere('code', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'label' => $p->name,
                'sub'   => trim(
                    ($p->code ?? '') . ' · ' . ($p->type ?? '') . ' · ' .
                    ($p->is_active ? 'Active' : 'Inactive'),
                    ' ·'
                ),
                'url'   => $this->safeRoute('admin.security-posts.show', $p->id),
            ])->toArray();
    }

    private function searchSecurityShifts(string $prefix, string $like, int $limit): array
    {
        return SecurityShift::query()
            ->select('id', 'name', 'start_time', 'end_time')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('name', 'LIKE', $prefix)
                  ->orWhere('name', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id'    => $s->id,
                'label' => $s->name,
                'sub'   => trim(($s->start_time ?? '') . ' – ' . ($s->end_time ?? '')),
                'url'   => $this->safeRoute('admin.security-shifts.show', $s->id),
            ])->toArray();
    }

    private function searchSecuritySchedules(string $prefix, string $like, int $limit): array
    {
        return SecuritySchedule::query()
            ->select('id', 'assignment_date', 'status', 'security_post_id', 'security_user_id')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('status', 'LIKE', $prefix)
                  ->orWhere('status', 'LIKE', $like);
            })
            ->orderByDesc('assignment_date')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id'    => $s->id,
                'label' => 'Schedule #' . $s->id,
                'sub'   => ($s->assignment_date
                                ? Carbon::parse($s->assignment_date)->format('M d, Y')
                                : '—')
                           . ' · ' . ($s->status ?? '—'),
                'url'   => $this->safeRoute('admin.security-schedules.show', $s->id),
            ])->toArray();
    }

    private function searchSupervisorAssignments(string $prefix, string $like, int $limit): array
    {
        return SecuritySupervisorAssignment::query()
            ->select('id', 'user_id', 'security_post_id', 'is_active', 'approval_status', 'supervisor_type', 'start_date', 'end_date')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('approval_status', 'LIKE', $prefix)
                  ->orWhere('supervisor_type', 'LIKE', $prefix)
                  ->orWhere('approval_status', 'LIKE', $like)
                  ->orWhere('supervisor_type', 'LIKE', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($a) {
                $parts = [];
                if ($a->supervisor_type) $parts[] = $a->supervisor_type;
                if ($a->approval_status) $parts[] = $a->approval_status;
                $parts[] = $a->is_active ? 'Active' : 'Inactive';
                if ($a->start_date) $parts[] = 'From ' . Carbon::parse($a->start_date)->format('M d');

                return [
                    'id'    => $a->id,
                    'label' => 'Supervisor Assignment #' . $a->id,
                    'sub'   => implode(' · ', array_filter($parts)),
                    'url'   => $this->safeRoute('admin.supervisor-assignments.show', $a->id),
                ];
            })->toArray();
    }

    private function searchSecurityReports(string $prefix, string $like, int $limit): array
    {
        return SecurityReport::query()
            ->select('id', 'title', 'report_type', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('title', 'LIKE', $prefix)
                  ->orWhere('title', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id'    => $r->id,
                'label' => $r->title,
                'sub'   => ($r->report_type ?? '') . ' · ' . ($r->status ?? ''),
                'url'   => $this->safeRoute('admin.security-reports.show', $r->id),
            ])->toArray();
    }

    private function searchOwnershipTransfers(string $prefix, string $like, int $limit): array
    {
        return PropertyOwnershipTransfer::query()
            ->select('id', 'document_reference', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('document_reference', 'LIKE', $prefix)
                  ->orWhere('document_reference', 'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($t) => [
                'id'    => $t->id,
                'label' => $t->document_reference,
                'sub'   => $t->status,
                'url'   => $this->safeRoute('admin.ownership-transfers.show', $t->id),
            ])->toArray();
    }

    private function searchSuperAdminBilling(int $userId, string $prefix, string $like, int $limit): array
    {
        return AdminBillingRecord::query()
            ->select('id', 'agreement_number', 'description', 'amount', 'status')
            ->where('super_admin_id', $userId)
            ->where(function ($q) use ($prefix, $like) {
                $q->where('agreement_number', 'LIKE', $prefix)
                  ->orWhere('description',    'LIKE', $prefix)
                  ->orWhere('agreement_number','LIKE', $like)
                  ->orWhere('description',    'LIKE', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn ($b) => [
                'id'    => $b->id,
                'label' => $b->agreement_number,
                'sub'   => ($b->description ?? '') . ' · ' . number_format((float) $b->amount, 2),
                'url'   => $this->safeRoute('superadmin.billing.view-agreement', $b->id),
            ])->toArray();
    }

    // =========================================================================
    // ✅ NEW: WHATSAPP SECTION SEARCHERS
    // =========================================================================

    private function searchWhatsAppMessages(string $prefix, string $like, int $limit): array
    {
        return WhatsAppMessage::query()
            ->select('id', 'to', 'from', 'message', 'status', 'provider')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('to',      'LIKE', $prefix)
                  ->orWhere('from',    'LIKE', $prefix)
                  ->orWhere('message', 'LIKE', $prefix)
                  ->orWhere('to',      'LIKE', $like)
                  ->orWhere('from',    'LIKE', $like)
                  ->orWhere('message', 'LIKE', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($m) => [
                'id'    => $m->id,
                'label' => Str::limit($m->message ?? 'Message #' . $m->id, 60),
                'sub'   => trim(($m->to ?? '') . ' · ' . ($m->provider ?? '') . ' · ' . ($m->status ?? ''), ' ·'),
                'url'   => $this->safeRoute('admin.whatsapp.messages.show', $m->id),
            ])->toArray();
    }

    private function searchWhatsAppTemplates(string $prefix, string $like, int $limit): array
    {
        return WhatsAppTemplate::query()
            ->select('id', 'name', 'description', 'content', 'category', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('name',        'LIKE', $prefix)
                  ->orWhere('description', 'LIKE', $prefix)
                  ->orWhere('content',   'LIKE', $prefix)
                  ->orWhere('name',        'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like)
                  ->orWhere('content',   'LIKE', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($t) => [
                'id'    => $t->id,
                'label' => $t->name,
                'sub'   => trim(($t->category ?? '') . ' · ' . ($t->status ?? ''), ' ·'),
                'url'   => $this->safeRoute('admin.whatsapp.templates.show', $t->id),
            ])->toArray();
    }

    private function searchWhatsAppLogs(string $prefix, string $like, int $limit): array
    {
        return WhatsAppLog::query()
            ->select('id', 'to', 'from', 'message', 'provider', 'message_id', 'status')
            ->where(function ($q) use ($prefix, $like) {
                $q->where('to',         'LIKE', $prefix)
                  ->orWhere('message',    'LIKE', $prefix)
                  ->orWhere('provider',   'LIKE', $prefix)
                  ->orWhere('message_id', 'LIKE', $prefix)
                  ->orWhere('to',         'LIKE', $like)
                  ->orWhere('message',    'LIKE', $like)
                  ->orWhere('provider',   'LIKE', $like)
                  ->orWhere('message_id', 'LIKE', $like);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($l) => [
                'id'    => $l->id,
                'label' => Str::limit($l->message ?? 'Log #' . $l->id, 60),
                'sub'   => trim(
                    ($l->to ?? '') . ' · ' . ($l->provider ?? '') . ' · ' . ($l->status ?? ''),
                    ' ·'
                ),
                'url'   => $this->safeRoute('admin.whatsapp.logs.show', $l->id),
            ])->toArray();
    }
}