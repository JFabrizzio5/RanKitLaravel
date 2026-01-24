<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, onUnmounted, watch } from 'vue'
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
  twitch_channel?: string;
  hero_image?: string;
  status?: string;
  game?: string;
}

interface LiveDataResponse {
  tournament: TournamentInfo;
  matches: PublicMatch[];
  ranking: PublicRankingItem[];
}

// --- TIPOS NUEVOS PARA NOTIFICACIONES ---
interface Toast {
    id: number;
    message: string;
    subtext?: string;
    type: 'drop' | 'info';
}

// --- PROPS ---
const props = defineProps<{
  tournament?: TournamentInfo;
  sponsors?: any[];
  totalPoints?: number;
  laravelVersion?: string;
  phpVersion?: string;
  canLogin?: boolean;
  canRegister?: boolean;
}>()

// --- STATE REACTIVO PARA PUNTOS (INTEGRACIÓN) ---
// Usamos esto en lugar de props.totalPoints directamente para que el socket pueda actualizarlo
const livePoints = ref(props.totalPoints ?? 0)

// Si Laravel actualiza la prop (ej. al canjear código manualmente), sincronizamos
watch(() => props.totalPoints, (newVal) => {
    if (newVal !== undefined) livePoints.value = newVal
})

// --- AUTH & USER ---
const page = usePage()
const user = page.props.auth?.user as any
const me = user // Alias para compatibilidad

/**
 * THEME MANAGEMENT
 */
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

/**
 * LOGICA DE DATOS (TABLA Y FILTROS)
 */
const matches = ref<PublicMatch[]>([])
const ranking = ref<PublicRankingItem[]>([])
const tournamentData = ref<TournamentInfo>(props.tournament || { id: 7 })
const isLoading = ref(true)
const progressText = ref(props.tournament?.status || "Cargando...")

// Filtros de Tabla
const selectedMatchId = ref<number | null>(null)
const leaderboardType = ref<'players' | 'teams'>('players')
const filterMode = ref<string>('all') 
const sortBy = ref<'points' | 'kills'>('points')
const expandedRowIndex = ref<number | null>(null)

let pollInterval: number | undefined

const loadData = async (showSpinner = false) => {
  try {
    const id = 7; // ID Fijo según tu lógica
    tournamentData.value.id = id;

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
      tournamentData.value.name = res.data.tournament.name
      progressText.value = res.data.tournament.progress || ''
      tournamentData.value.twitch_channel = res.data.tournament.twitch_channel 
    }
    
  } catch (e) { 
    console.error("Error polling data:", e) 
  } finally {
    if (showSpinner) isLoading.value = false
    if (isLoading.value && !showSpinner) isLoading.value = false 
  }
}

// Helpers de Tabla
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
  const id = 7
  const baseUrl = `${window.location.origin}/widget/obs/global/${id}`
  const query = `?type=${leaderboardType.value}&mode=${filterMode.value}&sort=${sortBy.value}&limit=10${selectedMatchId.value ? `&match_id=${selectedMatchId.value}` : ''}`
  navigator.clipboard.writeText(baseUrl + query)
  alert(`✅ Link OBS Copiado!\n\nConfiguración:\n• Modo: ${filterMode.value.toUpperCase()}\n• Vista: ${leaderboardType.value.toUpperCase()}\n• Orden: ${sortBy.value.toUpperCase()}\n• Match: ${selectedMatchId.value ? '#' + selectedMatchId.value : 'Global'}`)
}

const copyTrackingLink = (item: PublicRankingItem) => {
  const id = 7
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

/**
 * =========================================================================
 * LOGICA DEL WEBSOCKET (PYTHON MICROSERVICE) E INTEGRACIONES
 * =========================================================================
 * Implementación directa para conectar a jos5dev.com
 */
const activeTab = ref('comunidad') 

// --- TOASTS & WINNER STATE ---
const toasts = ref<Toast[]>([])
const showWinnerScreen = ref(false)
const winnerData = ref<any>(null)

function addToast(msg: string, sub: string) {
    const id = Date.now()
    toasts.value.push({ id, message: msg, subtext: sub, type: 'drop' })
    // Remover automáticamente después de 6 seg
    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id)
    }, 6000)
}

// Efecto de sonido opcional (moneda de juego)
const playCoinSound = () => {
    try {
        const audio = new Audio('https://cdn.freesound.org/previews/341/341695_5858296-lq.mp3'); 
        audio.volume = 0.4;
        audio.play().catch(() => {}); // Ignorar error si el navegador bloquea autoplay
    } catch(e) {}
}

// Estado reactivo del Socket
const socket = ref<WebSocket | null>(null)
const isConnected = ref(false)
const socketError = ref(false)
let pingInterval: number | undefined

const connectSocket = () => {
  if (!user?.id) {
    console.warn('[RankitSocket] ⚠️ Usuario no logueado. No se puede conectar al socket.')
    return
  }

  // Si ya existe una conexión abierta, no reconectar
  if (socket.value && (socket.value.readyState === WebSocket.OPEN || socket.value.readyState === WebSocket.CONNECTING)) {
    return
  }

  // --- CONSTRUCCIÓN DE LA URL ---
  const host = 'jos5dev.com'
  const path = `/ws/community/${user.id}`
  
  // URL Hardcodeada a wss://jos5dev.com/...
  const wsUrl = `wss://${host}${path}`
  
  // --- LOG SOLICITADO ---
  console.log(`%c[RankitSocket] 🔌 INTENTANDO CONECTAR A: ${wsUrl}`, 'color: #00ffff; font-weight: bold; background: #333; padding: 4px;')

  try {
    socket.value = new WebSocket(wsUrl)

    socket.value.onopen = () => {
      console.log(`%c[RankitSocket] ✅ CONEXIÓN EXITOSA`, 'color: #00ff00; font-weight: bold;')
      isConnected.value = true
      socketError.value = false
      
      // Iniciar Ping-Pong para mantener viva la conexión
      startPingPong()
    }

    socket.value.onmessage = (event) => {
      // Loguear mensajes entrantes (como puntos ganados o progreso)
      // console.log(`%c[RankitSocket] 📩 MENSAJE RECIBIDO:`, 'color: orange', event.data)
      
      try {
        if(event.data === 'pong') return;

        const data = JSON.parse(event.data)
        
        // --- 1. INTEGRACIÓN: PUNTO GANADO ---
        if (data.type === 'drop_earned' || data.type === 'point_earned') {
           // Actualizar variable reactiva en tiempo real
           livePoints.value = data.total_points
           
           // Lanzar Toast solicitado
           addToast("HAZ GANADO POR VER EN RANKIT.PRO", "2 BOLETOS MAS PARA MAS PROBABILIDADES")
           playCoinSound()
           
           console.log('💰 ¡Punto ganado!', data.total_points)
        }

        // --- 2. INTEGRACIÓN: GANADOR ---
        if (data.type === 'winner_alert') {
            winnerData.value = data
            showWinnerScreen.value = true
        }

      } catch (e) {
        // Ignorar si no es JSON
      }
    }

    socket.value.onclose = (event) => {
      console.log(`%c[RankitSocket] ❌ DESCONECTADO (Código: ${event.code})`, 'color: red;')
      isConnected.value = false
      stopPingPong()
      socket.value = null
    }

    socket.value.onerror = (error) => {
      console.error('[RankitSocket] ⚠️ ERROR:', error)
      socketError.value = true
    }

  } catch (e) {
    console.error('[RankitSocket] Error crítico al instanciar WebSocket:', e)
  }
}

const disconnectSocket = () => {
  if (socket.value) {
    console.log('[RankitSocket] Cerrando conexión manualmente...')
    socket.value.close()
    socket.value = null
  }
  isConnected.value = false
  stopPingPong()
}

// Mantener la conexión viva enviando "ping" cada 30s
const startPingPong = () => {
  stopPingPong()
  pingInterval = window.setInterval(() => {
    if (socket.value && socket.value.readyState === WebSocket.OPEN) {
      socket.value.send('ping')
    }
  }, 30000)
}

const stopPingPong = () => {
  if (pingInterval) {
    clearInterval(pingInterval)
    pingInterval = undefined
  }
}

// Gestión de Tabs y Visibilidad
const switchTab = (tab: string) => {
  activeTab.value = tab

  if (tab === 'comunidad') {
    connectSocket()
  } else {
    disconnectSocket()
  }
}

const handleVisibilityChange = () => {
  if (document.hidden) {
    console.log('[RankitSocket] 🙈 Pestaña oculta. Desconectando para ahorrar recursos...')
    disconnectSocket()
  } else {
    if (activeTab.value === 'comunidad') {
      console.log('[RankitSocket] 👁️ Pestaña visible. Reconectando...')
      connectSocket()
    }
  }
}

// Twitch Props
const twitchChannel = computed(() => tournamentData.value.twitch_channel ?? props.tournament?.twitch_channel ?? 'Rankit')
const tournamentTitle = computed(() => tournamentData.value.name ?? 'bellzCup') 
const twitchChatUrl = computed(() => {
    if (typeof window === 'undefined') return ''
    const parent = window.location.hostname
    const channel = twitchChannel.value
    return `https://www.twitch.tv/embed/${channel}/chat?parent=${parent}&darkpopout`
})

// ==============================
// REFERIDOS (MODALES + REDEEM)
// ==============================
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
    onSuccess: () => { 
        showCodeModal.value = false; 
        redeemForm.reset();
        // livePoints se actualiza gracias al watcher de props
        alert('¡Código canjeado con éxito!')
    },
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

onMounted(() => {
  // Theme init
  const savedTheme = localStorage.getItem('theme')
  const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true
  if (savedTheme === 'light') applyTheme(false)
  else if (savedTheme === 'dark') applyTheme(true)
  else applyTheme(systemPrefersDark)

  // Cargar Iconos
  if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/@phosphor-icons/web'
    script.async = true
    document.head.appendChild(script)
  }
  
  // Twitch Embed Player
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

  // Data Loading
  loadData(true)
  pollInterval = window.setInterval(() => loadData(false), 240000) // 4 min polling

  // INICIO DE SOCKET
  // Si arrancamos en la pestaña comunidad, conectamos
  if (activeTab.value === 'comunidad') {
    connectSocket()
  }

  // Listeners de Visibilidad
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
    
    <!-- NUEVO: SISTEMA DE TOASTS (NOTIFICACIONES FLOTANTES) -->
    <div class="fixed top-24 right-6 z-[100] flex flex-col gap-4 pointer-events-none">
        <transition-group name="toast">
            <div v-for="toast in toasts" :key="toast.id" class="pointer-events-auto bg-black border-2 border-[var(--rankit-neon)] text-white px-6 py-4 rounded-lg shadow-[0_0_20px_rgba(191,0,255,0.5)] brutal-card max-w-sm relative overflow-hidden">
                 <div class="absolute inset-0 bg-gradient-to-r from-[var(--rankit-neon)]/20 to-transparent"></div>
                 <div class="relative z-10 flex items-start gap-3">
                    <i class="mt-1 text-2xl text-yellow-400 ph-fill ph-ticket animate-bounce"></i>
                    <div>
                        <h4 class="text-sm font-black italic uppercase font-display text-[var(--rankit-neon)]">{{ toast.message }}</h4>
                        <p class="text-xs font-bold text-gray-300">{{ toast.subtext }}</p>
                    </div>
                 </div>
            </div>
        </transition-group>
    </div>

    <!-- NUEVO: PANTALLA DE GANADOR (OVERLAY) -->
    <div v-if="showWinnerScreen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/95 backdrop-blur-xl animate-fade-in">
        <div class="text-center p-8 border-4 border-yellow-500 rounded-2xl bg-gradient-to-br from-yellow-900/50 to-black brutal-card max-w-2xl w-full mx-4 shadow-[0_0_100px_rgba(234,179,8,0.5)] relative overflow-hidden">
            <!-- Confetti visual CSS puro -->
            <div class="absolute inset-0 pointer-events-none opacity-20 bg-[url('https://cdn.dribbble.com/users/129972/screenshots/3964116/75_smile.gif')] bg-cover bg-center"></div>
            
            <div class="relative z-10">
                <i class="mb-4 text-6xl text-yellow-500 ph-fill ph-trophy animate-bounce"></i>
                <h2 class="mb-2 text-6xl font-black text-white uppercase font-display drop-shadow-xl">¡GANASTE!</h2>
                <p class="text-2xl font-bold text-yellow-400 uppercase font-display">{{ winnerData?.username }}</p>
                
                <div class="w-full h-1 my-8 bg-gradient-to-r from-transparent via-yellow-500 to-transparent"></div>
                
                <p class="mb-8 text-lg text-gray-300">{{ winnerData?.message }}</p>
                
                <button @click="showWinnerScreen = false" class="px-8 py-4 text-xl font-bold text-black uppercase bg-yellow-500 btn-skew hover:scale-105">
                    <span class="btn-content">¡ENTENDIDO!</span>
                </button>
            </div>
        </div>
    </div>

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
        <div v-if="isConnected && activeTab === 'comunidad'" class="items-center hidden gap-2 px-3 py-1 text-xs font-bold text-green-500 border rounded-full md:flex bg-green-500/10 animate-pulse border-green-500/20">
            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
            SUMANDO PUNTOS
        </div>

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
          <span class="bg-white/10 border border-black/10 dark:border-white/10 text-black dark:text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 cursor-default brutal-card">
            <i class="ph ph-game-controller text-neon"></i>
            {{ props.tournament?.game ?? 'Fortnite' }}
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
            <p class="max-w-xl py-1 pl-6 text-lg font-light text-gray-600 border-l-4 dark:text-gray-400 border-neon">
             Torneo de fortnite con creadores de contenido y actores de doblaje. Sigue el directo, gana puntos y consigue premios exclusivos solo por ver el stream Desde Rankit.Pro
            </p>
          </div>

          <div class="flex flex-col w-full gap-4 delay-200 lg:w-auto sm:flex-row lg:flex-col animate-fade-in-up">
            <div class="brutal-card px-4 py-3 text-center bg-white dark:bg-[#0a0a0a]">
              <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Total boletos</div>
              <!-- AQUI EL CAMBIO: Usamos livePoints en vez de prop directo -->
              <div class="text-3xl font-black text-black transition-all duration-300 font-display dark:text-white" :key="livePoints">
                {{ livePoints.toLocaleString() }}
              </div>
              <div class="text-[10px] text-gray-500">Rifa bellzCup</div>
            </div>

            <div class="flex flex-1 gap-3">
              <button type="button" @click="openCode" class="brutal-card px-4 py-2 text-center flex-1 flex flex-col justify-center bg-white dark:bg-[#0a0a0a]">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Acceso</div>
                <div class="text-sm font-bold font-display">INGRESAR CÓDIGO</div>
              </button>

              <button type="button" @click="openInvite" class="flex-1 px-6 py-3 text-sm font-bold tracking-wider uppercase btn-skew">
                <span class="btn-content">INVITAR AMIGO</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="absolute bottom-0 z-20 flex items-center w-full overflow-hidden border-t h-14 bg-white/50 dark:bg-black/80 border-black/5 dark:border-white/5 backdrop-blur-md">
        <div class="flex items-center gap-16 pl-16 animate-marquee whitespace-nowrap">
          <span v-for="i in 10" :key="i" class="font-bold tracking-widest text-gray-400 uppercase opacity-50">SPONSOR {{ i }}</span>
        </div>
      </div>
    </header>

    <div class="sticky top-20 z-40 bg-white/90 dark:bg-[#050505]/90 backdrop-blur-lg border-b border-gray-200 dark:border-white/10">
      <div class="flex gap-8 px-6 mx-auto overflow-x-auto max-w-7xl lg:px-8 no-scrollbar">
        <button
          v-for="tab in ['resultados', 'comunidad', 'reglas', 'premios']"
          :key="tab"
          @click="switchTab(tab)"
          class="flex items-center gap-2 py-5 text-xs font-bold tracking-widest uppercase transition duration-300 border-b-2 whitespace-nowrap group"
          :class="activeTab === tab ? 'border-neon text-black dark:text-white' : 'border-transparent text-gray-500 hover:text-black dark:hover:text-gray-300'"
        >
          <i v-if="tab === 'resultados'" class="transition ph ph-list-numbers group-hover:text-neon"></i>
          <i v-if="tab === 'comunidad'" class="transition ph ph-users group-hover:text-neon"></i>
          <i v-if="tab === 'reglas'" class="transition ph ph-book-open group-hover:text-neon"></i>
          <i v-if="tab === 'premios'" class="transition ph ph-trophy group-hover:text-neon"></i>
          {{ tab }}
        </button>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 lg:px-8 py-10 min-h-[600px]">
      
      <div v-if="activeTab === 'resultados'" class="grid grid-cols-1 gap-8 animate-fade-in lg:grid-cols-12">
        
        <aside class="space-y-6 lg:col-span-4">
            <div class="brutal-card p-4 bg-white dark:bg-[#0a0a0a]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold tracking-widest text-gray-500 uppercase">Info Torneo</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-2 bg-gray-100 rounded dark:bg-white/5">
                        <span class="text-[10px] uppercase font-bold text-gray-500">ID Pública</span>
                        <span class="font-mono text-sm font-bold">{{ tournamentData.id || '---' }}</span>
                    </div>
                    <button @click="copyObsLink" class="w-full py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-bold uppercase btn-skew flex items-center justify-center gap-2 group">
                        <span class="flex items-center gap-2 btn-content"><i class="text-lg ph-bold ph-broadcast"></i> Copiar Tabla OBS</span>
                    </button>
                    <p class="text-[9px] text-center text-gray-400">*El link copia los filtros seleccionados.</p>
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
                            <tr v-if="ranking.length === 0 && !isLoading">
                                <td colspan="5" class="py-12 text-center text-gray-500">No hay datos disponibles con los filtros actuales.</td>
                            </tr>
                        </tbody>
                    </table>
                  </div>
            </div>
        </div>
      </div>

      <div v-show="activeTab === 'comunidad'" class="space-y-6 animate-fade-in">
        
        <div class="w-full brutal-card p-4 bg-white dark:bg-[#0a0a0a] border-l-4 transition-all duration-300 flex flex-col sm:flex-row items-center justify-between gap-4"
             :class="isConnected ? 'border-l-green-500 shadow-[0_0_20px_rgba(34,197,94,0.1)]' : 'border-l-red-500'">
            
            <div class="flex items-center gap-4">
                <div class="p-3 transition-colors border rounded-lg bg-gray-50 dark:bg-white/5"
                     :class="isConnected ? 'border-green-500 text-green-500' : 'border-red-500 text-red-500'">
                    <i class="text-2xl ph-fill" :class="isConnected ? 'ph-broadcast' : 'ph-wifi-slash'"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold tracking-widest text-gray-500 uppercase">Estado del Viewer</h4>
                    <div class="flex flex-col gap-1">
                      <div class="flex items-center gap-2 text-xl font-black uppercase font-display" :class="isConnected ? 'text-green-500' : 'text-red-500'">
                          {{ isConnected ? 'CONECTADO AL DROP SERVER' : 'DESCONECTADO' }}
                          <span v-if="isConnected" class="relative flex w-3 h-3">
                            <span class="absolute inline-flex w-full h-full bg-green-400 rounded-full opacity-75 animate-ping"></span>
                            <span class="relative inline-flex w-3 h-3 bg-green-500 rounded-full"></span>
                          </span>
                      </div>
                      <span class="text-xs font-bold text-gray-400">
                        Quédate viendo el stream aquí para ganar puntos.
                      </span>
                    </div>
                </div>
            </div>

            <div v-if="isConnected" class="flex items-center gap-3 px-5 py-2 text-white bg-green-500 btn-skew">
                <div class="flex items-center gap-2 text-sm font-bold uppercase btn-content">
                    <i class="ph-fill ph-coin-vertical animate-bounce"></i>
                    <span>Sumando Puntos</span>
                </div>
            </div>
            <div v-else class="px-3 py-1 font-mono text-xs text-red-500 rounded bg-red-500/10">
                Sin conexión al servidor de puntos
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[70vh]">
          <div class="h-full lg:col-span-9">
            <div class="w-full h-full p-0 overflow-hidden bg-black border-0 brutal-card">
              <div id="twitch-embed" class="w-full h-full"></div>
            </div>
          </div>
          <div class="flex flex-col h-full gap-4 lg:col-span-3">
             <div class="flex-1 brutal-card bg-white dark:bg-[#0a0a0a] relative overflow-hidden flex flex-col">
                <div class="flex justify-between p-2 text-xs font-bold text-gray-500 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                   <span>Chat en Vivo</span>
                </div>
                <div class="flex-1 overflow-hidden bg-gray-100 dark:bg-black/50">
                   <iframe
                      v-if="twitchChatUrl"
                      :src="twitchChatUrl"
                      frameborder="0"
                      scrolling="yes"
                      height="100%"
                      width="100%"
                      class="w-full h-full"
                   ></iframe>
                </div>
             </div>
          </div>
        </div>
      </div>

      <div v-show="activeTab === 'reglas'" class="animate-fade-in">
        <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
          
          <div v-if="Number(tournamentData.id) === 7">
             <div class="pb-4 mb-8 border-b border-gray-700">
                 <h2 class="text-3xl font-black italic uppercase font-display text-[var(--rankit-neon)] drop-shadow-lg">Reglas Oficiales</h2>
             </div>

             <div class="grid grid-cols-1 gap-12 md:grid-cols-2">
                 <div>
                     <h3 class="mb-6 text-xl font-bold text-black uppercase dark:text-white font-display text-[var(--rankit-neon)] border-b border-gray-700 pb-2">
                        Reglas Generales
                     </h3>
                     <ul class="space-y-4 text-sm font-bold text-gray-700 list-none dark:text-gray-300">
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-green-500 ph-fill ph-check-circle"></i> Todo es con construcción</li>
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-green-500 ph-fill ph-check-circle"></i> Quitar el anónimo</li>
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-red-500 ph-fill ph-prohibit"></i> No usar sniper</li>
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-red-500 ph-fill ph-prohibit"></i> No carros</li>
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-red-500 ph-fill ph-prohibit"></i> No usar el arma de Rayos</li>
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-red-500 ph-fill ph-prohibit"></i> No usar medallones de los jefes</li>
                         <li class="flex items-center gap-3 p-2 transition rounded hover:bg-white/5"><i class="text-xl text-red-500 ph-fill ph-prohibit"></i> No utilizar la varita de la tormenta</li>
                         
                         <li class="flex items-center gap-3 p-4 mt-4 text-red-500 border rounded bg-red-500/10 border-red-500/20">
                             <i class="text-2xl ph-fill ph-warning-octagon"></i> 
                             <span class="text-lg">No matar ni hacerse daño hasta la 7ma zona</span>
                         </li>
                     </ul>
                 </div>

                 <div>
                     <h3 class="mb-6 text-xl font-bold text-black uppercase dark:text-white font-display text-[var(--rankit-neon)] border-b border-gray-700 pb-2">
                        Formato y Puntuación
                     </h3>
                     
                     <div class="space-y-6">
                         <div class="p-6 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 relative overflow-hidden group hover:border-[var(--rankit-neon)] transition">
                             <div class="absolute top-0 right-0 p-2 transition opacity-10 group-hover:opacity-20">
                                <i class="text-6xl ph-fill ph-user"></i>
                             </div>
                             <h4 class="mb-1 text-xl font-bold text-white uppercase">Solitario</h4>
                             <p class="mb-4 font-mono text-xs tracking-widest text-gray-400 uppercase">4 Partidas</p>
                             <ul class="space-y-2 text-sm">
                                 <li class="flex items-center justify-between pb-1 border-b border-white/5"><span>Por Kill</span> <span class="font-bold text-[var(--rankit-neon)] bg-black/30 px-2 py-0.5 rounded">1 pt</span></li>
                                 <li class="flex items-center justify-between pb-1 border-b border-white/5"><span>Por Posición</span> <span class="font-bold text-[var(--rankit-neon)] bg-black/30 px-2 py-0.5 rounded">1 pt</span></li>
                                 <li class="flex items-center justify-between pt-1"><span class="font-bold text-yellow-400">Victoria Royale</span> <span class="font-bold text-black bg-yellow-400 px-2 py-0.5 rounded shadow-lg shadow-yellow-500/50">5 pts</span></li>
                             </ul>
                         </div>

                         <div class="relative p-6 overflow-hidden transition border border-gray-200 bg-gray-50 dark:bg-white/5 rounded-xl dark:border-white/10 group hover:border-green-500">
                             <div class="absolute top-0 right-0 p-2 transition opacity-10 group-hover:opacity-20">
                                <i class="text-6xl ph-fill ph-users"></i>
                             </div>
                             <h4 class="mb-1 text-xl font-bold text-white uppercase">Duos Random</h4>
                             <p class="mb-4 font-mono text-xs tracking-widest text-gray-400 uppercase">2 Partidas</p>
                             <p class="flex items-center gap-2 text-sm font-bold text-green-400">
                                <i class="ph-fill ph-gift"></i> Premio a los ganadores de cada partida
                             </p>
                         </div>

                         <div class="relative p-6 overflow-hidden transition border border-gray-200 bg-gray-50 dark:bg-white/5 rounded-xl dark:border-white/10 group hover:border-purple-500">
                             <div class="absolute top-0 right-0 p-2 transition opacity-10 group-hover:opacity-20">
                                <i class="text-6xl ph-fill ph-users-three"></i>
                             </div>
                             <h4 class="mb-1 text-xl font-bold text-white uppercase">Trios Random</h4>
                             <p class="mb-4 font-mono text-xs tracking-widest text-gray-400 uppercase">1 Partida</p>
                             <p class="flex items-center gap-2 text-sm font-bold text-purple-400">
                                <i class="ph-fill ph-crown"></i> Ganador de la partida se lo lleva todo
                             </p>
                         </div>
                     </div>
                 </div>
             </div>
          </div>

          <div v-else>
              <h3 class="mb-4 text-2xl font-bold text-black uppercase dark:text-white font-display">Reglas Generales</h3>
              <ul class="pl-5 space-y-2 text-sm text-gray-600 list-disc dark:text-gray-400">
                  <li>El uso de hacks o scripts resultará en descalificación inmediata.</li>
                  <li>Los equipos deben estar presentes en el lobby 10 minutos antes.</li>
                  <li>Las repeticiones deben guardarse por 24 horas.</li>
              </ul>
          </div>
        </div>
      </div>

       <div v-if="activeTab === 'premios'" class="animate-fade-in">
        <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
            
            <div class="mb-12 text-center">
                <h3 class="text-5xl font-black font-display text-[var(--rankit-neon)] uppercase mb-2 tracking-tight drop-shadow-[0_0_15px_rgba(191,0,255,0.5)]">
                    <i class="mr-2 ph-fill ph-gift"></i> SORTEOS BELLZCUP
                </h3>
                <p class="text-lg font-bold tracking-widest text-gray-400 uppercase">
                    Exclusivo para espectadores
                </p>
            </div>

            <div class="grid grid-cols-1 gap-12 lg:grid-cols-2">
                <div class="flex flex-col justify-center gap-6">
                    <div class="relative p-6 overflow-hidden border border-yellow-500/30 rounded-xl bg-yellow-500/10 group">
                         <div class="absolute top-0 right-0 p-4 text-6xl transition duration-500 text-yellow-500/20 group-hover:scale-110">
                            <i class="ph-fill ph-trophy"></i>
                         </div>
                         <h4 class="mb-2 text-3xl font-black text-white uppercase font-display">6 Ganadores</h4>
                         <p class="mb-4 text-sm font-bold tracking-widest text-yellow-500 uppercase">Premios a elección</p>
                         <ul class="space-y-3 text-lg font-bold text-gray-300">
                            <li class="flex items-center gap-3"><i class="text-green-500 ph-fill ph-money"></i> $100.00 MXN en efectivo</li>
                            <li class="flex items-center gap-3"><i class="text-purple-500 ph-fill ph-mask-happy"></i> Skins de Fortnite (valor equivalente)</li>
                            <li class="flex items-center gap-3"><i class="text-blue-500 ph-fill ph-gavel"></i> Picos de Fortnite (valor equivalente)</li>
                         </ul>
                         <p class="mt-4 text-xs text-gray-500">*Los items del juego no deben superar el valor del premio en efectivo.</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center w-12 h-12 text-xl font-black text-black bg-white rounded-full shrink-0 font-display">1</div>
                        <div>
                            <h5 class="text-xl font-bold text-white uppercase">Mira el Stream</h5>
                            <p class="text-sm text-gray-400">Ve a la pestaña <strong>"Comunidad"</strong> en esta página y quédate viendo el directo. No cierres la pestaña.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center w-12 h-12 text-xl font-black text-white rounded-full bg-[var(--rankit-neon)] shrink-0 font-display">2</div>
                        <div>
                            <h5 class="text-xl font-bold text-white uppercase">Acumula Puntos</h5>
                            <p class="text-sm text-gray-400">El sistema te dará puntos automáticamente cada minuto que estés conectado. ¡Mientras más tiempo, más chances!</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center w-12 h-12 text-xl font-black text-white bg-green-500 rounded-full shrink-0 font-display">3</div>
                        <div>
                            <h5 class="text-xl font-bold text-white uppercase">Reclama tu Premio</h5>
                            <p class="text-sm text-gray-400">Si resultas ganador en el sorteo, contáctanos inmediatamente:</p>
                            <div class="flex flex-wrap gap-3 mt-2">
                                <a href="https://instagram.com/rankit.pro" target="_blank" class="px-3 py-1 text-xs font-bold text-black uppercase bg-white rounded hover:bg-gray-200">
                                    <i class="ph-bold ph-instagram-logo"></i> @rankit.pro
                                </a>
                                <span class="px-3 py-1 text-xs font-bold text-white uppercase border rounded cursor-default border-white/20">
                                    <i class="ph-bold ph-envelope"></i> Espera nuestro correo
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
      </div>
    </main>

    <div class="fixed bottom-0 w-full bg-white/90 dark:bg-[#0B0C15]/90 backdrop-blur-md border-t border-gray-200 dark:border-white/10 p-4 z-50 lg:hidden">
      <div class="flex items-center justify-center gap-3">
         <span class="relative flex w-3 h-3">
            <span class="absolute inline-flex w-full h-full bg-green-400 rounded-full opacity-75 animate-ping"></span>
            <span class="relative inline-flex w-3 h-3 bg-green-500 rounded-full"></span>
         </span>
         <div class="text-xs font-bold text-center text-black uppercase dark:text-white">
            Mantente viendo el directo para seguir ganando
         </div>
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

/* NUEVOS ESTILOS PARA TRANSICIONES DE TOASTS */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.5s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(30px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateY(-30px);
}
</style>