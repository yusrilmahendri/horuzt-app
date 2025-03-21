<?php

namespace App\Http\Resources\Pernikahan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Pernikahan\PernikahanResource;

class PernikahanCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => PernikahanResource::collection($this->collection),
        ];
    }
}
