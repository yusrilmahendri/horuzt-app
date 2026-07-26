<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReligionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'religion_key',
        'religion_name',
        'opening_greeting',
        'closing_greeting',
        'invitation_intro',
        'whatsapp_message',
        'quote_text',
        'quote_source',
        'prayer_text',
        'blessing_text',
        'active',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'version' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
