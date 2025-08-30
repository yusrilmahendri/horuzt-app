<?php

namespace App\Http\Resources\Ucapan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UcapanResource extends JsonResource
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
            'nama' => $this->nama,
            'kehadiran' => $this->kehadiran,
            'kehadiran_label' => $this->getKehadiranLabel(),
            'pesan' => $this->pesan,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get human readable kehadiran label
     */
    private function getKehadiranLabel(): string
    {
        return match($this->kehadiran) {
            'hadir' => 'Hadir',
            'tidak_hadir' => 'Tidak Hadir',
            'mungkin' => 'Mungkin',
            default => 'Unknown'
        };
    }
}