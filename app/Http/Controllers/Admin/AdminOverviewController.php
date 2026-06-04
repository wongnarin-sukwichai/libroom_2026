<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Member;
use App\Models\Room;
use Carbon\Carbon;

class AdminOverviewController extends Controller
{
    public function stats()
    {
        $today = Carbon::today()->format('Y-m-d');

        return response()->json([
            'approved_today'  => BookingGroup::where('date', $today)->where('status', 'confirmed')->count(),
            'cancelled_today' => BookingGroup::where('date', $today)->where('status', 'cancelled')->count(),
            'active_rooms'    => Room::where('status', '0')->count(),
            'total_rooms'     => Room::count(),
            'total_members'   => Member::count(),
        ]);
    }
}
