<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

// --- TIPOS ---

interface PublicMatch {
    id: number;
    mode: string;
    code: string;
    status: string;
    is_active: boolean;
    created_at: string;
}

interface PublicRankingItem {
    player_name: string;
    member_names?: string[];
    games_played: number;
    total_kills: number;
    total_points: number;
    avg_points: number;
    avg_kills: number;
    avg_placement: number;
    best_placement: number;
}

interface TournamentInfo {
    id?: number;
    name?: string;
    progress?: string;
}

interface LiveDataResponse {
    tournament: TournamentInfo;
    matches: PublicMatch[];
    ranking: PublicRankingItem[];
}

// --- PROPS ---
const props = defineProps<{
  tournament?: TournamentInfo; // Data inicial opcional (SSG/Inertia load)
  sponsors?: any[];
  targetDate?: number;
}>();

// --- THEME ---
const isDark = ref(true);

function applyTheme(nextDark: boolean) {
  isDark.value = nextDark;
  const html = document.documentElement;
  if (nextDark) {
    html.classList.add('dark');
    localStorage.setItem('theme', 'dark');
  } else {
    html.classList.remove('dark');
    localStorage.setItem('theme', 'light');
  }
}
function toggleTheme() { applyTheme(!isDark.value); }

// --- LOGICA DE DATOS (Fuente D) ---
const matches = ref<PublicMatch[]>([]);
const ranking = ref<PublicRankingItem[]>([]);
const tournamentData = ref<TournamentInfo>(props.tournament || {});
const progressText = ref("Cargando...");
const isLoading = ref(true);
const viewerCount = ref(1240); // Mock inicial

// Tabs y Filtros
const activeTab = ref<'resultados' | 'partidas' | 'reglas'>('resultados');
const selectedMatchId = ref<number | null>(null);
const leaderboardType = ref<'players' | 'teams'>('players');
const filterMode = ref<string>('all'); // Nuevo: Filtro de modalidad
const sortBy = ref<'points' | 'kills'>('points');
const expandedRowIndex = ref<number | null>(null);

let pollInterval: number | undefined;

const loadData = async () => {
    try {
        const id = props.tournament?.id || tournamentData.value.id;
        if(!id) return;

        // Simular fluctuación de viewers
        viewerCount.value = Math.max(1000, viewerCount.value + Math.floor(Math.random() * 21) - 10);

        const url = `/api/live/${id}/data`;
        const params: any = {
            type: leaderboardType.value,
            mode: filterMode.value, // Usar filtro dinámico
            sort: sortBy.value
        };
        if (selectedMatchId.value) params.match_id = selectedMatchId.value;

        const res = await axios.get<LiveDataResponse>(url, { params });
        
        matches.value = res.data.matches;
        ranking.value = res.data.ranking;
        
        if(res.data.tournament) {
            tournamentData.value.name = res.data.tournament.name;
            progressText.value = res.data.tournament.progress || '';
        }
        
    } catch (e) { 
        console.error("Error polling data:", e); 
    } finally {
        isLoading.value = false;
    }
};

// UI Helpers
const switchTab = (tab: 'resultados' | 'partidas' | 'reglas') => (activeTab.value = tab);

const formatDec = (num: number | string) => {
    const n = typeof num === 'string' ? parseFloat(num) : num;
    return isNaN(n) ? '0' : n.toFixed(1).replace(/\.0$/, '');
};

const toggleMatchFilter = (matchId: number) => {
    selectedMatchId.value = selectedMatchId.value === matchId ? null : matchId;
    expandedRowIndex.value = null; 
    isLoading.value = true;
    loadData();
};

const copyObsLink = () => {
    const id = props.tournament?.id || tournamentData.value.id;
    if (!id) return;
    
    const baseUrl = `${window.location.origin}/widget/obs/global/${id}`;
    // Construir query string con TODOS los filtros actuales
    const query = `?type=${leaderboardType.value}&mode=${filterMode.value}&sort=${sortBy.value}&limit=10${selectedMatchId.value ? `&match_id=${selectedMatchId.value}` : ''}`;
    
    navigator.clipboard.writeText(baseUrl + query);
    
    // Feedback visual más detallado
    alert(`✅ Link OBS Copiado!\n\nConfiguración:\n• Modo: ${filterMode.value.toUpperCase()}\n• Vista: ${leaderboardType.value.toUpperCase()}\n• Orden: ${sortBy.value.toUpperCase()}\n• Match: ${selectedMatchId.value ? '#' + selectedMatchId.value : 'Global'}`);
};

const copyPublicLink = () => {
    navigator.clipboard.writeText(window.location.href);
    alert("✅ Enlace público copiado al portapapeles.");
}

onMounted(() => {
  // Init Theme
  const savedTheme = localStorage.getItem('theme');
  const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true;
  if (savedTheme === 'light') applyTheme(false);
  else applyTheme(true);

  // Init Phosphor Icons
  if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/@phosphor-icons/web';
    script.async = true;
    document.head.appendChild(script);
  }

  // Start Polling
  loadData();
  pollInterval = window.setInterval(loadData, 10000); // 10s poll (más rápido para sentirlo live)
});

onUnmounted(() => {
    if(pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <Head :title="`${tournamentData.name || 'Torneo'} - BellzCup Live`">
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
  </Head>

  <div class="overflow-x-hidden selection:bg-[var(--rankit-neon)] selection:text-white bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white font-sans transition-colors duration-300">
    
    <!-- Navbar -->
    <nav class="fixed w-full z-50 transition-colors duration-300 bg-white/90 border-b border-gray-200 dark:bg-[#050505]/95 dark:border-white/10 backdrop-blur-md h-20 flex items-center px-6 lg:px-12 justify-between">
      <Link href="/" class="flex items-center gap-3 cursor-pointer group">
        <svg class="w-10 h-10 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
        </svg>
        <span class="text-3xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">BellzCup</span>
      </Link>
      <div class="flex items-center gap-4">
        <button @click="toggleTheme" class="p-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
          <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
          <i v-else class="text-xl ph-fill ph-moon"></i>
        </button>
      </div>
    </nav>

    <!-- HERO SECTION -->
    <header class="relative min-h-[500px] h-auto flex items-end pt-24 overflow-hidden group pb-20 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px]">
      <div class="absolute inset-0 z-0 pointer-events-none">
        <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" class="w-full h-full object-cover opacity-20 dark:opacity-30 transform scale-105 group-hover:scale-110 transition duration-[30s] ease-linear grayscale mix-blend-multiply dark:mix-blend-overlay" />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-gray-50/80 to-transparent dark:from-[#050505] dark:via-[#050505]/80 dark:to-transparent"></div>
      </div>

      <div class="relative z-10 flex flex-col w-full gap-8 px-6 mx-auto max-w-7xl lg:px-8">
        <div class="flex flex-wrap items-center gap-3 animate-fade-in-up">
          <span class="bg-red-600/90 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-[0_0_20px_rgba(220,38,38,0.6)] animate-pulse flex items-center gap-2 btn-skew">
             <span class="flex items-center gap-2 btn-content"><span class="w-1.5 h-1.5 bg-white rounded-full"></span> EN VIVO</span>
          </span>
          <span class="bg-white/10 border border-black/10 dark:border-white/10 text-black dark:text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 cursor-default brutal-card">
            {{ progressText }}
          </span>
          
          <!-- Viewers Indicator -->
          <span class="flex items-center gap-2 text-[10px] font-bold text-gray-500 dark:text-gray-400 bg-white/50 dark:bg-black/50 px-2 py-1 rounded backdrop-blur">
              <i class="ph-fill ph-users text-[var(--rankit-neon)]"></i>
              {{ viewerCount.toLocaleString() }} viewers
          </span>
        </div>

        <div class="relative max-w-3xl delay-100 animate-fade-in-up">
            <h1 class="mb-4 text-5xl font-black leading-none tracking-tight text-black uppercase md:text-7xl font-display dark:text-white">
              {{ tournamentData.name || 'Torneo' }}
              <br/>
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-purple-600">
                LIVE STATS
              </span>
            </h1>
        </div>
      </div>
    </header>

    <!-- Navigation Tabs -->
    <div class="sticky top-20 z-40 bg-white/90 dark:bg-[#050505]/90 backdrop-blur-lg border-b border-gray-200 dark:border-white/10">
      <div class="flex gap-8 px-6 mx-auto overflow-x-auto max-w-7xl lg:px-8 no-scrollbar">
        <button
          v-for="tab in ['resultados', 'partidas', 'reglas']"
          :key="tab"
          @click="switchTab(tab as any)"
          class="flex items-center gap-2 py-5 text-xs font-bold tracking-widest uppercase transition duration-300 border-b-2 whitespace-nowrap group"
          :class="activeTab === tab ? 'border-neon text-black dark:text-white' : 'border-transparent text-gray-500 hover:text-black dark:hover:text-gray-300'"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 lg:px-8 py-10 min-h-[600px]">
      
      <!-- === RESULTADOS EN VIVO === -->
      <div v-if="activeTab === 'resultados'" class="grid grid-cols-1 gap-8 animate-fade-in lg:grid-cols-12">
        
        <!-- Sidebar: Info y Filtros -->
        <aside class="space-y-6 lg:col-span-4">
            
            <!-- TARJETA INFO & ID (Restaurada) -->
            <div class="brutal-card p-4 bg-white dark:bg-[#0a0a0a]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold tracking-widest text-gray-500 uppercase">Info Torneo</h3>
                    <button @click="copyPublicLink" class="text-[var(--rankit-neon)] hover:underline text-[10px] font-bold uppercase">Compartir</button>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-2 bg-gray-100 rounded dark:bg-white/5">
                        <span class="text-[10px] uppercase font-bold text-gray-500">ID Pública</span>
                        <span class="font-mono text-sm font-bold">{{ tournamentData.id || '---' }}</span>
                    </div>
                    <!-- BOTON OBS -->
                    <button @click="copyObsLink" class="w-full py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-bold uppercase btn-skew flex items-center justify-center gap-2 group">
                        <span class="flex items-center gap-2 btn-content">
                             <i class="text-lg ph-bold ph-broadcast"></i>
                             Copiar Tabla OBS
                        </span>
                    </button>
                    <p class="text-[9px] text-center text-gray-400">
                        *El link se copiará con los filtros (Modo, Tipo) que tengas seleccionados abajo.
                    </p>
                </div>
            </div>

            <!-- LISTA DE PARTIDAS -->
            <div>
                <h3 class="mb-4 text-xs font-bold tracking-widest text-gray-500 uppercase">Historial de Partidas</h3>
                
                <div 
                    @click="selectedMatchId = null; loadData()"
                    class="flex items-center justify-between p-4 mb-3 transition cursor-pointer brutal-card hover:bg-gray-50 dark:hover:bg-white/5"
                    :class="!selectedMatchId ? 'border-[var(--rankit-neon)] bg-[var(--rankit-neon)]/5' : ''"
                >
                    <span class="text-sm font-bold">Ranking Global Acumulado</span>
                    <i class="text-xl ph ph-globe"></i>
                </div>

                <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1 custom-scrollbar">
                    <div v-for="match in matches" :key="match.id" 
                        @click="toggleMatchFilter(match.id)"
                        class="relative p-3 overflow-hidden transition cursor-pointer brutal-card hover:bg-gray-50 dark:hover:bg-white/5 group"
                        :class="selectedMatchId === match.id ? 'border-[var(--rankit-neon)]' : ''"
                    >
                        <div class="relative z-10 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-bold text-gray-500 uppercase">{{ match.mode }}</div>
                                <div class="text-xl font-bold font-display">{{ match.code }}</div>
                            </div>
                            <div>
                                <span v-if="match.is_active" class="px-2 py-1 bg-red-500 text-white text-[9px] font-bold rounded animate-pulse">LIVE</span>
                                <span v-else class="text-[10px] text-gray-400 font-bold">FINAL</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main: Tabla Leaderboard -->
        <div class="lg:col-span-8">
            
            <!-- BARRA DE HERRAMIENTAS SUPERIOR -->
            <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-6 p-4 brutal-card bg-white dark:bg-[#0a0a0a]">
                
                <!-- Filtro Players/Teams -->
                <div class="flex p-1 bg-gray-200 rounded-lg dark:bg-white/5">
                    <button @click="leaderboardType='players'; loadData()" :class="leaderboardType==='players'?'bg-white dark:bg-gray-800 shadow text-black dark:text-white':'text-gray-500'" class="px-4 py-1 text-xs font-bold uppercase transition rounded">Jugadores</button>
                    <button @click="leaderboardType='teams'; loadData()" :class="leaderboardType==='teams'?'bg-white dark:bg-gray-800 shadow text-black dark:text-white':'text-gray-500'" class="px-4 py-1 text-xs font-bold uppercase transition rounded">Equipos</button>
                </div>

                <!-- Filtro MODALIDAD (Nuevo) -->
                <div class="flex max-w-full gap-1 p-1 overflow-x-auto bg-gray-200 rounded-lg dark:bg-white/5">
                     <button 
                        v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" 
                        :key="m"
                        @click="filterMode=m; loadData()"
                        :class="filterMode===m ? 'bg-[var(--rankit-neon)] text-white shadow' : 'text-gray-500 hover:text-black dark:hover:text-white'"
                        class="px-3 py-1 text-[10px] font-bold uppercase rounded transition whitespace-nowrap"
                     >
                        {{ m }}
                     </button>
                </div>
                
                <!-- Sort -->
                <button @click="sortBy= sortBy==='points'?'kills':'points'; loadData()" class="text-xs font-bold text-[var(--rankit-neon)] hover:underline uppercase flex items-center gap-1">
                    <i class="ph-bold" :class="sortBy==='points' ? 'ph-trophy' : 'ph-sword'"></i>
                    {{ sortBy }}
                </button>
            </div>

            <!-- TABLA -->
            <div class="brutal-card bg-white dark:bg-[#0a0a0a] min-h-[400px]">
                 <div v-if="isLoading" class="flex items-center justify-center h-64">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-10 h-10 border-4 border-[var(--rankit-neon)] border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-[10px] font-bold uppercase text-gray-400">Actualizando datos...</span>
                    </div>
                 </div>
                 <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-gray-500 bg-gray-100 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="p-4 font-bold uppercase text-[10px]">#</th>
                                <th class="p-4 font-bold uppercase text-[10px]">Participante</th>
                                <th class="p-4 font-bold uppercase text-[10px] text-center">Partidas</th>
                                <th class="p-4 font-bold uppercase text-[10px] text-right">Kills</th>
                                <th class="p-4 font-bold uppercase text-[10px] text-right">Pts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            <template v-for="(item, idx) in ranking" :key="idx">
                                <tr @click="expandedRowIndex = expandedRowIndex === idx ? null : idx" class="transition cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="p-4 text-lg font-bold text-gray-400 font-display">{{ idx + 1 }}</td>
                                    <td class="p-4 font-bold text-black dark:text-white">
                                        <div v-if="leaderboardType==='teams'" class="flex flex-wrap gap-1">
                                            <span v-for="m in item.member_names" :key="m" class="text-[9px] bg-gray-200 dark:bg-white/10 px-1 rounded">{{ m }}</span>
                                        </div>
                                        <div v-else>{{ item.player_name }}</div>
                                    </td>
                                    <td class="p-4 font-mono text-center text-gray-500">{{ item.games_played }}</td>
                                    <td class="p-4 font-mono text-right text-red-500">{{ item.total_kills }}</td>
                                    <td class="p-4 text-right font-mono text-xl font-bold text-[var(--rankit-neon)]">{{ formatDec(item.total_points) }}</td>
                                </tr>
                                <!-- Expansión -->
                                <tr v-if="expandedRowIndex === idx" class="bg-[var(--rankit-neon)]/5">
                                    <td colspan="5" class="p-4">
                                        <div class="grid grid-cols-2 gap-4 text-xs md:grid-cols-4">
                                            <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded">
                                                <span class="block text-gray-500 uppercase text-[9px]">Media Puntos</span>
                                                <span class="text-lg font-bold">{{ formatDec(item.avg_points) }}</span>
                                            </div>
                                            <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded">
                                                <span class="block text-gray-500 uppercase text-[9px]">Media Kills</span>
                                                <span class="text-lg font-bold text-red-500">{{ formatDec(item.avg_kills) }}</span>
                                            </div>
                                            <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded">
                                                <span class="block text-gray-500 uppercase text-[9px]">Media Top</span>
                                                <span class="text-lg font-bold text-yellow-500">#{{ formatDec(item.avg_placement) }}</span>
                                            </div>
                                            <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded">
                                                <span class="block text-gray-500 uppercase text-[9px]">Mejor Top</span>
                                                <span class="text-lg font-bold text-white">#{{ item.best_placement }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="ranking.length === 0">
                                <td colspan="5" class="py-12 text-center text-gray-500">No hay datos disponibles con los filtros actuales.</td>
                            </tr>
                        </tbody>
                    </table>
                 </div>
            </div>
        </div>
      </div>

      <!-- === REGLAS === -->
      <div v-if="activeTab === 'reglas'" class="animate-fade-in">
        <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
          <h3 class="mb-4 text-2xl font-bold text-black uppercase dark:text-white font-display">Reglas Generales</h3>
          <ul class="pl-5 space-y-2 text-sm text-gray-600 list-disc dark:text-gray-400">
              <li>El uso de hacks o scripts resultará en descalificación inmediata.</li>
              <li>Los equipos deben estar presentes en el lobby 10 minutos antes.</li>
              <li>Las repeticiones deben guardarse por 24 horas.</li>
              <li>El administrador tiene la decisión final en caso de disputas.</li>
          </ul>
        </div>
      </div>
    </main>
  </div>
</template>

<style>
/* Estilos Fuente C conservados */
:root { --rankit-neon: #bf00ff; }
.font-display { font-family: "Chakra Petch", sans-serif; }
.font-sans { font-family: "Archivo", sans-serif; }
.text-neon { color: var(--rankit-neon); }
.bg-neon { background-color: var(--rankit-neon); }
.border-neon { border-color: var(--rankit-neon); }

.bg-tech-grid-dark { background-image: linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px); }
.bg-tech-grid-light { background-image: linear-gradient(to right, rgba(0, 0, 0, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 0, 0, 0.05) 1px, transparent 1px); }

.brutal-card { position: relative; transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94); border: 1px solid; }
.dark .brutal-card { background: #0a0a0a; border-color: #333; }
html:not(.dark) .brutal-card { background: #ffffff; border-color: #e5e5e5; box-shadow: 4px 4px 0px #00000010; }
.brutal-card:hover { border-color: var(--rankit-neon); transform: translate(-4px, -4px); }
.dark .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon); }
html:not(.dark) .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon), 6px 6px 0px 2px black; }

.btn-skew { background-color: var(--rankit-neon); color: white; transform: skewX(-10deg); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-skew:hover { background-color: white; color: black; box-shadow: 0 0 15px var(--rankit-neon); }
html:not(.dark) .btn-skew:hover { background-color: black; color: white; box-shadow: 4px 4px 0px rgba(0,0,0,0.2); }
.btn-content { transform: skewX(10deg); }

.animate-fade-in-up { animation: fadeInUp 0.8s ease-out forwards; opacity: 0; }
.animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
.delay-100 { animation-delay: 0.1s; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>