<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface BookingRow {
    ids: number[];
    date: string;
    time_label: string;
    hours: number;
    status: string;
    confirm_type: 'auto' | 'manual';
    room_title: string;
    zone_title: string;
    loc_title: string;
    member_name: string;
    member_email: string;
}

interface Paginated {
    data: BookingRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    pending_count: number;
}

const emit = defineEmits<{ 'pending-count': [n: number] }>();

const paginated = ref<Paginated>({
    data: [], current_page: 1, last_page: 1,
    total: 0, from: 0, to: 0, pending_count: 0,
});
const loading      = ref(false);
const filterStatus = ref('');
const filterDate   = ref(new Date().toISOString().split('T')[0]);

const statusConfig: Record<string, { label: string; cls: string }> = {
    pending:         { label: 'รอยืนยัน',           cls: 'bg-amber-100 text-amber-700 border-amber-200 animate-pulse' },
    waiting_confirm: { label: 'รอเจ้าหน้าที่',       cls: 'bg-orange-100 text-orange-700 border-orange-200' },
    confirmed:       { label: 'ยืนยันแล้ว',           cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    completed:       { label: 'เสร็จสิ้น',            cls: 'bg-blue-100 text-blue-700 border-blue-200' },
    cancelled:       { label: 'ยกเลิกแล้ว',           cls: 'bg-red-100 text-red-600 border-red-200' },
};

const pageNumbers = computed(() => {
    const pages: (number | '...')[] = [];
    const last = paginated.value.last_page;
    const cur  = paginated.value.current_page;
    if (last <= 7) { for (let i = 1; i <= last; i++) pages.push(i); }
    else {
        pages.push(1);
        if (cur > 3) pages.push('...');
        for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
        if (cur < last - 2) pages.push('...');
        pages.push(last);
    }
    return pages;
});

async function fetch(page = 1) {
    loading.value = true;
    try {
        const res = await axios.get('/admin/bookings', {
            params: { page, status: filterStatus.value || undefined, date: filterDate.value || undefined  },
        });
        paginated.value = res.data;
        emit('pending-count', res.data.pending_count);
    } finally {
        loading.value = false;
    }
}

async function approve(row: BookingRow) {
    const hours = row.hours > 1 ? ` (${row.hours} ชม.)` : '';
    const result = await Swal.fire({
        title: 'อนุมัติการจอง?',
        html: `<div class="text-sm"><b>${row.room_title}</b><br>${row.date} • ${row.time_label}${hours}<br><span class="text-slate-400">${row.member_name}</span></div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'อนุมัติ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    await axios.post('/admin/bookings/approve', { ids: row.ids });
    Swal.fire({ title: 'อนุมัติสำเร็จ', icon: 'success', timer: 1200, showConfirmButton: false });
    fetch(paginated.value.current_page);
}

async function reject(row: BookingRow) {
    const hours = row.hours > 1 ? ` (${row.hours} ชม.)` : '';
    const result = await Swal.fire({
        title: 'ปฏิเสธการจอง?',
        html: `<div class="text-sm"><b>${row.room_title}</b><br>${row.date} • ${row.time_label}${hours}<br><span class="text-slate-400">${row.member_name}</span></div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ปฏิเสธ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    await axios.post('/admin/bookings/reject', { ids: row.ids });
    Swal.fire({ title: 'ปฏิเสธเรียบร้อย', icon: 'success', timer: 1200, showConfirmButton: false });
    fetch(paginated.value.current_page);
}

onMounted(() => fetch());
</script>

<template>
    <div class="space-y-4">
        <!-- Filter bar -->
        <div class="flex flex-wrap items-center gap-3">
            <select v-model="filterStatus" @change="fetch(1)"
                class="text-xs px-3 py-2 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">ทุกสถานะ</option>
                <option value="pending">รอยืนยัน</option>
                <option value="confirmed">ยืนยันแล้ว</option>
                <option value="cancelled">ยกเลิกแล้ว</option>
                <option value="completed">เสร็จสิ้น</option>
            </select>
            <input v-model="filterDate" @change="fetch(1)" type="date"
                class="text-xs px-3 py-2 border border-slate-200 rounded-xl bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            <button @click="filterStatus = ''; filterDate = ''; fetch(1)"
                class="text-xs px-3 py-2 border border-slate-200 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-rotate-right mr-1"></i>รีเซ็ต
            </button>
            <span class="ml-auto text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1.5 rounded-xl font-bold">
                {{ paginated.pending_count }} รายการรอดำเนินการ
            </span>
        </div>

        <!-- Table -->
        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">คำขอจองทั้งหมด</h3>
                    <p class="text-xs text-slate-400 mt-0.5">อนุมัติหรือปฏิเสธรายการที่ confirm_type = manual</p>
                </div>
            </div>

            <div v-if="loading" class="py-16 text-center text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
            </div>

            <div v-else>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider">
                                <th class="p-4">สมาชิก</th>
                                <th class="p-4">ห้อง / โซน</th>
                                <th class="p-4">วันและเวลา</th>
                                <th class="p-4 text-center">สถานะ</th>
                                <th class="p-4 text-right">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                            <tr v-for="row in paginated.data" :key="row.ids[0]"
                                class="transition-colors hover:bg-slate-50/70">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900">{{ row.member_name || '—' }}</div>
                                    <div class="text-[10px] text-slate-400">{{ row.member_email }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-slate-800">{{ row.room_title }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ row.loc_title }} › {{ row.zone_title }}</div>
                                </td>
                                <td class="p-4">
                                    <div>{{ row.date }}</div>
                                    <div class="font-bold text-slate-900 mt-0.5">
                                        <i class="fa-solid fa-clock mr-1 text-slate-400"></i>{{ row.time_label }}
                                    </div>
                                    <span v-if="row.hours > 1"
                                        class="text-[10px] bg-blue-50 text-blue-600 border border-blue-200 px-1.5 py-0.5 rounded-full font-medium">
                                        {{ row.hours }} ชม.
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <span :class="statusConfig[row.status]?.cls"
                                        class="text-[10px] font-bold px-2.5 py-1 rounded-full border inline-flex items-center gap-1">
                                        {{ statusConfig[row.status]?.label ?? row.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div v-if="row.status === 'pending' && row.confirm_type === 'manual'"
                                        class="flex justify-end gap-1.5">
                                        <button @click="approve(row)"
                                            class="bg-green-600 hover:bg-green-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] transition-all flex items-center gap-1">
                                            <i class="fa-solid fa-check"></i> อนุมัติ
                                        </button>
                                        <button @click="reject(row)"
                                            class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 transition-all">
                                            ปฏิเสธ
                                        </button>
                                    </div>
                                    <span v-else class="text-[10px] text-slate-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!paginated.data.length">
                                <td colspan="5" class="py-14 text-center text-slate-400 text-xs">
                                    <i class="fa-solid fa-inbox text-2xl mb-2 block"></i>
                                    ไม่มีรายการ
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="paginated.last_page > 1"
                    class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50/60">
                    <p class="text-[11px] text-slate-400">
                        แสดง {{ paginated.from }}–{{ paginated.to }} จาก {{ paginated.total }} รายการ
                    </p>
                    <div class="flex items-center gap-1">
                        <button @click="fetch(paginated.current_page - 1)"
                            :disabled="paginated.current_page === 1"
                            class="px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <template v-for="p in pageNumbers" :key="p">
                            <span v-if="p === '...'" class="px-1 text-xs text-slate-400">…</span>
                            <button v-else @click="fetch(p as number)"
                                :class="p === paginated.current_page
                                    ? 'bg-blue-900 text-white border-blue-900'
                                    : 'text-slate-600 border-slate-200 hover:bg-slate-100'"
                                class="w-7 h-7 text-xs rounded-lg border font-medium">
                                {{ p }}
                            </button>
                        </template>
                        <button @click="fetch(paginated.current_page + 1)"
                            :disabled="paginated.current_page === paginated.last_page"
                            class="px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
