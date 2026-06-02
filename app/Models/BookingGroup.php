<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingGroup extends Model
{
    protected $table = 'booking_groups';

    protected $fillable = [
        'room_id', 'date', 'time_id', 'lead_user_id', 'admin_id',
        'status', 'join_token', 'token_expires_at',
        'cancel_code', 'cancelled_at',
    ];

    protected $casts = ['date' => 'date'];

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
