<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ 
    tournamentId: Number, 
    playerName: String 
});

const stats = ref<any>({ rank: '-', total_points: 0, total_kills: 0 });

const update = async () => {
    try {
        // @ts-ignore
        const res = await axios.get(route('api.widget.stats', { 
            id: props.tournamentId, 
            player: props.playerName 
        }));
        stats.value = res.data;
    } catch (e) {
        // Silencioso para OBS
    }
};

onMounted(() => { 
    document.body.style.backgroundColor = 'transparent';
    update(); 
    const interval = setInterval(update, 60000); 
    onUnmounted(() => clearInterval(interval));
});
</script>

<template>
    <div class="inline-flex items-center gap-6 bg-[#050505] text-white px-6 py-4 border-2 border-[#bf00ff] shadow-neo-purple relative overflow-hidden animate-slide-in">
        
        <!-- Sección de Rango con estilo Display -->
        <div class="flex flex-col items-center pr-6 border-r-2 border-[#bf00ff]/30">
            <span class="text-[9px] text-[#bf00ff] uppercase font-black tracking-[0.2em] font-display">Rank</span>
            <span class="text-4xl italic font-black text-white font-display">#{{ stats.rank }}</span>
        </div>

        <div class="flex flex-col justify-center gap-2">
            <div class="flex items-center gap-3">
                <!-- Logo BellzCup -->
                <img src="/BellzCup.png" alt="BellzCup" class="h-5">
                <span class="text-xl italic font-black leading-none tracking-tighter uppercase font-display">
                    {{ playerName }}
                </span>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-[#111] border border-white/10 px-3 py-1 skew-x-[-10deg]">
                    <span class="text-[10px] font-bold text-gray-500 uppercase btn-content">PTS:</span>
                    <span class="text-lg font-black text-[#bf00ff] font-display btn-content">{{ stats.total_points }}</span>
                </div>
                <div class="flex items-center gap-2 bg-[#111] border border-white/10 px-3 py-1 skew-x-[-10deg]">
                    <span class="text-[10px] font-bold text-gray-500 uppercase btn-content">KILLS:</span>
                    <span class="text-lg font-black text-red-500 font-display btn-content">{{ stats.total_kills }}</span>
                </div>
            </div>
        </div>

        <!-- Marca de agua Rankit lateral -->
        <div class="absolute right-0 top-0 bottom-0 w-1 bg-[#bf00ff]"></div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@700&family=Archivo:wght@600;800&display=swap');

html, body { background-color: transparent !important; overflow: hidden; }
.font-display { font-family: 'Chakra Petch', sans-serif; }
.shadow-neo-purple { box-shadow: 6px 6px 0px 0px #000, 8px 8px 0px 0px #bf00ff; }
.btn-content { transform: skewX(10deg); display: inline-block; }
.animate-slide-in { animation: slideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) both; }
@keyframes slideIn { from { transform: translateX(-30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>