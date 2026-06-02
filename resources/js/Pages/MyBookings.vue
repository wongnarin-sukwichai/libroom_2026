<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({ bookings: Array });

const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const today = new Date().toISOString().split('T')[0];

const upcoming = computed(() => props.bookings.filter(b => b.date >= today && b.status !== 'cancelled'));
const past     = computed(() => props.bookings.filter(b => b.date < today || b.status === 'cancelled'));

const statusConfig = {
    pending:        { label: 'รอการยืนยัน',        color: 'bg-amber-100 text-amber-700 border-amber-200' },
    waiting_confirm:{ label: 'รอเจ้าหน้าที่ยืนยัน', color: 'bg-orange-100 text-orange-700 border-orange-200' },
    confirmed:      { label: 'ยืนยันแล้ว',           color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    completed:      { label: 'เสร็จสิ้น',            color: 'bg-blue-100 text-blue-700 border-blue-200' },
    cancelled:      { label: 'ยกเลิกแล้ว',           color: 'bg-red-100 text-red-600 border-red-200' },
};

const cancelling = ref(null);

const cancelBooking = async (id, booking) => {
    if (cancelling.value) return;

    const result = await Swal.fire({
        title: 'ยืนยันการยกเลิก?',
        html: `<div class="text-sm text-slate-600">
                <div class="font-bold text-slate-900 mb-1">${booking.room_title}</div>
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
        const res = await fetch(`/booking-groups/${id}/cancel`, {
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

const handleLogout = () => router.post('/logout');
</script>

<template>
    <div class="flex flex-col min-h-screen bg-slate-50 font-sans text-slate-800">

        <!-- Top bar -->
        <div class="px-4 py-2 text-xs text-white bg-gradient-to-r from-rose-400 via-fuchsia-500 to-indigo-500">
            <div class="flex items-center justify-between mx-auto max-w-4xl">
                <span class="font-semibold text-amber-300">MSU Library — ระบบจองพื้นที่ออนไลน์</span>
                <div class="flex items-center gap-3">
                    <span class="text-amber-300"><i class="fa-solid fa-circle-user mr-1"></i>{{ authUser?.name }}</span>
                    <button @click="handleLogout" class="bg-red-600 hover:bg-red-700 px-2 py-0.5 rounded text-[10px]">ออกจากระบบ</button>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="bg-white border-b border-slate-200 shadow-sm">
            <div class="flex items-center gap-3 px-4 py-3 mx-auto max-w-4xl">
                <a href="/" class="text-slate-400 hover:text-slate-700 transition-colors">
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-sm font-bold text-slate-900">ประวัติการจองของฉัน</h1>
                    <p class="text-[11px] text-slate-400">รายการจองทั้งหมดของคุณ</p>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 mx-auto w-full max-w-4xl space-y-8">

            <!-- Upcoming -->
            <section>
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-calendar-day mr-1.5"></i>การจองที่กำลังจะมาถึง
                    <span class="ml-1.5 bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">{{ upcoming.length }}</span>
                </h2>

                <div v-if="!upcoming.length" class="py-10 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
                    <i class="fa-regular fa-calendar text-2xl mb-2 block"></i>
                    ยังไม่มีการจองที่กำลังจะมาถึง
                </div>

                <div v-else class="space-y-3">
                    <div v-for="b in upcoming" :key="b.id"
                        class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-sm text-slate-900">{{ b.room_title }}</span>
                                <span :class="statusConfig[b.status]?.color"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border">
                                    {{ statusConfig[b.status]?.label }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 space-y-0.5">
                                <div><i class="fa-solid fa-location-dot mr-1.5 text-slate-300"></i>{{ b.loc_title }} › {{ b.zone_title }}</div>
                                <div><i class="fa-solid fa-calendar mr-1.5 text-slate-300"></i>{{ formatDate(b.date) }}</div>
                                <div><i class="fa-solid fa-clock mr-1.5 text-slate-300"></i>{{ b.time_label }}</div>
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
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-clock-rotate-left mr-1.5"></i>ประวัติที่ผ่านมา
                </h2>

                <div v-if="!past.length" class="py-8 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
                    <i class="fa-solid fa-inbox text-2xl mb-2 block"></i>
                    ยังไม่มีประวัติการจอง
                </div>

                <div v-else class="space-y-2">
                    <div v-for="b in past" :key="b.id"
                        class="bg-white border border-slate-100 rounded-xl p-4 flex items-start gap-3 opacity-70">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-slate-700">{{ b.room_title }}</span>
                                <span :class="statusConfig[b.status]?.color"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border">
                                    {{ statusConfig[b.status]?.label }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-400 mt-1 space-y-0.5">
                                <div><i class="fa-solid fa-location-dot mr-1.5 text-slate-300"></i>{{ b.loc_title }} › {{ b.zone_title }}</div>
                                <div><i class="fa-solid fa-calendar mr-1.5 text-slate-300"></i>{{ formatDate(b.date) }}</div>
                                <div><i class="fa-solid fa-clock mr-1.5 text-slate-300"></i>{{ b.time_label }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
