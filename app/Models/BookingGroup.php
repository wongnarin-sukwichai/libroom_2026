<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingGroup extends Model
{
    protected $table = 'booking_groups';

    protected $fillable = [
        'room_id', 'date', 'time_id', 'session_id', 'lead_user_id', 'admin_id',
        'status', 'source', 'join_token', 'token_expires_at',
        'cancel_code', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = ['date' => 'date', 'cancelled_at' => 'datetime'];

    /**
     * query ของทุก slot ในรอบการจองเดียวกัน (รวมตัวเอง)
     * แถวเก่าที่ยังไม่มี session_id → fallback lead+room+date แบบเดิม (เฉพาะแถวที่ไม่มี session_id ด้วยกัน)
     */
    public function sessionSiblings(): Builder
    {
        if ($this->session_id) {
            return static::query()->where('session_id', $this->session_id);
        }

        return static::query()
            ->whereNull('session_id')
            ->where('lead_user_id', $this->lead_user_id)
            ->where('room_id', $this->room_id)
            ->where('date', $this->date);
    }

    /** key สำหรับ groupBy เป็นรอบการจอง */
    public function sessionKey(): string
    {
        return $this->session_id
            ?? ($this->lead_user_id . '|' . $this->room_id . '|' . $this->date->toDateString());
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'group_id');
    }

    public function lead()
    {
        return $this->belongsTo(Member::class, 'lead_user_id');
    }

    public function admin()
    {
        return $this->belongsTo(\App\Models\User::class, 'admin_id');
    }
}
