<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Holiday;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date') ? $request->date : Carbon::today()->format('Y-m-d');

        $query = BookingGroup::with([
            'room'               => fn($q) => $q->select('id', 'zone_id', 'title', 'confirm_type'),
            'room.zone'          => fn($q) => $q->select('id', 'loc_id', 'title'),
            'room.zone.location' => fn($q) => $q->select('id', 'title'),
            'lead'               => fn($q) => $q->select('id', 'name', 'email', 'type'),
            'admin'              => fn($q) => $q->select('id', 'name', 'email'),
        ])
        ->where('date', $date)
        ->orderByRaw("FIELD(status, 'pending', 'waiting_confirm', 'confirmed', 'completed', 'cancelled')")
        ->orderBy('lead_user_id')
        ->orderBy('room_id')
        ->orderBy('time_id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $all      = $query->get();
        $sessions = $this->groupIntoSessions($all);

        // Manual pagination
        $perPage  = 10;
        $page     = max(1, (int)$request->get('page', 1));
        $total    = count($sessions);
        $items    = array_slice($sessions, ($page - 1) * $perPage, $perPage);

        $pendingCount = BookingGroup::where('status', 'pending')
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
                && $g->time_id === $current['last_time'] + 1;

            if ($sameSession) {
                $current['ids'][]     = $g->id;
                $current['last_time'] = $g->time_id;
                $current['end_hour']  = $g->time_id + 1;
                $current['hours']++;
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
                    'confirm_type' => $g->room?->confirm_type,
                    'room_title'   => $g->room?->title,
                    'zone_title'   => $g->room?->zone?->title,
                    'loc_title'    => $g->room?->zone?->location?->title,
                    'member_name'  => $g->lead?->name  ?? ($g->admin ? $g->admin->name  . ' (เจ้าหน้าที่)' : null),
                    'member_email' => $g->lead?->email ?? $g->admin?->email,
                ];
            }
        }
        if ($current) $sessions[] = $this->formatSession($current);

        return $sessions;
    }

    private function formatSession(array $s): array
    {
        $pad = fn($h) => sprintf('%02d:00', $h);
        return [
            'ids'          => $s['ids'],
            'date'         => $s['date'],
            'time_label'   => $pad($s['start_hour']) . ' – ' . $pad($s['end_hour']) . ' น.',
            'hours'        => $s['hours'],
            'status'       => $s['status'],
            'confirm_type' => $s['confirm_type'],
            'room_title'   => $s['room_title'],
            'zone_title'   => $s['zone_title'],
            'loc_title'    => $s['loc_title'],
            'member_name'  => $s['member_name'],
            'member_email' => $s['member_email'],
        ];
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

        $groups = BookingGroup::whereIn('id', $ids)->where('status', 'pending')->get();
        foreach ($groups as $g) {
            $g->update(['status' => 'confirmed']);
            $g->bookings()->update(['status' => 'confirmed']);
        }

        return response()->json(['message' => 'อนุมัติสำเร็จ', 'count' => $groups->count()]);
    }

    public function rejectSession(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $groups = BookingGroup::whereIn('id', $ids)
            ->whereIn('status', ['pending', 'waiting_confirm'])
            ->get();

        foreach ($groups as $g) {
            $g->update(['status' => 'cancelled', 'cancelled_at' => Carbon::now()]);
            $g->bookings()->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'ปฏิเสธเรียบร้อย', 'count' => $groups->count()]);
    }
}
