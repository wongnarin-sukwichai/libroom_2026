<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function slots(Room $room, Request $request)
    {
        $date      = $request->validate(['date' => 'required|date'])['date'];
        $isWeekend = Carbon::parse($date)->isWeekend();

        $zone         = $room->zone;
        $configId     = $isWeekend ? $zone->time_weekend : $zone->time_weekday;
        $config       = Time::findOrFail($configId);

        // Generate 1-hour slots from config start/end (e.g. '09:00'–'19:00')
        $startHour = $config->start_hour;
        $endHour   = $config->end_hour;
        $times     = collect();
        for ($h = $startHour; $h < $endHour; $h++) {
            $times->push([
                'id'    => $h,
                'start' => sprintf('%02d:00', $h),
                'end'   => sprintf('%02d:00', $h + 1),
                'title' => sprintf('%02d:00 – %02d:00 น.', $h, $h + 1),
            ]);
        }

        // time_id in booking_groups stores the hour number (9, 10, 11...)
        $bookedIds = BookingGroup::where('room_id', $room->id)
            ->where('date', $date)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->pluck('time_id')
            ->toArray();

        // นับชั่วโมงที่ member จองไปแล้ววันนี้รวมทุก zone (global quota)
        $globalQuota = 3;
        $usedHours   = 0;
        if (Auth::check()) {
            $usedHours = BookingGroup::where('lead_user_id', Auth::id())
                ->where('date', $date)
                ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                ->count();
        }

        return response()->json([
            'times'       => $times,
            'booked_ids'  => $bookedIds,
            'used_hours'  => $usedHours,
            'daily_quota' => $globalQuota,
        ]);
    }

    public function myBookings()
    {
        $member = Auth::user();

        $groups = BookingGroup::where('lead_user_id', $member->id)
            ->with([
                'room' => fn($q) => $q->select('id', 'zone_id', 'title', 'confirm_type'),
                'room.zone' => fn($q) => $q->select('id', 'loc_id', 'title'),
                'room.zone.location' => fn($q) => $q->select('id', 'title'),
            ])
            ->orderByDesc('date')
            ->orderByDesc('time_id')
            ->get()
            ->map(fn($g) => [
                'id'           => $g->id,
                'date'         => $g->date->format('Y-m-d'),
                'time_id'      => $g->time_id,
                'time_label'   => sprintf('%02d:00 – %02d:00 น.', $g->time_id, $g->time_id + 1),
                'status'       => $g->status,
                'confirm_type' => $g->room?->confirm_type,
                'room_title'   => $g->room?->title,
                'zone_title'   => $g->room?->zone?->title,
                'loc_title'    => $g->room?->zone?->location?->title,
                'can_cancel'   => in_array($g->status, ['pending', 'confirmed'])
                                  && $g->date->gte(Carbon::today()),
            ]);

        return inertia('MyBookings', ['bookings' => $groups]);
    }

    public function cancel(BookingGroup $group)
    {
        $member = Auth::user();

        if ($group->lead_user_id !== $member->id) {
            return response()->json(['message' => 'ไม่มีสิทธิ์ยกเลิกการจองนี้'], 403);
        }

        if (!in_array($group->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'ไม่สามารถยกเลิกการจองที่มีสถานะนี้ได้'], 422);
        }

        if (Carbon::parse($group->date)->lt(Carbon::today())) {
            return response()->json(['message' => 'ไม่สามารถยกเลิกการจองที่ผ่านมาแล้วได้'], 422);
        }

        DB::transaction(function () use ($group) {
            $group->update(['status' => 'cancelled', 'cancelled_at' => Carbon::now()]);
            $group->bookings()->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'ยกเลิกการจองสำเร็จ']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id'    => 'required|integer|exists:rooms,id',
            'time_ids'   => 'required|array|min:1',
            'time_ids.*' => 'integer',
        ]);

        $member  = Auth::user();
        $today   = Carbon::today();
        $date    = $today->format('Y-m-d');
        $timeIds = collect($data['time_ids'])->sort()->values()->all();

        // ตรวจสอบวันหยุด
        if (Holiday::where('d', (string)$today->day)->where('m', (string)$today->month)->exists()) {
            return response()->json(['message' => 'งดให้บริการเนื่องในวันหยุด'], 422);
        }

        $room = Room::findOrFail($data['room_id']);

        if ($room->status === '1') {
            return response()->json(['message' => 'ห้องนี้ไม่พร้อมให้บริการ'], 422);
        }

        // ตรวจสอบ slot ต่อเนื่อง
        for ($i = 1; $i < count($timeIds); $i++) {
            if ($timeIds[$i] !== $timeIds[$i - 1] + 1) {
                return response()->json(['message' => 'ต้องเลือกช่วงเวลาที่ต่อเนื่องกันเท่านั้น'], 422);
            }
        }

        $zone = $room->zone;

        try {
            $groups = DB::transaction(function () use ($data, $timeIds, $member, $date, $room, $zone) {

                // เช็ค slot ซ้ำและ quota พร้อมกัน (lock)
                foreach ($timeIds as $timeId) {
                    $taken = BookingGroup::where('room_id', $data['room_id'])
                        ->where('date', $date)
                        ->where('time_id', $timeId)
                        ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                        ->lockForUpdate()
                        ->exists();

                    if ($taken) throw new \Exception('slot_taken');
                }

                $globalQuota = 3;
                $usedHours   = BookingGroup::where('lead_user_id', $member->id)
                    ->where('date', $date)
                    ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                    ->count();

                if ($usedHours + count($timeIds) > $globalQuota) {
                    throw new \Exception('quota_exceeded');
                }

                $isAuto        = $room->confirm_type === 'auto';
                $groupStatus   = $isAuto ? 'confirmed' : 'pending';
                $bookingStatus = $isAuto ? 'confirmed' : 'pending';
                $groups        = [];

                foreach ($timeIds as $timeId) {
                    $group = BookingGroup::create([
                        'room_id'          => $data['room_id'],
                        'date'             => $date,
                        'time_id'          => $timeId,
                        'lead_user_id'     => $member->id,
                        'status'           => $groupStatus,
                        'join_token'       => Str::random(32),
                        'token_expires_at' => Carbon::now()->addMinutes(15),
                    ]);

                    Booking::create([
                        'group_id' => $group->id,
                        'user_id'  => $member->id,
                        'status'   => $bookingStatus,
                    ]);

                    $groups[] = $group;
                }

                return $groups;
            });

            return response()->json([
                'message'      => 'จองสำเร็จ',
                'count'        => count($groups),
                'status'       => $groups[0]->status,
                'confirm_type' => $room->confirm_type,
            ]);

        } catch (\Exception $e) {
            $msg = match ($e->getMessage()) {
                'slot_taken'     => 'เสียใจด้วย ช่วงเวลานี้ถูกจองไปแล้ว',
                'quota_exceeded' => 'เกินโควต้าการจอง (3 ชม./วัน รวมทุกโซน)',
                default          => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
            };
            return response()->json(['message' => $msg], 422);
        }
    }
}
