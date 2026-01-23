<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed, onUnmounted, watch } from 'vue'
import { useRankitSocket } from '@/Composables/useRankitSocket'

// CORRECCIÓN TS: Uso de sintaxis genérica para tipado correcto de Arrays
const props = defineProps<{
  tournament?: any;
  bracketRounds?: any[];
  sponsors?: any[];
  prizeDistribution?: any[];
  targetDate?: number;

  totalPoints?: number;
  viewerStartAt?: number;

  laravelVersion?: string;
  phpVersion?: string;
  canLogin?: boolean;
  canRegister?: boolean;
}>()

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
 * LOGICA DEL TORNEO
 */
const activeTab = ref('bracket')

// 1. OBTENER USUARIO ANTES DEL SOCKET
const page = usePage()
const user = page.props.auth?.user as any

// ===== Viewer Points (WEBSOCKET INTEGRATION) =====
// CORREGIDO: Pasamos user?.id en lugar de props.tournament?.id
const { connect: connectSocket, disconnect: disconnectSocket, isConnected } = useRankitSocket(
    'community', 
    user?.id, 
    { 
        autoConnect: false,
        manageVisibility: false 
    }
)

// DEBUG: Watcher para ver cuándo cambia realmente el estado
watch(isConnected, (newVal) => {
  console.log('%c[RankitSocket] Estado isConnected cambió a:', 'color: orange; font-weight: bold;', newVal)
})

const switchTab = (tab: string) => {
  activeTab.value = tab

  // Lógica de conexión basada en el Tab
  if (tab === 'comunidad') {
    console.log('%c[RankitSocket] Tab Comunidad activado. Iniciando conexión...', 'color: cyan; font-weight: bold;')
    
    if (!user) {
        console.warn('[RankitSocket] Usuario no autenticado. No se conectará al socket.')
    }

    connectSocket()
  } else {
    if (isConnected.value) {
      console.log('%c[RankitSocket] Saliendo de Comunidad. Desconectando...', 'color: gray;')
    }
    disconnectSocket()
  }
}

const twitchChannel = props.tournament?.twitch_channel ?? 'Jelty'
const tournamentTitle = 'bellzCup' 

// Tiempo
const timeLeft = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 })
const targetDate = props.targetDate ?? (new Date().getTime() + 2 * 24 * 60 * 60 * 1000)
const watchProgress = ref(65)

// ==============================
// REFERIDOS (MODALES + REDEEM)
// ==============================
// Nota: user ya está definido arriba como 'user', pero mantenemos 'me' para compatibilidad con tu template si lo usas
const me = user 

const showInviteModal = ref(false)
const showCodeModal = ref(false)

const myCode = computed(() => (me?.id ? `${me.id}rankit` : ''))
const inviteLink = computed(() => `https://rankit.pro/?ref=${myCode.value}`)

const redeemForm = useForm({
  code: '',
})

function openInvite() {
  showInviteModal.value = true
}

function openCode() {
  showCodeModal.value = true
  redeemForm.clearErrors()
}

function submitCode() {
  redeemForm.post(route('bellzcup.referidos.redeem'), {
    preserveScroll: true,
    onSuccess: () => {
      showCodeModal.value = false
      redeemForm.reset()
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
  ta.value = text
  ta.setAttribute('readonly', '')
  ta.style.position = 'fixed'
  ta.style.top = '-1000px'
  ta.style.left = '-1000px'
  document.body.appendChild(ta)
  ta.select()
  try { document.execCommand('copy') } catch (e) {}
  document.body.removeChild(ta)
}

// Control de visibilidad para pausar socket si minimizan
const handleVisibilityChange = () => {
  if (document.hidden) {
    console.log('[RankitSocket] Documento oculto (minimizado). Desconectando...')
    disconnectSocket()
  } else {
    // Solo reconectar si estamos en el tab correcto
    if (activeTab.value === 'comunidad') {
      console.log('[RankitSocket] Documento visible. Reconectando...')
      connectSocket()
    }
  }
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
  
  // Timer Logic
  const timerInterval = setInterval(() => {
    const now = new Date().getTime()
    const distance = targetDate - now
    if (distance < 0) {
      clearInterval(timerInterval)
      return
    }
    timeLeft.value.days = Math.floor(distance / (1000 * 60 * 60 * 24))
    timeLeft.value.hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
    timeLeft.value.minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))
    timeLeft.value.seconds = Math.floor((distance % (1000 * 60)) / 1000)
  }, 1000)

  // Twitch Embed
  const parentHost = window.location.hostname
  const initPlayer = () => {
    const embed = document.getElementById('twitch-embed')
    if (!embed) return
    // @ts-ignore
    if (window.Twitch && !embed.firstChild) {
      // @ts-ignore
      new window.Twitch.Player('twitch-embed', {
        channel: twitchChannel,
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

  // Listeners de Visibilidad
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  disconnectSocket() // Asegurar desconexión al salir de la página
})
</script>

<template>
  <Head title="bellzCup - Rankit">
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
        <span class="text-3xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">Rankit</span>
      </Link>

      <div class="flex items-center gap-4">
        <!-- Indicador de Puntos (Opcional, para debug visual) -->
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

    <!-- HERO SECTION -->
    <header class="relative min-h-[600px] h-auto flex items-end pt-24 overflow-hidden group pb-20 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px]">
      <div class="absolute inset-0 z-0 pointer-events-none">
        <img v-if="props.tournament?.hero_image" :src="props.tournament.hero_image" class="w-full h-full object-cover opacity-20 dark:opacity-30 transform scale-105 group-hover:scale-110 transition duration-[30s] ease-linear grayscale mix-blend-multiply dark:mix-blend-overlay" />
        <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-gray-50/80 to-transparent dark:from-[#050505] dark:via-[#050505]/80 dark:to-transparent"></div>
      </div>

      <div class="relative z-10 flex flex-col w-full gap-8 px-6 mx-auto max-w-7xl lg:px-8">
        <div class="flex flex-wrap items-center gap-3 animate-fade-in-up">
          <span class="bg-red-600/90 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-[0_0_20px_rgba(220,38,38,0.6)] animate-pulse flex items-center gap-2 btn-skew">
             <span class="flex items-center gap-2 btn-content"><span class="w-1.5 h-1.5 bg-white rounded-full"></span> {{ props.tournament?.status ?? 'En Vivo' }}</span>
          </span>
          <span class="bg-white/10 border border-black/10 dark:border-white/10 text-black dark:text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 cursor-default brutal-card">
            <i class="ph ph-game-controller text-neon"></i>
            {{ props.tournament?.game ?? 'Valorant' }}
          </span>
        </div>

        <div class="flex flex-col items-end justify-between gap-10 lg:flex-row">
          <div class="relative max-w-3xl delay-100 animate-fade-in-up">
            <h1 class="mb-4 text-5xl font-black leading-none tracking-tight text-black uppercase md:text-7xl font-display dark:text-white">
              {{ tournamentTitle }} <br />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-purple-600">
                SEASON 1
              </span>
            </h1>
            <p class="max-w-xl py-1 pl-6 text-lg font-light text-gray-600 border-l-4 dark:text-gray-400 border-neon">
              El escenario definitivo. 16 equipos, un solo trofeo y la gloria eterna en el torneo más grande de LATAM.
            </p>
          </div>

          <!-- Countdown & CTA -->
          <div class="flex flex-col w-full gap-4 delay-200 lg:w-auto sm:flex-row lg:flex-col animate-fade-in-up">
            <div class="brutal-card p-4 flex flex-col items-center bg-white dark:bg-[#0a0a0a]">
              <div class="text-[10px] text-neon uppercase font-bold mb-2 tracking-widest">Inscripciones Cierran En</div>
              <div class="flex items-center gap-4 font-mono">
                <div class="text-center">
                  <div class="text-3xl font-black leading-none text-black dark:text-white">{{ timeLeft.days }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Días</div>
                </div>
                <span class="font-bold text-gray-400">:</span>
                <div class="text-center">
                  <div class="text-3xl font-black leading-none text-black dark:text-white">{{ timeLeft.hours }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Hrs</div>
                </div>
                <span class="font-bold text-gray-400">:</span>
                <div class="text-center">
                  <div class="text-3xl font-black leading-none text-black dark:text-white">{{ timeLeft.minutes }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Min</div>
                </div>
              </div>
            </div>
            <!-- Total Points -->
            <div class="brutal-card px-4 py-3 text-center bg-white dark:bg-[#0a0a0a]">
              <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Total puntos</div>
              <div class="text-3xl font-black text-black font-display dark:text-white">
                {{ (props.totalPoints ?? 0).toLocaleString?.() ?? (props.totalPoints ?? 0) }}
              </div>
              <div class="text-[10px] text-gray-500">Rifa bellzCup</div>
            </div>

            <div class="flex flex-1 gap-3">
              <!-- INGRESAR CÓDIGO -->
              <button
                type="button"
                @click="openCode"
                class="brutal-card px-4 py-2 text-center flex-1 flex flex-col justify-center bg-white dark:bg-[#0a0a0a]"
              >
                <div class="text-[9px] text-gray-500 uppercase font-bold">Acceso</div>
                <div class="text-sm font-bold font-display">INGRESAR CÓDIGO</div>
              </button>

              <!-- INVITAR AMIGO -->
              <button
                type="button"
                @click="openInvite"
                class="flex-1 px-6 py-3 text-sm font-bold tracking-wider uppercase btn-skew"
              >
                <span class="btn-content">INVITAR AMIGO</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sponsors Marquee -->
      <div class="absolute bottom-0 z-20 flex items-center w-full overflow-hidden border-t h-14 bg-white/50 dark:bg-black/80 border-black/5 dark:border-white/5 backdrop-blur-md">
        <div class="flex items-center gap-16 pl-16 animate-marquee whitespace-nowrap">
          <span v-for="i in 10" :key="i" class="font-bold tracking-widest text-gray-400 uppercase opacity-50">SPONSOR {{ i }}</span>
        </div>
      </div>
    </header>

    <!-- Navigation Tabs -->
    <div class="sticky top-20 z-40 bg-white/90 dark:bg-[#050505]/90 backdrop-blur-lg border-b border-gray-200 dark:border-white/10">
      <div class="flex gap-8 px-6 mx-auto overflow-x-auto max-w-7xl lg:px-8 no-scrollbar">
        <button
          v-for="tab in ['bracket', 'detalles', 'comunidad', 'reglas']"
          :key="tab"
          @click="switchTab(tab)"
          class="flex items-center gap-2 py-5 text-xs font-bold tracking-widest uppercase transition duration-300 border-b-2 whitespace-nowrap group"
          :class="activeTab === tab ? 'border-neon text-black dark:text-white' : 'border-transparent text-gray-500 hover:text-black dark:hover:text-gray-300'"
        >
          <i v-if="tab === 'bracket'" class="transition ph ph-tree-structure group-hover:text-neon"></i>
          <i v-if="tab === 'detalles'" class="transition ph ph-info group-hover:text-neon"></i>
          <i v-if="tab === 'comunidad'" class="transition ph ph-users group-hover:text-neon"></i>
          <i v-if="tab === 'reglas'" class="transition ph ph-book-open group-hover:text-neon"></i>
          {{ tab }}
        </button>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 lg:px-8 py-10 min-h-[600px]">
      <!-- === BRACKET === -->
      <div v-if="activeTab === 'bracket'" class="space-y-10 animate-fade-in">
        <div class="flex flex-col items-end justify-between gap-4 md:flex-row">
          <div>
            <h2 class="text-3xl font-bold text-black uppercase font-display dark:text-white">Playoffs Stage</h2>
            <p class="mt-1 font-mono text-sm text-gray-500">Haz clic en un match para ver estadísticas detalladas.</p>
          </div>
          <div class="flex gap-4 p-2 overflow-x-auto text-xs bg-white border border-gray-200 rounded-lg dark:bg-black/30 dark:border-white/5">
            <span class="flex items-center gap-2 font-bold text-gray-500 dark:text-gray-400 whitespace-nowrap"><span class="w-2 h-2 bg-gray-400 rounded-full"></span> Finalizado</span>
            <span class="flex items-center gap-2 font-bold text-red-500 whitespace-nowrap"><span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span> En Vivo</span>
            <span class="flex items-center gap-2 font-bold text-neon whitespace-nowrap"><i class="ph ph-robot"></i> AI Prediction</span>
          </div>
        </div>

        <div class="pb-12 overflow-x-auto custom-scroll">
          <div class="flex gap-16 px-4 pt-8 min-w-max">
            <div v-for="(round, rIndex) in (props.bracketRounds || [{name:'Round 1', matches:[{id:1, p1:'Team A', p2:'Team B', status:'live'}]}])" :key="rIndex" class="relative flex flex-col justify-around gap-8">
              <!-- CORRECCIÓN TS: round es any, no dará error -->
              <h3 class="absolute -top-10 left-0 w-full text-center text-xs font-bold text-neon uppercase tracking-[0.2em] bg-neon/5 py-1 rounded">{{ round.name }}</h3>
              
              <div v-for="match in round.matches" :key="match.id" class="relative group">
                <!-- Connectors -->
                <div v-if="!match.isFinal" class="hidden md:block absolute -right-8 top-1/2 w-8 h-0.5 bg-gray-300 dark:bg-gray-800"></div>
                
                <!-- Match Card -->
                <div class="w-64 md:w-72 brutal-card transition-all duration-300 relative bg-white dark:bg-[#0a0a0a]"
                     :class="{ 'border-red-500 dark:border-red-500 shadow-[0_0_15px_rgba(220,38,38,0.3)]': match.status === 'live' }">
                  <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 bg-gray-50 dark:bg-white/5 dark:border-white/5">
                    <div class="flex gap-2">
                      <span v-if="match.status === 'live'" class="bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded animate-pulse">LIVE</span>
                      <span v-else class="text-[10px] text-gray-500 uppercase font-bold">{{ match.status ?? 'Pending' }}</span>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] text-neon"><i class="ph ph-brain"></i> 65% Win Prob</div>
                  </div>

                  <div class="p-2 space-y-1">
                    <div class="flex items-center justify-between p-2 transition rounded hover:bg-gray-100 dark:hover:bg-white/5">
                      <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded bg-gray-200 dark:bg-gray-800 flex items-center justify-center text-[10px] font-bold border border-black/10 dark:border-white/10">A</div>
                        <span class="text-xs font-bold text-black dark:text-white truncate max-w-[120px]">{{ match.p1 }}</span>
                      </div>
                      <span class="font-mono font-bold text-black dark:text-white">2</span>
                    </div>
                    <div class="flex items-center justify-between p-2 transition rounded hover:bg-gray-100 dark:hover:bg-white/5">
                      <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded bg-gray-200 dark:bg-gray-800 flex items-center justify-center text-[10px] font-bold border border-black/10 dark:border-white/10">B</div>
                        <span class="text-xs font-bold text-black dark:text-white truncate max-w-[120px]">{{ match.p2 }}</span>
                      </div>
                      <span class="font-mono font-bold text-black dark:text-white">1</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- === DETALLES === -->
      <div v-if="activeTab === 'detalles'" class="grid grid-cols-1 gap-8 animate-fade-in lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-8">
          <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
            <h3 class="mb-4 text-2xl font-bold text-black uppercase dark:text-white font-display">Información del Evento</h3>
            <p class="mb-8 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
              Bienvenidos a la 1ra edición de la bellzCup. Este torneo reúne a los mejores equipos amateurs y semi-pro de la región.
            </p>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
              <div class="p-4 text-center transition border border-gray-200 dark:border-white/10 group hover:border-neon">
                <i class="mb-2 text-xl ph ph-users text-neon"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Formato</div>
                <div class="font-bold text-black dark:text-white">5v5 Draft</div>
              </div>
              <div class="p-4 text-center transition border border-gray-200 dark:border-white/10 group hover:border-neon">
                <i class="mb-2 text-xl ph ph-desktop text-neon"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Plataforma</div>
                <div class="font-bold text-black dark:text-white">PC / Win 11</div>
              </div>
              <div class="p-4 text-center transition border border-gray-200 dark:border-white/10 group hover:border-neon">
                <i class="mb-2 text-xl ph ph-globe text-neon"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Región</div>
                <div class="font-bold text-black dark:text-white">LATAM Norte</div>
              </div>
              <div class="p-4 text-center transition border border-gray-200 dark:border-white/10 group hover:border-neon">
                <i class="mb-2 text-xl ph ph-shield-check text-neon"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Anti-Cheat</div>
                <div class="font-bold text-black dark:text-white">Requerido</div>
              </div>
            </div>
          </div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
          <div class="brutal-card p-6 bg-white dark:bg-[#0a0a0a] border-neon">
             <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-lg font-bold text-black uppercase font-display dark:text-white">Viewer Drops</h3>
                  <p class="text-[10px] text-neon font-bold uppercase tracking-wider">Temporada 5</p>
                </div>
                <i class="text-2xl ph ph-gift text-neon animate-bounce"></i>
             </div>
             <div class="space-y-4">
                <div>
                  <div class="flex justify-between mb-2 text-xs font-bold text-black dark:text-white">
                    <span>Nivel 3</span>
                    <span>{{ watchProgress }} / 100 XP</span>
                  </div>
                  <div class="w-full h-3 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-800">
                    <div class="h-3 rounded-full bg-neon" :style="`width: ${watchProgress}%`"></div>
                  </div>
                </div>
                <button class="w-full mt-6 bg-[#6441a5] text-white font-bold py-2 rounded-none btn-skew text-xs hover:bg-[#503484] transition flex items-center justify-center gap-2">
                  <span class="flex items-center gap-2 btn-content"><i class="fab fa-twitch"></i> Conectar Twitch</span>
                </button>
             </div>
          </div>
        </aside>
      </div>

      <!-- === COMUNIDAD (AQUÍ ES DONDE SUMAN PUNTOS) === -->
      <div v-show="activeTab === 'comunidad'" class="space-y-6 animate-fade-in">
        
        <!-- NEW STATUS BAR -->
        <div class="w-full brutal-card p-4 bg-white dark:bg-[#0a0a0a] border-l-4 transition-all duration-300 flex flex-col sm:flex-row items-center justify-between gap-4"
             :class="isConnected ? 'border-l-green-500 shadow-[0_0_20px_rgba(34,197,94,0.1)]' : 'border-l-red-500'">
            
            <div class="flex items-center gap-4">
                <div class="p-3 transition-colors border rounded-lg bg-gray-50 dark:bg-white/5"
                     :class="isConnected ? 'border-green-500 text-green-500' : 'border-red-500 text-red-500'">
                    <i class="text-2xl ph-fill" :class="isConnected ? 'ph-broadcast' : 'ph-wifi-slash'"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold tracking-widest text-gray-500 uppercase">Estado del Viewer</h4>
                    <div class="flex items-center gap-2 text-xl font-black uppercase font-display" :class="isConnected ? 'text-green-500' : 'text-red-500'">
                        {{ isConnected ? 'CONECTADO AL DROP SERVER' : 'DESCONECTADO' }}
                        <span v-if="isConnected" class="relative flex w-3 h-3">
                          <span class="absolute inline-flex w-full h-full bg-green-400 rounded-full opacity-75 animate-ping"></span>
                          <span class="relative inline-flex w-3 h-3 bg-green-500 rounded-full"></span>
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
                   <span class="flex items-center gap-1 text-green-500"><span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> 12.4k</span>
                </div>
                <!-- Chat Iframe Placeholder -->
                <div class="flex items-center justify-center flex-1 text-xs text-gray-500 bg-gray-100 dark:bg-black/50">
                   Chat Widget Placeholder
                </div>
             </div>
          </div>
        </div>
      </div>

      <!-- === REGLAS === -->
      <div v-show="activeTab === 'reglas'" class="animate-fade-in">
        <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
          <h3 class="mb-4 text-2xl font-bold text-black uppercase dark:text-white font-display">Reglas Oficiales</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">Aquí puedes pintar reglas desde DB (Markdown/HTML sanitizado).</p>
        </div>
      </div>
    </main>

    <!-- Mobile CTA -->
    <div class="fixed bottom-0 w-full bg-white/90 dark:bg-[#0B0C15]/90 backdrop-blur-md border-t border-gray-200 dark:border-white/10 p-4 z-50 lg:hidden">
      <div class="flex items-center justify-between">
        <div>
          <!-- CHANGE: Neon City Cup -> bellzCup -->
          <div class="text-sm font-bold text-black dark:text-white">{{ tournamentTitle }}</div>
          <div class="text-[10px] text-green-500">Inscripciones Abiertas</div>
        </div>
        <button class="px-6 py-2 text-sm font-bold text-white bg-neon btn-skew">
          <span class="btn-content">Inscribirse</span>
        </button>
      </div>
    </div>
    
    <!-- MODALES DE REFERIDOS -->
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
</style>