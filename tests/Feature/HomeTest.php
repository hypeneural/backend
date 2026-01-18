<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CityTrending;
use App\Models\Experience;
use App\Models\Family;
use App\Models\FamilyUser;
use App\Models\Place;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Family $family;
    protected City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'last_lat' => -23.5505,
            'last_lng' => -46.6333,
        ]);

        $this->family = Family::create([
            'id' => fake()->uuid(),
            'name' => 'Test Family',
            'type' => 'family',
        ]);

        FamilyUser::create([
            'id' => fake()->uuid(),
            'family_id' => $this->family->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        UserStats::create([
            'user_id' => $this->user->id,
            'xp' => 500,
            'level' => 2,
            'streak_days' => 5,
        ]);

        $this->city = City::create([
            'id' => fake()->uuid(),
            'name' => 'São Paulo',
            'slug' => 'sao-paulo',
            'state' => 'SP',
            'country' => 'BR',
            'lat' => -23.5505,
            'lng' => -46.6333,
            'timezone' => 'America/Sao_Paulo',
        ]);
    }

    public function test_can_get_home_data(): void
    {
        $this->createTrendingExperiences();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/home?city_id=' . $this->city->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['name', 'avatar', 'streak', 'level'],
                    'highlight',
                    'trending',
                    'chips',
                ],
                'meta' => ['success'],
            ])
            ->assertJsonPath('meta.success', true);
    }

    public function test_home_requires_city_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/home');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['city_id']);
    }

    public function test_home_includes_user_stats(): void
    {
        $this->createTrendingExperiences();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/home?city_id=' . $this->city->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.streak', 5)
            ->assertJsonPath('data.user.level', 2);
    }

    public function test_home_updates_user_location(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/home?city_id=' . $this->city->id . '&lat=-23.56&lng=-46.64');

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals(-23.56, (float) $this->user->last_lat);
        $this->assertEquals(-46.64, (float) $this->user->last_lng);
    }

    public function test_home_returns_chips_with_counts(): void
    {
        $this->createTrendingExperiences();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/home?city_id=' . $this->city->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'chips' => ['adventure', 'rain', 'baby', 'food'],
                ],
            ]);
    }

    protected function createTrendingExperiences(): void
    {
        $category = Category::create([
            'id' => fake()->uuid(),
            'name' => 'Parques',
            'slug' => 'parques',
            'emoji' => '🌳',
            'is_active' => true,
            'order' => 1,
        ]);

        $place = Place::create([
            'id' => fake()->uuid(),
            'name' => 'Parque Ibirapuera',
            'address' => 'Av. Pedro Álvares Cabral',
            'city_id' => $this->city->id,
            'neighborhood' => 'Vila Mariana',
            'lat' => -23.5874,
            'lng' => -46.6576,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $experience = Experience::create([
                'id' => fake()->uuid(),
                'title' => 'Trending Experience ' . $i,
                'mission_title' => 'Mission ' . $i,
                'summary' => 'Summary ' . $i,
                'category_id' => $category->id,
                'place_id' => $place->id,
                'city_id' => $this->city->id,
                'age_tags' => ['kid'],
                'vibe' => ['divertido'],
                'duration_min' => 60,
                'duration_max' => 120,
                'price_level' => 'free',
                'weather' => ['sun'],
                'practical' => ['parking' => true],
                'cover_image' => 'https://example.com/image.jpg',
                'saves_count' => 100 - $i * 10,
                'trending_score' => 100 - $i * 10,
                'status' => 'published',
                'source' => 'editorial',
            ]);

            CityTrending::create([
                'city_id' => $this->city->id,
                'experience_id' => $experience->id,
                'position' => $i + 1,
                'score' => 100 - $i * 10,
                'calculated_at' => now(),
            ]);
        }
    }
}
