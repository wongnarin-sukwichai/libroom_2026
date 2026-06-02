<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Location;
use App\Models\ServiceHour;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $today          = Carbon::now();
        $todayIsHoliday = Holiday::where('d', (string)$today->day)
            ->where('m', (string)$today->month)
            ->exists();

        $data = Location::select('id', 'title', 'title_eng', 'detail', 'status')
            ->with(['zones' => fn($q) => $q
                ->select('id', 'loc_id', 'pic', 'title', 'title_eng', 'detail', 'capacity', 'tool', 'zone_daily_quota', 'time_weekday', 'time_weekend', 'status')
                ->with(['rooms' => fn($r) => $r
                    ->select('id', 'zone_id', 'title', 'detail', 'pic', 'confirm_type', 'status')
                    ->with(['tools' => fn($t) => $t
                        ->select('id', 'room_id', 'tool_id', 'quantity')
                        ->with(['tool' => fn($tt) => $tt->select('id', 'name', 'icon')])
                    ])
                ])
            ])
            ->get();

        return inertia('Welcome', [
            'locations'      => $data,
            'todayIsHoliday' => $todayIsHoliday,
            'todayDate'      => $today->format('Y-m-d'),
        ]);
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
