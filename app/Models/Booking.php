<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = ['group_id', 'user_id', 'status'];

    public function group()
    {
        return $this->belongsTo(BookingGroup::class, 'group_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'user_id');
    }
}
