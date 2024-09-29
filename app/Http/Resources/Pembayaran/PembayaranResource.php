<?php

namespace App\Http\Resources\Pembayaran;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Bank\BankResource;
use App\Http\Resources\Order\OrderResource;

class PembayaranResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bank_id' => new BankResource($this->bank),
            'order_id' => new OrderResource($this->order),
            'status' => $this->status,
            'nama_pemilik_rek' => $this->nama_pemilik_rek,
            'no_rek' => $this->no_rek,
            'price' => $this->price,
            'va_number' => $this->va_number,
            'type_channel' => $this->type_channel
        ];
    }
}
