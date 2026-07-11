<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalMusicTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'album',
        'provider',
        'external_id',
        'provider_track_id',
        'stream_url',
        'preview_url',
        'thumbnail_url',
        'license_name',
        'license_url',
        'duration_seconds',
        'mime_type',
        'file_size',
        'is_active',
        'fetched_at',
        'sort_order',
        'raw_payload',
        'payload',
        'last_synced_at',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'fetched_at' => 'datetime',
        'sort_order' => 'integer',
        'raw_payload' => 'array',
        'payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function settings()
    {
        return $this->hasMany(Setting::class, 'external_music_track_id');
    }
}
