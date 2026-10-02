<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceHour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaceFactory extends Factory
{
    protected $model = Place::class;

    public function definition(): array
    {
        $category = Category::inRandomOrder()->first()
            ?? Category::firstOrCreate(
                ['slug' => 'sample-category'],
                [
                    'label' => 'Sample Category',
                    'icon_key' => 'pin',
                    'color' => '#94a3b8',
                    'sort_order' => 99,
                    'is_active' => true,
                ]
            );

        $lat = fake()->randomFloat(6, 17.588, 17.618);
        $lng = fake()->randomFloat(6, 120.600, 120.635);

        $name = ucfirst(fake()->word()) . ' ' . fake()->randomElement(['Grocer', 'Store', 'Mart', 'Depot', 'Canteen', 'Shop']);

        return [
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'address' => fake()->streetAddress() . ', Bangued',
            'lat' => $lat,
            'lng' => $lng,
            'geom' => $this->geomExpression($lng, $lat),
            'source' => 'manual',
            'status' => 'approved',
            'verified_tier' => 'community',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Place $place) {
            DB::table('places')
                ->where('id', $place->id)
                ->update([
                    'geom' => $this->geomExpression($place->lng, $place->lat),
                ]);
        });
    }

    public function unapproved(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function withHours(): static
    {
        return $this->afterCreating(function (Place $place) {
            foreach (range(0, 6) as $day) {
                PlaceHour::create([
                    'place_id' => $place->id,
                    'day' => $day,
                    'opens' => $day === 0 ? null : '07:00:00',
                    'closes' => $day === 0 ? null : '19:00:00',
                    'is_closed' => $day === 0,
                    'note' => null,
                ]);
            }
        });
    }

    private function geomExpression($lng, $lat)
    {
        return DB::raw("ST_GeomFromText('POINT({$lng} {$lat})', 4326)");
    }
}