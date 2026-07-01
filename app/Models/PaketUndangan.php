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
     * Source-of-truth package map based on stable code values.
     */
    public const PACKAGE_MAP = [
        'trial' => [
            'name' => 'Trial',
            'label' => 'Paket Trial',
        ],
        'ruby' => [
            'name' => 'Ruby',
            'label' => 'Paket Ruby',
        ],
        'sapphire' => [
            'name' => 'Sapphire',
            'label' => 'Paket Sapphire',
        ],
        'diamond' => [
            'name' => 'Diamond',
            'label' => 'Paket Diamond',
        ],
    ];

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
    public static function canonicalName(?string $rawName, ?string $code = null): ?string
    {
        $normalizedCode = self::normalizeCode($code);
        if ($normalizedCode !== null) {
            return self::PACKAGE_MAP[$normalizedCode]['name'];
        }

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
    public static function tierCode(?string $rawName, ?string $code = null): ?string
    {
        $normalizedCode = self::normalizeCode($code);
        if ($normalizedCode !== null) {
            return $normalizedCode;
        }

        $canonical = self::canonicalName($rawName);

        if ($canonical === null || trim($canonical) === '') {
            return null;
        }

        if (in_array($canonical, ['Ruby', 'Sapphire', 'Diamond', 'Trial'], true)) {
            return strtolower($canonical);
        }

        return strtolower(preg_replace('/\s+/', '-', trim($canonical)));
    }

    public static function displayLabelFromCode(?string $code, ?string $fallbackRaw = null): ?string
    {
        $normalizedCode = self::normalizeCode($code);
        if ($normalizedCode !== null) {
            return self::PACKAGE_MAP[$normalizedCode]['label'];
        }

        if ($fallbackRaw === null) {
            return null;
        }

        $canonical = self::canonicalName($fallbackRaw);
        if (in_array($canonical, ['Ruby', 'Sapphire', 'Diamond', 'Trial'], true)) {
            return 'Paket ' . $canonical;
        }

        return $fallbackRaw;
    }

    public static function shortNameFromCode(?string $code, ?string $fallbackRaw = null): ?string
    {
        $normalizedCode = self::normalizeCode($code);
        if ($normalizedCode !== null) {
            return self::PACKAGE_MAP[$normalizedCode]['name'];
        }

        return self::canonicalName($fallbackRaw);
    }

    public static function jenisPaketFromCode(?string $code, ?string $fallbackRaw = null): ?string
    {
        return self::displayLabelFromCode($code, $fallbackRaw);
    }

    private static function normalizeCode(?string $code): ?string
    {
        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        $normalized = strtolower(trim($code));

        return array_key_exists($normalized, self::PACKAGE_MAP) ? $normalized : null;
    }

    public function getNamePaketOriginalAttribute(): ?string
    {
        return $this->getRawOriginal('name_paket');
    }

    public function getJenisPaketAttribute($value): ?string
    {
        return self::jenisPaketFromCode($this->code, $value);
    }

    public function getNamePaketAttribute($value): ?string
    {
        return self::displayLabelFromCode($this->code, $value);
    }

    public function getNameAttribute(): ?string
    {
        return $this->name_paket ?? $this->jenis_paket ?? null;
    }

    public function getNamePaketDisplayAttribute(): ?string
    {
        return self::shortNameFromCode($this->code, $this->name_paket ?? null);
    }

    public function getPackageTierAttribute(): ?string
    {
        return self::tierCode($this->name_paket ?? null, $this->code);
    }

    public function getDisplayLabelAttribute(): ?string
    {
        return self::displayLabelFromCode($this->code, $this->name_paket ?? null);
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
