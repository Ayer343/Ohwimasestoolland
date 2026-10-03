<?php

namespace Database\Factories;

use App\Models\RentalAgreement;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalAgreementFactory extends Factory
{
    protected $model = RentalAgreement::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = (clone $startDate)->modify('+12 months');
        
        return [
            'unit_id' => PropertyUnit::factory(),
            'tenant_id' => User::factory()->state(['type' => 'tenant']),
            'property_id' => Property::factory(),
            'landlord_id' => User::factory()->state(['type' => 'landlord']),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'monthly_rent' => $this->faker->randomFloat(2, 500, 5000),
            'security_deposit' => $this->faker->randomFloat(2, 500, 2000),
            'late_fee' => $this->faker->randomFloat(2, 25, 100),
            'grace_period_days' => $this->faker->numberBetween(3, 7),
            'payment_due_day' => $this->faker->numberBetween(1, 28),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_months' => 12,
            'is_renewable' => $this->faker->boolean(),
            'renewal_notice_days' => $this->faker->numberBetween(30, 90),
            'renewal_rent_increase_percent' => $this->faker->randomFloat(2, 0, 10),
            'status' => $this->faker->randomElement([
                'draft', 'pending', 'active', 'expired', 'terminated'
            ]),
            'is_auto_renew' => $this->faker->boolean(30),
            'has_early_termination' => $this->faker->boolean(40),
            'early_termination_fee' => $this->faker->randomFloat(2, 500, 2000),
            'utilities_included' => json_encode(['water', 'trash']),
            'tenant_responsibilities' => json_encode(['electricity', 'internet']),
            'landlord_responsibilities' => json_encode(['maintenance', 'property_tax']),
            'special_terms' => json_encode(['pets_allowed' => true, 'pet_deposit' => 500]),
            'created_by' => User::factory(),
            'notes' => $this->faker->paragraph(),
            'metadata' => json_encode(['created_via' => 'factory']),
        ];
    }
}