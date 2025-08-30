<?php

namespace App\Http\Controllers;

use App\Models\Ucapan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard overview for specific wedding
     * GET /v1/dashboard/overview/{user_id}
     */
    public function overview(Request $request, string $user_id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period' => 'sometimes|in:7d,30d,90d,all',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
            ]);

            // Verify user exists
            $user = User::findOrFail($user_id);

            // TODO: Re-enable security check after debugging SQL issue
            // Security check: User can only access their own dashboard
            // $authenticatedUser = $request->user();
            // if (!$authenticatedUser || $authenticatedUser->id != $user_id) {
            //     return response()->json([
            //         'message' => 'Access denied. You can only view your own dashboard.'
            //     ], 403);
            // }

            // Determine date range
            $dateRange = $this->getDateRange($validated);

            // Build base query
            $baseQuery = Ucapan::where('user_id', $user_id);

            if ($dateRange['start']) {
                $baseQuery->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $baseQuery->where('created_at', '<=', $dateRange['end']);
            }

            // Get metrics efficiently in single query
            $metrics = $baseQuery->selectRaw('
                COUNT(*) as total_pengunjung,
                SUM(CASE WHEN kehadiran = "hadir" THEN 1 ELSE 0 END) as konfirmasi_kehadiran,
                SUM(CASE WHEN kehadiran = "tidak_hadir" THEN 1 ELSE 0 END) as tidak_hadir,
                SUM(CASE WHEN kehadiran = "mungkin" THEN 1 ELSE 0 END) as mungkin_hadir,
                COUNT(*) as total_ucapan
            ')->first();

            // Calculate percentages
            $totalResponses = $metrics->total_pengunjung;
            $attendanceRate = $totalResponses > 0 ? round(($metrics->konfirmasi_kehadiran / $totalResponses) * 100, 1) : 0;

            // Get comparison data (previous period)
            $previousPeriodData = $this->getPreviousPeriodComparison($user_id, $dateRange);

            $response = [
                'user_id' => (int) $user_id,
                'wedding_owner' => $user->name,
                'period' => [
                    'from' => $dateRange['start']?->format('Y-m-d'),
                    'to' => $dateRange['end']?->format('Y-m-d'),
                    'days' => $dateRange['days']
                ],
                'metrics' => [
                    'total_pengunjung' => [
                        'count' => $metrics->total_pengunjung,
                        'label' => 'Orang / ' . $dateRange['days'] . ' hari',
                        'change_percentage' => $previousPeriodData['pengunjung_change'],
                        'trending' => $previousPeriodData['pengunjung_trending']
                    ],
                    'konfirmasi_kehadiran' => [
                        'count' => $metrics->konfirmasi_kehadiran,
                        'label' => 'orang akan datang',
                        'percentage' => $attendanceRate,
                        'change_percentage' => $previousPeriodData['kehadiran_change'],
                        'trending' => $previousPeriodData['kehadiran_trending']
                    ],
                    'doa_ucapan' => [
                        'count' => $metrics->total_ucapan,
                        'label' => 'orang memberi ucapan',
                        'change_percentage' => $previousPeriodData['ucapan_change'],
                        'trending' => $previousPeriodData['ucapan_trending']
                    ],
                    'total_hadiah' => [
                        'count' => 0, // Placeholder - no gift table found
                        'label' => 'diterima',
                        'note' => 'Feature not yet implemented',
                        'change_percentage' => 0,
                        'trending' => 'stable'
                    ]
                ],
                'breakdown' => [
                    'kehadiran' => [
                        'hadir' => $metrics->konfirmasi_kehadiran,
                        'tidak_hadir' => $metrics->tidak_hadir,
                        'mungkin' => $metrics->mungkin_hadir
                    ],
                    'response_rate' => [
                        'hadir_percentage' => $totalResponses > 0 ? round(($metrics->konfirmasi_kehadiran / $totalResponses) * 100, 1) : 0,
                        'tidak_hadir_percentage' => $totalResponses > 0 ? round(($metrics->tidak_hadir / $totalResponses) * 100, 1) : 0,
                        'mungkin_percentage' => $totalResponses > 0 ? round(($metrics->mungkin_hadir / $totalResponses) * 100, 1) : 0
                    ]
                ]
            ];

            return response()->json(['data' => $response], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Parameter tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data dashboard.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get visitor trends over time for charts
     * GET /v1/dashboard/trends/{user_id}
     */
    public function trends(Request $request, string $user_id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period' => 'sometimes|in:7d,30d,90d,all',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'group_by' => 'sometimes|in:day,week,month',
            ]);

            // Verify user exists
            User::findOrFail($user_id);

            // TODO: Re-enable security check after debugging SQL issue
            // Security check: User can only access their own dashboard
            // $authenticatedUser = $request->user();
            // if (!$authenticatedUser || $authenticatedUser->id != $user_id) {
            //     return response()->json([
            //         'message' => 'Access denied. You can only view your own dashboard.'
            //     ], 403);
            // }

            // Determine date range and grouping
            $dateRange = $this->getDateRange($validated);
            $groupBy = $validated['group_by'] ?? $this->getDefaultGrouping($dateRange['days']);

            // Build trends query
            $trendsQuery = Ucapan::where('user_id', $user_id);

            if ($dateRange['start']) {
                $trendsQuery->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $trendsQuery->where('created_at', '<=', $dateRange['end']);
            }

            // Group by time period
            $dateFormat = $this->getDateFormat($groupBy);
            $trends = $trendsQuery
                ->selectRaw("
                    DATE_FORMAT(created_at, '{$dateFormat}') as period,
                    COUNT(*) as total_visitors,
                    SUM(CASE WHEN kehadiran = 'hadir' THEN 1 ELSE 0 END) as confirmed_attendance,
                    DATE_FORMAT(created_at, '{$dateFormat}') as date
                ")
                ->groupByRaw("DATE_FORMAT(created_at, '{$dateFormat}')")
                ->orderByRaw("DATE_FORMAT(created_at, '{$dateFormat}')")
                ->get();

            // Format for chart consumption
            $chartData = $trends->map(function ($item) use ($groupBy) {
                // Convert period back to a proper date for formatting
                $date = null;
                if ($groupBy === 'day') {
                    $date = $item->period; // Already in YYYY-MM-DD format
                } elseif ($groupBy === 'week') {
                    // Convert YYYY-WW to date
                    $parts = explode('-', $item->period);
                    $date = Carbon::now()->setISODate($parts[0], $parts[1])->format('Y-m-d');
                } elseif ($groupBy === 'month') {
                    // Convert YYYY-MM to date
                    $date = $item->period . '-01';
                }

                return [
                    'period' => $item->period,
                    'date' => $date,
                    'total_visitors' => $item->total_visitors,
                    'confirmed_attendance' => $item->confirmed_attendance,
                    'formatted_date' => $this->formatPeriodLabel($date, $groupBy)
                ];
            });

            return response()->json([
                'data' => [
                    'user_id' => (int) $user_id,
                    'period' => [
                        'from' => $dateRange['start']?->format('Y-m-d'),
                        'to' => $dateRange['end']?->format('Y-m-d'),
                        'group_by' => $groupBy
                    ],
                    'trends' => $chartData,
                    'summary' => [
                        'total_data_points' => $chartData->count(),
                        'peak_visitors' => $chartData->max('total_visitors'),
                        'average_daily_visitors' => $chartData->avg('total_visitors')
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data trend.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get recent messages/ucapan for dashboard display
     * GET /v1/dashboard/messages/{user_id}
     */
    public function messages(Request $request, string $user_id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0',
                'period' => 'sometimes|in:7d,30d,90d,all',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
            ]);

            // Verify user exists and authenticate access
            $user = User::findOrFail($user_id);

            // Security check: User can only access their own dashboard
            $authenticatedUser = $request->user();
            if (!$authenticatedUser || $authenticatedUser->id != $user_id) {
                return response()->json([
                    'message' => 'Access denied. You can only view your own dashboard.'
                ], 403);
            }

            $limit = $validated['limit'] ?? 10;
            $offset = $validated['offset'] ?? 0;
            $dateRange = $this->getDateRange($validated);

            // Build messages query
            $messagesQuery = Ucapan::where('user_id', $user_id);

            if ($dateRange['start']) {
                $messagesQuery->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $messagesQuery->where('created_at', '<=', $dateRange['end']);
            }

            // Get total count for pagination
            $totalCount = $messagesQuery->count();

            // Get paginated messages
            $messages = $messagesQuery
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'nama' => $message->nama,
                        'kehadiran' => $message->kehadiran,
                        'kehadiran_label' => $this->getKehadiranLabel($message->kehadiran),
                        'pesan' => $message->pesan,
                        'pesan_preview' => strlen($message->pesan) > 100 ?
                            substr($message->pesan, 0, 100) . '...' : $message->pesan,
                        'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                        'created_at_human' => $message->created_at->diffForHumans(),
                    ];
                });

            return response()->json([
                'data' => [
                    'user_id' => (int) $user_id,
                    'wedding_owner' => $user->name,
                    'pagination' => [
                        'total' => $totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $totalCount
                    ],
                    'messages' => $messages
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data pesan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Helper: Get date range based on request parameters
     */
    private function getDateRange(array $validated): array
    {
        $now = Carbon::now();

        if (isset($validated['date_from']) && isset($validated['date_to'])) {
            $start = Carbon::parse($validated['date_from'])->startOfDay();
            $end = Carbon::parse($validated['date_to'])->endOfDay();
        } else {
            $period = $validated['period'] ?? '7d';

            switch ($period) {
                case '7d':
                    $start = $now->copy()->subDays(7)->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                case '30d':
                    $start = $now->copy()->subDays(30)->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                case '90d':
                    $start = $now->copy()->subDays(90)->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                case 'all':
                default:
                    $start = null;
                    $end = null;
            }
        }

        $days = $start && $end ? $start->diffInDays($end) + 1 : 'all';

        return [
            'start' => $start,
            'end' => $end,
            'days' => $days
        ];
    }

    /**
     * Helper: Get previous period comparison data
     */
    private function getPreviousPeriodComparison(string $user_id, array $dateRange): array
    {
        if (!$dateRange['start'] || !$dateRange['end']) {
            return [
                'pengunjung_change' => 0,
                'pengunjung_trending' => 'stable',
                'kehadiran_change' => 0,
                'kehadiran_trending' => 'stable',
                'ucapan_change' => 0,
                'ucapan_trending' => 'stable'
            ];
        }

        $diffDays = $dateRange['start']->diffInDays($dateRange['end']);
        $previousStart = $dateRange['start']->copy()->subDays($diffDays + 1);
        $previousEnd = $dateRange['start']->copy()->subDay();

        $previousData = Ucapan::where('user_id', $user_id)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN kehadiran = "hadir" THEN 1 ELSE 0 END) as hadir
            ')->first();

        $currentData = Ucapan::where('user_id', $user_id)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN kehadiran = "hadir" THEN 1 ELSE 0 END) as hadir
            ')->first();

        return [
            'pengunjung_change' => $this->calculatePercentageChange($previousData->total, $currentData->total),
            'pengunjung_trending' => $this->getTrendDirection($previousData->total, $currentData->total),
            'kehadiran_change' => $this->calculatePercentageChange($previousData->hadir, $currentData->hadir),
            'kehadiran_trending' => $this->getTrendDirection($previousData->hadir, $currentData->hadir),
            'ucapan_change' => $this->calculatePercentageChange($previousData->total, $currentData->total),
            'ucapan_trending' => $this->getTrendDirection($previousData->total, $currentData->total)
        ];
    }

    /**
     * Helper: Calculate percentage change
     */
    private function calculatePercentageChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Helper: Get trend direction
     */
    private function getTrendDirection($previous, $current): string
    {
        if ($current > $previous) return 'up';
        if ($current < $previous) return 'down';
        return 'stable';
    }

    /**
     * Helper: Get default grouping based on date range
     */
    private function getDefaultGrouping(int $days): string
    {
        if ($days <= 14) return 'day';
        if ($days <= 90) return 'week';
        return 'month';
    }

    /**
     * Helper: Get MySQL date format for grouping
     */
    private function getDateFormat(string $groupBy): string
    {
        switch ($groupBy) {
            case 'day': return '%Y-%m-%d';
            case 'week': return '%Y-%u';
            case 'month': return '%Y-%m';
            default: return '%Y-%m-%d';
        }
    }

    /**
     * Helper: Format period label for display
     */
    private function formatPeriodLabel(string $date, string $groupBy): string
    {
        $carbon = Carbon::parse($date);

        switch ($groupBy) {
            case 'day': return $carbon->format('d M Y');
            case 'week': return 'Week ' . $carbon->week . ', ' . $carbon->year;
            case 'month': return $carbon->format('M Y');
            default: return $carbon->format('d M Y');
        }
    }

    /**
     * Helper: Get kehadiran label in Indonesian
     */
    private function getKehadiranLabel(string $kehadiran): string
    {
        switch ($kehadiran) {
            case 'hadir': return 'Akan Hadir';
            case 'tidak_hadir': return 'Tidak Hadir';
            case 'mungkin': return 'Mungkin Hadir';
            default: return 'Belum Dikonfirmasi';
        }
    }
}
