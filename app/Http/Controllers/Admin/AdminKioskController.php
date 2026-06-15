<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskBypassCode;
use Illuminate\Http\Request;

class AdminKioskController extends Controller
{
    public function index()
    {
        return response()->json(KioskBypassCode::orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'  => 'required|string|unique:kiosk_bypass_codes,code',
            'label' => 'required|string|max:100',
        ]);

        $bypass = KioskBypassCode::create([
            'code'      => $request->code,
            'label'     => $request->label,
            'is_active' => true,
        ]);

        return response()->json($bypass, 201);
    }

    public function toggle(KioskBypassCode $bypass)
    {
        $bypass->update(['is_active' => !$bypass->is_active]);
        return response()->json($bypass);
    }

    public function destroy(KioskBypassCode $bypass)
    {
        $bypass->delete();
        return response()->json(['ok' => true]);
    }
}
