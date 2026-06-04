<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    group:          { type: Object,  default: null },
    error:          { type: String,  default: null },
    already_joined: { type: Boolean, default: false },
});

const page      = usePage();
const authUser  = computed(() => page.props.auth?.user ?? null);
const joining   = ref(false);

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('th-TH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

const handleJoin = async () => {
    if (joining.value) return;

    const result = await Swal.fire({
        title: 'ยืนยันการเข้าร่วม?',
        html: `<div class="text-sm text-slate-600">
                <div class="font-bold text-slate-900 mb-1">${props.group.room_title}</div>
                <div>${formatDate(props.group.date)} • ${props.group.time_label}</div>
               </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e3a5f',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'เข้าร่วมกลุ่ม',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    joining.value = true;
    try {
        const res = await fetch(`/join/${props.group.token}`, {
            method: 'POST',
            headers: {
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
        });

        const json = await res.json();

        if (!res.ok) {
            Swal.fire({ title: 'ไม่สำเร็จ', text: json.message, icon: 'error', confirmButtonColor: '#1e3a5f' });
            return;
        }

        await Swal.fire({
            title: 'เข้าร่วมสำเร็จ!',
            text: 'รอให้ครบ ' + props.group.min_capacity + ' คน แล้วเจ้าหน้าที่จะยืนยันการจองให้',
            icon: 'success',
            confirmButtonColor: '#1e3a5f',
        });

        router.visit('/my-bookings');

    } finally {
        joining.value = false;
    }
};
</script>

<template>
    <div class="flex flex-col min-h-screen bg-slate-50 font-sans text-slate-800">

        <!-- Top bar -->
        <div class="px-4 py-2 text-xs text-white bg-gradient-to-r from-rose-400 via-fuchsia-500 to-indigo-500">
            <div class="flex items-center justify-between mx-auto max-w-lg">
                <span class="font-semibold text-amber-300">MSU Library — ระบบจองพื้นที่ออนไลน์</span>
                <span v-if="authUser" class="text-amber-300">
                    <i class="fa-solid fa-circle-user mr-1"></i>{{ authUser.name }}
                </span>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">

                <!-- Error state -->
                <div v-if="error" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center space-y-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-link-slash text-2xl text-red-500"></i>
                    </div>
                    <h2 class="text-base font-bold text-slate-900">ไม่สามารถเข้าร่วมได้</h2>
                    <p class="text-sm text-slate-500">{{ error }}</p>
                    <a href="/" class="inline-block mt-2 text-xs text-blue-700 hover:underline">
                        <i class="fa-solid fa-house mr-1"></i>กลับหน้าหลัก
                    </a>
                </div>

                <!-- Already joined -->
                <div v-else-if="already_joined" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center space-y-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-circle-check text-2xl text-blue-600"></i>
                    </div>
                    <h2 class="text-base font-bold text-slate-900">คุณอยู่ในกลุ่มนี้แล้ว</h2>
                    <p class="text-sm text-slate-500">{{ group.room_title }} • {{ formatDate(group.date) }}</p>
                    <a href="/my-bookings" class="inline-block mt-2 text-xs text-blue-700 hover:underline">
                        <i class="fa-solid fa-calendar-check mr-1"></i>ดูประวัติการจอง
                    </a>
                </div>

                <!-- Join card -->
                <div v-else-if="group" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Header -->
                    <div class="bg-blue-900 px-6 py-5 text-white">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-users text-amber-400"></i>
                            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">คำเชิญเข้าร่วมกลุ่ม</span>
                        </div>
                        <h1 class="text-base font-bold leading-tight">{{ group.room_title }}</h1>
                        <p class="text-xs text-slate-300 mt-0.5">{{ group.loc_title }} › {{ group.zone_title }}</p>
                    </div>

                    <div class="p-6 space-y-5">
                        <!-- Details -->
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center gap-3 text-slate-600">
                                <i class="fa-solid fa-calendar w-4 text-center text-slate-400"></i>
                                <span>{{ formatDate(group.date) }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-slate-600">
                                <i class="fa-solid fa-clock w-4 text-center text-slate-400"></i>
                                <span>{{ group.time_label }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-slate-600">
                                <i class="fa-solid fa-user w-4 text-center text-slate-400"></i>
                                <span>ผู้จอง: <span class="font-semibold text-slate-900">{{ group.lead_name }}</span></span>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between text-xs mb-2">
                                <span class="font-semibold text-slate-600">สมาชิกในกลุ่ม</span>
                                <span class="font-bold text-blue-900">{{ group.member_count }} / {{ group.min_capacity }} คน</span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-blue-600 rounded-full transition-all"
                                    :style="{ width: Math.min(100, (group.member_count / group.min_capacity) * 100) + '%' }"
                                ></div>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1.5">
                                ต้องการอีก {{ group.min_capacity - group.member_count }} คน เพื่อส่งให้เจ้าหน้าที่ยืนยัน
                            </p>
                        </div>

                        <!-- Join button -->
                        <button
                            @click="handleJoin"
                            :disabled="joining"
                            class="w-full bg-blue-900 hover:bg-blue-950 disabled:bg-slate-300 text-white font-bold py-3 rounded-xl text-sm transition-all flex items-center justify-center gap-2"
                        >
                            <i :class="joining ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-user-plus'"></i>
                            {{ joining ? 'กำลังเข้าร่วม...' : 'เข้าร่วมกลุ่ม' }}
                        </button>

                        <p class="text-center text-[11px] text-slate-400">
                            การเข้าร่วมใช้บัญชี MSU ของคุณ:
                            <span class="font-semibold text-slate-600">{{ authUser?.email }}</span>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
