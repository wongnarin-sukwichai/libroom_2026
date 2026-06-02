<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = ['pic', 'zone_id', 'title', 'detail', 'confirm_type', 'status'];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function tools()
    {
        return $this->hasMany(RoomTool::class, 'room_id');
    }
}
