<?php

namespace Database\Factories;

use App\Models\SuperAdminPaymentRequest;
use App\Models\DeveloperSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SuperAdminPaymentRequestFactory extends Factory
{
    protected $model = SuperAdminPaymentRequest::class;

    public function definition(): array
    {
        return [
            'developer_setting_id' => DeveloperSetting::factory(),
            'super_admin_id' => User::factory()->state(['type' => User::TYPE_SUPER_ADMIN]),
            'invoice_number' => 'SA-' . date('Ym') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'currency' => 'GHS',
            'category' => $this->faker->randomElement(['hosting', 'maintenance', 'upgrade', 'emergency', 'other']),
            'description' => $this->faker->sentence(10),
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'mobile_money', 'cash']),
            'status' => 'pending',
            'received_amount' => null,
            'received_date' => null,
            'transaction_reference' => null,
            'notes' => $this->faker->optional()->paragraph,
            'created_by' => User::factory(),
            'reminder_sent' => false,
            'reminder_count' => 0,
            'overdue_notification_sent' => false,
        ];
    }

    public function pending(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'received_amount' => null,
                'received_date' => null,
            ];
        });
    }

    public function completed(): Factory
    {
        return $this->state(function (array $attributes) {
            $receivedDate = $this->faker->dateTimeBetween('-1 month', 'now');
            return [
                'status' => 'completed',
                'received_amount' => $attributes['amount'],
                'received_date' => $receivedDate->format('Y-m-d'),
                'transaction_reference' => 'TRX-' . Str::upper(Str::random(10)),
            ];
        });
    }

    public function partial(): Factory
    {
        return $this->state(function (array $attributes) {
            $partialAmount = $attributes['amount'] * $this->faker->randomFloat(2, 0.1, 0.9);
            $receivedDate = $this->faker->dateTimeBetween('-1 month', 'now');
            return [
                'status' => 'partial',
                'received_amount' => $partialAmount,
                'received_date' => $receivedDate->format('Y-m-d'),
                'transaction_reference' => 'TRX-' . Str::upper(Str::random(10)),
            ];
        });
    }

    public function overdue(): Factory
    {
        return $this->state(function (array $attributes) {
            $overdueDate = $this->faker->dateTimeBetween('-1 month', '-1 day');
            return [
                'status' => 'overdue',
                'due_date' => $overdueDate->format('Y-m-d'),
                'overdue_notification_sent' => true,
            ];
        });
    }

    public function cancelled(): Factory
    {
        return $this->state(function (array $attributes) {
            $cancelledAt = $this->faker->dateTimeBetween('-1 week', 'now');
            return [
                'status' => 'cancelled',
                'cancelled_at' => $cancelledAt,
                'cancelled_by' => User::factory(),
            ];
        });
    }
}