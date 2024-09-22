<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\JenistThemas;

class Themas extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function jenisThema(){
        return $this->belongsToMany(JenisThemas::class, 'result_themas');
    }

    public function user(){
        return $this->belongsToMany(User::class, 'result_themas');
    }
}
