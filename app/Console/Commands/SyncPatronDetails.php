<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Support\PatronService;
use Illuminate\Console\Command;

class SyncPatronDetails extends Command
{
    protected $signature = 'members:sync-patron
        {--force : ยิงใหม่ทุกคนแม้เพิ่ง sync}
        {--stale=30 : sync ใหม่เฉพาะคนที่เกินกี่วันนับจากครั้งล่าสุด}
        {--sleep=200 : หน่วงระหว่างแต่ละ request (มิลลิวินาที) กัน rate limit}';

    protected $description = 'ดึง faculty/branch จากระบบ patron มาเติมให้ members (เฉพาะ code เป็นตัวเลข)';

    public function handle(PatronService $svc): int
    {
        $stale = (int) $this->option('stale');
        $force = (bool) $this->option('force');
        $sleep = max(0, (int) $this->option('sleep')) * 1000;

        $members = Member::whereRaw("code REGEXP '^[0-9]+$'")
            ->when(! $force, fn($q) => $q->where(function ($w) use ($stale) {
                $w->whereNull('patron_synced_at')
                  ->orWhere('patron_synced_at', '<', now()->subDays($stale));
            }))
            ->orderBy('id')
            ->get();

        if ($members->isEmpty()) {
            $this->info('ไม่มี member ที่ต้อง sync');
            return self::SUCCESS;
        }

        $this->info("เริ่ม sync {$members->count()} คน");
        $bar   = $this->output->createProgressBar($members->count());
        $found = 0;

        foreach ($members as $m) {
            if ($svc->syncMember($m, $stale, $force)) {
                $found++;
            }
            $bar->advance();
            if ($sleep) {
                usleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("เสร็จ — พบข้อมูล {$found}/{$members->count()} คน");

        return self::SUCCESS;
    }
}
