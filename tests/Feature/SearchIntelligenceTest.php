<?php

namespace Tests\Feature;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class SearchIntelligenceTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_multi_token_query_matches_all_tokens_across_fields(): void
    {
        $school = Place::factory()->create([
            'name' => 'Bangued National High School',
            'category_id' => $this->category('pharmacy')->id,
        ]);
        $zion = Place::factory()->create([
            'name' => 'Zion Academy',
            'address' => 'Mirabra Lane 4, Bangued',
            'category_id' => $this->category('pharmacy')->id,
        ]);

        $response = $this->getJson('/api/places?q=bangued national')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$school->id], $ids);
        $this->assertNotContains($zion->id, $ids);
    }

    public function test_search_matches_slug(): void
    {
        $school = Place::factory()->create([
            'name' => 'Bangued National High School',
            'slug' => 'bangued-national-high-school',
            'category_id' => $this->category('pharmacy')->id,
        ]);

        $this->getJson('/api/places?q=bangued-national')
            ->assertOk()
            ->assertJsonFragment(['id' => $school->id]);

        $this->getJson('/api/places?q=' . $school->slug)
            ->assertOk()
            ->assertJsonFragment(['id' => $school->id]);
    }

    public function test_search_is_accent_insensitive(): void
    {
        $accent = Place::factory()->create(['name' => 'Café Luzviminda']);
        $ascii = Place::factory()->create(['name' => 'Cafe de Bangued']);

        $this->getJson('/api/places?q=cafe')
            ->assertOk()
            ->assertJsonFragment(['id' => $accent->id])
            ->assertJsonFragment(['id' => $ascii->id]);

        $this->getJson('/api/places?q=café')
            ->assertOk()
            ->assertJsonFragment(['id' => $ascii->id])
            ->assertJsonFragment(['id' => $accent->id]);
    }

    public function test_search_still_matches_single_token_name_and_address(): void
    {
        $first = Place::factory()->create(['name' => 'Immaculate School of Bangued']);
        $second = Place::factory()->create(['name' => 'Zion Academy', 'address' => 'Mirabra Lane 4, Bangued']);

        $this->getJson('/api/places?q=Immaculate')->assertOk()->assertJsonFragment(['id' => $first->id]);
        $this->getJson('/api/places?q=Mirabra')->assertOk()->assertJsonFragment(['id' => $second->id]);
    }

    public function test_suggest_endpoint_returns_light_suggestions(): void
    {
        $store = Place::factory()->create([
            'name' => 'Quirino Supermart',
            'category_id' => $this->category('grocery')->id,
        ]);

        $this->getJson('/api/search/suggest?q=quirino')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $store->id)
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'category', 'barangay', 'lat', 'lng']]]);

        $this->getJson('/api/search/suggest?q=q')->assertUnprocessable();
    }
}