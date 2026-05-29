<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Time extends Model
{
    protected $fillable = ['title', 'total', 'hour', 'minute', 'start', 'end', 'owner'];
}
