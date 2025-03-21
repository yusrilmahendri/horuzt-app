<?php

namespace App\Http\Resources\Bukutamu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Bukutamu\BukuTamuResource;
use Carbon\Carbon; 

class BukuTamuCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allCountTamu = $this->resource->total(); // Total guests across all pages
        $todayCountTamu = $this->collection->filter(function ($item) {
            return Carbon::parse($item->created_at)->isToday();
        })->count();

        return [
            'data' => BukuTamuResource::collection($this->collection),
            'pagination' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
            ],
            'all_count_tamu' => $allCountTamu,
            'today_count_tamu' => $todayCountTamu,
        ];
    }
}
