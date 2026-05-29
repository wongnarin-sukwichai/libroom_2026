<?php

namespace App\Http\Controllers;

use App\Models\BookingGroup;
use App\Models\Room;
use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function slots(Room $room, Request $request)
    {
        $date      = $request->validate(['date' => 'required|date'])['date'];
        $isWeekend = Carbon::parse($date)->isWeekend();

        $zone         = $room->zone;
        $configId     = $isWeekend ? $zone->time_weekend : $zone->time_weekday;
        $config       = Time::findOrFail($configId);

        // Generate 1-hour slots dynamically from config (hour = start, total = count)
        $times = collect();
        for ($i = 0; $i < $config->total; $i++) {
            $hour = $config->hour + $i;
            $times->push([
                'id'    => $hour,
                'start' => sprintf('%02d:00', $hour),
                'end'   => sprintf('%02d:00', $hour + 1),
                'title' => sprintf('%02d:00 – %02d:00 น.', $hour, $hour + 1),
            ]);
        }

        // time_id in booking_groups stores the hour number (9, 10, 11...)
        $bookedIds = BookingGroup::where('room_id', $room->id)
            ->where('date', $date)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->pluck('time_id')
            ->toArray();

        return response()->json([
            'times'      => $times,
            'booked_ids' => $bookedIds,
        ]);
    }
}
