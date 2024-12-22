<?php

namespace App\Http\Resources\Acara;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcaraResource extends JsonResource
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
            'nama_acara' => $this->nama_acara,
            'tanggal_acara' => $this->tanggal_acara,
            'start_acara' => $this->start_acara,
            'end_acara' => $this->end_acara,
            'alamat' => $this->alamat,
            'link_maps' => $this->link_maps,
            'user_id' => $this->user_id,
            'countdown' => $this->countdown ? [
                'id' => $this->countdown->id,
                'name_countdown' => $this->countdown->name_countdown,
                'created_at' => $this->countdown->created_at,
                'updated_at' => $this->countdown->updated_at,
            ] : null,
        ];
    }
}
