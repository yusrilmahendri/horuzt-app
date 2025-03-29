<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Invitation;

class PaketUndangan extends Model
{
    use HasFactory;
    protected $guarded = [''];

    protected $table = 'paket_undangans';

    public function invitations() {
        return $this->hasMany(Invitation::class);
    }
}
