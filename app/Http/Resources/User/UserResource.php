<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'kode_pemesanan' => $this->kode_pemesanan,

            // Relasi invitations
            'invitations' => $this->whenLoaded('invitations', function () {
                return $this->invitations->map(function ($invitation) {
                    return [
                        'id' => $invitation->id ?? null,
                        'status' => $invitation->status ?? null,
                        'created_at' => $invitation->created_at ?? null,
                        'updated_at' => $invitation->updated_at ?? null,

                        // Relasi paket_undangan dalam invitation
                        'paket_undangan' => $invitation->paketUndangan ? [
                            'id' => $invitation->paketUndangan->id ?? null,
                            'nama_paket' => $invitation->paketUndangan->nama_paket ?? null,
                            'harga' => $invitation->paketUndangan->harga ?? null,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
