<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserActivityFactory extends Factory
{
    protected $model = UserActivity::class;

    public function definition(): array
    {
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        
        $activityTypes = array_keys(UserActivity::getActivityTypes());
        $levels = array_keys(UserActivity::getActivityLevels());
        $scopes = array_keys(UserActivity::getActivityScopes());

        return [
            'user_id' => $user->id,
            'activity_type' => $this->faker->randomElement($activityTypes),
            'description' => $this->faker->sentence(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'device_info' => [
                'device_type' => $this->faker->randomElement(['mobile', 'tablet', 'desktop']),
                'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                'os' => $this->faker->randomElement(['Windows', 'Mac OS', 'Linux', 'Android', 'iOS']),
            ],
            'location' => $this->faker->city(),
            'metadata' => [
                'random_data' => $this->faker->word(),
                'additional_info' => $this->faker->sentence(),
            ],
            'level' => $this->faker->randomElement($levels),
            'scope' => $this->faker->randomElement($scopes),
            'performed_by' => User::inRandomOrder()->first()->id ?? $user->id,
            'is_system' => $this->faker->boolean(20),
            'read_at' => $this->faker->optional(0.3)->dateTimeThisMonth(),
            'archived_at' => $this->faker->optional(0.1)->dateTimeThisMonth(),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function login(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'activity_type' => UserActivity::TYPE_LOGIN,
                'description' => 'User logged into the system',
                'level' => UserActivity::LEVEL_INFO,
                'scope' => UserActivity::SCOPE_SYSTEM,
                'is_system' => true,
            ];
        });
    }

    public function profileUpdate(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'activity_type' => UserActivity::TYPE_PROFILE_UPDATE,
                'description' => 'User updated their profile',
                'level' => UserActivity::LEVEL_INFO,
                'scope' => UserActivity::SCOPE_USER,
                'is_system' => false,
            ];
        });
    }

    public function propertyRegistered(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'activity_type' => UserActivity::TYPE_PROPERTY_REGISTERED,
                'description' => 'User registered a property',
                'level' => UserActivity::LEVEL_SUCCESS,
                'scope' => UserActivity::SCOPE_USER,
                'is_system' => false,
            ];
        });
    }

    public function invitationSent(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'activity_type' => UserActivity::TYPE_INVITATION_SENT,
                'description' => 'Invitation sent to user',
                'level' => UserActivity::LEVEL_INFO,
                'scope' => UserActivity::SCOPE_ADMIN,
                'is_system' => false,
            ];
        });
    }

    public function systemAction(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'activity_type' => UserActivity::TYPE_SYSTEM_ACTION,
                'description' => 'System action performed',
                'level' => UserActivity::LEVEL_INFO,
                'scope' => UserActivity::SCOPE_SYSTEM,
                'is_system' => true,
            ];
        });
    }

    public function unread(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => null,
            ];
        });
    }

    public function read(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => now(),
            ];
        });
    }

    public function archived(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'archived_at' => now(),
            ];
        });
    }

    public function recent(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    public function old(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-1 year', '-6 months'),
            ];
        });
    }
}