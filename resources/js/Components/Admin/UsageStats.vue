<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

interface ZoneCol { id: number; title: string; icon: string | null }
interface MonthRow { month: string; label: string; zones: Record<number, number>; total: number }
interface LocStat { id: number; title: string; zones: ZoneCol[]; rows: MonthRow[]; total: number }
interface StatsRes { from: string; to: string; unit: string; locations: LocStat[]; total: number }

const pad = (n: number) => String(n).padStart(2, '0');
const ym  = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
const thisMonth = ym(new Date());

const from    = ref(thisMonth);
const to      = ref(thisMonth);
const stats   = ref<StatsRes | null>(null);
const loading = ref(false);
const error   = ref('');

type Preset = 'this' | 'last3' | 'last6' | 'year';
const presets: { key: Preset; label: string }[] = [
    { key: 'this',  label: 'เดือนนี้' },
    { key: 'last3', label: '3 เดือนล่าสุด' },
    { key: 'last6', label: '6 เดือนล่าสุด' },
    { key: 'year',  label: 'ปีนี้' },
];
const activePreset = computed<Preset | null>(() => {
    if (to.value !== thisMonth) return null;
    const now = new Date();
    const back = (n: number) => ym(new Date(now.getFullYear(), now.getMonth() - n, 1));
    if (from.value === thisMonth) return 'this';
    if (from.value === back(2)) return 'last3';
    if (from.value === back(5)) return 'last6';
    if (from.value === `${now.getFullYear()}-01`) return 'year';
    return null;
});

function applyPreset(p: Preset) {
    const now = new Date();
    const back = (n: number) => ym(new Date(now.getFullYear(), now.getMonth() - n, 1));
    to.value   = thisMonth;
    from.value = p === 'this' ? thisMonth : p === 'last3' ? back(2) : p === 'last6' ? back(5) : `${now.getFullYear()}-01`;
    fetchStats();
}

async function fetchStats() {
    if (!from.value || !to.value) return;
    loading.value = true;
    error.value   = '';
    try {
        const { data } = await axios.get('/admin/stats/usage', { params: { from: from.value, to: to.value } });
        stats.value = data;
    } catch (e: any) {
        error.value = e.response?.data?.message ?? 'โหลดสถิติไม่สำเร็จ';
    } finally {
        loading.value = false;
    }
}

const multiMonth = computed(() => (stats.value?.locations[0]?.rows.length ?? 0) > 1);

const zoneSum = (loc: LocStat, zoneId: number) =>
    loc.rows.reduce((s, r) => s + (r.zones[zoneId] ?? 0), 0);

const fmt = (n: number) => n.toLocaleString('th-TH');

const LOC_ICONS = ['fa-book', 'fa-circle-play', 'fa-house', 'fa-building'];
const LOC_COLORS = ['text-violet-600', 'text-slate-700', 'text-amber-500', 'text-blue-600'];

onMounted(fetchStats);
</script>

<template>
    <div class="space-y-5">
        <!-- Header + filter -->
        <div class="p-5 bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                        <i class="fa-solid fa-chart-column text-amber-500"></i>
                        สถิติการเข้าใช้งาน
                    </h3>
                    <p class="mt-1 text-[11px] text-slate-400">
                        นับเฉพาะผู้ที่เช็คอินแล้ว · 1 คน ต่อ 1 ห้อง ต่อ 1 วัน นับเป็น 1 (จองหลายชั่วโมงติดกันนับครั้งเดียว)
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="flex flex-wrap items-center gap-1 p-1 bg-slate-100 rounded-xl w-fit">
                        <button
                            v-for="p in presets"
                            :key="p.key"
                            @click="applyPreset(p.key)"
                            :class="activePreset === p.key ? 'bg-white text-blue-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all"
                        >{{ p.label }}</button>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <input
                            v-model="from"
                            type="month"
                            :max="thisMonth"
                            @change="fetchStats"
                            class="px-2 py-1.5 text-xs border rounded-lg border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        />
                        <span>ถึง</span>
                        <input
                            v-model="to"
                            type="month"
                            :max="thisMonth"
                            @change="fetchStats"
                            class="px-2 py-1.5 text-xs border rounded-lg border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading / error -->
        <div v-if="loading" class="py-12 text-xs text-center text-slate-400">
            <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลด...
        </div>
        <div v-else-if="error" class="p-4 text-xs text-red-600 border border-red-200 rounded-xl bg-red-50">
            <i class="mr-1 fa-solid fa-triangle-exclamation"></i>{{ error }}
        </div>

        <template v-else-if="stats">
            <!-- Grand total -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                    <p class="text-xs font-semibold text-slate-400">รวมทุกพื้นที่</p>
                    <p class="mt-1 text-2xl font-bold text-blue-900">{{ fmt(stats.total) }}</p>
                    <p class="text-[10px] text-slate-400">{{ stats.unit }}</p>
                </div>
                <div
                    v-for="loc in stats.locations"
                    :key="loc.id"
                    class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200"
                >
                    <p class="text-xs font-semibold truncate text-slate-400" :title="loc.title">{{ loc.title }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-700">{{ fmt(loc.total) }}</p>
                    <p class="text-[10px] text-slate-400">{{ stats.unit }}</p>
                </div>
            </div>

            <!-- Per-location tables -->
            <div
                v-for="(loc, li) in stats.locations"
                :key="loc.id"
                class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <div class="flex items-center gap-2 px-5 py-3 border-b border-slate-200">
                    <i :class="`fa-solid ${LOC_ICONS[li % LOC_ICONS.length]} ${LOC_COLORS[li % LOC_COLORS.length]}`"></i>
                    <h4 class="text-sm font-bold text-slate-900">{{ loc.title }}</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-center">
                        <thead>
                            <tr class="font-bold border-b bg-amber-50 border-slate-200 text-slate-700">
                                <th class="p-3 text-left whitespace-nowrap">เดือน</th>
                                <th v-for="z in loc.zones" :key="z.id" class="p-3 min-w-[6rem]">{{ z.title }}</th>
                                <th class="p-3 bg-slate-100">ทั้งหมด</th>
                                <th class="p-3 bg-slate-100">หน่วย</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            <tr v-for="r in loc.rows" :key="r.month" class="hover:bg-slate-50/70">
                                <td class="p-3 font-semibold text-left whitespace-nowrap text-slate-800">{{ r.label }}</td>
                                <td
                                    v-for="z in loc.zones"
                                    :key="z.id"
                                    :class="r.zones[z.id] ? '' : 'text-slate-300'"
                                    class="p-3"
                                >{{ fmt(r.zones[z.id] ?? 0) }}</td>
                                <td class="p-3 font-bold bg-slate-50 text-slate-900">{{ fmt(r.total) }}</td>
                                <td class="p-3 bg-slate-50 text-slate-400">{{ stats.unit }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="multiMonth">
                            <tr class="font-bold border-t-2 border-slate-200 bg-blue-50/60 text-blue-900">
                                <td class="p-3 text-left">รวม</td>
                                <td v-for="z in loc.zones" :key="z.id" class="p-3">{{ fmt(zoneSum(loc, z.id)) }}</td>
                                <td class="p-3">{{ fmt(loc.total) }}</td>
                                <td class="p-3 font-normal text-slate-400">{{ stats.unit }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
