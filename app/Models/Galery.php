<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Galery extends Model
{
    use HasFactory;
    protected $fillable = [
        'photo',
        'file_path',
        'file_url',
        'photo_type',
        'description',
        'position',
        'display_mode',
        'focal_point_x',
        'focal_point_y',
        'is_featured',
        'sort_order',
        'original_name',
        'original_size',
        'compressed_size',
        'mime_type',
        'quality',
        'url_video',
        'nama_foto',
        'status',
    ];

    protected $casts = [
        'focal_point_x' => 'float',
        'focal_point_y' => 'float',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'original_size' => 'integer',
        'compressed_size' => 'integer',
        'quality' => 'integer',
        'status' => 'boolean',
    ];

    protected $appends = ['photo_url'];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getObjectPositionAttribute(): string
    {
        if ($this->focal_point_x !== null && $this->focal_point_y !== null) {
            return $this->formatFocalPoint($this->focal_point_x) . '% ' . $this->formatFocalPoint($this->focal_point_y) . '%';
        }

        return match ($this->position) {
            'top' => 'center top',
            'bottom' => 'center bottom',
            'left' => 'left center',
            'right' => 'right center',
            'top-left' => 'left top',
            'top-right' => 'right top',
            'bottom-left' => 'left bottom',
            'bottom-right' => 'right bottom',
            default => 'center center',
        };
    }

    /**
     * Get the full URL for the photo
     */
    public function getPhotoUrlAttribute()
    {
        $cleanPath = $this->normalizeStoragePath($this->file_path ?: $this->photo);

        if (! $cleanPath) {
            return null;
        }

        if (! Storage::disk('public')->exists($cleanPath)) {
            Log::warning('[MissingImageFile]', [
                'original_path' => $this->photo,
                'clean_path' => $cleanPath,
            ]);

            return null;
        }

        return Storage::disk('public')->url($cleanPath);
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);

        $path = preg_replace('#^https?://[^/]+/storage/#', '', $path);
        $path = preg_replace('#^/storage/#', '', $path);
        $path = preg_replace('#^storage/#', '', $path);
        $path = ltrim($path, '/');

        return $path ?: null;
    }

    private function formatFocalPoint(float|int|string $value): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
