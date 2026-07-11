<?php

namespace App\Services;

use App\Models\Acara;
use Illuminate\Support\Facades\Schema;

class LocationResolverService
{
    /**
     * @return array<string,mixed>
     */
    public function resolveAcara(Acara $acara): array
    {
        $address = $this->firstFilled(
            $this->columnValue($acara, 'address'),
            $acara->alamat
        );
        $latitude = $this->numericColumnValue($acara, 'latitude');
        $longitude = $this->numericColumnValue($acara, 'longitude');
        $storedGoogleMapsUrl = $this->columnValue($acara, 'google_maps_url');
        $legacyLinkMaps = $acara->link_maps;
        $resolvedGoogleMapsUrl = $this->resolveMapsUrl($storedGoogleMapsUrl, $latitude, $longitude, $legacyLinkMaps);

        return [
            'alamat' => $address,
            'link_maps' => $resolvedGoogleMapsUrl,
            'address' => $address,
            'location_name' => $this->columnValue($acara, 'location_name'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'google_maps_url' => $resolvedGoogleMapsUrl,
            'place_id' => $this->columnValue($acara, 'place_id'),
            'legacy_link_maps' => $legacyLinkMaps,
        ];
    }

    public function resolveMapsUrl(?string $googleMapsUrl, mixed $latitude, mixed $longitude, ?string $legacyLinkMaps = null): ?string
    {
        if ($this->filled($googleMapsUrl)) {
            return $googleMapsUrl;
        }

        if ($latitude !== null && $longitude !== null) {
            return sprintf('https://www.google.com/maps?q=%s,%s', $latitude, $longitude);
        }

        return $this->filled($legacyLinkMaps) ? $legacyLinkMaps : null;
    }

    public function hasModernLocationSchema(): bool
    {
        return Schema::hasTable('acaras')
            && Schema::hasColumn('acaras', 'address')
            && Schema::hasColumn('acaras', 'location_name')
            && Schema::hasColumn('acaras', 'latitude')
            && Schema::hasColumn('acaras', 'longitude')
            && Schema::hasColumn('acaras', 'google_maps_url')
            && Schema::hasColumn('acaras', 'place_id');
    }

    private function columnValue(Acara $acara, string $column): ?string
    {
        if (! Schema::hasColumn('acaras', $column)) {
            return null;
        }

        $value = $acara->{$column};

        return $this->filled($value) ? (string) $value : null;
    }

    private function numericColumnValue(Acara $acara, string $column): float|int|null
    {
        if (! Schema::hasColumn('acaras', $column)) {
            return null;
        }

        $value = $acara->{$column};

        return is_numeric($value) ? $value + 0 : null;
    }

    private function firstFilled(...$values): ?string
    {
        foreach ($values as $value) {
            if ($this->filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
