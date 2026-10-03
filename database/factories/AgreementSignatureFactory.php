<?php

namespace Database\Factories;

use App\Models\AgreementSignature;
use App\Models\AdminBillingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgreementSignatureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AgreementSignature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_id' => AdminBillingRecord::factory(),
            'user_id' => User::factory(),
            'signature_type' => $this->faker->randomElement(['developer', 'super_admin']),
            'signature_data' => $this->faker->paragraph(),
            'signature_format' => $this->faker->randomElement(['typed', 'draw', 'upload']),
            'signature_name' => $this->faker->name(),
            'signature_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'signature_path' => $this->faker->optional()->filePath(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'status' => $this->faker->randomElement(['pending', 'verified', 'revoked']),
        ];
    }

    /**
     * Indicate that the signature is verified.
     */
    public function verified(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'verified',
            ];
        });
    }

    /**
     * Indicate that the signature is from a developer.
     */
    public function developer(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'signature_type' => 'developer',
            ];
        });
    }

    /**
     * Indicate that the signature is from a super admin.
     */
    public function superAdmin(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'signature_type' => 'super_admin',
            ];
        });
    }

    /**
     * Indicate that the signature is typed.
     */
    public function typed(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'signature_format' => 'typed',
            ];
        });
    }

    /**
     * Indicate that the signature is drawn.
     */
    public function drawn(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'signature_format' => 'draw',
            ];
        });
    }

    /**
     * Indicate that the signature is uploaded.
     */
    public function uploaded(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'signature_format' => 'upload',
                'signature_path' => 'signatures/' . $this->faker->uuid() . '.png',
            ];
        });
    }
}