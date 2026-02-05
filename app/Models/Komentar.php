<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Komentar extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_id',
        'nama',
        'komentar',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }

    // Query Scopes
    public function scopeForInvitation($query, $invitationId)
    {
        return $query->where('invitation_id', $invitationId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeRecentByIp($query, $ipAddress, $hours = 1)
    {
        return $query->where('ip_address', $ipAddress)
                     ->where('created_at', '>=', now()->subHours($hours));
    }
}
