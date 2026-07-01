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

    public function packageForUser(User $user): ?PaketUndangan
    {
        $invitations = Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->whereIn('payment_status', ['paid', 'pending'])
            ->orderByDesc('id')
            ->get();

        $trialFallback = null;

        foreach ($invitations as $invitation) {
            $package = $this->resolvePackageFromInvitation($invitation);

            if (! $package) {
                continue;
            }

            if (! $this->usesLegacyThemeSelection($package)) {
                return $package;
            }

            $trialFallback ??= $package;
        }

        return $trialFallback;
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

        if ($this->usesLegacyThemeSelection($package)) {
            $reason = 'legacy_trial_package';

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

    public function cumulativeCategorySlugsForPackage(?PaketUndangan $package): array
    {
        $tier = $this->resolvePackageTier($package);

        if ($tier === null) {
            return [];
        }

        return self::CUMULATIVE_CATEGORY_ACCESS[$tier] ?? [];
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
        if (! $package || $this->usesLegacyThemeSelection($package)) {
            return false;
        }

        return in_array($this->resolvePackageTier($package), ['ruby', 'sapphire', 'diamond'], true);
    }

    private function baseCategoryQueryForPackage(PaketUndangan $package)
    {
        if ($this->usesLegacyThemeSelection($package)) {
            return CategoryThemas::query()
                ->whereRaw('1 = 0');
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

    private function usesLegacyThemeSelection(PaketUndangan $package): bool
    {
        return ($package->code ?: $package->package_tier) === 'trial';
    }
}
