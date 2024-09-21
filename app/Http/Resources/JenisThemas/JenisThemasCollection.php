<?php

namespace App\Http\Resources\JenisThemas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JenisThemasCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => JenisThemasResource::collection($this->collection),
            'total jenis thema' => $this->collection->count()
        ];
    }
}
