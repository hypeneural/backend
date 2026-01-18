<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            [
                'name' => 'São Paulo',
                'slug' => 'sao-paulo',
                'state' => 'SP',
                'country' => 'BR',
                'lat' => -23.5505,
                'lng' => -46.6333,
                'timezone' => 'America/Sao_Paulo',
                'population' => 12400000,
            ],
            [
                'name' => 'Rio de Janeiro',
                'slug' => 'rio-de-janeiro',
                'state' => 'RJ',
                'country' => 'BR',
                'lat' => -22.9068,
                'lng' => -43.1729,
                'timezone' => 'America/Sao_Paulo',
                'population' => 6720000,
            ],
        ];

        foreach ($cities as $city) {
            City::create([
                'id' => Str::uuid(),
                ...$city,
            ]);
        }
    }
}
