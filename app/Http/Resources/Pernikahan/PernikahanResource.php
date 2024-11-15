<?php

namespace App\Http\Resources\Pernikahan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;

class PernikahanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'nama_panggilan_pria' => $this->nama_panggilan_pria,
            'nama_panggilan_wanita' => $this->nama_panggilan_wanita,
            'nama_lengkap_pria' => $this->nama_lengkap_pria,
            'nama_lengkap_wanita' => $this->nama_lengkap_wanita,
            'gender_pria' => $this->gender_pria,
            'gender_wanita' => $this->gender_wanita,
            'alamat' => $this->alamat,
            'video' => $this->video,
            'photo_pria' => $this->photo_pria,
            'photo_wanita' => $this->photo_wanita,
            'tgl_cerita' => $this->tgl_cerita,
            'salam_pembuka' => $this->salam_pembuka,
            'salam_wa_atas' => $this->salam_wa_atas,
            'salam_wa_bawah' => $this->salam_wa_bawah,
        ];
    }
}
