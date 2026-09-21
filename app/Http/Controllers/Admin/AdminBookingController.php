<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        // มีวันที่ = กรองวันนั้น, ไม่มี = ทุกวัน (ใช้กับ tab ยืนยันแล้ว/ยกเลิก เพื่อดูย้อนหลัง)
        $date = $request->filled('date') ? $request->date : null;

        // แยกตาม tab: รอดำเนินการ / จองล่วงหน้า / ยืนยันแล้ว / ยกเลิก
        $statusMap = [
            'pending'   => ['pending', 'waiting_confirm'],
            'upcoming'  => ['confirmed'],
            'confirmed' => ['confirmed'],
            'cancelled' => ['cancelled'],
        ];
        $tab      = $request->get('tab', 'pending');
        $statuses = $statusMap[$tab] ?? $statusMap['pending'];

        // จองล่วงหน้า = เจ้าหน้าที่จองไว้ (admin_id != null) และวันที่ยังมาไม่ถึง (date > วันนี้)
        $isUpcoming    = $tab === 'upcoming';
        $upcomingAfter = $isUpcoming ? Carbon::now('Asia/Bangkok')->toDateString() : null;

        $search = trim((string) $request->get('search', ''));

        $query = BookingGroup::with([
            'room'               => fn($q) => $q->select('id', 'zone_id', 'title', 'confirm_type', 'access_control'),
            'room.zone'          => fn($q) => $q->select('id', 'loc_id', 'title'),
            'room.zone.location' => fn($q) => $q->select('id', 'title'),
            'lead'               => fn($q) => $q->select('id', 'name', 'email', 'type'),
            'admin'              => fn($q) => $q->select('id', 'name', 'email'),
            'bookings'           => fn($q) => $q->select('id', 'group_id', 'status'),
        ])
        ->when($date, fn($q) => $q->where('date', $date))
        ->when($isUpcoming, fn($q) => $q
            ->whereNotNull('admin_id')
            ->where('date', '>', $upcomingAfter))
        ->whereIn('status', $statuses)
        ->when($search !== '', function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $like = "%{$search}%";
                $sub->whereHas('lead',  fn($l) => $l->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orWhereHas('admin', fn($a) => $a->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orWhereHas('room',  fn($r) => $r->where('title', 'like', $like));
            });
        })
        ->orderByRaw("FIELD(status, 'pending', 'waiting_confirm', 'confirmed', 'cancelled')")
        ->orderBy('date')
        ->orderBy('lead_user_id')
        ->orderBy('room_id')
        ->orderBy('time_id');

        $all      = $query->get();
        $sessions = $this->groupIntoSessions($all);

        // Manual pagination
        $perPage  = 10;
        $page     = max(1, (int)$request->get('page', 1));
        $total    = count($sessions);
        $items    = array_slice($sessions, ($page - 1) * $perPage, $perPage);

        // badge คิวงาน = pending + waiting_confirm ของห้อง manual (ทุกวัน)
        $pendingCount = BookingGroup::whereIn('status', ['pending', 'waiting_confirm'])
            ->whereHas('room', fn($q) => $q->where('confirm_type', 'manual'))
            ->count();

        return response()->json([
            'data'          => array_values($items),
            'current_page'  => $page,
            'last_page'     => max(1, (int)ceil($total / $perPage)),
            'total'         => $total,
            'from'          => $total ? ($page - 1) * $perPage + 1 : 0,
            'to'            => min($page * $perPage, $total),
            'pending_count' => $pendingCount,
        ]);
    }

    private function groupIntoSessions($groups): array
    {
        $sessions = [];
        $current  = null;

        foreach ($groups as $g) {
            $sameSession = $current
                && $current['lead_user_id'] === $g->lead_user_id
                && $current['room_id']      === $g->room_id
                && $current['status']       === $g->status
                && $current['date']         === $g->date->format('Y-m-d')
                && $g->time_id === $current['last_time'] + 1;

            if ($sameSession) {
                $current['ids'][]     = $g->id;
                $current['last_time'] = $g->time_id;
                $current['end_hour']  = $g->time_id + 1;
                $current['hours']++;
                $current['bk_statuses'] = array_merge($current['bk_statuses'], $g->bookings->pluck('status')->all());
            } else {
                if ($current) $sessions[] = $this->formatSession($current);
                $current = [
                    'lead_user_id' => $g->lead_user_id,
                    'room_id'      => $g->room_id,
                    'status'       => $g->status,
                    'last_time'    => $g->time_id,
                    'ids'          => [$g->id],
                    'date'         => $g->date->format('Y-m-d'),
                    'start_hour'   => $g->time_id,
                    'end_hour'     => $g->time_id + 1,
                    'hours'        => 1,
                    'bk_statuses'   => $g->bookings->pluck('status')->all(),
                    'confirm_type'  => $g->room?->confirm_type,
                    'access_control' => $g->room?->access_control,
                    'room_title'   => $g->room?->title,
                    'zone_title'   => $g->room?->zone?->title,
                    'loc_title'    => $g->room?->zone?->location?->title,
                    'member_name'  => $g->lead?->name  ?? ($g->admin ? $g->admin->name  . ' (เจ้าหน้าที่)' : null),
                    'member_email' => $g->lead?->email ?? $g->admin?->email,
                    'cancelled_by'  => $g->cancelled_by,
                    'cancel_reason' => $g->cancel_reason,
                ];
            }
        }
        if ($current) $sessions[] = $this->formatSession($current);

        return $sessions;
    }

    private function formatSession(array $s): array
    {
        $pad = fn($h) => sprintf('%02d:00', $h);

        $bk        = collect($s['bk_statuses'] ?? [])->reject(fn($st) => $st === 'cancelled');
        $checkedIn = $bk->isNotEmpty() && $bk->every(fn($st) => $st === 'checked_in');

        return [
            'ids'            => $s['ids'],
            'date'           => $s['date'],
            'time_label'     => $pad($s['start_hour']) . ' – ' . $pad($s['end_hour']) . ' น.',
            'hours'          => $s['hours'],
            'status'         => $s['status'],
            'confirm_type'   => $s['confirm_type'],
            'access_control' => $s['access_control'] ?? '0',
            'checked_in'     => $checkedIn,
            'room_title'     => $s['room_title'],
            'zone_title'     => $s['zone_title'],
            'loc_title'      => $s['loc_title'],
            'member_name'    => $s['member_name'],
            'member_email'   => $s['member_email'],
            'cancelled_by'   => $s['cancelled_by']  ?? null,
            'cancel_reason'  => $s['cancel_reason'] ?? null,
        ];
    }

    // ─── ผังห้อง (board view) ─────────────────────────────────────────────
    /** ตารางเวลาของห้องเดียว + รายชื่อผู้จองในแต่ละช่อง */
    public function roomDay(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'date'    => 'required|date',
        ]);

        $room = Room::with([
            'zone'          => fn($q) => $q->select('id', 'loc_id', 'title', 'time_weekday', 'time_weekend'),
            'zone.location' => fn($q) => $q->select('id', 'title'),
        ])->findOrFail($data['room_id']);

        $isWeekend = Carbon::parse($data['date'])->isWeekend();
        $config    = Time::findOrFail($isWeekend ? $room->zone->time_weekend : $room->zone->time_weekday);

        // โครงช่องเวลา
        $slots = [];
        for ($h = $config->start_hour; $h < $config->end_hour; $h++) {
            $slots[$h] = [
                'hour'   => $h,
                'label'  => sprintf('%02d:00 – %02d:00 น.', $h, $h + 1),
                'groups' => [],
            ];
        }

        $groups = BookingGroup::with([
                'lead'            => fn($q) => $q->select('id', 'name', 'email'),
                'admin'           => fn($q) => $q->select('id', 'name'),
                'bookings'        => fn($q) => $q->select('id', 'group_id', 'user_id', 'status'),
                'bookings.member' => fn($q) => $q->select('id', 'name'),
            ])
            ->where('room_id', $room->id)
            ->where('date', $data['date'])
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->orderBy('lead_user_id')
            ->orderBy('admin_id')
            ->orderBy('status')
            ->orderBy('time_id')
            ->get();

        // จับ session: lead/admin + status เดียวกัน + time_id ต่อเนื่อง
        $sessions = [];
        $cur      = null;
        $keyOf    = fn($g) => ($g->lead_user_id ?? 'a' . $g->admin_id) . '|' . $g->status;

        foreach ($groups as $g) {
            if ($cur && $cur['key'] === $keyOf($g) && $g->time_id === $cur['last'] + 1) {
                $cur['ids'][]  = $g->id;
                $cur['last']   = $g->time_id;
                $cur['end']    = $g->time_id + 1;
                $cur['hours']++;
            } else {
                if ($cur) $sessions[] = $cur;
                $occ = $g->bookings->reject(fn($b) => $b->status === 'cancelled')->map(fn($b) => [
                    'name'   => $b->member?->name ?? '—',
                    'status' => $b->status,
                ])->values()->all();

                $cur = [
                    'key'        => $keyOf($g),
                    'ids'        => [$g->id],
                    'last'       => $g->time_id,
                    'start'      => $g->time_id,
                    'end'        => $g->time_id + 1,
                    'hours'      => 1,
                    'status'     => $g->status,
                    'source'     => $g->source,
                    'lead_name'  => $g->lead?->name ?? ($g->admin ? $g->admin->name . ' (เจ้าหน้าที่)' : '—'),
                    'lead_email' => $g->lead?->email,
                    'by_staff'   => $g->lead_user_id === null,
                    'occupants'  => $occ,
                ];
            }
        }
        if ($cur) $sessions[] = $cur;

        foreach ($sessions as $s) {
            $bk         = collect($s['occupants']);
            $checkedIn  = $bk->isNotEmpty() && $bk->every(fn($o) => $o['status'] === 'checked_in');
            $payload    = [
                'ids'        => $s['ids'],
                'status'     => $s['status'],
                'source'     => $s['source'] ?? 'web',
                'start_hour' => $s['start'],
                'end_hour'   => $s['end'],
                'hours'      => $s['hours'],
                'time_label' => sprintf('%02d:00 – %02d:00 น.', $s['start'], $s['end']),
                'lead_name'  => $s['lead_name'],
                'lead_email' => $s['lead_email'],
                'by_staff'   => $s['by_staff'],
                'occupants'  => $s['occupants'],
                'checked_in' => $checkedIn,
            ];
            for ($h = $s['start']; $h < $s['end']; $h++) {
                if (!isset($slots[$h])) continue;
                $slots[$h]['groups'][] = $payload + ['is_continuation' => $h !== $s['start']];
            }
        }

        return response()->json([
            'room' => [
                'id'             => $room->id,
                'title'          => $room->title,
                'zone_title'     => $room->zone?->title,
                'loc_title'      => $room->zone?->location?->title,
                'confirm_type'   => $room->confirm_type,
                'access_control' => $room->access_control,
            ],
            'date'       => $data['date'],
            'is_weekend' => $isWeekend,
            'slots'      => array_values($slots),
        ]);
    }

    /** จำนวนคำขอรอ / จองแล้ว ต่อห้อง สำหรับวันหนึ่ง (จุดสีบนปุ่มห้อง) */
    public function boardSummary(Request $request)
    {
        $date = $request->validate(['date' => 'required|date'])['date'];

        $rows = BookingGroup::selectRaw(
                "room_id,
                 SUM(status IN ('pending','waiting_confirm')) as pending,
                 SUM(status = 'confirmed') as booked"
            )
            ->where('date', $date)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->groupBy('room_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->room_id] = ['pending' => (int) $r->pending, 'booked' => (int) $r->booked];
        }

        return response()->json($out);
    }

    /** ยกเลิกการจองที่ยืนยันแล้ว (pending/waiting ใช้ reject) */
    public function cancelSession(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $groups   = BookingGroup::whereIn('id', $data['ids'])->where('status', 'confirmed')->get();
        $admin    = Auth::guard('admin')->user();
        $byLabel  = $admin ? "เจ้าหน้าที่ ({$admin->name})" : 'เจ้าหน้าที่';

        foreach ($groups as $g) {
            $g->update([
                'status'        => 'cancelled',
                'cancelled_at'  => Carbon::now(),
                'cancelled_by'  => $byLabel,
                'cancel_reason' => $data['reason'] ?? null,
            ]);
            $g->bookings()->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'ยกเลิกเรียบร้อย', 'count' => $groups->count()]);
    }

    public function staffStore(Request $request)
    {
        $data = $request->validate([
            'room_id'    => 'required|integer|exists:rooms,id',
            'date'       => 'required|date|after_or_equal:today',
            'time_ids'   => 'required|array|min:1',
            'time_ids.*' => 'integer',
        ]);

        $timeIds = collect($data['time_ids'])->sort()->values()->all();

        for ($i = 1; $i < count($timeIds); $i++) {
            if ($timeIds[$i] !== $timeIds[$i - 1] + 1) {
                return response()->json(['message' => 'ต้องเลือกช่วงเวลาที่ต่อเนื่องกันเท่านั้น'], 422);
            }
        }

        $room = Room::findOrFail($data['room_id']);

        if ($room->status === '1') {
            return response()->json(['message' => 'ห้องนี้ไม่พร้อมให้บริการ'], 422);
        }

        try {
            $groups = DB::transaction(function () use ($data, $timeIds, $room) {
                foreach ($timeIds as $timeId) {
                    $taken = BookingGroup::where('room_id', $data['room_id'])
                        ->where('date', $data['date'])
                        ->where('time_id', $timeId)
                        ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                        ->lockForUpdate()
                        ->exists();

                    if ($taken) throw new \Exception('slot_taken');
                }

                $adminId = Auth::guard('admin')->id();
                $groups  = [];

                foreach ($timeIds as $timeId) {
                    $group = BookingGroup::create([
                        'room_id'          => $data['room_id'],
                        'date'             => $data['date'],
                        'time_id'          => $timeId,
                        'lead_user_id'     => null,
                        'admin_id'         => $adminId,
                        'status'           => 'confirmed',
                        'source'           => 'staff',
                        'join_token'       => Str::random(32),
                        'token_expires_at' => Carbon::now()->addMinutes(15),
                    ]);

                    $groups[] = $group;
                }

                return $groups;
            });

            return response()->json([
                'message' => 'จองสำเร็จ',
                'count'   => count($groups),
            ]);

        } catch (\Exception $e) {
            $msg = $e->getMessage() === 'slot_taken'
                ? 'เสียใจด้วย ช่วงเวลานี้ถูกจองไปแล้ว'
                : 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            return response()->json(['message' => $msg], 422);
        }
    }

    public function approveSession(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $groups = BookingGroup::whereIn('id', $ids)
            ->whereIn('status', ['pending', 'waiting_confirm'])
            ->get();

        foreach ($groups as $g) {
            $g->update(['status' => 'confirmed']);
            // อนุมัติเท่านั้น — ไม่เช็คอิน (เช็คอินเกิดที่ kiosk หรือปุ่ม "เช็คอิน" วันใช้งานจริง)
            $g->bookings()->where('status', 'pending')->update(['status' => 'confirmed']);
        }

        return response()->json(['message' => 'อนุมัติสำเร็จ', 'count' => $groups->count()]);
    }

    // เจ้าหน้าที่กดเช็คอินให้ (ห้องที่ไม่ติด access control)
    public function checkinSession(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $groups = BookingGroup::whereIn('id', $ids)->where('status', 'confirmed')->get();

        $now   = Carbon::now('Asia/Bangkok');
        $count = 0;

        foreach ($groups as $g) {
            $count += $g->bookings()->where('status', 'confirmed')->update([
                'status'     => 'checked_in',
                'checkin_at' => $now,
            ]);
        }

        return response()->json(['message' => 'เช็คอินแล้ว', 'count' => $count]);
    }

    public function rejectSession(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $groups = BookingGroup::whereIn('id', $data['ids'])
            ->whereIn('status', ['pending', 'waiting_confirm'])
            ->get();

        $admin   = Auth::guard('admin')->user();
        $byLabel = $admin ? "เจ้าหน้าที่ ({$admin->name})" : 'เจ้าหน้าที่';

        foreach ($groups as $g) {
            $g->update([
                'status'        => 'cancelled',
                'cancelled_at'  => Carbon::now(),
                'cancelled_by'  => $byLabel,
                'cancel_reason' => $data['reason'] ?? null,
            ]);
            $g->bookings()->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'ปฏิเสธเรียบร้อย', 'count' => $groups->count()]);
    }
}
