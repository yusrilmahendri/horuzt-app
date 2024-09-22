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
    protected $guarded = [];

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
