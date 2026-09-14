<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = ['pic', 'zone_id', 'title', 'detail', 'confirm_type', 'access_control', 'scan_code', 'status'];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function tools()
    {
        return $this->hasMany(RoomTool::class, 'room_id');
    }

    /**
     * อุปกรณ์ที่ห้องนี้มีจริง = เฉพาะที่กำหนดไว้ใน roomtools ของห้องนี้ (ไม่ inherit จาก zone)
     * zone_tools เป็นแค่ "คลังอุปกรณ์ภายในโซน" ให้แอดมินเลือกติ๊ก
     * ต้อง eager load: tools.tool
     * คืน collection ของ ['tool_id', 'name', 'icon', 'quantity']
     */
    public function effectiveTools(): \Illuminate\Support\Collection
    {
        return $this->tools
            ->map(fn($rt) => [
                'tool_id'  => $rt->tool_id,
                'name'     => $rt->tool?->name,
                'icon'     => $rt->tool?->icon,
                'quantity' => (int) $rt->quantity,
            ])
            ->filter(fn($t) => $t['name'])
            ->values();
    }
}
