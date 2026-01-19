<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ tournaments: Array });

// --- ESTADOS DE UI ---
const showModal = ref(false);
const uploadProgress = ref(0);
const loadingLeaderboard = ref(false);
const processingSlot = ref<Record<number, boolean>>({}); // Estado de carga individual por botón

// --- ESTADOS DE DATOS ---
const selectedTournament = ref<any>(null);
const leaderboard = ref<any[]>([]);
const selectedMatchId = ref<number | null>(null);
const slotInputs = ref<Record<number, { game_mode: number, custom_code: string }>>({}); // Inputs independientes

// --- FILTROS Y ORDEN ---
const leaderboardType = ref('players');
const filterMode = ref('all');
const sortBy = ref('points');
const expandedRowIndex = ref<number | null>(null);

// Formularios Globales
const formTournament = useForm({ name: '', expected_matches: 5 });

// FIX: TypeScript type casting added here for target_match_id and replay
const formReplay = useForm({ 
    replay: null as File | null, 
    mode: 2, 
    target_match_id: null as number | null 
});

// Inicializar inputs independientes para cada torneo
const initSlotInputs = () => {
    if (props.tournaments) {
        props.tournaments.forEach((t: any) => {
            if (!slotInputs.value[t.id]) {
                slotInputs.value[t.id] = { game_mode: 2, custom_code: '' };
            }
        });
    }
};

onMounted(initSlotInputs);
watch(() => props.tournaments, initSlotInputs, { deep: true });

// --- MÉTODOS DE GESTIÓN ---

const createTournament = () => {
    formTournament.post(route('jangel.store'), { onSuccess: () => formTournament.reset() });
};

// Crear Slot (AHORA INDEPENDIENTE Y SIN BUGS)
const createSlot = (tnId: number) => {
    const input = slotInputs.value[tnId];
    
    if (!input || !input.custom_code) {
        alert("¡Escribe un código para la partida!");
        return;
    }

    processingSlot.value[tnId] = true;

    router.post(route('jangel.match.schedule', tnId), input, {
        onSuccess: () => {
            slotInputs.value[tnId].custom_code = ''; // Limpiar solo este input
            processingSlot.value[tnId] = false;
        },
        onError: (err) => {
            processingSlot.value[tnId] = false;
            alert("Error al crear slot: " + JSON.stringify(err));
        },
        preserveScroll: true
    });
};

// --- MÉTODOS DE UPLOAD (MODAL) ---

const openUploadModal = (tnId: number, matchId: number | null = null) => {
    formReplay.reset();
    formReplay.target_match_id = matchId;
    selectedTournament.value = props.tournaments?.find((t:any) => t.id === tnId);
    showModal.value = true;
    uploadProgress.value = 0;
};

const submitReplay = () => {
    if (!selectedTournament.value) return;
    
    formReplay.post(route('jangel.match.process', selectedTournament.value.id), {
        onProgress: (progress) => {
            uploadProgress.value = progress?.percentage || 0;
        },
        onSuccess: () => {
            showModal.value = false;
            uploadProgress.value = 0;
            if (selectedMatchId.value === formReplay.target_match_id) {
                fetchLeaderboard(selectedTournament.value, selectedMatchId.value);
            }
        },
        onError: (err) => {
            showModal.value = false;
            alert("Error al subir: " + JSON.stringify(err));
        }
    });
};

const deleteMatch = (id: number) => {
    if(confirm("¿Seguro que quieres borrar esta partida y sus datos?")) {
        router.delete(route('jangel.match.delete', id), {
            onSuccess: () => {
                if (selectedMatchId.value === id) fetchLeaderboard(selectedTournament.value, null);
            },
            preserveScroll: true
        });
    }
};

// --- MÉTODOS DE DATOS Y VISUALIZACIÓN ---

const fetchLeaderboard = async (tn: any, matchId: number | null = null) => {
    loadingLeaderboard.value = true;
    selectedTournament.value = tn;
    selectedMatchId.value = matchId;
    expandedRowIndex.value = null;

    try {
        const res = await axios.get(route('jangel.api.leaderboard', { 
            tournamentId: tn.id, 
            match_id: matchId,
            type: leaderboardType.value,
            mode: filterMode.value,
            sort: sortBy.value 
        }));
        leaderboard.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingLeaderboard.value = false;
    }
};

const switchTab = (type: string) => {
    leaderboardType.value = type;
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value, selectedMatchId.value);
};

const switchMode = (mode: string) => {
    filterMode.value = mode;
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value, selectedMatchId.value);
};

const toggleSort = () => {
    sortBy.value = sortBy.value === 'points' ? 'kills' : 'points';
    if (selectedTournament.value) fetchLeaderboard(selectedTournament.value, selectedMatchId.value);
};

const toggleRow = (index: number) => {
    expandedRowIndex.value = expandedRowIndex.value === index ? null : index;
};

// Helpers
const formatDec = (num: any) => {
    const n = parseFloat(num);
    return isNaN(n) ? '0' : n.toFixed(1).replace(/\.0$/, '');
};

const copyWidgetUrl = (tn: any) => {
    const url = route('api.widget.stats', tn.id); 
    navigator.clipboard.writeText(url);
    alert("URL Widget JSON copiada: " + url);
};

const getPublicUrl = (id: number) => `${window.location.origin}/live/${id}`;
const getObsUrl = (id: number) => `${window.location.origin}/widget/obs/global/${id}`;
</script>

<template>
    <Head title="Admin Jangel" />
    <AuthenticatedLayout>
        <div class="min-h-screen p-6 text-gray-100 bg-black">
            
            <!-- CREAR TORNEO -->
            <div class="flex items-end gap-4 p-4 mb-8 bg-gray-900 border border-indigo-900 rounded">
                <div class="flex-1">
                    <label class="block mb-1 text-xs text-gray-400">Nombre Torneo</label>
                    <input v-model="formTournament.name" type="text" class="w-full text-sm text-white bg-gray-800 border-gray-700 rounded focus:ring-indigo-500">
                </div>
                <div class="w-32">
                    <label class="block mb-1 text-xs text-gray-400">Total Partidas</label>
                    <input v-model="formTournament.expected_matches" type="number" class="w-full text-sm text-center text-white bg-gray-800 border-gray-700 rounded focus:ring-indigo-500">
                </div>
                <button @click="createTournament" :disabled="formTournament.processing" class="px-6 py-2 font-bold text-white bg-indigo-600 rounded hover:bg-indigo-500 disabled:opacity-50">
                    {{ formTournament.processing ? '...' : 'Crear' }}
                </button>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- COLUMNA IZQUIERDA: LISTA TORNEOS -->
                <div class="space-y-6">
                    <div v-for="tn in (tournaments as any)" :key="tn.id" class="p-4 bg-gray-900 border border-gray-800 rounded">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-lg font-bold text-white">{{ tn.name }}</h3>
                                <p class="font-mono text-xs text-indigo-400">{{ tn.progress_text }}</p>
                            </div>
                            <div class="flex flex-col gap-1 text-right">
                                <button @click="copyWidgetUrl(tn)" class="text-[10px] text-gray-400 hover:text-white mb-1">🔗 Copiar API</button>
                                <div class="flex gap-1">
                                    <a :href="getPublicUrl(tn.id)" target="_blank" class="text-[10px] bg-gray-800 px-2 py-1 rounded hover:text-white border border-gray-700">🌍 Public</a>
                                    <a :href="getObsUrl(tn.id)" target="_blank" class="text-[10px] bg-gray-800 px-2 py-1 rounded hover:text-white border border-gray-700">📺 OBS</a>
                                </div>
                            </div>
                        </div>

                        <!-- PANEL CREAR SLOT (AHORA INDEPENDIENTE) -->
                        <div v-if="slotInputs[tn.id]" class="p-2 mb-3 border border-gray-800 rounded bg-gray-950/50">
                            <div class="flex gap-2 mb-2">
                                <select v-model="slotInputs[tn.id].game_mode" class="w-20 text-xs text-white bg-gray-800 border-gray-700 rounded focus:ring-0">
                                    <option :value="1">Solo</option><option :value="2">Duo</option>
                                    <option :value="3">Trio</option><option :value="4">Squad</option>
                                </select>
                                <input 
                                    v-model="slotInputs[tn.id].custom_code" 
                                    @keyup.enter="createSlot(tn.id)"
                                    placeholder="Código (Ej: A1)" 
                                    class="flex-1 text-xs text-white bg-gray-800 border-gray-700 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                >
                            </div>
                            <button 
                                @click="createSlot(tn.id)" 
                                :disabled="processingSlot[tn.id]"
                                class="w-full bg-indigo-900/40 text-indigo-200 text-xs py-1.5 rounded hover:bg-indigo-800 border border-indigo-900/50 font-bold transition-all disabled:opacity-50"
                            >
                                {{ processingSlot[tn.id] ? 'Creando Slot...' : '+ Agregar Slot' }}
                            </button>
                        </div>

                        <!-- LISTA DE PARTIDAS -->
                        <div class="pr-1 space-y-1 overflow-y-auto max-h-60">
                            <div v-for="match in tn.matches" :key="match.id" 
                                class="flex items-center justify-between p-2 transition-colors border rounded cursor-pointer group"
                                :class="{
                                    'bg-indigo-900/30 border-indigo-500': selectedMatchId === match.id,
                                    'bg-yellow-900/10 border-yellow-900/30': match.status === 'pending' && selectedMatchId !== match.id,
                                    'bg-gray-800/50 border-gray-700': match.status !== 'pending' && selectedMatchId !== match.id
                                }"
                                @click="match.status === 'processed' ? fetchLeaderboard(tn, match.id) : null"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-gray-300 uppercase">{{ match.game_mode }}</span>
                                        <span v-if="match.status === 'pending'" class="text-[9px] bg-yellow-600 text-black px-1 rounded font-bold animate-pulse">EN VIVO</span>
                                        <span v-else class="text-[9px] bg-green-600 text-white px-1 rounded font-bold">LISTO</span>
                                    </div>
                                    <div class="mt-1 font-mono text-xs text-gray-500">Code: <span class="font-bold text-white">{{ match.custom_code }}</span></div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button v-if="match.status === 'pending'" @click.stop="openUploadModal(tn.id, match.id)" class="text-[10px] bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-500 font-bold shadow-lg shadow-blue-900/20">Subir Replay</button>
                                    <button v-else @click.stop="openUploadModal(tn.id, match.id)" class="text-xs text-gray-500 transition-colors hover:text-white" title="Re-subir (Sobreescribir)">↺</button>
                                    <button @click.stop="deleteMatch(match.id)" class="text-gray-600 transition-colors hover:text-red-500">✕</button>
                                </div>
                            </div>
                        </div>
                        
                        <button @click="fetchLeaderboard(tn, null)" class="w-full py-2 mt-2 text-xs text-center text-indigo-300 transition-colors bg-gray-800 border border-gray-700 rounded hover:bg-gray-700">Ver Ranking Global</button>
                    </div>
                </div>

                <!-- COLUMNA DERECHA: TABLA Y FILTROS -->
                <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded p-6 min-h-[500px] flex flex-col">
                    
                    <!-- Header de Tabla -->
                    <div v-if="selectedTournament" class="flex flex-col gap-4 pb-4 mb-6 border-b border-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-white">
                                    {{ selectedMatchId ? 'Resultados de Partida' : selectedTournament.name }} 
                                </h2>
                                <p class="text-sm text-gray-400">{{ selectedMatchId ? 'Vista Individual' : 'Ranking Global Acumulado' }}</p>
                            </div>
                            <div v-if="selectedMatchId">
                                <button @click="fetchLeaderboard(selectedTournament, null)" class="px-3 py-1 text-xs font-bold text-white transition-colors bg-indigo-600 rounded hover:bg-indigo-500">
                                    ← Volver al Global
                                </button>
                            </div>
                        </div>

                        <!-- BARRA DE FILTROS -->
                        <div class="flex flex-wrap items-center justify-between gap-3 p-2 border border-gray-800 rounded-lg bg-gray-950/50">
                            <div class="flex gap-2">
                                <!-- Tabs Jugadores/Equipos -->
                                <div class="flex p-1 bg-gray-900 border border-gray-700 rounded">
                                    <button @click="switchTab('players')" :class="{'bg-gray-700 text-white': leaderboardType === 'players', 'text-gray-500': leaderboardType !== 'players'}" class="px-3 py-1 text-xs font-bold uppercase transition-all rounded">Jugadores</button>
                                    <button @click="switchTab('teams')" :class="{'bg-gray-700 text-white': leaderboardType === 'teams', 'text-gray-500': leaderboardType !== 'teams'}" class="px-3 py-1 text-xs font-bold uppercase transition-all rounded">Equipos</button>
                                </div>
                                <!-- Botón Ordenar -->
                                <button @click="toggleSort" class="px-3 py-1 text-xs font-bold text-indigo-300 transition-colors border rounded border-indigo-900/50 hover:bg-indigo-900/20">
                                    Ordenar: {{ sortBy === 'points' ? 'Puntos 🏆' : 'Kills ⚔️' }}
                                </button>
                            </div>

                            <!-- Filtros de Modo (Solo visible en Global) -->
                            <div v-if="!selectedMatchId" class="flex gap-1 overflow-x-auto">
                                <button 
                                    v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" 
                                    :key="m" @click="switchMode(m)"
                                    :class="{'text-indigo-400 border-indigo-500': filterMode === m, 'text-gray-500 border-transparent': filterMode !== m}"
                                    class="px-2 py-1 text-xs font-bold uppercase transition-colors border-b-2 hover:text-gray-300"
                                >
                                    {{ m === 'all' ? 'Todas' : m }}
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div v-if="loadingLeaderboard" class="flex items-center justify-center flex-1 text-gray-500">
                        <div class="w-8 h-8 border-b-2 border-indigo-500 rounded-full animate-spin"></div>
                    </div>

                    <!-- TABLA COMPLETA (CON EXPANSION) -->
                    <div v-else-if="selectedTournament" class="overflow-x-auto">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead class="sticky top-0 text-xs text-gray-500 uppercase bg-gray-800">
                                <tr>
                                    <th class="p-3">Pos</th>
                                    <th class="p-3">{{ leaderboardType === 'teams' ? 'Equipo' : 'Jugador' }}</th>
                                    <th class="p-3 text-center">Partidas</th>
                                    <th class="p-3 text-center cursor-pointer hover:text-white" @click="sortBy = 'kills'; fetchLeaderboard(selectedTournament, selectedMatchId)" :class="{'text-white underline': sortBy==='kills'}">Kills</th>
                                    <th class="p-3 text-center cursor-pointer hover:text-white" @click="sortBy = 'points'; fetchLeaderboard(selectedTournament, selectedMatchId)" :class="{'text-white underline': sortBy==='points'}">Puntos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <template v-for="(item, idx) in leaderboard" :key="idx">
                                    <!-- FILA PRINCIPAL -->
                                    <tr @click="toggleRow(idx)" class="transition-colors border-b cursor-pointer hover:bg-gray-800/50 border-gray-800/50">
                                        <td class="w-12 p-3 font-mono font-bold text-gray-500">
                                            {{ idx < 3 ? ['🥇','🥈','🥉'][idx] : `#${idx + 1}` }}
                                        </td>
                                        <td class="p-3 font-bold text-white">
                                            <div v-if="leaderboardType === 'teams'" class="flex flex-wrap gap-1">
                                                <span v-for="(m, i) in item.member_names" :key="i" class="px-2 py-0.5 text-[10px] text-indigo-200 rounded bg-indigo-900/40">{{ m }}</span>
                                            </div>
                                            <div v-else>{{ item.player_name }}</div>
                                            <!-- Indicador pequeño -->
                                            <div class="text-[9px] text-gray-600 mt-0.5 font-normal">
                                                {{ expandedRowIndex === idx ? '▲ Ocultar' : '▼ Detalles' }}
                                            </div>
                                        </td>
                                        <td class="p-3 text-center text-gray-400">{{ item.games_played }}</td>
                                        <td class="p-3 font-mono font-bold text-center text-red-400">{{ item.total_kills }}</td>
                                        <td class="p-3 font-mono text-lg font-bold text-center text-green-400">{{ item.total_points }}</td>
                                    </tr>

                                    <!-- FILA DE DETALLES (EXPANDIBLE) -->
                                    <tr v-if="expandedRowIndex === idx" class="bg-gray-800/20 animate-fadeIn">
                                        <td colspan="5" class="p-3">
                                            <div class="grid grid-cols-2 gap-3 text-xs md:grid-cols-4">
                                                <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                    <span class="block mb-1 text-gray-500">Puntos Promedio</span>
                                                    <span class="text-base font-bold text-green-400">{{ formatDec(item.avg_points) }}</span>
                                                </div>
                                                <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                    <span class="block mb-1 text-gray-500">Kills Promedio</span>
                                                    <span class="text-base font-bold text-red-400">{{ formatDec(item.avg_kills) }}</span>
                                                </div>
                                                <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                    <span class="block mb-1 text-gray-500">Posición Promedio</span>
                                                    <span class="text-base font-bold text-yellow-400">#{{ formatDec(item.avg_placement) }}</span>
                                                </div>
                                                <div class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                    <span class="block mb-1 text-gray-500">Mejor Partida</span>
                                                    <span class="text-base font-bold text-white">#{{ item.best_placement }}</span>
                                                </div>
                                                
                                                <!-- Desglose puntos -->
                                                <div v-if="item.avg_kill_points" class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                    <span class="block text-gray-500">Pts por Kill (Avg)</span>
                                                    <span class="font-bold text-indigo-300">{{ formatDec(item.avg_kill_points) }}</span>
                                                </div>
                                                <div v-if="item.avg_placement_points" class="p-2 bg-gray-900 border border-gray-700 rounded">
                                                    <span class="block text-gray-500">Pts por Top (Avg)</span>
                                                    <span class="font-bold text-indigo-300">{{ formatDec(item.avg_placement_points) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div v-if="leaderboard.length === 0" class="py-10 text-center text-gray-500 border-t border-gray-800">
                            No hay datos para mostrar con los filtros actuales.
                        </div>
                    </div>
                    
                    <div v-else class="flex items-center justify-center flex-1 text-gray-500">
                        Selecciona un torneo o partida para ver datos.
                    </div>
                </div>
            </div>

            <!-- MODAL DE UPLOAD (PROGRESS BAR) -->
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
                <div class="p-6 bg-gray-900 border border-gray-700 shadow-2xl rounded-xl w-96">
                    <h3 class="mb-4 text-lg font-bold text-white">Subir Replay</h3>
                    
                    <div v-if="formReplay.target_match_id" class="p-2 mb-4 text-xs text-yellow-200 border rounded bg-yellow-900/20 border-yellow-700/50">
                        ⚠ Vas a sobreescribir los datos del slot seleccionado.
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block mb-1 text-xs text-gray-400">Archivo .replay</label>
                            <input type="file" @input="formReplay.replay = ($event.target as any).files[0]" class="block w-full text-xs text-gray-400 bg-gray-800 border border-gray-700 rounded cursor-pointer focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-l file:border-0 file:text-xs file:font-semibold file:bg-indigo-900 file:text-indigo-200 hover:file:bg-indigo-800">
                        </div>
                        <div v-if="!formReplay.target_match_id">
                            <label class="block mb-1 text-xs text-gray-400">Modo (Si es partida nueva)</label>
                            <select v-model="formReplay.mode" class="w-full text-xs text-white bg-gray-800 border-gray-700 rounded">
                                <option :value="1">Solo</option><option :value="2">Duo</option>
                                <option :value="3">Trio</option><option :value="4">Squad</option>
                            </select>
                        </div>
                        
                        <!-- BARRA DE PROGRESO -->
                        <div v-if="formReplay.processing" class="space-y-1">
                            <div class="flex justify-between text-xs text-gray-400">
                                <span>Subiendo y Procesando...</span>
                                <span>{{ uploadProgress }}%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-700 rounded-full">
                                <div class="h-2 transition-all duration-300 bg-indigo-500 rounded-full" :style="{ width: uploadProgress + '%' }"></div>
                            </div>
                            <p class="text-[10px] text-gray-500 pt-1">No cierres esta ventana. Puede tardar unos minutos.</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-gray-800">
                            <button @click="showModal = false" :disabled="formReplay.processing" class="px-3 py-1 text-sm text-gray-400 transition-colors hover:text-white disabled:opacity-50">Cancelar</button>
                            <button @click="submitReplay" :disabled="formReplay.processing" class="px-4 py-1 text-sm font-bold text-white transition-colors bg-indigo-600 rounded hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ formReplay.processing ? 'Procesando...' : (formReplay.target_match_id ? 'Sobreescribir' : 'Procesar') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>