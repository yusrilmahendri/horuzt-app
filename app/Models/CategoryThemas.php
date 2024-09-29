<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\JenisThemas;

class CategoryThemas extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function jenisThemas(){
        return $this->hasMany(JenisThemas::class);
    }
}
