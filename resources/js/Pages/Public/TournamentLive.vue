<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ tournament: Object });

// --- ESTADOS DE DATOS ---
const stats = ref<any>({ matches: [], ranking: [] });
const progressText = ref("Cargando...");
const isLoading = ref(true);

// --- ESTADOS DE FILTROS ---
const selectedMatchId = ref<number | null>(null);
const leaderboardType = ref('players'); // 'players' | 'teams'
const filterMode = ref('all');          // 'all', 'solo', 'duo'...
const sortBy = ref('points');           // 'points' | 'kills'
const expandedRowIndex = ref<number | null>(null);

let pollInterval: any = null;

const loadData = async () => {
    try {
        const url = `/api/live/${props.tournament?.id}/data`;
        
        const params: any = {
            type: leaderboardType.value,
            mode: filterMode.value,
            sort: sortBy.value
        };
        if (selectedMatchId.value) {
            params.match_id = selectedMatchId.value;
        }

        const res = await axios.get(url, { params });
        
        stats.value.matches = res.data.matches;
        stats.value.ranking = res.data.ranking;
        progressText.value = res.data.tournament.progress;
    } catch (e) { 
        console.error("Error obteniendo datos:", e); 
    } finally {
        isLoading.value = false;
    }
};

// --- MÉTODOS DE UI ---

const toggleMatchFilter = (matchId: number) => {
    if (selectedMatchId.value === matchId) {
        selectedMatchId.value = null; 
    } else {
        selectedMatchId.value = matchId; 
    }
    expandedRowIndex.value = null; 
    isLoading.value = true;
    loadData();
};

const setGlobal = () => {
    selectedMatchId.value = null;
    expandedRowIndex.value = null;
    isLoading.value = true;
    loadData();
};

const switchTab = (type: string) => {
    leaderboardType.value = type;
    expandedRowIndex.value = null;
    isLoading.value = true;
    loadData();
};

const switchMode = (mode: string) => {
    filterMode.value = mode;
    expandedRowIndex.value = null;
    isLoading.value = true;
    loadData();
};

const toggleSort = () => {
    sortBy.value = sortBy.value === 'points' ? 'kills' : 'points';
    isLoading.value = true;
    loadData();
};

const toggleRow = (index: number) => {
    expandedRowIndex.value = expandedRowIndex.value === index ? null : index;
};

// --- MÉTODOS OBS ---

// 1. Link para TABLA GLOBAL (Top 10)
const copyGlobalObsLink = () => {
    const baseUrl = `${window.location.origin}/widget/obs/global/${props.tournament?.id}`;
    // Limitamos a 10 para que no sea una lista infinita en OBS
    const query = `?type=${leaderboardType.value}&mode=${filterMode.value}&sort=${sortBy.value}&limit=10`;
    
    const fullUrl = baseUrl + query;
    navigator.clipboard.writeText(fullUrl);
    alert(`✅ Link de TABLA COPIADO.\n\nConfiguración: Top 10 ${leaderboardType.value} ordenado por ${sortBy.value}.\nPégalo en OBS.`);
};

// 2. Link para TRACKER INDIVIDUAL (Jugador/Equipo específico)
const copyIndividualObsLink = (item: any) => {
    const baseUrl = `${window.location.origin}/widget/obs/global/${props.tournament?.id}`;
    let query = `?type=${leaderboardType.value}&mode=${filterMode.value}&sort=${sortBy.value}`;
    
    let searchTerm = '';
    if (leaderboardType.value === 'teams' && item.member_names && item.member_names.length > 0) {
        searchTerm = item.member_names[0]; 
    } else if (item.player_name) {
        searchTerm = item.player_name;
    }

    if (searchTerm) {
        query += `&search=${encodeURIComponent(searchTerm)}`;
    }

    const fullUrl = baseUrl + query;
    navigator.clipboard.writeText(fullUrl);
    alert(`✅ Link de TRACKER COPIADO para: ${searchTerm}\n\nPégalo en OBS.`);
};

// Helper formateo decimales
const formatDec = (num: any) => {
    const n = parseFloat(num);
    return isNaN(n) ? '0' : n.toFixed(1).replace(/\.0$/, '');
};

onMounted(() => {
    loadData();
    // INTERVALO DE 90 SEGUNDOS (1.5 Minutos) para reducir carga
    pollInterval = setInterval(loadData, 90000); 
});

onUnmounted(() => clearInterval(pollInterval));
</script>

<template>
    <Head :title="tournament?.name" />
    
    <div class="min-h-screen bg-[#0a0a0a] text-white font-sans selection:bg-indigo-500/30">
        <header class="sticky top-0 z-50 border-b border-gray-800 bg-black/80 backdrop-blur-md">
            <div class="flex items-center justify-between px-4 py-4 mx-auto max-w-7xl">
                <div class="flex flex-col">
                    <h1 class="text-xl italic font-black tracking-tight text-white uppercase md:text-2xl">
                        {{ tournament?.name }}
                    </h1>
                    <span class="text-[10px] md:text-xs font-mono tracking-widest text-indigo-400 uppercase font-bold">
                        {{ progressText }}
                    </span>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 border rounded-full bg-red-900/10 border-red-500/50 animate-pulse">
                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                    <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider">En Vivo</span>
                </div>
            </div>
        </header>

        <main class="grid grid-cols-1 gap-6 px-4 py-8 mx-auto max-w-7xl lg:grid-cols-12">
            
            <div class="space-y-6 lg:col-span-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold tracking-widest text-gray-500 uppercase">Seleccionar Vista</h2>
                    <button @click="setGlobal" class="text-[10px] px-2 py-1 rounded transition-all font-bold border" :class="!selectedMatchId ? 'bg-indigo-600 border-indigo-500 text-white' : 'bg-gray-800 border-gray-700 text-gray-400'">VER GLOBAL</button>
                </div>

                <div v-if="isLoading && stats.matches.length === 0" class="space-y-4 animate-pulse">
                    <div class="h-24 bg-gray-900 border border-gray-800 rounded-xl"></div>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="match in stats.matches" :key="match.id" @click="toggleMatchFilter(match.id)"
                        class="relative p-4 overflow-hidden transition-all border cursor-pointer rounded-xl group"
                        :class="selectedMatchId === match.id ? 'bg-indigo-900/30 border-indigo-500' : 'bg-gray-900/40 border-gray-800 hover:bg-gray-800'"
                    >
                        <div class="relative z-10 flex items-center justify-between">
                            <div>
                                <span class="text-[9px] font-bold text-gray-400 uppercase bg-white/5 px-1.5 py-0.5 rounded">{{ match.mode }}</span>
                                <div class="font-mono text-2xl font-black text-white">{{ match.code }}</div>
                            </div>
                            <div class="text-right">
                                <span v-if="match.is_active" class="text-[9px] bg-indigo-600 px-2 py-0.5 rounded text-white font-bold">ACTIVA</span>
                                <span v-else class="text-[9px] text-gray-500 border border-gray-700 px-2 py-0.5 rounded">FINAL</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="flex flex-col gap-4 p-4 mb-4 border border-gray-800 shadow-xl bg-gray-900/80 rounded-xl backdrop-blur-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            <div class="flex p-1 bg-black border border-gray-700 rounded">
                                <button @click="switchTab('players')" :class="{'bg-gray-700 text-white': leaderboardType === 'players', 'text-gray-500': leaderboardType !== 'players'}" class="px-3 py-1 text-xs font-bold uppercase transition-all rounded">Jugadores</button>
                                <button @click="switchTab('teams')" :class="{'bg-gray-700 text-white': leaderboardType === 'teams', 'text-gray-500': leaderboardType !== 'teams'}" class="px-3 py-1 text-xs font-bold uppercase transition-all rounded">Equipos</button>
                            </div>
                            
                            <button @click="toggleSort" class="px-3 py-1 text-xs font-bold text-indigo-300 transition-colors border rounded border-indigo-900/50 hover:bg-indigo-900/20 bg-indigo-900/10">
                                {{ sortBy === 'points' ? 'Por Puntos 🏆' : 'Por Kills ⚔️' }}
                            </button>

                            <button @click="copyGlobalObsLink" class="flex items-center gap-1 px-3 py-1 text-xs font-bold text-white transition-colors bg-teal-700 border border-teal-500 rounded shadow-lg hover:bg-teal-600 shadow-teal-900/50">
                                📺 Tabla OBS
                            </button>
                        </div>

                        <div v-if="!selectedMatchId" class="flex gap-1 overflow-x-auto">
                            <button v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" :key="m" @click="switchMode(m)"
                                :class="filterMode === m ? 'text-indigo-400 border-indigo-500' : 'text-gray-500 border-transparent'"
                                class="px-2 py-1 text-[10px] font-bold uppercase border-b-2 hover:text-gray-300 transition-colors whitespace-nowrap"
                            >
                                {{ m === 'all' ? 'Todas' : m }}
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-hidden border border-gray-800 shadow-2xl bg-gray-900/80 rounded-xl backdrop-blur-sm min-h-[400px]">
                    <div v-if="isLoading" class="flex items-center justify-center h-64">
                        <div class="w-8 h-8 border-b-2 border-indigo-500 rounded-full animate-spin"></div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-bold text-gray-400 uppercase border-b border-gray-700/50 bg-black/40">
                                <tr>
                                    <th class="w-12 p-3 text-center">#</th>
                                    <th class="p-3">{{ leaderboardType === 'teams' ? 'Equipo' : 'Jugador' }}</th>
                                    <th class="p-3 text-center">Games</th>
                                    <th class="p-3 text-center cursor-pointer hover:text-white" @click="sortBy='kills';loadData()">Kills</th>
                                    <th class="p-3 text-right cursor-pointer hover:text-white" @click="sortBy='points';loadData()">Pts</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-800/50">
                                <template v-for="(item, idx) in stats.ranking" :key="idx">
                                    <tr @click="toggleRow(idx)" class="transition-colors cursor-pointer hover:bg-white/5 group">
                                        <td class="p-3 font-mono text-center">
                                            <span v-if="idx < 3" class="text-base">{{ ['🥇','🥈','🥉'][idx] }}</span>
                                            <span v-else class="font-bold text-indigo-400 opacity-60">#{{ idx + 1 }}</span>
                                        </td>
                                        
                                        <td class="p-3 font-bold text-white">
                                            <div class="flex items-center justify-between gap-4">
                                                <div>
                                                    <div v-if="leaderboardType === 'teams'" class="flex flex-wrap gap-1">
                                                        <span v-for="(m, i) in item.member_names" :key="i" class="px-1.5 py-0.5 text-[9px] text-indigo-200 rounded bg-indigo-900/30 border border-indigo-500/20">{{ m }}</span>
                                                    </div>
                                                    <div v-else>{{ item.player_name }}</div>
                                                    <div class="text-[9px] text-gray-600 mt-0.5 font-normal group-hover:text-indigo-400 transition-colors">
                                                        {{ expandedRowIndex === idx ? '▲ Ocultar detalles' : '▼ Ver promedios' }}
                                                    </div>
                                                </div>
                                                
                                                <button @click.stop="copyIndividualObsLink(item)" class="text-[10px] font-bold bg-white/5 hover:bg-white/20 text-gray-400 hover:text-indigo-300 border border-gray-700 rounded px-2 py-1 transition-all flex items-center gap-1" title="Copiar Link para OBS">
                                                    🔍 <span class="hidden sm:inline">Track</span>
                                                </button>
                                            </div>
                                        </td>
                                        
                                        <td class="p-3 font-mono text-xs text-center text-gray-500">{{ item.games_played }}</td>
                                        <td class="p-3 font-mono text-center text-red-400">{{ item.total_kills }}</td>
                                        <td class="p-3 font-mono text-lg font-bold text-right text-yellow-400">{{ formatDec(item.total_points) }}</td>
                                    </tr>

                                    <tr v-if="expandedRowIndex === idx" class="bg-indigo-900/10 animate-fadeIn">
                                        <td colspan="5" class="p-3 border-b border-gray-800/50">
                                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                <div class="p-2 border rounded bg-black/40 border-gray-700/50">
                                                    <span class="block text-[10px] text-gray-500 uppercase">Avg Puntos</span>
                                                    <span class="text-sm font-bold text-green-400">{{ formatDec(item.avg_points) }}</span>
                                                </div>
                                                <div class="p-2 border rounded bg-black/40 border-gray-700/50">
                                                    <span class="block text-[10px] text-gray-500 uppercase">Avg Kills</span>
                                                    <span class="text-sm font-bold text-red-400">{{ formatDec(item.avg_kills) }}</span>
                                                </div>
                                                <div class="p-2 border rounded bg-black/40 border-gray-700/50">
                                                    <span class="block text-[10px] text-gray-500 uppercase">Avg Top</span>
                                                    <span class="text-sm font-bold text-yellow-400">#{{ formatDec(item.avg_placement) }}</span>
                                                </div>
                                                <div class="p-2 border rounded bg-black/40 border-gray-700/50">
                                                    <span class="block text-[10px] text-gray-500 uppercase">Mejor Top</span>
                                                    <span class="text-sm font-bold text-white">#{{ item.best_placement }}</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                
                                <tr v-if="stats.ranking.length === 0" class="animate-fadeIn">
                                    <td colspan="5" class="p-12 text-center text-gray-500">
                                        No hay datos con los filtros actuales.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</template>