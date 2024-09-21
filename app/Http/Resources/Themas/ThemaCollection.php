<?php

namespace App\Http\Resources\Themas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ThemaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ThemaResource::collection($this->collection),
            'total thema' => $this->collection->count()
        ];
    }
}
