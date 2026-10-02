<?php

namespace Tests\Feature;

use App\Models\ModQueue;
use App\Models\Place;
use App\Services\DedupeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class DedupeServiceTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_exact_name_within_radius_is_duplicate(): void
    {
        Place::factory()->create(['name' => 'Dizon Pharmacy', 'lat' => 17.6000, 'lng' => 120.6100]);

        $service = app(DedupeService::class);

        $this->assertNotNull($service->isDuplicateApproved('Dizon Pharmacy', 17.6000, 120.6100));
        $this->assertNull($service->isDuplicateApproved('Dizon Pharmacy', 17.7000, 120.7000));
    }

    public function test_fuzzy_near_duplicate_name_within_fuzzy_radius_is_duplicate(): void
    {
        Place::factory()->create(['name' => 'Botika Villa', 'lat' => 17.6000, 'lng' => 120.6100]);

        $service = app(DedupeService::class);

        $this->assertNotNull($service->isDuplicateApproved('Botika Villa', 17.6005, 120.6105));
        $this->assertNotNull($service->isDuplicateApproved('Botika Villa', 17.6010, 120.6108));

        $this->assertNull($service->isDuplicateApproved('Entirely Different Name', 17.6005, 120.6105));
    }

    public function test_phone_match_across_names_within_phone_radius_is_duplicate(): void
    {
        Place::factory()->create([
            'name' => 'Mira Pharmacy',
            'phone' => '0917 666 8888',
            'lat' => 17.6000,
            'lng' => 120.6100,
        ]);

        $service = app(DedupeService::class);

        $this->assertNotNull($service->isDuplicateApproved('Mira Drugstore', 17.6010, 120.6110, 25, '09176668888'));
    }

    public function test_accent_folded_names_are_duplicates(): void
    {
        Place::factory()->create(['name' => 'Cañacao St Stall', 'lat' => 17.6010, 'lng' => 120.6110]);

        $service = app(DedupeService::class);

        $this->assertNotNull($service->isDuplicateApproved('Canacao St Stall', 17.6010, 120.6110));
    }

    public function test_check_against_queue_catches_fuzzy_and_phone(): void
    {
        Place::factory()->create(['name' => 'St. James Grocery', 'phone' => '0917 555 2222', 'lat' => 17.60, 'lng' => 120.61]);

        ModQueue::create([
            'action' => 'create',
            'payload' => [
                'name' => 'Pipeline Corner Store',
                'lat' => 17.6020,
                'lng' => 120.6120,
                'phone' => '0977 123 4567',
                'source' => 'overpass',
            ],
            'requested_by' => null,
            'status' => 'pending',
        ]);

        $service = app(DedupeService::class);

        $this->assertTrue($service->checkAgainstQueue([
            'name' => 'Pipeline Corner Stores',
            'lat' => 17.6020,
            'lng' => 120.6120,
        ]));

        $this->assertTrue($service->checkAgainstQueue([
            'name' => 'Queued Copy Shop',
            'phone' => '0977 123 4567',
            'lat' => 17.6022,
            'lng' => 120.6122,
        ]));

        $this->assertFalse($service->checkAgainstQueue([
            'name' => 'Unrelated Bakery',
            'lat' => 17.601,
            'lng' => 120.611,
        ]));
    }
}