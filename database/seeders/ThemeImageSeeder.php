<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisThemas;

class ThemeImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample images for themes
        $sampleImages = [
            [
                'preview_image' => 'theme-images/previews/sample-preview-1.jpg',
                'thumbnail_image' => 'theme-images/thumbnails/sample-thumb-1.jpg',
                'preview' => 'theme-images/previews/sample-preview-1.jpg',
                'demo_url' => 'https://demo.example.com/theme-1'
            ],
            [
                'preview_image' => 'theme-images/previews/sample-preview-2.jpg',
                'thumbnail_image' => 'theme-images/thumbnails/sample-thumb-2.jpg',
                'preview' => 'theme-images/previews/sample-preview-2.jpg',
                'demo_url' => 'https://demo.example.com/theme-2'
            ],
            [
                'preview_image' => 'theme-images/previews/sample-preview-3.jpg',
                'thumbnail_image' => 'theme-images/thumbnails/sample-thumb-3.jpg',
                'preview' => 'theme-images/previews/sample-preview-3.jpg',
                'demo_url' => 'https://demo.example.com/theme-3'
            ]
        ];

        // Get all existing themes
        $themes = JenisThemas::all();

        // Assign sample images to themes
        foreach ($themes as $index => $theme) {
            $imageData = $sampleImages[$index % count($sampleImages)];

            $theme->update([
                'preview_image' => $imageData['preview_image'],
                'thumbnail_image' => $imageData['thumbnail_image'],
                'preview' => $imageData['preview'],
                'demo_url' => $imageData['demo_url'],
                'description' => $theme->description ?: 'Beautiful ' . strtolower($theme->name) . ' theme with modern design and responsive layout.',
                'features' => $theme->features ?: ['Responsive', 'Mobile-friendly', 'Modern Design', 'Fast Loading']
            ]);
        }

        $this->command->info('Added sample images to ' . $themes->count() . ' themes.');
    }
}
