<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Themas;
use App\Models\Order;
use App\Models\Pernikahan;
use App\Models\Testimoni;
use App\Models\BukuTamu;
use App\Models\Cerita;
use App\Models\Qoute;
use App\Models\Galery;
use App\Models\Acara;
use App\Models\Ucapan;
use App\Models\Mempelai;
use App\Models\Setting;
use App\Models\FilterUndangan;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone'
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
        'password' => 'hashed',
    ];

    public function thema(){
        return $this->belongsToMany(Thema::class, 'result_themas');
    }

    public function order(){
        return $this->hasMany(Order::class, 'orders');
    }

    public function pernikahan(){
        return $this->hasOne(Pernikahan::class, 'user_id');
    }

    public function testimoni(){
        return $this->hasMany(Testimoni::class);
    }

    public function bukuTamu(){
        return $this->hasMany(BukuTamu::class);
    }

    public function cerita(){
        return $this->hasmany(Cerita::class);
    }

    public function qoute(){
        return $this->hasMany(Qoute::class);
    }

    public function gallery(){
        return $this->hasMany(Galery::class);
    }

    public function acara(){
        return $this->hasmany(Acara::class);
    }

    public function ucapan(){
        return $this->hasmany(Ucapan::class);
    }

    public function CountdownAcara(){
        return $this->hasMany(CountdownAcara::class);
    }

    public function mempelai(){
        return $this->hasMany(Mempelai::class);
    }

    public function setting(){
        return $this->hasMany(Setting::class);
    }

    public function filterUndangan(){
        return $this->hasMany(FilterUndangan::class);
    }
}