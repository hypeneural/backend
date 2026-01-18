<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSearch;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExperienceSeeder extends Seeder
{
    protected array $titles = [
        'Parques' => [
            'Piquenique em família',
            'Passeio de bicicleta',
            'Brincadeiras no playground',
            'Corrida matinal',
            'Tarde de relax',
            'Feed the ducks',
            'Caminhada pela trilha',
            'Observação de pássaros',
        ],
        'Museus' => [
            'Visita guiada',
            'Oficina de arte',
            'Exposição interativa',
            'Caça ao tesouro cultural',
            'História para crianças',
            'Leitura no museu',
            'Tour fotográfico',
            'Workshop criativo',
        ],
        'Aventura' => [
            'Montanha-russa radical',
            'Brinquedos aquáticos',
            'Escalada indoor',
            'Tirolesa',
            'Arvorismo',
            'Kart com a família',
            'Trampolim park',
            'Laser tag',
        ],
        'Gastronomia' => [
            'Brunch especial',
            'Aula de culinária kids',
            'Degustação de pizza',
            'Café da manhã colonial',
            'Festa do sorvete',
            'Piquenique gourmet',
            'Chef por um dia',
            'Festival de massas',
        ],
        'Natureza' => [
            'Trilha na mata',
            'Banho de cachoeira',
            'Observação de estrelas',
            'Camping light',
            'Pescaria em família',
            'Safari urbano',
            'Horta comunitária',
            'Plantio de árvores',
        ],
    ];

    protected array $missionTitles = [
        'Descubra a magia escondida',
        'Crie memórias inesquecíveis',
        'Aventure-se em família',
        'Explore novos horizontes',
        'Viva uma experiência única',
        'Conecte-se com a natureza',
        'Divirta-se sem limites',
        'Aprenda brincando',
    ];

    public function run(): void
    {
        $categories = Category::all()->keyBy('slug');
        $cities = City::all();

        foreach ($cities as $city) {
            $places = Place::where('city_id', $city->id)->get();

            // Create 40 experiences per city (80 total)
            $experiencesPerCity = 40;

            foreach ($categories as $slug => $category) {
                $categoryPlaces = $places->random(min(4, $places->count()));
                $titles = $this->titles[$category->name] ?? $this->titles['Parques'];

                foreach ($categoryPlaces as $index => $place) {
                    if ($index >= 8)
                        break; // 8 experiences per category

                    $this->createExperience($category, $place, $city, $titles[$index % count($titles)]);
                }
            }
        }
    }

    protected function createExperience(Category $category, Place $place, City $city, string $title): void
    {
        $durationMin = fake()->randomElement([30, 60, 90, 120]);
        $durationMax = $durationMin + fake()->randomElement([30, 60, 90]);
        $priceLevel = fake()->randomElement(['free', 'moderate', 'top']);
        $ageTags = fake()->randomElements(['baby', 'toddler', 'kid', 'teen', 'all'], rand(2, 4));
        $weather = fake()->randomElements(['sun', 'rain', 'any'], rand(1, 2));

        $experience = Experience::create([
            'id' => Str::uuid(),
            'title' => $title,
            'mission_title' => fake()->randomElement($this->missionTitles),
            'summary' => "Uma experiência incrível para toda a família! {$title} no {$place->name} é uma oportunidade perfeita para criar memórias inesquecíveis.",
            'category_id' => $category->id,
            'place_id' => $place->id,
            'city_id' => $city->id,
            'lat' => null, // Use place coordinates
            'lng' => null,
            'badges' => fake()->randomElements(['staff_pick', 'trending', 'new', 'family_favorite'], rand(0, 2)),
            'age_tags' => $ageTags,
            'vibe' => fake()->randomElements(['relaxante', 'aventura', 'educativo', 'divertido', 'emocionante'], rand(1, 3)),
            'duration_min' => $durationMin,
            'duration_max' => $durationMax,
            'price_level' => $priceLevel,
            'price_label' => match ($priceLevel) {
                'free' => 'Entrada gratuita',
                'moderate' => 'R$ 20-50 por pessoa',
                'top' => 'R$ 80+ por pessoa',
            },
            'weather' => $weather,
            'practical' => [
                'parking' => fake()->boolean(70),
                'bathroom' => fake()->boolean(90),
                'food' => fake()->boolean(60),
                'stroller' => fake()->boolean(70),
                'accessibility' => fake()->boolean(50),
                'changing_table' => fake()->boolean(40),
            ],
            'tips' => [
                'Chegue cedo para evitar filas',
                'Leve protetor solar e água',
                'Vista roupas confortáveis',
            ],
            'cover_image' => "https://picsum.photos/seed/{$place->id}/800/600",
            'gallery' => [
                "https://picsum.photos/seed/{$place->id}1/800/600",
                "https://picsum.photos/seed/{$place->id}2/800/600",
            ],
            'saves_count' => fake()->numberBetween(10, 500),
            'reviews_count' => fake()->numberBetween(5, 100),
            'average_rating' => fake()->randomFloat(1, 3.5, 5.0),
            'trending_score' => fake()->randomFloat(2, 0, 100),
            'status' => 'published',
            'source' => 'editorial',
            'published_at' => now()->subDays(rand(1, 90)),
        ]);

        // Create search record
        $this->createSearchRecord($experience, $place);
    }

    protected function createSearchRecord(Experience $experience, Place $place): void
    {
        $avgDuration = ($experience->duration_min + $experience->duration_max) / 2;
        $durationBucket = match (true) {
            $avgDuration < 60 => 'quick',
            $avgDuration <= 180 => 'half',
            default => 'full',
        };

        $ageTagsMask = $this->calculateAgeMask($experience->age_tags ?? []);
        $weatherMask = $this->calculateWeatherMask($experience->weather ?? []);
        $practicalMask = $this->calculatePracticalMask($experience->practical ?? []);

        $searchText = implode(' ', [
            $experience->title,
            $experience->mission_title,
            $experience->summary,
            $place->name,
            $place->neighborhood,
        ]);

        ExperienceSearch::create([
            'experience_id' => $experience->id,
            'title' => $experience->title,
            'mission_title' => $experience->mission_title,
            'cover_image' => $experience->cover_image,
            'category_id' => $experience->category_id,
            'city_id' => $experience->city_id,
            'lat' => $place->lat,
            'lng' => $place->lng,
            'price_level' => $experience->price_level,
            'duration_bucket' => $durationBucket,
            'age_tags_mask' => $ageTagsMask,
            'weather_mask' => $weatherMask,
            'practical_mask' => $practicalMask,
            'saves_count' => $experience->saves_count,
            'reviews_count' => $experience->reviews_count,
            'average_rating' => $experience->average_rating,
            'trending_score' => $experience->trending_score,
            'search_text' => $searchText,
            'status' => $experience->status,
            'updated_at' => now(),
        ]);
    }

    protected function calculateAgeMask(array $tags): int
    {
        $map = ['baby' => 1, 'toddler' => 2, 'kid' => 4, 'teen' => 8, 'all' => 16];
        $mask = 0;
        foreach ($tags as $tag) {
            $mask |= $map[$tag] ?? 0;
        }
        return $mask;
    }

    protected function calculateWeatherMask(array $weather): int
    {
        $map = ['sun' => 1, 'rain' => 2, 'any' => 4];
        $mask = 0;
        foreach ($weather as $w) {
            $mask |= $map[$w] ?? 0;
        }
        return $mask;
    }

    protected function calculatePracticalMask(array $practical): int
    {
        $map = [
            'parking' => 1,
            'bathroom' => 2,
            'food' => 4,
            'stroller' => 8,
            'accessibility' => 16,
            'changing_table' => 32,
        ];
        $mask = 0;
        foreach ($practical as $key => $value) {
            if ($value && isset($map[$key])) {
                $mask |= $map[$key];
            }
        }
        return $mask;
    }
}
