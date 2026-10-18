<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'address',
        'barangay_id',
        'geom',
        'lat',
        'lng',
        'source',
        'source_ref',
        'source_url',
        'status',
        'submitted_by',
        'approved_by',
        'approved_at',
        'verified_tier',
        'is_claimed',
        'claimed_by',
        'phone',
        'website',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_claimed' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(BoundaryArea::class, 'barangay_id');
    }

    public function hours(): HasMany
    {
        return $this->hasMany(PlaceHour::class)->orderBy('day');
    }

    public function photos(): HasMany
    {
        // is_cover first, then manual sort_order, with id as deterministic tiebreaker.
        return $this->hasMany(PlacePhoto::class)->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PlaceReport::class)->orderByDesc('created_at');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeClaimed(Builder $query): Builder
    {
        return $query->where('is_claimed', true);
    }

    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->where('is_claimed', false);
    }

    public function scopeInBounds(Builder $query, float|int|string $south, float|int|string $west, float|int|string $north, float|int|string $east): Builder
    {
        $minLat = min((float) $south, (float) $north);
        $maxLat = max((float) $south, (float) $north);
        $minLng = min((float) $west, (float) $east);
        $maxLng = max((float) $west, (float) $east);

        return $query->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng])
            ->withinBbox($south, $west, $north, $east);
    }

    public function scopeWithinBbox(Builder $query, $south, $west, $north, $east): Builder
    {
        return $query->whereRaw(
            'MBRContains(ST_GeomFromText(?), geom)',
            ['POLYGON(('.$west.' '.$south.','.$east.' '.$south.','.$east.' '.$north.','.$west.' '.$north.','.$west.' '.$south.'))']
        );
    }

    public function scopeWithinRadius(Builder $query, $lat, $lng, $radius): Builder
    {
        return $query->whereRaw(
            'ST_Distance_Sphere(geom, ST_GeomFromText(?, 4326), 6371008.8) <= ?',
            ['POINT('.$lng.' '.$lat.')', $radius]
        );
    }

    public function scopeWithDistance(Builder $query, $lat, $lng): Builder
    {
        if (empty($query->getQuery()->columns)) {
            $query->select('places.*');
        }

        return $query->selectRaw(
            'ST_Distance_Sphere(geom, ST_GeomFromText(?, 4326), 6371008.8) AS distance_m',
            ['POINT('.$lng.' '.$lat.')']
        );
    }

    public function scopeSearch(Builder $query, mixed $q): Builder
    {
        $raw = (string) $q;
        $tokens = array_values(array_filter(array_map(
            fn (string $token) => mb_substr(Text::fold($token), 0, 60),
            preg_split('/\s+/u', trim($raw)),
        ), fn (string $token) => $token !== ''));

        if ($tokens === []) {
            return $query;
        }

        foreach ($tokens as $token) {
            $value = '%'.$token.'%';
            $query->where(function (Builder $b) use ($value) {
                $b->where('name', 'like', $value)
                    ->orWhere('address', 'like', $value)
                    ->orWhere('slug', 'like', $value);
            });
        }

        return $query;
    }

    public function scopeByTier(Builder $query, $tier): Builder
    {
        return $query->where('verified_tier', $tier);
    }

    public function scopeInCategories(Builder $query, array $slugs): Builder
    {
        return $query->whereIn('category_id', function ($q) use ($slugs) {
            $q->select('id')->from('categories')->whereIn('slug', $slugs);
        });
    }
}
