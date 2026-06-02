<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface RoomRow  { id: number; title: string; confirm_type: string; status: string }
interface ZoneRow  { id: number; title: string; status: string; zone_daily_quota: number | null; time_weekday: number; time_weekend: number; min_capacity: number; rooms: RoomRow[] }
interface LocRow   { id: number; title: string; title_eng: string; status: string; zones: ZoneRow[] }
interface TimeOpt  { id: number; title: string; start: string; end: string; total: number }

const locations  = ref<LocRow[]>([]);
const times      = ref<TimeOpt[]>([]);
const loading    = ref(false);
const activeTab  = ref<'status' | 'settings'>('status');
const activeLoc  = ref<number>(0);
const toggling   = ref<string | null>(null);

// collapse/expand zone rooms
const expandedZones = ref<Set<number>>(new Set());
function toggleExpand(zoneId: number) {
    expandedZones.value.has(zoneId)
        ? expandedZones.value.delete(zoneId)
        : expandedZones.value.add(zoneId);
    expandedZones.value = new Set(expandedZones.value);
}

// settings form per zone
const editingZone    = ref<number | null>(null);
const settingsForm   = ref({ zone_daily_quota: 1, time_weekday: 1, time_weekend: 4, min_capacity: 1 });
const savingSettings = ref(false);

// bulk edit all zones in current location
const showBulk    = ref(false);
const bulkForm    = ref({ time_weekday: 1, time_weekend: 4 });
const savingBulk  = ref(false);

function openBulk() {
    const first = currentLoc.value?.zones[0];
    bulkForm.value = { time_weekday: first?.time_weekday ?? 1, time_weekend: first?.time_weekend ?? 4 };
    showBulk.value = true;
}

async function saveBulk() {
    const zones = currentLoc.value?.zones ?? [];
    if (!zones.length) return;
    const result = await Swal.fire({
        title: `ใช้ config นี้กับทุก zone ใน ${currentLoc.value?.title}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e3a5f',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    });
    if (!result.isConfirmed) return;
    savingBulk.value = true;
    try {
        await Promise.all(zones.map(z => axios.put(`/admin/zones/${z.id}/settings`, {
            zone_daily_quota: z.zone_daily_quota,
            min_capacity:     z.min_capacity,
            time_weekday:     bulkForm.value.time_weekday,
            time_weekend:     bulkForm.value.time_weekend,
        })));
        zones.forEach(z => {
            z.time_weekday = bulkForm.value.time_weekday;
            z.time_weekend = bulkForm.value.time_weekend;
        });
        showBulk.value = false;
        Swal.fire({ title: 'บันทึกทุก Zone แล้ว', icon: 'success', timer: 1200, showConfirmButton: false });
    } finally {
        savingBulk.value = false;
    }
}

async function fetchAll() {
    loading.value = true;
    try {
        const res       = await axios.get('/admin/rooms');
        locations.value = res.data.locations;
        times.value     = res.data.times;
        if (locations.value.length) activeLoc.value = locations.value[0].id;
    } finally {
        loading.value = false;
    }
}

const currentLoc  = computed(() => locations.value.find(l => l.id === activeLoc.value));

async function toggleLocation(loc: LocRow) {
    const key = `loc-${loc.id}`;
    if (toggling.value) return;
    toggling.value = key;
    try {
        const res = await axios.post(`/admin/locations/${loc.id}/toggle`);
        loc.status = res.data.status;
    } finally { toggling.value = null; }
}

async function toggleZone(zone: ZoneRow) {
    const key = `zone-${zone.id}`;
    if (toggling.value) return;
    toggling.value = key;
    try {
        const res = await axios.post(`/admin/zones/${zone.id}/toggle`);
        zone.status = res.data.status;
    } finally { toggling.value = null; }
}

async function toggleRoom(room: RoomRow) {
    const key = `room-${room.id}`;
    if (toggling.value) return;
    toggling.value = key;
    try {
        const res = await axios.post(`/admin/rooms/${room.id}/toggle`);
        room.status = res.data.status;
    } finally { toggling.value = null; }
}

function openSettings(zone: ZoneRow) {
    editingZone.value  = zone.id;
    settingsForm.value = {
        zone_daily_quota: zone.zone_daily_quota ?? 3,
        time_weekday:     zone.time_weekday,
        time_weekend:     zone.time_weekend,
        min_capacity:     zone.min_capacity,
    };
}

function cancelSettings() { editingZone.value = null; }

async function saveSettings(zone: ZoneRow) {
    savingSettings.value = true;
    try {
        await axios.put(`/admin/zones/${zone.id}/settings`, settingsForm.value);
        zone.zone_daily_quota = settingsForm.value.zone_daily_quota;
        zone.time_weekday     = settingsForm.value.time_weekday;
        zone.time_weekend     = settingsForm.value.time_weekend;
        zone.min_capacity     = settingsForm.value.min_capacity;
        editingZone.value     = null;
        Swal.fire({ title: 'บันทึกแล้ว', icon: 'success', timer: 1000, showConfirmButton: false });
    } catch (err: any) {
        Swal.fire('เกิดข้อผิดพลาด', err.response?.data?.message ?? '', 'error');
    } finally {
        savingSettings.value = false;
    }
}

const timeLabel = (id: number) => times.value.find(t => t.id === id)?.title ?? `id:${id}`;

onMounted(() => fetchAll());
</script>

<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <h3 class="text-base font-bold">จัดการพื้นที่ห้องบริการ</h3>
            <p class="mt-1 text-xs text-slate-200">เปิด/ปิดพื้นที่และห้อง หรือตั้งค่า quota/เวลาของแต่ละ zone</p>
        </div>

        <!-- Sub-tabs -->
        <div class="flex gap-2 border-b border-slate-200">
            <button v-for="tab in [{ id: 'status', label: 'สถานะพื้นที่', icon: 'fa-toggle-on' }, { id: 'settings', label: 'ตั้งค่า Zone', icon: 'fa-sliders' }]"
                :key="tab.id"
                @click="activeTab = tab.id as any"
                :class="activeTab === tab.id
                    ? 'border-b-2 border-blue-900 text-blue-900 font-bold'
                    : 'text-slate-500 hover:text-slate-700'"
                class="flex items-center gap-1.5 px-4 py-2.5 text-xs transition-colors -mb-px">
                <i :class="`fa-solid ${tab.icon}`"></i>{{ tab.label }}
            </button>
        </div>

        <div v-if="loading" class="py-12 text-center text-xs text-slate-400">
            <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
        </div>

        <template v-else>
            <!-- Location tabs -->
            <div class="flex gap-2 flex-wrap">
                <button v-for="loc in locations" :key="loc.id"
                    @click="activeLoc = loc.id"
                    :class="activeLoc === loc.id
                        ? 'bg-blue-900 text-white'
                        : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'"
                    class="text-xs font-semibold px-4 py-2 rounded-xl transition-all flex items-center gap-2">
                    <span :class="loc.status === '0' ? 'bg-green-400' : 'bg-red-400'"
                        class="w-2 h-2 rounded-full"></span>
                    {{ loc.title }}
                </button>
            </div>

            <!-- ── TAB 1: สถานะพื้นที่ ── -->
            <div v-if="activeTab === 'status' && currentLoc" class="space-y-4">

                <!-- Location status -->
                <div class="flex items-center justify-between p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
                    <div>
                        <div class="text-sm font-bold text-slate-900">{{ currentLoc.title }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">สถานะทั้ง location</div>
                    </div>
                    <button @click="toggleLocation(currentLoc)"
                        :disabled="toggling === `loc-${currentLoc.id}`"
                        :class="currentLoc.status === '0'
                            ? 'bg-green-600 hover:bg-green-700'
                            : 'bg-slate-400 hover:bg-slate-500'"
                        class="text-white text-xs font-bold px-4 py-2 rounded-xl transition-colors disabled:opacity-50 flex items-center gap-1.5 min-w-[100px] justify-center">
                        <i :class="toggling === `loc-${currentLoc.id}` ? 'fa-solid fa-spinner fa-spin' : currentLoc.status === '0' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'"></i>
                        {{ currentLoc.status === '0' ? 'เปิดอยู่' : 'ปิดอยู่' }}
                    </button>
                </div>

                <!-- Zones + Rooms -->
                <div v-for="zone in currentLoc.zones" :key="zone.id"
                    class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <!-- Zone header (clickable to expand) -->
                    <div @click="toggleExpand(zone.id)"
                        class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/60 cursor-pointer hover:bg-slate-100/60 transition-colors select-none">
                        <div class="flex items-center gap-2">
                            <i :class="expandedZones.has(zone.id) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                                class="text-[10px] text-slate-400 w-3"></i>
                            <span :class="zone.status === '0' ? 'bg-green-400' : 'bg-red-400'"
                                class="w-2 h-2 rounded-full shrink-0"></span>
                            <span class="text-sm font-bold text-slate-900">{{ zone.title }}</span>
                            <span class="text-[10px] text-slate-400">
                                ({{ zone.rooms.filter(r => r.status === '0').length }}/{{ zone.rooms.length }} ห้องเปิด)
                            </span>
                        </div>
                        <button @click.stop="toggleZone(zone)"
                            :disabled="toggling === `zone-${zone.id}`"
                            :class="zone.status === '0'
                                ? 'bg-green-100 text-green-700 border-green-200 hover:bg-green-200'
                                : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'"
                            class="text-[10px] font-bold px-2.5 py-1 rounded-lg border transition-colors disabled:opacity-50 flex items-center gap-1">
                            <i :class="toggling === `zone-${zone.id}` ? 'fa-solid fa-spinner fa-spin' : zone.status === '0' ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'"></i>
                            {{ zone.status === '0' ? 'เปิด Zone' : 'ปิด Zone' }}
                        </button>
                    </div>
                    <!-- Room list (collapse) -->
                    <div v-if="expandedZones.has(zone.id)" class="divide-y divide-slate-50 px-5">
                        <div v-for="room in zone.rooms" :key="room.id"
                            class="flex items-center justify-between py-2.5">
                            <div>
                                <span class="text-xs text-slate-700 font-medium">{{ room.title }}</span>
                                <span :class="room.confirm_type === 'auto' ? 'text-sky-600' : 'text-amber-600'"
                                    class="ml-2 text-[10px]">
                                    {{ room.confirm_type === 'auto' ? 'Auto' : 'Manual' }}
                                </span>
                            </div>
                            <button @click="toggleRoom(room)"
                                :disabled="toggling === `room-${room.id}`"
                                :class="room.status === '0'
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : 'bg-slate-300 hover:bg-slate-400'"
                                class="text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition-colors disabled:opacity-50 min-w-[60px] text-center">
                                <i v-if="toggling === `room-${room.id}`" class="fa-solid fa-spinner fa-spin"></i>
                                <span v-else>{{ room.status === '0' ? 'เปิด' : 'ปิด' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB 2: ตั้งค่า Zone ── -->
            <div v-if="activeTab === 'settings' && currentLoc" class="space-y-3">
                <!-- Bulk edit bar -->
                <div class="flex justify-end">
                    <button @click="openBulk"
                        class="flex items-center gap-1.5 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 px-3 py-2 rounded-xl transition-colors">
                        <i class="fa-solid fa-layer-group"></i> แก้ไขเวลาทุก Zone พร้อมกัน
                    </button>
                </div>

                <!-- Bulk modal -->
                <Transition name="fade">
                <div v-if="showBulk"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                    @click.self="showBulk = false">
                    <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">
                        <div class="flex items-center justify-between p-5 bg-indigo-900 text-white">
                            <h3 class="text-sm font-bold">แก้ไขเวลาทุก Zone — {{ currentLoc.title }}</h3>
                            <button @click="showBulk = false" class="text-slate-300 hover:text-white">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <p class="text-xs text-slate-500">เลือก config เวลาที่จะใช้กับ <b>ทุก zone</b> ใน location นี้ (quota และ คนขั้นต่ำ ยังคงเดิมของแต่ละ zone)</p>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">วันธรรมดา</label>
                                <select v-model.number="bulkForm.time_weekday"
                                    class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                    <option v-for="t in times" :key="t.id" :value="t.id">{{ t.title }} ({{ t.start }}–{{ t.end }})</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">วันหยุด</label>
                                <select v-model.number="bulkForm.time_weekend"
                                    class="w-full text-xs px-3 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-200">
                                    <option v-for="t in times" :key="t.id" :value="t.id">{{ t.title }} ({{ t.start }}–{{ t.end }})</option>
                                </select>
                            </div>
                            <div class="flex justify-end gap-2 pt-1">
                                <button @click="showBulk = false"
                                    class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">ยกเลิก</button>
                                <button @click="saveBulk" :disabled="savingBulk"
                                    class="px-4 py-2 text-xs font-bold text-white bg-indigo-700 hover:bg-indigo-800 rounded-lg transition-colors disabled:opacity-60 flex items-center gap-1.5">
                                    <i v-if="savingBulk" class="fa-solid fa-spinner animate-spin"></i>
                                    บันทึกทุก Zone
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                </Transition>
                <div v-for="zone in currentLoc.zones" :key="zone.id"
                    class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">

                    <!-- Zone info row -->
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/60">
                        <span class="text-sm font-bold text-slate-900">{{ zone.title }}</span>
                        <button v-if="editingZone !== zone.id"
                            @click="openSettings(zone)"
                            class="text-[10px] font-bold text-blue-700 hover:underline flex items-center gap-1">
                            <i class="fa-solid fa-pen text-[9px]"></i> แก้ไข
                        </button>
                    </div>

                    <!-- Display mode -->
                    <div v-if="editingZone !== zone.id" class="px-5 py-3.5 grid grid-cols-2 gap-y-2 gap-x-6 text-xs text-slate-600 sm:grid-cols-4">
                        <div>
                            <div class="text-[10px] text-slate-400 font-semibold">โควต้า/วัน</div>
                            <div class="font-bold text-slate-900 mt-0.5">{{ zone.zone_daily_quota ?? 'ไม่จำกัด' }} ชม.</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 font-semibold">คนขั้นต่ำ</div>
                            <div class="font-bold text-slate-900 mt-0.5">{{ zone.min_capacity }} คน</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 font-semibold">วันธรรมดา</div>
                            <div class="font-bold text-slate-900 mt-0.5">{{ timeLabel(zone.time_weekday) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-400 font-semibold">วันหยุด</div>
                            <div class="font-bold text-slate-900 mt-0.5">{{ timeLabel(zone.time_weekend) }}</div>
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <div v-else class="px-5 py-4 space-y-3">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">โควต้า/วัน (ชม.)</label>
                                <input v-model.number="settingsForm.zone_daily_quota" type="number" min="1" max="24"
                                    class="w-full text-xs px-2.5 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">คนขั้นต่ำ</label>
                                <input v-model.number="settingsForm.min_capacity" type="number" min="1"
                                    class="w-full text-xs px-2.5 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">วันธรรมดา</label>
                                <select v-model.number="settingsForm.time_weekday"
                                    class="w-full text-xs px-2.5 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option v-for="t in times" :key="t.id" :value="t.id">{{ t.title }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">วันหยุด</label>
                                <select v-model.number="settingsForm.time_weekend"
                                    class="w-full text-xs px-2.5 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option v-for="t in times" :key="t.id" :value="t.id">{{ t.title }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button @click="cancelSettings"
                                class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 font-bold transition-colors">
                                ยกเลิก
                            </button>
                            <button @click="saveSettings(zone)" :disabled="savingSettings"
                                class="text-xs px-4 py-1.5 rounded-lg bg-blue-900 text-white hover:bg-blue-800 font-bold transition-colors disabled:opacity-60 flex items-center gap-1.5">
                                <i v-if="savingSettings" class="fa-solid fa-spinner fa-spin"></i>
                                บันทึก
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
