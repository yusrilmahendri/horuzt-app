<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\PaketUndangan;

class Invitation extends Model
{
    use HasFactory;
    protected $guarded = [''];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function paketUndangan() {
        return $this->belongsTo(PaketUndangan::class);
    }
}
