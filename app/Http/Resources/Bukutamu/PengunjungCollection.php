<?php

namespace App\Http\Resources\BukuTamu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Carbon\Carbon; 
use App\Http\Resources\Bukutamu\PengunjungResource;


class PengunjungCollection extends ResourceCollection
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
        $monthlyCountTamu = $this->collection->groupBy(function ($item) {
            // Group by year and month
            return Carbon::parse($item->created_at)->format('Y-m'); // Format as 'YYYY-MM'
        })->map(function ($items) {
            return $items->count(); // Count the number of visits per month
        });

        return [
            'data' => PengunjungResource::collection($this->collection),
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
            'monthly_count_tamu' => $monthlyCountTamu,
        ];
    }
}
