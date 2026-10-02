<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;

trait CoreCatalog
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
    }

    protected function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function moderatorUser(): User
    {
        return $this->adminUser();
    }

    protected function residentUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    protected function category(string $slug): Category
    {
        return Category::where('slug', $slug)->firstOrFail();
    }
}