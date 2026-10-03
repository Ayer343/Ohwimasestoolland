<?php

namespace Database\Factories;

use App\Models\TenantInvitation;
use App\Models\Tenant;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantInvitationFactory extends Factory
{
    protected $model = TenantInvitation::class;

    public function definition()
    {
        $status = $this->faker->randomElement([
            TenantInvitation::STATUS_PENDING,
            TenantInvitation::STATUS_SENT,
            TenantInvitation::STATUS_COMPLETED,
            TenantInvitation::STATUS_FAILED,
        ]);

        $channels = $this->faker->randomElements(
            [TenantInvitation::CHANNEL_SMS, TenantInvitation::CHANNEL_EMAIL, TenantInvitation::CHANNEL_WHATSAPP],
            $this->faker->numberBetween(1, 3)
        );

        return [
            'tenant_id' => Tenant::factory(),
            'property_id' => Property::factory(),
            'invited_by' => User::factory(),
            'channels' => $channels,
            'token' => $this->faker->sha1,
            'status' => $status,
            'sent_channels' => $status === TenantInvitation::STATUS_SENT ? $channels : null,
            'sent_at' => $status === TenantInvitation::STATUS_SENT ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'completed_at' => $status === TenantInvitation::STATUS_COMPLETED ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'registered_user_id' => $status === TenantInvitation::STATUS_COMPLETED ? User::factory() : null,
            'failure_reason' => $status === TenantInvitation::STATUS_FAILED ? $this->faker->sentence() : null,
            'metadata' => $this->faker->boolean(30) ? ['custom_message' => $this->faker->sentence()] : null,
        ];
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => TenantInvitation::STATUS_PENDING,
                'sent_channels' => null,
                'sent_at' => null,
                'completed_at' => null,
                'registered_user_id' => null,
                'failure_reason' => null,
            ];
        });
    }

    public function sent()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => TenantInvitation::STATUS_SENT,
                'sent_channels' => $attributes['channels'],
                'sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'completed_at' => null,
                'registered_user_id' => null,
                'failure_reason' => null,
            ];
        });
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => TenantInvitation::STATUS_COMPLETED,
                'sent_channels' => $attributes['channels'],
                'sent_at' => $this->faker->dateTimeBetween('-2 weeks', '-1 week'),
                'completed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'registered_user_id' => User::factory(),
                'failure_reason' => null,
            ];
        });
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => TenantInvitation::STATUS_EXPIRED,
                'expires_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            ];
        });
    }

    public function failed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => TenantInvitation::STATUS_FAILED,
                'failure_reason' => $this->faker->sentence(),
                'sent_channels' => [],
                'sent_at' => null,
                'completed_at' => null,
                'registered_user_id' => null,
            ];
        });
    }
}