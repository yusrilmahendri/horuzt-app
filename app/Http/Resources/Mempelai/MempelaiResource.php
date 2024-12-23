<?php

namespace App\Http\Resources\Mempelai;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;

class MempelaiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->user),
            'cover_photo' => $this->cover_photo,
            'urutan_mempelai' => $this->urutan_mempelai,
            'photo_pria' => $this->photo_pria,
            'photo_wanita' => $this->photo_wanita,
            'name_lengkap_pria' => $this->name_lengkap_pria,
            'name_lengkap_wanita' => $this->name_lengkap_wanita,
            'name_panggilan_pria' => $this->name_panggilan_pria,
            'name_panggilan_wanita' => $this->name_panggilan_wanita,
            'ayah_pria' => $this->ayah_pria,
            'ayah_wanita' => $this->ayah_wanita,
            'ibu_pria' => $this->ibu_pria,
            'ibu_wanita' => $this->ibu_wanita,
        ];
    }
}
