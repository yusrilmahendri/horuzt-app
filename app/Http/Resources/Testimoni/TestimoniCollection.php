<?php

namespace App\Http\Resources\Testimoni;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Testimoni\TestimoniResource;

class TestimoniCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => TestimoniResource::collection($this->collection)
        ];
    }
}
