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
            'bank_name' => $this->bank ? $this->bank->name : null, // Include bank name
            'nomor_rekening' => $this->nomor_rekening,
            'nama_pemilik' => $this->nama_pemilik,
            'photo_rek' => $this->photo_rek ? asset('storage/photos' . $this->photo_rek) : null, // Public URL for the photo
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
