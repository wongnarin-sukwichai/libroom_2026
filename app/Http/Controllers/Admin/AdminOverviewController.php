<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Member;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

    // proxy เรียก external API สถิติการเข้าใช้บริการ Location เดือนนี้
    public function serviceStats()
    {
        try {
            $data = $this->apiHttp()->get('https://libroom.msu.ac.th/api/getService')->json();
            return response()->json($data ?? []);
        } catch (\Exception) {
            return response()->json([]);
        }
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

    private function apiHttp(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(5)->withToken(config('services.libroom.api_token'));
    }
}
