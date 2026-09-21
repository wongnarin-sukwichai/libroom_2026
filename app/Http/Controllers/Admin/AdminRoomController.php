<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomTool;
use App\Models\Time;
use App\Models\Tool;
use App\Models\Zone;
use App\Models\ZoneTool;
use Illuminate\Http\Request;

class AdminRoomController extends Controller
{
    public function index()
    {
        $locations = Location::select('id', 'title', 'title_eng', 'status')
            ->with(['zones' => fn($q) => $q
                ->select('id', 'loc_id', 'title', 'status', 'zone_daily_quota', 'time_weekday', 'time_weekend', 'min_capacity', 'scan_prefix', 'icon')
                ->with([
                    'tools' => fn($t) => $t->select('id', 'zone_id', 'tool_id', 'quantity'),
                    'rooms' => fn($r) => $r
                        ->select('id', 'zone_id', 'title', 'confirm_type', 'access_control', 'scan_code', 'status')
                        ->with(['tools' => fn($rt) => $rt->select('id', 'room_id', 'tool_id', 'mode', 'quantity')]),
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

        $tools = Tool::orderBy('name')->get(['id', 'name', 'icon']);

        return response()->json(['locations' => $locations, 'times' => $times, 'tools' => $tools]);
    }

    // ── คลังอุปกรณ์ (tool catalog) ──────────────────────────────
    public function toolStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tools,name'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);
        $tool = Tool::create(['name' => $data['name'], 'icon' => $data['icon'] ?: 'fa-wrench']);
        return response()->json($tool);
    }

    public function toolUpdate(Request $request, Tool $tool)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tools,name,' . $tool->id],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);
        $tool->update(['name' => $data['name'], 'icon' => $data['icon'] ?: 'fa-wrench']);
        return response()->json($tool);
    }

    public function toolDestroy(Tool $tool)
    {
        $tool->delete(); // cascade ลบ zone_tools + roomtools ที่อ้างถึง
        return response()->json(['message' => 'ลบแล้ว']);
    }

    // ── ชุดอุปกรณ์มาตรฐานของ zone ───────────────────────────────
    public function updateZoneTools(Request $request, Zone $zone)
    {
        $data = $request->validate([
            'tools'             => ['present', 'array'],
            'tools.*.tool_id'   => ['required', 'integer', 'exists:tools,id'],
            'tools.*.quantity'  => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $zone->tools()->delete();
        foreach (collect($data['tools'])->unique('tool_id') as $t) {
            ZoneTool::create(['zone_id' => $zone->id, 'tool_id' => $t['tool_id'], 'quantity' => $t['quantity']]);
        }
        return response()->json(['message' => 'บันทึกแล้ว']);
    }

    // ── override อุปกรณ์เฉพาะห้อง ───────────────────────────────
    public function updateRoomTools(Request $request, Room $room)
    {
        $data = $request->validate([
            'overrides'              => ['present', 'array'],
            'overrides.*.tool_id'    => ['required', 'integer', 'exists:tools,id'],
            'overrides.*.mode'       => ['required', 'in:add,remove'],
            'overrides.*.quantity'   => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $room->tools()->delete();
        foreach (collect($data['overrides'])->unique('tool_id') as $o) {
            RoomTool::create([
                'room_id'  => $room->id,
                'tool_id'  => $o['tool_id'],
                'mode'     => $o['mode'],
                'quantity' => $o['quantity'],
                'status'   => 'working',
            ]);
        }
        return response()->json(['message' => 'บันทึกแล้ว']);
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

    public function toggleRoomAccessControl(Room $room)
    {
        $room->access_control = $room->access_control === '1' ? '0' : '1';
        $room->save();
        return response()->json(['access_control' => $room->access_control]);
    }

    public function updateZoneSettings(Request $request, Zone $zone)
    {
        $data = $request->validate([
            'zone_daily_quota' => 'nullable|integer|min:1|max:24',
            'time_weekday'     => 'required|integer|exists:times,id',
            'time_weekend'     => 'required|integer|exists:times,id',
            'min_capacity'     => 'required|integer|min:1',
            'icon'             => 'nullable|string|max:60',
        ]);

        $zone->update($data);
        return response()->json(['message' => 'บันทึกแล้ว']);
    }

    // ── QR / scan_code ─────────────────────────────────────────
    public function updateZonePrefix(Request $request, Zone $zone)
    {
        $data = $request->validate([
            'scan_prefix' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]*$/'],
        ]);
        $zone->update(['scan_prefix' => $data['scan_prefix'] ?: null]);
        return response()->json(['scan_prefix' => $zone->scan_prefix]);
    }

    /** สร้าง/ออกใหม่ scan_code ให้ห้องเดียว (= {prefix}-{ลำดับถัดไปในโซน}) */
    public function generateRoomScanCode(Room $room)
    {
        $zone   = $room->zone;
        $prefix = trim((string) $zone?->scan_prefix);
        if ($prefix === '') {
            return response()->json(['message' => 'โซนนี้ยังไม่ได้ตั้ง prefix'], 422);
        }

        $max = Room::where('zone_id', $zone->id)
            ->where('scan_code', 'like', $prefix . '-%')
            ->where('id', '!=', $room->id)
            ->pluck('scan_code')
            ->map(fn($c) => (int) substr($c, strlen($prefix) + 1))
            ->max() ?? 0;

        $room->update(['scan_code' => sprintf('%s-%03d', $prefix, $max + 1)]);
        return response()->json(['scan_code' => $room->scan_code]);
    }

    /** แก้ scan_code เอง (หรือเคลียร์) */
    public function updateRoomScanCode(Request $request, Room $room)
    {
        $data = $request->validate([
            'scan_code' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-]*$/', 'unique:rooms,scan_code,' . $room->id],
        ]);
        $room->update(['scan_code' => $data['scan_code'] ?: null]);
        return response()->json(['scan_code' => $room->scan_code]);
    }

    /** หน้าพิมพ์ QR ทั้งโซน (Blade ธรรมดา สำหรับสั่งพิมพ์) */
    public function qrSheet(Zone $zone)
    {
        $rooms = $zone->rooms()->whereNotNull('scan_code')->orderBy('title')->get(['id', 'title', 'scan_code']);
        return view('admin.qr-sheet', [
            'zone'    => $zone,
            'rooms'   => $rooms,
            'baseUrl' => rtrim(config('app.url'), '/'),
        ]);
    }
}
