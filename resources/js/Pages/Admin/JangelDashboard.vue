<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

// Recibimos los torneos con sus partidas (matches)
const props = defineProps({
    tournaments: Array
});

// Formulario para crear torneo
const form = useForm({ name: '' });

// Formulario para subir replay
const replayForm = useForm({
    replay: null as any,
    mode: 2, // Default a Duos
});

const leaderboard = ref<any[]>([]);
const loadingLeaderboard = ref(false);
const selectedTournament = ref<any>(null);

// Estado de Filtros
const leaderboardType = ref('players'); // 'players' | 'teams'
const filterMode = ref('all'); // 'all', 'solo', 'duo', 'trio', 'squad'
const selectedMatchId = ref<number | null>(null); // Si es null, vemos global. Si tiene ID, vemos partida específica.
const selectedMatchInfo = ref<any>(null); // Para mostrar info del match seleccionado en el header

const createTournament = () => {
    form.post(route('jangel.store'), { onSuccess: () => form.reset() });
};

const uploadReplay = (id: number) => {
    if (!replayForm.replay) {
        alert("Por favor selecciona un archivo .replay");
        return;
    }
    
    replayForm.post(route('tournaments.process-replay', id), {
        onSuccess: () => {
            alert("¡Partida procesada con éxito!");
            replayForm.reset('replay');
            // Refrescar tabla si estamos en el torneo correcto
            if(selectedTournament.value?.id === id) {
                // Forzamos volver a global para ver el cambio
                selectedMatchId.value = null;
                fetchLeaderboard(selectedTournament.value);
            }
            router.reload({ only: ['tournaments'] });
        },
        onError: (errors) => {
            alert("Error al subir: " + JSON.stringify(errors));
        }
    });
};

const deleteMatch = (matchId: number) => {
    if(!confirm("¿Estás seguro de que quieres eliminar esta partida? Se borrarán los puntos asociados.")) return;
    
    router.delete(route('jangel.match.delete', matchId), {
        onSuccess: () => {
            alert("Partida eliminada.");
            if(selectedTournament.value) fetchLeaderboard(selectedTournament.value);
            router.reload({ only: ['tournaments'] });
        }
    });
};

// Carga la tabla según los filtros actuales
const fetchLeaderboard = async (tn: any, specificMatch: any = null) => {
    loadingLeaderboard.value = true;
    selectedTournament.value = tn;
    
    // Si se pasa un match específico, actualizamos el estado
    if (specificMatch) {
        selectedMatchId.value = specificMatch.id;
        selectedMatchInfo.value = specificMatch;
    } else if (specificMatch === false) {
        // Reset explícito a global
        selectedMatchId.value = null;
        selectedMatchInfo.value = null;
    }

    leaderboard.value = [];
    
    try {
        const response = await axios.get(route('api.leaderboard', {
            tournamentId: tn.id,
            type: leaderboardType.value,
            mode: filterMode.value,
            match_id: selectedMatchId.value // Enviamos el ID si existe, o null
        }));
        leaderboard.value = response.data;
    } catch (error) {
        console.error("Error cargando leaderboard:", error);
    } finally {
        loadingLeaderboard.value = false;
    }
};

// Cambiar pestaña (Jugadores/Equipos)
const switchTab = (type: string) => {
    leaderboardType.value = type;
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value);
};

// Cambiar filtro de modo (Solo/Duo/etc)
const switchMode = (mode: string) => {
    filterMode.value = mode;
    // Si cambiamos de modo, generalmente queremos ver el global de ese modo
    // Pero si estamos viendo una partida específica, el modo no importa tanto (la partida ya tiene su modo)
    // Aún así, recargamos.
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value);
};

// Volver a la vista global desde una partida
const resetToGlobal = () => {
    selectedMatchId.value = null;
    selectedMatchInfo.value = null;
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value);
}
</script>

<template>
    <Head title="Jangel Dashboard" />
    <AuthenticatedLayout>
        <div class="min-h-screen px-4 py-12 text-gray-100 bg-black">
            <div class="mx-auto space-y-8 max-w-7xl">
                
                <!-- Sección 1: Crear Nuevo Torneo -->
                <div class="p-6 bg-gray-900 border shadow-lg rounded-xl border-indigo-500/30">
                    <h3 class="flex items-center gap-2 mb-4 text-xl font-bold text-indigo-400">
                        🏆 <span class="text-white">Crear Nuevo Torneo</span>
                    </h3>
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <input 
                            v-model="form.name" 
                            type="text" 
                            placeholder="Ej: Copa Verano 2026" 
                            class="flex-1 text-white bg-gray-800 border-gray-700 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button 
                            @click="createTournament" 
                            class="px-8 py-2 font-bold text-white transition-colors bg-indigo-600 rounded-lg hover:bg-indigo-500"
                        >
                            Crear Torneo
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Sección 2: Lista de Torneos y Subida de Partidas -->
                    <div class="space-y-6">
                        <h4 class="text-sm font-semibold tracking-wider text-gray-400 uppercase">Torneos Activos</h4>
                        
                        <div v-for="tn in (tournaments as any)" :key="tn.id" class="p-5 transition-colors bg-gray-900 border border-gray-800 rounded-lg hover:border-gray-700">
                            <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-700">
                                <h4 class="text-lg font-bold text-white">{{ tn.name }}</h4>
                                <button 
                                    @click="fetchLeaderboard(tn, false)" 
                                    class="text-sm font-medium text-indigo-400 hover:text-indigo-300"
                                >
                                    Ver Global →
                                </button>
                            </div>
                            
                            <!-- Controles de Subida -->
                            <div class="p-3 mb-4 space-y-3 rounded-md bg-gray-950/50">
                                <label class="text-xs font-bold text-gray-500 uppercase">Nueva Partida</label>
                                <div class="flex gap-2">
                                    <select 
                                        v-model="replayForm.mode" 
                                        class="w-1/3 text-sm text-white bg-gray-800 border-gray-700 rounded focus:ring-indigo-500"
                                    >
                                        <option :value="1">Solo</option>
                                        <option :value="2">Duos</option>
                                        <option :value="3">Trios</option>
                                        <option :value="4">Squads</option>
                                    </select>
                                    <input 
                                        type="file" 
                                        @input="replayForm.replay = ($event.target as any).files[0]" 
                                        class="w-full text-xs text-gray-400 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600"
                                    />
                                </div>
                                <button 
                                    @click="uploadReplay(tn.id)" 
                                    class="w-full py-2 text-sm font-bold text-white transition-all bg-indigo-600 rounded shadow-md hover:bg-indigo-500 active:scale-95"
                                >
                                    Procesar Replay
                                </button>
                            </div>

                            <!-- Lista de Partidas Procesadas -->
                            <div v-if="tn.matches && tn.matches.length > 0">
                                <p class="mb-2 text-xs font-bold text-gray-500 uppercase">Historial (Clic para ver)</p>
                                <ul class="pr-1 space-y-1 overflow-y-auto max-h-48 scrollbar-thin scrollbar-thumb-gray-700">
                                    <li 
                                        v-for="(match, index) in tn.matches" 
                                        :key="match.id" 
                                        @click="fetchLeaderboard(tn, match)"
                                        class="flex items-center justify-between p-2 text-xs transition-colors border border-transparent rounded cursor-pointer group hover:bg-indigo-900/30 hover:border-indigo-500/30"
                                        :class="{'bg-indigo-900/50 border-indigo-500': selectedMatchId === match.id, 'bg-gray-800': selectedMatchId !== match.id}"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-300">
                                                <span class="text-indigo-400">#{{ tn.matches.length - index }}</span> {{ match.game_mode }}
                                            </span>
                                            <span class="text-gray-600 text-[10px]">{{ new Date(match.created_at).toLocaleString() }}</span>
                                        </div>
                                        <button 
                                            @click.stop="deleteMatch(match.id)" 
                                            class="p-1 text-gray-600 transition-colors rounded hover:text-red-400 hover:bg-red-900/20"
                                            title="Eliminar partida"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="py-2 text-xs italic text-center text-gray-600 rounded bg-gray-900/50">
                                Sin partidas.
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Tabla General (Leaderboard) -->
                    <div class="lg:col-span-2 bg-gray-900 p-6 rounded-xl border border-gray-800 min-h-[500px] flex flex-col">
                        
                        <!-- Header de la Tabla -->
                        <div v-if="selectedTournament" class="flex flex-col gap-4 pb-4 mb-6 border-b border-gray-800">
                            
                            <!-- Título y Estado -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="mb-1 text-2xl font-bold text-white">
                                        {{ selectedMatchId ? `Partida ${selectedMatchInfo?.game_mode}` : selectedTournament.name }}
                                    </h3>
                                    <p class="text-sm text-gray-400">
                                        {{ selectedMatchId ? 'Resultados Individuales' : 'Ranking Acumulado Global' }}
                                    </p>
                                </div>
                                <div v-if="selectedMatchId">
                                    <button @click="resetToGlobal" class="px-3 py-1 text-xs font-bold text-white bg-indigo-600 rounded hover:bg-indigo-500">
                                        ← Volver al Global
                                    </button>
                                </div>
                            </div>
                            
                            <!-- BARRA DE HERRAMIENTAS DE FILTROS -->
                            <div class="flex flex-wrap items-center justify-between gap-3 p-2 rounded-lg bg-gray-950">
                                
                                <!-- 1. Tipo de Vista (Jugadores / Equipos) -->
                                <div class="flex p-1 bg-gray-900 rounded">
                                    <button @click="switchTab('players')" :class="{'bg-gray-700 text-white': leaderboardType === 'players', 'text-gray-500 hover:text-gray-300': leaderboardType !== 'players'}" class="px-3 py-1 text-xs font-bold uppercase transition-colors rounded">Jugadores</button>
                                    <button @click="switchTab('teams')" :class="{'bg-gray-700 text-white': leaderboardType === 'teams', 'text-gray-500 hover:text-gray-300': leaderboardType !== 'teams'}" class="px-3 py-1 text-xs font-bold uppercase transition-colors rounded">Equipos</button>
                                </div>

                                <!-- 2. Filtro de Modalidad (Solo visible en Global) -->
                                <div v-if="!selectedMatchId" class="flex gap-1 overflow-x-auto">
                                    <button 
                                        v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" 
                                        :key="m"
                                        @click="switchMode(m)"
                                        :class="{'text-indigo-400 border-indigo-500': filterMode === m, 'text-gray-500 border-transparent hover:text-gray-300': filterMode !== m}"
                                        class="px-2 py-1 text-xs font-bold uppercase transition-colors border-b-2"
                                    >
                                        {{ m === 'all' ? 'Todas' : m }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado Vacío o Cargando -->
                        <div v-else class="flex flex-col items-center justify-center flex-1 text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4 opacity-50">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <p>Selecciona un torneo para ver su tabla.</p>
                        </div>

                        <div v-if="loadingLeaderboard" class="flex items-center justify-center flex-1">
                            <div class="w-8 h-8 border-b-2 border-indigo-500 rounded-full animate-spin"></div>
                        </div>

                        <!-- TABLA DE RESULTADOS -->
                        <div v-if="selectedTournament && !loadingLeaderboard" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky top-0 text-xs text-gray-400 uppercase bg-gray-800">
                                    <tr>
                                        <th class="p-3 rounded-tl-lg">Pos</th>
                                        
                                        <!-- Cabecera Dinámica -->
                                        <th class="p-3">
                                            {{ leaderboardType === 'teams' ? 'Integrantes del Equipo' : 'Jugador' }}
                                        </th>
                                        
                                        <th class="p-3 text-center">Partidas</th>
                                        <th class="p-3 text-center">Kills</th>
                                        <th class="p-3 text-center rounded-tr-lg">Puntos</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-gray-800">
                                    <tr v-for="(item, idx) in leaderboard" :key="idx" class="transition-colors hover:bg-gray-800/50">
                                        
                                        <!-- Posición -->
                                        <td class="w-12 p-3 font-mono font-bold text-gray-500">
                                            <span v-if="idx < 3" class="text-lg">
                                                {{ idx === 0 ? '🥇' : (idx === 1 ? '🥈' : '🥉') }}
                                            </span>
                                            <span v-else>#{{ idx + 1 }}</span>
                                        </td>
                                        
                                        <!-- Nombre o Nombres de Equipo -->
                                        <td class="p-3 font-bold text-white">
                                            <div v-if="leaderboardType === 'teams'" class="flex flex-wrap gap-2">
                                                <span 
                                                    v-for="(member, mIdx) in item.member_names" 
                                                    :key="mIdx"
                                                    class="px-2 py-1 text-xs font-medium text-indigo-200 border rounded bg-indigo-900/50 border-indigo-500/30"
                                                >
                                                    {{ member }}
                                                </span>
                                            </div>
                                            <div v-else>
                                                {{ item.player_name }}
                                            </div>
                                        </td>

                                        <td class="p-3 text-center text-gray-400">{{ item.games_played }}</td>
                                        <td class="p-3 font-mono text-center text-red-400">{{ item.total_kills }}</td>
                                        <td class="p-3 font-mono text-lg font-bold text-center text-green-400">{{ item.total_points }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <div v-if="leaderboard.length === 0" class="py-10 text-center text-gray-500">
                                No hay datos para mostrar con estos filtros.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>