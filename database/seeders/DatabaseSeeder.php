<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PropertyTypeSeeder::class,
            UserSeeder::class,
            RoleSeeder::class,
            SanitationSettingSeeder::class,
        ]);
    }
}