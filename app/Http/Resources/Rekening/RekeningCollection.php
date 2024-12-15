<?php

namespace App\Http\Resources\Rekening;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Rekening\RekeningResource;

class RekeningCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => RekeningResource::collection($this->collection)
        ];
    }
}
