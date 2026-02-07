<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, onUnmounted, watch } from 'vue'
import { useRankitSocket } from '@/Composables/useRankitSocket'
import axios from 'axios'
import Modal from '@/Components/Modal.vue'

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
  twitch_channel?: string;
  hero_image?: string;
  status?: string;
  game?: string;
  // Nuevos campos
  rules?: string;
  prizes?: string;
  scoring_format?: any; // Puede ser string JSON u objeto
  bracket_data?: any; // Brackets
}

interface LiveDataResponse {
  tournament: TournamentInfo;
  matches: PublicMatch[];
  ranking: PublicRankingItem[];
}

// --- PROPS ---
// --- PROPS ---
const props = defineProps<{
  tournament?: TournamentInfo;
  sponsors?: any[];
  totalPoints?: number;
  laravelVersion?: string;
  phpVersion?: string;
  canLogin?: boolean;
  canRegister?: boolean;
  // Payment Props
  accessCode?: string | null;
  requiresPayment?: boolean;
  hasPaid?: boolean;
  isOwner?: boolean;
  entryFee?: number;
}>()

// ... (existing code) ...

const formatCurrency = (amount?: number) => {
    if (!amount) return '$0.00';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount / 100);
}

const handlePayment = () => {
    if (!confirm(`¿Ir a pagar la entrada de ${formatCurrency(props.entryFee)}?`)) return;
    const tournamentId = props.tournament?.id || tournamentData.value.id;
    if (tournamentId) {
        // Usamos useForm para hacer post (Inertia) o window.location si fuera externo,
        // pero aquí definimos rutas inertia/laravel. Usaremos router.post de inertia
        // import { router } from '@inertiajs/vue3' -> need to add import if not present,
        // or just use form helper const form = useForm({}); form.post(...)
        const form = useForm({});
        form.post(route('tournament.join', tournamentId));
    }
}

const copyCode = () => {
    if (props.accessCode) {
        copyToClipboard(props.accessCode);
        alert('Código copiado: ' + props.accessCode);
    }
}
// ...

// --- END SCRIPTS ---

// --- STATE DE APELACIÓN ---
const showAppealModal = ref(false)
const appealForm = useForm({
  replay: null as File | null,
})

function openAppeal() {
  showAppealModal.value = true
  appealForm.reset()
  appealForm.clearErrors()
}

function handleAppealFile(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    appealForm.replay = target.files[0]
  }
}

function submitAppeal() {
  const tournamentId = props.tournament?.id || tournamentData.value.id || 7; // ID dinámico o fallback
  // Ruta directa al controlador del admin para procesar
  appealForm.post(`/admin/tournaments/${tournamentId}/appeal`, {
    preserveScroll: true,
    onSuccess: () => {
      showAppealModal.value = false
      alert('✅ Apelación recibida. El sistema ha recalculado tus puntos según las reglas del torneo.')
      loadData(true) // Recargar tabla
    },
    onError: (err) => {
      console.error(err)
      alert('❌ Error al procesar. Verifica que el archivo sea un .replay válido.')
    }
  })
}

// --- RESTO DEL COMPONENTE ORIGINAL (State, Theme, Sockets) ---
const isDark = ref(true)

function applyTheme(nextDark: boolean) {
  isDark.value = nextDark
  const html = document.documentElement
  if (nextDark) {
    html.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    html.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

function toggleTheme() {
  applyTheme(!isDark.value)
}

const matches = ref<PublicMatch[]>([])
const ranking = ref<PublicRankingItem[]>([])
const tournamentData = ref<TournamentInfo>(props.tournament || {})
const isLoading = ref(true)
const progressText = ref(props.tournament?.status || "Cargando...")

const selectedMatchId = ref<number | null>(null)
const leaderboardType = ref<'players' | 'teams'>('players')
const filterMode = ref<string>('all') 
const sortBy = ref<'points' | 'kills'>('points')
const expandedRowIndex = ref<number | null>(null)

let pollInterval: number | undefined

const loadData = async (showSpinner = false) => {
  try {
    const id = props.tournament?.id || tournamentData.value.id || 7
    if(!id) return

    if (showSpinner) {
      isLoading.value = true
      expandedRowIndex.value = null
    }

    const url = `/api/live/${id}/data`
    const params: any = {
      type: leaderboardType.value,
      mode: filterMode.value, 
      sort: sortBy.value
    }
    if (selectedMatchId.value) params.match_id = selectedMatchId.value

    const res = await axios.get<LiveDataResponse>(url, { params })
    
    matches.value = res.data.matches
    ranking.value = res.data.ranking
    
    if(res.data.tournament) {
      // Merge para no perder datos previos si la API no devuelve todo
      tournamentData.value = { ...tournamentData.value, ...res.data.tournament }
      progressText.value = res.data.tournament.progress || ''
    }
    
  } catch (e) { 
    console.error("Error polling data:", e) 
  } finally {
    if (showSpinner) isLoading.value = false
    if (isLoading.value && !showSpinner) isLoading.value = false 
  }
}

const formatDec = (num: number | string) => {
  const n = typeof num === 'string' ? parseFloat(num) : num
  return isNaN(n) ? '0' : n.toFixed(1).replace(/\.0$/, '')
}

const changeFilter = (type: 'type' | 'mode' | 'sort', value: string) => {
  if (type === 'type') leaderboardType.value = value as any
  if (type === 'mode') filterMode.value = value
  if (type === 'sort') sortBy.value = value as any
  loadData(true)
}

const toggleMatchFilter = (matchId: number | null) => {
  selectedMatchId.value = selectedMatchId.value === matchId ? null : matchId
  loadData(true)
}

const copyObsLink = () => {
  const id = props.tournament?.id || tournamentData.value.id
  if (!id) return
  
  const baseUrl = `${window.location.origin}/widget/obs/global/${id}`
  const query = `?type=${leaderboardType.value}&mode=${filterMode.value}&sort=${sortBy.value}&limit=10${selectedMatchId.value ? `&match_id=${selectedMatchId.value}` : ''}`
  
  navigator.clipboard.writeText(baseUrl + query)
  alert(`✅ Link OBS Copiado!`)
}

const copyTrackingLink = (item: PublicRankingItem) => {
  const id = props.tournament?.id || tournamentData.value.id
  if (!id) return
  let targetName = item.player_name
  if (leaderboardType.value === 'teams' && item.member_names && item.member_names.length > 0) {
    targetName = item.member_names[0]
  }
  if (!targetName) return
  const baseUrl = `${window.location.origin}/widget/obs/global/${id}`
  const query = `?type=${leaderboardType.value}&mode=all&sort=${sortBy.value}&limit=1&search=${encodeURIComponent(targetName)}`
  navigator.clipboard.writeText(baseUrl + query)
  alert(`✅ Tracking OBS copiado para: ${targetName}`)
}

const activeTab = ref('resultados') 
const page = usePage()
const user = page.props.auth?.user as any

const { connect: connectSocket, disconnect: disconnectSocket, isConnected } = useRankitSocket(
  'community', 
  user?.id, 
  { autoConnect: false, manageVisibility: false }
)

const switchTab = (tab: string) => {
  activeTab.value = tab
  if (tab === 'comunidad') {
    connectSocket()
  } else {
    disconnectSocket()
  }
}

const twitchChannel = computed(() => tournamentData.value.twitch_channel ?? props.tournament?.twitch_channel ?? 'Rankit')
const tournamentTitle = computed(() => tournamentData.value.name ?? 'bellzCup') 

// --- COMPUTED PARA SCORING FORMAT ---
const parsedScoring = computed(() => {
    let raw = tournamentData.value.scoring_format;
    if (!raw) return null;
    if (typeof raw === 'string') {
        try { return JSON.parse(raw); } catch { return null; }
    }
    return raw;
})

const parsedBracket = computed(() => {
    let raw = tournamentData.value.bracket_data;
    if (!raw) return null;
    if (typeof raw === 'string') {
        try { return JSON.parse(raw); } catch { return null; }
    }
    return raw;
})

const bracketRounds = computed(() => {
    return parsedBracket.value?.rounds || [];
})

// --- SISTEMA DE REFERIDOS ORIGINAL ---
const me = user 
const showInviteModal = ref(false)
const showCodeModal = ref(false)
const myCode = computed(() => (me?.id ? `${me.id}rankit` : ''))
const inviteLink = computed(() => `https://rankit.pro/?ref=${myCode.value}`)
const redeemForm = useForm({ code: '' })

function openInvite() { showInviteModal.value = true }
function openCode() { showCodeModal.value = true; redeemForm.clearErrors() }
function submitCode() {
  redeemForm.post(route('bellzcup.referidos.redeem'), {
    preserveScroll: true,
    onSuccess: () => { showCodeModal.value = false; redeemForm.reset() },
  })
}
function copyToClipboard(text: string) {
  if (typeof window === 'undefined') return
  const t = String(text ?? '')
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard.writeText(t).catch(() => fallbackCopy(t))
    return
  }
  fallbackCopy(t)
}
function fallbackCopy(text: string) {
  const ta = document.createElement('textarea')
  ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.top = '-1000px'; ta.style.left = '-1000px'
  document.body.appendChild(ta); ta.select(); try { document.execCommand('copy') } catch (e) {}; document.body.removeChild(ta)
}

const handleVisibilityChange = () => {
  if (document.hidden) disconnectSocket()
  else if (activeTab.value === 'comunidad') connectSocket()
}

onMounted(() => {
  const savedTheme = localStorage.getItem('theme')
  const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true
  if (savedTheme === 'light') applyTheme(false)
  else if (savedTheme === 'dark') applyTheme(true)
  else applyTheme(systemPrefersDark)

  if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/@phosphor-icons/web'
    script.async = true
    document.head.appendChild(script)
  }
  
  const parentHost = window.location.hostname
  const initPlayer = () => {
    const embed = document.getElementById('twitch-embed')
    if (!embed) return
    // @ts-ignore
    if (window.Twitch && !embed.firstChild) {
      // @ts-ignore
      new window.Twitch.Player('twitch-embed', {
        channel: twitchChannel.value,
        width: '100%',
        height: '100%',
        parent: [parentHost],
      })
    }
  }

  if (!document.getElementById('twitch-embed-script')) {
    const script = document.createElement('script')
    script.setAttribute('id', 'twitch-embed-script')
    script.setAttribute('src', 'https://player.twitch.tv/js/embed/v1.js')
    script.onload = initPlayer
    document.head.appendChild(script)
  } else {
    setTimeout(initPlayer, 500)
  }

  loadData(true)
  pollInterval = window.setInterval(() => loadData(false), 240000)
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  disconnectSocket()
  if(pollInterval) clearInterval(pollInterval)
})
</script>

<template>
  <Head title="bellzCup - Rankit">
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
  </Head>

  <div class="overflow-x-hidden selection:bg-[var(--rankit-neon)] selection:text-white bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white font-sans transition-colors duration-300">
    
    <nav class="fixed w-full z-50 transition-colors duration-300 bg-white/90 border-b border-gray-200 dark:bg-[#050505]/95 dark:border-white/10 backdrop-blur-md h-20 flex items-center px-6 lg:px-12 justify-between">
      <Link href="/" class="flex items-center gap-3 cursor-pointer group">
        <svg class="w-10 h-10 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
        </svg>
        <span class="text-3xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">Rankit</span>
      </Link>

      <div class="flex items-center gap-4">
        <button @click="toggleTheme" class="p-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
          <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
          <i v-else class="text-xl ph-fill ph-moon"></i>
        </button>
        
        <template v-if="$page.props.auth?.user">
             <Link :href="route('dashboard')" class="hidden mr-4 text-sm font-bold tracking-wider text-gray-600 uppercase sm:block dark:text-gray-300 hover:text-black dark:hover:text-white">Dashboard</Link>
        </template>
        <template v-else>
             <Link :href="route('login')" class="px-6 py-2 text-sm font-bold tracking-wider uppercase btn-skew"><span class="btn-content">Ingresar</span></Link>
        </template>
      </div>
    </nav>

    <header class="relative min-h-[500px] h-auto flex items-end pt-24 overflow-hidden group pb-20 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px]">
      <div class="absolute inset-0 z-0 pointer-events-none">
        <img src="https://rankit.pro/public/BellzCupBeta/BannerBellzCup.png" class="w-full h-full object-cover opacity-30 dark:opacity-40 transform scale-105 group-hover:scale-110 transition duration-[30s] ease-linear" />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-gray-50/80 to-transparent dark:from-[#050505] dark:via-[#050505]/80 dark:to-transparent"></div>
      </div>

      <div class="relative z-10 flex flex-col w-full gap-8 px-6 mx-auto max-w-7xl lg:px-8">
        <div class="flex flex-wrap items-center gap-3 animate-fade-in-up">
          <span class="bg-red-600/90 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-[0_0_20px_rgba(220,38,38,0.6)] animate-pulse flex items-center gap-2 btn-skew">
             <span class="flex items-center gap-2 btn-content"><span class="w-1.5 h-1.5 bg-white rounded-full"></span> {{ props.tournament?.status ?? 'En Vivo' }}</span>
          </span>
           <button @click="loadData(true)" class="flex items-center gap-2 px-3 py-1 text-[10px] font-bold text-white uppercase bg-[var(--rankit-neon)] hover:opacity-80 transition rounded-sm group">
              <span class="flex items-center gap-2 btn-content">
                <i class="transition-transform duration-500 ph-bold ph-arrows-clockwise group-hover:rotate-180"></i>
                Actualizar
              </span>
           </button>
        </div>

        <div class="flex flex-col items-end justify-between gap-10 lg:flex-row">
          <div class="relative max-w-3xl delay-100 animate-fade-in-up">
            <h1 class="mb-4 text-5xl font-black leading-none tracking-tight text-black uppercase md:text-7xl font-display dark:text-white">
              {{ tournamentTitle }} <br />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-purple-600">
                LIVE STATS
              </span>
            </h1>
          </div>

          <div class="flex flex-col w-full gap-4 delay-200 lg:w-auto sm:flex-row lg:flex-col animate-fade-in-up">
            <div class="brutal-card px-4 py-3 text-center bg-white dark:bg-[#0a0a0a]">
              <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Total puntos</div>
              <div class="text-3xl font-black text-black font-display dark:text-white">
                {{ (props.totalPoints ?? 0).toLocaleString?.() ?? (props.totalPoints ?? 0) }}
              </div>
              <div class="text-[10px] text-gray-500">Rifa bellzCup</div>
            </div>

            <div class="flex flex-1 gap-3">
               <!-- BOTÓN APELAR RESULTADO -->
              <button type="button" @click="openAppeal" class="flex-1 px-6 py-3 text-sm font-bold tracking-wider text-black uppercase bg-yellow-500 btn-skew hover:bg-yellow-400">
                <span class="btn-content"><i class="ph-bold ph-warning"></i> APELAR RESULTADO</span>
              </button>

              <button type="button" @click="openCode" class="brutal-card px-4 py-2 text-center flex-1 flex flex-col justify-center bg-white dark:bg-[#0a0a0a]">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Acceso</div>
                <div class="text-sm font-bold font-display">CÓDIGO</div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="sticky top-20 z-40 bg-white/90 dark:bg-[#050505]/90 backdrop-blur-lg border-b border-gray-200 dark:border-white/10">
      <div class="flex gap-8 px-6 mx-auto overflow-x-auto max-w-7xl lg:px-8 no-scrollbar">
        <button
          v-for="tab in ['resultados', 'comunidad', 'reglas', 'premios', ...(parsedBracket ? ['brackets'] : [])]"
          :key="tab"
          @click="switchTab(tab)"
          class="flex items-center gap-2 py-5 text-xs font-bold tracking-widest uppercase transition duration-300 border-b-2 whitespace-nowrap group"
          :class="activeTab === tab ? 'border-neon text-black dark:text-white' : 'border-transparent text-gray-500 hover:text-black dark:hover:text-gray-300'"
        >
          <i v-if="tab === 'resultados'" class="transition ph ph-list-numbers group-hover:text-neon"></i>
          <i v-if="tab === 'comunidad'" class="transition ph ph-users group-hover:text-neon"></i>
          <i v-if="tab === 'reglas'" class="transition ph ph-book-open group-hover:text-neon"></i>
          <i v-if="tab === 'premios'" class="transition ph ph-trophy group-hover:text-neon"></i>
          <i v-if="tab === 'brackets'" class="transition ph ph-tree-structure group-hover:text-neon"></i>
          {{ tab }}
        </button>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 lg:px-8 py-10 min-h-[600px]">
      
      <div v-if="activeTab === 'resultados'" class="grid grid-cols-1 gap-8 animate-fade-in lg:grid-cols-12">
        <aside class="space-y-6 lg:col-span-4">
            <div class="brutal-card p-4 bg-white dark:bg-[#0a0a0a]">
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-2 bg-gray-100 rounded dark:bg-white/5">
                        <span class="text-[10px] uppercase font-bold text-gray-500">ID Pública</span>
                        <span class="font-mono text-sm font-bold">{{ tournamentData.id || '---' }}</span>
                    </div>
                    <button @click="copyObsLink" class="w-full py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-bold uppercase btn-skew flex items-center justify-center gap-2 group">
                        <span class="flex items-center gap-2 btn-content"><i class="text-lg ph-bold ph-broadcast"></i> Copiar Tabla OBS</span>
                    </button>
                </div>
            </div>

            <div>
                <h3 class="mb-4 text-xs font-bold tracking-widest text-gray-500 uppercase">Historial de Partidas</h3>
                <div @click="toggleMatchFilter(null)" 
                    class="flex items-center justify-between p-4 mb-3 transition cursor-pointer brutal-card hover:bg-gray-50 dark:hover:bg-white/5"
                    :class="!selectedMatchId ? 'border-[var(--rankit-neon)] bg-[var(--rankit-neon)]/5' : ''">
                    <span class="text-sm font-bold">Ranking Global Acumulado</span>
                    <i class="text-xl ph ph-globe"></i>
                </div>
                <div class="space-y-2 max-h-[500px] overflow-y-auto pr-1 custom-scrollbar">
                    <div v-for="match in matches" :key="match.id" @click="toggleMatchFilter(match.id)"
                        class="relative p-3 overflow-hidden transition cursor-pointer brutal-card hover:bg-gray-50 dark:hover:bg-white/5 group"
                        :class="selectedMatchId === match.id ? 'border-[var(--rankit-neon)]' : ''">
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

        <div class="lg:col-span-8">
            <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-6 p-4 brutal-card bg-white dark:bg-[#0a0a0a]">
                <div class="flex p-1 bg-gray-200 rounded-lg dark:bg-white/5">
                    <button @click="changeFilter('type', 'players')" :class="leaderboardType==='players'?'bg-white dark:bg-gray-800 shadow text-black dark:text-white':'text-gray-500'" class="px-4 py-1 text-xs font-bold uppercase transition rounded">Jugadores</button>
                    <button @click="changeFilter('type', 'teams')" :class="leaderboardType==='teams'?'bg-white dark:bg-gray-800 shadow text-black dark:text-white':'text-gray-500'" class="px-4 py-1 text-xs font-bold uppercase transition rounded">Equipos</button>
                </div>
                <div class="flex max-w-full gap-1 p-1 overflow-x-auto bg-gray-200 rounded-lg dark:bg-white/5">
                      <button v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" :key="m" @click="changeFilter('mode', m)"
                        :class="filterMode===m ? 'bg-[var(--rankit-neon)] text-white shadow' : 'text-gray-500 hover:text-black dark:hover:text-white'"
                        class="px-3 py-1 text-[10px] font-bold uppercase rounded transition whitespace-nowrap">
                        {{ m }}
                      </button>
                </div>
                <button @click="changeFilter('sort', sortBy==='points'?'kills':'points')" class="text-xs font-bold text-[var(--rankit-neon)] hover:underline uppercase flex items-center gap-1">
                    <i class="ph-bold" :class="sortBy==='points' ? 'ph-trophy' : 'ph-sword'"></i>
                    {{ sortBy }}
                </button>
            </div>

            <div class="brutal-card bg-white dark:bg-[#0a0a0a] min-h-[400px] relative">
                  
                  <div v-if="isLoading" class="absolute inset-0 z-20 flex items-center justify-center transition-opacity duration-200 bg-white/80 dark:bg-black/80 backdrop-blur-sm">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-10 h-10 border-4 border-[var(--rankit-neon)] border-t-transparent rounded-full animate-spin"></div>
                        <span class="text-[10px] font-bold uppercase text-[var(--rankit-neon)] animate-pulse">Actualizando datos...</span>
                    </div>
                  </div>

                  <div class="overflow-x-auto">
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
                                <tr v-if="expandedRowIndex === idx" class="bg-[var(--rankit-neon)]/5">
                                    <td colspan="5" class="p-4">
                                        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                                            <div class="grid flex-1 w-full grid-cols-2 gap-4 text-xs md:grid-cols-4 md:w-auto">
                                                <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded"><span class="block text-gray-500 uppercase text-[9px]">Avg Puntos</span><span class="text-lg font-bold">{{ formatDec(item.avg_points) }}</span></div>
                                                <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded"><span class="block text-gray-500 uppercase text-[9px]">Avg Kills</span><span class="text-lg font-bold text-red-500">{{ formatDec(item.avg_kills) }}</span></div>
                                                <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded"><span class="block text-gray-500 uppercase text-[9px]">Avg Top</span><span class="text-lg font-bold text-yellow-500">#{{ formatDec(item.avg_placement) }}</span></div>
                                                <div class="p-2 border border-[var(--rankit-neon)]/20 bg-white dark:bg-black rounded"><span class="block text-gray-500 uppercase text-[9px]">Mejor Top</span><span class="text-lg font-bold text-white">#{{ item.best_placement }}</span></div>
                                            </div>
                                            <button @click.stop="copyTrackingLink(item)" class="px-3 py-2 bg-black dark:bg-white text-white dark:text-black text-[10px] font-bold uppercase rounded hover:opacity-80 transition flex items-center gap-2 whitespace-nowrap w-full md:w-auto justify-center">
                                                <i class="ph ph-target"></i> Copiar Tracking OBS
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                  </div>
            </div>
        </div>
      </div>

      <div v-show="activeTab === 'comunidad'" class="py-20 text-center">
         <!-- Aquí iría el chat o embed de twitch si se desea -->
         <div id="twitch-embed" class="w-full overflow-hidden bg-black border border-gray-800 rounded-lg shadow-2xl aspect-video"></div>
      </div>

      <!-- REGLAS TAB CON SISTEMA DE PUNTOS -->
      <div v-show="activeTab === 'reglas'" class="py-8 space-y-8 animate-fade-in">
         
         <!-- SISTEMA DE PUNTOS VISUAL -->
         <div v-if="parsedScoring" class="brutal-card bg-white dark:bg-[#0a0a0a] p-8 max-w-4xl mx-auto border-l-4 border-l-[var(--rankit-neon)]">
             <div class="flex items-center gap-3 mb-6">
                 <div class="w-10 h-10 flex items-center justify-center bg-[var(--rankit-neon)] text-white rounded font-bold">
                     <i class="text-xl ph-bold ph-chart-bar"></i>
                 </div>
                 <h2 class="text-2xl font-black uppercase font-display">Sistema de Puntuación</h2>
             </div>

             <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                 <!-- KILLS -->
                 <div class="flex flex-col items-center justify-center p-6 text-center border border-gray-200 rounded-lg bg-gray-50 dark:bg-white/5 dark:border-white/10">
                     <div class="mb-2 text-xs font-bold tracking-widest text-gray-500 uppercase">Eliminaciones</div>
                     <div class="text-5xl font-black font-display text-[var(--rankit-neon)] mb-1">
                         +{{ parsedScoring.kill_points }}
                     </div>
                     <div class="text-sm font-bold">Puntos por Kill</div>
                 </div>

                 <!-- PLACEMENT TABLE -->
                 <div class="p-6 border border-gray-200 rounded-lg bg-gray-50 dark:bg-white/5 dark:border-white/10">
                     <div class="mb-4 text-xs font-bold tracking-widest text-center text-gray-500 uppercase">Posicionamiento</div>
                     <div v-if="parsedScoring.placement && parsedScoring.placement.length > 0" class="space-y-2">
                         <div v-for="(rule, idx) in parsedScoring.placement" :key="idx" 
                              class="flex items-center justify-between p-2 bg-white border border-gray-100 rounded dark:bg-black/20 dark:border-white/5">
                             <span class="text-sm font-bold">Top {{ rule.from }} - {{ rule.to }}</span>
                             <span class="font-mono font-bold text-[var(--rankit-neon)]">+{{ rule.points }} pts</span>
                         </div>
                     </div>
                     <div v-else class="text-sm italic text-center text-gray-400">
                         Sistema por defecto Rankit (Top 1, 5, 15, 25)
                     </div>
                 </div>
             </div>
         </div>

         <div class="brutal-card bg-white dark:bg-[#0a0a0a] p-8 max-w-4xl mx-auto">
             <div class="flex items-center gap-3 mb-6">
                 <div class="w-12 h-12 flex items-center justify-center bg-[var(--rankit-neon)]/10 text-[var(--rankit-neon)] rounded-full">
                     <i class="text-2xl ph-duotone ph-book-open"></i>
                 </div>
                 <h2 class="text-3xl font-black uppercase font-display">Reglamento Oficial</h2>
             </div>
             
             <div class="font-sans text-sm leading-relaxed prose whitespace-pre-wrap dark:prose-invert max-w-none">
                 {{ tournamentData.rules || 'No hay reglas publicadas todavía para este torneo.' }}
             </div>
         </div>
      </div>

      <!-- PREMIOS TAB -->
      <div v-show="activeTab === 'premios'" class="py-8 animate-fade-in">
          <div class="brutal-card bg-white dark:bg-[#0a0a0a] p-8 max-w-4xl mx-auto text-center">
             <i class="mb-4 text-6xl text-yellow-500 ph-duotone ph-trophy animate-bounce"></i>
             <h2 class="mb-8 text-3xl font-black uppercase font-display">Prize Pool & Recompensas</h2>
             
             <div class="p-6 font-sans text-sm leading-relaxed prose whitespace-pre-wrap border border-gray-300 border-dashed rounded-lg dark:prose-invert max-w-none bg-gray-50 dark:bg-white/5 dark:border-white/10">
                 {{ tournamentData.prizes || 'Los premios se anunciarán próximamente.' }}
             </div>
         </div>
      </div>

      <!-- BRACKETS TAB -->
      <div v-show="activeTab === 'brackets' && parsedBracket" class="py-8 animate-fade-in">
          <div class="brutal-card bg-white dark:bg-[#0a0a0a] p-8 max-w-7xl mx-auto overflow-x-auto">
              <div class="flex items-center gap-3 mb-6 sticky left-0">
                  <div class="w-10 h-10 flex items-center justify-center bg-[var(--rankit-neon)] text-white rounded font-bold">
                      <i class="text-xl ph-bold ph-tree-structure"></i>
                  </div>
                  <h2 class="text-2xl font-black uppercase font-display">Bracket del Torneo</h2>
              </div>

              <!-- BRACKET TREE -->
              <div class="flex gap-8 pb-4 min-w-max">
                  <div v-for="round in bracketRounds" :key="round.name" class="flex flex-col gap-4 min-w-[200px]">
                      <div class="text-xs font-bold uppercase text-center bg-gray-100 dark:bg-white/5 py-2 rounded mb-2 text-[var(--rankit-neon)] tracking-widest border-b-2 border-[var(--rankit-neon)]">
                          {{ round.name }}
                      </div>
                      <div class="flex flex-col justify-around h-full gap-4"> 
                        <!-- Nota: justify-around funciona si la altura es fija, en bracket dinamico a veces se usa gap + espaciadores.
                             Para simplicidad, usaremos gap uniforme por ahora. -->
                          <div v-for="match in round.matches" :key="match.id" 
                               class="relative bg-gray-50 dark:bg-black/40 border border-gray-200 dark:border-white/10 rounded p-3 transition hover:border-[var(--rankit-neon)] group">
                              
                              <!-- Connector Lines (Visual enhancement logic would go here, skipping for basic CSS flex) -->
                              
                              <div class="flex justify-between items-center mb-2 border-b border-dashed border-gray-200 dark:border-white/5 pb-1">
                                  <span class="text-[9px] font-mono text-gray-400">{{ match.id }}</span>
                                  <span v-if="match.winner" class="text-[9px] font-bold text-green-500">FINALIZADO</span>
                                  <span v-else class="text-[9px] font-bold text-yellow-500 animate-pulse">PENDIENTE</span>
                              </div>

                              <div class="flex flex-col gap-2">
                                  <!-- P1 -->
                                  <div class="flex justify-between items-center p-1 rounded transition"
                                       :class="match.winner === match.p1 ? 'bg-[var(--rankit-neon)]/10 text-[var(--rankit-neon)] font-bold' : 'text-gray-600 dark:text-gray-300'">
                                      <span class="truncate max-w-[120px] text-xs">{{ match.p1 }}</span>
                                      <span class="font-mono text-xs">{{ match.score1 || '-' }}</span>
                                  </div>
                                  <!-- P2 -->
                                  <div class="flex justify-between items-center p-1 rounded transition"
                                       :class="match.winner === match.p2 ? 'bg-[var(--rankit-neon)]/10 text-[var(--rankit-neon)] font-bold' : 'text-gray-600 dark:text-gray-300'">
                                      <span class="truncate max-w-[120px] text-xs">{{ match.p2 }}</span>
                                      <span class="font-mono text-xs">{{ match.score2 || '-' }}</span>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

    </main>

    <!-- MODAL DE APELACIÓN -->
    <div v-if="showAppealModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showAppealModal=false"></div>
      <div class="relative w-full max-w-lg brutal-card bg-white dark:bg-[#0a0a0a] p-8 animate-fade-in-up border-l-4 border-l-yellow-500">
        <div class="flex items-start justify-between gap-4 mb-6">
          <div>
            <div class="flex items-center gap-2 text-xs font-bold tracking-widest text-yellow-500 uppercase">
                <i class="ph-fill ph-warning-octagon"></i> Corrección de Puntos
            </div>
            <div class="text-3xl font-black text-black uppercase font-display dark:text-white">Apelar Resultado</div>
          </div>
          <button type="button" class="text-gray-500 transition hover:text-black dark:hover:text-white" @click="showAppealModal=false">
            <i class="text-xl ph-bold ph-x"></i>
          </button>
        </div>

        <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
            Sube el archivo <strong>.replay</strong> de tu partida. El sistema buscará la partida original y recalculará 
            <strong>tus puntos automáticamente</strong> basándose en las reglas oficiales del torneo.
        </p>

        <form @submit.prevent="submitAppeal" class="space-y-6">
           <div class="relative p-8 text-center transition-colors border-2 border-gray-300 border-dashed cursor-pointer dark:border-gray-700 rounded-xl hover:border-yellow-500 bg-gray-50 dark:bg-black/20 group">
               <input type="file" @change="handleAppealFile" accept=".replay" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required />
               <div class="pointer-events-none">
                   <i class="mb-2 text-4xl text-gray-400 transition-colors ph-duotone ph-upload-simple group-hover:text-yellow-500"></i>
                   <div v-if="appealForm.replay" class="px-4 text-sm font-bold text-yellow-500 truncate">
                       {{ appealForm.replay.name }}
                   </div>
                   <div v-else class="text-sm font-bold text-gray-500 group-hover:text-gray-300">
                       Arrastra o selecciona tu .replay
                   </div>
               </div>
           </div>

           <div class="flex justify-end gap-3">
               <button type="button" @click="showAppealModal=false" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase transition hover:text-black dark:hover:text-white">Cancelar</button>
               <button type="submit" :disabled="appealForm.processing || !appealForm.replay" 
                   class="px-6 py-3 text-sm font-bold text-black uppercase bg-yellow-500 btn-skew disabled:opacity-50 disabled:cursor-not-allowed">
                   <span class="btn-content" v-if="!appealForm.processing">Enviar Apelación</span>
                   <span class="flex items-center gap-2 btn-content" v-else>
                       <i class="ph-bold ph-spinner animate-spin"></i> Procesando...
                   </span>
               </button>
           </div>
        </form>
      </div>
    </div>

    <!-- MODALES EXISTENTES (INVITE/CODE) -->
    <div v-if="showCodeModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60" @click="showCodeModal=false"></div>
      <div class="relative w-full max-w-md brutal-card bg-white dark:bg-[#0a0a0a] p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-bold text-gray-500 uppercase">Ingresar código</div>
            <div class="text-2xl font-black uppercase font-display">Pega tu código</div>
          </div>
          <button type="button" class="text-gray-500 hover:text-black dark:hover:text-white" @click="showCodeModal=false">✕</button>
        </div>
        <form class="mt-4 space-y-3" @submit.prevent="submitCode">
          <input v-model="redeemForm.code" type="text" placeholder="Ej: 22rankit" class="w-full px-3 py-3 text-black bg-white brutal-card dark:bg-black/30 dark:text-white" />
          <div v-if="redeemForm.errors.code" class="text-xs font-bold text-red-500">{{ redeemForm.errors.code }}</div>
          <button class="w-full py-3 text-sm font-bold uppercase btn-skew" type="submit" :disabled="redeemForm.processing">
            <span class="btn-content">{{ redeemForm.processing ? 'Aplicando...' : 'Aplicar código' }}</span>
          </button>
        </form>
      </div>
    </div>

    <div v-if="showInviteModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60" @click="showInviteModal=false"></div>
      <div class="relative w-full max-w-lg brutal-card bg-white dark:bg-[#0a0a0a] p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-bold text-gray-500 uppercase">Invitar amigo</div>
            <div class="text-2xl font-black uppercase font-display">Comparte tu código</div>
          </div>
          <button type="button" class="text-gray-500 hover:text-black dark:hover:text-white" @click="showInviteModal=false">✕</button>
        </div>
        <div class="mt-4 space-y-3 text-sm text-gray-700 dark:text-gray-300">
          <p>1) Mándale este link a tu amigo y pidele que se registre:</p>
          <div class="flex items-center justify-between gap-3 p-3 brutal-card bg-gray-50 dark:bg-black/30">
            <div class="font-mono text-xs break-all">{{ inviteLink }}</div>
            <button type="button" class="px-3 py-2 text-xs font-bold btn-skew" @click="copyToClipboard(inviteLink)">
              <span class="btn-content">Copiar</span>
            </button>
          </div>
          <p>2) Y pidele que ingrese este código en “Ingresar código”:</p>
          <div class="flex items-center justify-between gap-3 p-3 brutal-card bg-gray-50 dark:bg-black/30">
            <div class="font-mono text-lg font-bold">{{ myCode }}</div>
            <button type="button" class="px-3 py-2 text-xs font-bold btn-skew" @click="copyToClipboard(myCode)">
              <span class="btn-content">Copiar</span>
            </button>
          </div>
          <p class="text-[11px] text-gray-500">Cuando tu amigo ingrese el código, tú recibes <b>+2 puntos</b> para la rifa.</p>
        </div>
      </div>
    </div>

  </div>
</template>

<style>
/* Global Styles from Inicio.vue */
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
.delay-200 { animation-delay: 0.2s; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
.animate-marquee { animation: marquee 30s linear infinite; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: var(--rankit-neon); border-radius: 4px; }
</style>