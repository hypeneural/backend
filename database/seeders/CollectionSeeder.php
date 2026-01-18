<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Experience;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collections = [
            [
                'name' => 'Dia de Chuva',
                'slug' => 'dia-de-chuva',
                'emoji' => '🌧️',
                'description' => 'Experiências perfeitas para quando o tempo não colabora. Tudo coberto e divertido!',
                'type' => 'editorial',
                'is_featured' => true,
                'order' => 1,
                'filter' => ['weather_mask' => 2], // rain bit
            ],
            [
                'name' => 'Com Bebê',
                'slug' => 'com-bebe',
                'emoji' => '👶',
                'description' => 'Lugares preparados para receber os pequeninos. Fraldário, carrinho ok!',
                'type' => 'editorial',
                'is_featured' => true,
                'order' => 2,
                'filter' => ['age_tags_mask' => 1], // baby bit
            ],
            [
                'name' => 'Grátis',
                'slug' => 'gratis',
                'emoji' => '🆓',
                'description' => 'Diversão sem gastar nada! Passeios gratuitos para toda família.',
                'type' => 'editorial',
                'is_featured' => true,
                'order' => 3,
                'filter' => ['price_level' => 'free'],
            ],
            [
                'name' => 'Aventura Garantida',
                'slug' => 'aventura-garantida',
                'emoji' => '🎢',
                'description' => 'Para famílias que gostam de adrenalina e diversão intensa!',
                'type' => 'editorial',
                'is_featured' => false,
                'order' => 4,
                'filter' => ['category_slug' => 'aventura'],
            ],
            [
                'name' => 'Rapidinho (até 1h)',
                'slug' => 'rapidinho',
                'emoji' => '⚡',
                'description' => 'Sem tempo? Essas experiências são perfeitas para encaixar na correria.',
                'type' => 'editorial',
                'is_featured' => false,
                'order' => 5,
                'filter' => ['duration_bucket' => 'quick'],
            ],
            [
                'name' => 'Fim de Semana em Família',
                'slug' => 'fim-de-semana',
                'emoji' => '☀️',
                'description' => 'Programas especiais para aproveitar o final de semana juntos.',
                'type' => 'editorial',
                'is_featured' => true,
                'order' => 6,
                'filter' => ['duration_bucket' => 'full'],
            ],
            [
                'name' => 'Natureza e Ar Livre',
                'slug' => 'natureza',
                'emoji' => '🌳',
                'description' => 'Parques, trilhas e muito verde para respirar ar puro.',
                'type' => 'editorial',
                'is_featured' => false,
                'order' => 7,
                'filter' => ['category_slug' => 'natureza'],
            ],
            [
                'name' => 'Cultural Kids',
                'slug' => 'cultural-kids',
                'emoji' => '🎨',
                'description' => 'Museus e exposições pensados para encantar as crianças.',
                'type' => 'editorial',
                'is_featured' => false,
                'order' => 8,
                'filter' => ['category_slug' => 'cultura'],
            ],
        ];

        foreach ($collections as $collectionData) {
            $filter = $collectionData['filter'] ?? [];
            unset($collectionData['filter']);

            $collection = Collection::create([
                'id' => Str::uuid()->toString(),
                ...$collectionData,
                'is_active' => true,
            ]);

            // Add experiences based on filter
            $this->addExperiencesToCollection($collection, $filter);
        }

        $this->command->info('✓ Created ' . count($collections) . ' collections');
    }

    /**
     * Add experiences to collection based on filter
     */
    protected function addExperiencesToCollection(Collection $collection, array $filter): void
    {
        $query = Experience::query()
            ->where('status', 'published')
            ->limit(10);

        if (isset($filter['weather_mask'])) {
            $query->whereHas(
                'searchRecord',
                fn($q) =>
                $q->whereRaw("(weather_mask & {$filter['weather_mask']}) > 0")
            );
        }

        if (isset($filter['age_tags_mask'])) {
            $query->whereHas(
                'searchRecord',
                fn($q) =>
                $q->whereRaw("(age_tags_mask & {$filter['age_tags_mask']}) > 0")
            );
        }

        if (isset($filter['price_level'])) {
            $query->where('price_level', $filter['price_level']);
        }

        if (isset($filter['duration_bucket'])) {
            $query->where('duration_bucket', $filter['duration_bucket']);
        }

        if (isset($filter['category_slug'])) {
            $query->whereHas(
                'category',
                fn($q) =>
                $q->where('slug', $filter['category_slug'])
            );
        }

        $experiences = $query->inRandomOrder()->get();

        foreach ($experiences as $index => $experience) {
            CollectionItem::create([
                'id' => Str::uuid()->toString(),
                'collection_id' => $collection->id,
                'experience_id' => $experience->id,
                'order' => $index + 1,
            ]);
        }
    }
}
