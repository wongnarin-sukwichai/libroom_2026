<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * ช่วงเวลาที่เปิดให้ "กดจอง" (global ทั้งระบบ)
 *
 * คนละชั้นกับ times/service hours (ช่วงเวลาให้บริการห้อง)
 * ใช้กันผู้ใช้มาเฝ้ากดจองตอนกลางดึก — ตัดสินจากเวลา server (Asia/Bangkok) เท่านั้น
 */
class BookingWindow
{
    public const TZ = 'Asia/Bangkok';

    /** สถานะปัจจุบันของหน้าต่างการจอง (ใช้ทั้ง backend guard และส่งให้ frontend) */
    public static function status(): array
    {
        $enabled = Setting::get('booking_window_enabled', '1') === '1';
        $open    = Setting::get('booking_open_time', '06:00');
        $close   = Setting::get('booking_close_time', '19:00');

        $now     = Carbon::now(self::TZ);
        $openAt  = self::todayAt($now, $open);
        $closeAt = self::todayAt($now, $close);

        $isOpenNow = ! $enabled || ($now->gte($openAt) && $now->lt($closeAt));

        $opensInSeconds = null;
        if ($enabled && ! $isOpenNow && $now->lt($openAt)) {
            $opensInSeconds = (int) $now->diffInSeconds($openAt);
        }

        return [
            'enabled'          => $enabled,
            'open'             => $open,
            'close'            => $close,
            'is_open_now'      => $isOpenNow,
            'opens_in_seconds' => $opensInSeconds,
            'server_time'      => $now->format('H:i'),
            'message'          => $isOpenNow
                ? 'ระบบเปิดให้จอง'
                : "ขณะนี้อยู่นอกเวลาทำการจอง — ระบบเปิดให้จองเวลา {$open} – {$close} น.",
        ];
    }

    /** true ถ้าตอนนี้จองได้ */
    public static function isOpenNow(): bool
    {
        return self::status()['is_open_now'];
    }

    private static function todayAt(Carbon $ref, string $hhmm): Carbon
    {
        [$h, $m] = array_pad(explode(':', $hhmm), 2, '0');
        return $ref->copy()->setTime((int) $h, (int) $m, 0);
    }
}
