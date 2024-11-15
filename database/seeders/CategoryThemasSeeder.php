<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            ['name' => 'thema_video'],
            ['name' => 'slug_video'],
            ['name' => 'thema_website'],
            ['name' => 'slug_website'],
        ];

        foreach ($categories as $category) {
            CategoryThemas::create($category);
        }
    }
}
