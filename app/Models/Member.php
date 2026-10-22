<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Member extends Authenticatable
{
    use Notifiable;

    protected $table = 'members';

    protected $fillable = [
        'google_id',
        'name',
        'email',
        'avatar',
        'code',
        'type',
        'faculty',
        'branch',
        'patron_synced_at',
        'blocked_at',
        'block_reason',
        'blocked_by',
    ];

    protected $casts = [
        'patron_synced_at' => 'datetime',
        'blocked_at'       => 'datetime',
    ];

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** ข้อความแจ้งสมาชิกเมื่อถูกระงับการจอง */
    public function blockedMessage(): string
    {
        return 'บัญชีของคุณถูกระงับสิทธิ์การจอง'
            . ($this->block_reason ? " — {$this->block_reason}" : '')
            . ' กรุณาติดต่อเจ้าหน้าที่';
    }
}
