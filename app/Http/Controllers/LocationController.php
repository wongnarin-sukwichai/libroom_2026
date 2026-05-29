<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Location::select('id', 'title', 'title_eng', 'detail', 'status')
            ->with(['zones' => fn($q) => $q
                ->select('id', 'loc_id', 'pic', 'title', 'title_eng', 'detail', 'capacity', 'tool', 'zone_daily_quota', 'time_weekday', 'time_weekend', 'status')
                ->with(['rooms' => fn($r) => $r->select('id', 'zone_id', 'title', 'detail', 'pic', 'confirm_type')])
            ])
            ->get();

        return inertia('Welcome', [
            'locations' => $data,
        ]);
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
