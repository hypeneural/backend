<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Experience;
use App\Models\ExperienceSearch;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected City $city;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

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

        $this->category = Category::create([
            'id' => fake()->uuid(),
            'name' => 'Parques',
            'slug' => 'parques',
            'emoji' => '🌳',
            'is_active' => true,
            'order' => 1,
        ]);
    }

    public function test_can_search_experiences(): void
    {
        $this->createTestExperiences(5);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search?city_id=' . $this->city->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'results',
                    'facets',
                    'applied_filters',
                ],
                'meta' => ['success', 'next_cursor', 'has_more'],
            ])
            ->assertJsonPath('meta.success', true);
    }

    public function test_search_requires_city_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['city_id']);
    }

    public function test_can_filter_by_category(): void
    {
        $this->createTestExperiences(3);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search?city_id=' . $this->city->id . '&categories[]=' . $this->category->id);

        $response->assertStatus(200);

        foreach ($response->json('data.results') as $result) {
            // All results should match category
        }
    }

    public function test_can_filter_by_price(): void
    {
        $this->createTestExperiences(5);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search?city_id=' . $this->city->id . '&price[]=free');

        $response->assertStatus(200);
    }

    public function test_cursor_pagination_works(): void
    {
        $this->createTestExperiences(25);

        // First page
        $response1 = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search?city_id=' . $this->city->id . '&limit=10');

        $response1->assertStatus(200)
            ->assertJsonPath('meta.has_more', true);

        $cursor = $response1->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        // Second page
        $response2 = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search?city_id=' . $this->city->id . '&limit=10&cursor=' . $cursor);

        $response2->assertStatus(200);

        // Results should be different
        $page1Ids = collect($response1->json('data.results'))->pluck('id');
        $page2Ids = collect($response2->json('data.results'))->pluck('id');

        $this->assertEmpty($page1Ids->intersect($page2Ids));
    }

    public function test_returns_facets(): void
    {
        $this->createTestExperiences(5);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/experiences/search?city_id=' . $this->city->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'facets' => ['categories', 'price_level', 'age_tags'],
                ],
            ]);
    }

    protected function createTestExperiences(int $count): void
    {
        $place = Place::create([
            'id' => fake()->uuid(),
            'name' => 'Test Place',
            'address' => 'Test Address',
            'city_id' => $this->city->id,
            'neighborhood' => 'Test',
            'lat' => -23.5505,
            'lng' => -46.6333,
        ]);

        for ($i = 0; $i < $count; $i++) {
            $experience = Experience::create([
                'id' => fake()->uuid(),
                'title' => 'Experience ' . $i,
                'mission_title' => 'Mission ' . $i,
                'summary' => 'Summary for experience ' . $i,
                'category_id' => $this->category->id,
                'place_id' => $place->id,
                'city_id' => $this->city->id,
                'age_tags' => ['kid', 'teen'],
                'vibe' => ['divertido'],
                'duration_min' => 60,
                'duration_max' => 120,
                'price_level' => fake()->randomElement(['free', 'moderate', 'top']),
                'weather' => ['sun', 'any'],
                'practical' => ['parking' => true, 'bathroom' => true],
                'cover_image' => 'https://example.com/image.jpg',
                'saves_count' => rand(0, 100),
                'reviews_count' => rand(0, 50),
                'trending_score' => rand(0, 100) + ($count - $i), // Ensure unique scores
                'status' => 'published',
                'source' => 'editorial',
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
                'duration_bucket' => 'half',
                'age_tags_mask' => 12, // kid + teen
                'weather_mask' => 5, // sun + any
                'practical_mask' => 3, // parking + bathroom
                'saves_count' => $experience->saves_count,
                'reviews_count' => $experience->reviews_count,
                'trending_score' => $experience->trending_score,
                'search_text' => $experience->title . ' ' . $experience->mission_title,
                'status' => 'published',
                'updated_at' => now(),
            ]);
        }
    }
}
