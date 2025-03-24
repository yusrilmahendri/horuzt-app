<?php
namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArras(Request $request): array
    {
        return [
            'data' => UserResource::collection($this->collection),
        ];
    }
    public function toArray($request)
    {
        return $this->collection->map(function ($user) {
            return [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'kode_pemesanan'    => $user->kodePemesanan ? $user->kodePemesanan->kode_pemesanan : null,
                'keterangan'        => $user->kodePemesanan ? $user->kodePemesanan->keterangan : null,
                'domain'            => $user->settingOne ? $user->settingOne->domain : null,
                'url_video'         => $user->mempelaiOne ? $user->mempelaiOne->url_video : null,
                'paket_undangan_id' => $user->invitation ? $user->invitation->paket_undangan_id : null,
                'created_at'        => $user->created_at,
                'updated_at'        => $user->updated_at,
            ];
        });
    }
}
