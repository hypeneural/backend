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
        // For fresh databases, use TestDataSeeder which handles all relationships
        // php artisan db:seed --class=TestDataSeeder

        $this->call([
                // Base data
            CategorySeeder::class,
            CitySeeder::class,
            PlaceSeeder::class,
            ExperienceSeeder::class,

                // Enhanced features
            CollectionSeeder::class,
            PlaceOpeningHoursSeeder::class,
        ]);
    }
}

