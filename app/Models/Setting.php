<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** อ่านค่า setting ตาม key (คืน $default ถ้าไม่มี) */
    public static function get(string $key, $default = null)
    {
        $value = static::query()->where('key', $key)->value('value');
        return $value ?? $default;
    }

    /** เขียนค่า setting (สร้างใหม่ถ้ายังไม่มี) */
    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
