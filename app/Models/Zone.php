<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $table = 'zones';

    protected $fillable = [
        'pic', 'loc_id', 'title', 'title_eng', 'detail',
        'capacity', 'tool', 'min_capacity', 'zone_daily_quota',
        'time_weekday', 'time_weekend', 'status',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'loc_id');
    }
}
