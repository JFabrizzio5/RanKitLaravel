<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';
import axios from 'axios';

// Corrección: Usamos la sintaxis de objeto de runtime para defineProps
// Esto evita el error de compilación con la sintaxis de tipos genéricos <{}>
const props = defineProps({
    tournamentId: {
        type: Number,
        required: true
    },
    defaultSearch: {
        type: String,
        default: ''
    }
});

const items = ref<any[]>([]);
const loading = ref(true);

// Detectar parámetros de la URL del navegador (OBS Browser Source)
const getParams = () => {
    // Verificamos si window existe para evitar errores en SSR
    if (typeof window === 'undefined') return {};
    
    const params = new URLSearchParams(window.location.search);
    return {
        sort: params.get('sort') || 'points', // 'points' | 'kills'
        mode: params.get('mode') || 'all',    // 'all' | 'solo' | 'duo' ...
        type: params.get('type') || 'players',// 'players' | 'teams'
        limit: params.get('limit') || 5,      // Cuantos mostrar
        search: params.get('search') || props.defaultSearch || '' // Trackear especifico
    };
};

const params = getParams();

const update = async () => {
    try {
        // @ts-ignore: route is globally defined in Laravel Inertia
        const url = route('api.widget.stats', props.tournamentId);
        
        const res = await axios.get(url, {
            params: params
        });
        items.value = res.data;
        loading.value = false;
    } catch (e) { 
        console.error("Widget Error:", e);
    }
};

// Título dinámico según filtros
const widgetTitle = computed(() => {
    // @ts-ignore
    if (params.search) return 'TRACKER';
    let t = 'TOP ';
    // @ts-ignore
    t += params.type === 'teams' ? 'TEAMS' : 'PLAYERS';
    // @ts-ignore
    t += params.sort === 'kills' ? ' (KILLS)' : '';
    return t;
});

onMounted(() => { 
    update(); 
    // Mantenemos el intervalo de 60 segundos para proteger la base de datos
    const interval = setInterval(update, 60000); 
    onUnmounted(() => clearInterval(interval));
});
</script>

<template>
    <!-- Contenedor Principal: Transparente por defecto -->
    <div class="font-sans antialiased select-none" style="background-color: transparent;">
        
        <!-- VISTA TRACKER (Solo 1 resultado) -->
        <!-- @ts-ignore -->
        <div v-if="params.search && items.length > 0" class="inline-flex flex-col">
            <div class="relative overflow-hidden bg-gray-900/90 text-white border-l-4 border-indigo-500 rounded-r-lg shadow-[0_0_15px_rgba(0,0,0,0.5)] min-w-[300px]">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-2 border-b bg-gradient-to-r from-indigo-900/50 to-transparent border-white/10">
                    <span class="text-[10px] font-black tracking-[0.2em] text-indigo-300 uppercase">
                        <!-- @ts-ignore -->
                        {{ params.type === 'teams' ? 'TEAM TRACKER' : 'PLAYER TRACKER' }}
                    </span>
                    <span class="text-xl italic font-black text-yellow-400">#{{ items[0].rank }}</span>
                </div>
                
                <!-- Body -->
                <div class="p-4">
                    <div class="text-2xl font-bold leading-none uppercase mb-3 truncate max-w-[280px]">
                        <!-- @ts-ignore -->
                        <template v-if="params.type === 'teams'">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="m in items[0].member_names" :key="m" class="px-1 text-sm rounded bg-indigo-500/20">{{ m }}</span>
                            </div>
                        </template>
                        <template v-else>
                            {{ items[0].player_name }}
                        </template>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2">
                        <div class="p-2 text-center border rounded bg-black/40 border-white/5">
                            <div class="text-[9px] text-gray-400 uppercase tracking-wider">PTS</div>
                            <div class="text-xl font-black text-indigo-400">{{ items[0].total_points }}</div>
                        </div>
                        <div class="p-2 text-center border rounded bg-black/40 border-white/5">
                            <div class="text-[9px] text-gray-400 uppercase tracking-wider">Kills</div>
                            <div class="text-xl font-bold text-red-400">{{ items[0].total_kills }}</div>
                        </div>
                        <div class="p-2 text-center border rounded bg-black/40 border-white/5">
                            <div class="text-[9px] text-gray-400 uppercase tracking-wider">Games</div>
                            <div class="text-xl font-bold text-gray-300">{{ items[0].games_played }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VISTA LISTA (Top 5/10) -->
        <div v-else class="w-[350px]">
            <div class="overflow-hidden border shadow-2xl bg-gray-900/80 backdrop-blur-sm rounded-xl border-white/5">
                <div class="flex items-center justify-between px-4 py-3 border-b bg-gradient-to-r from-indigo-900/80 to-gray-900 border-indigo-500/30">
                    <h2 class="text-xs font-black tracking-widest text-indigo-300 uppercase">
                        {{ widgetTitle }}
                    </h2>
                    <!-- @ts-ignore -->
                    <span v-if="params.mode !== 'all'" class="text-[9px] bg-indigo-500 text-white px-1.5 py-0.5 rounded font-bold uppercase">{{ params.mode }}</span>
                </div>
                
                <div class="divide-y divide-white/5">
                    <div v-for="(item, idx) in items" :key="idx" 
                        class="flex items-center justify-between px-3 py-2 transition-colors bg-transparent"
                        :class="{'bg-yellow-500/10': idx === 0}"
                    >
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-6 font-mono text-lg font-bold text-center" 
                                :class="idx === 0 ? 'text-yellow-400' : (idx === 1 ? 'text-gray-300' : (idx === 2 ? 'text-orange-400' : 'text-gray-500'))">
                                #{{ item.rank }}
                            </div>
                            
                            <div class="flex flex-col truncate">
                                <div class="w-40 text-sm font-bold text-white truncate">
                                    <!-- @ts-ignore -->
                                    <template v-if="params.type === 'teams'">
                                        {{ item.member_names.join(', ') }}
                                    </template>
                                    <template v-else>
                                        {{ item.player_name }}
                                    </template>
                                </div>
                                <div class="text-[9px] text-gray-400 flex gap-2">
                                    <span>{{ item.games_played }} Games</span>
                                    <!-- @ts-ignore -->
                                    <span v-if="params.sort !== 'kills'" class="text-red-300">{{ item.total_kills }} Kills</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pl-2 text-right">
                            <!-- @ts-ignore -->
                            <div class="text-base font-black leading-none" 
                                :class="params.sort === 'kills' ? 'text-red-400' : 'text-indigo-300'">
                                <!-- @ts-ignore -->
                                {{ params.sort === 'kills' ? item.total_kills : item.total_points }}
                            </div>
                            <div class="text-[8px] font-bold text-gray-500 uppercase tracking-wider">
                                <!-- @ts-ignore -->
                                {{ params.sort === 'kills' ? 'KILLS' : 'PTS' }}
                            </div>
                        </div>
                    </div>

                    <!-- @ts-ignore -->
                    <div v-if="items.length === 0 && !loading" class="py-4 text-xs text-center text-gray-500">
                        <!-- @ts-ignore -->
                        {{ params.search ? 'Jugador no encontrado' : 'Esperando datos...' }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>