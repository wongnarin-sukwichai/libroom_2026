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

        $zone     = $room->zone;
        $config   = Time::findOrFail($isWeekend ? $zone->time_weekend : $zone->time_weekday);

        $times = collect();
        for ($h = $config->start_hour; $h < $config->end_hour; $h++) {
            $times->push([
                'id'    => $h,
                'start' => sprintf('%02d:00', $h),
                'end'   => sprintf('%02d:00', $h + 1),
                'title' => sprintf('%02d:00 – %02d:00 น.', $h, $h + 1),
            ]);
        }

        $bookedIds = BookingGroup::where('room_id', $room->id)
            ->where('date', $date)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->pluck('time_id')
            ->toArray();

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
                'room'               => fn($q) => $q->select('id', 'zone_id', 'title', 'confirm_type'),
                'room.zone'          => fn($q) => $q->select('id', 'loc_id', 'title'),
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
                'member_count' => $g->bookings()->count(),
                'min_capacity' => $g->room?->zone?->min_capacity ?? 1,
                'join_url'     => $g->status === 'pending' && $g->room?->confirm_type === 'manual'
                                  ? route('booking.join', $g->join_token)
                                  : null,
                'can_cancel'   => in_array($g->status, ['pending', 'waiting_confirm'])
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

        if (!in_array($group->status, ['pending', 'waiting_confirm'])) {
            return response()->json(['message' => 'ไม่สามารถยกเลิกการจองที่มีสถานะนี้ได้'], 422);
        }

        if (Carbon::parse($group->date)->lt(Carbon::today())) {
            return response()->json(['message' => 'ไม่สามารถยกเลิกการจองที่ผ่านมาแล้วได้'], 422);
        }

        DB::transaction(function () use ($group) {
            // ยกเลิกทุก slot ในเซสชันเดียวกัน (room+date+lead)
            $siblings = BookingGroup::where('lead_user_id', $group->lead_user_id)
                ->where('room_id', $group->room_id)
                ->where('date', $group->date)
                ->whereIn('status', ['pending', 'waiting_confirm'])
                ->get();

            foreach ($siblings as $g) {
                $g->update(['status' => 'cancelled', 'cancelled_at' => Carbon::now()]);
                $g->bookings()->update(['status' => 'cancelled']);
            }
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

        if (Holiday::where('d', (string)$today->day)->where('m', (string)$today->month)->exists()) {
            return response()->json(['message' => 'งดให้บริการเนื่องในวันหยุด'], 422);
        }

        $room = Room::with('zone')->findOrFail($data['room_id']);

        if ($room->status === '1') {
            return response()->json(['message' => 'ห้องนี้ไม่พร้อมให้บริการ'], 422);
        }

        for ($i = 1; $i < count($timeIds); $i++) {
            if ($timeIds[$i] !== $timeIds[$i - 1] + 1) {
                return response()->json(['message' => 'ต้องเลือกช่วงเวลาที่ต่อเนื่องกันเท่านั้น'], 422);
            }
        }

        $zone = $room->zone;

        try {
            $result = DB::transaction(function () use ($data, $timeIds, $member, $date, $room, $zone) {

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

                $isAuto      = $room->confirm_type === 'auto';
                $minCapacity = $zone->min_capacity ?? 1;

                // auto → confirmed ทันที
                // manual + leader คนเดียวครบ min_capacity → waiting_confirm
                // manual + ยังต้องการเพื่อน → pending
                $groupStatus = $isAuto
                    ? 'confirmed'
                    : ($minCapacity <= 1 ? 'waiting_confirm' : 'pending');

                $groups    = [];
                $shareToken = Str::random(32);

                foreach ($timeIds as $timeId) {
                    $group = BookingGroup::create([
                        'room_id'          => $data['room_id'],
                        'date'             => $date,
                        'time_id'          => $timeId,
                        'lead_user_id'     => $member->id,
                        'status'           => $groupStatus,
                        'join_token'       => $timeId === $timeIds[0]
                                             ? $shareToken
                                             : Str::random(32),
                        'token_expires_at' => Carbon::now()->addMinutes(15),
                    ]);

                    Booking::create([
                        'group_id' => $group->id,
                        'user_id'  => $member->id,
                        'status'   => $groupStatus === 'confirmed' ? 'confirmed' : 'pending',
                    ]);

                    $groups[] = $group;
                }

                return [
                    'groups'      => $groups,
                    'share_token' => $shareToken,
                    'group_status'=> $groupStatus,
                ];
            });

            $resp = [
                'message'      => 'จองสำเร็จ',
                'count'        => count($result['groups']),
                'status'       => $result['group_status'],
                'confirm_type' => $room->confirm_type,
            ];

            // manual ที่ยังต้องการเพื่อน → คืน join_url
            if ($room->confirm_type === 'manual' && $result['group_status'] === 'pending') {
                $resp['join_url']      = route('booking.join', $result['share_token']);
                $resp['min_capacity']  = $zone->min_capacity ?? 1;
                $resp['member_count']  = 1;
            }

            return response()->json($resp);

        } catch (\Exception $e) {
            $msg = match ($e->getMessage()) {
                'slot_taken'     => 'เสียใจด้วย ช่วงเวลานี้ถูกจองไปแล้ว',
                'quota_exceeded' => 'เกินโควต้าการจอง (3 ชม./วัน รวมทุกโซน)',
                default          => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
            };
            return response()->json(['message' => $msg], 422);
        }
    }

    // ─── Join Flow ────────────────────────────────────────────────────────────

    public function joinPage(string $token)
    {
        $group = BookingGroup::where('join_token', $token)
            ->with([
                'room'               => fn($q) => $q->select('id', 'zone_id', 'title', 'confirm_type'),
                'room.zone'          => fn($q) => $q->select('id', 'loc_id', 'title', 'min_capacity'),
                'room.zone.location' => fn($q) => $q->select('id', 'title'),
                'lead'               => fn($q) => $q->select('id', 'name'),
            ])
            ->first();

        if (!$group) {
            return inertia('Join', ['error' => 'ลิงก์ไม่ถูกต้องหรือหมดอายุแล้ว']);
        }

        if ($group->status !== 'pending') {
            return inertia('Join', ['error' => 'การจองนี้ไม่อยู่ในสถานะรับสมาชิกแล้ว']);
        }

        if (Carbon::now()->gt($group->token_expires_at)) {
            return inertia('Join', ['error' => 'ลิงก์นี้หมดอายุแล้ว']);
        }

        $member      = Auth::user();
        $minCapacity = $group->room?->zone?->min_capacity ?? 1;

        // นับสมาชิกที่ join แล้ว (รวมทุก slot ในเซสชัน)
        $memberCount = Booking::where('group_id', $group->id)->count();

        // เช็คว่า member นี้ join แล้วหรือยัง
        $alreadyJoined = Booking::whereIn(
            'group_id',
            BookingGroup::where('lead_user_id', $group->lead_user_id)
                ->where('room_id', $group->room_id)
                ->where('date', $group->date)
                ->pluck('id')
        )->where('user_id', $member->id)->exists();

        return inertia('Join', [
            'group' => [
                'token'        => $token,
                'date'         => $group->date->format('Y-m-d'),
                'time_label'   => sprintf('%02d:00 น. เป็นต้นไป', $group->time_id),
                'room_title'   => $group->room?->title,
                'zone_title'   => $group->room?->zone?->title,
                'loc_title'    => $group->room?->zone?->location?->title,
                'lead_name'    => $group->lead?->name,
                'member_count' => $memberCount,
                'min_capacity' => $minCapacity,
            ],
            'already_joined' => $alreadyJoined,
        ]);
    }

    public function join(string $token)
    {
        $member = Auth::user();

        $group = BookingGroup::where('join_token', $token)
            ->lockForUpdate()
            ->first();

        if (!$group) {
            return response()->json(['message' => 'ลิงก์ไม่ถูกต้อง'], 404);
        }

        if ($group->status !== 'pending') {
            return response()->json(['message' => 'การจองนี้ไม่อยู่ในสถานะรับสมาชิกแล้ว'], 422);
        }

        if (Carbon::now()->gt($group->token_expires_at)) {
            return response()->json(['message' => 'ลิงก์นี้หมดอายุแล้ว'], 422);
        }

        try {
            DB::transaction(function () use ($group, $member) {
                $zone        = $group->room->zone;
                $minCapacity = $zone->min_capacity ?? 1;

                // หา sibling groups (slot อื่นในเซสชันเดียวกัน)
                $siblings = BookingGroup::where('lead_user_id', $group->lead_user_id)
                    ->where('room_id', $group->room_id)
                    ->where('date', $group->date)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->get();

                // เช็คว่า member join แล้วหรือยัง
                $alreadyJoined = Booking::whereIn('group_id', $siblings->pluck('id'))
                    ->where('user_id', $member->id)
                    ->exists();

                if ($alreadyJoined) throw new \Exception('already_joined');

                if ($member->id === $group->lead_user_id) throw new \Exception('already_joined');

                // join ทุก slot ในเซสชัน
                foreach ($siblings as $g) {
                    Booking::create([
                        'group_id' => $g->id,
                        'user_id'  => $member->id,
                        'status'   => 'pending',
                    ]);
                }

                // นับสมาชิกทั้งหมดใน group นี้ (หลัง join)
                $totalMembers = Booking::where('group_id', $group->id)->count();

                // ครบ min_capacity → เปลี่ยนทุก slot เป็น waiting_confirm
                if ($totalMembers >= $minCapacity) {
                    foreach ($siblings as $g) {
                        $g->update(['status' => 'waiting_confirm']);
                    }
                }
            });

            return response()->json(['message' => 'เข้าร่วมสำเร็จ']);

        } catch (\Exception $e) {
            $msg = match ($e->getMessage()) {
                'already_joined' => 'คุณอยู่ในกลุ่มนี้แล้ว',
                default          => 'เกิดข้อผิดพลาด กรุณาลองใหม่',
            };
            return response()->json(['message' => $msg], 422);
        }
    }
}
