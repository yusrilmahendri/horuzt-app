<?php

namespace App\Http\Resources\Acara;

use App\Services\LocationResolverService;
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
        $location = app(LocationResolverService::class)->resolveAcara($this->resource);

        return [
            'id' => $this->id,
            'jenis_acara' => $this->jenis_acara,
            'nama_acara' => $this->nama_acara,
            'tanggal_acara' => $this->tanggal_acara,
            'start_acara' => $this->start_acara,
            'end_acara' => $this->end_acara,
            'alamat' => $location['alamat'],
            'link_maps' => $location['link_maps'],
            'address' => $location['address'],
            'location_name' => $location['location_name'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'google_maps_url' => $location['google_maps_url'],
            'place_id' => $location['place_id'],
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
