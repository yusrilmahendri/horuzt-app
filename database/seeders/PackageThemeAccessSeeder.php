<?php

namespace Database\Seeders;

use App\Models\CategoryThemas;
use App\Models\PaketUndangan;
use Illuminate\Database\Seeder;

class PackageThemeAccessSeeder extends Seeder
{
    public function run(): void
    {
        $access = [
            'trial' => [],
            'ruby' => ['minimalis', 'floral'],
            'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
            'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
        ];

        foreach ($access as $code => $categorySlugs) {
            $package = PaketUndangan::where('code', $code)->firstOrFail();
            $categoryIds = CategoryThemas::whereIn('slug', $categorySlugs)->pluck('id');
            $package->accessibleCategories()->sync($categoryIds);
        }
    }
}
