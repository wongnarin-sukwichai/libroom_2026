<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface BypassCode {
    id: number;
    code: string;
    label: string;
    is_active: boolean;
}

const codes   = ref<BypassCode[]>([]);
const loading = ref(false);
const form    = ref({ code: '', label: '' });
const saving  = ref(false);

async function fetchCodes() {
    loading.value = true;
    try {
        const res = await axios.get('/admin/kiosk-bypass');
        codes.value = res.data;
    } finally {
        loading.value = false;
    }
}

onMounted(fetchCodes);

async function addCode() {
    if (!form.value.code || !form.value.label) return;
    saving.value = true;
    try {
        const res = await axios.post('/admin/kiosk-bypass', form.value);
        codes.value.unshift(res.data);
        form.value = { code: '', label: '' };
    } catch (err: any) {
        const msg = err.response?.data?.errors?.code?.[0] ?? 'เกิดข้อผิดพลาด';
        Swal.fire({ title: 'ไม่สำเร็จ', text: msg, icon: 'error', confirmButtonColor: '#1e3a5f' });
    } finally {
        saving.value = false;
    }
}

async function toggleCode(bypass: BypassCode) {
    const res = await axios.post(`/admin/kiosk-bypass/${bypass.id}/toggle`);
    const idx = codes.value.findIndex(c => c.id === bypass.id);
    if (idx !== -1) codes.value[idx] = res.data;
}

async function deleteCode(bypass: BypassCode) {
    const result = await Swal.fire({
        title: 'ลบรหัส Bypass?',
        text: `"${bypass.label}" (${bypass.code}) จะถูกลบออกถาวร`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
    });
    if (!result.isConfirmed) return;
    await axios.delete(`/admin/kiosk-bypass/${bypass.id}`);
    codes.value = codes.value.filter(c => c.id !== bypass.id);
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-10 h-10 bg-violet-500/10 text-violet-600 rounded-xl">
                <i class="fa-solid fa-door-open"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900">จัดการรหัส Bypass ประตู Kiosk</h3>
                <p class="text-xs text-slate-400">รหัสพิเศษที่ผ่านได้ตลอดโดยไม่ตรวจ slot การจอง (เฉพาะ Admin)</p>
            </div>
        </div>

        <!-- Add form -->
        <div class="p-5 bg-white border rounded-2xl border-slate-200 shadow-sm">
            <h4 class="text-xs font-bold text-slate-700 mb-3">เพิ่มรหัส Bypass ใหม่</h4>
            <div class="flex gap-2 flex-wrap">
                <input
                    v-model="form.code"
                    type="text"
                    placeholder="รหัส (เช่น 4013)"
                    class="text-xs px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-200 w-40"
                    @keyup.enter="addCode"
                />
                <input
                    v-model="form.label"
                    type="text"
                    placeholder="คำอธิบาย (เช่น บัตร Admin หลัก)"
                    class="text-xs px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-200 flex-1 min-w-48"
                    @keyup.enter="addCode"
                />
                <button
                    @click="addCode"
                    :disabled="!form.code || !form.label || saving"
                    class="flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 disabled:bg-slate-300 rounded-xl transition-all"
                >
                    <i :class="saving ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-plus'"></i>
                    เพิ่ม
                </button>
            </div>
        </div>

        <!-- List -->
        <div class="bg-white border rounded-2xl border-slate-200 shadow-sm overflow-hidden">
            <div v-if="loading" class="py-10 text-center text-xs text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
            </div>
            <div v-else-if="!codes.length" class="py-10 text-center text-xs text-slate-400">
                <i class="fa-solid fa-inbox text-xl block mb-1"></i>
                ยังไม่มีรหัส Bypass
            </div>
            <table v-else class="w-full text-xs">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold text-slate-500">รหัส</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500">คำอธิบาย</th>
                        <th class="px-4 py-3 text-center font-bold text-slate-500">สถานะ</th>
                        <th class="px-4 py-3 text-right font-bold text-slate-500">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="bypass in codes" :key="bypass.id" class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 font-mono font-bold text-slate-800">{{ bypass.code }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ bypass.label }}</td>
                        <td class="px-4 py-3 text-center">
                            <button @click="toggleCode(bypass)" class="transition-colors">
                                <span v-if="bypass.is_active" class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 border border-green-200 rounded-full text-[10px] font-bold">
                                    <i class="fa-solid fa-circle text-[6px] animate-pulse"></i> เปิดใช้งาน
                                </span>
                                <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 text-slate-500 border border-slate-200 rounded-full text-[10px] font-bold">
                                    <i class="fa-solid fa-circle text-[6px]"></i> ปิดใช้งาน
                                </span>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button @click="deleteCode(bypass)" class="text-red-400 hover:text-red-600 transition-colors p-1">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
