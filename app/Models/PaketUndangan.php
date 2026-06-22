<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Invitation;
use App\Models\CategoryThemas;

class PaketUndangan extends Model
{
    use HasFactory;
    protected $guarded = [''];

    protected $table = 'paket_undangans';

    /**
     * Expose the rebranded package name to API responses without
     * touching the stored value (no migration / schema change).
     * These are display-only fields; backward-compatible fields like
     * name_paket / jenis_paket stay intact.
     */
    protected $appends = [
        'name_paket_original',
        'name_paket_display',
        'package_tier',
        'display_label',
    ];

    /**
     * Map any legacy/raw package label to the current brand name.
     *
     * Mapping is tier-based and case-insensitive so it stays compatible
     * with old data still living in the database:
     *   - Silver / Standart / Ruby    => Ruby     (paket standar)
     *   - Gold   / Sapphire           => Sapphire (paket premium)
     *   - Platinum / Diamond          => Diamond  (paket eksklusif)
     *   - Trial                       => Trial
     * Unknown labels are returned as-is to avoid mangling future packages.
     */
    public static function canonicalName(?string $rawName): ?string
    {
        if ($rawName === null || trim($rawName) === '') {
            return $rawName;
        }

        $name = strtolower($rawName);

        return match (true) {
            str_contains($name, 'trial') => 'Trial',
            str_contains($name, 'diamond'), str_contains($name, 'platinum') => 'Diamond',
            str_contains($name, 'sapphire'), str_contains($name, 'gold') => 'Sapphire',
            str_contains($name, 'ruby'),
            str_contains($name, 'silver'),
            str_contains($name, 'standart'),
            str_contains($name, 'standar') => 'Ruby',
            default => $rawName,
        };
    }

    /**
     * Stable lowercase tier code derived from the package name.
     * Useful for legacy records that have not been backfilled yet.
     */
    public static function tierCode(?string $rawName): ?string
    {
        $canonical = self::canonicalName($rawName);

        if ($canonical === null || trim($canonical) === '') {
            return null;
        }

        if (in_array($canonical, ['Ruby', 'Sapphire', 'Diamond', 'Trial'], true)) {
            return strtolower($canonical);
        }

        return strtolower(preg_replace('/\s+/', '-', trim($canonical)));
    }

    public function getNamePaketOriginalAttribute(): ?string
    {
        return $this->name_paket ?? null;
    }

    public function getNamePaketDisplayAttribute(): ?string
    {
        return self::canonicalName($this->name_paket ?? null);
    }

    public function getPackageTierAttribute(): ?string
    {
        return $this->code ?: self::tierCode($this->name_paket ?? null);
    }

    public function getDisplayLabelAttribute(): ?string
    {
        $raw = $this->name_paket ?? null;
        $canonical = self::canonicalName($raw);

        if (in_array($canonical, ['Ruby', 'Sapphire', 'Diamond', 'Trial'], true)) {
            return 'Paket ' . $canonical;
        }

        return $raw;
    }

    public function invitations() {
        return $this->hasMany(Invitation::class);
    }

    public function accessibleCategories()
    {
        return $this->belongsToMany(
            CategoryThemas::class,
            'paket_undangan_category_thema',
            'paket_undangan_id',
            'category_thema_id'
        )->withTimestamps();
    }
}
