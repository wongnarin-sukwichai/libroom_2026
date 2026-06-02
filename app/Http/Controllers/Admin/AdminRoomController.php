<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Room;
use App\Models\Time;
use App\Models\Zone;
use Illuminate\Http\Request;

class AdminRoomController extends Controller
{
    public function index()
    {
        $locations = Location::select('id', 'title', 'title_eng', 'status')
            ->with(['zones' => fn($q) => $q
                ->select('id', 'loc_id', 'title', 'status', 'zone_daily_quota', 'time_weekday', 'time_weekend', 'min_capacity')
                ->with(['rooms' => fn($r) => $r
                    ->select('id', 'zone_id', 'title', 'confirm_type', 'status')
                ])
            ])
            ->get();

        $times = Time::orderBy('id')->get()->map(fn($t) => [
            'id'    => $t->id,
            'title' => $t->title,
            'start' => $t->start,
            'end'   => $t->end,
            'total' => $t->total,
        ]);

        return response()->json(['locations' => $locations, 'times' => $times]);
    }

    public function toggleLocation(Location $location)
    {
        $location->status = $location->status === '0' ? '1' : '0';
        $location->save();
        return response()->json(['status' => $location->status]);
    }

    public function toggleZone(Zone $zone)
    {
        $zone->status = $zone->status === '0' ? '1' : '0';
        $zone->save();
        return response()->json(['status' => $zone->status]);
    }

    public function toggleRoom(Room $room)
    {
        $room->status = $room->status === '0' ? '1' : '0';
        $room->save();
        return response()->json(['status' => $room->status]);
    }

    public function updateZoneSettings(Request $request, Zone $zone)
    {
        $data = $request->validate([
            'zone_daily_quota' => 'nullable|integer|min:1|max:24',
            'time_weekday'     => 'required|integer|exists:times,id',
            'time_weekend'     => 'required|integer|exists:times,id',
            'min_capacity'     => 'required|integer|min:1',
        ]);

        $zone->update($data);
        return response()->json(['message' => 'บันทึกแล้ว']);
    }
}
