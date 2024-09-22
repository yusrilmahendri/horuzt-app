<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;

class JenisThemas extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function category(){
        return $this->hasMany(Category::class);
    }

    public function themas(){
        return $this->belongsToMany(Themas::class, 'result_themas');
    }
}
