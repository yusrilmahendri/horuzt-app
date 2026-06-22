<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisThemas;
use App\Models\CategoryThemas;

class JenisThemasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $themes = [
            ['name' => 'Soft Ivory', 'slug' => 'soft-ivory', 'category' => 'minimalis', 'sort_order' => 10],
            ['name' => 'Lavender Bloom', 'slug' => 'lavender-bloom', 'category' => 'floral', 'sort_order' => 10],
            ['name' => 'Garden Whisper', 'slug' => 'garden-whisper', 'category' => 'floral', 'sort_order' => 20],
            ['name' => 'Modern Vows', 'slug' => 'modern-vows', 'category' => 'modern', 'sort_order' => 10],
            ['name' => 'Champagne Rose', 'slug' => 'champagne-rose', 'category' => 'elegant', 'sort_order' => 10],
            ['name' => 'Velvet Mauve', 'slug' => 'velvet-mauve', 'category' => 'luxury', 'sort_order' => 10],
        ];

        foreach ($themes as $theme) {
            $category = CategoryThemas::where('slug', $theme['category'])->firstOrFail();

            JenisThemas::updateOrCreate(
                ['slug' => $theme['slug']],
                [
                    'category_id' => $category->id,
                    'name' => $theme['name'],
                    'price' => 0,
                    'preview' => '',
                    'url_thema' => '/themes/'.$theme['slug'],
                    'is_active' => true,
                    'sort_order' => $theme['sort_order'],
                ]
            );
        }
    }
}
