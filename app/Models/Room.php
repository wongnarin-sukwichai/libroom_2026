<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = ['pic', 'zone_id', 'title', 'detail', 'confirm_type'];

    public function tools()
    {
        return $this->hasMany(RoomTool::class, 'room_id');
    }
}
