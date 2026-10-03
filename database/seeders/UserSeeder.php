<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create only Developer user
        User::create([
            'name' => 'Stephen Ayer',
            'email' => 'loubahno@gmail.com',
            'phone' => '0595652410',
            'password' => Hash::make('robinho@@@123'),
            'digital_address' => 'GA-123-461',
            'region' => 'Greater Accra',
            'location' => 'Airport Residential',
            'gender' => 'female',
            'type' => User::TYPE_DEVELOPER, // 5
            'email_verified_at' => now(),
            'created_by' => null, // Developer is created by system
        ]);

        $this->command->info('Developer user created successfully!');
        $this->command->info('Email: loubahno@gmail.com');
        $this->command->info('Password: robinho@@@123');
        $this->command->warn('Please change the password after first login!');
    }
}