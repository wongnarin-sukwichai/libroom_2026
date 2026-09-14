<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR — {{ $zone->title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Sarabun", "TH Sarabun New", system-ui, sans-serif; margin: 0; padding: 24px; background: #f1f5f9; color: #1e293b; }
        .toolbar { max-width: 900px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        h1 { font-size: 18px; margin: 0; }
        .toolbar .sub { font-size: 12px; color: #64748b; }
        button { font: inherit; font-weight: 700; font-size: 13px; background: #1e3a8a; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; cursor: pointer; }
        .grid { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; text-align: center; page-break-inside: avoid; }
        .card svg { width: 100%; height: auto; max-width: 180px; }
        .card .code { font-family: ui-monospace, monospace; font-weight: 700; font-size: 13px; margin-top: 6px; }
        .card .name { font-size: 12px; color: #64748b; }
        .card .zone { font-size: 10px; color: #94a3b8; margin-top: 2px; }
        .empty { max-width: 900px; margin: 40px auto; text-align: center; color: #94a3b8; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .grid { gap: 8px; }
            .card { border-color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h1>QR สแกนเพื่อจอง — {{ $zone->title }}</h1>
            <div class="sub">{{ $rooms->count() }} จุด · ปลายทาง {{ $baseUrl }}/s/&lt;code&gt;</div>
        </div>
        <button onclick="window.print()">พิมพ์</button>
    </div>

    @if($rooms->isEmpty())
        <div class="empty">ยังไม่มีห้องในโซนนี้ที่มี scan_code — สร้างก่อนที่ Admin › Rooms › อุปกรณ์/QR</div>
    @else
        <div class="grid">
            @foreach($rooms as $room)
                <div class="card">
                    {!! QrCode::size(180)->margin(1)->generate($baseUrl . '/s/' . $room->scan_code) !!}
                    <div class="code">{{ $room->scan_code }}</div>
                    <div class="name">{{ $room->title }}</div>
                    <div class="zone">{{ $zone->title }}</div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
