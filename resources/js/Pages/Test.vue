<script setup>
// ── หน้าจำลอง flow การจองของผู้ใช้ (ชั่วคราว) ──
// ลบไฟล์นี้ + route '/badge-preview' ใน routes/web.php เมื่อรีวิวเสร็จ
import { ref, computed } from "vue";

const scenarios = [
    { key: "auto_kiosk",   label: "ยืนยันทันที + มี QR",      confirm_type: "auto",   access_control: "1" },
    { key: "auto_staff",   label: "ยืนยันทันที + ไม่มี QR",   confirm_type: "auto",   access_control: "0" },
    { key: "manual_kiosk", label: "ต้องครบกลุ่ม + มี QR",     confirm_type: "manual", access_control: "1" },
    { key: "manual_staff", label: "ต้องครบกลุ่ม + ไม่มี QR",  confirm_type: "manual", access_control: "0" },
];
const active = ref("manual_kiosk");
const sc = computed(() => scenarios.find((s) => s.key === active.value));

const room = computed(() => ({
    title: sc.value.confirm_type === "manual" ? "ห้องประชุมกลุ่ม B-03" : "Study Room A-01",
    detail: sc.value.confirm_type === "manual" ? "ชั้น 3 · จุ 8 คน · ขั้นต่ำ 3 คน" : "ชั้น 2 · จุ 4 คน",
    confirm_type: sc.value.confirm_type,
    access_control: sc.value.access_control,
}));

// ข้อความ callout ในกล่องจอง
const bookingCallout = computed(() =>
    sc.value.confirm_type === "auto"
        ? { cls: "bg-emerald-50 border-emerald-200 text-emerald-800", icon: "fa-circle-check", text: "ยืนยันทันที — จองแล้วใช้ได้เลย ตัดโควตาทันที" }
        : { cls: "bg-amber-50 border-amber-200 text-amber-800", icon: "fa-users", text: "ต้องมีสมาชิกครบกลุ่มก่อน (แชร์ลิงก์เชิญเพื่อน) จึงจะส่งให้อนุมัติ" }
);

// สถานะหลังกดจอง + ข้อความ toast
const afterBook = computed(() => {
    if (sc.value.confirm_type === "auto")
        return { badge: "ยืนยันแล้ว", badgeCls: "bg-emerald-100 text-emerald-700 border-emerald-200", toast: "จองสำเร็จ! ✓ ยืนยันแล้ว" };
    return { badge: "รอสมาชิกครบกลุ่ม", badgeCls: "bg-amber-100 text-amber-700 border-amber-200", toast: "จองสำเร็จ — แชร์ลิงก์ให้เพื่อนเข้าร่วมให้ครบกลุ่ม" };
});

// ขั้นตอนถัดไป วันใช้งานจริง
const entryStep = computed(() => {
    const kiosk = sc.value.access_control === "1";
    const manual = sc.value.confirm_type === "manual";
    if (kiosk && manual)
        return { icon: "fa-qrcode", cls: "bg-blue-50 border-blue-200 text-blue-800", title: "วันใช้งาน — สแกน QR Code ที่หน้าห้อง", body: "สแกนด้วยรหัสนิสิต = อนุมัติ + เช็คอิน ในขั้นตอนเดียว ไม่ต้องรอเจ้าหน้าที่" };
    if (kiosk)
        return { icon: "fa-qrcode", cls: "bg-blue-50 border-blue-200 text-blue-800", title: "วันใช้งาน — สแกน QR Code ที่หน้าห้อง", body: "สแกนด้วยรหัสนิสิต แล้วเข้าใช้บริการได้เลย" };
    return { icon: "fa-bell-concierge", cls: "bg-red-50 border-red-200 text-red-700", title: "วันใช้งาน — ติดต่อเจ้าหน้าที่ก่อนเข้าใช้บริการ", body: manual ? "เจ้าหน้าที่จะตรวจสอบการจอง อนุมัติ และเช็คอินให้ที่หน้าห้อง" : "แจ้งเจ้าหน้าที่ที่เคาน์เตอร์เพื่อขอเข้าใช้ห้อง" };
});
</script>

<template>
    <div class="min-h-screen bg-slate-100 py-8 px-4">
        <div class="max-w-md mx-auto space-y-4">

            <header>
                <h1 class="text-base font-bold text-slate-900">จำลอง flow การจองของผู้ใช้</h1>
                <p class="text-xs text-slate-500 mt-0.5">เลือกประเภทห้อง แล้วดูว่าผู้ใช้เห็นอะไรในแต่ละขั้น</p>
            </header>

            <!-- scenario switcher -->
            <div class="grid grid-cols-2 gap-1.5">
                <button v-for="s in scenarios" :key="s.key" @click="active = s.key"
                    :class="active === s.key ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    class="text-[11px] font-bold px-2 py-2 rounded-lg border transition-colors">
                    {{ s.label }}
                </button>
            </div>

            <!-- STEP 1 — room card -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2.5">
                    <span class="w-5 h-5 rounded-full bg-blue-900 text-white text-[10px] font-bold grid place-content-center">1</span>
                    <span class="text-xs font-bold text-slate-700">หน้าแรก — เลือกห้องในโซน</span>
                </div>
                <div class="w-full text-left p-3.5 border border-slate-200 rounded-xl">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-slate-900">{{ room.title }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ room.detail }}</div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-1 shrink-0 max-w-[55%]">
                            <span v-if="room.confirm_type === 'auto'"
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap bg-emerald-100 text-emerald-700 border-emerald-200">
                                <i class="fa-solid fa-circle-check mr-0.5"></i>ยืนยันทันที
                            </span>
                            <span v-else
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap bg-amber-100 text-amber-700 border-amber-200">
                                <i class="fa-solid fa-users mr-0.5"></i>ต้องครบกลุ่ม
                            </span>
                            <span v-if="room.access_control === '1'"
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-blue-100 text-blue-700 border-blue-200">
                                <i class="fa-solid fa-qrcode mr-0.5"></i>แสกน QR Code เพื่อเข้าใช้บริการ
                            </span>
                            <span v-else
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-red-100 text-red-700 border-red-200">
                                <i class="fa-solid fa-bell-concierge mr-0.5"></i>ติดต่อเจ้าหน้าที่ก่อนเข้าใช้บริการ
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center text-slate-300"><i class="fa-solid fa-chevron-down"></i></div>

            <!-- STEP 2 — booking modal -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2.5">
                    <span class="w-5 h-5 rounded-full bg-blue-900 text-white text-[10px] font-bold grid place-content-center">2</span>
                    <span class="text-xs font-bold text-slate-700">กดที่ห้อง — กล่องจองเปิดขึ้น</span>
                </div>
                <div class="border border-slate-200 rounded-xl p-3.5 space-y-2.5">
                    <div class="text-xs font-bold text-slate-700">
                        <i class="mr-1 fa-solid fa-door-open text-slate-400"></i>{{ room.title }}
                    </div>
                    <div :class="bookingCallout.cls" class="flex items-start gap-2 px-3 py-2.5 border rounded-lg text-[11px]">
                        <i class="fa-solid mt-0.5 shrink-0" :class="bookingCallout.icon"></i>
                        <span>{{ bookingCallout.text }}</span>
                    </div>
                </div>
            </div>

            <div class="text-center text-slate-300"><i class="fa-solid fa-chevron-down"></i></div>

            <!-- STEP 3 — pick slots -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2.5">
                    <span class="w-5 h-5 rounded-full bg-blue-900 text-white text-[10px] font-bold grid place-content-center">3</span>
                    <span class="text-xs font-bold text-slate-700">เลือกช่วงเวลา แล้วกดยืนยัน</span>
                </div>
                <div class="border border-slate-200 rounded-xl p-3.5 space-y-2.5">
                    <div class="grid grid-cols-4 gap-1.5">
                        <span class="text-[11px] text-center py-2 border rounded-lg border-slate-200 text-slate-500">09:00</span>
                        <span class="text-[11px] text-center py-2 border rounded-lg bg-blue-600 border-blue-700 text-white font-bold">10:00</span>
                        <span class="text-[11px] text-center py-2 border rounded-lg bg-blue-600 border-blue-700 text-white font-bold">11:00</span>
                        <span class="text-[11px] text-center py-2 border rounded-lg border-slate-200 text-slate-500">12:00</span>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 text-[11px] text-blue-900">
                        <b>10:00 – 12:00 น.</b> · รวม 2 ชั่วโมง
                    </div>
                    <div class="bg-emerald-600 text-white text-xs font-bold text-center py-2 rounded-lg">
                        <i class="fa-solid fa-circle-check mr-1"></i>ยืนยันการจอง
                    </div>
                </div>
            </div>

            <div class="text-center text-slate-300"><i class="fa-solid fa-chevron-down"></i></div>

            <!-- STEP 4 — success + next step -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2.5">
                    <span class="w-5 h-5 rounded-full bg-blue-900 text-white text-[10px] font-bold grid place-content-center">4</span>
                    <span class="text-xs font-bold text-slate-700">จองสำเร็จ</span>
                </div>
                <div class="border border-emerald-200 bg-emerald-50 text-emerald-800 rounded-lg px-3 py-2.5 text-[11px] mb-2.5">
                    <i class="fa-solid fa-circle-check mr-1"></i>{{ afterBook.toast }}
                </div>

                <!-- manual: invite step -->
                <div v-if="sc.confirm_type === 'manual'" class="border border-amber-200 bg-amber-50 rounded-lg px-3 py-2.5 text-[11px] text-amber-800 mb-2.5">
                    <i class="fa-solid fa-link mr-1"></i><b>แชร์ลิงก์เชิญเพื่อน</b> — เมื่อครบ 3 คน ระบบจะเปลี่ยนสถานะเป็น "รอเจ้าหน้าที่อนุมัติ"
                    <template v-if="sc.access_control === '1'"> หรือรอไปสแกน QR หน้าห้องวันงาน</template>
                </div>

                <div :class="entryStep.cls" class="border rounded-lg px-3 py-2.5 text-[11px]">
                    <div class="font-bold mb-0.5"><i class="fa-solid mr-1" :class="entryStep.icon"></i>{{ entryStep.title }}</div>
                    <div>{{ entryStep.body }}</div>
                </div>
            </div>

            <div class="text-center text-slate-300"><i class="fa-solid fa-chevron-down"></i></div>

            <!-- STEP 5 — my bookings -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2.5">
                    <span class="w-5 h-5 rounded-full bg-blue-900 text-white text-[10px] font-bold grid place-content-center">5</span>
                    <span class="text-xs font-bold text-slate-700">หน้า "ประวัติการจองของฉัน"</span>
                </div>
                <div class="border border-slate-200 rounded-xl p-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-sm text-slate-900">{{ room.title }}</span>
                        <span :class="afterBook.badgeCls" class="text-[10px] font-semibold px-2 py-0.5 rounded-full border">
                            {{ afterBook.badge }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-500 mt-1 space-y-0.5">
                        <div><i class="fa-solid fa-calendar mr-1.5 text-slate-300"></i>วันนี้</div>
                        <div><i class="fa-solid fa-clock mr-1.5 text-slate-300"></i>10:00 – 12:00 น.</div>
                        <div :class="sc.access_control === '1' ? 'text-blue-700' : 'text-red-600'">
                            <i class="fa-solid mr-1.5" :class="sc.access_control === '1' ? 'fa-qrcode' : 'fa-bell-concierge'"></i>
                            {{ sc.access_control === '1' ? 'เข้าห้อง: สแกน QR Code ที่หน้าห้อง' : 'เข้าห้อง: ติดต่อเจ้าหน้าที่ก่อนเข้าใช้' }}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
