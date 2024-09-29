<?php

namespace App\Http\Resources\PaketNikah;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\PaketNikah\PaketResource;

class PaketCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => PaketResource::collection($this->collection),
            'total paket' => $this->collection->count(),
        ];
    }
}
