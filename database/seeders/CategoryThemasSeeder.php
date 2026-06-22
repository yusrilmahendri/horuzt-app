<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoryThemas;

class CategoryThemasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Minimalis', 'slug' => 'minimalis', 'sort_order' => 10],
            ['name' => 'Floral', 'slug' => 'floral', 'sort_order' => 20],
            ['name' => 'Modern', 'slug' => 'modern', 'sort_order' => 30],
            ['name' => 'Elegant', 'slug' => 'elegant', 'sort_order' => 40],
            ['name' => 'Luxury', 'slug' => 'luxury', 'sort_order' => 50],
        ];

        foreach ($categories as $category) {
            CategoryThemas::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['type' => 'website', 'is_active' => true])
            );
        }
    }
}
