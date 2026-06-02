<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
                    'member_name'  => $g->lead?->name,
                    'member_email' => $g->lead?->email,
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
