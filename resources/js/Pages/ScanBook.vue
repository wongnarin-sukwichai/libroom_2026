<script setup>
import { ref, computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const props = defineProps({
    scanCode:  String,
    state:     String,
    room:      { type: Object, default: null },
    booking:   { type: Object, default: null },
    curHour:   { type: Number, default: 0 },
    slots:     { type: Array, default: () => [] },
    maxHours:  { type: Number, default: 1 },
    quotaLeft: { type: Number, default: 0 },
    zoneQuota: { type: Number, default: 3 },
    window:    { type: Object, default: null },
});

const appBase  = window.APP_BASE ?? "";
const authUser = computed(() => usePage().props.auth?.user ?? null);

const hours = ref(1);
const submitting = ref(false);

const rangeLabel = computed(() => {
    const s = props.curHour;
    const e = props.curHour + hours.value;
    const p = (n) => String(n).padStart(2, "0");
    return `${p(s)}:00 – ${p(e)}:00 น.`;
});

function confirmBooking() {
    if (submitting.value) return;
    submitting.value = true;
    router.post(`${appBase}/s/${props.scanCode}/book`, { hours: hours.value }, {
        onFinish: () => (submitting.value = false),
    });
}

const STATE = {
    not_found:          { icon: "fa-link-slash",       color: "text-slate-400", title: "ไม่พบ QR นี้ในระบบ",        desc: "โค้ดบนสติกเกอร์อาจไม่ถูกต้อง หรือจุดนี้ถูกยกเลิกแล้ว" },
    room_closed:        { icon: "fa-ban",              color: "text-red-500",   title: "จุดนี้งดให้บริการชั่วคราว",  desc: "กรุณาเลือกจุดอื่น หรือติดต่อเจ้าหน้าที่" },
    busy:              { icon: "fa-user-clock",        color: "text-amber-500", title: "จุดนี้มีคนใช้อยู่",          desc: "ช่วงเวลานี้ถูกจองแล้ว ลองสแกนจุดอื่นที่ว่าง" },
    busy_self:         { icon: "fa-circle-exclamation",color: "text-amber-500", title: "คุณมีการจองอยู่แล้วตอนนี้",  desc: "คุณจองช่วงเวลานี้ไว้ที่จุดอื่นแล้ว ไม่สามารถจองซ้อนกันได้" },
    window_closed:      { icon: "fa-clock",            color: "text-orange-500",title: "อยู่นอกเวลาทำการจอง",       desc: "" },
    holiday:           { icon: "fa-calendar-xmark",    color: "text-red-500",   title: "งดให้บริการวันนี้",          desc: "เนื่องในวันหยุด กรุณากลับมาในวันทำการ" },
    quota_exceeded:    { icon: "fa-circle-exclamation",color: "text-amber-500", title: "ใช้โควตาโซนนี้ครบแล้ว",      desc: "คุณจองครบชั่วโมงสูงสุดต่อวันในโซนนี้แล้ว" },
    no_slot:           { icon: "fa-calendar-xmark",    color: "text-slate-400", title: "ไม่มีช่วงเวลาเหลือวันนี้",   desc: "หมดเวลาให้บริการของวันนี้แล้ว" },
    error:             { icon: "fa-triangle-exclamation", color: "text-slate-400", title: "เกิดข้อผิดพลาด",         desc: "กรุณาลองใหม่อีกครั้ง" },
};
const info = computed(() => STATE[props.state] ?? STATE.error);
const isCheckedIn = computed(() => props.state === "checked_in" || props.state === "already_checked_in");
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex flex-col items-center px-4 py-8">
        <div class="w-full max-w-sm">

            <!-- header -->
            <div class="text-center mb-4">
                <div class="inline-flex items-center gap-2 text-blue-900 font-bold">
                    <i class="fa-solid fa-qrcode"></i>
                    <span class="text-sm">MSU Library — สแกนเพื่อจอง</span>
                </div>
            </div>

            <!-- room header (ทุก state ที่มี room) -->
            <div v-if="room" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-3">
                <div class="text-[10px] text-slate-400 font-semibold">{{ room.loc }} › {{ room.zone }}</div>
                <div class="text-lg font-bold text-slate-900 mt-0.5">{{ room.title }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5 font-mono">{{ scanCode }}</div>
                <div v-if="room.equipment?.length" class="flex flex-wrap gap-1 mt-2">
                    <span v-for="rt in room.equipment" :key="rt.tool_id"
                        class="text-[10px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded-full flex items-center gap-0.5">
                        <i v-if="rt.icon" :class="`fa-solid ${rt.icon} text-[9px]`"></i>
                        {{ rt.name }}<template v-if="rt.quantity > 1"> ×{{ rt.quantity }}</template>
                    </span>
                </div>
            </div>

            <!-- ✅ เช็คอินสำเร็จ / เช็คอินแล้ว -->
            <div v-if="isCheckedIn" class="bg-white rounded-2xl border border-emerald-200 shadow-sm p-6 text-center">
                <div class="w-14 h-14 mx-auto rounded-full bg-emerald-100 grid place-content-center mb-3">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-600"></i>
                </div>
                <div class="text-base font-bold text-slate-900">
                    {{ state === "checked_in" ? "เช็คอินสำเร็จ" : "คุณเช็คอินจุดนี้แล้ว" }}
                </div>
                <div v-if="booking" class="mt-2 text-sm text-slate-600">
                    <i class="fa-solid fa-clock text-slate-400 mr-1"></i>{{ booking.label }}
                    <span class="text-slate-400">· {{ booking.hours }} ชม.</span>
                </div>
                <div class="text-xs text-slate-400 mt-3">เข้าใช้บริการได้เลย</div>
                <a :href="`${appBase}/my-bookings`"
                    class="mt-4 inline-block text-xs font-bold text-blue-700 hover:underline">
                    ดูการจองของฉัน <i class="fa-solid fa-chevron-right text-[9px]"></i>
                </a>
            </div>

            <!-- 🟦 หน้าจอง -->
            <div v-else-if="state === 'book'" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="text-sm font-bold text-slate-800 mb-1">จองจุดนี้ตอนนี้</div>
                <div class="text-xs text-slate-400 mb-3">เริ่ม {{ String(curHour).padStart(2,'0') }}:00 น. · เหลือโควตา {{ quotaLeft }} ชม.</div>

                <div class="text-[11px] font-bold text-slate-500 mb-1.5">จองกี่ชั่วโมง</div>
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <button v-for="h in maxHours" :key="h" type="button" @click="hours = h"
                        :class="hours === h
                            ? 'bg-blue-900 text-white border-blue-900'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-blue-400'"
                        class="border rounded-xl py-3 text-sm font-bold transition-all">
                        {{ h }} ชม.
                    </button>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-xl px-3 py-2.5 text-xs text-blue-900 mb-4">
                    <i class="fa-solid fa-clock text-blue-500 mr-1.5"></i><b>{{ rangeLabel }}</b>
                </div>

                <button type="button" @click="confirmBooking" :disabled="submitting"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold py-3 rounded-xl text-sm transition-all flex items-center justify-center gap-2">
                    <i :class="submitting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i>
                    {{ submitting ? "กำลังจอง..." : "จอง + เช็คอินเลย" }}
                </button>
                <p class="text-[10px] text-slate-400 text-center mt-2">จองแล้วเช็คอินให้อัตโนมัติ เพราะคุณอยู่ที่จุดนี้แล้ว</p>
            </div>

            <!-- ⚠️ state อื่น ๆ -->
            <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center">
                <i :class="`fa-solid ${info.icon} ${info.color}`" class="text-3xl mb-3"></i>
                <div class="text-base font-bold text-slate-900">{{ info.title }}</div>
                <div v-if="state === 'window_closed' && window" class="text-sm text-slate-600 mt-1">
                    ระบบเปิดจอง {{ window.open }} – {{ window.close }} น.
                    <div class="text-xs text-slate-400 mt-0.5">(เวลาระบบตอนนี้ {{ window.server_time }} น.)</div>
                </div>
                <div v-else-if="info.desc" class="text-sm text-slate-500 mt-1">{{ info.desc }}</div>

                <a :href="`${appBase}/`"
                    class="mt-4 inline-block text-xs font-bold text-blue-700 hover:underline">
                    <i class="fa-solid fa-house text-[10px] mr-1"></i>ไปหน้าจองปกติ
                </a>
            </div>

            <div v-if="authUser" class="text-center mt-4 text-[11px] text-slate-400">
                <i class="fa-solid fa-circle-user mr-1"></i>{{ authUser.name }}
            </div>
        </div>
    </div>
</template>
