<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['scan_code', 'room_id', 'user_id', 'outcome', 'ip', 'user_agent', 'created_at'];

    /** บันทึกผลการสแกน 1 ครั้ง */
    public static function record(string $code, ?int $roomId, ?int $userId, string $outcome): void
    {
        static::create([
            'scan_code'  => $code,
            'room_id'    => $roomId,
            'user_id'    => $userId,
            'outcome'    => $outcome,
            'ip'         => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
