<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneTool extends Model
{
    protected $table = 'zone_tools';

    protected $fillable = ['zone_id', 'tool_id', 'quantity'];

    public function tool()
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
