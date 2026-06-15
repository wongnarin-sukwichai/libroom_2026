<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

interface MemberRow {
    id: number;
    name: string;
    email: string;
    code: string | null;
    faculty: string | null;
    branch: string | null;
    type: string | null;
    total_bookings: number;
    last_booking: string | null;
}

interface Paginated {
    data: MemberRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
}

const paginated = ref<Paginated>({ data: [], current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const loading   = ref(false);
const search    = ref('');
let   searchTimer: ReturnType<typeof setTimeout> | null = null;

const editingCodeId  = ref<number | null>(null);
const editingCodeVal = ref('');
const savingCode     = ref(false);

function startEditCode(m: MemberRow) {
    editingCodeId.value  = m.id;
    editingCodeVal.value = m.code ?? '';
}
function cancelEditCode() {
    editingCodeId.value = null;
}
async function saveCode(m: MemberRow) {
    savingCode.value = true;
    try {
        const res = await axios.put(`/admin/members/${m.id}/code`, { code: editingCodeVal.value || null });
        m.code = res.data.code;
        editingCodeId.value = null;
    } finally {
        savingCode.value = false;
    }
}

const typeConfig: Record<string, string> = {
    'นิสิต ป.ตรี':     'bg-blue-50 text-blue-800 border-blue-200',
    'บัณฑิตศึกษา':     'bg-indigo-50 text-indigo-800 border-indigo-200',
    'บุคลากร/อาจารย์': 'bg-amber-50 text-amber-800 border-amber-200',
};

const memberInitials = (name: string) =>
    name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

const fmtDate = (iso: string | null) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
};

async function fetchMembers(page = 1) {
    loading.value = true;
    try {
        const res = await axios.get('/admin/members', { params: { page, search: search.value || undefined } });
        paginated.value = res.data;
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchMembers());

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchMembers(1), 350);
});
</script>

<template>
    <div class="space-y-5">
        <!-- KPI Cards -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <p class="text-xs font-semibold text-slate-400">สมาชิกทั้งหมด</p>
                <p class="mt-1 text-2xl font-bold text-blue-900">{{ paginated.total }}</p>
            </div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <p class="text-xs font-semibold text-slate-400">หน้า</p>
                <p class="mt-1 text-2xl font-bold text-slate-700">{{ paginated.current_page }}/{{ paginated.last_page }}</p>
            </div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200 col-span-2 sm:col-span-1">
                <p class="text-xs font-semibold text-slate-400">แสดง</p>
                <p class="mt-1 text-2xl font-bold text-slate-700">
                    {{ paginated.from }}–{{ paginated.to }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <!-- Header + Search -->
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-900">รายชื่อสมาชิกทั้งหมด</h3>
                <div class="relative">
                    <i class="absolute text-xs -translate-y-1/2 left-3 top-1/2 fa-solid fa-magnifying-glass text-slate-400"></i>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="ค้นหาชื่อ อีเมล หรือรหัส..."
                        class="pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 w-52"
                    />
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="py-12 text-center text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
            </div>

            <!-- Empty -->
            <div v-else-if="!paginated.data.length" class="py-12 text-center text-xs text-slate-400">
                <i class="fa-solid fa-users-slash text-2xl mb-2 block"></i>
                ไม่พบสมาชิก
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider">
                            <th class="p-4">สมาชิก</th>
                            <th class="p-4">รหัส</th>
                            <th class="hidden p-4 lg:table-cell">คณะ</th>
                            <th class="hidden p-4 xl:table-cell">สาขา</th>
                            <th class="p-4">ประเภท</th>
                            <th class="p-4 text-center">จองทั้งหมด</th>
                            <th class="hidden p-4 sm:table-cell">จองล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                        <tr v-for="m in paginated.data" :key="m.id" class="transition-colors hover:bg-slate-50/70">
                            <td class="p-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full text-[10px] font-bold text-white bg-gradient-to-tr from-blue-600 to-indigo-500 shrink-0">
                                        {{ memberInitials(m.name) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ m.name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ m.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <div v-if="editingCodeId === m.id" class="flex items-center gap-1">
                                    <input
                                        v-model="editingCodeVal"
                                        type="text"
                                        class="w-28 text-xs px-2 py-1 border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 font-mono"
                                        @keyup.enter="saveCode(m)"
                                        @keyup.escape="cancelEditCode"
                                        autofocus
                                    />
                                    <button @click="saveCode(m)" :disabled="savingCode" class="text-green-600 hover:text-green-800 p-0.5">
                                        <i :class="savingCode ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-check'" class="text-xs"></i>
                                    </button>
                                    <button @click="cancelEditCode" class="text-slate-400 hover:text-slate-600 p-0.5">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-1.5 group">
                                    <span class="font-mono text-slate-600">{{ m.code ?? '—' }}</span>
                                    <button
                                        @click="startEditCode(m)"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-blue-600"
                                        title="แก้ไขรหัส"
                                    >
                                        <i class="fa-solid fa-pen text-[10px]"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="hidden p-4 lg:table-cell text-slate-500">{{ m.faculty ?? '—' }}</td>
                            <td class="hidden p-4 xl:table-cell text-slate-500">{{ m.branch ?? '—' }}</td>
                            <td class="p-4">
                                <span
                                    :class="typeConfig[m.type ?? ''] ?? 'bg-slate-50 text-slate-600 border-slate-200'"
                                    class="text-[10px] px-2 py-0.5 rounded border font-semibold"
                                >{{ m.type ?? 'ไม่ระบุ' }}</span>
                            </td>
                            <td class="p-4 font-bold text-center text-slate-900">{{ m.total_bookings }}</td>
                            <td class="hidden p-4 sm:table-cell text-slate-400">{{ fmtDate(m.last_booking) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="paginated.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-slate-100">
                <span class="text-xs text-slate-400">{{ paginated.from }}–{{ paginated.to }} จาก {{ paginated.total }} ราย</span>
                <div class="flex gap-1">
                    <button
                        v-for="p in paginated.last_page"
                        :key="p"
                        @click="fetchMembers(p)"
                        :class="p === paginated.current_page
                            ? 'bg-blue-900 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="w-7 h-7 text-xs font-bold rounded-lg transition-all"
                    >{{ p }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
