// tests/Feature/Security/QrCodeAccessTest.php
public function test_security_personnel_with_permission_can_access_qr_codes(): void
{
    $supervisor = User::factory()->securityPersonnel()->create();
    $supervisor->givePermissionTo('manage-security-schedules');

    $post = SecurityPost::factory()->create();

    $this->actingAs($supervisor)
        ->get(route('security.posts.qr-codes.index', $post))
        ->assertOk()
        ->assertViewIs('security.posts.qr-codes.index');
}

public function test_security_personnel_without_permission_gets_403(): void
{
    $supervisor = User::factory()->securityPersonnel()->create();
    $post = SecurityPost::factory()->create();

    $this->actingAs($supervisor)
        ->get(route('security.posts.qr-codes.index', $post))
        ->assertForbidden();
}