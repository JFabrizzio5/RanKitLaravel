<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ tournamentId: Number, playerName: String });
const stats = ref<any>({ rank: '-', total_points: 0, total_kills: 0 });

const update = async () => {
    try {
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
    // Forzar transparencia
    document.body.style.backgroundColor = 'transparent';
    update(); 
    // Actualizar cada 60s para proteger hosting
    const interval = setInterval(update, 60000); 
    onUnmounted(() => {
        clearInterval(interval);
        document.body.style.backgroundColor = '';
    });
});
</script>

<template>
    <div class="inline-flex items-center gap-5 bg-white text-gray-800 px-6 py-3 rounded-full shadow-[0_4px_15px_rgba(0,0,0,0.2)] border-2 border-indigo-600">
        
        <div class="flex flex-col items-center pr-5 border-r border-gray-200">
            <span class="text-[9px] text-gray-400 uppercase font-black tracking-[0.2em]">Rank</span>
            <span class="text-3xl italic font-black text-indigo-600">#{{ stats.rank }}</span>
        </div>

        <div class="flex flex-col justify-center">
            <span class="mb-1 text-lg font-black leading-none tracking-tight text-gray-900 uppercase">{{ playerName }}</span>
            
            <div class="flex items-center gap-4 font-mono text-xs font-bold">
                <div class="flex items-center gap-1.5 bg-indigo-50 px-2 py-0.5 rounded text-indigo-700">
                    <span>PTS:</span>
                    <span class="text-sm font-black">{{ stats.total_points }}</span>
                </div>
                <div class="flex items-center gap-1.5 bg-red-50 px-2 py-0.5 rounded text-red-700">
                    <span>KILLS:</span>
                    <span class="text-sm font-black">{{ stats.total_kills }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
html, body {
    background-color: transparent !important;
}
</style>