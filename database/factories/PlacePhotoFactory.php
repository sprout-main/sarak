<?php

namespace Database\Factories;

use App\Enums\PhotoStatus;
use App\Models\Place;
use App\Models\PlacePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlacePhotoFactory extends Factory
{
    protected $model = PlacePhoto::class;

    public function definition(): array
    {
        $place = Place::query()->first() ?? Place::factory()->create();

        return [
            'place_id' => $place->id,
            'path' => 'photos/'.$place->id.'/'.Str::random(40).'.jpg',
            'uploader_id' => null,
            'is_cover' => false,
            'status' => PhotoStatus::Approved->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PhotoStatus::Pending->value]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => PhotoStatus::Rejected->value]);
    }

    public function cover(): static
    {
        return $this->state(fn () => ['is_cover' => true]);
    }

    // Named forPlace rather than for: Factory::for() already exists with an
    // incompatible signature, and overriding it makes the factory unbootable.
    public function forPlace(Place|int $place): static
    {
        $id = $place instanceof Place ? $place->id : $place;

        return $this->state(fn () => [
            'place_id' => $id,
            'path' => 'photos/'.$id.'/'.Str::random(40).'.jpg',
        ]);
    }
}
