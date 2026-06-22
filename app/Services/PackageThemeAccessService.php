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
        $invitation = Invitation::with('paketUndangan')
            ->where('user_id', $user->id)
            ->whereIn('payment_status', ['paid', 'pending'])
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        return $invitation?->paketUndangan;
    }

    public function packageFromCodeOrId(null|int|string $identifier): ?PaketUndangan
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return PaketUndangan::query()
            ->when(is_numeric($identifier), function ($query) use ($identifier) {
                $query->where('id', (int) $identifier);
            }, function ($query) use ($identifier) {
                $query->where('code', (string) $identifier);
            })
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

    private function usesLegacyThemeSelection(PaketUndangan $package): bool
    {
        return $package->code === 'trial';
    }
}
