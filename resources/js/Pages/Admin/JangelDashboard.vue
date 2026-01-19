<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    tournaments: Array
});

const form = useForm({ name: '' });

// Formulario para Crear Partida Programada (Código)
const scheduledMatchForm = useForm({
    game_mode: 2,
    custom_code: ''
});

// Formulario para subir replay
const replayForm = useForm({
    replay: null as any,
    mode: 2,
    target_match_id: null as number | null
});

const leaderboard = ref<any[]>([]);
const loadingLeaderboard = ref(false);
const selectedTournament = ref<any>(null);

// Estados de Filtros y Orden
const leaderboardType = ref('players'); 
const filterMode = ref('all'); 
const selectedMatchId = ref<number | null>(null);
const selectedMatchInfo = ref<any>(null);
const sortBy = ref('points'); // 'points' | 'kills'

// Estado para filas expandidas (Detalles AVG)
const expandedRowIndex = ref<number | null>(null);

const createTournament = () => {
    form.post(route('jangel.store'), { onSuccess: () => form.reset() });
};

// Crear partida solo con código
const createScheduledMatch = (tournamentId: number) => {
    if(!scheduledMatchForm.custom_code) {
        alert("Ingresa un código"); return;
    }
    scheduledMatchForm.post(route('jangel.match.schedule', tournamentId), {
        onSuccess: () => {
            alert("Partida creada con código. La lista se actualizará.");
            scheduledMatchForm.reset();
            // Recargar conservando el scroll para ver el nuevo slot
            router.reload({ only: ['tournaments'], preserveScroll: true });
        }
    });
};

const uploadReplay = (id: number, targetMatchId: number | null = null, existingMode: string | null = null) => {
    if (!replayForm.replay) {
        alert("Por favor selecciona un archivo .replay");
        return;
    }
    
    if (targetMatchId) {
        replayForm.target_match_id = targetMatchId;
    } else {
        replayForm.target_match_id = null;
    }
    
    replayForm.post(route('tournaments.process-replay', id), {
        onSuccess: () => {
            alert("¡Procesado con éxito!");
            replayForm.reset('replay', 'target_match_id');
            if(selectedTournament.value?.id === id) {
                selectedMatchId.value = null; 
                fetchLeaderboard(selectedTournament.value);
            }
            router.reload({ only: ['tournaments'], preserveScroll: true });
        },
        onError: (errors) => {
            alert("Error: " + JSON.stringify(errors));
        }
    });
};

const deleteMatch = (matchId: number) => {
    if(!confirm("¿Eliminar partida? Se borrarán datos.")) return;
    router.delete(route('jangel.match.delete', matchId), {
        onSuccess: () => {
            if(selectedTournament.value) fetchLeaderboard(selectedTournament.value);
            router.reload({ only: ['tournaments'], preserveScroll: true });
        }
    });
};

const fetchLeaderboard = async (tn: any, specificMatch: any = null) => {
    loadingLeaderboard.value = true;
    selectedTournament.value = tn;
    expandedRowIndex.value = null; // Resetear expansión al cambiar tabla
    
    if (specificMatch) {
        selectedMatchId.value = specificMatch.id;
        selectedMatchInfo.value = specificMatch;
    } else if (specificMatch === false) {
        selectedMatchId.value = null;
        selectedMatchInfo.value = null;
    }

    leaderboard.value = [];
    
    try {
        const response = await axios.get(route('api.leaderboard', {
            tournamentId: tn.id,
            type: leaderboardType.value,
            mode: filterMode.value,
            match_id: selectedMatchId.value,
            sort: sortBy.value 
        }));
        leaderboard.value = response.data;
    } catch (error) {
        console.error("Error cargando leaderboard:", error);
    } finally {
        loadingLeaderboard.value = false;
    }
};

const switchTab = (type: string) => {
    leaderboardType.value = type;
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value);
};

const switchMode = (mode: string) => {
    filterMode.value = mode;
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value);
};

const toggleSort = () => {
    sortBy.value = sortBy.value === 'points' ? 'kills' : 'points';
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value);
}

// Función para expandir/colapsar detalles
const toggleRow = (index: number) => {
    if (expandedRowIndex.value === index) {
        expandedRowIndex.value = null;
    } else {
        expandedRowIndex.value = index;
    }
}

const formatDec = (num: any) => {
    const n = parseFloat(num);
    return isNaN(n) ? '0' : n.toFixed(1).replace(/\.0$/, '');
}

const copyWidgetUrl = (tn: any) => {
    const url = route('api.widget.stats', tn.id); 
    navigator.clipboard.writeText(url);
    alert("URL del Widget API copiada: " + url);
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
                    <!-- Sección 2: Lista de Torneos y Gestión -->
                    <div class="space-y-6">
                        <h4 class="text-sm font-semibold tracking-wider text-gray-400 uppercase">Torneos Activos</h4>
                        
                        <div v-for="tn in (tournaments as any)" :key="tn.id" class="p-5 transition-colors bg-gray-900 border border-gray-800 rounded-lg hover:border-gray-700">
                            <!-- Header Torneo -->
                            <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-700">
                                <h4 class="text-lg font-bold text-white">{{ tn.name }}</h4>
                                <div class="flex gap-2">
                                    <button @click="copyWidgetUrl(tn)" title="Copiar Widget JSON" class="text-gray-400 hover:text-white">🔗</button>
                                    <button 
                                        @click="fetchLeaderboard(tn, false)" 
                                        class="text-sm font-medium text-indigo-400 hover:text-indigo-300"
                                    >
                                        Ver Global →
                                    </button>
                                </div>
                            </div>
                            
                            <!-- 1. CREAR PARTIDA CON CÓDIGO -->
                            <div class="p-3 mb-4 space-y-2 border border-gray-800 rounded-md bg-gray-950/80">
                                <label class="text-[10px] font-bold text-indigo-400 uppercase">Programar Partida (Código)</label>
                                <div class="flex gap-2">
                                    <select v-model="scheduledMatchForm.game_mode" class="w-1/3 text-xs bg-gray-800 border-gray-700 rounded">
                                        <option :value="1">Solo</option>
                                        <option :value="2">Duo</option>
                                        <option :value="3">Trio</option>
                                        <option :value="4">Squad</option>
                                    </select>
                                    <input v-model="scheduledMatchForm.custom_code" type="text" placeholder="Código (ej: FINAL1)" class="w-full text-xs bg-gray-800 border-gray-700 rounded" />
                                </div>
                                <button @click="createScheduledMatch(tn.id)" class="w-full py-1 text-xs font-bold text-indigo-100 bg-indigo-700 rounded hover:bg-indigo-600">
                                    + Crear Slot de Partida
                                </button>
                            </div>

                            <!-- 2. SUBIR REPLAY (General) -->
                            <div class="p-3 mb-4 space-y-3 rounded-md bg-gray-950/50">
                                <label class="text-[10px] font-bold text-gray-500 uppercase">Subir Replay (Nueva Partida)</label>
                                <div class="flex gap-2">
                                    <select v-model="replayForm.mode" class="w-1/3 text-xs bg-gray-800 border-gray-700 rounded">
                                        <option :value="1">Solo</option>
                                        <option :value="2">Duo</option>
                                        <option :value="3">Trio</option>
                                        <option :value="4">Squad</option>
                                    </select>
                                    <input type="file" @input="replayForm.replay = ($event.target as any).files[0]" class="w-full text-xs text-gray-400" />
                                </div>
                                <button @click="uploadReplay(tn.id)" class="w-full py-1 text-xs font-bold text-white bg-green-700 rounded hover:bg-green-600">
                                    Procesar Replay Nueva
                                </button>
                            </div>

                            <!-- LISTA DE PARTIDAS (Historial) -->
                            <div v-if="tn.matches && tn.matches.length > 0">
                                <p class="mb-2 text-xs font-bold text-gray-500 uppercase">Partidas</p>
                                <ul class="pr-1 space-y-1 overflow-y-auto max-h-64 scrollbar-thin scrollbar-thumb-gray-700">
                                    <li 
                                        v-for="(match, index) in tn.matches" 
                                        :key="match.id" 
                                        class="flex flex-col p-2 text-xs transition-all border rounded"
                                        :class="{
                                            'bg-indigo-900/30 border-indigo-500': selectedMatchId === match.id,
                                            'bg-gray-800 border-transparent': selectedMatchId !== match.id,
                                            'opacity-90': match.status === 'pending'
                                        }"
                                    >
                                        <div class="flex items-center justify-between cursor-pointer" @click="match.status === 'processed' ? fetchLeaderboard(tn, match) : null">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-gray-300">
                                                        {{ match.game_mode }}
                                                    </span>
                                                    <!-- Badge de Estado -->
                                                    <span v-if="match.status === 'pending'" class="px-1 text-[9px] font-bold text-yellow-900 bg-yellow-500 rounded">PENDIENTE</span>
                                                    <span v-else class="px-1 text-[9px] font-bold text-green-900 bg-green-500 rounded">LISTO</span>
                                                </div>
                                                <span v-if="match.custom_code" class="text-indigo-300 font-mono font-bold mt-0.5">Code: {{ match.custom_code }}</span>
                                                <span class="text-gray-600 text-[10px]">{{ new Date(match.created_at).toLocaleString() }}</span>
                                            </div>
                                            
                                            <button @click.stop="deleteMatch(match.id)" class="text-gray-600 hover:text-red-400">✕</button>
                                        </div>

                                        <!-- Subir Replay a esta partida pendiente -->
                                        <div v-if="match.status === 'pending'" class="flex gap-2 pt-2 mt-2 border-t border-gray-700">
                                            <input type="file" @input="replayForm.replay = ($event.target as any).files[0]" class="text-[9px] text-gray-400 w-full" />
                                            <button 
                                                @click="uploadReplay(tn.id, match.id)" 
                                                class="px-2 py-0.5 text-[9px] font-bold bg-blue-600 rounded text-white hover:bg-blue-500 whitespace-nowrap"
                                            >
                                                Subir
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Tabla General -->
                    <div class="lg:col-span-2 bg-gray-900 p-6 rounded-xl border border-gray-800 min-h-[500px] flex flex-col">
                        
                        <div v-if="selectedTournament" class="flex flex-col gap-4 pb-4 mb-6 border-b border-gray-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="mb-1 text-2xl font-bold text-white">
                                        {{ selectedMatchId ? `Partida ${selectedMatchInfo?.game_mode}` : selectedTournament.name }}
                                    </h3>
                                    <p class="text-sm text-gray-400">
                                        {{ selectedMatchId ? 'Resultados' : 'Ranking Global' }}
                                    </p>
                                </div>
                                <div v-if="selectedMatchId">
                                    <button @click="fetchLeaderboard(selectedTournament, false)" class="px-3 py-1 text-xs font-bold text-white bg-indigo-600 rounded hover:bg-indigo-500">
                                        ← Volver al Global
                                    </button>
                                </div>
                            </div>
                            
                            <!-- BARRA DE FILTROS -->
                            <div class="flex flex-wrap items-center justify-between gap-3 p-2 rounded-lg bg-gray-950">
                                <div class="flex gap-2">
                                    <!-- Jugadores/Equipos -->
                                    <div class="flex p-1 bg-gray-900 rounded">
                                        <button @click="switchTab('players')" :class="{'bg-gray-700 text-white': leaderboardType === 'players', 'text-gray-500': leaderboardType !== 'players'}" class="px-3 py-1 text-xs font-bold uppercase rounded">Jugadores</button>
                                        <button @click="switchTab('teams')" :class="{'bg-gray-700 text-white': leaderboardType === 'teams', 'text-gray-500': leaderboardType !== 'teams'}" class="px-3 py-1 text-xs font-bold uppercase rounded">Equipos</button>
                                    </div>
                                    <!-- Ordenar por Kills/Puntos -->
                                    <button @click="toggleSort" class="px-3 py-1 text-xs font-bold text-indigo-300 border border-indigo-900 rounded hover:bg-indigo-900/50">
                                        Ordenar: {{ sortBy === 'points' ? 'Puntos 🏆' : 'Kills ⚔️' }}
                                    </button>
                                </div>

                                <div v-if="!selectedMatchId" class="flex gap-1 overflow-x-auto">
                                    <button 
                                        v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" 
                                        :key="m" @click="switchMode(m)"
                                        :class="{'text-indigo-400 border-indigo-500': filterMode === m, 'text-gray-500 border-transparent': filterMode !== m}"
                                        class="px-2 py-1 text-xs font-bold uppercase border-b-2"
                                    >
                                        {{ m === 'all' ? 'Todas' : m }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tabla -->
                        <div v-if="selectedTournament && !loadingLeaderboard" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky top-0 text-xs text-gray-400 uppercase bg-gray-800">
                                    <tr>
                                        <th class="p-3">Pos</th>
                                        <th class="p-3">{{ leaderboardType === 'teams' ? 'Equipo' : 'Jugador' }}</th>
                                        <th class="p-3 text-center">Partidas</th>
                                        <th class="p-3 text-center cursor-pointer" @click="sortBy = 'kills'; fetchLeaderboard(selectedTournament)" :class="{'text-white underline': sortBy==='kills'}">Kills</th>
                                        <th class="p-3 text-center cursor-pointer" @click="sortBy = 'points'; fetchLeaderboard(selectedTournament)" :class="{'text-white underline': sortBy==='points'}">Puntos</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-gray-800">
                                    <!-- Iterar con template para poder usar filas expandibles -->
                                    <template v-for="(item, idx) in leaderboard" :key="idx">
                                        <!-- Fila Principal (Clickable) -->
                                        <tr @click="toggleRow(idx)" class="transition-colors border-b cursor-pointer hover:bg-gray-800/50 border-gray-800/50">
                                            <td class="w-12 p-3 font-mono font-bold text-gray-500">
                                                {{ idx < 3 ? ['🥇','🥈','🥉'][idx] : `#${idx + 1}` }}
                                            </td>
                                            <td class="p-3 font-bold text-white">
                                                <div v-if="leaderboardType === 'teams'" class="flex flex-wrap gap-2">
                                                    <span v-for="(m, i) in item.member_names" :key="i" class="px-2 py-1 text-xs text-indigo-200 rounded bg-indigo-900/50">{{ m }}</span>
                                                </div>
                                                <div v-else>
                                                    {{ item.player_name }}
                                                </div>
                                                <!-- Indicador visual de expansión -->
                                                <div class="text-[10px] text-gray-500 mt-1 font-normal">
                                                    {{ expandedRowIndex === idx ? '▲ Ocultar Detalles' : '▼ Ver Promedios y Detalles' }}
                                                </div>
                                            </td>
                                            <td class="p-3 text-center text-gray-400">{{ item.games_played }}</td>
                                            <td class="p-3 font-mono font-bold text-center text-red-400">{{ item.total_kills }}</td>
                                            <td class="p-3 font-mono text-lg font-bold text-center text-green-400">{{ item.total_points }}</td>
                                        </tr>

                                        <!-- Fila de Detalles Expandidos -->
                                        <tr v-if="expandedRowIndex === idx" class="bg-gray-800/40 animate-fadeIn">
                                            <td colspan="5" class="p-4">
                                                <div class="grid grid-cols-2 gap-4 text-xs md:grid-cols-4">
                                                    <!-- AVG Puntos -->
                                                    <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Puntos Promedio</span>
                                                        <span class="text-lg font-bold text-green-400">{{ formatDec(item.avg_points) }}</span>
                                                    </div>
                                                    <!-- AVG Kills -->
                                                    <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Kills Promedio</span>
                                                        <span class="text-lg font-bold text-red-400">{{ formatDec(item.avg_kills) }}</span>
                                                    </div>
                                                    <!-- AVG Placement -->
                                                    <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Posición Promedio</span>
                                                        <span class="text-lg font-bold text-yellow-400">#{{ formatDec(item.avg_placement) }}</span>
                                                    </div>
                                                    <!-- Best Placement -->
                                                    <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Mejor Partida</span>
                                                        <span class="text-lg font-bold text-white">#{{ item.best_placement }}</span>
                                                    </div>
                                                    
                                                    <!-- Desglose de Puntos (Solo si no estamos viendo una partida específica, ya que global es suma) -->
                                                    <div v-if="item.avg_kill_points" class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Avg. Puntos de Kill</span>
                                                        <span class="font-bold text-indigo-300">{{ formatDec(item.avg_kill_points) }}</span>
                                                    </div>
                                                    <div v-if="item.avg_placement_points" class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Avg. Puntos de Top</span>
                                                        <span class="font-bold text-indigo-300">{{ formatDec(item.avg_placement_points) }}</span>
                                                    </div>
                                                    
                                                    <!-- Stats de Daño/Knocks si existen -->
                                                    <div v-if="item.avg_knocks" class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                        <span class="block mb-1 text-gray-400">Knocks Promedio</span>
                                                        <span class="font-bold text-orange-300">{{ formatDec(item.avg_knocks) }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <div v-if="leaderboard.length === 0" class="py-10 text-center text-gray-500">Sin datos.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>