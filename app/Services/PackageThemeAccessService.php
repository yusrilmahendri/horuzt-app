<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\CategoryThemas;
use App\Models\JenisThemas;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PackageThemeAccessService
{
    /**
     * Cumulative website category access by package tier.
     * Higher tiers inherit all categories from lower tiers.
     */
    private const CUMULATIVE_CATEGORY_ACCESS = [
        'trial' => [],
        'ruby' => ['minimalis', 'floral'],
        'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
        'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
    ];

    private const PACKAGE_TIER_RANK = [
        'trial' => 0,
        'ruby' => 1,
        'sapphire' => 2,
        'diamond' => 3,
    ];

    public function packageForUser(User $user): ?PaketUndangan
    {
        $invitations = Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->whereIn('payment_status', ['paid', 'confirmed'])
            ->where(function ($query) {
                $query->whereNull('domain_expires_at')
                    ->orWhere('domain_expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->get();

        foreach ($invitations as $invitation) {
            $package = $this->resolvePackageFromInvitation($invitation);

            if ($package) {
                return $package;
            }
        }

        return null;
    }

    public function pendingUpgradeForUser(User $user): ?Invitation
    {
        return Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->where('package_features_snapshot->invoice_type', 'package_upgrade')
            ->orderByDesc('id')
            ->first();
    }

    public function packageFromCodeOrId(null|int|string $identifier): ?PaketUndangan
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        if (! is_numeric($identifier)) {
            $normalizedCode = PaketUndangan::tierCode((string) $identifier) ?? strtolower(trim((string) $identifier));

            return PaketUndangan::query()
                ->where('code', $normalizedCode)
                ->orWhereRaw('LOWER(name_paket) = ?', [strtolower(trim((string) $identifier))])
                ->orWhereRaw('LOWER(jenis_paket) = ?', [strtolower(trim((string) $identifier))])
                ->first();
        }

        return PaketUndangan::query()
            ->where('id', (int) $identifier)
            ->first();
    }

    public function accessibleCategories(User $user, bool $withThemes = false): Collection
    {
        return $this->accessibleCategoriesForPackage($this->packageForUser($user), $withThemes);
    }

    public function accessibleCategoriesForPackage(?PaketUndangan $package, bool $withThemes = false): Collection
    {
        if (! $package) {
            return new Collection();
        }

        $query = $this->baseCategoryQueryForPackage($package);

        if ($withThemes) {
            $query->with(['jenisThemas' => function ($themeQuery) {
                $themeQuery->active()->ordered();
            }]);
        }

        return $query->get();
    }

    public function accessibleCategoryIds(User $user): array
    {
        return array_map('intval', $this->accessibleCategories($user)->modelKeys());
    }

    public function accessibleCategoryIdsForPackage(?PaketUndangan $package): array
    {
        return array_map('intval', $this->accessibleCategoriesForPackage($package)->modelKeys());
    }

    public function canAccessTheme(User $user, JenisThemas $theme): bool
    {
        return $this->canPackageAccessTheme($this->packageForUser($user), $theme);
    }

    public function canPackageAccessTheme(?PaketUndangan $package, ?JenisThemas $theme): bool
    {
        $packageId = (int) ($package?->id ?? 0);
        $themeCategoryId = (int) ($theme?->category_id ?? 0);
        $pivotExists = false;
        $reason = null;

        if (! $package || ! $theme) {
            $reason = 'missing_package_or_theme';

            return $this->logThemeAccessDecision($package, $theme, $themeCategoryId, $pivotExists, false, $reason);
        }

        if (! $theme->is_active) {
            $reason = 'theme_inactive';

            return $this->logThemeAccessDecision($package, $theme, $themeCategoryId, $pivotExists, false, $reason);
        }

        if ($themeCategoryId <= 0) {
            $reason = 'missing_theme_category_id';

            return $this->logThemeAccessDecision($package, $theme, $themeCategoryId, $pivotExists, false, $reason);
        }

        if (! $theme->relationLoaded('category') || $this->themeCategoryNeedsReload($theme)) {
            $theme->load('category');
        }

        if (! $theme->category) {
            $reason = 'missing_theme_category';

            return $this->logThemeAccessDecision($package, $theme, $themeCategoryId, $pivotExists, false, $reason);
        }

        if (! $theme->category->is_active) {
            $reason = 'inactive_theme_category';

            return $this->logThemeAccessDecision($package, $theme, $themeCategoryId, $pivotExists, false, $reason);
        }

        if ($theme->category->type !== 'website') {
            $reason = 'non_website_theme_category';

            return $this->logThemeAccessDecision($package, $theme, $themeCategoryId, $pivotExists, false, $reason);
        }

        if ($this->resolvePackageTier($package) === 'trial') {
            $trialTheme = $this->trialThemeForPackage($package);
            $allowed = $trialTheme && (int) $trialTheme->id === (int) $theme->id;

            return $this->logThemeAccessDecision(
                $package,
                $theme,
                $themeCategoryId,
                $pivotExists,
                $allowed,
                $allowed ? 'trial_theme_match' : 'trial_theme_only'
            );
        }

        // Query the pivot table directly so access checks stay correct even if
        // PDO/Eloquent hydrate ids as strings and strict comparisons would fail.
        $pivotExists = $this->packageHasThemeCategoryPivot($packageId, $themeCategoryId);
        $resolvedTier = $this->resolvePackageTier($package);
        $categorySlug = (string) ($theme->category->slug ?? '');
        $cumulativeAllowed = $this->categoryAllowedByCumulativeMapping($package, $categorySlug);
        $allowed = $pivotExists || $cumulativeAllowed;

        $this->logCumulativeThemeAccess(
            $package,
            $resolvedTier,
            $theme,
            $categorySlug,
            $allowed
        );

        return $this->logThemeAccessDecision(
            $package,
            $theme,
            $themeCategoryId,
            $pivotExists,
            $allowed,
            $allowed
                ? ($pivotExists ? 'pivot_match' : 'cumulative_match')
                : 'access_denied'
        );
    }

    public function resolvePackageTier(?PaketUndangan $package): ?string
    {
        if (! $package) {
            return null;
        }

        $candidates = [
            $package->code ?? null,
            $package->package_tier ?? null,
            PaketUndangan::tierCode($package->jenis_paket ?? null, $package->code ?? null),
            PaketUndangan::tierCode($package->name_paket ?? null, $package->code ?? null),
        ];

        foreach ($candidates as $candidate) {
            $tier = PaketUndangan::tierCode(is_string($candidate) ? $candidate : null);

            if (in_array($tier, ['trial', 'ruby', 'sapphire', 'diamond'], true)) {
                return $tier;
            }
        }

        return null;
    }

    public function packageRank(?PaketUndangan $package): int
    {
        $tier = $this->resolvePackageTier($package);

        return self::PACKAGE_TIER_RANK[$tier] ?? -1;
    }

    public function isHigherPackage(?PaketUndangan $target, ?PaketUndangan $current): bool
    {
        return $this->packageRank($target) > $this->packageRank($current);
    }

    public function minimumPackageForTheme(?JenisThemas $theme): ?PaketUndangan
    {
        if (! $theme) {
            return null;
        }

        if (! $theme->relationLoaded('category')) {
            $theme->load('category');
        }

        return PaketUndangan::query()
            ->whereNotNull('code')
            ->get()
            ->sortBy(fn (PaketUndangan $package) => $this->packageRank($package))
            ->first(fn (PaketUndangan $package) => $this->canPackageAccessTheme($package, $theme));
    }

    public function cumulativeCategorySlugsForPackage(?PaketUndangan $package): array
    {
        $tier = $this->resolvePackageTier($package);

        if ($tier === null) {
            return [];
        }

        return self::CUMULATIVE_CATEGORY_ACCESS[$tier] ?? [];
    }

    public function rubyCategorySlugs(): array
    {
        return self::CUMULATIVE_CATEGORY_ACCESS['ruby'];
    }

    public function categoryAllowedByCumulativeMapping(?PaketUndangan $package, ?string $categorySlug): bool
    {
        $normalizedSlug = strtolower(trim((string) $categorySlug));

        if ($normalizedSlug === '') {
            return false;
        }

        return in_array($normalizedSlug, $this->cumulativeCategorySlugsForPackage($package), true);
    }

    public function packageUsesTierThemeCatalog(?PaketUndangan $package): bool
    {
        if (! $package) {
            return false;
        }

        return in_array($this->resolvePackageTier($package), ['ruby', 'sapphire', 'diamond'], true);
    }

    public function trialThemeForPackage(?PaketUndangan $package): ?JenisThemas
    {
        if (! $package || $this->resolvePackageTier($package) !== 'trial') {
            return null;
        }

        $categoryIds = $this->configuredCategoryIdsForPackage($package);

        if (count($categoryIds) !== 1) {
            return null;
        }

        $category = CategoryThemas::query()
            ->active()
            ->website()
            ->whereKey($categoryIds[0])
            ->whereIn('slug', $this->rubyCategorySlugs())
            ->first();

        if (! $category) {
            return null;
        }

        return JenisThemas::query()
            ->with('category')
            ->active()
            ->where('category_id', $category->id)
            ->ordered()
            ->orderBy('id')
            ->first();
    }

    public function accessibleThemesForPackage(?PaketUndangan $package): Collection
    {
        if (! $package) {
            return new Collection();
        }

        if ($this->resolvePackageTier($package) === 'trial') {
            $trialTheme = $this->trialThemeForPackage($package);

            return $trialTheme ? new Collection([$trialTheme]) : new Collection();
        }

        $categoryIds = $this->accessibleCategoryIdsForPackage($package);

        if ($categoryIds === []) {
            return new Collection();
        }

        return JenisThemas::with('category')
            ->active()
            ->whereHas('category', fn ($query) => $query->active()->website())
            ->whereIn('category_id', $categoryIds)
            ->ordered()
            ->get();
    }

    public function packagePayload(PaketUndangan $package): array
    {
        $tier = $this->resolvePackageTier($package);
        $themes = $this->accessibleThemesForPackage($package);
        $isTrial = $tier === 'trial';

        return [
            'id' => $package->id,
            'code' => $tier,
            'name' => PaketUndangan::displayLabelFromCode($tier, $package->getRawOriginal('name_paket') ?? $package->name_paket),
            'display_label' => PaketUndangan::shortNameFromCode($tier, $package->getRawOriginal('name_paket') ?? $package->name_paket),
            'price' => number_format((float) $package->price, 2, '.', ''),
            'active_days' => (int) $package->masa_aktif,
            'is_active' => $this->packageIsActive($package),
            'features' => [
                'guest_book' => true,
                'whatsapp_share' => (bool) $package->kirim_wa,
                'gift' => (bool) $package->kirim_hadiah,
                'guest_import' => (bool) $package->import_data,
                'custom_music' => $tier === 'diamond',
                'free_theme_choice' => ! $isTrial && (bool) $package->bebas_pilih_tema,
                'google_maps' => true,
                'religion_customization' => true,
                'gallery_video' => true,
                'active_days' => (int) $package->masa_aktif,
            ],
            'theme_access' => [
                'can_choose_theme' => ! $isTrial,
                'accessible_theme_count' => $themes->count(),
                'configuration_complete' => ! $isTrial || $themes->count() === 1,
                'accessible_themes' => $themes
                    ->map(fn (JenisThemas $theme) => $this->themeSummaryPayload($theme))
                    ->values()
                    ->all(),
            ],
            'jenis_paket' => PaketUndangan::jenisPaketFromCode($tier, $package->getRawOriginal('jenis_paket') ?? $package->jenis_paket),
            'name_paket' => PaketUndangan::displayLabelFromCode($tier, $package->getRawOriginal('name_paket') ?? $package->name_paket),
            'masa_aktif' => (int) $package->masa_aktif,
            'halaman_buku' => $package->halaman_buku,
            'kirim_wa' => (bool) $package->kirim_wa,
            'kirim_hadiah' => (bool) $package->kirim_hadiah,
            'import_data' => (bool) $package->import_data,
            'bebas_pilih_tema' => $isTrial ? false : (bool) $package->bebas_pilih_tema,
        ];
    }

    public function packageSummaryPayload(?PaketUndangan $package): ?array
    {
        if (! $package) {
            return null;
        }

        $tier = $this->resolvePackageTier($package) ?? $package->code;

        return [
            'id' => $package->id,
            'code' => $tier,
            'name' => PaketUndangan::displayLabelFromCode($tier, $package->name_paket),
        ];
    }

    public function themeAccessPayload(JenisThemas $theme, ?PaketUndangan $package, ?int $selectedThemeId = null): array
    {
        if (! $theme->relationLoaded('category')) {
            $theme->load('category');
        }

        $targetPackage = $this->minimumPackageForTheme($theme);
        $canUse = $package
            ? $this->canPackageAccessTheme($package, $theme)
            : false;

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'category' => $theme->category ? [
                'id' => $theme->category->id,
                'name' => $theme->category->name,
                'slug' => $theme->category->slug,
                'type' => $theme->category->type,
            ] : null,
            'package_required' => $this->packageSummaryPayload($targetPackage),
            'can_preview' => true,
            'can_use' => $canUse,
            'is_current_theme' => $selectedThemeId !== null && (int) $selectedThemeId === (int) $theme->id,
            'upgrade_required' => $package !== null && ! $canUse,
            'locked' => ! $canUse,
            'target_package' => $canUse ? null : $this->packageSummaryPayload($targetPackage),
            'price' => $theme->price,
            'preview' => $theme->preview,
            'preview_image' => $theme->preview_image,
            'thumbnail_image' => $theme->thumbnail_image,
            'image' => $theme->image,
            'demo_url' => $theme->demo_url,
            'url_thema' => $theme->url_thema,
            'features' => $theme->features,
            'description' => $theme->description,
            'sort_order' => $theme->sort_order,
        ];
    }

    public function packageCollectionPayload(iterable $packages): array
    {
        return collect($packages)
            ->sortBy(fn (PaketUndangan $package) => $this->packageRank($package))
            ->map(fn (PaketUndangan $package) => $this->packagePayload($package))
            ->values()
            ->all();
    }

    private function baseCategoryQueryForPackage(PaketUndangan $package)
    {
        if ($this->resolvePackageTier($package) === 'trial') {
            $trialTheme = $this->trialThemeForPackage($package);

            return $trialTheme
                ? CategoryThemas::query()->whereKey($trialTheme->category_id)->active()->website()->ordered()
                : CategoryThemas::query()->whereRaw('1 = 0');
        }

        $cumulativeSlugs = $this->cumulativeCategorySlugsForPackage($package);

        if ($cumulativeSlugs !== []) {
            return CategoryThemas::query()
                ->active()
                ->website()
                ->ordered()
                ->whereIn('slug', $cumulativeSlugs);
        }

        if ($this->packageHasCategoryPivot($package)) {
            return $package->accessibleCategories()
                ->active()
                ->website()
                ->ordered();
        }

        $query = CategoryThemas::query()
            ->active()
            ->website()
            ->ordered();

        if ((bool) $package->bebas_pilih_tema) {
            return $query;
        }

        return $query->whereHas('jenisThemas', function ($themeQuery) {
            $themeQuery->active()->where('price', '<=', 0);
        });
    }

    private function packageHasCategoryPivot(PaketUndangan $package): bool
    {
        return DB::table('paket_undangan_category_thema')
            ->where('paket_undangan_id', (int) $package->id)
            ->exists();
    }

    private function configuredCategoryIdsForPackage(PaketUndangan $package): array
    {
        return DB::table('paket_undangan_category_thema')
            ->where('paket_undangan_id', (int) $package->id)
            ->pluck('category_thema_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function packageIsActive(PaketUndangan $package): bool
    {
        return Schema::hasColumn('paket_undangans', 'is_active')
            ? (bool) $package->getAttribute('is_active')
            : true;
    }

    private function themeSummaryPayload(JenisThemas $theme): array
    {
        if (! $theme->relationLoaded('category')) {
            $theme->load('category');
        }

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'category_id' => $theme->category_id,
            'category_slug' => $theme->category?->slug,
            'category_name' => $theme->category?->name,
            'is_active' => (bool) $theme->is_active,
        ];
    }

    private function packageHasThemeCategoryPivot(int $packageId, int $categoryId): bool
    {
        if ($packageId <= 0 || $categoryId <= 0) {
            return false;
        }

        return DB::table('paket_undangan_category_thema')
            ->where('paket_undangan_id', $packageId)
            ->where('category_thema_id', $categoryId)
            ->exists();
    }

    private function themeCategoryNeedsReload(JenisThemas $theme): bool
    {
        if (! $theme->category) {
            return false;
        }

        $attributes = $theme->category->getAttributes();

        return ! array_key_exists('is_active', $attributes)
            || ! array_key_exists('type', $attributes)
            || ! array_key_exists('slug', $attributes);
    }

    private function logThemeAccessDecision(
        ?PaketUndangan $package,
        ?JenisThemas $theme,
        int $themeCategoryId,
        bool $pivotExists,
        bool $result,
        string $reason
    ): bool {
        Log::info('Package theme access check', [
            'package_id' => (int) ($package?->id ?? 0),
            'package_code' => $package?->code,
            'theme_id' => (int) ($theme?->id ?? 0),
            'theme_slug' => $theme?->slug,
            'theme_category_id' => $themeCategoryId,
            'theme_category_slug' => $theme?->category?->slug,
            'pivot_exists' => $pivotExists,
            'result' => $result,
            'reason' => $reason,
        ]);

        return $result;
    }

    private function logCumulativeThemeAccess(
        ?PaketUndangan $package,
        ?string $resolvedTier,
        ?JenisThemas $theme,
        string $categorySlug,
        bool $allowed
    ): void {
        Log::info('[PackageThemeAccessCumulative]', [
            'package_id' => (int) ($package?->id ?? 0),
            'package_code' => $package?->code,
            'resolved_tier' => $resolvedTier,
            'theme_slug' => $theme?->slug,
            'category_slug' => $categorySlug,
            'allowed' => $allowed,
        ]);
    }

    private function resolvePackageFromInvitation(Invitation $invitation): ?PaketUndangan
    {
        $packageCodeHint = $this->resolvePackageCodeHint($invitation);

        foreach ($invitation->packageIdentifierHints() as $packageId) {
            $package = ($invitation->relationLoaded('paketUndangan') && (int) $invitation->paketUndangan?->id === $packageId)
                ? $invitation->paketUndangan
                : PaketUndangan::find($packageId);

            if (! $package) {
                continue;
            }

            $resolvedCode = $package->package_tier
                ?: $package->code
                ?: PaketUndangan::tierCode($package->name_paket ?? $package->jenis_paket ?? null);

            if ($packageCodeHint && $resolvedCode && $packageCodeHint !== $resolvedCode) {
                $hintPackage = $this->packageFromCodeOrId($packageCodeHint);

                if ($hintPackage) {
                    return $hintPackage;
                }
            }

            return $package;
        }

        return $packageCodeHint
            ? $this->packageFromCodeOrId($packageCodeHint)
            : null;
    }

    private function resolvePackageCodeHint(Invitation $invitation): ?string
    {
        foreach ($invitation->packageNameHints() as $name) {
            $code = PaketUndangan::tierCode($name);

            if (in_array($code, ['trial', 'ruby', 'sapphire', 'diamond'], true)) {
                return $code;
            }
        }

        return null;
    }

}
