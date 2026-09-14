<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\Zone;
use Illuminate\Console\Command;

class GenerateScanCodes extends Command
{
    protected $signature = 'rooms:generate-scan-codes
        {--zone= : เฉพาะ zone id นี้}
        {--force : เขียนทับ scan_code เดิมด้วย}';

    protected $description = 'สร้าง scan_code ให้ห้อง = {zones.scan_prefix}-{ลำดับ 3 หลัก} (โซนต้องตั้ง scan_prefix ก่อน)';

    public function handle(): int
    {
        $zones = Zone::query()
            ->when($this->option('zone'), fn($q) => $q->where('id', $this->option('zone')))
            ->whereNotNull('scan_prefix')
            ->where('scan_prefix', '!=', '')
            ->orderBy('id')
            ->get();

        if ($zones->isEmpty()) {
            $this->warn('ไม่มีโซนที่ตั้ง scan_prefix — ตั้งก่อนที่ Admin › Rooms › (แก้ไข prefix)');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $total = 0;

        foreach ($zones as $zone) {
            $prefix = trim($zone->scan_prefix);
            $rooms  = Room::where('zone_id', $zone->id)->orderBy('id')->get();

            // เลขที่ใช้ไปแล้ว (จาก scan_code เดิมที่ prefix ตรงกัน)
            $used = $rooms->pluck('scan_code')
                ->filter(fn($c) => $c && str_starts_with($c, $prefix . '-'))
                ->map(fn($c) => (int) substr($c, strlen($prefix) + 1))
                ->filter()
                ->values();
            $next = ($used->max() ?? 0) + 1;

            $made = 0;
            foreach ($rooms as $room) {
                if ($room->scan_code && ! $force) {
                    continue;
                }
                if ($room->scan_code && str_starts_with($room->scan_code, $prefix . '-') && ! $force) {
                    continue;
                }
                $room->update(['scan_code' => sprintf('%s-%03d', $prefix, $next)]);
                $next++;
                $made++;
            }

            $total += $made;
            $this->line("  {$zone->title} [{$prefix}] — สร้าง {$made} โค้ด");
        }

        $this->info("เสร็จ — สร้าง scan_code รวม {$total} ห้อง");
        return self::SUCCESS;
    }
}
