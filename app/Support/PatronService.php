<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ดึง faculty / branch / type จากระบบ patron (libapp.msu.ac.th) ด้วยรหัสนิสิต
 *
 * best-effort เท่านั้น — ถ้าไม่พบ/error ผู้ใช้ต้อง login และจองได้ตามปกติ
 * (ถือว่าผ่าน OAuth มาแล้ว)
 */
class PatronService
{
    /**
     * ยิง API ดึงข้อมูล 1 คน
     * คืน ['faculty' => ?, 'branch' => ?, 'type' => ?] หรือ null ถ้าไม่พบ/error
     */
    public function fetch(string $studentId): ?array
    {
        $base  = rtrim((string) config('services.patron.url'), '/');
        $token = config('services.patron.token');

        try {
            $res = Http::timeout(3)
                ->acceptJson()
                ->get("{$base}/{$studentId}", array_filter(['token' => $token]));

            if (! $res->successful()) {
                return null;
            }

            $d = $res->json();

            // API อาจคืน object เดียว หรือ array ของ object
            if (is_array($d) && array_is_list($d)) {
                $d = $d[0] ?? null;
            }
            if (! is_array($d) || blank($d['FACULTYNAMETHAI'] ?? null)) {
                return null;
            }

            $norm = fn($v) => filled($v) ? preg_replace('/\s+/u', ' ', trim((string) $v)) : null;

            return [
                'faculty' => $norm($d['FACULTYNAMETHAI'] ?? null),
                'branch'  => $norm($d['PROGRAMNAMETHAI'] ?? null),
                'type'    => $norm($d['PTTYPENAMETHAI'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning("PatronService: fetch failed for {$studentId} — {$e->getMessage()}");
            return null;
        }
    }

    /**
     * sync ให้ member 1 คน
     *
     * - code ไม่ใช่ตัวเลข (บุคลากร) → ข้าม
     * - เพิ่ง sync มาไม่ถึง $staleDays วัน และไม่ได้ force → ข้าม
     * - อัปเดต patron_synced_at เสมอเมื่อได้ลองยิง (แม้ไม่พบข้อมูล) เพื่อไม่ยิงซ้ำถี่ ๆ
     *
     * @return bool  true = ได้ข้อมูลกลับมาและอัปเดตแล้ว
     */
    public function syncMember(Member $member, int $staleDays = 30, bool $force = false): bool
    {
        if (! ctype_digit((string) $member->code)) {
            return false;
        }

        if (! $force
            && $member->patron_synced_at
            && $member->patron_synced_at->gt(now()->subDays($staleDays))) {
            return false;
        }

        $data = $this->fetch((string) $member->code);

        $update = ['patron_synced_at' => now()];
        if ($data) {
            if (filled($data['faculty'])) $update['faculty'] = $data['faculty'];
            if (filled($data['branch']))  $update['branch']  = $data['branch'];
            if (filled($data['type']))    $update['type']    = $data['type'];
        }
        $member->forceFill($update)->save();

        return (bool) $data;
    }
}
