<?php

namespace App\Http\Resources\Komentar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class KomentarCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
            ],
        ];
    }
}
