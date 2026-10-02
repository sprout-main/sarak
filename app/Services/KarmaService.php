<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class KarmaService
{
    public const TIER_APPRENTICE = 'Apprentice Scout';
    public const TIER_RANGER = 'Bangued Ranger';
    public const TIER_GUARDIAN = 'Sarak Guardian';

    public function awardUser(User $user, int $points): User
    {
        $user->karma_points = max(0, (int) $user->karma_points + $points);
        $user->reputation_badge = $this->calculateBadge($user->karma_points);
        $user->save();

        return $user;
    }

    public function calculateBadge(int $points): string
    {
        if ($points >= 200) {
            return self::TIER_GUARDIAN;
        }

        if ($points >= 50) {
            return self::TIER_RANGER;
        }

        return self::TIER_APPRENTICE;
    }

    public static function maskName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return 'C***';
        }

        $masked = array_map(function (string $part) {
            $len = mb_strlen($part);
            if ($len <= 1) {
                return $part . '***';
            }
            return mb_substr($part, 0, 1) . '***';
        }, $parts);

        return implode(' ', $masked);
    }

    public function leaderboard(int $limit = 10): Collection
    {
        $users = User::query()
            ->where('karma_points', '>', 0)
            ->withCount([
                'claimedPlaces',
                'submittedPlaces as approved_submissions_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->orderByDesc('karma_points')
            ->orderBy('id')
            ->limit(max(1, min(50, $limit)))
            ->get();

        return $users->values()->map(function (User $user, int $index) {
            $shouldCensor = $user->censor_contributor_name !== false;

            return [
                'rank' => $index + 1,
                'id' => $user->id,
                'name' => $shouldCensor ? self::maskName((string) $user->name) : (string) $user->name,
                'is_censored' => $shouldCensor,
                'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
                'is_admin' => $user->isAdmin(),
                'is_head_admin' => $user->isHeadAdmin(),
                'karma_points' => (int) $user->karma_points,
                'reputation_badge' => $user->reputation_badge ?: $this->calculateBadge((int) $user->karma_points),
                'claimed_count' => (int) $user->claimed_places_count,
                'approved_submissions_count' => (int) ($user->approved_submissions_count ?? 0),
            ];
        });
    }
}
