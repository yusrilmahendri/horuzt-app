<?php

namespace App\Http\Resources\Bukutamu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Bukutamu\BukuTamuResource;
<<<<<<< HEAD
=======
use Carbon\Carbon; 
>>>>>>> 067dd6d37f3e90bdb30b98d8da65384f01ce9070

class BukuTamuCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
<<<<<<< HEAD
    {
        return [
            'data' => BukuTamuResource::collection($this->collection)
=======
    {   
        // all count tamu
        $allCountTamu = $this->collection->count();

        // Total tamu hari ini
        $todayCountTamu = $this->collection->filter(function ($item) {
            return Carbon::parse($item->created_at)->isToday();
        })->count();

        return [
            'data' => BukuTamuResource::collection($this->collection),
            'all_count_tamu' => $allCountTamu,
            'today_count_tamu' => $todayCountTamu,
>>>>>>> 067dd6d37f3e90bdb30b98d8da65384f01ce9070
        ];
    }
}
