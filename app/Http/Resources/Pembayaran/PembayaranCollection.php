<?php

namespace App\Http\Resources\Pembayaran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Pembayaran\PembayaranResource;

class PembayaranCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => PembayaranResource::collection($this->collection),
            'total Transaksi' => $this->collection->count()
        ];
    }
}
