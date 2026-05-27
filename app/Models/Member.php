<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Member extends Authenticatable
{
    use Notifiable;

    protected $table = 'table_members';

    protected $fillable = [
        'google_id',
        'name',
        'email',
        'avatar',
        'code',
        'type',
        'faculty',
        'branch',
    ];
}
