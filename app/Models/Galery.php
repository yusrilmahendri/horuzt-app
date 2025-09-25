<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

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
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }
}
