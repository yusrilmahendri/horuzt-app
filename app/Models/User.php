<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'kode_pemesanan',
        'profile_photo',
        'verification_channel',
        'whatsapp_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'whatsapp_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isWhatsappVerified(): bool
    {
        return $this->whatsapp_verified_at !== null;
    }

    public function isAccountVerified(): bool
    {
        return $this->verification_channel === 'whatsapp'
            ? $this->isWhatsappVerified()
            : $this->isEmailVerified();
    }

    public function verificationTokens()
    {
        return $this->hasMany(AccountVerificationToken::class);
    }

    /**
     * Send the custom Sena Digital password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function order()
    {
        return $this->hasMany(Order::class, 'orders');
    }

    public function pernikahan()
    {
        return $this->hasOne(Pernikahan::class, 'user_id');
    }

    public function testimoni()
    {
        return $this->hasMany(Testimoni::class);
    }

    public function bukuTamu()
    {
        return $this->hasMany(BukuTamu::class);
    }

    public function cerita()
    {
        return $this->hasMany(Cerita::class);
    }

    public function qoute()
    {
        return $this->hasMany(Qoute::class);
    }

    public function gallery()
    {
        return $this->hasMany(Galery::class);
    }

    public function collage()
    {
        return $this->hasMany(Galery::class)
            ->where('photo_type', 'collage');
    }

    public function photos()
    {
        return $this->hasMany(Galery::class);
    }

    public function acara()
    {
        return $this->hasMany(Acara::class);
    }

    public function ucapan()
    {
        return $this->hasMany(Ucapan::class);
    }

    public function CountdownAcara()
    {
        return $this->hasMany(CountdownAcara::class);
    }

    public function rekening()
    {
        return $this->hasMany(Rekening::class);
    }

    public function rekenings()
    {
        return $this->hasMany(Rekening::class);
    }

    public function mempelai()
    {
        return $this->hasMany(Mempelai::class);
    }

    public function mempelaiOne()
    {
        return $this->hasOne(Mempelai::class, 'user_id');
    }

    public function setting()
    {
        return $this->hasMany(Setting::class);
    }

    public function settingOne()
    {
        return $this->hasOne(Setting::class, 'user_id');
    }

    public function filterUndangan()
    {
        return $this->hasMany(FilterUndangan::class);
    }

    public function filterUndanganOne()
    {
        return $this->hasOne(FilterUndangan::class, 'user_id');
    }

    public function invitation()
    {
        return $this->hasOne(Invitation::class);
    }

    /**
     * Get the primary/active invitation for this user
     * Prioritizes paid invitations, then falls back to latest by id
     * This ensures the correct invitation is used for wedding profile display
     */
    public function invitationOne()
    {
        return $this->hasOne(Invitation::class, 'user_id')
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhere('payment_status', 'pending');
            })
            ->orderByRaw("CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc');
    }

    public function thema()
    {
        return $this->belongsToMany(Themas::class, 'result_themas', 'user_id', 'thema_id');
    }

    public function selectedTheme()
    {
        return $this->hasOne(\App\Models\ResultThemas::class, 'user_id')
            ->with(['jenisThema.category'])
            ->latest('selected_at');
    }

    public function jenisThemas()
    {
        return $this->belongsToMany(\App\Models\JenisThemas::class, 'result_themas', 'user_id', 'jenis_id');
    }

    public function weddingGuests()
    {
        return $this->hasMany(WeddingGuest::class);
    }
}
