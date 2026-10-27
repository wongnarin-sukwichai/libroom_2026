<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Member;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\UsageStats;

class AdminOverviewController extends Controller
{
    public function stats()
    {
        $today = Carbon::today()->format('Y-m-d');

        return response()->json([
            'approved_today'  => BookingGroup::where('date', $today)->where('status', 'confirmed')->count(),
            'cancelled_today' => BookingGroup::where('date', $today)->where('status', 'cancelled')->count(),
            'active_rooms'    => Room::where('status', '0')->count(),
            'total_rooms'     => Room::count(),
            'total_members'   => Member::count(),
        ]);
    }

    /**
     * สถิติการเข้าใช้บริการรายอาคาร (location) เดือนนี้ — จากระบบปัจจุบัน (UsageStats ตัวเดียวกับแท็บ "สถิติการเข้าใช้งาน")
     * เดิม proxy ไประบบเก่า libroom.msu.ac.th/api/getService (เลิกใช้ 2026-10-27)
     * ใช้ทั้ง /admin/overview-service (การ์ดหน้าภาพรวม) และ /api/overview-service (ระบบภายนอก)
     * คีย์ arec/dlp/space = รูปแบบเดิมของระบบเก่า คงไว้ให้ผู้เรียกภายนอกที่อาจยังใช้อยู่ไม่พัง (location id 1/2/3)
     */
    public function serviceStats()
    {
        $now   = Carbon::now('Asia/Bangkok');
        $stats = UsageStats::monthly($now, $now);

        $locations = $stats['locations']->map(fn($loc) => [
            'id'    => $loc['id'],
            'title' => $loc['title'],
            'total' => $loc['total'],
        ])->values();

        $legacy = [];
        foreach ([1 => 'arec', 2 => 'dlp', 3 => 'space'] as $id => $key) {
            $legacy[$key] = $locations->firstWhere('id', $id)['total'] ?? 0;
        }

        return response()->json($legacy + [
            'month'     => $stats['from'],
            'label'     => UsageStats::monthLabel($now),
            'unit'      => $stats['unit'],
            'total'     => $stats['total'],
            'locations' => $locations,
        ]);
    }

    // top 5 zone ที่มีการจองมากที่สุดในเดือนปัจจุบัน (จาก DB เราเอง)
    public function mostStats()
    {
        $rows = DB::table('booking_groups')
            ->join('rooms', 'rooms.id', '=', 'booking_groups.room_id')
            ->join('zones', 'zones.id', '=', 'rooms.zone_id')
            ->whereIn('booking_groups.status', ['waiting_confirm', 'confirmed'])
            ->whereMonth('booking_groups.date', Carbon::now()->month)
            ->whereYear('booking_groups.date', Carbon::now()->year)
            ->groupBy('zones.id', 'zones.title')
            ->orderByDesc('count')
            ->limit(5)
            ->select('zones.title', DB::raw('COUNT(booking_groups.id) as count'))
            ->get();

        return response()->json($rows);
    }
}
