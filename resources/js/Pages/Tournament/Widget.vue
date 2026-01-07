<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

// Este componente no usa el AuthenticatedLayout porque queremos que sea transparente/limpio para OBS
defineProps<{
    leaderboard: Array<{ rank: number; team: string; score: number; wins: number }>;
}>();

// Opcional: Añadir clase al body para permitir fondo transparente en OBS
onMounted(() => {
    document.body.style.backgroundColor = 'transparent';
});
</script>

<template>
    <Head title="OBS Widget - Scoreboard" />

    <!-- Contenedor principal diseñado para ser capturado. 
         Usa bg-transparent si configuras OBS para captura con transparencia, 
         o un chroma key (ej. verde) si prefieres. Aquí uso un fondo semitransparente oscuro para legibilidad. -->
    <div class="min-h-screen w-full flex items-start justify-center p-10 bg-transparent">
        
        <div class="w-full max-w-2xl bg-gray-900/90 backdrop-blur-md rounded-xl border-2 border-yellow-500/50 shadow-[0_0_20px_rgba(234,179,8,0.3)] overflow-hidden">
            
            <!-- Cabecera del Widget -->
            <div class="bg-gradient-to-r from-yellow-600 to-yellow-800 p-4 flex justify-between items-center">
                <h1 class="text-2xl font-black text-white italic tracking-tighter uppercase drop-shadow-md">
                    TORNEO <span class="text-yellow-300">FICTICIO</span>
                </h1>
                <div class="text-xs font-bold text-yellow-100 bg-black/30 px-2 py-1 rounded">
                    EN VIVO
                </div>
            </div>

            <!-- Tabla de Puntuación -->
            <div class="p-0">
                <table class="w-full">
                    <thead>
                        <tr class="bg-black/50 text-yellow-500 text-xs uppercase tracking-widest">
                            <th class="py-3 px-4 text-left">#</th>
                            <th class="py-3 px-4 text-left">Equipo</th>
                            <th class="py-3 px-4 text-center">Wins</th>
                            <th class="py-3 px-4 text-right">Puntos</th>
                        </tr>
                    </thead>
                    <tbody class="text-white">
                        <tr v-for="(team, index) in leaderboard" :key="team.team" 
                            class="border-b border-gray-700/50 last:border-0 hover:bg-white/5 transition-colors duration-300"
                            :class="{'bg-yellow-500/10': index === 0}"> <!-- Resaltar al líder -->
                            
                            <td class="py-3 px-4 font-bold text-lg text-gray-400">
                                <span v-if="index === 0" class="text-yellow-400 text-2xl">👑</span>
                                <span v-else>{{ team.rank }}</span>
                            </td>
                            
                            <td class="py-3 px-4 font-bold tracking-wide text-lg">
                                {{ team.team }}
                            </td>
                            
                            <td class="py-3 px-4 text-center font-mono text-gray-300">
                                {{ team.wins }}
                            </td>
                            
                            <td class="py-3 px-4 text-right font-black text-xl text-yellow-400 font-mono">
                                {{ team.score }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pie del Widget -->
            <div class="bg-black/80 p-2 text-center">
                <p class="text-[10px] text-gray-500 uppercase tracking-[0.2em]">
                    Resultados Oficiales
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Asegurar que el fondo de la página sea transparente para OBS */
:global(body) {
    background-color: transparent !important;
}
:global(html) {
    background-color: transparent !important;
}
</style>