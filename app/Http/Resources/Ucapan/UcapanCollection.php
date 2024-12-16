<?php

namespace App\Http\Resources\Ucapan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Bukutamu\UcapanResource;
use Carbon\Carbon; 


class UcapanCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allCountUcapan = $this->resource->total(); // Total guests across all pages
        $todayCountUcapan = $this->collection->filter(function ($item) {
            return Carbon::parse($item->created_at)->isToday();
        })->count();

        return [
            'data' => UcapanResource::collection($this->collection),
            'pagination' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
            ],
            'all_count_tamu' => $allCountUcapan,
            'today_count_tamu' => $todayCountUcapan,
        ];
    }
}
