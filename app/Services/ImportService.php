<?php

namespace App\Services;

use App\Enums\QueueAction;
use App\Models\Category;
use App\Models\ImportRun;
use Throwable;

class ImportService
{
    private const CATEGORY_ALIASES = [
        'coffee_shop' => ['cafe', 'cafes', 'coffee', 'coffee_shop', 'coffee shop', 'coffee_shops', 'coffee shops', 'coffeehouse', 'coffee house', 'kapehan', 'kape'],
        'pharmacy' => ['pharmacy', 'pharmacies', 'drugstore', 'drug_store', 'chemist', 'chemists', 'botika'],
        'hospital' => ['hospital', 'hospitals', 'clinic', 'clinics', 'medical_center', 'medical center', 'health_center', 'health center', 'medical_centre'],
        'fast_food' => ['fast_food', 'fast food', 'fastfood', 'fast_food_chains', 'fast food chains'],
        'grocery' => ['supermarket', 'convenience', 'convenience_store', 'convenience store', 'grocery', 'groceries', 'marketplace', 'market', 'mini_market', 'mini market', 'minimarket'],
    ];

    public function __construct(
        private ModerationService $moderation,
        private DedupeService $dedupe,
        private BarangayResolver $barangays,
    ) {
    }

    private array $categoryIdCache = [];

    public function runImport(int $runId, array $elements, string $source, bool $final = true): array
    {
        $run = ImportRun::findOrFail($runId);
        $prior = is_array($run->batch_summary) ? $run->batch_summary : [];
        $summary = [
            'attempted' => (int) ($prior['attempted'] ?? 0),
            'queued' => (int) ($prior['queued'] ?? 0),
            'skipped_duplicates' => (int) ($prior['skipped_duplicates'] ?? 0),
            'skipped_invalid' => (int) ($prior['skipped_invalid'] ?? 0),
        ];

        try {
            $summary['attempted'] += count($elements);
            foreach ($elements as $element) {
                if (!is_array($element)) {
                    $summary['skipped_invalid']++;

                    continue;
                }

                $payload = $this->normalizePayload($element, $source);
                if (!$this->isSubmittable($payload)) {
                    $summary['skipped_invalid']++;

                    continue;
                }

                if ($this->dedupe->isDuplicateApproved(
                    (string) $payload['name'],
                    (float) $payload['lat'],
                    (float) $payload['lng'],
                    25,
                    isset($payload['phone']) ? (string) $payload['phone'] : null,
                ) !== null) {
                    $summary['skipped_duplicates']++;

                    continue;
                }

                if ($this->dedupe->checkAgainstQueue($payload)) {
                    $summary['skipped_duplicates']++;

                    continue;
                }

                $payload['barangay_id'] = $this->barangays->resolveId((float) $payload['lat'], (float) $payload['lng']);

                $queuedPayload = array_replace($payload, [
                    'source' => $source,
                    'source_ref' => $payload['source_ref'] ?? null,
                    'fetched_at' => now()->toIso8601String(),
                ]);

                $this->moderation->submit(QueueAction::Create, $queuedPayload, null);
                $summary['queued']++;
            }

            $run->forceFill([
                'batch_summary' => $summary,
                'status' => $final ? 'done' : 'running',
                'finished_at' => $final ? now() : null,
            ])->save();

            return $summary;
        } catch (Throwable $e) {
            $run->forceFill([
                'batch_summary' => array_merge($summary, ['error' => $e->getMessage()]),
                'status' => 'failed',
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }

    public static function bestMatchCategorySlug(string $raw): ?string
    {
        $key = self::normalizeToken($raw);
        if ($key === '') {
            return null;
        }

        foreach (self::CATEGORY_ALIASES as $slug => $aliases) {
            if ($key === self::normalizeToken($slug)) {
                return $slug;
            }

            foreach ($aliases as $alias) {
                if ($key === self::normalizeToken($alias)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    private static function normalizeToken(string $value): string
    {
        $lower = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $cleaned = (string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower));

        return trim($cleaned);
    }

    private function normalizePayload(array $payload, string $source): array
    {
        $out = $payload;

        foreach (['name', 'address', 'phone', 'website', 'source_ref'] as $key) {
            if (isset($out[$key]) && !is_string($out[$key])) {
                $out[$key] = null;
            }
        }

        if (isset($out['lat'])) {
            $out['lat'] = (float) $out['lat'];
        }
        if (isset($out['lng'])) {
            $out['lng'] = (float) $out['lng'];
        }

        $categorySlug = isset($out['category_slug']) && is_string($out['category_slug'])
            ? $out['category_slug']
            : (isset($out['category']) && is_string($out['category']) ? $out['category'] : null);
        $out['category_slug'] = $categorySlug !== null ? self::bestMatchCategorySlug($categorySlug) : null;
        $out['category_id'] = $this->categoryIdForSlug($out['category_slug']);

        return $out;
    }

    private function categoryIdForSlug(?string $slug): ?int
    {
        if ($slug === null) {
            return null;
        }

        if (!array_key_exists($slug, $this->categoryIdCache)) {
            $this->categoryIdCache[$slug] = Category::where('slug', $slug)->value('id');
        }

        return $this->categoryIdCache[$slug];
    }

    private function isSubmittable(array $payload): bool
    {
        if (!isset($payload['name']) || trim((string) $payload['name']) === '') {
            return false;
        }

        if (!isset($payload['lat']) || !isset($payload['lng'])) {
            return false;
        }

        $lat = (float) $payload['lat'];
        $lng = (float) $payload['lng'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }

        return true;
    }
}