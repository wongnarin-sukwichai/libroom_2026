<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

interface ToolItem { id: number; name: string; icon: string }
interface ZoneToolRow { tool_id: number; quantity: number }
interface RoomToolRow { tool_id: number; mode: 'add' | 'remove'; quantity: number }
interface RoomRow  { id: number; title: string; confirm_type: string; access_control: string; status: string; tools?: RoomToolRow[]; scan_code?: string | null }
interface ZoneRow  { id: number; title: string; status: string; zone_daily_quota: number | null; time_weekday: number; time_weekend: number; min_capacity: number; rooms: RoomRow[]; tools?: ZoneToolRow[]; scan_prefix?: string | null; icon?: string | null; scan_only?: string | null }

// ไอคอนให้เจ้าหน้าที่เลือกแทนโซน (แสดงที่หน้าแรก)
const ZONE_ICONS = [
    'fa-users', 'fa-tv', 'fa-desktop', 'fa-gamepad', 'fa-microphone',
    'fa-book-open', 'fa-couch', 'fa-bed', 'fa-chalkboard-user', 'fa-video',
    'fa-headphones', 'fa-door-open', 'fa-mug-hot', 'fa-graduation-cap',
    'fa-building', 'fa-wifi', 'fa-print', 'fa-star',
];
interface LocRow   { id: number; title: string; title_eng: string; status: string; zones: ZoneRow[] }
interface TimeOpt  { id: number; title: string; start: string; end: string; total: number }

const locations  = ref<LocRow[]>([]);
const times      = ref<TimeOpt[]>([]);
const tools      = ref<ToolItem[]>([]);
const loading    = ref(false);
const activeTab  = ref<'status' | 'settings' | 'tools' | 'qr'>('status');
const activeLoc  = ref<number>(0);
const toggling   = ref<string | null>(null);

// อธิบาย Auto/Manual × Kiosk/ไม่มี Kiosk (popup ช่วยจำ)
function showConfirmTypeHelp() {
    Swal.fire({
        title: 'Auto / Manual × Kiosk คืออะไร',
        width: 560,
        html: `
            <div style="text-align:left;font-size:12.5px;line-height:1.6;color:#334155">
                <p style="margin-bottom:10px">
                    ตัดสินด้วย 2 แกนอิสระจากกัน —
                    <b>Auto/Manual</b> = ต้องรอคนอนุมัติไหม,
                    <b>Kiosk</b> = มีกลไกเช็คอินด้วยตัวเองไหม (kiosk คือ "กลอนประตูไฟฟ้า" ไม่ใช่ด่านขออนุญาตจากคน)
                </p>
                <table style="width:100%;border-collapse:collapse;font-size:11.5px">
                    <tr style="background:#f1f5f9">
                        <th style="padding:6px;border:1px solid #e2e8f0;text-align:left"></th>
                        <th style="padding:6px;border:1px solid #e2e8f0;text-align:left">มี Kiosk</th>
                        <th style="padding:6px;border:1px solid #e2e8f0;text-align:left">ไม่มี Kiosk</th>
                    </tr>
                    <tr>
                        <td style="padding:6px;border:1px solid #e2e8f0;font-weight:bold;background:#f8fafc">Auto</td>
                        <td style="padding:6px;border:1px solid #e2e8f0">confirmed ทันที เช็คอินด้วยการแตะบัตรที่ kiosk (ปลดล็อกประตูไปในตัว) — ไม่ติดต่อใครเลย</td>
                        <td style="padding:6px;border:1px solid #e2e8f0">confirmed ทันที เช็คอินผ่าน QR (<code>scan_code</code>) ถ้ามี เช่น เก้าอี้ — ถ้าไม่มี ต้องพึ่งปุ่ม "เช็คอิน" ของแอดมิน เช่น NAP</td>
                    </tr>
                    <tr>
                        <td style="padding:6px;border:1px solid #e2e8f0;font-weight:bold;background:#f8fafc">Manual</td>
                        <td style="padding:6px;border:1px solid #e2e8f0">ครบสมาชิก → ข้าม "รอเจ้าหน้าที่" ไป confirmed ทันที (kiosk verify แทนคน) → ต้องสแกนใน 15 นาที ไม่งั้นระบบตัด</td>
                        <td style="padding:6px;border:1px solid #e2e8f0">ครบสมาชิก → รอเจ้าหน้าที่ → นิสิตมาติดต่อเคาน์เตอร์ → กด "อนุมัติ" ปุ่มเดียว = ยืนยัน+เช็คอินพร้อมกัน ไม่อนุมัติใน 15 นาทีระบบตัด</td>
                    </tr>
                </table>
            </div>
        `,
        confirmButtonText: 'เข้าใจแล้ว',
        confirmButtonColor: '#1e3a8a',
    });
}

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
const settingsForm   = ref({ zone_daily_quota: 1, time_weekday: 1, time_weekend: 4, min_capacity: 1, icon: '' as string | null, scan_only: false });
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
        tools.value     = res.data.tools ?? [];
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

async function toggleRoomAccess(room: RoomRow) {
    const key = `room-ac-${room.id}`;
    if (toggling.value) return;
    toggling.value = key;
    try {
        const res = await axios.post(`/admin/rooms/${room.id}/toggle-access`);
        room.access_control = res.data.access_control;
    } finally { toggling.value = null; }
}

async function toggleRoomConfirmType(room: RoomRow) {
    const key = `room-ct-${room.id}`;
    if (toggling.value) return;
    toggling.value = key;
    try {
        const res = await axios.post(`/admin/rooms/${room.id}/toggle-confirm-type`);
        room.confirm_type = res.data.confirm_type;
    } finally { toggling.value = null; }
}

function openSettings(zone: ZoneRow) {
    editingZone.value  = zone.id;
    settingsForm.value = {
        zone_daily_quota: zone.zone_daily_quota ?? 3,
        time_weekday:     zone.time_weekday,
        time_weekend:     zone.time_weekend,
        min_capacity:     zone.min_capacity,
        icon:             zone.icon ?? null,
        scan_only:        zone.scan_only === '1',
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
        zone.icon             = settingsForm.value.icon;
        zone.scan_only        = settingsForm.value.scan_only ? '1' : '0';
        editingZone.value     = null;
        Swal.fire({ title: 'บันทึกแล้ว', icon: 'success', timer: 1000, showConfirmButton: false });
    } catch (err: any) {
        Swal.fire('เกิดข้อผิดพลาด', err.response?.data?.message ?? '', 'error');
    } finally {
        savingSettings.value = false;
    }
}

const timeLabel = (id: number) => times.value.find(t => t.id === id)?.title ?? `id:${id}`;

// ══════════ อุปกรณ์ (tools) ══════════
const toolById = (id: number) => tools.value.find(t => t.id === id);

// -- คลังอุปกรณ์ CRUD --
const toolForm      = ref<{ id: number; name: string; icon: string }>({ id: 0, name: '', icon: 'fa-wrench' });
const editingToolId = ref<number | null>(null);   // 0 = เพิ่มใหม่, null = ไม่ได้แก้
const savingTool    = ref(false);

function startAddTool()  { editingToolId.value = 0;    toolForm.value = { id: 0, name: '', icon: 'fa-wrench' }; }
function startEditTool(t: ToolItem) { editingToolId.value = t.id; toolForm.value = { ...t }; }
function cancelTool()    { editingToolId.value = null; }

async function saveTool() {
    if (!toolForm.value.name.trim()) return;
    savingTool.value = true;
    try {
        if (toolForm.value.id) {
            const { data } = await axios.put(`/admin/tools/${toolForm.value.id}`, toolForm.value);
            const i = tools.value.findIndex(x => x.id === data.id);
            if (i >= 0) tools.value[i] = data;
        } else {
            const { data } = await axios.post('/admin/tools', toolForm.value);
            tools.value.push(data);
        }
        tools.value.sort((a, b) => a.name.localeCompare(b.name, 'th'));
        editingToolId.value = null;
    } catch (e: any) {
        const errs = e.response?.data?.errors;
        Swal.fire('เกิดข้อผิดพลาด', errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message ?? ''), 'error');
    } finally {
        savingTool.value = false;
    }
}

async function deleteTool(t: ToolItem) {
    const r = await Swal.fire({
        title: `ลบ "${t.name}"?`, text: 'จะถูกลบออกจากทุก zone และทุกห้องด้วย',
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626',
        confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก', reverseButtons: true,
    });
    if (!r.isConfirmed) return;
    await axios.delete(`/admin/tools/${t.id}`);
    await fetchAll();
}

// -- effective tools = อุปกรณ์ที่ห้องกำหนดเอง (ไม่ inherit จาก zone) --
function effectiveTools(_zone: ZoneRow, room: RoomRow) {
    return (room.tools ?? [])
        .map(o => ({ tool_id: o.tool_id, quantity: o.quantity }))
        .filter(o => toolById(o.tool_id));
}

// -- ชุดอุปกรณ์มาตรฐานของ zone --
const editingZoneTools = ref<number | null>(null);
const zoneToolsForm    = ref<ZoneToolRow[]>([]);
const savingZoneTools  = ref(false);

function openZoneTools(zone: ZoneRow) {
    editingZoneTools.value = zone.id;
    zoneToolsForm.value = (zone.tools ?? []).map(t => ({ tool_id: t.tool_id, quantity: t.quantity }));
}
function addZoneToolRow() {
    const used = new Set(zoneToolsForm.value.map(r => r.tool_id));
    const next = tools.value.find(t => !used.has(t.id));
    zoneToolsForm.value.push({ tool_id: next?.id ?? tools.value[0]?.id ?? 0, quantity: 1 });
}
async function saveZoneTools(zone: ZoneRow) {
    savingZoneTools.value = true;
    const payload = zoneToolsForm.value.filter(t => t.tool_id);
    try {
        await axios.put(`/admin/zones/${zone.id}/tools`, { tools: payload });
        zone.tools = payload.map(t => ({ tool_id: t.tool_id, quantity: t.quantity }));
        editingZoneTools.value = null;
        Swal.fire({ title: 'บันทึกแล้ว', icon: 'success', timer: 1000, showConfirmButton: false });
    } finally {
        savingZoneTools.value = false;
    }
}

// -- override อุปกรณ์เฉพาะห้อง --
const editingRoomTools = ref<number | null>(null);
const roomZoneRows     = ref<{ tool_id: number; has: boolean; quantity: number }[]>([]);
const roomExtraRows    = ref<{ tool_id: number; quantity: number }[]>([]);
const savingRoomTools  = ref(false);

function openRoomTools(zone: ZoneRow, room: RoomRow) {
    editingRoomTools.value = room.id;
    const owned    = room.tools ?? [];
    const zoneTools = zone.tools ?? [];
    // อุปกรณ์ในคลังโซน — ติ๊กว่าห้องนี้มีตัวไหนบ้าง (เริ่มจากที่ห้องกำหนดไว้)
    roomZoneRows.value = zoneTools.map(zt => {
        const has = owned.find(o => o.tool_id === zt.tool_id);
        return { tool_id: zt.tool_id, has: !!has, quantity: has?.quantity ?? zt.quantity };
    });
    // อุปกรณ์ที่ห้องมี แต่ไม่อยู่ในคลังโซน
    roomExtraRows.value = owned
        .filter(o => !zoneTools.some(zt => zt.tool_id === o.tool_id))
        .map(o => ({ tool_id: o.tool_id, quantity: o.quantity }));
}
function addRoomExtraRow() {
    roomExtraRows.value.push({ tool_id: tools.value[0]?.id ?? 0, quantity: 1 });
}
async function saveRoomTools(room: RoomRow) {
    savingRoomTools.value = true;
    const overrides: RoomToolRow[] = [];
    for (const r of roomZoneRows.value) {
        if (r.has) overrides.push({ tool_id: r.tool_id, mode: 'add', quantity: r.quantity });
    }
    for (const r of roomExtraRows.value) {
        if (r.tool_id) overrides.push({ tool_id: r.tool_id, mode: 'add', quantity: r.quantity });
    }
    try {
        await axios.put(`/admin/rooms/${room.id}/tools`, { overrides });
        room.tools = overrides.map(o => ({ ...o }));
        editingRoomTools.value = null;
        Swal.fire({ title: 'บันทึกแล้ว', icon: 'success', timer: 1000, showConfirmButton: false });
    } finally {
        savingRoomTools.value = false;
    }
}

// ══════════ QR / scan_code ══════════
const qrBase = (window as any).APP_BASE ?? '';
const savingPrefix = ref<number | null>(null);
const editingCode  = ref<number | null>(null);
const codeDraft    = ref('');
const savingCode   = ref(false);

async function saveZonePrefix(zone: ZoneRow) {
    savingPrefix.value = zone.id;
    try {
        const { data } = await axios.put(`/admin/zones/${zone.id}/scan-prefix`, { scan_prefix: zone.scan_prefix ?? '' });
        zone.scan_prefix = data.scan_prefix;
        Swal.fire({ title: 'บันทึก prefix แล้ว', icon: 'success', timer: 900, showConfirmButton: false });
    } catch (e: any) {
        Swal.fire('เกิดข้อผิดพลาด', e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(' '), 'error');
    } finally { savingPrefix.value = null; }
}

function startEditCode(room: RoomRow, zone: ZoneRow) {
    editingCode.value = room.id;
    codeDraft.value = room.scan_code || (zone.scan_prefix ? `${zone.scan_prefix}-` : '');
}

function cancelEditCode() {
    editingCode.value = null;
    codeDraft.value = '';
}

async function saveRoomCode(room: RoomRow) {
    savingCode.value = true;
    try {
        const { data } = await axios.put(`/admin/rooms/${room.id}/scan-code`, { scan_code: codeDraft.value.trim() });
        room.scan_code = data.scan_code;
        editingCode.value = null;
        codeDraft.value = '';
    } catch (e: any) {
        Swal.fire('บันทึกไม่ได้', e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(' '), 'error');
    } finally { savingCode.value = false; }
}

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
            <button v-for="tab in [{ id: 'status', label: 'สถานะพื้นที่', icon: 'fa-toggle-on' }, { id: 'settings', label: 'ตั้งค่า Zone', icon: 'fa-sliders' }, { id: 'tools', label: 'อุปกรณ์', icon: 'fa-toolbox' }, { id: 'qr', label: 'QR', icon: 'fa-qrcode' }]"
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

                <!-- ปุ่มช่วยอธิบาย Auto/Manual × Kiosk -->
                <button @click="showConfirmTypeHelp"
                    class="flex items-center gap-1.5 text-[11px] font-bold text-blue-700 hover:underline">
                    <i class="fa-solid fa-circle-info"></i>
                    Auto / Manual × Kiosk คืออะไร?
                </button>

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
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs text-slate-700 font-medium">{{ room.title }}</span>
                                <button @click="toggleRoomConfirmType(room)"
                                    :disabled="toggling === `room-ct-${room.id}`"
                                    :class="room.confirm_type === 'auto'
                                        ? 'bg-sky-50 text-sky-600 border-sky-200'
                                        : 'bg-amber-50 text-amber-600 border-amber-200'"
                                    class="text-[10px] font-bold px-2 py-0.5 rounded-full border transition-colors disabled:opacity-50 flex items-center gap-1"
                                    :title="room.confirm_type === 'auto' ? 'จองแล้วยืนยันทันที ไม่ต้องรออนุมัติ' : 'ต้องรอเจ้าหน้าที่/ครบสมาชิกก่อนยืนยัน'">
                                    <i :class="toggling === `room-ct-${room.id}` ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-arrows-rotate'"></i>
                                    {{ room.confirm_type === 'auto' ? 'Auto' : 'Manual' }}
                                </button>
                                <button @click="toggleRoomAccess(room)"
                                    :disabled="toggling === `room-ac-${room.id}`"
                                    :class="room.access_control === '1'
                                        ? 'bg-blue-50 text-blue-700 border-blue-200'
                                        : 'bg-slate-50 text-slate-400 border-slate-200'"
                                    class="text-[10px] font-bold px-2 py-0.5 rounded-full border transition-colors disabled:opacity-50 flex items-center gap-1"
                                    :title="room.access_control === '1' ? 'ติด kiosk — สแกนเช็คอิน/อนุมัติเอง' : 'ไม่ติด kiosk — เจ้าหน้าที่เช็คอิน'">
                                    <i :class="toggling === `room-ac-${room.id}` ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-door-closed'"></i>
                                    {{ room.access_control === '1' ? 'Kiosk' : 'ไม่มี Kiosk' }}
                                </button>
                            </div>
                            <button @click="toggleRoom(room)"
                                :disabled="toggling === `room-${room.id}`"
                                :class="room.status === '0'
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : 'bg-slate-300 hover:bg-slate-400'"
                                class="text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition-colors disabled:opacity-50 min-w-[60px] text-center shrink-0">
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
                        <span class="flex items-center gap-2 text-sm font-bold text-slate-900">
                            <i v-if="zone.icon" :class="`fa-solid ${zone.icon}`" class="text-slate-400"></i>
                            {{ zone.title }}
                        </span>
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
                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 mb-1.5">ไอคอนโซน (แสดงที่หน้าแรก)</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    @click="settingsForm.icon = null"
                                    class="flex items-center justify-center w-8 h-8 border rounded-lg transition-colors"
                                    :class="!settingsForm.icon ? 'bg-blue-900 border-blue-900 text-white' : 'border-slate-200 text-slate-300 hover:bg-slate-50'"
                                    title="ไม่มีไอคอน"
                                ><i class="text-xs fa-solid fa-ban"></i></button>
                                <button
                                    v-for="ic in ZONE_ICONS" :key="ic"
                                    @click="settingsForm.icon = ic"
                                    class="flex items-center justify-center w-8 h-8 border rounded-lg transition-colors"
                                    :class="settingsForm.icon === ic ? 'bg-blue-900 border-blue-900 text-white' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
                                ><i class="text-xs fa-solid" :class="ic"></i></button>
                            </div>
                        </div>
                        <label class="flex items-start gap-2 p-3 border border-amber-200 bg-amber-50 rounded-lg cursor-pointer">
                            <input v-model="settingsForm.scan_only" type="checkbox"
                                class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-400" />
                            <span class="text-xs text-amber-800">
                                <span class="font-bold block">ห้ามจองผ่านหน้าเว็บ (ต้องสแกน QR ที่ตัวอุปกรณ์เท่านั้น)</span>
                                ใช้สำหรับโซนที่ต้องการให้ผู้ใช้มาถึงอุปกรณ์จริงก่อนจะจองได้ เช่น เก้าอี้ — หน้าแรกจะยังเห็นตารางว่าง แต่กดจองไม่ได้
                            </span>
                        </label>
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

            <!-- ── TAB 3: อุปกรณ์ ── -->
            <div v-if="activeTab === 'tools' && currentLoc" class="space-y-4">

                <!-- คลังอุปกรณ์ -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/60">
                        <span class="text-sm font-bold text-slate-900"><i class="fa-solid fa-boxes-stacked text-slate-400 mr-1.5"></i>คลังอุปกรณ์ (ใช้ร่วมทุกโซน)</span>
                        <button v-if="editingToolId === null" @click="startAddTool"
                            class="text-[10px] font-bold text-blue-700 hover:underline flex items-center gap-1">
                            <i class="fa-solid fa-plus text-[9px]"></i> เพิ่มอุปกรณ์
                        </button>
                    </div>
                    <div class="p-4">
                        <div v-if="editingToolId !== null" class="flex flex-wrap items-end gap-2 mb-3 p-3 bg-slate-50 rounded-lg">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">ชื่อ</label>
                                <input v-model="toolForm.name" type="text"
                                    class="text-xs px-2.5 py-2 border border-slate-300 rounded-lg w-40 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">ไอคอน (Font Awesome)</label>
                                <div class="flex items-center gap-1.5">
                                    <input v-model="toolForm.icon" type="text" placeholder="fa-tv"
                                        class="text-xs px-2.5 py-2 border border-slate-300 rounded-lg w-32 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                                    <i :class="`fa-solid ${toolForm.icon || 'fa-wrench'} text-slate-500`"></i>
                                </div>
                            </div>
                            <button @click="saveTool" :disabled="savingTool || !toolForm.name.trim()"
                                class="text-xs px-3 py-2 rounded-lg bg-blue-900 text-white hover:bg-blue-800 font-bold disabled:opacity-50 flex items-center gap-1.5">
                                <i v-if="savingTool" class="fa-solid fa-spinner fa-spin"></i>{{ toolForm.id ? 'บันทึก' : 'เพิ่ม' }}
                            </button>
                            <button @click="cancelTool" class="text-xs px-3 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 font-bold">ยกเลิก</button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="t in tools" :key="t.id"
                                class="inline-flex items-center gap-1.5 text-[11px] bg-slate-100 border border-slate-200 text-slate-700 pl-2 pr-1 py-1 rounded-full">
                                <i :class="`fa-solid ${t.icon}`" class="text-[10px] text-slate-500"></i>{{ t.name }}
                                <button @click="startEditTool(t)" class="w-4 h-4 grid place-content-center rounded-full hover:bg-slate-200 text-slate-400 hover:text-blue-600"><i class="fa-solid fa-pen text-[8px]"></i></button>
                                <button @click="deleteTool(t)" class="w-4 h-4 grid place-content-center rounded-full hover:bg-red-100 text-slate-400 hover:text-red-600"><i class="fa-solid fa-xmark text-[9px]"></i></button>
                            </span>
                            <span v-if="!tools.length" class="text-xs text-slate-400">ยังไม่มีอุปกรณ์ในคลัง</span>
                        </div>
                    </div>
                </div>

                <!-- อุปกรณ์แต่ละ zone (กด header เพื่อ show/hide) -->
                <div v-for="zone in currentLoc.zones" :key="zone.id"
                    class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div @click="toggleExpand(zone.id)"
                        class="flex items-center gap-2 px-5 py-3.5 bg-slate-50/60 cursor-pointer hover:bg-slate-100/60 transition-colors select-none"
                        :class="{ 'border-b border-slate-100': expandedZones.has(zone.id) }">
                        <i :class="expandedZones.has(zone.id) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                            class="text-[10px] text-slate-400 w-3"></i>
                        <span class="text-sm font-bold text-slate-900">{{ zone.title }}</span>
                        <span class="text-[10px] text-slate-400">
                            ({{ (zone.tools ?? []).length }} อุปกรณ์ในคลัง · {{ zone.rooms.length }} ห้อง)
                        </span>
                    </div>

                    <template v-if="expandedZones.has(zone.id)">
                    <!-- คลังอุปกรณ์ภายในโซน -->
                    <div class="px-5 py-3.5 border-b border-slate-100">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-bold text-slate-500">ชุดอุปกรณ์ภายในโซน <span class="font-normal text-slate-400">(คลังให้แต่ละห้องเลือกติ๊ก)</span></span>
                            <button v-if="editingZoneTools !== zone.id" @click="openZoneTools(zone)"
                                class="text-[10px] font-bold text-blue-700 hover:underline"><i class="fa-solid fa-pen text-[9px] mr-1"></i>แก้ไข</button>
                        </div>
                        <div v-if="editingZoneTools !== zone.id" class="flex flex-wrap gap-1.5">
                            <span v-for="zt in (zone.tools ?? [])" :key="zt.tool_id"
                                class="text-[11px] bg-slate-100 border border-slate-200 text-slate-700 px-2 py-0.5 rounded-full flex items-center gap-1">
                                <i :class="`fa-solid ${toolById(zt.tool_id)?.icon}`" class="text-[10px] text-slate-500"></i>
                                {{ toolById(zt.tool_id)?.name }}<template v-if="zt.quantity > 1"> ×{{ zt.quantity }}</template>
                            </span>
                            <span v-if="!(zone.tools ?? []).length" class="text-xs text-slate-400">— ไม่มี —</span>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="(row, i) in zoneToolsForm" :key="i" class="flex items-center gap-2">
                                <select v-model.number="row.tool_id" class="text-xs px-2 py-1.5 border border-slate-300 rounded-lg flex-1">
                                    <option v-for="t in tools" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <input v-model.number="row.quantity" type="number" min="1" max="99" class="text-xs px-2 py-1.5 border border-slate-300 rounded-lg w-16" />
                                <button @click="zoneToolsForm.splice(i, 1)" class="text-slate-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <button @click="addZoneToolRow" class="text-[11px] font-bold text-blue-700 hover:underline"><i class="fa-solid fa-plus text-[9px] mr-1"></i>เพิ่มอุปกรณ์</button>
                            <div class="flex justify-end gap-2 pt-1">
                                <button @click="editingZoneTools = null" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 font-bold">ยกเลิก</button>
                                <button @click="saveZoneTools(zone)" :disabled="savingZoneTools"
                                    class="text-xs px-4 py-1.5 rounded-lg bg-blue-900 text-white font-bold disabled:opacity-60 flex items-center gap-1.5">
                                    <i v-if="savingZoneTools" class="fa-solid fa-spinner fa-spin"></i>บันทึก
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ต่อห้อง -->
                    <div class="divide-y divide-slate-50">
                        <div v-for="room in zone.rooms" :key="room.id" class="px-5 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800">{{ room.title }}</div>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        <span v-for="et in effectiveTools(zone, room)" :key="et.tool_id"
                                            class="text-[10px] border bg-slate-100 border-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full flex items-center gap-0.5">
                                            <i :class="`fa-solid ${toolById(et.tool_id)?.icon} text-[9px]`"></i>
                                            {{ toolById(et.tool_id)?.name }}<template v-if="et.quantity > 1"> ×{{ et.quantity }}</template>
                                        </span>
                                        <span v-if="!effectiveTools(zone, room).length" class="text-[10px] text-slate-400">ยังไม่ได้กำหนด</span>
                                    </div>
                                </div>
                                <button v-if="editingRoomTools !== room.id" @click="openRoomTools(zone, room)"
                                    class="text-[10px] font-bold text-blue-700 hover:underline shrink-0"><i class="fa-solid fa-pen text-[9px] mr-1"></i>แก้ไข</button>
                            </div>

                            <div v-if="editingRoomTools === room.id" class="mt-2.5 p-3 bg-slate-50 rounded-lg space-y-2.5">
                                <div>
                                    <div class="text-[10px] font-bold text-slate-500 mb-1">ติ๊กอุปกรณ์ที่ห้องนี้มี (เลือกจากคลังโซน) — ปรับจำนวนได้</div>
                                    <div v-for="row in roomZoneRows" :key="row.tool_id" class="flex items-center gap-2 py-0.5">
                                        <label class="flex items-center gap-1.5 flex-1 text-xs text-slate-700 cursor-pointer">
                                            <input type="checkbox" v-model="row.has" class="rounded border-slate-300 text-blue-600" />
                                            <i :class="`fa-solid ${toolById(row.tool_id)?.icon} text-[10px] text-slate-400`"></i>{{ toolById(row.tool_id)?.name }}
                                        </label>
                                        <input v-model.number="row.quantity" :disabled="!row.has" type="number" min="1" max="99"
                                            class="text-xs px-2 py-1 border border-slate-300 rounded-lg w-14 disabled:bg-slate-100 disabled:text-slate-400" />
                                    </div>
                                    <div v-if="!roomZoneRows.length" class="text-[10px] text-slate-400">โซนนี้ยังไม่มีอุปกรณ์ในคลัง — เพิ่มด้านบนก่อน</div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-bold text-slate-500 mb-1">เพิ่มเฉพาะห้องนี้</div>
                                    <div v-for="(row, i) in roomExtraRows" :key="i" class="flex items-center gap-2 py-0.5">
                                        <select v-model.number="row.tool_id" class="text-xs px-2 py-1 border border-slate-300 rounded-lg flex-1">
                                            <option v-for="t in tools" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                        <input v-model.number="row.quantity" type="number" min="1" max="99" class="text-xs px-2 py-1 border border-slate-300 rounded-lg w-14" />
                                        <button @click="roomExtraRows.splice(i, 1)" class="text-slate-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                    <button @click="addRoomExtraRow" class="text-[11px] font-bold text-blue-700 hover:underline"><i class="fa-solid fa-plus text-[9px] mr-1"></i>เพิ่ม</button>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button @click="editingRoomTools = null" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 font-bold">ยกเลิก</button>
                                    <button @click="saveRoomTools(room)" :disabled="savingRoomTools"
                                        class="text-xs px-4 py-1.5 rounded-lg bg-blue-900 text-white font-bold disabled:opacity-60 flex items-center gap-1.5">
                                        <i v-if="savingRoomTools" class="fa-solid fa-spinner fa-spin"></i>บันทึก
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </template>
                </div>
            </div>

            <!-- ── TAB 4: QR ── -->
            <div v-if="activeTab === 'qr' && currentLoc" class="space-y-4">
                <p class="text-xs text-slate-500">
                    ตั้ง <b>prefix</b> ของโซน (ใช้เป็นค่าตั้งต้นตอนกรอก) → <b>กรอก <code>scan_code</code> ของแต่ละห้องเอง</b> → <b>พิมพ์ QR</b> ไปติดที่ตัวเก้าอี้/จุด
                    <br>QR ปลายทาง: <code>{{ qrBase }}/s/&lt;scan_code&gt;</code> — สแกนแล้วจอง + เช็คอินให้เลย
                </p>

                <div v-for="zone in currentLoc.zones" :key="zone.id"
                    class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div @click="toggleExpand(zone.id)"
                        class="flex items-center gap-2 px-5 py-3.5 bg-slate-50/60 cursor-pointer hover:bg-slate-100/60 select-none"
                        :class="{ 'border-b border-slate-100': expandedZones.has(zone.id) }">
                        <i :class="expandedZones.has(zone.id) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                            class="text-[10px] text-slate-400 w-3"></i>
                        <span class="text-sm font-bold text-slate-900">{{ zone.title }}</span>
                        <span class="text-[10px] text-slate-400">
                            ({{ zone.rooms.filter(r => r.scan_code).length }}/{{ zone.rooms.length }} มีโค้ด)
                        </span>
                    </div>

                    <template v-if="expandedZones.has(zone.id)">
                        <!-- prefix + actions -->
                        <div class="px-5 py-3.5 border-b border-slate-100 flex flex-wrap items-end gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">Prefix ของโซน</label>
                                <input v-model="zone.scan_prefix" type="text" placeholder="เช่น 3F-CH"
                                    class="text-xs px-2.5 py-2 border border-slate-300 rounded-lg w-32 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                            </div>
                            <button @click="saveZonePrefix(zone)" :disabled="savingPrefix === zone.id"
                                class="text-xs px-3 py-2 rounded-lg bg-blue-900 text-white font-bold disabled:opacity-60 flex items-center gap-1.5">
                                <i v-if="savingPrefix === zone.id" class="fa-solid fa-spinner fa-spin"></i>บันทึก
                            </button>
                            <a :href="`${qrBase}/admin/zones/${zone.id}/qr-sheet`" target="_blank" rel="noopener"
                                class="text-xs px-3 py-2 rounded-lg border border-blue-200 text-blue-700 hover:bg-blue-50 font-bold">
                                <i class="fa-solid fa-print mr-1"></i>พิมพ์ QR ทั้งโซน
                            </a>
                        </div>

                        <!-- rooms -->
                        <div class="divide-y divide-slate-50">
                            <div v-for="room in zone.rooms" :key="room.id"
                                class="px-5 py-2.5 flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-slate-800">{{ room.title }}</div>

                                    <div v-if="editingCode === room.id" class="mt-1 flex items-center gap-1.5">
                                        <input v-model="codeDraft" type="text" placeholder="เช่น 3F-CH-001"
                                            @keyup.enter="saveRoomCode(room)" @keyup.esc="cancelEditCode()"
                                            class="text-[11px] font-mono px-2 py-1 border border-slate-300 rounded-lg w-40 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                                        <button @click="saveRoomCode(room)" :disabled="savingCode"
                                            class="text-[10px] font-bold px-2 py-1 rounded-lg bg-blue-900 text-white disabled:opacity-60">
                                            <i v-if="savingCode" class="fa-solid fa-spinner fa-spin"></i><span v-else>บันทึก</span>
                                        </button>
                                        <button @click="cancelEditCode()" class="text-[10px] px-1.5 py-1 text-slate-400 hover:text-slate-600">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                        <span class="text-[10px] text-slate-400">เว้นว่าง = ลบโค้ด</span>
                                    </div>
                                    <div v-else class="text-[11px] font-mono mt-0.5" :class="room.scan_code ? 'text-slate-600' : 'text-slate-300'">
                                        {{ room.scan_code || '— ยังไม่มีโค้ด —' }}
                                    </div>
                                </div>
                                <button v-if="editingCode !== room.id" @click="startEditCode(room, zone)"
                                    class="shrink-0 text-[10px] font-bold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                                    <i class="fa-solid fa-pen mr-1"></i>{{ room.scan_code ? 'แก้ไข' : 'สร้าง' }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</template>
