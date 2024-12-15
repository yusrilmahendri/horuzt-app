<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Bank;

class Rekening extends Model
{
    use HasFactory;
    protected $table = 'rekenings';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship to the Bank
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'kode_bank', 'id');
    }
}
