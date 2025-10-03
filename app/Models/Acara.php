<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\CountdownAcara;

class Acara extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'countdown_id',
        'nama_acara',
        'jenis_acara',
        'tanggal_acara',
        'start_acara',
        'end_acara',
        'alamat',
        'link_maps'
    ];

    protected $casts = [
        'tanggal_acara' => 'date'
    ];

    // Event types enum values
    public const JENIS_AKAD = 'akad';
    public const JENIS_RESEPSI = 'resepsi';

    public const JENIS_ACARA_OPTIONS = [
        self::JENIS_AKAD => 'Akad Nikah',
        self::JENIS_RESEPSI => 'Resepsi'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function countDown()
    {
        return $this->belongsTo(CountdownAcara::class, 'countdown_id');
    }

    public function countdownAcara()
    {
        return $this->belongsTo(CountdownAcara::class, 'countdown_id');
    }

    // Scope for filtering by event type
    public function scopeAkad($query)
    {
        return $query->where('jenis_acara', self::JENIS_AKAD);
    }

    public function scopeResepsi($query)
    {
        return $query->where('jenis_acara', self::JENIS_RESEPSI);
    }
}
