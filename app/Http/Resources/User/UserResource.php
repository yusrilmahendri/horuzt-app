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

        $domainCreateDate = $this->mempelaiOne ? $this->mempelaiOne->created_at : null;


        $domainEndDate = null;
        if ($domainCreateDate && $this->invitationOne) {
            $monthsToAdd = match ($this->invitationOne->paket_undangan_id) {
                1 => 1,
                2 => 2,
                3 => 3,
                default => 0,
            };

            $domainEndDate = \Carbon\Carbon::parse($domainCreateDate)->addMonths($monthsToAdd)->toDateTimeString();
        }

        $userAktif = 1;
        if ($domainEndDate && \Carbon\Carbon::parse($domainEndDate)->isPast()) {
            $userAktif = 0;
        }

        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'kode_pemesanan'     => $this->kode_pemesanan,
            'user_aktif'         => $userAktif,
            'domain'             => $this->settingOne ? $this->settingOne->domain : null,
            'status'             => $this->mempelaiOne ? $this->mempelaiOne->status : null,
            'kd_status'          => $this->mempelaiOne ? $this->mempelaiOne->kd_status : null,
            'domain_create_date' => $domainCreateDate,
            'domain_end_date'    => $domainEndDate,
            'paket_undangan_id'  => $this->invitationOne ? $this->invitationOne->paket_undangan_id : null,


            'invitations'        => $this->whenLoaded('invitation', function () {
                return $this->invitations->map(function ($invitation) {
                    return [
                        'id'             => $invitation->id ?? null,
                        'status'         => $invitation->status ?? null,
                        'created_at'     => $invitation->created_at ?? null,
                        'updated_at'     => $invitation->updated_at ?? null,


                        'paket_undangan' => $invitation->paketUndangan ? [
                            'id'         => $invitation->paketUndangan->id ?? null,
                            'nama_paket' => $invitation->paketUndangan->nama_paket ?? null,
                            'harga'      => $invitation->paketUndangan->harga ?? null,
                        ] : null,
                    ];
                });
            }),
        ];
    }

}
