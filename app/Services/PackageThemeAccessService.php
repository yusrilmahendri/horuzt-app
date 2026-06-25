<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\CategoryThemas;
use App\Models\JenisThemas;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PackageThemeAccessService
{
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
        return $this->accessibleCategories($user)->modelKeys();
    }

    public function accessibleCategoryIdsForPackage(?PaketUndangan $package): array
    {
        return $this->accessibleCategoriesForPackage($package)->modelKeys();
    }

    public function canAccessTheme(User $user, JenisThemas $theme): bool
    {
        return $this->canPackageAccessTheme($this->packageForUser($user), $theme);
    }

    public function canPackageAccessTheme(?PaketUndangan $package, JenisThemas $theme): bool
    {
        if (! $package || ! $theme->is_active || ! $theme->category_id) {
            return false;
        }

        if ($this->usesLegacyThemeSelection($package)) {
            return false;
        }

        if (! $theme->relationLoaded('category')) {
            $theme->load('category');
        }

        if (! $theme->category || ! $theme->category->is_active || $theme->category->type !== 'website') {
            return false;
        }

        if ($this->packageHasCategoryPivot($package)) {
            return in_array($theme->category_id, $this->accessibleCategoryIdsForPackage($package), true);
        }

        if ((bool) $package->bebas_pilih_tema) {
            return true;
        }

        return (float) $theme->price <= 0;
    }

    private function baseCategoryQueryForPackage(PaketUndangan $package)
    {
        if ($this->usesLegacyThemeSelection($package)) {
            return CategoryThemas::query()
                ->whereRaw('1 = 0');
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
        return $package->accessibleCategories()->exists();
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
