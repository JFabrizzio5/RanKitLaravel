<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    tournamentId: { type: Number, required: true },
    defaultSearch: { type: String, default: '' }
});

const items = ref<any[]>([]);
const loading = ref(true);
const errorCount = ref(0);

// Detectar parámetros para "simular" el menú de filtros del admin
const getParams = () => {
    if (typeof window === 'undefined') return {};
    const params = new URLSearchParams(window.location.search);
    return {
        sort: params.get('sort') || 'points', // 'points' | 'kills'
        mode: params.get('mode') || 'all',    // 'all' | 'solo' | 'duo' | 'trio' | 'squad'
        type: params.get('type') || 'players',// 'players' | 'teams'
        limit: params.get('limit') || 10,     // Mostrar Top 10 por defecto
        search: params.get('search') || props.defaultSearch || '' 
    };
};

const params = getParams();

const update = async () => {
    // Protección anti-spam: Si fallamos 3 veces seguidas, dejamos de intentar un rato
    if (errorCount.value > 3) return;

    try {
        // @ts-ignore
        const res = await axios.get(route('api.widget.stats', props.tournamentId), { params });
        items.value = res.data;
        loading.value = false;
        errorCount.value = 0; // Reset error count
    } catch (e) { 
        console.error("Widget Error (Connection limit protection active):", e);
        errorCount.value++;
    }
};

const widgetTitle = computed(() => {
    // @ts-ignore
    if (params.search) return 'JUGADOR / EQUIPO';
    // @ts-ignore
    let t = params.type === 'teams' ? 'TOP EQUIPOS' : 'TOP JUGADORES';
    // @ts-ignore
    if (params.sort === 'kills') t += ' (KILLS)';
    // @ts-ignore
    if (params.mode !== 'all') t += ` - ${params.mode.toUpperCase()}`;
    return t;
});

onMounted(() => { 
    // Forzar fondo transparente al montar
    document.body.style.backgroundColor = 'transparent';
    
    update(); 
    // AUMENTADO A 60 SEGUNDOS para evitar error de "max connections" en hosting compartido
    const interval = setInterval(update, 60000); 
    onUnmounted(() => {
        clearInterval(interval);
        document.body.style.backgroundColor = ''; // Limpiar al salir
    });
});
</script>

<template>
    <div class="flex items-start w-full min-h-screen p-4 font-sans antialiased select-none">
        
        <div v-if="params.search && items.length > 0" class="relative">
            <div class="bg-white/95 backdrop-blur shadow-[0_4px_20px_rgba(0,0,0,0.15)] border-l-8 border-indigo-600 rounded-r-xl overflow-hidden min-w-[320px]">
                
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                    <span class="text-[11px] font-black tracking-[0.2em] text-indigo-600 uppercase">
                        {{ params.type === 'teams' ? 'TEAM' : 'PLAYER' }}
                    </span>
                    <span class="text-2xl italic font-black text-gray-800">#{{ items[0].rank }}</span>
                </div>
                
                <div class="p-5">
                    <div class="text-2xl font-black text-gray-800 leading-none uppercase mb-4 truncate max-w-[280px]">
                        <template v-if="params.type === 'teams'">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="m in items[0].member_names" :key="m" class="px-2 py-0.5 text-sm rounded bg-indigo-100 text-indigo-700">{{ m }}</span>
                            </div>
                        </template>
                        <template v-else>
                            {{ items[0].player_name }}
                        </template>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col items-center p-2 rounded-lg bg-indigo-50">
                            <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">PTS</span>
                            <span class="text-xl font-black text-indigo-700">{{ items[0].total_points }}</span>
                        </div>
                        <div class="flex flex-col items-center p-2 rounded-lg bg-red-50">
                            <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest">Kills</span>
                            <span class="text-xl font-black text-red-600">{{ items[0].total_kills }}</span>
                        </div>
                        <div class="flex flex-col items-center p-2 bg-gray-100 rounded-lg">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Games</span>
                            <span class="text-xl font-black text-gray-600">{{ items[0].games_played }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="w-[400px]">
            <div class="overflow-hidden border border-gray-200 shadow-2xl bg-white/95 backdrop-blur rounded-xl">
                <div class="flex items-center justify-between px-5 py-4 bg-white border-b border-gray-100">
                    <h2 class="flex items-center gap-2 text-sm font-black tracking-widest text-gray-800 uppercase">
                        <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        {{ widgetTitle }}
                    </h2>
                </div>
                
                <div class="divide-y divide-gray-100">
                    <div v-for="(item, idx) in items" :key="idx" 
                        class="flex items-center justify-between px-4 py-3 bg-white"
                        :class="{'bg-yellow-50': idx === 0}"
                    >
                        <div class="flex items-center gap-4 overflow-hidden">
                            <div class="flex items-center justify-center w-8 h-8 text-sm font-black rounded-full" 
                                :class="idx === 0 ? 'bg-yellow-400 text-yellow-900' : (idx === 1 ? 'bg-gray-200 text-gray-700' : (idx === 2 ? 'bg-orange-200 text-orange-800' : 'bg-gray-100 text-gray-500'))">
                                {{ item.rank }}
                            </div>
                            
                            <div class="flex flex-col truncate">
                                <div class="w-48 text-sm font-bold text-gray-800 truncate">
                                    <template v-if="params.type === 'teams'">
                                        {{ item.member_names.join(', ') }}
                                    </template>
                                    <template v-else>
                                        {{ item.player_name }}
                                    </template>
                                </div>
                                <div class="text-[10px] text-gray-500 font-semibold flex gap-2">
                                    <span>{{ item.games_played }} P. Jugadas</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <div class="text-lg font-black leading-none" 
                                :class="params.sort === 'kills' ? 'text-red-600' : 'text-indigo-600'">
                                {{ params.sort === 'kills' ? item.total_kills : item.total_points }}
                            </div>
                            <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wider mt-0.5">
                                {{ params.sort === 'kills' ? 'KILLS' : 'PTS' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="items.length === 0 && !loading" class="py-6 text-sm font-medium text-center text-gray-400">
                         Esperando datos...
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style>
/* FORZAR TRANSPARENCIA GLOBAL */
html, body {
    background-color: transparent !important;
    overflow: hidden; /* Evitar scrollbars en OBS */
}
</style>