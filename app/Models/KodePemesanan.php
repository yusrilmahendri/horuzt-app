<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class KodePemesanan extends Model
{
    use HasFactory;
    protected $table = 'kode_pemesanan';

    protected $guarded = [];


    public function KodePemesanan()
    {
        return $this->belongsTo(user::class);
    }

    protected $fillable = [
        'id_user',
        'nama',
        'kode_pemesanan',
        'keterangan'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

}
