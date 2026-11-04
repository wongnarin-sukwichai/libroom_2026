<script setup>
// หน้ายืนยัน "เจ้าหน้าที่จองแทน" — หัวหน้ากลุ่มสแกน QR บนจอเจ้าหน้าที่ แล้วกดยืนยัน/ปฏิเสธเองภายใน 10 นาที
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    token:     { type: String,  default: null },
    group:     { type: Object,  default: null },
    error:     { type: String,  default: null },
    is_leader: { type: Boolean, default: false },
});

const appBase  = window.APP_BASE ?? '';
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const busy     = ref(false);

// นับถอยหลังถึงหมดเวลายืนยัน
const now = ref(Date.now());
let timer;
onMounted(() => { timer = setInterval(() => (now.value = Date.now()), 1000); });
onUnmounted(() => clearInterval(timer));
const secondsLeft = computed(() => props.group?.expires_at
    ? Math.max(0, Math.floor((new Date(props.group.expires_at).getTime() - now.value) / 1000)) : 0);
const countdown = computed(() => `${Math.floor(secondsLeft.value / 60)}:${String(secondsLeft.value % 60).padStart(2, '0')}`);
const state = computed(() => {
    if (!props.group) return null;
    if (props.group.status === 'pending' && secondsLeft.value <= 0) return 'expired';
    return props.group.status; // pending | confirmed | cancelled | expired
});
const minutesLeftInHour = computed(() => 60 - new Date(now.value).getMinutes());

const formatDate = (s) => s ? new Date(s).toLocaleDateString('th-TH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '';

const post = async (action) => {
    const res = await fetch(`${appBase}/walkin/${props.token}/${action}`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
    });
    return [res.ok, await res.json()];
};

const accept = async () => {
    if (busy.value) return;
    busy.value = true;
    try {
        const [ok, json] = await post('accept');
        await Swal.fire({ title: ok ? 'ยืนยันแล้ว' : 'ไม่สำเร็จ', text: json.message, icon: ok ? 'success' : 'error', confirmButtonColor: '#1e3a5f' });
        if (ok) router.visit(`${appBase}/my-bookings`);
    } finally { busy.value = false; }
};

const decline = async () => {
    if (busy.value) return;
    const r = await Swal.fire({
        title: 'ปฏิเสธการจองนี้?',
        text: 'ถ้าคุณไม่ได้ขอให้เจ้าหน้าที่จองให้ กดปฏิเสธ ระบบจะยกเลิกและคืนโควตาทุกคนทันที',
        icon: 'warning', showCancelButton: true, reverseButtons: true,
        confirmButtonColor: '#dc2626', cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ปฏิเสธ', cancelButtonText: 'กลับ',
    });
    if (!r.isConfirmed) return;
    busy.value = true;
    try {
        const [ok, json] = await post('decline');
        await Swal.fire({ title: ok ? 'ปฏิเสธแล้ว' : 'ไม่สำเร็จ', text: json.message, icon: ok ? 'success' : 'error', confirmButtonColor: '#1e3a5f' });
        if (ok) router.visit(`${appBase}/my-bookings`);
    } finally { busy.value = false; }
};
</script>

<template>
    <div class="flex flex-col min-h-screen font-sans bg-slate-50 text-slate-800">
        <div class="px-4 py-2 text-xs bg-amber-400 text-slate-900">
            <div class="flex items-center justify-between max-w-lg mx-auto">
                <span class="font-bold">MSU Library — ระบบจองพื้นที่ออนไลน์</span>
                <span v-if="authUser"><i class="mr-1 fa-solid fa-circle-user"></i>{{ authUser.name }}</span>
            </div>
        </div>

        <div class="flex items-center justify-center flex-1 px-4 py-10">
            <div class="w-full max-w-md">
                <!-- ไม่พบ -->
                <div v-if="error" class="p-8 space-y-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto bg-red-100 rounded-full">
                        <i class="text-2xl text-red-500 fa-solid fa-link-slash"></i>
                    </div>
                    <p class="text-sm text-slate-500">{{ error }}</p>
                    <a :href="`${appBase}/`" class="inline-block text-xs text-blue-700 hover:underline"><i class="mr-1 fa-solid fa-house"></i>กลับหน้าหลัก</a>
                </div>

                <div v-else-if="group" class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
                    <div class="px-6 py-5 bg-amber-400 text-slate-900">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-user-plus"></i>
                            <span class="text-xs font-bold tracking-wider uppercase">เจ้าหน้าที่สร้างการจองให้คุณ</span>
                        </div>
                        <h1 class="text-base font-bold leading-tight">{{ group.room_title }}</h1>
                        <p class="text-xs mt-0.5 text-slate-700">{{ group.loc_title }} › {{ group.zone_title }}</p>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="space-y-2 text-sm text-slate-600">
                            <div><i class="w-4 mr-2 text-center fa-solid fa-calendar text-slate-400"></i>{{ formatDate(group.date) }}</div>
                            <div><i class="w-4 mr-2 text-center fa-solid fa-clock text-slate-400"></i>{{ group.time_label }}</div>
                            <div v-if="group.staff_name"><i class="w-4 mr-2 text-center fa-solid fa-user-shield text-slate-400"></i>สร้างโดยเจ้าหน้าที่: <b class="text-slate-800">{{ group.staff_name }}</b></div>
                        </div>

                        <!-- รายชื่อ -->
                        <div class="p-4 border border-blue-200 bg-blue-50 rounded-xl">
                            <div class="mb-2 text-xs font-semibold text-blue-900">ผู้ใช้ห้อง {{ group.members.length }} คน</div>
                            <ul class="space-y-1">
                                <li v-for="(m, i) in group.members" :key="i" class="flex items-center gap-1.5 text-[11px] text-blue-600 min-w-0">
                                    <i class="w-3 text-center shrink-0" :class="m.is_lead ? 'fa-solid fa-crown text-amber-500' : 'fa-solid fa-user text-blue-300'"></i>
                                    <span class="font-semibold text-blue-900 truncate">{{ m.name }}</span>
                                    <span v-if="m.is_me" class="shrink-0 text-[9px] font-bold px-1.5 rounded bg-blue-600 text-white">คุณ</span>
                                    <span class="truncate">{{ m.email }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- รอยืนยัน -->
                        <template v-if="state === 'pending'">
                            <div class="p-3 text-[12px] border rounded-lg bg-amber-50 border-amber-200 text-amber-900">
                                <i class="mr-1 fa-solid fa-triangle-exclamation"></i>
                                ชั่วโมงนี้เริ่มไปแล้ว — ใช้ห้องได้อีกประมาณ <b>{{ minutesLeftInHour }} นาที</b> (ถึง {{ String(group.end_hour).padStart(2, '0') }}:00 น.)
                                แต่ระบบหักโควตา<b>คนละ 1 ชั่วโมง</b>
                            </div>

                            <template v-if="is_leader">
                                <div class="text-xs text-center text-slate-500">
                                    กรุณายืนยันภายใน <b class="text-lg text-red-600 tabular-nums">{{ countdown }}</b> นาที
                                </div>
                                <button @click="accept" :disabled="busy"
                                    class="flex items-center justify-center w-full gap-2 py-3 text-sm font-bold text-white transition-all bg-emerald-600 rounded-xl hover:bg-emerald-700 disabled:bg-slate-300">
                                    <i :class="busy ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i> ยืนยัน — ฉันขอใช้ห้องนี้
                                </button>
                                <button @click="decline" :disabled="busy"
                                    class="w-full py-2.5 text-xs font-bold text-red-600 border border-red-200 rounded-xl hover:bg-red-50 disabled:opacity-50">
                                    ไม่ใช่ฉัน / ปฏิเสธ
                                </button>
                            </template>
                            <div v-else class="p-3 text-xs text-center border rounded-lg text-slate-600 bg-slate-50 border-slate-200">
                                <i class="mr-1 fa-solid fa-hourglass-half"></i>รอ <b>{{ group.lead_name }}</b> (หัวหน้ากลุ่ม) กดยืนยัน — เหลือ {{ countdown }} นาที
                            </div>
                        </template>

                        <div v-else-if="state === 'confirmed'" class="p-3 text-sm font-bold text-center border rounded-lg text-emerald-700 bg-emerald-50 border-emerald-200">
                            <i class="mr-1 fa-solid fa-circle-check"></i>ยืนยันแล้ว — เข้าใช้ห้องได้เลย
                        </div>
                        <div v-else-if="state === 'expired'" class="p-3 text-sm text-center text-red-600 border border-red-200 rounded-lg bg-red-50">
                            <i class="mr-1 fa-solid fa-clock"></i>หมดเวลายืนยันแล้ว — กรุณาติดต่อเจ้าหน้าที่
                        </div>
                        <div v-else class="p-3 text-sm text-center border rounded-lg text-slate-500 bg-slate-50 border-slate-200">
                            <i class="mr-1 fa-solid fa-ban"></i>รายการนี้ถูกยกเลิกแล้ว
                        </div>

                        <p class="text-center text-[11px] text-slate-400">
                            ใช้บัญชี MSU ของคุณ: <span class="font-semibold text-slate-600">{{ authUser?.email }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
