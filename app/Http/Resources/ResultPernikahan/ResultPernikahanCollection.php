<?php

namespace App\Http\Resources\ResultPernikahan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Pernikahan\PernikahanResource;

class ResultPernikahanCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pernikahan' => PernikahanResource::collection($this->pernikahan),
        ];
    }
}
