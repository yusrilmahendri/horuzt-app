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
            'nama_panggilan_pria' => fake()->name(),
            'nama_panggilan_wanita' => fake()->name(),
            'nama_lengkap_pria' => fake()->name(),
            'nama_lengkap_wanita' => fake()->name(),
            'gender_pria' => fake()->name(),
            'gender_wanita' => fake()->name(),
            'alamat' => fake()->name(),
            'video' => fake()->name(),
            'photo_pria' => fake()->name(),
            'photo_wanita' => fake()->name(),
            'tgl_cerita' => fake()->name(),
            'salam_pembuka' => fake()->name(),
            'salam_wa_atas' => fake()->name(),
            'salam_wa_bawah' => fake()->name(),
        ];
    }
}
