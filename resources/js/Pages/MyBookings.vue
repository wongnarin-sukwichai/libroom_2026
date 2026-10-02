<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({ bookings: Array });

const appBase  = window.APP_BASE ?? '';
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const today = new Date().toISOString().split('T')[0];

const upcoming = computed(() => props.bookings.filter(b => b.date >= today && b.status !== 'cancelled'));

// ประวัติที่ผ่านมา — เอาแค่ 10 รายการล่าสุด (bookings มาจาก backend เรียง date/time_id ล่าสุดก่อนอยู่แล้ว) แบ่งหน้าละ 5
const PAST_LIMIT    = 10;
const PAST_PER_PAGE = 5;
const pastPage = ref(1);

const pastLatest  = computed(() => props.bookings.filter(b => b.date < today || b.status === 'cancelled').slice(0, PAST_LIMIT));
const pastTotalPages = computed(() => Math.max(1, Math.ceil(pastLatest.value.length / PAST_PER_PAGE)));
const past = computed(() => {
    const start = (pastPage.value - 1) * PAST_PER_PAGE;
    return pastLatest.value.slice(start, start + PAST_PER_PAGE);
});

const statusConfig = {
    pending:        { label: 'รอการยืนยัน',        color: 'bg-amber-100 text-amber-700 border-amber-200' },
    waiting_confirm:{ label: 'รอเจ้าหน้าที่ยืนยัน', color: 'bg-orange-100 text-orange-700 border-orange-200' },
    confirmed:      { label: 'ยืนยันแล้ว',           color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    cancelled:      { label: 'ยกเลิกแล้ว',           color: 'bg-red-100 text-red-600 border-red-200' },
};

const cancelling = ref(null);

const cancelBooking = async (id, booking) => {
    if (cancelling.value) return;

    const result = await Swal.fire({
        title: 'ยืนยันการยกเลิก?',
        html: `<div class="text-sm text-slate-600">
                <div class="mb-1 font-bold text-slate-900">${booking.room_title}</div>
                <div>${booking.time_label} • ${booking.date}</div>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor:  '#94a3b8',
        confirmButtonText:  'ยืนยัน ยกเลิกการจอง',
        cancelButtonText:   'ไม่ยกเลิก',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    cancelling.value = id;
    try {
        const res = await fetch(`${window.APP_BASE ?? ''}/booking-groups/${id}/cancel`, {
            method: 'POST',
            headers: {
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
        });
        if (res.ok) router.reload({ only: ['bookings'] });
    } finally {
        cancelling.value = null;
    }
};

const formatDate = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('th-TH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
};

const handleLogout = () => router.post(`${window.APP_BASE ?? ''}/logout`);

const copyLink = (url) => {
    navigator.clipboard.writeText(url);
};
</script>

<template>
    <div class="flex flex-col min-h-screen font-sans bg-slate-50 text-slate-800">

        <!-- Header (โทนสีเดียวกับหน้าแรก — พื้นเหลือง amber ตัวหนังสือดำ) -->
        <header class="sticky top-0 z-40 bg-white border-b shadow-sm">
            <div class="flex items-center justify-between max-w-4xl gap-3 px-4 py-3 mx-auto">
                <div class="flex items-center min-w-0 gap-3">
                    <a :href="`${appBase}/`" class="transition-colors text-amber-400 hover:text-amber-300 shrink-0">
                        <i class="text-sm fa-solid fa-chevron-left"></i>
                    </a>
                    <div class="min-w-0">
                        <h1 class="text-sm font-bold text-amber-400">ประวัติการจองของฉัน</h1>
                        <p class="text-[11px] text-slate-900">รายการจองทั้งหมดของคุณ</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="hidden text-xs font-bold text-slate-900 sm:inline">
                        <i class="mr-1 fa-solid fa-circle-user text-amber-400"></i>{{ authUser?.name }}
                    </span>
                    <button @click="handleLogout"
                        class="px-3 py-2 text-[11px] font-bold transition-all rounded-lg bg-amber-400/80 hover:bg-amber-200 hover:text-red-600 text-slate-900">
                        ออกจากระบบ
                    </button>
                </div>
            </div>
        </header>

        <main class="flex-1 w-full max-w-4xl px-4 py-6 mx-auto space-y-8">

            <!-- Upcoming -->
            <section>
                <h2 class="mb-3 text-xs font-bold tracking-wider uppercase text-slate-500">
                    <i class="fa-solid fa-calendar-day mr-1.5"></i>การจองที่กำลังจะมาถึง
                    <span class="ml-1.5 bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">{{ upcoming.length }}</span>
                </h2>

                <div v-if="!upcoming.length" class="py-10 text-xs text-center border border-dashed text-slate-400 border-slate-200 rounded-xl">
                    <i class="block mb-2 text-2xl fa-regular fa-calendar"></i>
                    ยังไม่มีการจองที่กำลังจะมาถึง
                </div>

                <div v-else class="space-y-3">
                    <div v-for="b in upcoming" :key="b.id"
                        class="flex items-start justify-between gap-3 p-4 bg-white border shadow-sm border-slate-200 rounded-xl">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-900">{{ b.room_title }}</span>
                                <span :class="statusConfig[b.status]?.color"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border">
                                    {{ statusConfig[b.status]?.label }}
                                </span>
                                <span v-if="!b.is_leader"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-indigo-100 text-indigo-700 border-indigo-200">
                                    <i class="fa-solid fa-user-group mr-0.5"></i>เข้าร่วม
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 space-y-0.5">
                                <div><i class="fa-solid fa-location-dot mr-1.5 text-slate-300"></i>{{ b.loc_title }} › {{ b.zone_title }}</div>
                                <div><i class="fa-solid fa-calendar mr-1.5 text-slate-300"></i>{{ formatDate(b.date) }}</div>
                                <div><i class="fa-solid fa-clock mr-1.5 text-slate-300"></i>{{ b.time_label }}</div>
                                <div v-if="!b.is_leader">
                                    <i class="fa-solid fa-user-tie mr-1.5 text-slate-300"></i>หัวหน้ากลุ่ม: {{ b.lead_name ?? '—' }}
                                </div>
                                <div v-if="b.status === 'pending' && b.member_count < b.min_capacity">
                                    <i class="fa-solid fa-users mr-1.5 text-slate-300"></i>
                                    <span class="font-semibold text-amber-600">สมาชิก {{ b.member_count }}/{{ b.min_capacity }} คน</span>
                                </div>
                            </div>

                            <!-- Join link สำหรับ leader ที่ลืม copy -->
                            <div v-if="b.join_url" class="flex items-center gap-2 mt-2">
                                <input :value="b.join_url" readonly
                                    class="flex-1 text-[10px] px-2 py-1 border border-slate-200 rounded bg-slate-50 text-slate-500 truncate" />
                                <button @click="copyLink(b.join_url)"
                                    class="shrink-0 text-[10px] font-bold px-2 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50 transition-all flex items-center gap-1">
                                    <i class="fa-solid fa-copy"></i> คัดลอก
                                </button>
                            </div>
                        </div>
                        <button v-if="b.can_cancel"
                            @click="cancelBooking(b.id, b)"
                            :disabled="cancelling === b.id"
                            class="shrink-0 text-[11px] font-bold px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-all disabled:opacity-50">
                            <i :class="cancelling === b.id ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-xmark'" class="mr-1"></i>
                            {{ cancelling === b.id ? 'กำลังยกเลิก...' : 'ยกเลิก' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Past -->
            <section>
                <h2 class="mb-3 text-xs font-bold tracking-wider uppercase text-slate-500">
                    <i class="fa-solid fa-clock-rotate-left mr-1.5"></i>ประวัติที่ผ่านมา
                    <span class="ml-1.5 normal-case font-normal tracking-normal text-slate-400">(แสดง 10 รายการล่าสุด)</span>
                </h2>

                <div v-if="!past.length" class="py-8 text-xs text-center border border-dashed text-slate-400 border-slate-200 rounded-xl">
                    <i class="block mb-2 text-2xl fa-solid fa-inbox"></i>
                    ยังไม่มีประวัติการจอง
                </div>

                <div v-else class="space-y-2">
                    <div v-for="b in past" :key="b.id"
                        class="flex items-start gap-3 p-4 bg-white border border-slate-100 rounded-xl opacity-70">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-slate-700">{{ b.room_title }}</span>
                                <span :class="statusConfig[b.status]?.color"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border">
                                    {{ statusConfig[b.status]?.label }}
                                </span>
                                <span v-if="!b.is_leader"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-indigo-100 text-indigo-700 border-indigo-200">
                                    <i class="fa-solid fa-user-group mr-0.5"></i>เข้าร่วม
                                </span>
                            </div>
                            <div class="text-xs text-slate-400 mt-1 space-y-0.5">
                                <div><i class="fa-solid fa-location-dot mr-1.5 text-slate-300"></i>{{ b.loc_title }} › {{ b.zone_title }}</div>
                                <div><i class="fa-solid fa-calendar mr-1.5 text-slate-300"></i>{{ formatDate(b.date) }}</div>
                                <div><i class="fa-solid fa-clock mr-1.5 text-slate-300"></i>{{ b.time_label }}</div>
                                <div v-if="!b.is_leader">
                                    <i class="fa-solid fa-user-tie mr-1.5 text-slate-300"></i>หัวหน้ากลุ่ม: {{ b.lead_name ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pastLatest.length > PAST_PER_PAGE" class="flex items-center justify-center gap-2 mt-4">
                    <button @click="pastPage--" :disabled="pastPage === 1"
                        class="w-8 h-8 transition-all border rounded-lg border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed">
                        <i class="text-xs fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="text-xs font-bold text-slate-500">หน้า {{ pastPage }} / {{ pastTotalPages }}</span>
                    <button @click="pastPage++" :disabled="pastPage === pastTotalPages"
                        class="w-8 h-8 transition-all border rounded-lg border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed">
                        <i class="text-xs fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </section>
        </main>
    </div>
</template>
