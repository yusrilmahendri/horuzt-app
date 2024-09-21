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

    public function user(){
        return $this->belongsToMany(User::class);
    }

    public function themas(){
        return $this->belongsToMany(Themas::class);
    }

    public function jenisThemas(){
        return $this->belongsToMany(JenisThemas::class);
    }
    
}
