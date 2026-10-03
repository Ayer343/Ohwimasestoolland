<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class SmsProviderConfigurationTest extends TestCase
{
    public function test_it_can_save_a_new_arkesel_sender_id(): void
    {
        $before = $this->readEnvKey('ARKESEL_SMS_SENDER_ID');

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.sms-providers.configure'), [
                'provider'  => 'arkesel',
                'enabled'   => 1,
                'sender_id' => 'TestSender123',
                'api_key'   => 'dummy-api-key',
                'base_url'  => 'https://sms.arkesel.com',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $after = $this->readEnvKey('ARKESEL_SMS_SENDER_ID');

        $this->assertEquals('TestSender123', $after);
        $this->assertNotEquals($before, $after);
    }

    protected function readEnvKey(string $key): ?string
    {
        $content = File::get(base_path('.env'));
        return preg_match("/^{$key}=(.*)$/m", $content, $m)
            ? trim($m[1], "\"'")
            : null;
    }
}