<?php

namespace App\Http\Resources\Bukutamu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Bukutamu\BukuTamuResource;

class BukuTamuCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => BukuTamuResource::collection($this->collection)
        ];
    }
}
