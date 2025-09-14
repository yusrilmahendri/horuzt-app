<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Themas;
use App\Models\JenisThemas;

class ResultThemas extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'jenis_id',
        'thema_id',
        'selected_at'
    ];

    protected $casts = [
        'selected_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thema()
    {
        return $this->belongsTo(Themas::class, 'thema_id');
    }

    public function jenisThema()
    {
        return $this->belongsTo(JenisThemas::class, 'jenis_id');
    }
}
