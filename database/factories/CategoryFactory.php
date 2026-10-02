<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $label = ucfirst(fake()->unique()->word());

        return [
            'slug' => Str::slug($label . '-' . Str::random(4)),
            'label' => $label,
            'icon_key' => fake()->randomElement(['coffee_shop', 'fast_food', 'grocery', 'hospital', 'pharmacy']),
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}