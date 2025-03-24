<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TransaksiRekening extends Model
{
    use HasFactory;
    protected $table = 'transaksi_rekening';

    protected $guarded = [];


    public function TransaksiRekening()
    {
        return $this->belongsTo(user::class);
    }

}
