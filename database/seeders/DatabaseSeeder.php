<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            BoundarySeeder::class,
            GoogleMapsPlacesSeeder::class,
            BanguedPhotosSeeder::class,
        ]);
    }
}