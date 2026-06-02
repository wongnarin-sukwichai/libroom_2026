<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminTimeController extends Controller
{
    public function index()
    {
        return response()->json(
            Time::orderBy('id')->get()->map(fn($t) => [
                'id'    => $t->id,
                'title' => $t->title,
                'start' => $t->start,
                'end'   => $t->end,
                'total' => $t->total,
            ])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100|unique:times,title',
            'start' => 'required|date_format:H:i',
            'end'   => 'required|date_format:H:i|after:start',
        ]);

        $time = Time::create([...$data, 'owner' => Auth::id() ?? 1]);

        return response()->json([...$time->toArray(), 'total' => $time->total], 201);
    }

    public function update(Request $request, Time $time)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100|unique:times,title,' . $time->id,
            'start' => 'required|date_format:H:i',
            'end'   => 'required|date_format:H:i|after:start',
        ]);

        $time->update($data);

        return response()->json([...$time->toArray(), 'total' => $time->total]);
    }

    public function destroy(Time $time)
    {
        $inUse = \App\Models\Zone::where('time_weekday', $time->id)
            ->orWhere('time_weekend', $time->id)
            ->exists();

        if ($inUse) {
            return response()->json(['message' => 'ไม่สามารถลบได้ เนื่องจาก config นี้ถูกใช้งานโดย zone อยู่'], 422);
        }

        $time->delete();
        return response()->json(['message' => 'ลบเรียบร้อย']);
    }
}
