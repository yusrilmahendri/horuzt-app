<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountVerificationToken extends Model
{
    protected $fillable = [
        'user_id', 'channel', 'purpose', 'token_hash', 'expires_at',
        'used_at', 'attempts', 'sent_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
