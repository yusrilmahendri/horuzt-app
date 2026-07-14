<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORIES = [
        'minimalis' => ['name' => 'Minimalis', 'sort_order' => 10],
        'floral' => ['name' => 'Floral', 'sort_order' => 20],
        'elegant' => ['name' => 'Elegant', 'sort_order' => 40],
        'luxury' => ['name' => 'Luxury', 'sort_order' => 50],
    ];

    private const THEMES = [
        'soft-ivory' => ['name' => 'Soft Ivory', 'category' => 'minimalis', 'package' => 'ruby', 'sort_order' => 10],
        'lavender-bloom' => ['name' => 'Lavender Bloom', 'category' => 'floral', 'package' => 'ruby', 'sort_order' => 20],
        'garden-whisper' => ['name' => 'Garden Whisper', 'category' => 'floral', 'package' => 'sapphire', 'sort_order' => 30],
        'champagne-rose' => ['name' => 'Champagne Rose', 'category' => 'elegant', 'package' => 'diamond', 'sort_order' => 40],
        'diamond-garden' => ['name' => 'Diamond Garden', 'category' => 'luxury', 'package' => 'diamond', 'sort_order' => 50],
    ];

    private const ACCESS = [
        'trial' => ['minimalis'],
        'ruby' => ['minimalis', 'floral'],
        'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
        'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('category_themas') || ! Schema::hasTable('jenis_themas')) {
            return;
        }

        $now = now();
        $categoryIds = [];

        foreach (self::CATEGORIES as $slug => $definition) {
            $category = DB::table('category_themas')
                ->where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($definition['name'])])
                ->orderBy('id')
                ->first();

            $values = [
                'name' => $definition['name'],
                'slug' => $slug,
                'type' => 'website',
                'is_active' => true,
                'sort_order' => $definition['sort_order'],
                'updated_at' => $now,
            ];

            if ($category) {
                DB::table('category_themas')->where('id', $category->id)->update($values);
                $categoryIds[$slug] = $category->id;
                continue;
            }

            $categoryIds[$slug] = DB::table('category_themas')->insertGetId($values + [
                'description' => null,
                'icon' => null,
                'created_at' => $now,
            ]);
        }

        foreach (self::THEMES as $slug => $definition) {
            $theme = DB::table('jenis_themas')
                ->where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($definition['name'])])
                ->orderBy('id')
                ->first();

            $values = [
                'category_id' => $categoryIds[$definition['category']],
                'name' => $definition['name'],
                'slug' => $slug,
                'price' => '0',
                'preview' => $theme?->preview ?: 'theme-images/previews/'.$slug.'.jpg',
                'url_thema' => $theme?->url_thema ?: '/themes/'.$slug,
                'is_active' => true,
                'description' => $theme?->description ?: $definition['name'].' website theme',
                'demo_url' => $theme?->demo_url ?: '/themes/'.$slug,
                'sort_order' => $definition['sort_order'],
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('jenis_themas', 'preview_image')) {
                $values['preview_image'] = $theme?->preview_image ?: 'theme-images/previews/'.$slug.'.jpg';
            }

            if (Schema::hasColumn('jenis_themas', 'thumbnail_image')) {
                $values['thumbnail_image'] = $theme?->thumbnail_image ?: 'theme-images/thumbnails/'.$slug.'.jpg';
            }

            if (Schema::hasColumn('jenis_themas', 'image')) {
                $values['image'] = $theme?->image ?: 'theme-images/previews/'.$slug.'.jpg';
            }

            if ($theme) {
                DB::table('jenis_themas')->where('id', $theme->id)->update($values);
                continue;
            }

            DB::table('jenis_themas')->insert($values + ['created_at' => $now]);
        }

        $this->syncPackageCategoryAccess($now);
    }

    public function down(): void
    {
        // Data backfill only; intentionally no destructive rollback.
    }

    private function syncPackageCategoryAccess($now): void
    {
        if (! Schema::hasTable('paket_undangan_category_thema')
            || ! Schema::hasColumn('paket_undangans', 'code')) {
            return;
        }

        $packageIds = DB::table('paket_undangans')
            ->whereIn('code', array_keys(self::ACCESS))
            ->pluck('id', 'code');
        $categoryIds = DB::table('category_themas')
            ->whereIn('slug', collect(self::ACCESS)->flatten()->unique()->all())
            ->pluck('id', 'slug');

        foreach (self::ACCESS as $code => $categorySlugs) {
            if (! isset($packageIds[$code])) {
                continue;
            }

            foreach ($categorySlugs as $categorySlug) {
                if (! isset($categoryIds[$categorySlug])) {
                    continue;
                }

                DB::table('paket_undangan_category_thema')->updateOrInsert(
                    [
                        'paket_undangan_id' => $packageIds[$code],
                        'category_thema_id' => $categoryIds[$categorySlug],
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
};
