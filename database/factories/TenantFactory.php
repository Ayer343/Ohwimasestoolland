<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition()
    {
        $phone = $this->faker->regexify('0[2345][0-9]{8}'); // Ghana phone number format
        
        return [
            'name' => $this->faker->name(),
            'phone' => $phone,
            'email' => $this->faker->unique()->safeEmail(),
            'gender' => $this->faker->randomElement(['male', 'female', 'other', null]),
            'notes' => $this->faker->boolean(30) ? $this->faker->sentence() : null,
            'user_id' => $this->faker->boolean(50) ? User::factory() : null,
            'created_by' => User::factory(),
        ];
    }

    public function registered()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => User::factory(),
            ];
        });
    }

    public function unregistered()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => null,
            ];
        });
    }

    public function male()
    {
        return $this->state(function (array $attributes) {
            return [
                'gender' => 'male',
            ];
        });
    }

    public function female()
    {
        return $this->state(function (array $attributes) {
            return [
                'gender' => 'female',
            ];
        });
    }
}