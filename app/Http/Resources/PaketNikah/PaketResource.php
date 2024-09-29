<?php

namespace App\Http\Resources\PaketNikah;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaketResource extends JsonResource
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
            'name' => $this->name,
            'price' => $this->price,
            'masa_aktif' => $this->masa_aktif,
            'buku_tamu' => $this->buku_tamu,
            'kirim_wa' => $this->kirim_wa,
            'kirim_hadiah' => $this->kirim_hadiah,
            'tema_bebas' => $this->tema_bebas,
            'import_data' => $this->import_data,
        ];
    }
}
