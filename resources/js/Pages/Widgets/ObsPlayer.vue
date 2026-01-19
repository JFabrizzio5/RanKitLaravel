<script setup lang="ts">
import { onMounted, ref } from 'vue';
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
    } catch (e) {}
};

onMounted(() => { update(); setInterval(update, 30000); });
</script>

<template>
    <div class="inline-flex items-center gap-4 bg-gray-900/95 text-white px-6 py-3 rounded-full border-2 border-indigo-600 shadow-[0_0_20px_rgba(79,70,229,0.5)]">
        <div class="flex flex-col items-center pr-4 leading-none border-r border-gray-600">
            <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Rank</span>
            <span class="text-3xl italic font-black text-yellow-400">#{{ stats.rank }}</span>
        </div>
        <div class="flex flex-col items-start gap-1 leading-none">
            <span class="text-base font-bold tracking-wide text-white uppercase">{{ playerName }}</span>
            <div class="flex gap-3 font-mono text-xs text-indigo-300">
                <span><b class="text-white">{{ stats.total_points }}</b> PTS</span>
                <span><b class="text-white">{{ stats.total_kills }}</b> KILLS</span>
            </div>
        </div>
    </div>
</template>