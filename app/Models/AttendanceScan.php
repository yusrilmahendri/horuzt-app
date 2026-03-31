<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'acara_id',
        'guest_name',
        'guest_identifier',
        'scan_type',
        'scanned_at',
        'scanned_by',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acara(): BelongsTo
    {
        return $this->belongsTo(Acara::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAcara($query, $acaraId)
    {
        return $query->where('acara_id', $acaraId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scanned_at', today());
    }

    public function scopeByScanType($query, $type)
    {
        return $query->where('scan_type', $type);
    }
}
