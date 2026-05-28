<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTool extends Model
{
    protected $fillable = ['room_id', 'tool_id', 'quantity', 'status', 'note'];

    public function tool()
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
