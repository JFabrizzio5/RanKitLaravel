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

// Detectar parámetros
const getParams = () => {
    if (typeof window === 'undefined') return {};
    const params = new URLSearchParams(window.location.search);
    return {
        sort: params.get('sort') || 'points', 
        mode: params.get('mode') || 'all',    
        type: params.get('type') || 'players',
        limit: parseInt(params.get('limit') || '10'),     
        search: params.get('search') || props.defaultSearch || '' 
    };
};

const params = getParams();

const update = async () => {
    if (errorCount.value > 3) return;
    try {
        // @ts-ignore
        const res = await axios.get(route('api.widget.stats', props.tournamentId), { params });
        items.value = res.data;
        loading.value = false;
        errorCount.value = 0;
    } catch (e) { 
        errorCount.value++;
    }
};

// Buscamos quién tiene más kills para resaltarlo
const maxKills = computed(() => {
    if (!items.value.length) return 0;
    return Math.max(...items.value.map(i => i.total_kills || 0));
});

const widgetTitle = computed(() => {
    if (params.search) return 'PLAYER TRACKING';
    let t = params.type === 'teams' ? 'TOP EQUIPOS' : 'TOP PLAYERS';
    if (params.sort === 'kills') t += ' (BY KILLS)';
    return t;
});

onMounted(() => { 
    document.body.style.backgroundColor = 'transparent';
    update(); 
    // Actualización cada 60s
    const interval = setInterval(update, 60000); 
    onUnmounted(() => clearInterval(interval));
});
</script>

<template>
    <div class="flex items-start w-full min-h-screen p-6 font-sans antialiased select-none">
        
        <!-- VISTA TRACKING INDIVIDUAL (SEARCH) -->
        <div v-if="params.search && items.length > 0" class="relative animate-slide-in">
            <div class="relative overflow-hidden bg-[#050505] border-2 border-white shadow-neo-purple min-w-[360px]">
                
                <!-- Header con SVG Rankit -->
                <div class="flex items-center justify-between px-4 py-3 bg-[#0a0a0a] border-b-2 border-[#bf00ff]">
                    <div class="flex items-center gap-3">
                        <img src="/BellzCup.png" alt="BellzCup" class="h-7">
                        <div class="w-[30px] h-[30px]">
                            <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white"/> 
                                <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white"/>
                                <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff"/>
                            </svg>
                        </div>
                    </div>
                    <span class="text-xl italic font-black text-white font-display">#{{ items[0].rank }}</span>
                </div>

                <div class="p-5">
                    <div class="mb-4 text-2xl italic font-black text-white uppercase font-display">
                        {{ params.type === 'teams' ? items[0].member_names.join(' / ') : items[0].player_name }}
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="flex flex-col items-center p-2 bg-[#111] border border-white/10 border-b-[#bf00ff] border-b-2">
                            <span class="text-[9px] font-bold text-gray-500 uppercase">PUNTOS</span>
                            <span class="text-2xl font-black text-[#bf00ff] font-display">{{ items[0].total_points }}</span>
                        </div>
                        <div class="flex flex-col items-center p-2 bg-[#111] border border-white/10 border-b-red-500 border-b-2">
                            <span class="text-[9px] font-bold text-gray-500 uppercase">KILLS</span>
                            <span class="text-2xl font-black text-red-500 font-display">{{ items[0].total_kills }}</span>
                        </div>
                        <div class="flex flex-col items-center p-2 bg-[#111] border border-white/10">
                            <span class="text-[9px] font-bold text-gray-500 uppercase">PARTIDAS</span>
                            <span class="text-2xl font-black text-white font-display">{{ items[0].games_played }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VISTA TABLA GENERAL (Fija a 10) -->
        <div v-else class="w-[400px] animate-slide-in">
            <div class="overflow-hidden bg-[#050505] border-2 border-white shadow-neo-purple">
                
                <div class="flex items-center justify-between px-5 py-3 bg-[#0a0a0a] border-b-2 border-[#bf00ff]">
                    <div class="flex items-center gap-3">
                         <img src="https://rankit.pro/public/BellzCup.png" alt="BellzCup" class="h-6">
                         <div class="w-[24px] h-[24px]">
                            <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white"/> 
                                <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white"/>
                                <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff"/>
                            </svg>
                         </div>
                         <h2 class="text-[10px] font-black tracking-widest text-white uppercase font-display">
                            {{ widgetTitle }}
                        </h2>
                    </div>
                    <span class="w-2 h-2 bg-[#bf00ff] rounded-full animate-pulse"></span>
                </div>
                
                <div class="divide-y divide-white/5">
                    <div v-for="(item, idx) in items" :key="idx" 
                        class="flex items-center justify-between px-4 py-2.5 transition-colors relative overflow-hidden"
                        :class="[
                            idx === 0 ? 'bg-[#bf00ff]/10' : 'bg-transparent',
                            item.total_kills === maxKills && maxKills > 0 ? 'border-r-4 border-red-600' : ''
                        ]"
                    >
                        <!-- Barra roja de fondo si es el killer (opcional, muy sutil) -->
                        <div v-if="item.total_kills === maxKills && maxKills > 0" class="absolute inset-0 pointer-events-none bg-red-600/5"></div>

                        <div class="relative z-10 flex items-center gap-4">
                            <!-- Rank con Iconos para Top 3 -->
                            <div class="flex items-center justify-center w-8 h-8 text-xs font-black skew-x-[-10deg] border border-white/20" 
                                :class="[
                                    idx === 0 ? 'bg-yellow-500 text-black border-yellow-300' : 
                                    idx === 1 ? 'bg-gray-300 text-black border-white' : 
                                    idx === 2 ? 'bg-orange-600 text-white border-orange-400' : 
                                    'bg-[#1a1a1a] text-gray-500'
                                ]">
                                <span class="skew-x-[10deg] flex items-center justify-center">
                                    <template v-if="idx === 0">🏆</template>
                                    <template v-else-if="idx === 1">🥈</template>
                                    <template v-else-if="idx === 2">🥉</template>
                                    <template v-else>{{ item.rank }}</template>
                                </span>
                            </div>
                            
                            <div class="flex flex-col truncate">
                                <div class="text-sm font-bold tracking-tight uppercase truncate w-44 font-display"
                                     :class="item.total_kills === maxKills && maxKills > 0 ? 'text-red-500' : 'text-white'">
                                    {{ params.type === 'teams' ? item.member_names.join(', ') : item.player_name }}
                                </div>
                                <div class="text-[8px] font-bold text-gray-600 uppercase">{{ item.games_played }} Games</div>
                            </div>
                        </div>
                        
                        <div class="relative z-10 text-right">
                            <div class="text-lg font-black leading-none font-display" 
                                :class="params.sort === 'kills' ? 'text-red-500' : 'text-[#bf00ff]'">
                                {{ params.sort === 'kills' ? item.total_kills : item.total_points }}
                            </div>
                            <div class="text-[7px] font-bold text-gray-500 uppercase mt-0.5 tracking-tighter">
                                {{ params.sort === 'kills' ? 'KILLS' : 'PUNTOS' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 bg-[#000] flex justify-center items-center gap-3 border-t border-white/5">
                    <div class="w-[14px] h-[14px]">
                        <svg viewBox="0 0 100 100" fill="none">
                            <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white" opacity="0.3"/> 
                            <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white" opacity="0.3"/>
                            <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff" opacity="0.5"/>
                        </svg>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30">Rankit.pro</span>
                </div>
            </div>
        </div>

    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@700&family=Archivo:wght@600;800&display=swap');

html, body { background-color: transparent !important; overflow: hidden; }
.font-display { font-family: 'Chakra Petch', sans-serif; }
.shadow-neo-purple { box-shadow: 6px 6px 0px 0px #000, 8px 8px 0px 0px #bf00ff; }
.animate-slide-in { animation: slideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) both; }
@keyframes slideIn { from { transform: translateX(-20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>