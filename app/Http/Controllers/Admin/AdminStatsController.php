<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\UsageStats;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    private const MAX_MONTHS = 24;

    // GET /admin/stats/usage?from=Y-m&to=Y-m — ไม่ส่ง = เดือนปัจจุบัน
    public function usage(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date_format:Y-m',
            'to'   => 'nullable|date_format:Y-m',
        ]);

        $now  = Carbon::now('Asia/Bangkok');
        $from = isset($data['from']) ? Carbon::createFromFormat('!Y-m', $data['from']) : $now->copy();
        $to   = isset($data['to'])   ? Carbon::createFromFormat('!Y-m', $data['to'])   : $from->copy();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInMonths($to) >= self::MAX_MONTHS) {
            return response()->json(['message' => 'เลือกช่วงได้ไม่เกิน ' . self::MAX_MONTHS . ' เดือน'], 422);
        }

        return response()->json(UsageStats::monthly($from, $to));
    }
}
