<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class BukuTamu extends Model
{
    use HasFactory;

    protected $table = 'buku_tamus';

    protected $fillable = [
        'user_id',
        'nama',
        'email',
        'telepon',
        'ucapan',
        'status_kehadiran',
        'jumlah_tamu',
        'is_approved',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'jumlah_tamu' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_approved', false);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status_kehadiran', $status);
    }

    public function scopeHadir(Builder $query): Builder
    {
        return $query->where('status_kehadiran', 'hadir');
    }

    public function scopeTidakHadir(Builder $query): Builder
    {
        return $query->where('status_kehadiran', 'tidak_hadir');
    }

    public function scopeRagu(Builder $query): Builder
    {
        return $query->where('status_kehadiran', 'ragu');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('nama', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('ucapan', 'like', "%{$search}%");
        });
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }
}
