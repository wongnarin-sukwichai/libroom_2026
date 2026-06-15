<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 560px; margin: 0 auto; padding: 24px; }
        .header { background: #b91c1c; color: #fff; padding: 16px 24px; border-radius: 6px 6px 0 0; }
        .header h1 { margin: 0; font-size: 18px; }
        .body { border: 1px solid #e5e7eb; border-top: none; padding: 24px; border-radius: 0 0 6px 6px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .info-table td { padding: 6px 0; vertical-align: top; }
        .info-table td:first-child { color: #6b7280; width: 130px; }
        .footer { margin-top: 24px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>แจ้งยกเลิกการจองพื้นที่</h1>
    </div>
    <div class="body">
        <p>เรียน คุณ{{ $group->lead->name }}</p>

        <p>
            ระบบได้ยกเลิกการจองพื้นที่ของคุณโดยอัตโนมัติ เนื่องจากเจ้าหน้าที่ไม่ได้ยืนยันการจองภายใน
            <strong>15 นาที</strong> หลังเวลาเข้าใช้บริการที่กำหนด
        </p>

        <table class="info-table">
            <tr>
                <td>ห้อง</td>
                <td><strong>{{ $group->room->title }}</strong></td>
            </tr>
            <tr>
                <td>วันที่</td>
                <td>{{ \Carbon\Carbon::parse($group->date)->translatedFormat('j F Y') }}</td>
            </tr>
            <tr>
                <td>เวลาที่จอง</td>
                <td>{{ str_pad($group->time_id, 2, '0', STR_PAD_LEFT) }}:00 น.</td>
            </tr>
            <tr>
                <td>สถานะ</td>
                <td style="color: #b91c1c;"><strong>ยกเลิก</strong></td>
            </tr>
        </table>

        <p>
            หากมีข้อสงสัย กรุณาติดต่อเจ้าหน้าที่สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม
        </p>
    </div>
    <div class="footer">
        อีเมลนี้ถูกส่งโดยอัตโนมัติจากระบบ LibRoom — กรุณาอย่าตอบกลับ
    </div>
</div>
</body>
</html>
