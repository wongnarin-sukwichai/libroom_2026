<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Time extends Model
{
    protected $fillable = ['title', 'start', 'end', 'owner'];

    public function getStartHourAttribute(): int
    {
        return (int) explode(':', $this->start)[0];
    }

    public function getEndHourAttribute(): int
    {
        return (int) explode(':', $this->end)[0];
    }

    public function getTotalAttribute(): int
    {
        return $this->end_hour - $this->start_hour;
    }
}
