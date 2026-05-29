<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingGroup extends Model
{
    protected $table = 'booking_groups';

    protected $fillable = [
        'room_id', 'date', 'time_id', 'lead_user_id',
        'status', 'join_token', 'token_expires_at',
        'cancel_code', 'cancelled_at',
    ];
}
