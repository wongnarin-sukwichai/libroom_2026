<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminHolidayController extends Controller
{
    private array $months = [
        1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.',
        5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.',
        9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.',
    ];

    public function index()
    {
        $holidays = Holiday::orderBy('m')->orderBy('d')->get()
            ->map(fn($h) => [
                'id'         => $h->id,
                'd'          => $h->d,
                'm'          => $h->m,
                'detail'     => $h->detail,
                'type'       => $h->type,
                'owner'      => $h->owner,
                'date_label' => $h->d . ' ' . ($this->months[(int)$h->m] ?? $h->m),
            ]);

        return response()->json([
            'holidays'       => $holidays,
            'national_count' => $holidays->where('type', 'national')->count(),
            'library_count'  => $holidays->where('type', 'library')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'd'      => 'required|integer|min:1|max:31',
            'm'      => 'required|integer|min:1|max:12',
            'detail' => 'required|string|max:255',
            'type'   => 'required|in:national,library',
        ]);

        if (Holiday::where('d', (string)$data['d'])->where('m', (string)$data['m'])->exists()) {
            return response()->json(['message' => 'วันที่นี้มีในระบบแล้ว'], 422);
        }

        $holiday = Holiday::create([
            'd'      => (string)$data['d'],
            'm'      => (string)$data['m'],
            'detail' => $data['detail'],
            'type'   => $data['type'],
            'owner'  => Auth::id() ?? 1,
        ]);

        return response()->json([
            ...$holiday->toArray(),
            'date_label' => $holiday->d . ' ' . ($this->months[(int)$holiday->m] ?? $holiday->m),
        ], 201);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return response()->json(['message' => 'ลบเรียบร้อย']);
    }
}
