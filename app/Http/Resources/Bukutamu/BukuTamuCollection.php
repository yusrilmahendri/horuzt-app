<?php

declare(strict_types=1);

namespace App\Http\Resources\Bukutamu;

use App\Models\BukuTamu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BukuTamuCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id ?? $request->query('user_id');
        
        $statistics = $this->calculateStatistics($userId);

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
            'statistics' => $statistics,
        ];
    }

    private function calculateStatistics(?int $userId): array
    {
        if (!$userId) {
            return $this->emptyStatistics();
        }

        $baseQuery = BukuTamu::forUser($userId);
        
        $total = (clone $baseQuery)->count();
        $hadir = (clone $baseQuery)->hadir()->count();
        $tidakHadir = (clone $baseQuery)->tidakHadir()->count();
        $ragu = (clone $baseQuery)->ragu()->count();
        $today = (clone $baseQuery)->today()->count();
        $totalTamu = (clone $baseQuery)->hadir()->sum('jumlah_tamu');
        $approved = (clone $baseQuery)->approved()->count();
        $pending = (clone $baseQuery)->pending()->count();

        return [
            'total_entries' => $total,
            'total_hadir' => $hadir,
            'total_tidak_hadir' => $tidakHadir,
            'total_ragu' => $ragu,
            'total_tamu_hadir' => (int) $totalTamu,
            'today_entries' => $today,
            'approved_entries' => $approved,
            'pending_entries' => $pending,
            'percentage_hadir' => $total > 0 ? round(($hadir / $total) * 100, 1) : 0,
            'percentage_tidak_hadir' => $total > 0 ? round(($tidakHadir / $total) * 100, 1) : 0,
            'percentage_ragu' => $total > 0 ? round(($ragu / $total) * 100, 1) : 0,
        ];
    }

    private function emptyStatistics(): array
    {
        return [
            'total_entries' => 0,
            'total_hadir' => 0,
            'total_tidak_hadir' => 0,
            'total_ragu' => 0,
            'total_tamu_hadir' => 0,
            'today_entries' => 0,
            'approved_entries' => 0,
            'pending_entries' => 0,
            'percentage_hadir' => 0,
            'percentage_tidak_hadir' => 0,
            'percentage_ragu' => 0,
        ];
    }
}
