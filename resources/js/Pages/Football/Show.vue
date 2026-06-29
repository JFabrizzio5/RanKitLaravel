<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    tournament: Object,
    teams: Array,
    players: Array,
    matches: Array,
});

// Modals
const showAddTeam = ref(false);
const showAddPlayer = ref(false);
const showResult = ref(false);

const selectedTeamId = ref(null);
const selectedMatch = ref(null);

const formTeam = useForm({
    name: '',
    logo: '',
});

const formPlayer = useForm({
    name: '',
    number: '',
    position: 'MID',
});

const formResult = useForm({
    match_id: null,
    score1: 0,
    score2: 0,
    penalties_winner_id: null,
    stats: {}, // { playerId: { goals: 0, assists: 0, yellow_cards: 0, red_cards: 0 } }
});

const submitTeam = () => {
    formTeam.post(route('football.team.add', props.tournament.id), {
        onSuccess: () => {
            showAddTeam.value = false;
            formTeam.reset();
        },
    });
};

const deleteTeam = (id) => {
    if(confirm('¿Eliminar equipo y todos sus jugadores?')) {
        router.delete(route('football.team.remove', [props.tournament.id, id]));
    }
};

const openAddPlayer = (teamId) => {
    selectedTeamId.value = teamId;
    showAddPlayer.value = true;
};

const submitPlayer = () => {
    formPlayer.post(route('football.player.add', [props.tournament.id, selectedTeamId.value]), {
        onSuccess: () => {
            showAddPlayer.value = false;
            formPlayer.reset();
        },
    });
};

const deletePlayer = (id) => {
    if(confirm('¿Eliminar jugador?')) {
        router.delete(route('football.player.remove', [props.tournament.id, id]));
    }
};

const generate = () => {
    if(confirm(`¿Generar bracket de ${props.tournament.format}? (No podrás agregar más equipos)`)) {
        router.post(route('football.generate', props.tournament.id));
    }
};

const openResult = (m) => {
    selectedMatch.value = m;
    formResult.match_id = m.id;
    formResult.score1 = m.score1 || 0;
    formResult.score2 = m.score2 || 0;
    formResult.penalties_winner_id = null;
    formResult.stats = {};
    showResult.value = true;
};

const incStat = (playerId, stat) => {
    if(!formResult.stats[playerId]) {
        formResult.stats[playerId] = { goals:0, assists:0, yellow_cards:0, red_cards:0 };
    }
    formResult.stats[playerId][stat]++;
};

const decStat = (playerId, stat) => {
    if(formResult.stats[playerId] && formResult.stats[playerId][stat] > 0) {
        formResult.stats[playerId][stat]--;
    }
};

const getMatchPlayers = (teamId) => {
    return props.players.filter(p => p.football_team_id === teamId);
};

const submitResult = () => {
    formResult.post(route('football.result', props.tournament.id), {
        onSuccess: () => {
            showResult.value = false;
            formResult.reset();
        },
    });
};

const deleteTournament = () => {
    if(confirm('¿ELIMINAR TORNEO COMPLETO? Esta acción es irreversible.')) {
        router.delete(route('football.destroy', props.tournament.id));
    }
};
</script>

<template>
    <Head :title="tournament.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-2xl text-white leading-tight flex items-center gap-2">
                        <Link :href="route('football.index')" class="text-gray-500 hover:text-white mr-2">&larr;</Link>
                        <span class="text-emerald-500">⚽</span> {{ tournament.name }}
                    </h2>
                    <p class="text-gray-400 text-sm mt-1">
                        Formato: <strong class="text-white">{{ tournament.format === 'elimination' ? 'Eliminación Directa' : 'Liga' }}</strong> | 
                        Fase: <strong class="text-emerald-400 uppercase">{{ tournament.phase }}</strong>
                    </p>
                </div>
                <div class="flex gap-3">
                    <a :href="route('football.public.view', tournament.id)" target="_blank" class="bg-gray-800 border border-gray-700 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-bold transition-colors">
                        Vista Pública
                    </a>
                    <button v-if="tournament.phase === 'pending'" @click="generate" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-bold transition-colors shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                        Generar Fixture
                    </button>
                    <button @click="deleteTournament" class="bg-red-900/50 hover:bg-red-800 text-red-200 px-4 py-2 rounded-lg font-bold transition-colors">
                        Eliminar
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-6">
                
                <!-- COLUMNA EQUIPOS -->
                <div class="w-full lg:w-1/3 space-y-6">
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-500"></i> Equipos ({{ teams.length }})</h3>
                            <button v-if="tournament.phase === 'pending'" @click="showAddTeam = true" class="text-xs bg-gray-800 hover:bg-gray-700 text-white px-2 py-1 rounded border border-gray-700">+ Equipo</button>
                        </div>
                        
                        <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                            <div v-for="team in teams" :key="team.id" class="bg-gray-800/50 border border-gray-700 rounded-lg p-3">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="flex items-center gap-2">
                                        <img v-if="team.logo" :src="team.logo" class="w-6 h-6 rounded-full object-cover bg-gray-700">
                                        <div v-else class="w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold">{{ team.name.charAt(0) }}</div>
                                        <strong class="text-white">{{ team.name }}</strong>
                                    </div>
                                    <button v-if="tournament.phase === 'pending'" @click="deleteTeam(team.id)" class="text-red-400 hover:text-red-300 text-xs"><i class="fa-solid fa-trash"></i></button>
                                </div>
                                
                                <div class="text-xs text-gray-400 mb-2">
                                    Plantilla: {{ players.filter(p => p.football_team_id === team.id).length }} jug.
                                </div>
                                
                                <!-- Jugadores del equipo -->
                                <div class="space-y-1 mb-2">
                                    <div v-for="p in players.filter(p => p.football_team_id === team.id)" :key="p.id" class="flex justify-between items-center text-[10px] bg-gray-900 p-1.5 rounded">
                                        <span class="text-gray-300"><strong class="text-emerald-500">{{ p.number || '-' }}</strong> {{ p.name }} ({{ p.position }})</span>
                                        <button v-if="tournament.phase === 'pending'" @click="deletePlayer(p.id)" class="text-red-500 hover:text-red-400"><i class="fa-solid fa-times"></i></button>
                                    </div>
                                </div>
                                
                                <button v-if="tournament.phase === 'pending'" @click="openAddPlayer(team.id)" class="w-full py-1 text-xs text-emerald-400 border border-emerald-900/50 hover:bg-emerald-900/20 rounded mt-1 transition-colors">
                                    + Agregar Jugador
                                </button>
                            </div>
                            
                            <div v-if="teams.length === 0" class="text-center py-4 text-gray-500 text-sm">
                                Agrega equipos para comenzar.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA PRINCIPAL (PARTIDOS Y LIGA) -->
                <div class="w-full lg:w-2/3 space-y-6">
                    
                    <!-- TABLA LIGA -->
                    <div v-if="tournament.format === 'league' && tournament.phase !== 'pending'" class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-table-list text-emerald-500"></i> Tabla de Posiciones</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-300">
                                <thead class="text-xs text-gray-500 uppercase border-b border-gray-800">
                                    <tr>
                                        <th class="py-2">Pos</th>
                                        <th class="py-2">Equipo</th>
                                        <th class="py-2 text-center">PJ</th>
                                        <th class="py-2 text-center">G</th>
                                        <th class="py-2 text-center">E</th>
                                        <th class="py-2 text-center">P</th>
                                        <th class="py-2 text-center">GF</th>
                                        <th class="py-2 text-center">GC</th>
                                        <th class="py-2 text-center">DG</th>
                                        <th class="py-2 text-center text-emerald-400 font-bold">PTS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(team, index) in teams" :key="team.id" class="border-b border-gray-800/50 hover:bg-gray-800/30">
                                        <td class="py-2 font-bold" :class="index === 0 ? 'text-emerald-400' : ''">{{ index + 1 }}</td>
                                        <td class="py-2 flex items-center gap-2">
                                            <div v-if="!team.logo" class="w-5 h-5 rounded-full bg-gray-700 flex items-center justify-center text-[9px]">{{ team.name.charAt(0) }}</div>
                                            <img v-else :src="team.logo" class="w-5 h-5 rounded-full object-cover">
                                            <span class="font-bold text-white">{{ team.name }}</span>
                                        </td>
                                        <td class="py-2 text-center">{{ team.wins + team.draws + team.losses }}</td>
                                        <td class="py-2 text-center">{{ team.wins }}</td>
                                        <td class="py-2 text-center">{{ team.draws }}</td>
                                        <td class="py-2 text-center">{{ team.losses }}</td>
                                        <td class="py-2 text-center">{{ team.goals_for }}</td>
                                        <td class="py-2 text-center">{{ team.goals_against }}</td>
                                        <td class="py-2 text-center">{{ team.goals_for - team.goals_against }}</td>
                                        <td class="py-2 text-center font-bold text-emerald-400 bg-emerald-500/10">{{ team.points }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PARTIDOS -->
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-calendar-days text-emerald-500"></i> Partidos y Resultados</h3>
                        
                        <div v-if="matches.length === 0" class="text-center py-10 text-gray-500">
                            No hay partidos generados. Haz clic en "Generar Fixture" arriba.
                        </div>
                        
                        <div v-else class="space-y-4 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                            <div v-for="m in matches" :key="m.id" class="bg-gray-800 border border-gray-700 rounded-lg p-3 flex flex-col sm:flex-row items-center justify-between gap-4 relative overflow-hidden group">
                                <div class="text-[10px] uppercase font-bold text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded absolute top-2 left-2">
                                    {{ m.phase === 'league' ? 'Jornada' : 'Ronda' }} {{ m.round }}
                                </div>
                                
                                <div class="flex-1 flex justify-end items-center gap-2 pt-4 sm:pt-0">
                                    <span class="font-bold text-white text-right">{{ m.team1 ? m.team1.name : 'TBD' }}</span>
                                    <div v-if="m.team1 && !m.team1.logo" class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs text-white">{{ m.team1.name.charAt(0) }}</div>
                                    <img v-if="m.team1 && m.team1.logo" :src="m.team1.logo" class="w-8 h-8 rounded-full object-cover">
                                </div>
                                
                                <div class="px-4 py-2 bg-gray-900 rounded-lg border border-gray-700 text-center min-w-[100px]">
                                    <div v-if="m.status === 'done'" class="text-2xl font-bold font-mono text-white">
                                        {{ m.score1 }} - {{ m.score2 }}
                                    </div>
                                    <div v-else class="text-sm text-gray-500 font-bold">VS</div>
                                    <div v-if="m.status === 'done' && m.winner_id && m.score1 === m.score2" class="text-[9px] text-yellow-500 mt-1 uppercase">Penales</div>
                                </div>
                                
                                <div class="flex-1 flex justify-start items-center gap-2">
                                    <div v-if="m.team2 && !m.team2.logo" class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-xs text-white">{{ m.team2.name.charAt(0) }}</div>
                                    <img v-if="m.team2 && m.team2.logo" :src="m.team2.logo" class="w-8 h-8 rounded-full object-cover">
                                    <span class="font-bold text-white text-left">{{ m.team2 ? m.team2.name : (m.status === 'done' ? 'BYE' : 'TBD') }}</span>
                                </div>
                                
                                <button v-if="m.status === 'pending' && m.team1 && m.team2" @click="openResult(m)" class="sm:absolute right-4 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-3 py-1.5 rounded transition-colors">
                                    Registrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals for adding entities -->
        <div v-if="showAddTeam" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-gray-900 border border-gray-700 rounded-xl w-full max-w-sm p-6">
                <h3 class="text-lg font-bold text-white mb-4">Agregar Equipo</h3>
                <form @submit.prevent="submitTeam" class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nombre *</label>
                        <input v-model="formTeam.name" type="text" required class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">URL Escudo (Opcional)</label>
                        <input v-model="formTeam.logo" type="url" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white" placeholder="https://...">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAddTeam = false" class="px-3 py-1.5 text-sm text-gray-400">Cancelar</button>
                        <button type="submit" :disabled="formTeam.processing" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded text-sm font-bold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showAddPlayer" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-gray-900 border border-gray-700 rounded-xl w-full max-w-sm p-6">
                <h3 class="text-lg font-bold text-white mb-4">Agregar Jugador</h3>
                <form @submit.prevent="submitPlayer" class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Nombre *</label>
                        <input v-model="formPlayer.name" type="text" required class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white">
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-400 mb-1">Dorsal</label>
                            <input v-model="formPlayer.number" type="text" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white" placeholder="10">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-400 mb-1">Posición</label>
                            <select v-model="formPlayer.position" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white">
                                <option value="GK">Portero (GK)</option>
                                <option value="DEF">Defensa (DEF)</option>
                                <option value="MID">Medio (MID)</option>
                                <option value="FWD">Delantero (FWD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAddPlayer = false" class="px-3 py-1.5 text-sm text-gray-400">Cancelar</button>
                        <button type="submit" :disabled="formPlayer.processing" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded text-sm font-bold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Registrar Resultado -->
        <div v-if="showResult" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[100] flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-gray-900 border border-emerald-500/30 rounded-2xl w-full max-w-4xl p-6 shadow-2xl mt-10 mb-10">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="fa-solid fa-stopwatch text-emerald-500"></i> Reportar Partido</h3>
                    <button @click="showResult = false" class="text-gray-500 hover:text-white text-2xl">&times;</button>
                </div>
                
                <form @submit.prevent="submitResult" class="space-y-8">
                    
                    <!-- Marcador Principal -->
                    <div class="bg-gray-800/50 rounded-xl p-6 flex flex-col md:flex-row items-center justify-center gap-8 border border-gray-800">
                        <!-- Equipo 1 -->
                        <div class="text-center flex-1">
                            <div class="text-lg font-bold text-white mb-4">{{ selectedMatch.team1?.name }}</div>
                            <input v-model="formResult.score1" type="number" min="0" required class="w-24 text-center text-5xl bg-gray-900 border border-gray-700 rounded-xl py-4 text-white focus:border-emerald-500">
                        </div>
                        
                        <div class="text-gray-500 font-bold text-xl">VS</div>
                        
                        <!-- Equipo 2 -->
                        <div class="text-center flex-1">
                            <div class="text-lg font-bold text-white mb-4">{{ selectedMatch.team2?.name }}</div>
                            <input v-model="formResult.score2" type="number" min="0" required class="w-24 text-center text-5xl bg-gray-900 border border-gray-700 rounded-xl py-4 text-white focus:border-emerald-500">
                        </div>
                    </div>

                    <!-- Desempate Penalties -->
                    <div v-if="tournament.format === 'elimination' && formResult.score1 === formResult.score2" class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 text-center">
                        <p class="text-yellow-400 text-sm font-bold mb-3">En eliminación directa debe haber un ganador (Desempate por Penales)</p>
                        <div class="flex justify-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer bg-gray-800 px-4 py-2 rounded border border-gray-700 hover:border-yellow-500">
                                <input type="radio" v-model="formResult.penalties_winner_id" :value="selectedMatch.team1_id" required class="text-yellow-500 focus:ring-yellow-500">
                                <span class="text-white text-sm font-bold">Ganó {{ selectedMatch.team1?.name }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer bg-gray-800 px-4 py-2 rounded border border-gray-700 hover:border-yellow-500">
                                <input type="radio" v-model="formResult.penalties_winner_id" :value="selectedMatch.team2_id" required class="text-yellow-500 focus:ring-yellow-500">
                                <span class="text-white text-sm font-bold">Ganó {{ selectedMatch.team2?.name }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Estadísticas Detalladas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Stats Equipo 1 -->
                        <div class="bg-gray-800/30 border border-gray-800 rounded-xl p-4">
                            <h4 class="text-emerald-400 font-bold mb-3 border-b border-gray-800 pb-2">{{ selectedMatch.team1?.name }} - Estadísticas</h4>
                            <div class="space-y-3">
                                <div v-for="p in getMatchPlayers(selectedMatch.team1_id)" :key="p.id" class="bg-gray-900 border border-gray-800 rounded p-2 flex justify-between items-center">
                                    <div class="text-xs text-white w-1/3 truncate"><span class="text-emerald-500 font-bold mr-1">{{ p.number || '-' }}</span> {{ p.name }}</div>
                                    <div class="flex gap-2">
                                        <!-- Goles -->
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-gray-400">⚽</span>
                                            <button type="button" @click="decStat(p.id, 'goals')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">-</button>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.goals || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'goals')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                        <!-- Asistencias -->
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-gray-400">👟</span>
                                            <button type="button" @click="decStat(p.id, 'assists')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">-</button>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.assists || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'assists')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                        <!-- Tarjetas Amarillas -->
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-yellow-500">🟨</span>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.yellow_cards || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'yellow_cards')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                        <!-- Tarjetas Rojas -->
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-red-500">🟥</span>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.red_cards || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'red_cards')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="getMatchPlayers(selectedMatch.team1_id).length === 0" class="text-xs text-gray-500 italic">No hay jugadores registrados.</div>
                            </div>
                        </div>

                        <!-- Stats Equipo 2 -->
                        <div class="bg-gray-800/30 border border-gray-800 rounded-xl p-4">
                            <h4 class="text-emerald-400 font-bold mb-3 border-b border-gray-800 pb-2">{{ selectedMatch.team2?.name }} - Estadísticas</h4>
                            <div class="space-y-3">
                                <div v-for="p in getMatchPlayers(selectedMatch.team2_id)" :key="p.id" class="bg-gray-900 border border-gray-800 rounded p-2 flex justify-between items-center">
                                    <div class="text-xs text-white w-1/3 truncate"><span class="text-emerald-500 font-bold mr-1">{{ p.number || '-' }}</span> {{ p.name }}</div>
                                    <div class="flex gap-2">
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-gray-400">⚽</span>
                                            <button type="button" @click="decStat(p.id, 'goals')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">-</button>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.goals || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'goals')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-gray-400">👟</span>
                                            <button type="button" @click="decStat(p.id, 'assists')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">-</button>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.assists || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'assists')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-yellow-500">🟨</span>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.yellow_cards || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'yellow_cards')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                        <div class="flex items-center bg-gray-800 rounded overflow-hidden border border-gray-700">
                                            <span class="px-1.5 text-[10px] text-red-500">🟥</span>
                                            <span class="px-2 text-xs text-white font-mono">{{ formResult.stats[p.id]?.red_cards || 0 }}</span>
                                            <button type="button" @click="incStat(p.id, 'red_cards')" class="px-1.5 bg-gray-700 hover:bg-gray-600 text-white">+</button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="getMatchPlayers(selectedMatch.team2_id).length === 0" class="text-xs text-gray-500 italic">No hay jugadores registrados.</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                        <button type="button" @click="showResult = false" class="px-6 py-3 text-gray-400 hover:text-white font-bold">Cancelar</button>
                        <button type="submit" :disabled="formResult.processing" class="bg-emerald-600 hover:bg-emerald-500 text-white px-8 py-3 rounded-lg font-bold text-lg disabled:opacity-50">
                            Guardar Resultado
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #374151;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #10b981;
}
</style>
