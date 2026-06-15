<?php

namespace App\Http\Controllers;

use App\Models\KioskBypassCode;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KioskController extends Controller
{
    public function getAccess(string $roomId, string $code)
    {
        // 1. Admin bypass — ผ่านตลอด ไม่ตรวจ slot
        $bypass = KioskBypassCode::where('code', $code)->where('is_active', true)->first();
        if ($bypass) {
            return response()->json([
                'room_id' => $roomId,
                'uid'     => 'staff',
                'status'  => 1,
            ]);
        }

        // 2. หา member จาก code
        $member = Member::where('code', $code)->first();
        if (!$member) {
            return response()->json([]);
        }

        // 3. ดึง timestamp server → เช็ค slot ปัจจุบัน (Asia/Bangkok)
        $now     = Carbon::now('Asia/Bangkok');
        $today   = $now->toDateString();
        $curHour = $now->hour;

        // 4. เช็ค booking_groups ที่ confirmed + room + วันนี้ + slot ปัจจุบัน + member คนนี้อยู่ใน session
        $hasAccess = DB::table('booking_groups')
            ->join('bookings', 'bookings.group_id', '=', 'booking_groups.id')
            ->where('booking_groups.room_id', $roomId)
            ->where('booking_groups.date', $today)
            ->where('booking_groups.time_id', $curHour)
            ->where('booking_groups.status', 'confirmed')
            ->where('bookings.user_id', $member->id)
            ->whereNotIn('bookings.status', ['cancelled'])
            ->exists();

        if (!$hasAccess) {
            return response()->json([]);
        }

        return response()->json([
            'room_id' => $roomId,
            'uid'     => $code,
            'status'  => 1,
        ]);
    }
}
