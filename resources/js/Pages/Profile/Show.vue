<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  profile: Object,
  activeTournaments: Array,
  scoreboardWidgetUrl: String,
  organizer: Object,
  canLogin: Boolean, 
  laravelVersion: String,
  phpVersion: String,
})

// Acceso al usuario autenticado (Fallback si no viene en profile)
const user = usePage().props.auth.user

// --- THEME & UTILS ---
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

onMounted(() => {
  const savedTheme = localStorage.getItem('theme')
  const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true
  if (savedTheme === 'light') applyTheme(false)
  else if (savedTheme === 'dark') applyTheme(true)
  else applyTheme(systemPrefersDark)

  // Load Phosphor Icons
  if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/@phosphor-icons/web'
    script.async = true
    document.head.appendChild(script)
  }
})

// --- Lógica Dashboard ---
const bellzTab = ref('codes')
const matchCodes = ref<Record<string, string>>({ m1: '', m2: '', m3: '', m4: '', m5: '', m6: '' })
const copied = ref(false)

const copyWidgetUrl = async () => {
  try {
    await navigator.clipboard.writeText(props.scoreboardWidgetUrl || 'https://rankit.gg/widget/bellzcup-uuid')
    copied.value = true
    setTimeout(() => (copied.value = false), 1200)
  } catch (e) {
    copied.value = false
    alert('Copia manualmente la URL.')
  }
}

// Modal Logic
const showMatchModal = ref(false)
const selectedMatchId = ref<number | null>(null)
const replayFile = ref<File | null>(null)

const openMatchDetails = (id: any) => {
  selectedMatchId.value = id
  replayFile.value = null
  showMatchModal.value = true
}

const handleFileUpload = (event: any) => {
  const file = event.target.files[0]
  if (file) replayFile.value = file
}

const submitReplay = () => {
  if (!replayFile.value) return
  alert(`Replay subida. Procesando stats...`)
  showMatchModal.value = false
}

const mockTableData = ref([
  { team: 'Team Liquid', kills: 45, points: 25 },
  { team: 'G2 Esports', kills: 32, points: 18 },
  { team: 'Sentinels', kills: 28, points: 15 },
  { team: 'KRÜ', kills: 20, points: 10 },
])
</script>

<template>
  <Head title="Dashboard - Rankit">
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
  </Head>

  <div class="min-h-screen pb-12 font-sans transition-colors duration-300 overflow-x-hidden selection:bg-[var(--rankit-neon)] selection:text-white bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white">
    
    <!-- Match Bar (Sticky) -->
    <div class="fixed top-0 left-0 w-full h-14 flex items-center justify-between px-6 lg:px-12 z-[60] border-b transition-colors bg-white border-gray-200 dark:bg-[#0a0a0a] dark:border-white/10">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
          <span class="text-xs font-bold tracking-wider uppercase text-gray-600 dark:text-gray-300">bellzCup - En Vivo</span>
        </div>
        <div class="w-px h-4 bg-gray-300 dark:bg-gray-700"></div>
        <span class="text-sm font-bold">Partida 3/6 en curso</span>
      </div>
      <Link href="#" class="px-4 py-1 text-[10px] font-bold tracking-wider uppercase btn-skew">
        <span class="btn-content">Ir al Stream</span>
      </Link>
    </div>

    <!-- Navbar -->
    <nav class="fixed z-50 flex items-center justify-between w-full h-20 px-6 transition-all duration-300 border-b lg:px-12 backdrop-blur-md top-14 bg-white/90 border-gray-200 dark:bg-[#050505]/95 dark:border-white/10">
      <Link href="/" class="flex items-center gap-3 cursor-pointer group">
        <svg class="w-8 h-8 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
        </svg>
        <span class="text-2xl italic font-bold tracking-tighter uppercase font-display text-black dark:text-white">Rankit</span>
      </Link>

      <div class="flex items-center gap-4">
        <!-- Theme Toggle -->
        <button @click="toggleTheme" class="p-2 transition-colors border border-transparent rounded-lg text-gray-500 hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
          <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
          <i v-else class="text-xl ph-fill ph-moon"></i>
        </button>

        <!-- LOGOUT BUTTON AÑADIDO -->
        <Link 
            :href="route('logout')" 
            method="post" 
            as="button" 
            class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-red-500 uppercase transition-all border border-red-500/20 rounded-lg hover:bg-red-500 hover:text-white hover:border-red-500 group"
        >
            <i class="text-lg ph-bold ph-sign-out"></i>
            <span class="hidden sm:inline">Salir</span>
        </Link>
      </div>
    </nav>

    <main class="grid grid-cols-1 gap-8 px-6 py-8 mx-auto max-w-7xl lg:px-8 lg:grid-cols-12 pt-44">
      <!-- LEFT COLUMN -->
      <aside class="space-y-6 lg:col-span-4">
        <!-- Profile Card -->
        <div class="relative p-6 overflow-hidden text-center brutal-card group bg-white dark:bg-[#0a0a0a]">
          <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-[var(--rankit-neon)]/20 to-transparent"></div>
          <div class="relative z-10">
            <div class="relative flex items-center justify-center mx-auto mb-4 w-24 h-24 rounded-full p-1 bg-gradient-to-r from-cyan-500 to-[var(--rankit-neon)]">
              <!-- Avatar Fallback using UI Avatars -->
              <img 
                :src="props.profile?.avatar || `https://ui-avatars.com/api/?name=${user?.name || 'User'}&background=000&color=fff`" 
                class="w-full h-full rounded-full object-cover border-4 border-white dark:border-[#0a0a0a]" 
              />
            </div>
            <h1 class="text-2xl font-bold font-display text-black dark:text-white">{{ props.profile?.username ?? user?.name ?? 'Jugador' }}</h1>
            <p class="mb-4 text-xs text-gray-500 font-bold uppercase tracking-widest">{{ props.profile?.badge ?? 'Participante' }}</p>
          </div>
        </div>

        <!-- MANAGER PANEL -->
        <div class="overflow-hidden text-left brutal-card bg-white dark:bg-[#0a0a0a]">
          <div class="p-4 border-b border-gray-200 dark:border-white/10">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--rankit-neon)]">Panel de Control</div>
                <div class="text-xl italic font-bold uppercase font-display text-black dark:text-white">bellzCup</div>
              </div>
              <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_10px_#22c55e]"></div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="flex border-b border-gray-200 dark:border-white/10">
            <button v-for="tab in ['codes', 'widget', 'matches']" :key="tab" @click="bellzTab = tab"
              class="flex-1 py-3 text-[10px] font-bold uppercase tracking-wider text-center transition"
              :class="bellzTab === tab ? 'bg-[var(--rankit-neon)] text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5'">
              {{ tab }}
            </button>
          </div>

          <!-- CONTENT: CODES -->
          <div v-if="bellzTab === 'codes'" class="p-4 space-y-3 animate-fade-in">
            <div class="grid grid-cols-2 gap-3">
              <div v-for="i in 6" :key="i">
                <label class="text-[9px] font-bold uppercase text-gray-500 block mb-1">Partida {{ i }}</label>
                <input v-model="matchCodes[`m${i}`]" type="text" placeholder="Código..." 
                  class="brutal-input text-center text-xs font-mono py-1" />
              </div>
            </div>
            <button class="w-full mt-2 py-3 text-xs font-bold uppercase border border-black/10 dark:border-white/10 hover:bg-[var(--rankit-neon)] hover:text-white hover:border-[var(--rankit-neon)] transition">
              Actualizar Códigos
            </button>
          </div>

          <!-- CONTENT: WIDGET -->
          <div v-if="bellzTab === 'widget'" class="p-4 space-y-4 text-center animate-fade-in">
            <div class="p-3 border border-dashed rounded-lg border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-black/20">
              <div class="text-[10px] uppercase font-bold text-gray-500 mb-2">URL del Scoreboard (OBS)</div>
              <div class="mb-3 font-mono text-xs break-all opacity-70 text-black dark:text-white">
                {{ props.scoreboardWidgetUrl || 'https://rankit.gg/overlay/bellzcup/v1' }}
              </div>
              <button @click="copyWidgetUrl" class="px-4 py-2 text-[10px] font-bold uppercase border hover:border-[var(--rankit-neon)] transition flex items-center justify-center gap-2 mx-auto text-black dark:text-white border-gray-300 dark:border-gray-600">
                <i class="ph ph-copy"></i> {{ copied ? '¡Copiado!' : 'Copiar Link' }}
              </button>
            </div>
          </div>

          <!-- CONTENT: MATCHES -->
          <div v-if="bellzTab === 'matches'" class="p-2 animate-fade-in">
            <div class="space-y-1">
              <div v-for="i in 6" :key="i" @click="openMatchDetails(i)"
                class="flex items-center justify-between p-3 cursor-pointer group transition border border-transparent hover:border-[var(--rankit-neon)] bg-gray-50 dark:bg-white/5">
                <div class="flex items-center gap-3">
                  <div class="w-6 h-6 flex items-center justify-center text-[10px] font-bold bg-gray-200 dark:bg-gray-800 text-gray-500 group-hover:bg-[var(--rankit-neon)] group-hover:text-white transition">
                    {{ i }}
                  </div>
                  <div>
                    <div class="text-xs font-bold uppercase text-black dark:text-white">Partida {{ i }}</div>
                    <div class="text-[9px] text-gray-500" v-if="i <= 3">Finalizada</div>
                    <div class="text-[9px] text-[var(--rankit-neon)]" v-else-if="i === 4">En Curso</div>
                    <div class="text-[9px] text-gray-400" v-else>Pendiente</div>
                  </div>
                </div>
                <i class="text-xs ph ph-caret-right text-gray-400 group-hover:text-white"></i>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- CENTER COLUMN -->
      <div class="space-y-8 lg:col-span-8">
        <div class="flex gap-6 pb-1 border-b border-gray-300 dark:border-gray-800">
          <button class="font-bold text-sm pb-3 text-[var(--rankit-neon)] border-b-2 border-[var(--rankit-neon)] uppercase tracking-wider">
            Torneos Activos
          </button>
        </div>

        <div class="p-6 border-l-4 brutal-card border-l-[var(--rankit-neon)] bg-white dark:bg-[#0a0a0a]">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="text-2xl italic font-bold uppercase font-display text-black dark:text-white">bellzCup Season 1</h3>
              <p class="mt-1 text-sm text-gray-500">Fase de Grupos • Partida 3/6</p>
            </div>
            <span class="px-2 py-1 bg-red-500 text-white text-[10px] font-bold uppercase animate-pulse">En Vivo</span>
          </div>
          <div class="flex items-center justify-center h-48 border bg-gray-100 dark:bg-black/50 border-gray-200 dark:border-white/5 relative overflow-hidden group">
            <!-- Placeholder Background -->
             <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')] bg-cover bg-center opacity-30 grayscale group-hover:grayscale-0 transition duration-700"></div>
            
            <span class="text-xs tracking-widest text-gray-500 uppercase flex items-center gap-2 relative z-10 font-bold bg-black/50 px-4 py-2 rounded-full border border-white/10 backdrop-blur-sm">
                <i class="ph ph-video-camera text-xl text-red-500"></i> Vista Previa del Stream
            </span>
          </div>
        </div>
      </div>
    </main>

    <!-- MODAL -->
    <Modal :show="showMatchModal" @close="showMatchModal = false" maxWidth="2xl">
      <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
        <div class="flex items-start justify-between mb-6">
          <div>
            <h2 class="text-2xl italic font-bold uppercase font-display">
              Detalles <span class="text-[var(--rankit-neon)]">#{{ selectedMatchId }}</span>
            </h2>
            <p class="text-xs tracking-wider text-gray-500 uppercase">bellzCup S1</p>
          </div>
          <button @click="showMatchModal = false" class="text-gray-500 hover:text-red-500">
            <i class="text-xl ph ph-x"></i>
          </button>
        </div>

        <div class="mb-8">
          <h3 class="flex items-center gap-2 mb-3 text-sm font-bold uppercase">
            <i class="ph ph-table text-[var(--rankit-neon)]"></i> Tabla de Posiciones
          </h3>
          <div class="overflow-hidden border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm text-left">
              <thead class="bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-400">
                <tr>
                  <th class="px-4 py-2 font-bold uppercase text-[10px]">Equipo</th>
                  <th class="px-4 py-2 font-bold uppercase text-[10px] text-right">Kills</th>
                  <th class="px-4 py-2 font-bold uppercase text-[10px] text-right">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                <tr v-for="(row, idx) in mockTableData" :key="idx" class="hover:bg-gray-50 dark:hover:bg-white/5">
                  <td class="px-4 py-3 font-bold">{{ row.team }}</td>
                  <td class="px-4 py-3 font-mono text-right">{{ row.kills }}</td>
                  <td class="px-4 py-3 text-right font-bold text-[var(--rankit-neon)]">{{ row.points }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-[var(--rankit-neon)]/5 border border-[var(--rankit-neon)]/20 p-4">
          <h3 class="text-sm font-bold uppercase mb-3 flex items-center gap-2 text-[var(--rankit-neon)]">
            <i class="ph ph-upload-simple"></i> Subir Repetición
          </h3>
          <div class="flex items-end gap-4">
            <div class="flex-1">
              <input type="file" @change="handleFileUpload" 
                class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-xs file:font-bold file:uppercase file:bg-[var(--rankit-neon)] file:text-white hover:file:bg-black file:cursor-pointer file:transition cursor-pointer bg-gray-100 dark:bg-black/20 border border-gray-200 dark:border-white/10" />
            </div>
            <button @click="submitReplay" :disabled="!replayFile" class="px-6 py-2 bg-black dark:bg-white text-white dark:text-black font-bold uppercase text-xs hover:opacity-80 disabled:opacity-50 transition h-[34px]">
              Procesar
            </button>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>

<style>
/* Global Styles Sync */
:root { --rankit-neon: #bf00ff; }
.font-display { font-family: "Chakra Petch", sans-serif; }
.font-sans { font-family: "Archivo", sans-serif; }
.text-neon { color: var(--rankit-neon); }

/* Brutal Cards */
.brutal-card { position: relative; transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94); border: 1px solid; }
.dark .brutal-card { background: #0a0a0a; border-color: #333; }
html:not(.dark) .brutal-card { background: #ffffff; border-color: #e5e5e5; box-shadow: 4px 4px 0px #00000010; }
.brutal-card:hover { border-color: var(--rankit-neon); transform: translate(-4px, -4px); }
.dark .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon); }
html:not(.dark) .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon), 6px 6px 0px 2px black; }

/* Inputs */
.brutal-input { width: 100%; background: transparent; border-bottom: 2px solid #333; padding: 0.5rem 0; font-family: "Archivo", sans-serif; font-weight: 600; outline: none; transition: all 0.3s; }
.dark .brutal-input { color: white; border-color: #333; }
html:not(.dark) .brutal-input { color: black; border-color: #e5e5e5; }
.brutal-input:focus { border-color: var(--rankit-neon); padding-left: 0.5rem; }

/* Btn Skew */
.btn-skew { background-color: var(--rankit-neon); color: white; transform: skewX(-10deg); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-skew:hover { background-color: white; color: black; box-shadow: 0 0 15px var(--rankit-neon); }
html:not(.dark) .btn-skew:hover { background-color: black; color: white; box-shadow: 4px 4px 0px rgba(0,0,0,0.2); }
.btn-content { transform: skewX(10deg); }

.animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>