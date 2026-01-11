<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

// CORRECCIÓN TS: Uso de sintaxis genérica para tipado correcto de Arrays
const props = defineProps<{
  tournament?: any;
  bracketRounds?: any[];
  sponsors?: any[];
  prizeDistribution?: any[];
  targetDate?: number;
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
const switchTab = (tab: string) => (activeTab.value = tab)
const twitchChannel = props.tournament?.twitch_channel ?? 'Jelty'

// Título forzado
const tournamentTitle = 'bellzCup' 

// Tiempo
const timeLeft = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 })
const targetDate = props.targetDate ?? (new Date().getTime() + 2 * 24 * 60 * 60 * 1000)
const watchProgress = ref(65)

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

      <div class="relative z-10 w-full max-w-7xl mx-auto px-6 lg:px-8 flex flex-col gap-8">
        <div class="flex flex-wrap items-center gap-3 animate-fade-in-up">
          <span class="bg-red-600/90 text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider shadow-[0_0_20px_rgba(220,38,38,0.6)] animate-pulse flex items-center gap-2 btn-skew">
             <span class="btn-content flex items-center gap-2"><span class="w-1.5 h-1.5 bg-white rounded-full"></span> {{ props.tournament?.status ?? 'En Vivo' }}</span>
          </span>
          <span class="bg-white/10 border border-black/10 dark:border-white/10 text-black dark:text-white px-3 py-1 text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 cursor-default brutal-card">
            <i class="ph ph-game-controller text-neon"></i>
            {{ props.tournament?.game ?? 'Valorant' }}
          </span>
        </div>

        <div class="flex flex-col lg:flex-row items-end justify-between gap-10">
          <div class="max-w-3xl animate-fade-in-up delay-100 relative">
            <h1 class="text-5xl md:text-7xl font-display font-black text-black dark:text-white mb-4 leading-none tracking-tight uppercase">
              {{ tournamentTitle }} <br />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-purple-600">
                SEASON 1
              </span>
            </h1>
            <p class="text-gray-600 dark:text-gray-400 text-lg font-light max-w-xl border-l-4 border-neon pl-6 py-1">
              El escenario definitivo. 16 equipos, un solo trofeo y la gloria eterna en el torneo más grande de LATAM.
            </p>
          </div>

          <!-- Countdown & CTA -->
          <div class="w-full lg:w-auto flex flex-col sm:flex-row lg:flex-col gap-4 animate-fade-in-up delay-200">
            <div class="brutal-card p-4 flex flex-col items-center bg-white dark:bg-[#0a0a0a]">
              <div class="text-[10px] text-neon uppercase font-bold mb-2 tracking-widest">Inscripciones Cierran En</div>
              <div class="flex gap-4 items-center font-mono">
                <div class="text-center">
                  <div class="text-3xl font-black text-black dark:text-white leading-none">{{ timeLeft.days }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Días</div>
                </div>
                <span class="text-gray-400 font-bold">:</span>
                <div class="text-center">
                  <div class="text-3xl font-black text-black dark:text-white leading-none">{{ timeLeft.hours }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Hrs</div>
                </div>
                <span class="text-gray-400 font-bold">:</span>
                <div class="text-center">
                  <div class="text-3xl font-black text-black dark:text-white leading-none">{{ timeLeft.minutes }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Min</div>
                </div>
              </div>
            </div>

            <div class="flex gap-3 flex-1">
              <div class="brutal-card px-4 py-2 text-center flex-1 flex flex-col justify-center bg-white dark:bg-[#0a0a0a]">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Prize Pool</div>
                <div class="text-xl font-display font-bold text-green-600 dark:text-green-400">
                  ${{ (props.tournament?.prize_pool ?? 25000).toLocaleString?.() ?? (props.tournament?.prize_pool ?? 25000) }}
                </div>
              </div>

              <button class="flex-1 btn-skew py-3 px-6 text-sm font-bold tracking-wider uppercase">
                <span class="btn-content">INSCRIBIRSE</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sponsors Marquee -->
      <div class="absolute bottom-0 w-full h-14 bg-white/50 dark:bg-black/80 border-t border-black/5 dark:border-white/5 backdrop-blur-md flex items-center z-20 overflow-hidden">
        <div class="flex items-center gap-16 animate-marquee whitespace-nowrap pl-16">
          <span v-for="i in 10" :key="i" class="text-gray-400 font-bold uppercase tracking-widest opacity-50">SPONSOR {{ i }}</span>
        </div>
      </div>
    </header>

    <!-- Navigation Tabs -->
    <div class="sticky top-20 z-40 bg-white/90 dark:bg-[#050505]/90 backdrop-blur-lg border-b border-gray-200 dark:border-white/10">
      <div class="max-w-7xl mx-auto px-6 lg:px-8 flex gap-8 overflow-x-auto no-scrollbar">
        <button
          v-for="tab in ['bracket', 'detalles', 'comunidad', 'reglas']"
          :key="tab"
          @click="switchTab(tab)"
          class="py-5 text-xs font-bold uppercase tracking-widest border-b-2 transition duration-300 whitespace-nowrap flex items-center gap-2 group"
          :class="activeTab === tab ? 'border-neon text-black dark:text-white' : 'border-transparent text-gray-500 hover:text-black dark:hover:text-gray-300'"
        >
          <i v-if="tab === 'bracket'" class="ph ph-tree-structure group-hover:text-neon transition"></i>
          <i v-if="tab === 'detalles'" class="ph ph-info group-hover:text-neon transition"></i>
          <i v-if="tab === 'comunidad'" class="ph ph-users group-hover:text-neon transition"></i>
          <i v-if="tab === 'reglas'" class="ph ph-book-open group-hover:text-neon transition"></i>
          {{ tab }}
        </button>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 lg:px-8 py-10 min-h-[600px]">
      <!-- === BRACKET === -->
      <div v-if="activeTab === 'bracket'" class="animate-fade-in space-y-10">
        <div class="flex flex-col md:flex-row justify-between items-end gap-4">
          <div>
            <h2 class="text-3xl font-display font-bold text-black dark:text-white uppercase">Playoffs Stage</h2>
            <p class="text-gray-500 text-sm mt-1 font-mono">Haz clic en un match para ver estadísticas detalladas.</p>
          </div>
          <div class="flex gap-4 text-xs bg-white dark:bg-black/30 p-2 rounded-lg border border-gray-200 dark:border-white/5 overflow-x-auto">
            <span class="flex items-center gap-2 text-gray-500 dark:text-gray-400 font-bold whitespace-nowrap"><span class="w-2 h-2 rounded-full bg-gray-400"></span> Finalizado</span>
            <span class="flex items-center gap-2 text-red-500 font-bold whitespace-nowrap"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> En Vivo</span>
            <span class="flex items-center gap-2 text-neon font-bold whitespace-nowrap"><i class="ph ph-robot"></i> AI Prediction</span>
          </div>
        </div>

        <div class="overflow-x-auto pb-12 custom-scroll">
          <div class="flex gap-16 min-w-max px-4 pt-8">
            <div v-for="(round, rIndex) in (props.bracketRounds || [{name:'Round 1', matches:[{id:1, p1:'Team A', p2:'Team B', status:'live'}]}])" :key="rIndex" class="flex flex-col justify-around gap-8 relative">
              <!-- CORRECCIÓN TS: round es any, no dará error -->
              <h3 class="absolute -top-10 left-0 w-full text-center text-xs font-bold text-neon uppercase tracking-[0.2em] bg-neon/5 py-1 rounded">{{ round.name }}</h3>
              
              <div v-for="match in round.matches" :key="match.id" class="relative group">
                <!-- Connectors -->
                <div v-if="!match.isFinal" class="hidden md:block absolute -right-8 top-1/2 w-8 h-0.5 bg-gray-300 dark:bg-gray-800"></div>
                
                <!-- Match Card -->
                <div class="w-64 md:w-72 brutal-card transition-all duration-300 relative bg-white dark:bg-[#0a0a0a]"
                     :class="{ 'border-red-500 dark:border-red-500 shadow-[0_0_15px_rgba(220,38,38,0.3)]': match.status === 'live' }">
                  <div class="bg-gray-50 dark:bg-white/5 px-3 py-2 flex justify-between items-center border-b border-gray-200 dark:border-white/5">
                    <div class="flex gap-2">
                      <span v-if="match.status === 'live'" class="bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded animate-pulse">LIVE</span>
                      <span v-else class="text-[10px] text-gray-500 uppercase font-bold">{{ match.status ?? 'Pending' }}</span>
                    </div>
                    <div class="flex items-center gap-1 text-[10px] text-neon"><i class="ph ph-brain"></i> 65% Win Prob</div>
                  </div>

                  <div class="p-2 space-y-1">
                    <div class="flex justify-between items-center p-2 rounded hover:bg-gray-100 dark:hover:bg-white/5 transition">
                      <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded bg-gray-200 dark:bg-gray-800 flex items-center justify-center text-[10px] font-bold border border-black/10 dark:border-white/10">A</div>
                        <span class="text-xs font-bold text-black dark:text-white truncate max-w-[120px]">{{ match.p1 }}</span>
                      </div>
                      <span class="font-mono font-bold text-black dark:text-white">2</span>
                    </div>
                    <div class="flex justify-between items-center p-2 rounded hover:bg-gray-100 dark:hover:bg-white/5 transition">
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
      <div v-if="activeTab === 'detalles'" class="animate-fade-in grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-8 space-y-8">
          <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
            <h3 class="font-bold text-2xl mb-4 text-black dark:text-white font-display uppercase">Información del Evento</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed mb-8">
              <!-- CHANGE: Neon City Cup -> bellzCup -->
              Bienvenidos a la 1ra edición de la bellzCup. Este torneo reúne a los mejores equipos amateurs y semi-pro de la región.
            </p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="p-4 border border-gray-200 dark:border-white/10 text-center group hover:border-neon transition">
                <i class="ph ph-users text-neon mb-2 text-xl"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Formato</div>
                <div class="text-black dark:text-white font-bold">5v5 Draft</div>
              </div>
              <div class="p-4 border border-gray-200 dark:border-white/10 text-center group hover:border-neon transition">
                <i class="ph ph-desktop text-neon mb-2 text-xl"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Plataforma</div>
                <div class="text-black dark:text-white font-bold">PC / Win 11</div>
              </div>
              <div class="p-4 border border-gray-200 dark:border-white/10 text-center group hover:border-neon transition">
                <i class="ph ph-globe text-neon mb-2 text-xl"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Región</div>
                <div class="text-black dark:text-white font-bold">LATAM Norte</div>
              </div>
              <div class="p-4 border border-gray-200 dark:border-white/10 text-center group hover:border-neon transition">
                <i class="ph ph-shield-check text-neon mb-2 text-xl"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Anti-Cheat</div>
                <div class="text-black dark:text-white font-bold">Requerido</div>
              </div>
            </div>
          </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
          <div class="brutal-card p-6 bg-white dark:bg-[#0a0a0a] border-neon">
             <div class="flex justify-between items-start mb-4">
                <div>
                  <h3 class="font-display font-bold text-lg text-black dark:text-white uppercase">Viewer Drops</h3>
                  <p class="text-[10px] text-neon font-bold uppercase tracking-wider">Temporada 5</p>
                </div>
                <i class="ph ph-gift text-2xl text-neon animate-bounce"></i>
             </div>
             <div class="space-y-4">
                <div>
                  <div class="flex justify-between text-xs font-bold text-black dark:text-white mb-2">
                    <span>Nivel 3</span>
                    <span>{{ watchProgress }} / 100 XP</span>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-3 overflow-hidden">
                    <div class="bg-neon h-3 rounded-full" :style="`width: ${watchProgress}%`"></div>
                  </div>
                </div>
                <button class="w-full mt-6 bg-[#6441a5] text-white font-bold py-2 rounded-none btn-skew text-xs hover:bg-[#503484] transition flex items-center justify-center gap-2">
                  <span class="btn-content flex items-center gap-2"><i class="fab fa-twitch"></i> Conectar Twitch</span>
                </button>
             </div>
          </div>
        </aside>
      </div>

      <!-- === COMUNIDAD === -->
      <div v-show="activeTab === 'comunidad'" class="animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[70vh]">
          <div class="lg:col-span-9 h-full">
            <div class="w-full h-full brutal-card overflow-hidden bg-black p-0 border-0">
              <div id="twitch-embed" class="w-full h-full"></div>
            </div>
          </div>
          <div class="lg:col-span-3 h-full flex flex-col gap-4">
             <div class="flex-1 brutal-card bg-white dark:bg-[#0a0a0a] relative overflow-hidden flex flex-col">
                <div class="p-2 text-xs font-bold text-gray-500 border-b border-gray-200 dark:border-white/10 flex justify-between bg-gray-50 dark:bg-white/5">
                   <span>Chat en Vivo</span>
                   <span class="text-green-500 flex items-center gap-1"><span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> 12.4k</span>
                </div>
                <!-- Chat Iframe Placeholder -->
                <div class="flex-1 bg-gray-100 dark:bg-black/50 flex items-center justify-center text-xs text-gray-500">
                   Chat Widget Placeholder
                </div>
             </div>
          </div>
        </div>
      </div>

      <!-- === REGLAS === -->
      <div v-show="activeTab === 'reglas'" class="animate-fade-in">
        <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
          <h3 class="font-bold text-2xl mb-4 text-black dark:text-white font-display uppercase">Reglas Oficiales</h3>
          <p class="text-gray-600 dark:text-gray-400 text-sm">Aquí puedes pintar reglas desde DB (Markdown/HTML sanitizado).</p>
        </div>
      </div>
    </main>

    <!-- Mobile CTA -->
    <div class="fixed bottom-0 w-full bg-white/90 dark:bg-[#0B0C15]/90 backdrop-blur-md border-t border-gray-200 dark:border-white/10 p-4 z-50 lg:hidden">
      <div class="flex justify-between items-center">
        <div>
          <!-- CHANGE: Neon City Cup -> bellzCup -->
          <div class="text-black dark:text-white font-bold text-sm">{{ tournamentTitle }}</div>
          <div class="text-[10px] text-green-500">Inscripciones Abiertas</div>
        </div>
        <button class="bg-neon text-white font-bold px-6 py-2 btn-skew text-sm">
          <span class="btn-content">Inscribirse</span>
        </button>
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