<?php

namespace App\Support;

use App\Models\BookingGroup;
use App\Models\Member;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * เช็คอิน member เข้าห้อง ณ ชั่วโมงปัจจุบัน
 * ใช้ร่วมกัน: KioskController (เครื่อง kiosk) และ ScanBookController (สแกน QR ที่ตัว unit)
 *
 * - ห้อง access_control='1' → promote waiting_confirm→confirmed แล้ว set checked_in
 *   ทุก slot ที่เหลือใน session วันนี้ (idempotent)
 * - ห้อง access_control='0' → ปกติเจ้าหน้าที่เช็คอินให้ → คืน PENDING_STAFF (ไม่เขียน)
 *   ยกเว้นเรียกแบบ $force = true (สแกน QR ที่ตัว = ยืนอยู่จริง) จะเช็คอินให้เลย
 */
class ScanCheckin
{
    public const NONE               = 'none';               // ไม่มี booking ที่ใช้ได้ตอนนี้
    public const CHECKED_IN         = 'checked_in';         // เพิ่งเช็คอินให้
    public const ALREADY_CHECKED_IN = 'already_checked_in'; // เช็คอินไปแล้ว
    public const PENDING_STAFF      = 'pending_staff';      // มี booking แต่รอเจ้าหน้าที่เช็คอิน

    public static function attempt(Room $room, Member $member, ?Carbon $now = null, bool $force = false): string
    {
        $now     = $now ?: Carbon::now('Asia/Bangkok');
        $today   = $now->toDateString();
        $curHour = $now->hour;
        $isAC    = $room->access_control === '1';

        $allowed = ($isAC || $force) ? ['waiting_confirm', 'confirmed'] : ['confirmed'];

        $current = BookingGroup::where('room_id', $room->id)
            ->where('date', $today)
            ->where('time_id', $curHour)
            ->whereIn('status', $allowed)
            ->whereHas('bookings', fn($q) => $q
                ->where('user_id', $member->id)
                ->whereNotIn('status', ['cancelled', 'no_show']))
            ->first();

        if (! $current) {
            return self::NONE;
        }

        $alreadyIn = $current->status === 'confirmed'
            && $current->bookings()->where('user_id', $member->id)->where('status', 'checked_in')->exists();

        if ($alreadyIn) {
            return self::ALREADY_CHECKED_IN;
        }

        if (! $isAC && ! $force) {
            return self::PENDING_STAFF;
        }

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

        return self::CHECKED_IN;
    }
}
