<script setup>
import { ref, onMounted } from 'vue'

// Si existe en tu proyecto:
// import TheNavbar from '@/Components/TheNavbar.vue'

const props = defineProps({
  tournament: Object,
  bracketRounds: Array,
  sponsors: Array,
  prizeDistribution: Array,
  targetDate: Number, // ms
})

const activeTab = ref('bracket')
const switchTab = (tab) => (activeTab.value = tab)

const twitchChannel = props.tournament?.twitch_channel ?? 'Jelty'

// --- LÓGICA DE TIEMPO (Cuenta Regresiva) ---
const timeLeft = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 })
const targetDate = props.targetDate ?? (new Date().getTime() + 2 * 24 * 60 * 60 * 1000)

// --- DATOS DE ENGAGEMENT (Drops) ---
const watchProgress = ref(65)

onMounted(() => {
  // Timer
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

  // Twitch Embed Logic
  const parentHost = window.location.hostname

  const initPlayer = () => {
  const embed = document.getElementById('twitch-embed')
  if (!embed) return

  if (window.Twitch && !embed.firstChild) {
    new window.Twitch.Player('twitch-embed', {
      channel: twitchChannel,
      width: '100%',
      height: '100%',
      parent: [parentHost.value], // ✅
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
  <div class="bg-[#0B0C15] min-h-screen font-sans text-white pb-24 overflow-x-hidden">
    <!-- <TheNavbar /> -->

    <!-- HERO SECTION -->
    <header class="relative min-h-[600px] h-auto flex items-end pt-24 overflow-hidden group pb-20">
      <div class="absolute inset-0 z-0">
        <img
          :src="props.tournament?.hero_image"
          class="w-full h-full object-cover opacity-30 transform scale-105 group-hover:scale-110 transition duration-[30s] ease-linear"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-[#0B0C15] via-[#0B0C15]/60 to-[#0B0C15]/40"></div>
        <div
          class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20 mix-blend-overlay"
        ></div>
      </div>

      <div class="relative z-10 w-full max-w-7xl mx-auto px-6 lg:px-8 flex flex-col gap-8">
        <div class="flex flex-wrap items-center gap-3 animate-fade-in-up">
          <span
            class="bg-red-600/90 backdrop-blur-sm text-white px-3 py-1 rounded text-[10px] font-bold uppercase tracking-wider shadow-[0_0_20px_rgba(220,38,38,0.6)] animate-pulse flex items-center gap-2"
          >
            <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
            {{ props.tournament?.status ?? 'En Vivo' }}
          </span>

          <span
            class="bg-white/5 backdrop-blur-md border border-white/10 text-white px-3 py-1 rounded text-[10px] font-bold uppercase tracking-wider flex items-center gap-2 hover:bg-white/10 transition cursor-default"
          >
            <i class="fas fa-gamepad text-brand-cyan"></i>
            {{ props.tournament?.game ?? 'Valorant' }}
          </span>
        </div>

        <div class="flex flex-col lg:flex-row items-end justify-between gap-10">
          <div class="max-w-3xl animate-fade-in-up delay-100 relative">
            <h1 class="text-4xl md:text-5xl lg:text-7xl font-display font-bold text-white mb-4 leading-none text-shadow-xl tracking-tight">
              {{ props.tournament?.title ?? 'NEON CITY' }} <br />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-cyan via-white to-brand-purple">
                CHAMPIONSHIP
              </span>
            </h1>

            <p
              class="text-gray-300 text-base md:text-lg font-light max-w-xl border-l-4 border-brand-cyan pl-6 py-1 bg-gradient-to-r from-brand-cyan/5 to-transparent"
            >
              El escenario definitivo. 16 equipos, un solo trofeo y la gloria eterna en el torneo más grande de LATAM.
            </p>
          </div>

          <div class="w-full lg:w-auto flex flex-col sm:flex-row lg:flex-col gap-4 animate-fade-in-up delay-200">
            <div class="glass-panel p-4 rounded-xl border-t-2 border-brand-cyan bg-[#151725]/60 backdrop-blur-xl flex flex-col items-center shadow-2xl flex-1">
              <div class="text-[10px] text-brand-cyan uppercase font-bold mb-2 tracking-widest">Inscripciones Cierran En</div>
              <div class="flex gap-4 items-center font-mono">
                <div class="text-center">
                  <div class="text-2xl md:text-3xl font-bold text-white leading-none">{{ timeLeft.days }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Días</div>
                </div>
                <span class="text-gray-600 font-bold">:</span>
                <div class="text-center">
                  <div class="text-2xl md:text-3xl font-bold text-white leading-none">{{ timeLeft.hours }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Hrs</div>
                </div>
                <span class="text-gray-600 font-bold">:</span>
                <div class="text-center">
                  <div class="text-2xl md:text-3xl font-bold text-white leading-none">{{ timeLeft.minutes }}</div>
                  <div class="text-[8px] text-gray-500 uppercase">Min</div>
                </div>
              </div>
            </div>

            <div class="flex gap-3 flex-1">
              <div class="glass-panel px-4 py-2 rounded-xl text-center flex-1 flex flex-col justify-center bg-[#151725]/60 backdrop-blur-xl">
                <div class="text-[9px] text-gray-500 uppercase font-bold">Prize Pool</div>
                <div class="text-lg md:text-xl font-display font-bold text-green-400 drop-shadow-sm">
                  ${{ (props.tournament?.prize_pool ?? 25000).toLocaleString?.() ?? (props.tournament?.prize_pool ?? 25000) }}
                </div>
              </div>

              <button
                class="bg-gradient-to-r from-brand-cyan to-blue-500 hover:from-cyan-400 hover:to-blue-400 text-black font-bold py-3 px-6 rounded-xl transition shadow-[0_0_25px_rgba(6,182,212,0.4)] hover:shadow-[0_0_35px_rgba(6,182,212,0.6)] transform hover:-translate-y-1 flex-1 whitespace-nowrap"
              >
                INSCRIBIRSE
                <span class="block text-[9px] opacity-70 font-normal mt-0.5">Cupos Limitados</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="absolute bottom-0 w-full h-14 bg-black/80 border-t border-white/5 backdrop-blur-md flex items-center z-20 overflow-hidden">
        <div class="flex items-center gap-16 animate-marquee whitespace-nowrap pl-16">
          <img
            v-for="(sponsor, i) in [...props.sponsors, ...props.sponsors, ...props.sponsors]"
            :key="i"
            :src="sponsor.logo"
            class="h-6 w-auto object-contain opacity-40 hover:opacity-100 transition duration-300 grayscale hover:grayscale-0 filter"
            :alt="sponsor.name"
          />
        </div>
      </div>
    </header>

    <!-- Navigation Tabs -->
    <div class="sticky top-0 lg:top-16 z-40 bg-[#0B0C15]/90 backdrop-blur-lg border-b border-white/5 shadow-lg">
      <div class="max-w-7xl mx-auto px-6 lg:px-8 flex gap-8 overflow-x-auto no-scrollbar">
        <button
          v-for="tab in ['bracket', 'detalles', 'comunidad', 'reglas']"
          :key="tab"
          @click="switchTab(tab)"
          class="py-5 text-xs font-bold uppercase tracking-widest border-b-2 transition duration-300 whitespace-nowrap flex items-center gap-2 group"
          :class="activeTab === tab ? 'border-brand-cyan text-white' : 'border-transparent text-gray-500 hover:text-white'"
        >
          <i v-if="tab === 'bracket'" class="fas fa-sitemap group-hover:text-brand-cyan transition"></i>
          <i v-if="tab === 'detalles'" class="fas fa-info-circle group-hover:text-brand-cyan transition"></i>
          <i v-if="tab === 'comunidad'" class="fas fa-users group-hover:text-brand-cyan transition"></i>
          <i v-if="tab === 'reglas'" class="fas fa-book group-hover:text-brand-cyan transition"></i>
          {{ tab }}
        </button>
      </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 lg:px-8 py-10 min-h-[600px]">
      <!-- === BRACKET === -->
      <div v-if="activeTab === 'bracket'" class="animate-fade-in space-y-10">
        <div class="flex flex-col md:flex-row justify-between items-end gap-4">
          <div>
            <h2 class="text-3xl font-display font-bold text-white">Playoffs Stage</h2>
            <p class="text-gray-500 text-sm mt-1">Haz clic en un match para ver estadísticas detalladas.</p>
          </div>

          <div class="flex gap-4 text-xs bg-black/30 p-2 rounded-lg border border-white/5 overflow-x-auto">
            <span class="flex items-center gap-2 text-gray-400 whitespace-nowrap">
              <span class="w-2 h-2 rounded-full bg-gray-600"></span> Finalizado
            </span>
            <span class="flex items-center gap-2 text-red-400 font-bold whitespace-nowrap">
              <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> En Vivo
            </span>
            <span class="flex items-center gap-2 text-brand-cyan font-bold whitespace-nowrap">
              <i class="fas fa-robot"></i> AI Prediction
            </span>
          </div>
        </div>

        <div class="overflow-x-auto pb-12 custom-scroll">
          <div class="flex gap-16 min-w-max px-4 pt-8">
            <div v-for="(round, rIndex) in props.bracketRounds" :key="rIndex" class="flex flex-col justify-around gap-8 relative">
              <h3
                class="absolute -top-10 left-0 w-full text-center text-xs font-bold text-brand-cyan uppercase tracking-[0.2em] opacity-80 bg-brand-cyan/5 py-1 rounded"
              >
                {{ round.name }}
              </h3>

              <div v-for="match in round.matches" :key="match.id" class="relative group perspective-container">
                <div v-if="!match.isFinal" class="hidden md:block absolute -right-8 top-1/2 w-8 h-0.5 bg-gray-800 group-hover:bg-gray-700 transition"></div>
                <div
                  v-if="!match.isFinal && rIndex < props.bracketRounds.length - 1"
                  class="hidden md:block absolute -right-8 w-0.5 bg-gray-800 group-hover:bg-gray-700 transition"
                  :class="match.id % 2 !== 0 ? 'top-1/2 h-[calc(50%+2rem)] border-t-0 rounded-tr-xl' : 'bottom-1/2 h-[calc(50%+2rem)] border-b-0 rounded-br-xl'"
                ></div>

                <div
                  class="w-64 md:w-72 bg-[#151725] rounded-xl border border-white/5 overflow-hidden transition-all duration-300 shadow-lg relative hover:-translate-y-1 hover:border-brand-purple/40 hover:shadow-[0_10px_30px_-10px_rgba(124,58,237,0.2)]"
                  :class="{ 'ring-1 ring-red-500 shadow-red-900/20': match.status === 'live' }"
                >
                  <div class="bg-black/40 px-3 py-2 flex justify-between items-center border-b border-white/5">
                    <div class="flex gap-2">
                      <span v-if="match.status === 'live'" class="bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded animate-pulse">LIVE</span>
                      <span v-else class="text-[10px] text-gray-500 uppercase font-bold">{{ match.status }}</span>
                      <span v-if="match.isBo3" class="bg-white/10 text-gray-300 text-[9px] font-bold px-1.5 py-0.5 rounded border border-white/5">BO3</span>
                    </div>

                    <div v-if="match.prediction" class="flex items-center gap-1 text-[10px] text-brand-cyan opacity-0 group-hover:opacity-100 transition">
                      <i class="fas fa-brain"></i> {{ match.prediction }}% Win Prob
                    </div>
                  </div>

                  <div class="p-1">
                    <div
                      class="flex justify-between items-center p-2 md:p-3 rounded hover:bg-white/5 transition"
                      :class="{ 'opacity-50': match.winner === 'p2', 'bg-gradient-to-r from-green-500/10 to-transparent': match.winner === 'p1' }"
                    >
                      <div class="flex items-center gap-3">
                        <div class="w-6 h-6 md:w-8 md:h-8 rounded bg-gray-800 flex items-center justify-center text-[10px] text-gray-500 font-bold border border-white/10">IMG</div>
                        <span class="text-xs md:text-sm font-bold truncate max-w-[120px]" :class="match.winner === 'p1' ? 'text-white' : 'text-gray-400'">
                          {{ match.p1 }}
                        </span>
                      </div>
                      <span class="font-mono font-bold text-base md:text-lg" :class="match.winner === 'p1' ? 'text-green-400' : 'text-gray-600'">{{ match.s1 }}</span>
                    </div>

                    <div
                      class="flex justify-between items-center p-2 md:p-3 rounded hover:bg-white/5 transition"
                      :class="{ 'opacity-50': match.winner === 'p1', 'bg-gradient-to-r from-green-500/10 to-transparent': match.winner === 'p2' }"
                    >
                      <div class="flex items-center gap-3">
                        <div class="w-6 h-6 md:w-8 md:h-8 rounded bg-gray-800 flex items-center justify-center text-[10px] text-gray-500 font-bold border border-white/10">IMG</div>
                        <span class="text-xs md:text-sm font-bold truncate max-w-[120px]" :class="match.winner === 'p2' ? 'text-white' : 'text-gray-400'">
                          {{ match.p2 }}
                        </span>
                      </div>
                      <span class="font-mono font-bold text-base md:text-lg" :class="match.winner === 'p2' ? 'text-green-400' : 'text-gray-600'">{{ match.s2 }}</span>
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
          <div class="glass-panel p-8 rounded-2xl bg-[#151725] border border-white/5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-brand-purple/10 rounded-full blur-[100px]"></div>
            <h3 class="font-bold text-2xl mb-4 text-white font-display">Información del Evento</h3>
            <p class="text-gray-400 text-sm leading-relaxed mb-8">
              Bienvenidos a la 5ta edición de la Neon City Cup. Este torneo reúne a los mejores equipos amateurs y semi-pro de la región.
            </p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="bg-black/40 p-4 rounded-xl border border-white/5 hover:border-brand-cyan/30 transition text-center group">
                <i class="fas fa-users text-brand-cyan mb-2 text-xl group-hover:scale-110 transition"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Formato</div>
                <div class="text-white font-bold">5v5 Draft</div>
              </div>
              <div class="bg-black/40 p-4 rounded-xl border border-white/5 hover:border-brand-cyan/30 transition text-center group">
                <i class="fas fa-desktop text-brand-cyan mb-2 text-xl group-hover:scale-110 transition"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Plataforma</div>
                <div class="text-white font-bold">PC / Win 11</div>
              </div>
              <div class="bg-black/40 p-4 rounded-xl border border-white/5 hover:border-brand-cyan/30 transition text-center group">
                <i class="fas fa-server text-brand-cyan mb-2 text-xl group-hover:scale-110 transition"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Región</div>
                <div class="text-white font-bold">LATAM Norte</div>
              </div>
              <div class="bg-black/40 p-4 rounded-xl border border-white/5 hover:border-brand-cyan/30 transition text-center group">
                <i class="fas fa-shield-alt text-brand-cyan mb-2 text-xl group-hover:scale-110 transition"></i>
                <div class="text-[10px] text-gray-500 uppercase font-bold">Anti-Cheat</div>
                <div class="text-white font-bold">Requerido</div>
              </div>
            </div>
          </div>

          <div class="glass-panel p-8 rounded-2xl bg-[#151725] border border-white/5">
            <h3 class="font-bold text-xl mb-6 text-white font-display flex items-center gap-2">
              <i class="fas fa-trophy text-yellow-500"></i> Distribución de Premios
            </h3>
            <div class="space-y-4">
              <div v-for="(prize, i) in props.prizeDistribution" :key="i" class="relative">
                <div class="flex justify-between text-sm font-bold mb-1 z-10 relative">
                  <span class="text-white">{{ prize.place }} Place</span>
                  <span class="text-green-400">{{ prize.amount }}</span>
                </div>
                <div class="w-full bg-black/50 rounded-full h-8 overflow-hidden relative border border-white/5">
                  <div :class="`h-full ${prize.color} relative`" :style="`width: ${prize.percent}%`">
                    <div class="absolute top-0 right-0 bottom-0 left-0 bg-gradient-to-b from-white/20 to-transparent"></div>
                  </div>
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-white/50">{{ prize.percent }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
          <div class="bg-[#1A1C2E] p-1 rounded-2xl border border-brand-purple/50 relative overflow-hidden group shadow-[0_0_30px_rgba(124,58,237,0.15)]">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-purple/20 via-transparent to-transparent opacity-50"></div>

            <div class="bg-[#0B0C15]/90 rounded-xl p-6 relative z-10 h-full">
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h3 class="font-display font-bold text-lg text-white">Viewer Drops</h3>
                  <p class="text-[10px] text-brand-cyan font-bold uppercase tracking-wider">Temporada 5</p>
                </div>
                <i class="fas fa-gift text-2xl text-brand-purple animate-bounce"></i>
              </div>

              <div class="space-y-4">
                <div>
                  <div class="flex justify-between text-xs font-bold text-white mb-2">
                    <span>Nivel 3</span>
                    <span>{{ watchProgress }} / 100 XP</span>
                  </div>
                  <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden border border-gray-700">
                    <div class="bg-gradient-to-r from-brand-purple to-brand-cyan h-3 rounded-full relative shadow-[0_0_10px_rgba(124,58,237,0.5)]" :style="`width: ${watchProgress}%`">
                      <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-30"></div>
                    </div>
                  </div>
                </div>

                <div class="flex gap-2 justify-between">
                  <div class="w-12 h-12 bg-gray-800 rounded border border-gray-600 flex items-center justify-center opacity-50 grayscale cursor-help" title="Skin Exclusiva">
                    <i class="fas fa-tshirt text-white"></i>
                  </div>
                  <div class="w-12 h-12 bg-gray-800 rounded border border-gray-600 flex items-center justify-center opacity-50 grayscale cursor-help" title="Moneda Virtual">
                    <i class="fas fa-coins text-yellow-500"></i>
                  </div>
                  <div class="w-12 h-12 bg-brand-purple/20 rounded border border-brand-purple flex items-center justify-center relative shadow-[0_0_10px_rgba(124,58,237,0.4)]">
                    <i class="fas fa-box-open text-white"></i>
                    <div class="absolute -top-1 -right-1 bg-red-500 w-3 h-3 rounded-full border border-black"></div>
                  </div>
                </div>
              </div>

              <button class="w-full mt-6 bg-white text-black font-bold py-2 rounded-lg text-xs hover:bg-gray-200 transition flex items-center justify-center gap-2">
                <i class="fab fa-twitch"></i> Conectar Twitch
              </button>
            </div>
          </div>

          <div class="glass-panel p-6 rounded-2xl border border-white/5">
            <h4 class="text-sm font-bold text-white mb-4 uppercase tracking-wider">Equipos Destacados</h4>
            <div class="space-y-3">
              <div v-for="i in 4" :key="i" class="flex items-center gap-3 p-2 hover:bg-white/5 rounded-lg transition cursor-pointer group">
                <div class="w-8 h-8 rounded bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-500 border border-white/10 group-hover:border-brand-cyan transition">T{{ i }}</div>
                <div class="flex-1">
                  <div class="text-sm font-bold text-gray-300 group-hover:text-white transition">Team Alpha {{ i }}</div>
                  <div class="text-[10px] text-gray-500">2,450 ELO</div>
                </div>
                <i class="fas fa-chevron-right text-xs text-gray-600 group-hover:text-brand-cyan transition"></i>
              </div>
            </div>
          </div>
        </aside>
      </div>

      <!-- === COMUNIDAD === -->
      <div v-show="activeTab === 'comunidad'" class="animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[70vh]">
          <div class="lg:col-span-9 h-full">
            <div class="w-full h-full rounded-2xl overflow-hidden shadow-2xl border border-gray-800 bg-black relative">
              <div id="twitch-embed" class="w-full h-full"></div>
            </div>
          </div>

          <div class="lg:col-span-3 h-full flex flex-col gap-4">
            <div class="flex-1 glass-panel rounded-2xl overflow-hidden border border-gray-700 relative">
              <div class="absolute top-0 w-full bg-[#0B0C15] p-2 text-xs font-bold text-gray-400 border-b border-gray-800 z-10 flex justify-between">
                <span>Chat en Vivo</span>
                <span class="text-green-500 flex items-center gap-1">
                  <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> 12.4k
                </span>
              </div>

               <!-- Ejemplo: Twitch chat -->
  <iframe
    :src="`https://www.twitch.tv/embed/Jelty/chat?parent=${parentHost}&darkpopout`"
    height="100%"
    width="100%"
  />
            </div>

            <div class="h-auto bg-[#1A1C2E] p-4 rounded-xl border border-brand-cyan/20">
              <div class="text-[10px] text-brand-cyan font-bold uppercase mb-2">Predicción Activa</div>
              <p class="text-xs text-white font-bold mb-3">¿Quién ganará el mapa 1?</p>
              <div class="flex gap-2">
                <button class="flex-1 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white text-xs py-1.5 rounded border border-blue-600/50 transition">Liquid</button>
                <button class="flex-1 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white text-xs py-1.5 rounded border border-red-600/50 transition">G2</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- === REGLAS (placeholder) === -->
      <div v-show="activeTab === 'reglas'" class="animate-fade-in">
        <div class="glass-panel p-8 rounded-2xl bg-[#151725] border border-white/5">
          <h3 class="font-bold text-2xl mb-4 text-white font-display">Reglas</h3>
          <p class="text-gray-400 text-sm">Aquí puedes pintar reglas desde DB (Markdown/HTML sanitizado).</p>
        </div>
      </div>
    </main>

    <div class="fixed bottom-0 w-full bg-[#0B0C15]/90 backdrop-blur-md border-t border-brand-cyan/20 p-4 z-50 lg:hidden">
      <div class="flex justify-between items-center">
        <div>
          <div class="text-white font-bold text-sm">{{ props.tournament?.title ?? 'Neon City Cup' }}</div>
          <div class="text-[10px] text-green-400">Inscripciones Abiertas</div>
        </div>
        <button class="bg-brand-cyan text-black font-bold px-6 py-2 rounded-lg text-sm shadow-[0_0_15px_rgba(6,182,212,0.3)]">
          Inscribirse
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes marquee {
  0% { transform: translateX(0); }
  100% { transform: translateX(-33.33%); }
}
.animate-marquee { animation: marquee 20s linear infinite; }

.glass-panel { background: rgba(21, 23, 37, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
.text-shadow-xl { text-shadow: 0 4px 30px rgba(0,0,0,0.8); }

.custom-scroll::-webkit-scrollbar { height: 8px; }
.custom-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background: #06B6D4; }

.animate-fade-in-up { animation: fadeInUp 0.8s ease-out forwards; opacity: 0; }
.animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
.delay-100 { animation-delay: 0.1s; }
.delay-200 { animation-delay: 0.2s; }

@keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
