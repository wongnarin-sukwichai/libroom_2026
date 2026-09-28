<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * โควตาการจอง 2 ชั้น (ตรวจพร้อมกันเสมอ):
 *  1) เพดานรวมทุก zone ต่อคนต่อวัน (global) — ตั้งค่าได้ที่ /admin/settings, default 3 ชม.
 *  2) เพดานเฉพาะ zone (zones.zone_daily_quota) — ซ้อนอยู่ภายในเพดาน global
 *     เช่น zone คาราโอเกะ = 1 ชม. → ใช้ 1 ชม.ที่นั่น เหลืออีก (global - 1) ชม. ไปใช้ zone อื่นได้
 *
 * นับรวม booking ทั้งที่เป็น leader และที่ join คนอื่น (join ก็ผูก user_id ใน bookings เหมือนกัน)
 * นับต่อเมื่อยังไม่ถูกยกเลิก (คนจองยกเลิกเอง/เจ้าหน้าที่ยกเลิก/ระบบยกเลิก คืนสิทธิ์ให้ทั้งหมดเหมือนกัน)
 * ไม่รวม booking ที่เจ้าหน้าที่จองแทน (source=staff) เพราะไม่ผูก bookings.user_id ของสมาชิกจริง
 */
class Quota
{
    public const DEFAULT_DAILY_LIMIT = 3;

    /** เพดานรวมทุก zone ต่อคนต่อวัน */
    public static function dailyLimit(): int
    {
        return (int) Setting::get('member_daily_quota_hours', (string) self::DEFAULT_DAILY_LIMIT);
    }

    /** ชม.ที่ใช้ไปแล้ววันนี้ — ไม่ส่ง $zoneId = นับรวมทุกโซน (global) */
    public static function usedHours(int $userId, string $date, ?int $zoneId = null): int
    {
        return (int) DB::table('bookings')
            ->join('booking_groups', 'bookings.group_id', '=', 'booking_groups.id')
            ->join('rooms', 'rooms.id', '=', 'booking_groups.room_id')
            ->where('bookings.user_id', $userId)
            ->where('booking_groups.date', $date)
            ->when($zoneId, fn($q) => $q->where('rooms.zone_id', $zoneId))
            ->whereIn('booking_groups.status', ['pending', 'waiting_confirm', 'confirmed'])
            ->whereNotIn('bookings.status', ['cancelled'])
            ->count();
    }

    /**
     * เช็คทั้ง 2 ชั้น ก่อนให้จอง/join เพิ่ม $requestHours ชม.
     * throw RuntimeException('zone_quota_exceeded' | 'daily_quota_exceeded') ถ้าเกิน
     */
    public static function assertCanBook(int $userId, string $date, int $zoneId, int $zoneQuota, int $requestHours): void
    {
        $zoneUsed = self::usedHours($userId, $date, $zoneId);
        if ($zoneUsed + $requestHours > $zoneQuota) {
            throw new \RuntimeException('zone_quota_exceeded');
        }

        $globalUsed = self::usedHours($userId, $date);
        if ($globalUsed + $requestHours > self::dailyLimit()) {
            throw new \RuntimeException('daily_quota_exceeded');
        }
    }

    /**
     * true ถ้า user มี booking ที่ยัง active อยู่แล้วในวันนี้ ตรงกับช่วงเวลาใดช่วงเวลาหนึ่งใน $timeIds
     * ไม่ว่าจะห้อง/โซนไหนก็ตาม — กันจองซ้อน (1 คน 2 ที่ เวลาเดียวกัน)
     */
    public static function hasTimeConflict(int $userId, string $date, array $timeIds): bool
    {
        if (empty($timeIds)) {
            return false;
        }

        return DB::table('bookings')
            ->join('booking_groups', 'bookings.group_id', '=', 'booking_groups.id')
            ->where('bookings.user_id', $userId)
            ->where('booking_groups.date', $date)
            ->whereIn('booking_groups.time_id', $timeIds)
            ->whereIn('booking_groups.status', ['pending', 'waiting_confirm', 'confirmed'])
            ->whereNotIn('bookings.status', ['cancelled'])
            ->exists();
    }
}
