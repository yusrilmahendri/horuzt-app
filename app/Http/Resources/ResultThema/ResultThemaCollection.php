<?php

namespace App\Http\Resources\ResultThema;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\JenisThemas\JenisThemasResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Themas\ThemaResource;
use App\Http\Resources\ResultThema\ResultThemaResource;


class ResultThemaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ResultThemaResource::collection($this->collection),
            'count result thema' => $this->collection->count()
        ];
    }
}
