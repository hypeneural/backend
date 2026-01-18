<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Parques',
                'slug' => 'parques',
                'emoji' => '🌳',
                'icon' => 'trees',
                'color' => '#22c55e',
                'description' => 'Parques, praças e áreas verdes para curtir ao ar livre',
                'order' => 1,
            ],
            [
                'name' => 'Museus',
                'slug' => 'museus',
                'emoji' => '🏛️',
                'icon' => 'landmark',
                'color' => '#8b5cf6',
                'description' => 'Museus, exposições e centros culturais',
                'order' => 2,
            ],
            [
                'name' => 'Aventura',
                'slug' => 'aventura',
                'emoji' => '🎢',
                'icon' => 'ferris-wheel',
                'color' => '#ef4444',
                'description' => 'Parques de diversão e aventuras radicais',
                'order' => 3,
            ],
            [
                'name' => 'Gastronomia',
                'slug' => 'gastronomia',
                'emoji' => '🍕',
                'icon' => 'utensils',
                'color' => '#f59e0b',
                'description' => 'Restaurantes, cafés e experiências culinárias',
                'order' => 4,
            ],
            [
                'name' => 'Natureza',
                'slug' => 'natureza',
                'emoji' => '🏞️',
                'icon' => 'mountain',
                'color' => '#06b6d4',
                'description' => 'Trilhas, cachoeiras e passeios na natureza',
                'order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'id' => Str::uuid(),
                ...$category,
                'is_active' => true,
            ]);
        }
    }
}
