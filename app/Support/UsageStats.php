<?php

namespace App\Support;

use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * สถิติการเข้าใช้งานรายเดือน แยกตาม location → zone (หน่วย: คน)
 *
 * นับ "คน" = bookings.status = checked_in, 1 คน × 1 ห้อง × 1 วัน = 1 (จองหลายชั่วโมงติดกันก็นับ 1)
 * staff booking (source=staff) ไม่มีแถว bookings จึงไม่ถูกนับ
 * ใช้ร่วมกันใน AdminStatsController (หน้า admin) และ Api\StatsController (API ภายนอก)
 */
class UsageStats
{
    public const COUNTED_STATUSES = ['checked_in'];

    private const THAI_MONTHS = [
        1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
    ];

    /**
     * @param  Carbon  $from  วันใดก็ได้ในเดือนเริ่ม
     * @param  Carbon  $to    วันใดก็ได้ในเดือนสุดท้าย
     */
    public static function monthly(Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfMonth();
        $end   = $to->copy()->endOfMonth();

        $counts = DB::table('bookings')
            ->join('booking_groups', 'booking_groups.id', '=', 'bookings.group_id')
            ->join('rooms', 'rooms.id', '=', 'booking_groups.room_id')
            ->whereIn('bookings.status', self::COUNTED_STATUSES)
            ->whereBetween('booking_groups.date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('ym', 'rooms.zone_id')
            ->select(
                DB::raw("DATE_FORMAT(booking_groups.date, '%Y-%m') as ym"),
                'rooms.zone_id',
                DB::raw('COUNT(DISTINCT bookings.user_id, booking_groups.room_id, booking_groups.date) as people'),
            )
            ->get()
            ->groupBy('ym')
            ->map(fn ($rows) => $rows->pluck('people', 'zone_id')->map(fn ($v) => (int) $v));

        $months = [];
        for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
            $months[] = [
                'key'   => $m->format('Y-m'),
                'label' => self::monthLabel($m),
            ];
        }

        $locations = Location::with(['zones' => fn ($q) => $q->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(function ($loc) use ($months, $counts) {
                $zones = $loc->zones->map(fn ($z) => ['id' => $z->id, 'title' => $z->title, 'icon' => $z->icon])->values();

                $rows = collect($months)->map(function ($mo) use ($zones, $counts) {
                    $byZone = $zones->mapWithKeys(fn ($z) => [$z['id'] => $counts[$mo['key']][$z['id']] ?? 0]);
                    return [
                        'month' => $mo['key'],
                        'label' => $mo['label'],
                        'zones' => $byZone,
                        'total' => $byZone->sum(),
                    ];
                })->values();

                return [
                    'id'    => $loc->id,
                    'title' => $loc->title,
                    'zones' => $zones,
                    'rows'  => $rows,
                    'total' => $rows->sum('total'),
                ];
            })
            ->filter(fn ($loc) => $loc['zones']->isNotEmpty())
            ->values();

        return [
            'from'      => $start->format('Y-m'),
            'to'        => $end->format('Y-m'),
            'months'    => $months,
            'unit'      => 'คน',
            'locations' => $locations,
            'total'     => $locations->sum('total'),
        ];
    }

    /** "ตุลาคม 2569" */
    public static function monthLabel(Carbon $m): string
    {
        return self::THAI_MONTHS[$m->month] . ' ' . ($m->year + 543);
    }
}
