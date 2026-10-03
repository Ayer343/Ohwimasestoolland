<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    public function definition()
    {
        $types = [
            Maintenance::TYPE_PLANNED,
            Maintenance::TYPE_EMERGENCY,
            Maintenance::TYPE_PREVENTIVE,
            Maintenance::TYPE_CORRECTIVE,
            Maintenance::TYPE_UPGRADE,
            Maintenance::TYPE_SECURITY,
            Maintenance::TYPE_PERFORMANCE,
            Maintenance::TYPE_DATABASE,
            Maintenance::TYPE_INFRASTRUCTURE
        ];
        
        $impacts = [
            Maintenance::IMPACT_LOW,
            Maintenance::IMPACT_MEDIUM,
            Maintenance::IMPACT_HIGH,
            Maintenance::IMPACT_CRITICAL
        ];
        
        $statuses = [
            Maintenance::STATUS_DRAFT,
            Maintenance::STATUS_SCHEDULED,
            Maintenance::STATUS_IN_PROGRESS,
            Maintenance::STATUS_COMPLETED,
            Maintenance::STATUS_CANCELLED
        ];
        
        $type = $this->faker->randomElement($types);
        $impact = $this->faker->randomElement($impacts);
        $status = $this->faker->randomElement($statuses);
        
        $scheduledStart = $this->faker->dateTimeBetween('now', '+30 days');
        $scheduledEnd = Carbon::parse($scheduledStart)->addHours($this->faker->numberBetween(1, 8));
        
        $actualStart = null;
        $actualEnd = null;
        $actualDuration = null;
        
        if (in_array($status, [Maintenance::STATUS_IN_PROGRESS, Maintenance::STATUS_COMPLETED])) {
            $actualStart = $this->faker->dateTimeBetween($scheduledStart, $scheduledEnd);
            
            if ($status === Maintenance::STATUS_COMPLETED) {
                $actualEnd = Carbon::parse($actualStart)->addMinutes($this->faker->numberBetween(30, 240));
                $actualDuration = $actualStart->diffInMinutes($actualEnd);
            }
        }
        
        return [
            'title' => $this->getTitle($type, $impact),
            'description' => $this->faker->paragraphs(3, true),
            'reason' => $this->faker->sentence(),
            'status' => $status,
            'type' => $type,
            'impact_level' => $impact,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'actual_start' => $actualStart,
            'actual_end' => $actualEnd,
            'estimated_duration_minutes' => $scheduledStart->diffInMinutes($scheduledEnd),
            'actual_duration_minutes' => $actualDuration,
            'affected_modules' => $this->faker->randomElements(
                ['application', 'api', 'database', 'payment', 'sms', 'email', 'storage'],
                $this->faker->numberBetween(1, 3)
            ),
            'affected_user_types' => $this->faker->randomElements(
                [0, 1, 2, 3, 4, 5, 6],
                $this->faker->numberBetween(0, 3)
            ),
            'notify_users' => $this->faker->boolean(80),
            'notification_channels' => $this->faker->randomElements(
                [
                    Maintenance::CHANNEL_EMAIL,
                    Maintenance::CHANNEL_SMS,
                    Maintenance::CHANNEL_IN_APP,
                    Maintenance::CHANNEL_SYSTEM_BANNER
                ],
                $this->faker->numberBetween(1, 3)
            ),
            'scheduled_by' => User::factory(),
            'backup_taken' => $this->faker->boolean(70),
            'backup_verified' => $this->faker->boolean(70),
            'verification_completed' => $status === Maintenance::STATUS_COMPLETED ? $this->faker->boolean(90) : false,
            'performance_impact' => $this->faker->numberBetween(1, 10),
            'downtime_minutes' => $status === Maintenance::STATUS_COMPLETED ? $this->faker->numberBetween(5, 60) : 0,
            'cost_estimate' => $this->faker->randomFloat(2, 0, 1000),
            'actual_cost' => $status === Maintenance::STATUS_COMPLETED ? $this->faker->randomFloat(2, 0, 1000) : null,
            'is_recurring' => $this->faker->boolean(20),
            'created_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
        ];
    }

    private function getTitle($type, $impact)
    {
        $titles = [
            'planned' => [
                'low' => 'Routine System Maintenance',
                'medium' => 'Scheduled System Update',
                'high' => 'Major System Upgrade',
                'critical' => 'Critical Infrastructure Maintenance'
            ],
            'emergency' => [
                'low' => 'Emergency Bug Fix',
                'medium' => 'Emergency System Patch',
                'high' => 'Critical Emergency Maintenance',
                'critical' => 'Emergency System Recovery'
            ],
            'security' => [
                'low' => 'Security Patch Application',
                'medium' => 'Security Vulnerability Fix',
                'high' => 'Critical Security Update',
                'critical' => 'Emergency Security Patch'
            ],
            'database' => [
                'low' => 'Database Optimization',
                'medium' => 'Database Schema Update',
                'high' => 'Database Migration',
                'critical' => 'Emergency Database Maintenance'
            ],
            'performance' => [
                'low' => 'Performance Tuning',
                'medium' => 'System Optimization',
                'high' => 'Performance Enhancement',
                'critical' => 'Critical Performance Fix'
            ]
        ];

        $typeKey = strtolower($type);
        $impactKey = strtolower($impact);
        
        if (isset($titles[$typeKey][$impactKey])) {
            return $titles[$typeKey][$impactKey];
        }
        
        return ucfirst($type) . ' Maintenance - ' . ucfirst($impact) . ' Impact';
    }
}