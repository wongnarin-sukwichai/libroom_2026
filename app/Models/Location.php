<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['pic', 'title', 'title_eng', 'detail', 'status'];

    public function zones()
    {
        return $this->hasMany(Zone::class, 'loc_id');
    }
}
