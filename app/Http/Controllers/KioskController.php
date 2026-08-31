<?php

namespace App\Http\Controllers;

use App\Models\BookingGroup;
use App\Models\KioskBypassCode;
use App\Models\Member;
use App\Models\Room;
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

        $room = Room::find($roomId);
        if (!$room) {
            return response()->json([]);
        }

        // 3. เวลา server (Asia/Bangkok)
        $now     = Carbon::now('Asia/Bangkok');
        $today   = $now->toDateString();
        $curHour = $now->hour;

        // ห้องที่ติด access control: kiosk อนุมัติ + เช็คอินเองได้ (รับทั้ง waiting_confirm/confirmed)
        // ห้องปกติ: ต้อง confirmed มาก่อน (เจ้าหน้าที่ approve)
        $isAC              = $room->access_control === '1';
        $allowedGroupStati = $isAC ? ['waiting_confirm', 'confirmed'] : ['confirmed'];

        // 4. booking_groups ของ member นี้ ในห้องนี้ วันนี้ ที่สถานะเข้าเกณฑ์
        $groups = BookingGroup::where('room_id', $room->id)
            ->where('date', $today)
            ->whereIn('status', $allowedGroupStati)
            ->whereHas('bookings', fn($q) => $q
                ->where('user_id', $member->id)
                ->whereNotIn('status', ['cancelled', 'no_show']))
            ->get();

        // 5. ต้องมี slot ที่ครอบชั่วโมงปัจจุบัน (time_id = ชั่วโมงนี้)
        //    ถ้ายังเป็น pending (สมาชิกไม่ครบ min_capacity) จะไม่อยู่ใน $groups → ไม่ผ่าน
        $hasNow = $groups->firstWhere('time_id', $curHour);
        if (!$hasNow) {
            return response()->json([]);
        }

        // 6. ห้อง access control → อนุมัติ + เช็คอิน เบ็ดเสร็จตรงนี้ (current slot + slot ที่เหลือใน session)
        if ($isAC) {
            DB::transaction(function () use ($room, $today, $curHour, $member) {
                $targets = BookingGroup::where('room_id', $room->id)
                    ->where('date', $today)
                    ->where('time_id', '>=', $curHour)
                    ->whereIn('status', ['waiting_confirm', 'confirmed'])
                    ->lockForUpdate()
                    ->get();

                foreach ($targets as $g) {
                    if ($g->status === 'waiting_confirm') {
                        $g->update(['status' => 'confirmed']);
                    }
                    $g->bookings()
                        ->where('user_id', $member->id)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->update([
                            'status'     => 'checked_in',
                            'checkin_at' => Carbon::now('Asia/Bangkok'),
                        ]);
                }
            });
        }

        return response()->json([
            'room_id'    => $roomId,
            'uid'        => $code,
            'status'     => 1,
            'checked_in' => $isAC,
        ]);
    }
}
