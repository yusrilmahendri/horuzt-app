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
        'user_id', 'photo', 'url_video', 'nama_foto', 'status'
    ];

    protected $appends = ['photo_url'];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the full URL for the photo
     */
    public function getPhotoUrlAttribute()
    {
        $cleanPath = $this->normalizeStoragePath($this->photo);

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
}
