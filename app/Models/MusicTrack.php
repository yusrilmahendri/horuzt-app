<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MusicTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'slug',
        'file_path',
        'duration_seconds',
        'mime_type',
        'file_size',
        'description',
        'is_active',
        'is_default',
        'sort_order',
        'source',
        'external_id',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'duration_seconds' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = ['url'];

    /**
     * Settings (users) currently pointing at this catalog track.
     */
    public function settings()
    {
        return $this->hasMany(Setting::class, 'music_track_id');
    }

    /**
     * Public asset URL for the catalog file.
     * Normalizes the stored path so it never becomes /storage/public/...
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        $publicPath = preg_replace('#^public/#', '', $this->file_path);

        return asset('storage/' . $publicPath);
    }
}
