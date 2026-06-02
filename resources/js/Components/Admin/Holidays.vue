<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface HolidayRow {
    id: number;
    d: string;
    m: string;
    detail: string;
    type: 'national' | 'library';
    date_label: string;
}

const holidays       = ref<HolidayRow[]>([]);
const loading        = ref(false);
const showModal      = ref(false);
const saving         = ref(false);
const formError      = ref('');
const form           = ref({ d: '', m: '', detail: '', type: 'national' });

const nationalCount  = computed(() => holidays.value.filter(h => h.type === 'national').length);
const libraryCount   = computed(() => holidays.value.filter(h => h.type === 'library').length);

const thMonths = [
    { v: '1', l: 'มกราคม' }, { v: '2', l: 'กุมภาพันธ์' }, { v: '3', l: 'มีนาคม' },
    { v: '4', l: 'เมษายน' }, { v: '5', l: 'พฤษภาคม' },    { v: '6', l: 'มิถุนายน' },
    { v: '7', l: 'กรกฎาคม' }, { v: '8', l: 'สิงหาคม' },   { v: '9', l: 'กันยายน' },
    { v: '10', l: 'ตุลาคม' }, { v: '11', l: 'พฤศจิกายน' }, { v: '12', l: 'ธันวาคม' },
];

async function fetchHolidays() {
    loading.value = true;
    try {
        const res    = await axios.get('/admin/holidays');
        holidays.value = res.data.holidays;
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    form.value  = { d: '', m: '', detail: '', type: 'national' };
    formError.value = '';
    showModal.value = true;
}

async function save() {
    formError.value = '';
    saving.value = true;
    try {
        const res = await axios.post('/admin/holidays', form.value);
        holidays.value.push(res.data);
        holidays.value.sort((a, b) => +a.m - +b.m || +a.d - +b.d);
        showModal.value = false;
    } catch (err: any) {
        formError.value = err.response?.data?.message ?? 'เกิดข้อผิดพลาด';
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(h: HolidayRow) {
    const result = await Swal.fire({
        title: 'ลบวันหยุด?',
        html: `<b>${h.detail}</b><br><span style="font-size:12px;color:#94a3b8">วันที่ ${h.date_label} ทุกปี</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    await axios.delete(`/admin/holidays/${h.id}`);
    holidays.value = holidays.value.filter(x => x.id !== h.id);
    Swal.fire({ title: 'ลบเรียบร้อย', icon: 'success', timer: 1200, showConfirmButton: false });
}

onMounted(() => fetchHolidays());
</script>

<template>
    <div class="space-y-5">
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-red-500 rounded-xl bg-red-50 shrink-0">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">วันหยุดในระบบ</p>
                    <p class="text-xl font-bold text-slate-900">{{ holidays.length }} วัน</p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-blue-600 rounded-xl bg-blue-50 shrink-0">
                    <i class="fa-solid fa-flag"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">วันหยุดราชการ</p>
                    <p class="text-xl font-bold text-slate-900">{{ nationalCount }} วัน</p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-50 text-amber-600 shrink-0">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">ปิดปรับปรุง (ห้องสมุด)</p>
                    <p class="text-xl font-bold text-slate-900">{{ libraryCount }} ครั้ง</p>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">ตารางวันหยุดและปิดให้บริการ</h3>
                    <p class="text-xs text-slate-500 mt-0.5">ระบบจะปิดรับการจองในวันเหล่านี้โดยอัตโนมัติ</p>
                </div>
                <button @click="openAdd"
                    class="flex items-center gap-1.5 bg-blue-900 hover:bg-blue-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors">
                    <i class="fa-solid fa-plus"></i> เพิ่มวันหยุด
                </button>
            </div>

            <div v-if="loading" class="py-12 text-center text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
            </div>

            <div v-else-if="!holidays.length" class="py-12 text-center text-xs text-slate-400">
                <i class="fa-solid fa-calendar-check text-2xl mb-2 block text-slate-300"></i>
                ยังไม่มีวันหยุดในระบบ
            </div>

            <div v-else class="divide-y divide-slate-100">
                <div v-for="h in holidays" :key="h.id"
                    class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50/70 transition-colors">
                    <div class="flex items-center gap-3">
                        <div :class="h.type === 'national' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                            class="flex items-center justify-center w-8 h-8 text-xs rounded-lg shrink-0">
                            <i :class="h.type === 'national' ? 'fa-solid fa-flag' : 'fa-solid fa-wrench'"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-900">{{ h.detail }}</p>
                            <p class="text-[11px] text-slate-400">
                                <i class="mr-1 fa-regular fa-calendar text-slate-300"></i>
                                วันที่ {{ h.date_label }} ของทุกปี
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="h.type === 'national' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                            class="text-[10px] font-bold px-2 py-0.5 rounded border hidden sm:inline-block">
                            {{ h.type === 'national' ? 'ราชการ' : 'ห้องสมุด' }}
                        </span>
                        <button @click="confirmDelete(h)"
                            class="p-1 transition-colors text-slate-400 hover:text-red-500" title="ลบ">
                            <i class="text-xs fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="fade">
            <div v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="showModal = false">
                <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between p-5 bg-blue-900 text-white">
                        <h3 class="text-sm font-bold">เพิ่มวันหยุด</h3>
                        <button @click="showModal = false" class="text-slate-300 hover:text-white">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                    <form @submit.prevent="save" class="p-6 space-y-4">
                        <div v-if="formError"
                            class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ formError }}
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">วันที่</label>
                                <input v-model="form.d" type="number" min="1" max="31" required
                                    placeholder="1–31"
                                    class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">เดือน</label>
                                <select v-model="form.m" required
                                    class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">เลือกเดือน</option>
                                    <option v-for="mo in thMonths" :key="mo.v" :value="mo.v">{{ mo.l }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ชื่อวันหยุด</label>
                            <input v-model="form.detail" type="text" required placeholder="เช่น วันปีใหม่"
                                class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">ประเภท</label>
                            <select v-model="form.type"
                                class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="national">ราชการ — วันหยุดนักขัตฤกษ์</option>
                                <option value="library">ห้องสมุด — ปิดปรับปรุงชั่วคราว</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                                ยกเลิก
                            </button>
                            <button type="submit" :disabled="saving"
                                class="px-4 py-2 text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition-colors disabled:opacity-60 flex items-center gap-1.5">
                                <i v-if="saving" class="fa-solid fa-spinner animate-spin"></i>
                                บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>
