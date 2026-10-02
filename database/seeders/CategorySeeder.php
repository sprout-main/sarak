<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['coffee_shop', 'Coffee Shops', 'coffee_shop', '#a16207', 1],
            ['fast_food', 'Fast Food Chains', 'fast_food', '#fb923c', 2],
            ['grocery', 'Groceries', 'grocery', '#34d399', 3],
            ['hospital', 'Hospitals', 'hospital', '#fb7185', 4],
            ['pharmacy', 'Pharmacies', 'pharmacy', '#2dd4bf', 5],
        ];

        foreach ($categories as [$slug, $label, $iconKey, $color, $sortOrder]) {
            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $label,
                    'icon_key' => $iconKey,
                    'color' => $color,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );
        }

        Category::whereIn('slug', ['school', 'restaurant', 'gas_station'])->update(['is_active' => false]);
    }
}