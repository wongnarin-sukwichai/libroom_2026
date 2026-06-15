<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskBypassCode extends Model
{
    protected $table    = 'kiosk_bypass_codes';
    protected $fillable = ['code', 'label', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];
}
