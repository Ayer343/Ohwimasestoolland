<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DeveloperEmailAudit;
use App\Models\User;

class DeveloperEmailAuditFactory extends Factory
{
    protected $model = DeveloperEmailAudit::class;

    public function definition(): array
    {
        $actions = ['update', 'test', 'reset', 'fix', 'verification'];
        $statuses = ['pending', 'processing', 'success', 'failed', 'cancelled'];
        
        return [
            'user_id' => User::factory(),
            'job_id' => 'job_' . $this->faker->uuid,
            'action' => $this->faker->randomElement($actions),
            'status' => $this->faker->randomElement($statuses),
            'message' => $this->faker->sentence,
            'old_configuration' => [
                'host' => $this->faker->domainName,
                'port' => $this->faker->numberBetween(1, 65535),
                'username' => $this->faker->email,
                'encryption' => $this->faker->randomElement(['tls', 'ssl', 'none']),
            ],
            'new_configuration' => [
                'host' => $this->faker->domainName,
                'port' => $this->faker->numberBetween(1, 65535),
                'username' => $this->faker->email,
                'encryption' => $this->faker->randomElement(['tls', 'ssl', 'none']),
            ],
            'metadata' => [
                'app_env' => config('app.env'),
                'timestamp' => now()->toISOString(),
            ],
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'execution_time_ms' => $this->faker->numberBetween(100, 5000),
            'attempts' => $this->faker->numberBetween(0, 3),
            'started_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function successful(): self
    {
        return $this->state([
            'status' => 'success',
        ]);
    }

    public function failed(): self
    {
        return $this->state([
            'status' => 'failed',
            'error_details' => $this->faker->sentence,
            'error_type' => 'Exception',
        ]);
    }

    public function pending(): self
    {
        return $this->state([
            'status' => 'pending',
            'completed_at' => null,
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }

    public function withAction(string $action): self
    {
        return $this->state([
            'action' => $action,
        ]);
    }

    public function withConfigurationChanges(array $oldConfig, array $newConfig): self
    {
        return $this->state([
            'old_configuration' => $oldConfig,
            'new_configuration' => $newConfig,
        ]);
    }
}