<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACCESS = [
        'ruby' => ['minimalis', 'floral'],
        'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
        'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('paket_undangan_category_thema')
            || ! Schema::hasColumn('paket_undangans', 'code')) {
            return;
        }

        $packageIds = DB::table('paket_undangans')
            ->whereIn('code', ['trial', 'ruby', 'sapphire', 'diamond'])
            ->pluck('id', 'code');

        $categoryIds = DB::table('category_themas')
            ->whereIn('slug', ['minimalis', 'floral', 'modern', 'elegant', 'luxury'])
            ->pluck('id', 'slug');

        if (isset($packageIds['trial'])) {
            DB::table('paket_undangan_category_thema')
                ->where('paket_undangan_id', $packageIds['trial'])
                ->delete();
        }

        foreach (self::ACCESS as $code => $slugs) {
            if (! isset($packageIds[$code])) {
                continue;
            }

            $targetIds = collect($slugs)
                ->map(fn ($slug) => $categoryIds[$slug] ?? null)
                ->filter()
                ->values();

            DB::table('paket_undangan_category_thema')
                ->where('paket_undangan_id', $packageIds[$code])
                ->whereNotIn('category_thema_id', $targetIds->all())
                ->delete();

            foreach ($targetIds as $categoryId) {
                DB::table('paket_undangan_category_thema')->updateOrInsert(
                    [
                        'paket_undangan_id' => $packageIds[$code],
                        'category_thema_id' => $categoryId,
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('paket_undangan_category_thema')
            || ! Schema::hasColumn('paket_undangans', 'code')) {
            return;
        }

        $trialId = DB::table('paket_undangans')->where('code', 'trial')->value('id');
        $minimalisId = DB::table('category_themas')->where('slug', 'minimalis')->value('id');

        if ($trialId && $minimalisId) {
            DB::table('paket_undangan_category_thema')->updateOrInsert(
                [
                    'paket_undangan_id' => $trialId,
                    'category_thema_id' => $minimalisId,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
};
