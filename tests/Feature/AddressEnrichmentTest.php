<?php

namespace Tests\Feature;

use App\Services\AddressEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class AddressEnrichmentTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_enrich_adds_reverse_geocoded_address_only_when_missing(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return Http::response([
                'display_name' => '17 Mirabra St, Bangued, Abra, Philippines',
            ], 200);
        });

        $enricher = app(AddressEnricher::class);
        $enricher->minIntervalSeconds = 0;

        $withAddress = $enricher->enrich(['name' => 'A', 'lat' => 17.6, 'lng' => 120.61, 'address' => 'Existing 1']);
        $this->assertSame('Existing 1', $withAddress['address']);
        $this->assertSame(0, $calls);

        $enriched = $enricher->enrich(['name' => 'B', 'lat' => 17.605, 'lng' => 120.615]);
        $this->assertSame('17 Mirabra St, Bangued, Abra, Philippines', $enriched['address']);
        $this->assertSame(1, $calls);

        $cached = $enricher->enrich(['name' => 'C', 'lat' => 17.605, 'lng' => 120.615]);
        $this->assertSame('17 Mirabra St, Bangued, Abra, Philippines', $cached['address']);
        $this->assertSame(1, $calls, 'The reverse geocode must be cached.');
    }

    public function test_enrich_is_non_fatal_on_nominatim_failure(): void
    {
        Http::fake([
            '*' => Http::response('rate limited', 429),
        ]);

        $enricher = app(AddressEnricher::class);
        $enricher->minIntervalSeconds = 0;

        $payload = $enricher->enrich(['name' => 'Silent Hollow', 'lat' => 17.6, 'lng' => 120.61]);

        $this->assertArrayNotHasKey('address', $payload);
        $this->assertSame('Silent Hollow', $payload['name']);
    }

    public function test_enrich_can_be_disabled_via_config(): void
    {
        config(['atlas.enrich_addresses' => false]);

        $this->assertFalse(app(AddressEnricher::class)->enabled());
    }
}