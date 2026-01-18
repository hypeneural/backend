<?php

namespace Database\Seeders;

use App\Services\PlaceOpeningHoursService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlaceOpeningHoursSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = new PlaceOpeningHoursService();

        // Get all places with their experience category
        $places = DB::table('places')
            ->join('experiences', 'experiences.place_id', '=', 'places.id')
            ->join('categories', 'experiences.category_id', '=', 'categories.id')
            ->select(['places.id as place_id', 'categories.slug as category_slug', 'places.name'])
            ->distinct()
            ->get();

        $count = 0;

        foreach ($places as $place) {
            // Apply hours based on category
            match ($place->category_slug) {
                'parques', 'natureza' => $service->setParkHours($place->place_id),
                'museus', 'cultura' => $service->setMuseumHours($place->place_id),
                'gastronomia', 'restaurantes' => $this->setRestaurantHours($service, $place->place_id),
                'aventura', 'esportes' => $this->setActivityHours($service, $place->place_id),
                'compras', 'shopping' => $this->setMallHours($service, $place->place_id),
                default => $service->setTypicalBusinessHours($place->place_id),
            };

            $count++;
        }

        $this->command->info("✓ Created opening hours for {$count} places");
    }

    /**
     * Set restaurant hours (Tue-Sun 11am-10pm)
     */
    protected function setRestaurantHours(PlaceOpeningHoursService $service, string $placeId): void
    {
        // Monday: Closed (common in Brazil)
        $service->setHours($placeId, 1, null, null, isClosed: true);

        // Tuesday to Sunday: 11am - 10pm
        foreach ([2, 3, 4, 5, 6, 0] as $day) {
            $service->setHours($placeId, $day, '11:00:00', '22:00:00');
        }
    }

    /**
     * Set activity/adventure hours (daily 9am-5pm)
     */
    protected function setActivityHours(PlaceOpeningHoursService $service, string $placeId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $service->setHours($placeId, $day, '09:00:00', '17:00:00');
        }
    }

    /**
     * Set mall/shopping hours (daily 10am-10pm)
     */
    protected function setMallHours(PlaceOpeningHoursService $service, string $placeId): void
    {
        // Monday to Saturday: 10am - 10pm
        for ($day = 1; $day <= 6; $day++) {
            $service->setHours($placeId, $day, '10:00:00', '22:00:00');
        }

        // Sunday: 2pm - 8pm (common in Brazil)
        $service->setHours($placeId, 0, '14:00:00', '20:00:00');
    }
}
