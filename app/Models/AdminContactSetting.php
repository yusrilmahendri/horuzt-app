<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminContactSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_email',
        'email',
        'whatsapp',
        'email_password',
        'whatsapp_token',
        'whatsapp_message',
    ];

    protected $hidden = [
        'email_password',
        'whatsapp_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toUserArray(): array
    {
        return [
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'whatsapp_message' => $this->whatsapp_message,
        ];
    }
}
