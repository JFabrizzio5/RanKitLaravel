<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    tournaments: Array
});

const form = useForm({ name: '' });

// Formulario para subir replays con número de partida
const replayForm = useForm({
    replay: null as any,
    match_number: 1
});

const leaderboard = ref<any[]>([]);
const loadingLeaderboard = ref(false);
const selectedTournament = ref<any>(null);

const createTournament = () => {
    form.post(route('jangel.store'), { onSuccess: () => form.reset() });
};

const uploadReplay = (id: number) => {
    if (!replayForm.replay) {
        alert("Selecciona un archivo .replay");
        return;
    }
    // Usamos el ID del torneo para la ruta
    replayForm.post(route('tournaments.process-replay', id), {
        onSuccess: () => {
            alert("¡Partida " + replayForm.match_number + " procesada!");
            replayForm.reset('replay');
            if(selectedTournament.value?.id === id) fetchLeaderboard(selectedTournament.value);
        }
    });
};

const fetchLeaderboard = async (tn: any) => {
    loadingLeaderboard.value = true;
    selectedTournament.value = tn;
    try {
        // Ahora la API responde correctamente gracias al controlador
        const response = await axios.get(`/api/leaderboard/${tn.table_name}`);
        leaderboard.value = response.data;
    } catch (error) {
        alert("Error al cargar la tabla.");
    } finally {
        loadingLeaderboard.value = false;
    }
};
</script>

<template>
    <Head title="Jangel Dashboard" />
    <AuthenticatedLayout>
        <div class="py-12 bg-black min-h-screen text-gray-100 px-4">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <!-- Crear Torneo -->
                <div class="p-6 bg-gray-900 rounded-xl border border-indigo-500/30">
                    <h3 class="text-indigo-400 font-bold mb-4">🏆 Crear Nuevo Torneo</h3>
                    <div class="flex gap-4">
                        <input v-model="form.name" type="text" placeholder="Nombre del Torneo" class="flex-1 bg-gray-800 border-gray-700 rounded-lg"/>
                        <button @click="createTournament" class="bg-indigo-600 px-6 py-2 rounded-lg font-bold">Crear</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Lista de Torneos -->
                    <div class="space-y-4">
                        <div v-for="tn in (tournaments as any)" :key="tn.id" class="p-4 bg-gray-900 rounded-lg border border-gray-800">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-bold">{{ tn.name }}</h4>
                                <button @click="fetchLeaderboard(tn)" class="text-indigo-400 text-sm">Ver Tabla</button>
                            </div>
                            
                            <div class="space-y-2 border-t border-gray-800 pt-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">Número de Juego:</span>
                                    <input type="number" v-model="replayForm.match_number" class="w-16 bg-gray-800 border-gray-700 rounded text-sm text-center"/>
                                </div>
                                <input type="file" @input="replayForm.replay = ($event.target as any).files[0]" class="text-xs w-full"/>
                                <button @click="uploadReplay(tn.id)" class="w-full py-2 bg-white text-black font-bold rounded text-xs">Subir Replay</button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla General -->
                    <div class="lg:col-span-2 bg-gray-900 p-6 rounded-xl border border-gray-800 min-h-[400px]">
                        <h3 v-if="selectedTournament" class="text-xl font-bold mb-6">Tabla: {{ selectedTournament.name }}</h3>
                        <p v-else class="text-center text-gray-600 py-20">Selecciona un torneo</p>

                        <table v-if="selectedTournament" class="w-full text-left">
                            <thead class="text-xs text-gray-400 uppercase bg-gray-800">
                                <tr>
                                    <th class="p-3">Jugador</th>
                                    <th class="p-3 text-center">Partidas</th>
                                    <th class="p-3 text-center">Kills</th>
                                    <th class="p-3 text-center">Puntos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <tr v-for="player in leaderboard" :key="player.player_name">
                                    <td class="p-3">{{ player.player_name }}</td>
                                    <td class="p-3 text-center">{{ player.games_played }}</td>
                                    <td class="p-3 text-center text-red-400">{{ player.total_kills }}</td>
                                    <td class="p-3 text-center text-green-400 font-bold">{{ player.total_points }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>