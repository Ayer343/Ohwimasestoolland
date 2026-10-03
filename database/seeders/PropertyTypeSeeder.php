<?php

namespace Database\Seeders;  

use Illuminate\Database\Seeder;
use App\Models\PropertyType;

class PropertyTypeSeeder extends Seeder
{
    public function run()
    {
        $propertyTypes = [
            [
                'name' => 'Resident',
                'slug' => 'resident',
                'icon' => 'fa-home',
                'color' => '#3B82F6',
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 1,
                'description' => 'Residential property'
            ],
            [
                'name' => 'School',
                'slug' => 'school', 
                'icon' => 'fa-graduation-cap',
                'color' => '#10B981',
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 2,
                'description' => 'Educational institution'
            ],
            [
                'name' => 'Church',
                'slug' => 'church',
                'icon' => 'fa-church',
                'color' => '#F59E0B',
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 3,
                'description' => 'Religious place of worship'
            ],
            [
                'name' => 'Hospital',
                'slug' => 'hospital',
                'icon' => 'fa-hospital',
                'color' => '#EF4444',
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 4,
                'description' => 'Medical facility'
            ],
            [
                'name' => 'Police Station',
                'slug' => 'police-station',
                'icon' => 'fa-shield-alt',
                'color' => '#8B5CF6',
                'is_active' => true,
                'is_custom' => false,
                'sort_order' => 5,
                'description' => 'Law enforcement facility'
            ],
            [
                'name' => 'Custom',
                'slug' => 'custom',
                'icon' => 'fa-edit',
                'color' => '#6B7280',
                'is_active' => true,
                'is_custom' => true,
                'sort_order' => 99,
                'description' => 'Custom property type defined by user'
            ]
        ];

        foreach ($propertyTypes as $type) {
            PropertyType::create($type);
        }
    }
}