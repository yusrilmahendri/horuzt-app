<?php

declare(strict_types=1);

namespace App\Http\Resources\Bukutamu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BukuTamuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'nama' => $this->nama,
            'email' => $this->email,
            'telepon' => $this->telepon,
            'ucapan' => $this->ucapan,
            'status_kehadiran' => $this->status_kehadiran,
            'status_kehadiran_label' => $this->getStatusLabel(),
            'jumlah_tamu' => $this->jumlah_tamu,
            'is_approved' => $this->is_approved,
            'ip_address' => $this->when($request->user()?->hasRole('admin'), $this->ip_address),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match ($this->status_kehadiran) {
            'hadir' => 'Hadir',
            'tidak_hadir' => 'Tidak Hadir',
            'ragu' => 'Masih Ragu',
            default => 'Belum Konfirmasi',
        };
    }
}
