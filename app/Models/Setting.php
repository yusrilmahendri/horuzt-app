<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Setting extends Model
{
    use HasFactory;
     protected $fillable = [
        'user_id',
        'domain',
        'token',
        'musik',
        'music_track_id',
        'music_source_type',
        'external_music_track_id',
        'salam_pembuka',
        'salam_atas',
        'salam_bawah',
        'trial_masa_aktif',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function musicTrack()
    {
        return $this->belongsTo(MusicTrack::class, 'music_track_id');
    }

    public function externalMusicTrack()
    {
        return $this->belongsTo(ExternalMusicTrack::class, 'external_music_track_id');
    }
}
