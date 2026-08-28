<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface TimeRow {
    id: number;
    title: string;
    start: string;
    end: string;
    total: number;
}

const times     = ref<TimeRow[]>([]);
const loading   = ref(false);
const showModal = ref(false);
const isEditing = ref(false);
const saving    = ref(false);
const formError = ref('');
const form      = ref({ id: 0, title: '', start: '09:00', end: '19:00' });

// ── หน้าต่างเวลาเปิดให้จอง (global) ───────────────────────────────
interface BookingWindow {
    enabled: boolean;
    open: string;
    close: string;
    is_open_now: boolean;
    server_time: string;
}
const bw         = ref<BookingWindow>({ enabled: true, open: '06:00', close: '19:00', is_open_now: true, server_time: '' });
const bwLoading  = ref(false);
const bwSaving   = ref(false);
const bwError    = ref('');
const bwExpanded = ref(false);   // card ยุบไว้เป็นค่าเริ่มต้น (ไม่ได้แก้บ่อย)

async function fetchWindow() {
    bwLoading.value = true;
    try {
        const res = await axios.get('/admin/settings');
        bw.value = res.data;
    } finally {
        bwLoading.value = false;
    }
}

async function saveWindow() {
    bwError.value  = '';
    bwSaving.value = true;
    try {
        const res = await axios.put('/admin/settings', {
            booking_window_enabled: bw.value.enabled,
            booking_open_time:      bw.value.open,
            booking_close_time:     bw.value.close,
        });
        bw.value = res.data;
        Swal.fire({ title: 'บันทึกแล้ว', icon: 'success', timer: 1200, showConfirmButton: false });
    } catch (err: any) {
        const errors = err.response?.data?.errors;
        bwError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (err.response?.data?.message ?? 'เกิดข้อผิดพลาด');
    } finally {
        bwSaving.value = false;
    }
}

async function fetchTimes() {
    loading.value = true;
    try {
        const res = await axios.get('/admin/times');
        times.value = res.data;
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    isEditing.value = false;
    form.value      = { id: 0, title: '', start: '09:00', end: '19:00' };
    formError.value = '';
    showModal.value = true;
}

function openEdit(t: TimeRow) {
    isEditing.value = true;
    form.value      = { id: t.id, title: t.title, start: t.start, end: t.end };
    formError.value = '';
    showModal.value = true;
}

async function save() {
    formError.value = '';
    saving.value    = true;
    try {
        if (isEditing.value) {
            const res = await axios.put(`/admin/times/${form.value.id}`, form.value);
            const idx = times.value.findIndex(t => t.id === form.value.id);
            if (idx !== -1) times.value[idx] = res.data;
        } else {
            const res = await axios.post('/admin/times', form.value);
            times.value.push(res.data);
        }
        showModal.value = false;
    } catch (err: any) {
        const errors = err.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (err.response?.data?.message ?? 'เกิดข้อผิดพลาด');
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(t: TimeRow) {
    const result = await Swal.fire({
        title: 'ลบ config นี้?',
        html: `<b>${t.title}</b><br><span style="font-size:12px;color:#94a3b8">${t.start} – ${t.end} น. (${t.total} สล็อต)</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;

    try {
        await axios.delete(`/admin/times/${t.id}`);
        times.value = times.value.filter(x => x.id !== t.id);
        Swal.fire({ title: 'ลบเรียบร้อย', icon: 'success', timer: 1200, showConfirmButton: false });
    } catch (err: any) {
        Swal.fire('ไม่สามารถลบได้', err.response?.data?.message ?? 'เกิดข้อผิดพลาด', 'error');
    }
}

onMounted(() => { fetchWindow(); fetchTimes(); });
</script>

<template>
    <div class="space-y-5">
        <!-- หน้าต่างเวลาเปิดให้จอง (global) — ยุบ/ขยายได้ -->
        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <button type="button" @click="bwExpanded = !bwExpanded"
                class="flex items-center w-full gap-3 p-5 text-left transition-colors hover:bg-slate-50">
                <i class="fa-solid fa-door-open text-blue-500 shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold text-slate-900">ช่วงเวลาเปิดให้จอง (ทั้งระบบ)</h3>
                    <p class="text-xs text-slate-400 mt-0.5 truncate">
                        นอกช่วงเวลานี้ ผู้ใช้จะกดจองไม่ได้ (เจ้าหน้าที่จองหลังบ้านได้ตามปกติ)
                    </p>
                </div>
                <span v-if="!bwLoading"
                    class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full border"
                    :class="!bw.enabled
                        ? 'bg-slate-100 text-slate-500 border-slate-200'
                        : (bw.is_open_now
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                            : 'bg-red-50 text-red-600 border-red-200')">
                    {{ !bw.enabled ? 'ปิดการจำกัด' : (bw.is_open_now ? `${bw.open}–${bw.close}` : `${bw.open}–${bw.close} · ปิดอยู่`) }}
                </span>
                <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform shrink-0"
                    :class="{ 'rotate-180': bwExpanded }"></i>
            </button>

            <div v-show="bwExpanded" class="border-t border-slate-200">
            <div v-if="bwLoading" class="py-10 text-center text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
            </div>

            <form v-else @submit.prevent="saveWindow" class="p-5 space-y-4">
                <div v-if="bwError"
                    class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ bwError }}
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input v-model="bw.enabled" type="checkbox"
                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    <span class="text-xs font-bold text-slate-700">เปิดใช้งานการจำกัดเวลา</span>
                </label>

                <div class="grid grid-cols-2 gap-3 max-w-xs">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">เปิดจองเวลา</label>
                        <input v-model="bw.open" type="time" required :disabled="!bw.enabled"
                            class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-slate-100 disabled:text-slate-400" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">ปิดจองเวลา</label>
                        <input v-model="bw.close" type="time" required :disabled="!bw.enabled"
                            class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-slate-100 disabled:text-slate-400" />
                    </div>
                </div>

                <div class="flex items-center gap-2 text-[11px]">
                    <span class="text-slate-400">เวลาเซิร์ฟเวอร์ตอนนี้: <b class="text-slate-600">{{ bw.server_time }}</b></span>
                    <span v-if="!bw.enabled"
                        class="bg-slate-100 text-slate-500 border border-slate-200 font-bold px-2 py-0.5 rounded-full">ปิดการจำกัด</span>
                    <span v-else-if="bw.is_open_now"
                        class="bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold px-2 py-0.5 rounded-full">ขณะนี้เปิดจอง</span>
                    <span v-else
                        class="bg-red-50 text-red-600 border border-red-200 font-bold px-2 py-0.5 rounded-full">ขณะนี้ปิดจอง</span>
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" :disabled="bwSaving"
                        class="px-4 py-2 text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition-colors disabled:opacity-60 flex items-center gap-1.5">
                        <i v-if="bwSaving" class="fa-solid fa-spinner animate-spin"></i> บันทึก
                    </button>
                </div>
            </form>
            </div>
        </div>

        <!-- Header -->
        <div class="p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <h3 class="text-base font-bold">จัดการ Config เวลาให้บริการ</h3>
            <p class="mt-1 text-xs text-slate-200">
                แต่ละ config กำหนดช่วง slot ที่ zone จะใช้งาน — zone เลือก config แยกสำหรับวันธรรมดาและวันหยุด
            </p>
        </div>

        <!-- Table -->
        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">รายการ Config เวลา</h3>
                    <p class="text-xs text-slate-400 mt-0.5">ใช้กำหนด slot ของแต่ละ zone</p>
                </div>
                <button @click="openAdd"
                    class="flex items-center gap-1.5 bg-blue-900 hover:bg-blue-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors">
                    <i class="fa-solid fa-plus"></i> เพิ่ม Config
                </button>
            </div>

            <div v-if="loading" class="py-12 text-center text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider">
                            <th class="p-4">#</th>
                            <th class="p-4">ชื่อ Config</th>
                            <th class="p-4">เวลา</th>
                            <th class="p-4 text-center">จำนวนสล็อต</th>
                            <th class="p-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                        <tr v-for="t in times" :key="t.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="p-4 text-slate-400">{{ t.id }}</td>
                            <td class="p-4 font-semibold text-slate-900">{{ t.title }}</td>
                            <td class="p-4">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-clock text-blue-400 text-[10px]"></i>
                                    {{ t.start }} – {{ t.end }} น.
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                    {{ t.total }} สล็อต
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEdit(t)"
                                        class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors" title="แก้ไข">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button @click="confirmDelete(t)"
                                        class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition-colors" title="ลบ">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!times.length">
                            <td colspan="5" class="py-12 text-center text-slate-400 text-xs">
                                <i class="fa-solid fa-clock text-2xl mb-2 block text-slate-300"></i>
                                ยังไม่มี config เวลา
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notice -->
        <div class="flex items-start gap-3 p-4 border bg-amber-50 border-amber-200 rounded-xl">
            <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 shrink-0"></i>
            <p class="text-xs text-amber-800">
                การลบ config จะทำได้เฉพาะ config ที่ไม่มี zone ใช้งานอยู่เท่านั้น
                หากต้องการแก้ไขเวลาของ zone ให้ไปที่เมนูจัดการพื้นที่
            </p>
        </div>

        <!-- Modal -->
        <Transition name="fade">
            <div v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="showModal = false">
                <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between p-5 bg-blue-900 text-white">
                        <h3 class="text-sm font-bold">{{ isEditing ? 'แก้ไข Config' : 'เพิ่ม Config เวลา' }}</h3>
                        <button @click="showModal = false" class="text-slate-300 hover:text-white">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                    <form @submit.prevent="save" class="p-6 space-y-4">
                        <div v-if="formError"
                            class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ formError }}
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ชื่อ Config</label>
                            <input v-model="form.title" type="text" required
                                placeholder="เช่น จันทร์-ศุกร์ (ปกติ)"
                                class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">เวลาเริ่ม</label>
                                <input v-model="form.start" type="time" required
                                    class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">เวลาสิ้นสุด</label>
                                <input v-model="form.end" type="time" required
                                    class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400">
                            จำนวนสล็อต:
                            <span class="font-bold text-blue-700">
                                {{ Math.max(0, parseInt(form.end) - parseInt(form.start)) }} สล็อต
                            </span>
                        </p>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                                ยกเลิก
                            </button>
                            <button type="submit" :disabled="saving"
                                class="px-4 py-2 text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition-colors disabled:opacity-60 flex items-center gap-1.5">
                                <i v-if="saving" class="fa-solid fa-spinner animate-spin"></i>
                                {{ isEditing ? 'บันทึก' : 'เพิ่ม' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
