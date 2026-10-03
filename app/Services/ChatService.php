<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ChatService
{
    /**
     * Base signature for intent detection.
     *
     * Kept as a class constant so tests and other services can reuse it.
     */
    protected const INTENTS = [
        'greeting'   => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'],
        'help'       => ['help', 'how to', 'how do i', 'what is', 'how can i', 'where can i', 'show me'],
        'navigation' => ['where is', 'find', 'navigate', 'go to', 'menu', 'location of'],
        'profile'    => ['profile', 'account', 'settings', 'update', 'change password', 'edit my'],
        'features'   => ['feature', 'what can', 'capabilities', 'functions', 'what does this app do'],
        'problems'   => ['error', 'problem', 'not working', 'issue', 'bug', 'fix', 'trouble', 'difficult'],
        'goodbye'    => ['bye', 'goodbye', 'see you', 'exit', 'quit', 'later'],
    ];

    /* ============================================================
       PUBLIC API
       ============================================================ */

    /**
     * ⭐ CHANGED: Accepts nullable userId + optional guestToken.
     *
     * Behavior:
     *   - Authenticated: $userId non-null → role-aware reply
     *   - Guest:         $userId null + $guestToken → guest reply
     *   - Invalid:       $userId null + $guestToken null → soft error string
     *
     * @param  int|null    $userId
     * @param  string      $message
     * @param  int|null    $userType    Kept for BC; resolved from User when null
     * @param  string|null $guestToken  Required when $userId is null
     */
    public function processMessage(
        ?int $userId,
        string $message,
        ?int $userType = null,
        ?string $guestToken = null
    ): string {
        // Resolve the user (may be null for guests)
        $user = $userId ? User::find($userId) : null;

        // Safety: authenticated flow with a missing user
        if ($userId && !$user) {
            return "I'm sorry, I couldn't find your user account. Please try again.";
        }

        // Safety: neither user nor guest token
        if (!$user && !$guestToken) {
            return "I'm sorry, I couldn't identify your session. Please refresh the page and try again.";
        }

        // ---------- Persist the incoming message ----------
        try {
            $chatMessage = ChatMessage::create([
                'user_id'          => $user?->id,
                'guest_token'      => $user ? null : $guestToken,
                'guest_ip'         => $user ? null : request()?->ip(),
                'guest_user_agent' => $user ? null : substr((string) request()?->userAgent(), 0, 500),
                'message'          => $message,
                'is_bot'           => false,
                'response'         => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to persist chat message', [
                'user_id' => $user?->id,
                'guest'   => $guestToken,
                'error'   => $e->getMessage(),
            ]);
            // Don't 500 the request; still return a reply
            $chatMessage = null;
        }

        // ---------- Generate the reply ----------
        $response = $user
            ? $this->generateAppSpecificResponse($message, $user)
            : $this->generateGuestResponse($message);

        // ---------- Attach the reply to the stored row ----------
        if ($chatMessage) {
            try {
                $chatMessage->update(['response' => $response]);
            } catch (\Throwable $e) {
                Log::warning('Failed to store bot reply', [
                    'chat_message_id' => $chatMessage->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }

    /**
     * Expose intent detection externally (tests, future AI upgrades).
     *
     * ⭐ CHANGED: Now uses word-boundary regex instead of str_contains()
     * so "which" no longer matches "hi".
     */
    public function detectIntent(string $message): string
    {
        $lower = strtolower(trim($message));

        foreach (self::INTENTS as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/') . '(?![\p{L}\p{N}])/iu';
                if (preg_match($pattern, $lower)) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /* ============================================================
       INTERNAL DISPATCH — AUTHENTICATED
       ============================================================ */

    protected function generateAppSpecificResponse(string $message, User $user): string
    {
        $lower  = strtolower(trim($message));
        $intent = $this->detectIntent($lower);

        return $this->generateResponseByIntent($intent, $lower, $user);
    }

    protected function generateResponseByIntent(string $intent, string $message, User $user): string
    {
        return match ($intent) {
            'greeting'   => $this->getGreetingResponse($user),
            'help'       => $this->getHelpResponse($message, $user),
            'navigation' => $this->getNavigationResponse($message, $user),
            'profile'    => $this->getProfileResponse($message, $user),
            'features'   => $this->getFeaturesResponse($user),
            'problems'   => $this->getTroubleshootingResponse($message, $user),
            'goodbye'    => $this->getGoodbyeResponse($user),
            default      => $this->getGeneralResponse($message, $user),
        };
    }

    /* ============================================================
       INTERNAL DISPATCH — GUEST
       ============================================================ */

    /**
     * ⭐ NEW: Full guest reply generator.
     *
     * Guests are pre-registration visitors — the reply set focuses on
     * "how do I get started", fees, documents, approval, and support.
     */
    protected function generateGuestResponse(string $message): string
    {
        $lower  = strtolower(trim($message));
        $intent = $this->detectIntent($lower);

        return match ($intent) {
            'greeting'   => $this->getGuestGreetingResponse(),
            'help'       => $this->getGuestHelpResponse($lower),
            'navigation' => $this->getGuestNavigationResponse($lower),
            'profile'    => $this->getGuestProfileResponse($lower),
            'features'   => $this->getGuestFeaturesResponse(),
            'problems'   => $this->getGuestTroubleshootingResponse($lower),
            'goodbye'    => $this->getGuestGoodbyeResponse(),
            default      => $this->getGuestGeneralResponse($message),
        };
    }

    /* ============================================================
       GUEST RESPONSE BUILDERS
       ============================================================ */

    protected function getGuestGreetingResponse(): string
    {
        $greetings = [
            "Hello! 👋 I'm your assistant for this platform.",
            "Hi there! 🤖 I'm here to help you get started.",
            "Welcome! 🎉 I'm here to answer your questions.",
        ];

        return $greetings[array_rand($greetings)]
            . "\n\nI can help you with:\n"
            . "• Registering your property\n"
            . "• Fees and payment options\n"
            . "• Required documents\n"
            . "• Approval timelines\n"
            . "• Contacting support\n\n"
            . "What would you like to know?";
    }

    protected function getGuestHelpResponse(string $message): string
    {
        $topics = [
            'register' => "**To register your property:**\n\n"
                . "1. Click **Register Your Land/Property** on the homepage\n"
                . "2. Choose **Vacant Land** or **Existing Property**\n"
                . "3. Fill in your personal and property details\n"
                . "4. Upload your ownership document\n"
                . "5. Submit — our team reviews within 2–3 business days\n\n"
                . "You'll get an email/SMS the moment your property is approved.",

            'login' => "**To log in:**\n\n"
                . "1. Click **Login** in the top-right corner\n"
                . "2. Enter your registered email or phone number\n"
                . "3. Enter your password\n\n"
                . "If you've forgotten your password, click **Forgot Password** on the login page.",

            'fee' => "**Registration is completely free.**\n\n"
                . "Estate dues are billed monthly **after** your property is approved. "
                . "You can pay via Mobile Money, bank transfer, card, or cash at the office.",

            'document' => "**Documents you'll need:**\n\n"
                . "• Land ownership document (title, indenture, or lease)\n"
                . "• Valid ID (Ghana Card, passport, driver's license)\n"
                . "• Property details (plot number, street, digital address)\n"
                . "• Photos of an existing property (optional)\n\n"
                . "Accepted formats: PDF, JPG, PNG (up to 10 MB each).",

            'how long' => "**Approval timeline:**\n\n"
                . "• Form completion: 10–15 minutes\n"
                . "• Verification: **2–3 business days**\n"
                . "• You'll be notified by email/SMS once approved.",
        ];

        foreach ($topics as $keyword => $reply) {
            if (str_contains($message, $keyword)) {
                return $reply;
            }
        }

        // Keyword-driven fallback
        if ($this->containsAny($message, ['start', 'begin', 'sign up', 'sign-up', 'create account', 'new account'])) {
            return $topics['register'];
        }

        return "I can help you get started. Here's what I know best:\n\n"
            . "• **Registration** – How to register your property\n"
            . "• **Login** – How to access your account\n"
            . "• **Fees** – What it costs\n"
            . "• **Documents** – What you need to provide\n"
            . "• **Timeline** – How long approval takes\n\n"
            . "What would you like to know?";
    }

    protected function getGuestNavigationResponse(string $message): string
    {
        $universal = [
            'register' => "Click the **Register Your Land/Property** button on the homepage. It's in the hero section right under the welcome text.",
            'login'    => "Click **Login** in the top-right of the header, or visit `/login`.",
            'contact'  => "Contact info is in the **Contact Us** section near the bottom of this page — email, phone, and office hours.",
            'faq'      => "Browse the **Frequently Asked Questions** section on this page for quick answers.",
        ];

        foreach ($universal as $place => $directions) {
            if (str_contains($message, $place)) {
                return $directions;
            }
        }

        return "Here's how to find things on this page:\n\n"
            . "• **Register** – Purple button in the hero section\n"
            . "• **Login** – Top-right of the header\n"
            . "• **Services** – Scroll down to \"Our Services\"\n"
            . "• **Payment methods** – See the \"Payment Methods\" section\n"
            . "• **FAQ** – Bottom of the page\n"
            . "• **Contact** – \"Contact Us\" section\n\n"
            . "What are you looking for?";
    }

    protected function getGuestProfileResponse(string $message): string
    {
        if ($this->containsAny($message, ['register', 'sign up', 'create account'])) {
            return $this->getGuestHelpResponse('register');
        }

        return "You don't have an account yet — that's where we start!\n\n"
            . "**To create one:**\n"
            . "1. Click **Register Your Land/Property** on the homepage\n"
            . "2. Fill in your name, phone, and property details\n"
            . "3. Upload your ownership document\n"
            . "4. Submit — our team will create your account upon approval\n\n"
            . "Once approved, you'll be able to log in and manage everything from your dashboard.";
    }

    protected function getGuestFeaturesResponse(): string
    {
        return "🚀 **What you can do on this platform:**\n"
            . "• Register vacant land or existing properties\n"
            . "• Submit construction plans for approval\n"
            . "• Register and manage tenants\n"
            . "• Pay estate dues (Mobile Money, bank, card, cash)\n"
            . "• Track property value and analytics\n"
            . "• Get 24/7 support\n\n"
            . "Ready to start? Click **Register Your Land/Property** at the top of the page.";
    }

    protected function getGuestTroubleshootingResponse(string $message): string
    {
        if (str_contains($message, 'login')) {
            return "**Having trouble logging in?**\n\n"
                . "• Double-check your email and password\n"
                . "• Use **Forgot Password** on the login page\n"
                . "• Clear your browser cache and cookies\n"
                . "• Try another browser\n"
                . "• Contact support if the problem persists";
        }

        if ($this->containsAny($message, ['register', 'form', 'submit'])) {
            return "**Having trouble with registration?**\n\n"
                . "• Make sure all required fields are filled (marked with *)\n"
                . "• Confirm your phone number is 10 digits (e.g. 0595652410)\n"
                . "• Upload documents as PDF, JPG, or PNG (max 10 MB each)\n"
                . "• Tick the declaration checkbox before submitting\n"
                . "• If it still fails, contact support with a screenshot";
        }

        if ($this->containsAny($message, ['slow', 'not working', 'error', 'issue', 'bug'])) {
            return "**Something not working?**\n\n"
                . "• Refresh the page\n"
                . "• Clear your browser cache\n"
                . "• Try another browser or device\n"
                . "• If the error persists, contact support with:\n"
                . "  – The exact error message\n"
                . "  – A screenshot\n"
                . "  – What you were trying to do";
        }

        return "I can help troubleshoot common issues:\n\n"
            . "• **Login problems** – Can't access your account\n"
            . "• **Registration issues** – Form or uploads failing\n"
            . "• **Performance** – Slow or unresponsive\n"
            . "• **Errors** – Understanding error messages\n\n"
            . "What's the specific problem?";
    }

    protected function getGuestGoodbyeResponse(): string
    {
        $goodbyes = [
            "Goodbye! When you're ready to register, click **Register Your Land/Property** at the top of the page. 👋",
            "See you soon! I'm here whenever you have more questions. 😊",
            "Bye! Don't hesitate to come back if you need anything.",
        ];

        return $goodbyes[array_rand($goodbyes)];
    }

    protected function getGuestGeneralResponse(string $message): string
    {
        return "I understand you're asking about: \"{$message}\"\n\n"
            . "As a **guest**, here are some things I can help you with:\n\n"
            . "• \"How do I register my property?\"\n"
            . "• \"Is registration free?\"\n"
            . "• \"What documents do I need?\"\n"
            . "• \"How long does approval take?\"\n"
            . "• \"How do I pay estate dues?\"\n"
            . "• \"How do I contact support?\"\n\n"
            . "What would you like to know?";
    }

    /* ============================================================
       AUTHENTICATED — ROLE HELPERS (UNCHANGED)
       ============================================================ */

    protected function roleLabel(User $user): string
    {
        return match ($user->type) {
            User::TYPE_SUPER_ADMIN          => 'Super Administrator',
            User::TYPE_ADMIN                => 'Administrator',
            User::TYPE_LANDLORD             => 'Landlord',
            User::TYPE_TENANT               => 'Tenant',
            User::TYPE_FIELD_AGENT          => 'Field Agent',
            User::TYPE_DEVELOPER            => 'Developer',
            User::TYPE_SECURITY_PERSONNEL   => 'Security Personnel',
            User::TYPE_CONTRACTOR           => 'Contractor',
            User::TYPE_SANITATION_PERSONNEL => 'Sanitation Personnel',
            default                         => 'User',
        };
    }

    protected function roleCapabilitySummary(User $user): string
    {
        return match ($user->type) {
            User::TYPE_SUPER_ADMIN => "You have full system access — user management, system settings, registration plans, payments configuration, and system-wide analytics.",
            User::TYPE_ADMIN       => "You can manage properties, process payments, generate invoices, manage landlords, and view admin analytics.",
            User::TYPE_LANDLORD    => "You can manage your properties, units, tenants, leases, view payment history, and handle maintenance requests.",
            User::TYPE_TENANT      => "You can view your unit details, pay rent, submit maintenance requests, and contact your landlord.",
            User::TYPE_FIELD_AGENT => "You can register properties, view your assigned registration plans, log inspections, and track collections.",
            User::TYPE_DEVELOPER   => "You can monitor system health, run diagnostics, manage backups, view logs, control maintenance mode, and manage developer billing.",
            User::TYPE_SECURITY_PERSONNEL => "You can view your shift schedule, check in/out, report incidents, request swaps, and manage security posts.",
            User::TYPE_CONTRACTOR  => "You can view your construction contracts, update progress, submit milestones, manage workers, and send badges.",
            User::TYPE_SANITATION_PERSONNEL => "You can manage waste collection requests, link properties, manage collection zones, and update personnel.",
            default                => "You can explore the dashboard to access all the features available to you.",
        };
    }

    /* ============================================================
       AUTHENTICATED — PER-INTENT HANDLERS (UNCHANGED)
       ============================================================ */

    protected function getGreetingResponse(User $user): string
    {
        $greetings = [
            "Hello {$user->name}! 👋 I'm your assistant for this application.",
            "Hi {$user->name}! 🤖 I'm here to help you navigate our app.",
            "Welcome back {$user->name}! 🎉 How can I assist you today?",
        ];

        return $greetings[array_rand($greetings)]
            . "\n\nAs a **{$this->roleLabel($user)}**, " . $this->roleCapabilitySummary($user)
            . "\n\nI can help you with:\n"
            . "• Navigating through the app\n"
            . "• Using role-specific features\n"
            . "• Account and profile settings\n"
            . "• Troubleshooting issues\n\n"
            . "What would you like to know about?";
    }

    protected function getHelpResponse(string $message, User $user): string
    {
        $universal = [
            'register' => "To register for a new account:\n\n1. Click the 'Register' button on the homepage\n2. Fill in your details (name, email, password)\n3. Verify your email by clicking the link sent to your inbox\n4. Complete your profile setup\n5. Start using the application!",
            'login'    => "To login to your account:\n\n1. Go to /login\n2. Enter your registered email address\n3. Enter your password\n4. Click 'Sign In'\n5. If you forgot your password, use 'Forgot Password' to reset it",
            'dashboard' => "The dashboard is your main control panel. You can:\n• View your personal statistics and overview\n• Access quick actions\n• See recent activity\n• Navigate to other sections\n• Access your profile and settings",
            'profile'  => "To update your profile:\n\n1. Click your profile picture/username in the top nav\n2. Select 'Profile Settings' or 'Edit Profile'\n3. Update your personal information\n4. Upload a new profile picture\n5. Click 'Save Changes'",
            'password' => "To change your password:\n\n1. Go to Profile Settings\n2. Click the 'Security' or 'Change Password' tab\n3. Enter your current password\n4. Enter a strong new password\n5. Confirm the new password\n6. Click 'Update Password'",
        ];

        foreach ($universal as $topic => $helpText) {
            if (str_contains($message, $topic)) {
                return $helpText;
            }
        }

        $roleTopics = $this->getRoleSpecificHelpTopics($user);
        foreach ($roleTopics as $topic => $helpText) {
            if (str_contains($message, $topic)) {
                return $helpText;
            }
        }

        return "I can help you with various topics. Here are some common ones:\n\n"
            . "• **Registration** – Create a new account\n"
            . "• **Login** – Access your account\n"
            . "• **Dashboard** – Understand your main dashboard\n"
            . "• **Profile** – Update your information\n"
            . "• **Password** – Change your password\n\n"
            . "As a **{$this->roleLabel($user)}**, you also have access to role-specific help. "
            . "What specific feature do you need help with?";
    }

    protected function getRoleSpecificHelpTopics(User $user): array
    {
        return match ($user->type) {
            User::TYPE_SUPER_ADMIN => [
                'system settings' => "To manage system settings:\n\n1. Go to **Admin → System Settings**\n2. Configure email, WhatsApp, payment, logo, and notification channels\n3. Save each section separately\n4. Changes apply immediately to all users",
                'payment provider' => "To configure payment providers:\n\n1. Go to **Admin → Payment Providers**\n2. Toggle providers on/off (Paystack, Hubtel, ExpressPay, Flutterwave, MTN MoMo, Telecel, AirtelTigo)\n3. Enter API keys and secrets\n4. Test the connection\n5. Set a default provider",
                'registration plan' => "Registration plans control property numbering. Manage them under **Admin → Registration Plans** — you can create plans, assign field agents, track progress, and enforce global sequences.",
            ],
            User::TYPE_ADMIN => [
                'properties' => "To manage properties:\n\n1. Go to **Admin → Properties**\n2. View, filter, and export the list\n3. Click a property to see units, tenants, and financials\n4. Use **Trash** to restore deleted properties",
                'invoice'    => "To generate invoices:\n\n1. Go to **Admin → Invoices**\n2. Click **Generate Monthly** for automatic billing\n3. Or use **Create Invoice** for manual entries\n4. Track payment status and send reminders",
                'landlord'   => "To manage landlords:\n\n1. Go to **Admin → Users → filter by Landlord**\n2. View their properties and tenants\n3. Send invitations or reset passwords\n4. Assign landlord role to existing users",
            ],
            User::TYPE_LANDLORD => [
                'payment'  => "To make a payment:\n\n1. Go to **Landlord → Payments → Make Payment**\n2. Select the property\n3. Choose a payment method\n4. Complete the payment\n5. Verify via the confirmation page",
                'property' => "Your properties are under **Landlord → Properties**. Click any property to view units, tenants, leases, financials, and maintenance.",
                'tenant'   => "To manage tenants:\n\n1. Go to **Landlord → Tenants**\n2. Invite a new tenant or assign one to a unit\n3. Track their payment and lease status",
            ],
            User::TYPE_TENANT => [
                'rent'        => "To pay rent:\n\n1. Go to **Tenant → Invoices**\n2. Find the outstanding invoice\n3. Click **Pay Now**\n4. Choose a payment method\n5. Verify the payment",
                'maintenance' => "To report a maintenance issue:\n\n1. Go to **Tenant → Maintenance → Create**\n2. Describe the issue and attach photos\n3. Submit — your landlord will be notified",
                'landlord'    => "To contact your landlord:\n\n1. Go to **Tenant → My Unit → Contact Landlord**\n2. Send a message through the in-app form",
            ],
            User::TYPE_FIELD_AGENT => [
                'property'   => "To register a property:\n\n1. Go to **Field Agent → Properties → Create**\n2. Fill in the property details\n3. Attach photos and documents\n4. Submit for verification",
                'plan'       => "Your assigned registration plans are under **Field Agent → Registration Plans**. Click a plan to see your quota and progress.",
                'inspection' => "To log an inspection:\n\n1. Go to **Field Agent → Inspections → Create**\n2. Select the property\n3. Record findings and photos\n4. Submit the report",
            ],
            User::TYPE_DEVELOPER => [
                'system health' => "System health is on **Developer → Health**. You can view overall status, server metrics, database health, application performance, and security checks.",
                'log'           => "Logs are on **Developer → Logs**. Filter by level (error, warning, info), search, or export to CSV/JSON/PDF.",
                'backup'        => "To run a backup:\n\n1. Go to **Developer → Tools → Backup Database**\n2. Or use the CLI: `php artisan backup:run`\n3. Backups are listed under **Developer → Backup → History**",
                'maintenance'   => "To enable maintenance mode:\n\n1. Go to **Developer → Maintenance → Create**\n2. Schedule the window and affected modules\n3. Users see a maintenance banner during the window",
            ],
            User::TYPE_SECURITY_PERSONNEL => [
                'schedule' => "Your shifts are on **Security → Schedules**. Click any shift to view details, check in/out, or request a swap.",
                'check'    => "To check in or out:\n\n1. Open the shift from **Security → Schedules**\n2. Click **Check In** (or **Smart Check In** with QR)\n3. Your attendance is logged automatically",
                'incident' => "To report an incident:\n\n1. Open your active shift\n2. Click **Report Issue**\n3. Describe the incident and attach evidence\n4. Your supervisor is notified immediately",
            ],
            User::TYPE_CONTRACTOR => [
                'contract'  => "Your contracts are on **Contractor → Contracts**. Click one to update progress, submit milestones, and view the timeline.",
                'worker'    => "Manage workers under **Contractor → Contracts → [Contract] → Workers**. You can add, edit, assign to site, and send badges.",
                'milestone' => "To submit a milestone:\n\n1. Open the contract\n2. Go to the milestone list\n3. Click **Submit** on the milestone\n4. Upload evidence (photos, files)\n5. Submit for approval",
                'badge'     => "To send worker badges:\n\n1. Go to the workers list for a contract\n2. Select workers\n3. Click **Send Badges** — each worker receives an email/SMS with a QR badge link",
            ],
            User::TYPE_SANITATION_PERSONNEL => [
                'request'   => "Collection requests are on **Sanitation → Requests**. Filter by pending, assigned, or completed and update status as you go.",
                'zone'      => "Collection zones are on **Sanitation → Zones**. Create zones, assign personnel, and track coverage.",
                'link'      => "To link a property:\n\n1. Go to **Sanitation → Properties → Available**\n2. Select properties and click **Link**\n3. Or approve a landlord's service request from **Approvals → Pending**",
                'personnel' => "Manage personnel on **Sanitation → Personnel**. Add workers, assign zones, and track their live location.",
            ],
            default => [],
        };
    }

    protected function getNavigationResponse(string $message, User $user): string
    {
        $universal = [
            'dashboard' => "Your dashboard is at /dashboard. As a **{$this->roleLabel($user)}**, you can also use the role shortcut in the top navigation.",
            'profile'   => "To reach your profile:\n• Click your username/avatar in the top right\n• Select 'My Profile'\n• Or visit /profile",
            'settings'  => "Application settings:\n• Click the gear icon ⚙️ or your avatar\n• Select 'Settings'\n• Or visit /settings",
        ];

        foreach ($universal as $place => $directions) {
            if (str_contains($message, $place)) {
                return $directions;
            }
        }

        $roleNav = match ($user->type) {
            User::TYPE_SUPER_ADMIN => [
                'system settings'    => "Super Admin → System Settings (/admin/system-settings)",
                'users'              => "Super Admin → Users (/admin/users)",
                'registration plans' => "Super Admin → Registration Plans (/registration-plans)",
            ],
            User::TYPE_ADMIN => [
                'properties' => "Admin → Properties (/admin/properties)",
                'invoices'   => "Admin → Invoices (/admin/invoices)",
                'payments'   => "Admin → Payments (/admin/payments)",
            ],
            User::TYPE_LANDLORD => [
                'properties' => "Landlord → Properties (/landlord/properties)",
                'tenants'    => "Landlord → Tenants (/landlord/tenants)",
                'payments'   => "Landlord → Payments (/landlord/payments/history)",
            ],
            User::TYPE_TENANT => [
                'invoices'    => "Tenant → Invoices (/tenant/invoices)",
                'maintenance' => "Tenant → Maintenance (/tenant/maintenance)",
                'unit'        => "Tenant → My Unit (/tenant/property-units/my-unit)",
            ],
            User::TYPE_FIELD_AGENT => [
                'properties' => "Field Agent → Properties (/field-agent/properties)",
                'plans'      => "Field Agent → Registration Plans (/field-agent/registration-plans)",
            ],
            User::TYPE_DEVELOPER => [
                'health'   => "Developer → Health (/developer/health)",
                'logs'     => "Developer → Logs (/developer/logs)",
                'backup'   => "Developer → Backup (/developer/backup/history)",
                'settings' => "Developer → Settings (/developer/settings)",
            ],
            User::TYPE_SECURITY_PERSONNEL => [
                'schedules' => "Security → Schedules (/security/schedules)",
                'posts'     => "Security → Posts (/security/posts)",
            ],
            User::TYPE_CONTRACTOR => [
                'contracts' => "Contractor → Contracts (/contractor/contracts)",
                'workers'   => "Contractor → Workers (/contractor/workers)",
                'calendar'  => "Contractor → Calendar (/contractor/calendar)",
            ],
            User::TYPE_SANITATION_PERSONNEL => [
                'requests'  => "Sanitation → Requests (/sanitation/requests)",
                'zones'     => "Sanitation → Zones (/sanitation/zones)",
                'personnel' => "Sanitation → Personnel (/sanitation/personnel)",
            ],
            default => [],
        };

        foreach ($roleNav as $place => $path) {
            if (str_contains($message, $place)) {
                return "Here's where to find **{$place}**:\n• {$path}";
            }
        }

        return "I can guide you to different parts of the application:\n\n"
            . "• **Dashboard** – Your main overview\n"
            . "• **Profile** – Personal information\n"
            . "• **Settings** – Application preferences\n\n"
            . "As a **{$this->roleLabel($user)}**, you can also ask me about role-specific pages. Where would you like to go?";
    }

    protected function getProfileResponse(string $message, User $user): string
    {
        if (str_contains($message, 'update') || str_contains($message, 'edit')) {
            return "To update your profile:\n\n"
                . "1. Click your profile picture/name in the top right\n"
                . "2. Select 'Edit Profile'\n"
                . "3. Update any of the following:\n"
                . "   - Name: {$user->name}\n"
                . "   - Email: {$user->email}\n"
                . "   - Profile picture\n"
                . "   - Contact information\n"
                . "4. Click 'Save Changes'\n\n"
                . "Your changes apply immediately.";
        }

        if (str_contains($message, 'password')) {
            return "To change your password securely:\n\n"
                . "1. Go to **Settings → Security** (or **Profile → Password**)\n"
                . "2. Click **Change Password**\n"
                . "3. Enter your current password\n"
                . "4. Enter a new strong password (min 8 characters)\n"
                . "5. Confirm the new password\n"
                . "6. Click **Update Password**\n\n"
                . "You may be logged out and need to sign in again.";
        }

        if (str_contains($message, 'photo') || str_contains($message, 'picture') || str_contains($message, 'avatar')) {
            return "To update your profile photo:\n\n"
                . "1. Go to **Profile → Edit**\n"
                . "2. Click the avatar area\n"
                . "3. Upload a JPG or PNG (max 2 MB)\n"
                . "4. Crop if needed and save";
        }

        return "I can help you with profile management!\n\n"
            . "• **View Profile** – See your current info\n"
            . "• **Edit Profile** – Update name, email, and details\n"
            . "• **Change Password** – Update security credentials\n"
            . "• **Profile Picture** – Upload or change your avatar\n\n"
            . "What would you like to do with your profile?";
    }

    protected function getFeaturesResponse(User $user): string
    {
        $roleFeatures = match ($user->type) {
            User::TYPE_SUPER_ADMIN => "👑 **Super Admin Features**\n"
                . "• System-wide settings and configuration\n"
                . "• User, landlord, and tenant management\n"
                . "• Registration plan management\n"
                . "• Payment & SMS provider configuration\n"
                . "• System analytics and reporting\n"
                . "• Property ownership transfers",

            User::TYPE_ADMIN => "🛠️ **Admin Features**\n"
                . "• Property, unit, and lease management\n"
                . "• Invoice generation and payment processing\n"
                . "• Landlord and tenant support\n"
                . "• Bulk approvals and exports\n"
                . "• Financial reporting",

            User::TYPE_LANDLORD => "🏠 **Landlord Features**\n"
                . "• Property, unit, and tenant management\n"
                . "• Lease creation and e-signing\n"
                . "• Rent invoicing and payment tracking\n"
                . "• Maintenance request handling\n"
                . "• Ownership transfer requests",

            User::TYPE_TENANT => "🏡 **Tenant Features**\n"
                . "• View your unit and lease\n"
                . "• Pay rent online\n"
                . "• Submit maintenance requests\n"
                . "• Contact your landlord\n"
                . "• Download invoices and receipts",

            User::TYPE_FIELD_AGENT => "📋 **Field Agent Features**\n"
                . "• Register new properties\n"
                . "• View assigned registration plans\n"
                . "• Log field inspections\n"
                . "• Track payment collections\n"
                . "• Generate field reports",

            User::TYPE_DEVELOPER => "💻 **Developer Features**\n"
                . "• System health monitoring\n"
                . "• Log viewer and diagnostics\n"
                . "• Backup and restore tools\n"
                . "• Maintenance and emergency modes\n"
                . "• Developer billing & Super Admin management",

            User::TYPE_SECURITY_PERSONNEL => "🛡️ **Security Personnel Features**\n"
                . "• Shift schedule and check-in/out\n"
                . "• Security post management\n"
                . "• Incident reporting\n"
                . "• Shift swaps and handovers\n"
                . "• Availability preferences",

            User::TYPE_CONTRACTOR => "🏗️ **Contractor Features**\n"
                . "• View assigned construction contracts\n"
                . "• Progress updates and milestones\n"
                . "• Worker management and badges\n"
                . "• Project calendar and reports\n"
                . "• Site assignment tracking",

            User::TYPE_SANITATION_PERSONNEL => "🧹 **Sanitation Features**\n"
                . "• Waste collection requests\n"
                . "• Property linking & approvals\n"
                . "• Collection zone management\n"
                . "• Personnel and worker tracking\n"
                . "• Live map view",

            default => "🚀 **Application Features**\n"
                . "• Dashboard overview\n"
                . "• Profile management\n"
                . "• Secure authentication\n"
                . "• Notifications\n"
                . "• Settings and preferences",
        };

        return $roleFeatures . "\n\nWhich feature would you like to learn more about?";
    }

    protected function getTroubleshootingResponse(string $message, User $user): string
    {
        $solutions = [
            'login' => "If you're having trouble logging in:\n\n"
                . "• **Check credentials**: Ensure email and password are correct\n"
                . "• **Forgot password**: Use 'Reset Password'\n"
                . "• **Clear cache**: Clear browser cache and cookies\n"
                . "• **Try another browser**: Extensions can interfere\n"
                . "• **Check internet**: Ensure a stable connection\n"
                . "• **Contact support**: If all else fails, reach out to us",

            'slow' => "If the application is running slow:\n\n"
                . "• **Check connection**: Verify your internet speed\n"
                . "• **Clear cache**: Clear browser cache\n"
                . "• **Close tabs**: Too many tabs slow things down\n"
                . "• **Update browser**: Use the latest version\n"
                . "• **Try later**: Server may be under high load\n"
                . "• **Report issue**: Let us know if it persists",

            'error' => "If you encounter an error:\n\n"
                . "• **Note the error**: Write down the exact message\n"
                . "• **Refresh page**: Try reloading first\n"
                . "• **Check permissions**: Ensure you have access\n"
                . "• **Try later**: The issue may be temporary\n"
                . "• **Report**: Send us the error with a screenshot",
        ];

        foreach ($solutions as $problem => $solution) {
            if (str_contains($message, $problem)) {
                return $solution;
            }
        }

        return "I can help troubleshoot common issues:\n\n"
            . "• **Login Problems** – Can't access your account\n"
            . "• **Performance Issues** – Slow or unresponsive\n"
            . "• **Error Messages** – Understanding and fixing errors\n"
            . "• **Feature Issues** – Something isn't working\n\n"
            . "What specific problem are you experiencing?";
    }

    protected function getGoodbyeResponse(User $user): string
    {
        $goodbyes = [
            "Goodbye, {$user->name}! Feel free to come back if you have more questions!",
            "See you later, {$user->name}! I'm here whenever you need help!",
            "Bye, {$user->name}! Don't hesitate to ask if you need assistance!",
        ];

        return $goodbyes[array_rand($goodbyes)];
    }

    protected function getGeneralResponse(string $message, User $user): string
    {
        return "I understand you're asking about: \"{$message}\"\n\n"
            . "I'm specifically designed to help you use this application as a **{$this->roleLabel($user)}**. "
            . "Here are some things you can ask me:\n\n"
            . "• \"How do I update my profile?\"\n"
            . "• \"Where can I find the dashboard?\"\n"
            . "• \"How do I change my password?\"\n"
            . "• \"What features are available to me?\"\n"
            . "• \"I'm having trouble logging in\"\n\n"
            . "What would you like to know about using our application?";
    }

    /* ============================================================
       INTERNAL UTILITIES
       ============================================================ */

    /**
     * ⭐ NEW: Case-insensitive "contains any of these needles" check
     * using word boundaries to avoid false positives like "hi" in "which".
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/iu';
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }
        return false;
    }
}