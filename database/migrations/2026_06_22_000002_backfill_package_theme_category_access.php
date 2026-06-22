<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PACKAGES = [
        'trial' => [
            'name' => 'Paket Trial',
            'aliases' => ['trial'],
            'defaults' => [
                'price' => 0,
                'masa_aktif' => 3,
                'halaman_buku' => 0,
                'kirim_wa' => false,
                'bebas_pilih_tema' => false,
                'kirim_hadiah' => false,
                'import_data' => false,
            ],
        ],
        'ruby' => [
            'name' => 'Paket Ruby',
            'aliases' => ['silver', 'standart', 'standar', 'ruby'],
            'defaults' => [
                'price' => 99000,
                'masa_aktif' => 30,
                'halaman_buku' => 50,
                'kirim_wa' => true,
                'bebas_pilih_tema' => false,
                'kirim_hadiah' => false,
                'import_data' => true,
            ],
        ],
        'sapphire' => [
            'name' => 'Paket Sapphire',
            'aliases' => ['gold', 'sapphire'],
            'defaults' => [
                'price' => 199000,
                'masa_aktif' => 60,
                'halaman_buku' => 100,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => false,
                'import_data' => true,
            ],
        ],
        'diamond' => [
            'name' => 'Paket Diamond',
            'aliases' => ['platinum', 'diamond'],
            'defaults' => [
                'price' => 299000,
                'masa_aktif' => 90,
                'halaman_buku' => 200,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => true,
                'import_data' => true,
            ],
        ],
    ];

    private const CATEGORIES = [
        'minimalis' => ['name' => 'Minimalis', 'sort_order' => 10],
        'floral' => ['name' => 'Floral', 'sort_order' => 20],
        'modern' => ['name' => 'Modern', 'sort_order' => 30],
        'elegant' => ['name' => 'Elegant', 'sort_order' => 40],
        'luxury' => ['name' => 'Luxury', 'sort_order' => 50],
    ];

    private const THEMES = [
        'soft-ivory' => ['name' => 'Soft Ivory', 'category' => 'minimalis', 'sort_order' => 10],
        'lavender-bloom' => ['name' => 'Lavender Bloom', 'category' => 'floral', 'sort_order' => 10],
        'garden-whisper' => ['name' => 'Garden Whisper', 'category' => 'floral', 'sort_order' => 20],
        'modern-vows' => ['name' => 'Modern Vows', 'category' => 'modern', 'sort_order' => 10],
        'champagne-rose' => ['name' => 'Champagne Rose', 'category' => 'elegant', 'sort_order' => 10],
        'velvet-mauve' => ['name' => 'Velvet Mauve', 'category' => 'luxury', 'sort_order' => 10],
    ];

    private const ACCESS = [
        'trial' => ['minimalis'],
        'ruby' => ['minimalis', 'floral'],
        'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
        'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
    ];

    public function up(): void
    {
        $now = now();
        $packageIds = [];

        foreach (self::PACKAGES as $code => $definition) {
            $package = DB::table('paket_undangans')->where('code', $code)->first();

            if (! $package) {
                $package = DB::table('paket_undangans')->orderBy('id')->get()->first(
                    fn ($candidate) => $this->matchesPackage($candidate, $definition['aliases'])
                );
            }

            $values = [
                'code' => $code,
                'jenis_paket' => $definition['name'],
                'name_paket' => $definition['name'],
                'updated_at' => $now,
            ];

            if ($code === 'trial') {
                $values = array_merge($values, $definition['defaults']);
            }

            if ($package) {
                DB::table('paket_undangans')->where('id', $package->id)->update($values);
                $packageIds[$code] = $package->id;
                continue;
            }

            $packageIds[$code] = DB::table('paket_undangans')->insertGetId(array_merge(
                $definition['defaults'],
                $values,
                ['created_at' => $now]
            ));
        }

        $categoryIds = [];
        foreach (self::CATEGORIES as $slug => $definition) {
            $category = DB::table('category_themas')->where('slug', $slug)->first();

            if ($category) {
                DB::table('category_themas')->where('id', $category->id)->update([
                    'name' => $definition['name'],
                    'type' => 'website',
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                    'updated_at' => $now,
                ]);
                $categoryIds[$slug] = $category->id;
                continue;
            }

            $categoryIds[$slug] = DB::table('category_themas')->insertGetId([
                'name' => $definition['name'],
                'slug' => $slug,
                'is_active' => true,
                'type' => 'website',
                'description' => null,
                'icon' => null,
                'sort_order' => $definition['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::THEMES as $slug => $definition) {
            $theme = DB::table('jenis_themas')->where('slug', $slug)->first();
            $values = [
                'category_id' => $categoryIds[$definition['category']],
                'name' => $definition['name'],
                'is_active' => true,
                'sort_order' => $definition['sort_order'],
                'updated_at' => $now,
            ];

            if ($theme) {
                DB::table('jenis_themas')->where('id', $theme->id)->update($values);
                continue;
            }

            DB::table('jenis_themas')->insert(array_merge($values, [
                'slug' => $slug,
                'price' => '0',
                'preview' => '',
                'url_thema' => '/themes/'.$slug,
                'created_at' => $now,
            ]));
        }

        foreach (self::ACCESS as $code => $categorySlugs) {
            DB::table('paket_undangan_category_thema')
                ->where('paket_undangan_id', $packageIds[$code])
                ->delete();

            foreach ($categorySlugs as $categorySlug) {
                DB::table('paket_undangan_category_thema')->insert([
                    'paket_undangan_id' => $packageIds[$code],
                    'category_thema_id' => $categoryIds[$categorySlug],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $legacyNames = [
            'ruby' => 'Paket Silver',
            'sapphire' => 'Paket Gold',
            'diamond' => 'Paket Platinum',
            'trial' => 'Paket Trial',
        ];

        foreach ($legacyNames as $code => $name) {
            $package = DB::table('paket_undangans')->where('code', $code)->first();
            if (! $package) {
                continue;
            }

            DB::table('paket_undangan_category_thema')
                ->where('paket_undangan_id', $package->id)
                ->delete();
            DB::table('paket_undangans')->where('id', $package->id)->update([
                'code' => null,
                'jenis_paket' => $name,
                'name_paket' => $name,
                'updated_at' => now(),
            ]);
        }
    }

    private function matchesPackage(object $package, array $aliases): bool
    {
        $label = strtolower(trim(($package->name_paket ?? '').' '.($package->jenis_paket ?? '')));

        foreach ($aliases as $alias) {
            if (str_contains($label, $alias)) {
                return true;
            }
        }

        return false;
    }
};
