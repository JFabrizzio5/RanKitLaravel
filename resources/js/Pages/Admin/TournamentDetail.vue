<script setup lang="ts">
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3'; // Corregido el paquete

const props = defineProps<{
    tournament: any;
    rankingsSolo: any[];
    rankingsDuo: any[];
    recentMatches: any[];
}>();

const activeMode = ref('solo');
const currentRankings = computed(() => activeMode.value === 'solo' ? props.rankingsSolo : props.rankingsDuo);

const uploadForm = useForm({
    replay: null as File | null,
});

const handleUpload = () => {
    // Apunta a la ruta de procesamiento. En el controlador he puesto ambos métodos para que no falle.
    uploadForm.post(route('tournaments.process-replay', props.tournament.id), {
        onSuccess: () => uploadForm.reset(),
    });
};
</script>

<template>
    <Head :title="`Torneo: ${tournament.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ tournament.name }} - Panel de Estadísticas
            </h2>
        </template>

        <div class="py-12 px-4 max-w-7xl mx-auto space-y-8">
            
            <!-- Zona de Carga -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border-t-4 border-indigo-500">
                <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-white">Subir Resultados de Partida (.replay)</h3>
                <form @submit.prevent="handleUpload" class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1 w-full">
                        <input 
                            type="file" 
                            @input="uploadForm.replay = ($event.target as HTMLInputElement).files![0]"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            required
                        />
                    </div>
                    <button 
                        type="submit" 
                        :disabled="uploadForm.processing"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition disabled:opacity-50"
                    >
                        {{ uploadForm.processing ? 'Analizando Replay...' : 'Cargar Partida' }}
                    </button>
                </form>
            </div>

            <!-- Leaderboards con Pestañas -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700">
                <div class="flex border-b border-gray-700">
                    <button 
                        @click="activeMode = 'solo'"
                        :class="['flex-1 py-4 font-bold transition uppercase tracking-widest text-xs', activeMode === 'solo' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:bg-gray-700']"
                    >
                        Tabla Solo
                    </button>
                    <button 
                        @click="activeMode = 'duo'"
                        :class="['flex-1 py-4 font-bold transition uppercase tracking-widest text-xs', activeMode === 'duo' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:bg-gray-700']"
                    >
                        Tabla Dúo
                    </button>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-700">
                                    <th class="px-4 py-3">Rango</th>
                                    <th class="px-4 py-3">Jugador</th>
                                    <th class="px-4 py-3 text-center">Partidas</th>
                                    <th class="px-4 py-3 text-center">Kills</th>
                                    <th class="px-4 py-3 text-center">Daño Realizado</th>
                                    <th class="px-4 py-3 text-center">Puntos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr v-for="(row, index) in currentRankings" :key="row.player_name" class="hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-4 font-mono text-indigo-400">#{{ index + 1 }}</td>
                                    <td class="px-4 py-4 font-bold text-gray-200">{{ row.player_name }}</td>
                                    <td class="px-4 py-4 text-center">{{ row.total_matches }}</td>
                                    <td class="px-4 py-4 text-center text-green-400 font-bold">{{ row.total_kills }}</td>
                                    <td class="px-4 py-4 text-center text-gray-400">{{ Math.round(row.total_damage).toLocaleString() }}</td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="bg-indigo-500/20 text-indigo-400 px-3 py-1 rounded-full font-black text-xs">
                                            {{ row.total_points }} PTS
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="currentRankings.length === 0">
                                    <td colspan="6" class="py-12 text-center text-gray-500 italic">No hay datos para el modo {{ activeMode }} en este torneo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Partidas Recientes -->
            <div class="bg-gray-900 rounded-xl p-6 shadow-inner border border-gray-800">
                <h3 class="text-sm font-bold text-gray-500 uppercase mb-4 tracking-tighter">Últimas 10 Partidas Procesadas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="match in recentMatches" :key="match.match_id" class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-black px-2 py-0.5 rounded bg-indigo-500 text-white uppercase">{{ match.game_mode }}</span>
                            <span class="text-[10px] text-gray-500">{{ new Date(match.created_at).toLocaleString() }}</span>
                        </div>
                        <p class="text-xs text-gray-400 font-mono truncate">ID: {{ match.match_id }}</p>
                        <p class="text-sm font-bold text-gray-200 mt-1">Mapa: {{ match.map_name }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>