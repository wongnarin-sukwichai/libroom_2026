<?php

namespace App\Http\Controllers;

use App\Support\UsageStats;
use Carbon\Carbon;

/**
 * API สถิติสำหรับระบบภายนอก — เดือนปัจจุบันเท่านั้น
 * GET /api/stats/usage  (Authorization: Bearer LIBROOM_STATS_API_TOKEN)
 */
class StatsApiController extends Controller
{
    public function usage()
    {
        $now   = Carbon::now('Asia/Bangkok');
        $stats = UsageStats::monthly($now, $now);

        return response()->json([
            'month'        => $stats['from'],
            'label'        => UsageStats::monthLabel($now),
            'unit'         => $stats['unit'],
            'generated_at' => $now->toIso8601String(),
            'total'        => $stats['total'],
            'locations'    => $stats['locations']->map(fn ($loc) => [
                'id'    => $loc['id'],
                'title' => $loc['title'],
                'total' => $loc['total'],
                'zones' => $loc['zones']->map(fn ($z) => [
                    'id'    => $z['id'],
                    'title' => $z['title'],
                    'count' => $loc['rows'][0]['zones'][$z['id']] ?? 0,
                ])->values(),
            ])->values(),
        ]);
    }
}
