<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pernikahan;
use App\Models\Mempelai;
use App\Models\Acara;
use App\Models\Pengunjung;
use App\Models\Qoute;

class ResultPernikahan extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function pernikahan(){
        return $this->belongsTo(Pernikahan::class, 'pernikahan_id');
    }
    
    public function mempelai(){
        return $this->hasOne(Mempelai::class, 'id');
    }
    
    public function acara(){
        return $this->hasOne(Acara::class, 'id');
    }

    public function pengunjung(){
        return $this->hasOne(Pengunjung::class, 'id');
    }

    public function qoute(){
        return $this->hasOne(Qoute::class, 'id');
    }
}
