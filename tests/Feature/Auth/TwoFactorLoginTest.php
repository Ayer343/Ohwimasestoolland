<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Authentication\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected TwoFactorAuthService $twoFactorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twoFactorService = app(TwoFactorAuthService::class);

        // Clear all rate limiters so tests are isolated
        foreach (['2fa_verify', '2fa_resend', '2fa_setup', '2fa_password'] as $prefix) {
            RateLimiter::clear($prefix);
        }
    }

    /* ============================================================
     | Helpers
     * ============================================================ */

    protected function createUserWith2fa(string $password = 'TestPassword123!'): User
    {
        $secret = $this->twoFactorService->generateSecret();

        return User::factory()->create([
            'type'                  => User::TYPE_DEVELOPER,
            'status'                => 'active',
            'password'              => Hash::make($password),
            'two_factor_secret'     => $secret,
            'two_factor_enabled'    => true,
            'two_factor_enabled_at' => now(),
        ]);
    }

    protected function validCodeFor(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);
    }

    /**
     * ✅ FIXED: drive the login form to establish the 2FA handoff
     * session state. This replaces the fragile
     * `withSession(['2fa:user_id' => ...])` pattern that caused
     * intermittent test failures.
     *
     * Under the hood:
     *   - POSTs to /login with the user's credentials
     *   - Asserts the redirect to /2fa/verify
     *   - The test client carries the session forward automatically
     *     (session driver, cookie jar, everything)
     *
     * After this call, the next POST to /2fa/verify.submit will see
     * `session('2fa:user_id')` correctly set.
     */
    protected function beginTwoFactorHandoff(User $user, string $password = 'TestPassword123!'): void
    {
        $this->post('/login', [
            'login'    => $user->email,
            'password' => $password,
        ])->assertRedirect(route('2fa.verify'));
    }

    /* ============================================================
     | 1. Login phase
     * ============================================================ */

    /** @test */
    public function login_without_2fa_succeeds_and_authenticates_user(): void
    {
        $user = User::factory()->create([
            'type'               => User::TYPE_TENANT,
            'status'             => 'active',
            'password'           => Hash::make('TestPassword123!'),
            'two_factor_enabled' => false,
        ]);

        $response = $this->post('/login', [
            'login'    => $user->email,
            'password' => 'TestPassword123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    /** @test */
    public function login_with_2fa_enabled_redirects_to_verify_page(): void
    {
        $user = $this->createUserWith2fa();

        $response = $this->post('/login', [
            'login'    => $user->email,
            'password' => 'TestPassword123!',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('2fa.verify'));
        $response->assertSessionHas('2fa:user_id', $user->id);
    }

    /** @test */
    public function login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create([
            'type'     => User::TYPE_TENANT,
            'status'   => 'active',
            'password' => Hash::make('TestPassword123!'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'login'    => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('login');
    }

    /** @test */
    public function login_rate_limits_after_five_failures(): void
    {
        $user = User::factory()->create([
            'type'     => User::TYPE_TENANT,
            'status'   => 'active',
            'password' => Hash::make('TestPassword123!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'login'    => $user->email,
                'password' => 'WrongPassword!',
            ]);
        }

        $response = $this->post('/login', [
            'login'    => $user->email,
            'password' => 'TestPassword123!',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    /* ============================================================
     | 2. Verify page access
     * ============================================================ */

    /** @test */
    public function verify_page_requires_2fa_session(): void
    {
        $response = $this->get(route('2fa.verify'));
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function verify_page_renders_with_valid_2fa_session(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        $response = $this->get(route('2fa.verify'));

        $response->assertOk();
        $response->assertViewIs('auth.two-factor-verify');
    }

    /* ============================================================
     | 3. Verify — code submission
     * ============================================================ */

    /** @test */
    public function valid_totp_code_authenticates_user(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        $code = $this->validCodeFor($user);

        $response = $this->post(route('2fa.verify.submit'), [
            'code' => $code,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    /** @test */
    public function invalid_code_rejects_and_keeps_guest(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        $response = $this->post(route('2fa.verify.submit'), [
            'code' => '000000',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    /** @test */
    public function five_invalid_codes_lock_out_the_session(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        $lastResponse = null;

        for ($i = 0; $i < 5; $i++) {
            $lastResponse = $this->post(route('2fa.verify.submit'), [
                'code' => '000000',
            ]);
        }

        $lastResponse->assertSessionMissing('2fa:user_id');
        $this->assertGuest();
    }

    /* ============================================================
     | 4. Resend
     * ============================================================ */

    /** @test */
    public function resend_without_session_returns_401(): void
    {
        $response = $this->postJson(route('2fa.resend'));

        $response->assertStatus(401);
        $response->assertJson(['error' => 'session_expired']);
    }

    /** @test */
    public function resend_with_session_succeeds(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        $response = $this->postJson(route('2fa.resend'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function resend_rate_limits_after_three_attempts(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('2fa.resend'));
        }

        $response = $this->postJson(route('2fa.resend'));

        $response->assertStatus(429);
    }

    /* ============================================================
     | 5. Trusted device
     * ============================================================ */

    /** @test */
    public function trusting_a_device_sets_the_cookie(): void
    {
        $user = $this->createUserWith2fa();

        $this->beginTwoFactorHandoff($user);

        $code = $this->validCodeFor($user);

        $response = $this->post(route('2fa.verify.submit'), [
            'code'         => $code,
            'trust_device' => true,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertCookie('trusted_device_token');
    }

    /** @test */
    public function second_login_with_trusted_device_skips_2fa(): void
    {
        $user = $this->createUserWith2fa();

        // First login with trust
        $this->beginTwoFactorHandoff($user);

        $code = $this->validCodeFor($user);
        $first = $this->post(route('2fa.verify.submit'), [
            'code'         => $code,
            'trust_device' => true,
        ]);

        $cookie = $first->getCookie('trusted_device_token');
        $this->assertNotNull($cookie, 'trusted_device_token cookie was not set');
        $token = $cookie->getValue();

        // Logout
        $this->post('/logout');

        // Second login with the trusted cookie
        $response = $this->withCookie('trusted_device_token', $token)
            ->post('/login', [
                'login'    => $user->email,
                'password' => 'TestPassword123!',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect();
    }

    /** @test */
    public function tampered_trusted_device_cookie_still_requires_2fa(): void
    {
        $user = $this->createUserWith2fa();

        $response = $this->withCookie('trusted_device_token', 'bogus-token')
            ->post('/login', [
                'login'    => $user->email,
                'password' => 'TestPassword123!',
            ]);

        $this->assertGuest();
        $response->assertRedirect(route('2fa.verify'));
    }

    /* ============================================================
     | 6. 2FA setup
     * ============================================================ */

    /** @test */
    public function setup_page_is_accessible_for_authenticated_users(): void
    {
        $user = User::factory()->create([
            'type'               => User::TYPE_DEVELOPER,
            'status'             => 'active',
            'two_factor_enabled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('2fa.setup'));

        $response->assertOk();
        $response->assertViewIs('auth.two-factor-setup');
        $response->assertSessionHas('2fa:pending_secret');
    }

    /** @test */
    public function enabling_2fa_with_valid_code_persists_secret(): void
    {
        $user = User::factory()->create([
            'type'               => User::TYPE_DEVELOPER,
            'status'             => 'active',
            'two_factor_enabled' => false,
        ]);

        $secret = $this->twoFactorService->generateSecret();
        $code   = app(Google2FA::class)->getCurrentOtp($secret);

        $this->actingAs($user)
            ->withSession(['2fa:pending_secret' => $secret])
            ->post(route('2fa.enable'), [
                'verification_code' => $code,
            ]);

        $user->refresh();
        $this->assertTrue((bool) $user->two_factor_enabled);
        $this->assertEquals($secret, $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_backup_codes);
    }

    /** @test */
    public function enabling_2fa_with_invalid_code_does_not_persist_secret(): void
    {
        $user = User::factory()->create([
            'type'               => User::TYPE_DEVELOPER,
            'status'             => 'active',
            'two_factor_enabled' => false,
        ]);

        $secret = $this->twoFactorService->generateSecret();

        $this->actingAs($user)
            ->withSession(['2fa:pending_secret' => $secret])
            ->post(route('2fa.enable'), [
                'verification_code' => '000000',
            ]);

        $user->refresh();
        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
    }

    /* ============================================================
     | 7. Backup codes
     * ============================================================ */

    /** @test */
    public function backup_code_can_be_used_in_place_of_totp(): void
    {
        $user = $this->createUserWith2fa();

        // Generate a fresh set of backup codes and grab one.
        $codes = $this->twoFactorService->generateBackupCodes($user);
        $backupCode = $codes[0];

        // Establish the 2FA handoff session via the real login flow.
        $this->beginTwoFactorHandoff($user);

        // Submit the backup code.
        $response = $this->post(route('2fa.verify.submit'), [
            'code' => $backupCode,
        ]);

        $this->assertAuthenticatedAs($user);

        // Verify the code was consumed — a second use should fail.
        $this->post('/logout');

        $this->beginTwoFactorHandoff($user);

        $this->post(route('2fa.verify.submit'), [
            'code' => $backupCode,
        ]);

        $this->assertGuest();
    }

    /* ============================================================
     | 8. Archival
     * ============================================================ */

    /** @test */
    public function archived_user_sees_archival_notice(): void
    {
        $user = User::factory()->create([
            'type'               => User::TYPE_TENANT,
            'status'             => 'archived',
            'password'           => Hash::make('TestPassword123!'),
            'two_factor_enabled' => false,
        ]);

        $response = $this->post('/login', [
            'login'    => $user->email,
            'password' => 'TestPassword123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHas('archived_tenant_account');
    }
}