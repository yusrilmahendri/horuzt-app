<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_token',
        'domain',
        'first_visit_at',
        'ip_address',
        'user_agent',
        'attended',
        'attended_at',
        'attended_acara_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'first_visit_at' => 'datetime',
        'attended_at' => 'datetime',
        'attended' => 'boolean',
    ];

    /**
     * Wedding owner relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Event attended relationship
     */
    public function acara(): BelongsTo
    {
        return $this->belongsTo(Acara::class, 'attended_acara_id');
    }

    /**
     * Generate unique token for guest
     */
    public static function generateUniqueToken(string $guestName, string $domain): string
    {
        $data = $guestName . $domain . now()->timestamp . random_bytes(16);
        return hash('sha256', $data);
    }

    /**
     * Mark guest as attended
     */
    public function markAsAttended(int $acaraId): bool
    {
        $this->attended = true;
        $this->attended_at = now();
        $this->attended_acara_id = $acaraId;
        return $this->save();
    }

    /**
     * Scope for attended guests
     */
    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }

    /**
     * Scope for pending guests
     */
    public function scopePending($query)
    {
        return $query->where('attended', false);
    }
}
