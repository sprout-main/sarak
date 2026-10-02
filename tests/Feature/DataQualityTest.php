<?php

namespace Tests\Feature;

use App\Models\BoundaryArea;
use App\Models\Place;
use App\Services\GeoSpatial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BoundaryFixtures;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class DataQualityTest extends TestCase
{
    use CoreCatalog, BoundaryFixtures, RefreshDatabase;

    private BoundaryArea $palao;

    private BoundaryArea $mira;

    protected function setUp(): void
    {
        parent::setUp();

        $this->palao = $this->polygon('Palao', $this->boxRing(17.58, 120.60, 17.605, 120.62));
        $this->mira = $this->polygon('Mira', $this->boxRing(17.605, 120.62, 17.63, 120.64));
    }

    public function test_detects_disagreements_between_assigned_and_polygon_barangay(): void
    {
        $wrong = Place::factory()->create(['name' => 'Wrongly Assign', 'lat' => 17.59, 'lng' => 120.61, 'barangay_id' => $this->mira->id]);
        $missing = Place::factory()->create(['name' => 'Not Assigned', 'lat' => 17.592, 'lng' => 120.615, 'barangay_id' => null]);
        $correct = Place::factory()->create(['name' => 'Correctly Assigned', 'lat' => 17.591, 'lng' => 120.616, 'barangay_id' => $this->palao->id]);
        $outsideNull = Place::factory()->create(['name' => 'Outside No Claim', 'lat' => 17.7, 'lng' => 120.7, 'barangay_id' => null]);
        $outsideWrong = Place::factory()->create(['name' => 'Outside Wrong', 'lat' => 17.7, 'lng' => 120.71, 'barangay_id' => $this->palao->id]);

        $rows = app(GeoSpatial::class)->barangayMismatchRows();

        $ids = collect($rows)->pluck('place_id')->all();

        $this->assertContains((int) $wrong->id, $ids);
        $this->assertContains((int) $missing->id, $ids);
        $this->assertContains((int) $outsideWrong->id, $ids);
        $this->assertNotContains((int) $correct->id, $ids);
        $this->assertNotContains((int) $outsideNull->id, $ids);

        $wrongRow = collect($rows)->firstWhere('place_id', (int) $wrong->id);
        $this->assertSame($this->palao->id, $wrongRow['resolved_id']);
        $this->assertSame('Mira', $wrongRow['assigned_name']);
    }

    public function test_fix_reassigns_mismatched_places(): void
    {
        $wrong = Place::factory()->create(['name' => 'Wrongly Assign', 'lat' => 17.59, 'lng' => 120.61, 'barangay_id' => $this->mira->id]);
        $missing = Place::factory()->create(['name' => 'Not Assigned', 'lat' => 17.592, 'lng' => 120.615, 'barangay_id' => null]);
        $outsideWrong = Place::factory()->create(['name' => 'Outside Wrong', 'lat' => 17.7, 'lng' => 120.71, 'barangay_id' => $this->palao->id]);

        $fixed = app(GeoSpatial::class)->fixBarangayMismatch(10);

        $this->assertSame(3, $fixed);

        $this->assertSame($this->palao->id, $wrong->fresh()->barangay_id);
        $this->assertSame($this->palao->id, $missing->fresh()->barangay_id);
        $this->assertNull($outsideWrong->fresh()->barangay_id);

        $this->assertSame([], app(GeoSpatial::class)->barangayMismatchRows());
    }

    public function test_admin_quality_endpoints_report_and_guard_roles(): void
    {
        Place::factory()->create(['name' => 'Wrongly Assign', 'lat' => 17.59, 'lng' => 120.61, 'barangay_id' => $this->mira->id]);

        $this->getJson('/api/admin/quality/barangay')->assertUnauthorized();

        $user = $this->residentUser();
        $this->actingAs($user)->getJson('/api/admin/quality/barangay')->assertForbidden();

        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/quality/barangay')
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Palao', $response->json('data.0.resolved_name'));

        $fix = $this->actingAs($admin)
            ->postJson('/api/admin/quality/barangay/fix', ['limit' => 10])
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $fix['fixed']);
        $this->assertSame([], app(GeoSpatial::class)->barangayMismatchRows());
    }
}