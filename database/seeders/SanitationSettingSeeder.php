<?php
// database/seeders/SanitationSettingSeeder.php

namespace Database\Seeders;

use App\Models\SanitationSetting;
use Illuminate\Database\Seeder;

class SanitationSettingSeeder extends Seeder
{
    public function run()
    {
        SanitationSetting::create([
            'company_name' => 'Clean City Sanitation Services',
            'company_short_name' => 'CCS',
            'company_email' => 'info@cleancitysanitation.com',
            'company_phone' => '+233241234567',
            'company_address' => '123 Main Street, Accra, Ghana',
            'registration_number' => 'REG-2024-001',
            'tax_id' => 'TAX-12345678',
            'license_number' => 'LIC-2024-001',
            'contact_person_name' => 'John Doe',
            'contact_person_phone' => '+233241234568',
            'contact_person_email' => 'john.doe@cleancitysanitation.com',
            'operational_start_time' => '08:00:00',
            'operational_end_time' => '17:00:00',
            'operational_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'service_areas' => ['Central', 'East', 'West', 'North', 'South'],
            'default_collection_frequencies' => ['daily', 'weekly', 'biweekly', 'monthly'],
            'default_waste_types' => ['general', 'recyclable', 'organic', 'hazardous', 'bulk'],
            'default_collection_days' => ['Monday', 'Wednesday', 'Friday'],
            'default_collection_fee' => 50.00,
            'emergency_collection_fee' => 100.00,
            'late_fee_percentage' => 5.00,
            'vehicle_types' => ['Truck', 'Van', 'Compactor'],
            'default_worker_count_per_vehicle' => 2,
            'primary_color' => '#10B981',
            'secondary_color' => '#059669',
            'company_description' => 'Providing reliable and efficient waste collection services to keep our city clean and healthy.',
            'website_url' => 'https://cleancitysanitation.com',
            'facebook_url' => 'https://facebook.com/cleancitysanitation',
            'twitter_url' => 'https://twitter.com/cleancitysan',
            'instagram_url' => 'https://instagram.com/cleancitysanitation',
            'is_active' => true,
            'created_by' => 1,
        ]);
    }
}