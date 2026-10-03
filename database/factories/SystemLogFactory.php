<?php

namespace Database\Factories;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemLogFactory extends Factory
{
    protected $model = SystemLog::class;

    public function definition()
    {
        $levels = [
            SystemLog::LEVEL_INFO,
            SystemLog::LEVEL_WARNING,
            SystemLog::LEVEL_ERROR,
            SystemLog::LEVEL_DEBUG
        ];
        
        $sources = [
            SystemLog::SOURCE_APPLICATION,
            SystemLog::SOURCE_API,
            SystemLog::SOURCE_DATABASE,
            SystemLog::SOURCE_PAYMENT,
            SystemLog::SOURCE_SMS,
            SystemLog::SOURCE_EMAIL
        ];
        
        $level = $this->faker->randomElement($levels);
        $source = $this->faker->randomElement($sources);
        
        return [
            'level' => $level,
            'message' => $this->getMessage($level, $source),
            'context' => [
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'url' => $this->faker->url(),
                'user_id' => User::factory(),
            ],
            'user_id' => User::factory(),
            'user_type' => $this->faker->numberBetween(0, 6),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'route_name' => $this->faker->randomElement([
                'api.v1.users.index',
                'api.v1.payments.process',
                'admin.dashboard',
                'landlord.properties.index'
            ]),
            'url' => $this->faker->url(),
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'status_code' => $this->faker->randomElement([200, 201, 400, 401, 403, 404, 500]),
            'response_time_ms' => $this->faker->numberBetween(50, 5000),
            'memory_usage_mb' => $this->faker->randomFloat(2, 1, 100),
            'tags' => $this->faker->randomElements(
                ['api', 'database', 'payment', 'security', 'performance', 'user', 'admin'],
                $this->faker->numberBetween(1, 3)
            ),
            'source' => $source,
            'is_resolved' => $this->faker->boolean(70),
            'severity_score' => $this->calculateSeverity($level),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    private function getMessage($level, $source)
    {
        $messages = [
            'info' => [
                "User authentication successful",
                "Database backup completed",
                "Cache cleared successfully",
                "Email notification sent",
                "Payment processed successfully"
            ],
            'warning' => [
                "Slow database query detected",
                "High memory usage warning",
                "API rate limit approaching",
                "Disk space running low",
                "Unusual activity detected"
            ],
            'error' => [
                "Database connection failed",
                "Payment gateway timeout",
                "Email service unavailable",
                "File upload failed",
                "API validation error"
            ],
            'debug' => [
                "Debug: User session data",
                "Debug: API request payload",
                "Debug: Database query executed",
                "Debug: Cache statistics",
                "Debug: Performance metrics"
            ]
        ];

        $levelKey = strtolower($level);
        $message = $this->faker->randomElement($messages[$levelKey] ?? $messages['info']);
        
        return "[{$source}] {$message}";
    }

    private function calculateSeverity($level)
    {
        return match($level) {
            'emergency', 'alert', 'critical' => SystemLog::SEVERITY_CRITICAL,
            'error' => SystemLog::SEVERITY_HIGH,
            'warning' => SystemLog::SEVERITY_MEDIUM,
            'notice', 'info', 'debug' => SystemLog::SEVERITY_LOW,
            default => SystemLog::SEVERITY_LOW
        };
    }
}