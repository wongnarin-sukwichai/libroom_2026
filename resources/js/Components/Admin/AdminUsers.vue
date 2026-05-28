<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff';
    created_at: string;
}

interface Paginated {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    admin_count: number;
    staff_count: number;
}

const paginated = ref<Paginated>({
    data: [], current_page: 1, last_page: 1,
    total: 0, from: 0, to: 0,
    admin_count: 0, staff_count: 0,
});
const loading = ref(false);
const showModal = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const form = ref({ id: 0, email: '', role: 'staff' as 'admin' | 'staff' });
const formError = ref('');

const pageNumbers = computed(() => {
    const pages: (number | '...')[] = [];
    const last = paginated.value.last_page;
    const cur = paginated.value.current_page;
    if (last <= 7) {
        for (let i = 1; i <= last; i++) pages.push(i);
    } else {
        pages.push(1);
        if (cur > 3) pages.push('...');
        for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
        if (cur < last - 2) pages.push('...');
        pages.push(last);
    }
    return pages;
});

async function fetchUsers(page = 1) {
    loading.value = true;
    try {
        const res = await axios.get('/admin/users', { params: { page } });
        paginated.value = res.data;
    } finally {
        loading.value = false;
    }
}

function openAdd() {
    isEditing.value = false;
    form.value = { id: 0, email: '', role: 'staff' };
    formError.value = '';
    showModal.value = true;
}

function openEdit(user: AdminUser) {
    isEditing.value = true;
    form.value = { id: user.id, email: user.email, role: user.role };
    formError.value = '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

async function save() {
    formError.value = '';
    saving.value = true;
    try {
        if (isEditing.value) {
            await axios.put(`/admin/users/${form.value.id}`, {
                email: form.value.email,
                role: form.value.role,
            });
        } else {
            await axios.post('/admin/users', {
                email: form.value.email,
                role: form.value.role,
            });
        }
        closeModal();
        await fetchUsers(paginated.value.current_page);
    } catch (err: any) {
        const errors = err.response?.data?.errors;
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (err.response?.data?.message ?? 'เกิดข้อผิดพลาด');
    } finally {
        saving.value = false;
    }
}

async function confirmDelete(user: AdminUser) {
    const result = await Swal.fire({
        title: 'ลบบัญชีผู้ดูแล?',
        html: `ต้องการลบ <strong>${user.email}</strong> ออกจากระบบใช่หรือไม่?<br><span style="font-size:11px;color:#94a3b8">การดำเนินการนี้ไม่สามารถย้อนกลับได้</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;

    try {
        await axios.delete(`/admin/users/${user.id}`);
        const goToPage = paginated.value.data.length === 1 && paginated.value.current_page > 1
            ? paginated.value.current_page - 1
            : paginated.value.current_page;
        await fetchUsers(goToPage);
        Swal.fire({ title: 'ลบเรียบร้อย', icon: 'success', timer: 1500, showConfirmButton: false });
    } catch (err: any) {
        Swal.fire('ไม่สามารถลบได้', err.response?.data?.message ?? 'เกิดข้อผิดพลาด', 'error');
    }
}

onMounted(() => fetchUsers());
</script>

<template>
    <div class="space-y-5">
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="p-4 text-center bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-blue-900">
                <p class="text-xs font-semibold text-slate-400">ผู้ดูแลทั้งหมด</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ paginated.total }}</p>
            </div>
            <div class="p-4 text-center bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-amber-500">
                <p class="text-xs font-semibold text-slate-400">Admin</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ paginated.admin_count }}</p>
            </div>
            <div class="p-4 text-center bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-slate-400">
                <p class="text-xs font-semibold text-slate-400">Staff</p>
                <p class="mt-1 text-2xl font-bold text-slate-600">{{ paginated.staff_count }}</p>
            </div>
        </div>

        <!-- Table card -->
        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">รายชื่อผู้ดูแลระบบ</h3>
                    <p class="text-xs text-slate-400 mt-0.5">จัดการสิทธิ์การเข้าถึง Backend</p>
                </div>
                <button
                    @click="openAdd"
                    class="flex items-center gap-1.5 bg-blue-900 hover:bg-blue-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors"
                >
                    <i class="fa-solid fa-plus"></i>
                    เพิ่มผู้ดูแล
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400 text-sm gap-2">
                <i class="fa-solid fa-spinner animate-spin"></i> กำลังโหลด...
            </div>

            <div v-else>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider">
                                <th class="p-4">#</th>
                                <th class="p-4">อีเมล</th>
                                <th class="p-4">ชื่อ</th>
                                <th class="p-4">Role</th>
                                <th class="p-4 text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                            <tr
                                v-for="(user, idx) in paginated.data"
                                :key="user.id"
                                class="transition-colors hover:bg-slate-50/70"
                            >
                                <td class="p-4 text-slate-400">
                                    {{ (paginated.current_page - 1) * 10 + idx + 1 }}
                                </td>
                                <td class="p-4 font-medium text-slate-900">{{ user.email }}</td>
                                <td class="p-4">
                                    <span v-if="user.name" class="text-slate-500">{{ user.name }}</span>
                                    <span v-else class="text-[10px] italic text-slate-300">ยังไม่ได้ login</span>
                                </td>
                                <td class="p-4">
                                    <span
                                        :class="user.role === 'admin'
                                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                                            : 'bg-slate-100 text-slate-600 border-slate-200'"
                                        class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="openEdit(user)"
                                            class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="แก้ไข"
                                        >
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                        <button
                                            @click="confirmDelete(user)"
                                            class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition-colors"
                                            title="ลบ"
                                        >
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="paginated.data.length === 0">
                                <td colspan="5" class="py-12 text-center text-slate-400 text-xs">
                                    <i class="fa-solid fa-users-slash text-2xl mb-2 block"></i>
                                    ยังไม่มีข้อมูล
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="paginated.last_page > 1"
                    class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50/60"
                >
                    <p class="text-[11px] text-slate-400">
                        แสดง {{ paginated.from }}–{{ paginated.to }} จาก {{ paginated.total }} รายการ
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            @click="fetchUsers(paginated.current_page - 1)"
                            :disabled="paginated.current_page === 1"
                            class="px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <template v-for="p in pageNumbers" :key="p">
                            <span v-if="p === '...'" class="px-1 text-xs text-slate-400">…</span>
                            <button
                                v-else
                                @click="fetchUsers(p as number)"
                                :class="p === paginated.current_page
                                    ? 'bg-blue-900 text-white border-blue-900'
                                    : 'text-slate-600 border-slate-200 hover:bg-slate-100'"
                                class="w-7 h-7 text-xs rounded-lg border transition-colors font-medium"
                            >
                                {{ p }}
                            </button>
                        </template>
                        <button
                            @click="fetchUsers(paginated.current_page + 1)"
                            :disabled="paginated.current_page === paginated.last_page"
                            class="px-2.5 py-1.5 text-xs rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        >
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Transition name="fade">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="closeModal"
            >
                <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between p-5 bg-blue-900 text-white">
                        <h3 class="text-sm font-bold">
                            {{ isEditing ? 'แก้ไขผู้ดูแลระบบ' : 'เพิ่มผู้ดูแลระบบ' }}
                        </h3>
                        <button @click="closeModal" class="text-slate-300 hover:text-white transition-colors">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                    <form @submit.prevent="save" class="p-6 space-y-4">
                        <div v-if="formError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ formError }}
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">อีเมล</label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="example@msu.ac.th"
                                class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Role</label>
                            <select
                                v-model="form.role"
                                class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400"
                            >
                                <option value="admin">admin — จัดการได้ทุกเมนู</option>
                                <option value="staff">staff — เข้าได้บางเมนู</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                            >
                                ยกเลิก
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition-colors disabled:opacity-60 flex items-center gap-1.5"
                            >
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
