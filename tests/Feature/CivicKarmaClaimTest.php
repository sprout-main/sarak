<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use App\Services\KarmaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class CivicKarmaClaimTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_claim_endpoint_is_removed(): void
    {
        $place = Place::factory()->create();
        $user = $this->residentUser();

        $this->actingAs($user)->postJson("/api/places/{$place->id}/claim")
            ->assertNotFound();
    }

    public function test_submitting_a_place_earns_zero_karma_until_approved(): void
    {
        $user = $this->residentUser();
        $this->assertSame(0, $user->karma_points);

        $res = $this->actingAs($user)->postJson('/api/places', [
            'name' => 'Kusina Abra',
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.598,
            'lng' => 120.618,
        ])->assertStatus(201);

        $user->refresh();
        $this->assertSame(0, $user->karma_points);

        $queueId = $res->json('data.queue_id');
        $this->assertNotNull($queueId);

        // Admin approves the place submission
        $admin = $this->adminUser();
        $this->actingAs($admin)->postJson("/api/admin/queue/{$queueId}/approve")
            ->assertOk();

        $user->refresh();
        $this->assertSame(20, $user->karma_points);
        $this->assertSame(KarmaService::TIER_APPRENTICE, $user->reputation_badge);
    }

    public function test_admin_approving_pending_place_status_awards_karma_to_submitter(): void
    {
        $submitter = $this->residentUser();
        $admin = $this->adminUser();

        $place = Place::factory()->create([
            'status' => 'pending',
            'submitted_by' => $submitter->id,
        ]);

        $this->assertSame(0, $submitter->karma_points);

        $this->actingAs($admin)->patchJson("/api/admin/places/{$place->id}", [
            'status' => 'approved',
        ])->assertOk();

        $submitter->refresh();
        $this->assertSame(20, $submitter->karma_points);
    }

    public function test_karma_service_tier_badge_progression(): void
    {
        $service = app(KarmaService::class);
        $user = $this->residentUser();

        $this->assertSame(KarmaService::TIER_APPRENTICE, $service->calculateBadge(0));
        $this->assertSame(KarmaService::TIER_APPRENTICE, $service->calculateBadge(49));

        $service->awardUser($user, 50);
        $user->refresh();
        $this->assertSame(KarmaService::TIER_RANGER, $user->reputation_badge);

        $service->awardUser($user, 150);
        $user->refresh();
        $this->assertSame(KarmaService::TIER_GUARDIAN, $user->reputation_badge);
    }

    public function test_leaderboard_endpoint_returns_ranked_contributors(): void
    {
        $topUser = User::factory()->create(['name' => 'Top Ranger', 'karma_points' => 300, 'reputation_badge' => KarmaService::TIER_GUARDIAN]);
        $midUser = User::factory()->create(['name' => 'Mid Scout', 'karma_points' => 80, 'reputation_badge' => 'Bangued Ranger']);
        $newUser = User::factory()->create(['name' => 'New Contributor', 'karma_points' => 10, 'reputation_badge' => 'Apprentice Scout']);

        $response = $this->getJson('/api/contributors/leaderboard?limit=10')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'rank',
                        'id',
                        'name',
                        'karma_points',
                        'reputation_badge',
                        'claimed_count',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Find users in the leaderboard list
        $userRanks = collect($data)->keyBy('id');
        $this->assertTrue($userRanks->has($topUser->id));
        $this->assertTrue($userRanks->has($midUser->id));

        $topRank = $userRanks[$topUser->id]['rank'];
        $midRank = $userRanks[$midUser->id]['rank'];
        $this->assertLessThan($midRank, $topRank);
    }

    public function test_contributor_privacy_censoring_and_staff_roles_on_leaderboard(): void
    {
        $headAdmin = User::factory()->create([
            'name' => 'Bangued Supreme',
            'email' => 'admin@bangued.ph',
            'role' => 'admin',
            'karma_points' => 500,
            'censor_contributor_name' => true,
        ]);

        $deputyAdmin = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'deputy@bangued.ph',
            'role' => 'admin',
            'karma_points' => 300,
            'censor_contributor_name' => false,
        ]);

        $resident = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'role' => 'user',
            'karma_points' => 150,
            'censor_contributor_name' => true,
        ]);

        $response = $this->getJson('/api/contributors/leaderboard?limit=10')
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');

        // Head Admin: censored by default, marked as admin and head_admin
        $this->assertTrue($rows->has($headAdmin->id));
        $this->assertSame('B*** S***', $rows[$headAdmin->id]['name']);
        $this->assertTrue($rows[$headAdmin->id]['is_censored']);
        $this->assertTrue($rows[$headAdmin->id]['is_admin']);
        $this->assertTrue($rows[$headAdmin->id]['is_head_admin']);

        // Deputy Admin: explicitly uncensored
        $this->assertTrue($rows->has($deputyAdmin->id));
        $this->assertSame('Maria Santos', $rows[$deputyAdmin->id]['name']);
        $this->assertFalse($rows[$deputyAdmin->id]['is_censored']);
        $this->assertTrue($rows[$deputyAdmin->id]['is_admin']);
        $this->assertFalse($rows[$deputyAdmin->id]['is_head_admin']);

        // Resident: censored by default, not admin
        $this->assertTrue($rows->has($resident->id));
        $this->assertSame('J*** D*** C***', $rows[$resident->id]['name']);
        $this->assertTrue($rows[$resident->id]['is_censored']);
        $this->assertFalse($rows[$resident->id]['is_admin']);
        $this->assertFalse($rows[$resident->id]['is_head_admin']);
    }

    public function test_user_can_toggle_contributor_privacy_preference(): void
    {
        $user = User::factory()->create([
            'name' => 'Pedro Penduko',
            'censor_contributor_name' => true,
        ]);

        // Uncensor name
        $this->actingAs($user)
            ->patchJson('/profile/contributor-privacy', [
                'censor_contributor_name' => false,
            ])
            ->assertOk()
            ->assertJsonPath('censor_contributor_name', false);

        $this->assertFalse($user->fresh()->censor_contributor_name);

        // Re-censor name
        $this->actingAs($user)
            ->patchJson('/profile/contributor-privacy', [
                'censor_contributor_name' => true,
            ])
            ->assertOk()
            ->assertJsonPath('censor_contributor_name', true);

        $this->assertTrue($user->fresh()->censor_contributor_name);
    }
}
