<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\PaketUndangan;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'paket_undangan_id',
        'status',
        'order_id',
        'midtrans_transaction_id',
        'payment_status',
        'domain_expires_at',
        'payment_confirmed_at',
        'package_price_snapshot',
        'package_duration_snapshot',
        'package_features_snapshot'
    ];

    protected $casts = [
        'domain_expires_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'package_features_snapshot' => 'array',
        'package_price_snapshot' => 'decimal:2',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paketUndangan() {
        return $this->belongsTo(PaketUndangan::class);
    }

    public function paymentLogs() {
        return $this->hasMany(\App\Models\PaymentLog::class);
    }

    public function komentars()
    {
        return $this->hasMany(Komentar::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if domain is still active
     */
    public function isDomainActive(): bool
    {
        if (!$this->domain_expires_at || $this->payment_status !== 'paid') {
            return false;
        }

        return $this->domain_expires_at->isFuture();
    }

    /**
     * Get days until domain expires
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->domain_expires_at) {
            return null;
        }

        return now()->diffInDays($this->domain_expires_at, false);
    }

    /**
     * Scope for active domains
     */
    public function scopeActiveDomains($query)
    {
        return $query->where('payment_status', 'paid')
                    ->where('domain_expires_at', '>', now());
    }

    /**
     * Scope for expired domains
     */
    public function scopeExpiredDomains($query)
    {
        return $query->where('payment_status', 'paid')
                    ->where('domain_expires_at', '<=', now());
    }
}
