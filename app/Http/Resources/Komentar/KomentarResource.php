<?php

namespace App\Http\Resources\Komentar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KomentarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'komentar' => $this->komentar,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
