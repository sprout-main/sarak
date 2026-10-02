<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Place;
use App\Support\Text;
use Illuminate\Support\Collection;

class SemanticSearchService
{
    private const CATEGORY_VOCABULARY = [
        'coffee_shop' => [
            'kape', 'kapehan', 'coffee', 'cafe', 'espresso', 'brew', 'latte', 'study',
            'work', 'laptop', 'wifi', 'internet', 'generator', 'power', 'pagsalaan',
            'tambayan', 'chill', 'merienda', 'cozy',
        ],
        'fast_food' => [
            'mangan', 'kanan', 'food', 'fastfood', 'burger', 'chicken', 'fries',
            'takeout', 'dine', 'dinner', 'lunch', 'breakfast', 'rabii', 'ulam',
            'snack', 'eatery', 'takeaway',
        ],
        'grocery' => [
            'tindahan', 'groseri', 'supermarket', 'mart', 'market', 'tienda', 'store',
            'goods', 'gulay', 'supply', 'supplies', 'grocery', 'presko', 'karne',
            'pamilihan',
        ],
        'hospital' => [
            'ospital', 'doktor', 'doctor', 'clinic', 'klinika', 'emergency',
            'medical', 'dentist', 'health', 'tulong', 'buntis', 'panganak', 'nurse',
            'checkup', 'first aid', 'ambulance',
        ],
        'pharmacy' => [
            'agas', 'botika', 'pharmacy', 'gamot', 'droga', 'medicine', 'prescription',
            'vitamins', 'reseta', 'mercury', 'drugstore', 'pildoras',
        ],
    ];

    public function __construct(
        private readonly HoursService $hours,
    ) {
    }

    public function search(string $query, ?float $lat = null, ?float $lng = null, int $limit = 10): Collection
    {
        $raw = trim($query);
        if ($raw === '') {
            return collect();
        }

        $normalized = Text::fold($raw);
        $tokens = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $normalized),
            fn ($t) => mb_strlen($t) >= 2
        ));

        if (empty($tokens)) {
            return collect();
        }

        $matchedCategorySlugs = $this->detectCategoryIntents($tokens);
        $wantsOpen = $this->containsAny($tokens, ['open', 'lukat', 'now', 'tatta']);
        $wantsLate = $this->containsAny($tokens, ['late', 'rabii', '247', '24h', 'overnight', 'gabi']);
        $wantsPower = $this->containsAny($tokens, ['generator', 'power', 'kuryente', 'outlet']);

        $categoryIds = !empty($matchedCategorySlugs)
            ? Category::whereIn('slug', $matchedCategorySlugs)->pluck('id')->all()
            : [];

        $placesQuery = Place::approved()
            ->select('places.*')
            ->with(['category', 'barangay', 'hours', 'photos']);

        if ($lat !== null && $lng !== null) {
            $placesQuery->withDistance($lat, $lng);
        }

        $places = $placesQuery->get();

        $scored = $places->map(function (Place $place) use ($tokens, $categoryIds, $matchedCategorySlugs, $wantsOpen, $wantsLate, $wantsPower) {
            $score = 0;
            $reasons = [];

            $nameFolded = Text::fold($place->name ?? '');
            $descFolded = Text::fold($place->description ?? '');
            $addrFolded = Text::fold($place->address ?? '');

            if (!empty($categoryIds) && in_array($place->category_id, $categoryIds, true)) {
                $score += 45;
                $catSlug = $place->category?->slug ?? '';
                $reasons[] = "Matches category intent for {$place->category?->label}";
            }

            foreach ($tokens as $token) {
                if (str_contains($nameFolded, $token)) {
                    $score += 40;
                    $reasons[] = "Direct match in name: '{$token}'";
                } elseif (str_contains($descFolded, $token)) {
                    $score += 25;
                    $reasons[] = "Mentioned in description: '{$token}'";
                } elseif (str_contains($addrFolded, $token)) {
                    $score += 15;
                    $reasons[] = "Location match: '{$token}'";
                }
            }

            $isOpen = $this->hours->openNow($place);
            $is24h = $this->hours->is24h($place);

            if ($wantsOpen) {
                if ($isOpen) {
                    $score += 35;
                    $reasons[] = 'Currently open';
                } elseif ($is24h) {
                    $score += 35;
                    $reasons[] = 'Open 24 hours';
                }
            }

            if ($wantsLate) {
                if ($is24h) {
                    $score += 40;
                    $reasons[] = 'Open 24 hours';
                } elseif ($isOpen) {
                    $score += 20;
                    $reasons[] = 'Open now';
                }
            }

            if ($wantsPower) {
                if (str_contains($descFolded, 'generator') || str_contains($nameFolded, 'generator')) {
                    $score += 50;
                    $reasons[] = 'Equipped with backup generator';
                } elseif ($place->category?->slug === 'coffee_shop') {
                    $score += 20;
                    $reasons[] = 'Work/study friendly';
                }
            }

            if ($place->verified_tier === 'verified') {
                $score += 10;
                $reasons[] = 'Verified civic listing';
            }

            if ($place->distance_m !== null) {
                $distKm = $place->distance_m / 1000;
                if ($distKm <= 1.0) {
                    $score += 15;
                } elseif ($distKm <= 3.0) {
                    $score += 8;
                }
            }

            return [
                'place' => $place,
                'score' => $score,
                'reasons' => array_values(array_unique($reasons)),
            ];
        });

        return $scored->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(function ($item) {
                /** @var Place $place */
                $place = $item['place'];
                $telemetry = $this->hours->telemetry($place);

                return [
                    'id' => $place->id,
                    'name' => $place->name,
                    'slug' => $place->slug,
                    'category' => $place->category ? [
                        'id' => $place->category->id,
                        'slug' => $place->category->slug,
                        'label' => $place->category->label,
                        'color' => $place->category->color,
                    ] : null,
                    'barangay' => $place->barangay ? [
                        'id' => $place->barangay->id,
                        'name' => $place->barangay->name,
                    ] : null,
                    'address' => $place->address,
                    'lat' => $place->lat,
                    'lng' => $place->lng,
                    'distance_m' => $place->distance_m !== null ? round((float) $place->distance_m, 1) : null,
                    'telemetry' => $telemetry,
                    'score' => $item['score'],
                    'match_reasons' => $item['reasons'],
                ];
            });
    }

    private function detectCategoryIntents(array $tokens): array
    {
        $categories = [];

        foreach (self::CATEGORY_VOCABULARY as $categorySlug => $words) {
            foreach ($tokens as $token) {
                if (in_array($token, $words, true)) {
                    $categories[] = $categorySlug;
                    break;
                }
            }
        }

        return array_values(array_unique($categories));
    }

    private function containsAny(array $tokens, array $candidates): bool
    {
        foreach ($tokens as $token) {
            if (in_array($token, $candidates, true)) {
                return true;
            }
        }

        return false;
    }
}
