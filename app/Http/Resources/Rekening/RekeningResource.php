<?php

namespace App\Http\Resources\Rekening;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RekeningResource extends JsonResource
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
            'kode_bank' => $this->kode_bank,
            'nomor_rekening' => $this->nomor_rekening,
            'nama_bank' => $this->nama_bank,
            'nama_pemilik' => $this->nama_pemilik,
            'photo_rek' => $this->photo_url,
            'bank_info' => $this->whenLoaded('bank', function () {
                return [
                    'id' => $this->bank->id,
                    'name' => $this->bank->name,
                    'kode_bank' => $this->bank->kode_bank,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}