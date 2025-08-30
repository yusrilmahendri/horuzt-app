<?php

namespace App\Http\Resources\Ucapan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UcapanCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => UcapanResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'hadir_count' => $this->collection->where('kehadiran', 'hadir')->count(),
                'tidak_hadir_count' => $this->collection->where('kehadiran', 'tidak_hadir')->count(),
                'mungkin_count' => $this->collection->where('kehadiran', 'mungkin')->count(),
            ]
        ];
    }
}