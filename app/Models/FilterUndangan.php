<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class FilterUndangan extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'halaman_sampul',
        'halaman_mempelai',
        'halaman_acara',
        'halaman_ucapan',
        'halaman_galery',
        'halaman_cerita',
        'halaman_lokasi',
        'halaman_prokes',
        'halaman_send_gift',
        'halaman_qoute',
    ];


    public function user(){
        return $this->belongsTo(User::class);
    }
}
