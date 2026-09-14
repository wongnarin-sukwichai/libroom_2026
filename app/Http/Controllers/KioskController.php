<?php

namespace App\Http\Controllers;

use App\Models\KioskBypassCode;
use App\Models\Member;
use App\Models\Room;
use App\Support\ScanCheckin;

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
        if (! $member) {
            return response()->json([]);
        }

        $room = Room::find($roomId);
        if (! $room) {
            return response()->json([]);
        }

        // 3. เช็ค booking ณ ชั่วโมงปัจจุบัน + เช็คอิน (ห้อง access_control='1')
        $result = ScanCheckin::attempt($room, $member);

        if ($result === ScanCheckin::NONE) {
            return response()->json([]);
        }

        return response()->json([
            'room_id'    => $roomId,
            'uid'        => $code,
            'status'     => 1,
            'checked_in' => $result === ScanCheckin::CHECKED_IN,
        ]);
    }
}
