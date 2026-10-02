<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\BookingGroup;
use App\Models\Holiday;
use App\Models\Location;
use App\Models\ServiceHour;
use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource. (โฉมใหม่ — เดิมทดลองที่ /welcome-test แล้วโปรโมทมาแทนหน้าแรกจริง)
     */
    public function index()
    {
        return $this->renderHome('Welcome');
    }

    /** หน้าทดลอง — ตอนนี้เนื้อหาเหมือนหน้าแรกทุกอย่าง เก็บ route ไว้เผื่อเทียบ/ทดลองเวอร์ชันถัดไป */
    public function indexTest()
    {
        return $this->renderHome('WelcomeTest');
    }

    private function renderHome(string $component)
    {
        $today          = Carbon::now('Asia/Bangkok');
        $todayIsHoliday = Holiday::where('d', (string)$today->day)
            ->where('m', (string)$today->month)
            ->exists();

        $banners = Banner::where('status', '0')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'image_path'])
            ->map(fn ($b) => ['image' => asset('imgs/banner/' . $b->image_path)])
            ->values();

        return inertia($component, [
            'locations'      => $this->loadLocationsTree(),
            'todayIsHoliday' => $todayIsHoliday,
            'todayDate'      => $today->format('Y-m-d'),
            'roomStatusPool' => $this->publicRoomStatusPool($today),
            'banners'        => $banners,
        ]);
    }

    /** โครงสร้าง location→zone→room + อุปกรณ์ (ใช้ร่วมกันระหว่าง index / indexTest) */
    private function loadLocationsTree()
    {
        $data = Location::select('id', 'pic', 'title', 'title_eng', 'detail', 'status')
            ->with(['zones' => fn($q) => $q
                ->select('id', 'loc_id', 'pic', 'icon', 'title', 'title_eng', 'detail', 'capacity', 'tool', 'zone_daily_quota', 'time_weekday', 'time_weekend', 'status', 'scan_only')
                ->with([
                    'tools.tool' => fn($tt) => $tt->select('id', 'name', 'icon'),
                    'rooms' => fn($r) => $r
                        ->select('id', 'zone_id', 'title', 'detail', 'pic', 'confirm_type', 'access_control', 'status')
                        ->with(['tools.tool' => fn($tt) => $tt->select('id', 'name', 'icon')]),
                ])
            ])
            ->get();

        // zone.equipment = คลังอุปกรณ์ภายในโซน · room.equipment = เฉพาะที่ห้องกำหนดเอง
        $data->each(fn($loc) => $loc->zones->each(function ($zone) {
            $zone->rooms->each(function ($room) {
                $equipment = $room->effectiveTools();
                $room->unsetRelation('tools');
                $room->setAttribute('equipment', $equipment);
            });
            $zone->setAttribute('equipment', $zone->tools
                ->map(fn($zt) => [
                    'tool_id'  => $zt->tool_id,
                    'name'     => $zt->tool?->name,
                    'icon'     => $zt->tool?->icon,
                    'quantity' => (int) $zt->quantity,
                ])
                ->filter(fn($t) => $t['name'])
                ->values());
            $zone->unsetRelation('tools');
        }));

        return $data;
    }

    /**
     * กลุ่มห้องตัวอย่าง (1 ห้อง/โซน ทั้งระบบ) พร้อมสถานะ "ตอนนี้" (ชั่วโมงปัจจุบัน)
     * และตาราง cells รายชั่วโมงทั้งวัน (ไว้ทำเวอร์ชันตารางเทียบกัน) — ไม่โชว์ชื่อผู้จอง แค่ ว่าง/มีคนใช้/ปิด
     */
    private function publicRoomStatusPool(Carbon $now): array
    {
        $isWeekend = $now->isWeekend();
        $curHour   = (int) $now->format('G');
        $colStart  = 9;
        $colEnd    = 21; // ครอบคลุม time config ทุกแบบ (09:00–21:00)

        $pool = Location::where('status', '0')
            ->with(['zones' => fn($q) => $q->where('status', '0')->orderBy('id')
                ->with(['rooms' => fn($r) => $r->orderBy('id')])])
            ->get()
            ->flatMap(fn($loc) => $loc->zones->map(function ($zone) use ($loc) {
                $room = $zone->rooms->first(fn($r) => $r->status === '0') ?? $zone->rooms->first();
                return $room ? ['room' => $room, 'zone' => $zone, 'loc' => $loc] : null;
            })->filter())
            ->values();

        if ($pool->isEmpty()) return ['columns' => [], 'rooms' => []];

        $roomIds    = $pool->pluck('room.id')->all();
        $bookedNow  = BookingGroup::whereIn('room_id', $roomIds)
            ->where('date', $now->toDateString())
            ->where('time_id', $curHour)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->pluck('room_id')
            ->all();
        $bookedAllDay = BookingGroup::whereIn('room_id', $roomIds)
            ->where('date', $now->toDateString())
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->get()
            ->groupBy('room_id')
            ->map(fn($rows) => $rows->pluck('time_id')->all());

        $timesCache = [];
        $getTime = function (?int $id) use (&$timesCache) {
            if (!$id) return null;
            return $timesCache[$id] ??= Time::find($id);
        };

        $columns = [];
        for ($h = $colStart; $h < $colEnd; $h++) $columns[] = sprintf('%02d:00', $h);

        $rooms = $pool->map(function ($r) use ($colStart, $colEnd, $curHour, $bookedNow, $bookedAllDay, $getTime, $isWeekend) {
            $room   = $r['room'];
            $zone   = $r['zone'];
            $config = $getTime($isWeekend ? $zone->time_weekend : $zone->time_weekday);
            $isOpen = $config && $curHour >= $config->start_hour && $curHour < $config->end_hour;

            $state = 'free';
            if ($room->status === '1' || !$isOpen) {
                $state = 'closed';
            } elseif (in_array($room->id, $bookedNow)) {
                $state = 'booked';
            }

            $bookedHours = $bookedAllDay[$room->id] ?? [];
            $cells = [];
            for ($h = $colStart; $h < $colEnd; $h++) {
                if ($room->status === '1') {
                    $cells[] = 'closed';
                } elseif ($config && ($h < $config->start_hour || $h >= $config->end_hour)) {
                    $cells[] = 'closed';
                } elseif ($h < $curHour) {
                    $cells[] = 'closed'; // เวลาที่ผ่านไปแล้ววันนี้
                } elseif (in_array($h, $bookedHours)) {
                    $cells[] = 'booked';
                } else {
                    $cells[] = 'free';
                }
            }

            return [
                'room_title' => $room->title,
                'zone_title' => $zone->title,
                'loc_title'  => $r['loc']->title,
                'zone_pic'   => $zone->pic,
                'state'      => $state,
                'cells'      => $cells,
            ];
        })->values()->all();

        return ['columns' => $columns, 'rooms' => $rooms];
    }

    public function forBooking()
    {
        return Location::select('id', 'title', 'title_eng', 'status')
            ->where('status', '0')
            ->with(['zones' => fn($q) => $q
                ->select('id', 'loc_id', 'title', 'title_eng', 'status')
                ->where('status', '0')
                ->with(['rooms' => fn($r) => $r
                    ->select('id', 'zone_id', 'title', 'detail', 'confirm_type', 'status')
                    ->where('confirm_type', 'manual')
                    ->where('status', '0')
                ])
            ])
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
