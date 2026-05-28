<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'table_rooms';

    protected $fillable = ['zone_id', 'name', 'min_capacity', 'status'];

    public function tools()
    {
        return $this->hasMany(RoomTool::class, 'room_id');
    }
}
