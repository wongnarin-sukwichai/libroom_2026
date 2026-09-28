<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface BannerRow {
    id: number;
    image_path: string;
    sort_order: number;
    status: '0' | '1';
}

const banners  = ref<BannerRow[]>([]);
const loading  = ref(false);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const imgUrl = (path: string) => `/storage/${path}`;

async function fetchBanners() {
    loading.value = true;
    try {
        const res = await axios.get('/admin/banners');
        banners.value = res.data;
    } finally {
        loading.value = false;
    }
}

function pickFile() {
    fileInput.value?.click();
}

async function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire('ไฟล์ใหญ่เกินไป', 'ขนาดไฟล์ต้องไม่เกิน 5MB', 'error');
        return;
    }

    uploading.value = true;
    try {
        const form = new FormData();
        form.append('image', file);
        const res = await axios.post('/admin/banners', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        banners.value.push(res.data);
        Swal.fire({ title: 'อัปโหลดสำเร็จ', icon: 'success', timer: 1200, showConfirmButton: false });
    } catch (err: any) {
        Swal.fire('อัปโหลดไม่สำเร็จ', err.response?.data?.message ?? 'เกิดข้อผิดพลาด', 'error');
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

async function toggleStatus(b: BannerRow) {
    const next = b.status === '0' ? '1' : '0';
    b.status = next; // optimistic
    try {
        await axios.put(`/admin/banners/${b.id}`, { status: next });
    } catch {
        b.status = next === '0' ? '1' : '0'; // revert
    }
}

async function persistOrder() {
    await axios.put('/admin/banners/reorder', { ids: banners.value.map(b => b.id) });
}

function moveUp(index: number) {
    if (index === 0) return;
    const arr = banners.value;
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    persistOrder();
}

function moveDown(index: number) {
    const arr = banners.value;
    if (index === arr.length - 1) return;
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    persistOrder();
}

async function confirmDelete(b: BannerRow, index: number) {
    const result = await Swal.fire({
        title: 'ลบแบนเนอร์นี้?',
        html: `<img src="${imgUrl(b.image_path)}" style="max-width:100%;border-radius:8px;margin-bottom:8px" />`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    await axios.delete(`/admin/banners/${b.id}`);
    banners.value.splice(index, 1);
    Swal.fire({ title: 'ลบเรียบร้อย', icon: 'success', timer: 1200, showConfirmButton: false });
}

onMounted(() => fetchBanners());
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-start gap-3 p-4 border-2 border-red-300 bg-red-50 rounded-2xl">
            <i class="mt-0.5 text-lg text-red-500 fa-solid fa-triangle-exclamation shrink-0"></i>
            <div class="text-xs text-red-700">
                เนื่องจากระบบป้องกันของ Cloudflare บล็อกการส่งข้อมูล ทำให้ไม่สามารถอัปโหลด/แก้ไขแบนเนอร์
                <span class="font-bold">กรุณาติดต่อผู้พัฒนาระบบ</span> เพื่อดำเนินการเพิ่มแบนเนอร์
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900">แบนเนอร์หน้าแรก (สไลด์)</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    รูปที่ "เปิดใช้งาน" จะแสดงเป็นสไลด์ที่หน้าแรก เรียงตามลำดับด้านล่าง — แนะนำขนาด 1600×400px ไฟล์ไม่เกิน 5MB
                </p>
            </div>
            <div>
                <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onFileChange" />
                <button
                    @click="pickFile"
                    :disabled="uploading"
                    class="bg-blue-900 hover:bg-blue-950 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 disabled:opacity-60"
                >
                    <i :class="uploading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-upload'"></i>
                    {{ uploading ? 'กำลังอัปโหลด...' : 'อัปโหลดแบนเนอร์ใหม่' }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="py-16 text-xs text-center text-slate-400">
            <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลด...
        </div>

        <div v-else-if="!banners.length" class="py-16 text-xs text-center text-slate-400 bg-white border border-slate-200 rounded-2xl">
            <i class="block mb-2 text-2xl fa-solid fa-images"></i>ยังไม่มีแบนเนอร์ — อัปโหลดรูปแรกได้เลย
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="(b, i) in banners" :key="b.id"
                class="flex items-center gap-4 p-3 bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <div class="flex flex-col gap-1 shrink-0">
                    <button
                        @click="moveUp(i)" :disabled="i === 0"
                        class="w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed"
                    ><i class="fa-solid fa-chevron-up text-[10px]"></i></button>
                    <button
                        @click="moveDown(i)" :disabled="i === banners.length - 1"
                        class="w-7 h-7 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed"
                    ><i class="fa-solid fa-chevron-down text-[10px]"></i></button>
                </div>

                <div class="w-40 overflow-hidden bg-slate-100 rounded-xl shrink-0 aspect-[1600/400]">
                    <img :src="imgUrl(b.image_path)" class="object-cover w-full h-full" />
                </div>

                <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-slate-800">ลำดับที่ {{ i + 1 }}</div>
                    <div class="text-[11px] text-slate-400 truncate mt-0.5">{{ b.image_path }}</div>
                </div>

                <label class="inline-flex items-center gap-2 text-xs font-bold cursor-pointer shrink-0"
                    :class="b.status === '0' ? 'text-emerald-600' : 'text-slate-400'">
                    <span class="relative inline-block w-9 h-5">
                        <input type="checkbox" class="sr-only peer" :checked="b.status === '0'" @change="toggleStatus(b)" />
                        <span class="absolute inset-0 transition-colors bg-slate-200 rounded-full peer-checked:bg-emerald-500"></span>
                        <span class="absolute w-4 h-4 transition-transform bg-white rounded-full top-0.5 left-0.5 peer-checked:translate-x-4"></span>
                    </span>
                    {{ b.status === '0' ? 'เปิดใช้งาน' : 'ปิด' }}
                </label>

                <button
                    @click="confirmDelete(b, i)"
                    class="flex items-center justify-center w-9 h-9 text-red-500 transition-colors border border-red-200 rounded-lg shrink-0 hover:bg-red-50"
                ><i class="fa-solid fa-trash text-xs"></i></button>
            </div>
        </div>
    </div>
</template>
