<?php

namespace App\Http\Resources\BukuTamu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;
use Carbon\Carbon; 

class PengunjungResource extends JsonResource
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
            'nama' => $this->nama,
            'tanggal' => Carbon::parse($this->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
