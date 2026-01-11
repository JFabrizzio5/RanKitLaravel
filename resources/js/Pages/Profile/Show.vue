<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue' // Importamos el Modal para los detalles de la partida
// import AppLogo from '@/Components/AppLogo.vue' // Asegúrate de tener este componente o coméntalo

const props = defineProps({
  profile: Object,
  activeTournaments: Array,
  scoreboardWidgetUrl: String,
  organizer: Object,
})

// --- Lógica Global ---
const currentLang = ref('es')
const toggleLanguage = () => (currentLang.value = currentLang.value === 'es' ? 'en' : 'es')

const isDark = ref(true)
const toggleTheme = () => {
  isDark.value = !isDark.value
  updateTheme()
}

const updateTheme = () => {
  if (typeof window !== 'undefined') {
    document.documentElement.classList.toggle('dark', isDark.value)
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    const saved = localStorage.getItem('theme')
    isDark.value =
      saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)
    updateTheme()
  }
})

const activeTab = ref('active')
const switchTab = (tab) => (activeTab.value = tab)

// --- bellzCup LOGIC ---
const organizerEnabled = ref(!!props.organizer?.enabled)
const bellzTab = ref('codes') // 'codes', 'widget', 'matches'

// 6 Códigos de Partida
const matchCodes = ref({
  m1: '', m2: '', m3: '', m4: '', m5: '', m6: ''
})

// Widget Copy Logic
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

// --- PARTIDAS & MODAL ---
const showMatchModal = ref(false)
const selectedMatchId = ref(null)
const replayFile = ref(null)

const openMatchDetails = (id) => {
  selectedMatchId.value = id
  replayFile.value = null // Reset file input
  showMatchModal.value = true
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    replayFile.value = file
  }
}

const submitReplay = () => {
  if (!replayFile.value) return
  
  // Aquí iría la lógica para enviar al microservicio
  console.log(`Enviando replay de Partida ${selectedMatchId.value}:`, replayFile.value.name)
  
  // Simulación de éxito
  alert(`Replay de la Partida ${selectedMatchId.value} subida correctamente. Procesando stats...`)
  showMatchModal.value = false
}

// Datos Mock para la tabla dentro del modal
const mockTableData = ref([
  { team: 'Team Liquid', kills: 45, points: 25, status: 'Winner' },
  { team: 'G2 Esports', kills: 32, points: 18, status: 'Top 2' },
  { team: 'Sentinels', kills: 28, points: 15, status: 'Top 3' },
  { team: 'KRÜ', kills: 20, points: 10, status: 'Top 4' },
])
</script>

<template>
  <div
    class="min-h-screen pb-12 font-sans transition-colors duration-300 main-wrapper"
    :class="isDark ? 'bg-[#050505] text-white' : 'bg-gray-50 text-gray-900'"
  >
    <!-- Match Bar (Persistent) -->
    <div
      class="fixed top-0 left-0 w-full h-14 flex items-center justify-between px-6 lg:px-12 z-[60] shadow-lg border-b transition-colors"
      :class="isDark ? 'bg-[#0a0a0a] border-white/10' : 'bg-white border-gray-200'"
    >
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
          <span
            class="text-xs font-bold tracking-wider uppercase"
            :class="isDark ? 'text-gray-300' : 'text-gray-600'"
          >
            bellzCup - En Vivo
          </span>
        </div>
        <div class="w-px h-4 bg-gray-600"></div>
        <span class="text-sm font-bold">
          Partida 3/6 en curso
        </span>
      </div>

      <div class="flex items-center gap-3">
        <Link
          href="#"
          class="px-4 py-1 text-[10px] font-bold tracking-wider uppercase btn-skew"
        >
          <span class="btn-content">Ir al Stream</span>
        </Link>
      </div>
    </div>

    <!-- Navbar -->
    <nav
      class="fixed z-50 flex items-center justify-between w-full h-20 px-6 transition-all duration-300 border-b lg:px-12 backdrop-blur-md top-14"
      :class="isDark ? 'bg-[#050505]/95 border-white/10' : 'bg-white/90 border-gray-200'"
    >
      <Link href="/" class="flex items-center gap-3 cursor-pointer group">
        <!-- Logo placeholder -->
        <div class="w-8 h-8 bg-current rounded-full"></div> 
        <span class="text-3xl italic font-bold tracking-tighter uppercase font-display" :class="isDark ? 'text-white' : 'text-black'">
          Rankit
        </span>
      </Link>

      <div class="flex items-center gap-4">
        <button
          @click="toggleTheme"
          class="p-2 transition-colors border border-transparent rounded-lg"
          :class="isDark ? 'text-gray-400 hover:text-[var(--rankit-neon)] hover:border-gray-700' : 'text-gray-500 hover:text-[var(--rankit-neon)] hover:border-gray-300'"
        >
          <span v-if="isDark">☀</span>
          <span v-else>☾</span>
        </button>
      </div>
    </nav>

    <main class="grid grid-cols-1 gap-8 px-6 py-8 mx-auto max-w-7xl lg:px-8 lg:grid-cols-12 pt-44">
      <!-- LEFT COLUMN -->
      <aside class="space-y-6 lg:col-span-4">
        <!-- Profile Card -->
        <div class="relative p-6 overflow-hidden text-center brutal-card group">
          <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-[var(--rankit-neon)]/20 to-transparent"></div>
          <div class="relative z-10">
            <div class="relative flex items-center justify-center mx-auto mb-4 profile-avatar-container">
              <img :src="props.profile?.avatar || 'https://ui-avatars.com/api/?name=Admin&background=random'" alt="Avatar" class="profile-avatar-img" />
            </div>

            <h1 class="text-2xl font-bold font-display" :class="isDark ? 'text-white' : 'text-black'">
              {{ props.profile?.username ?? 'BellzAdmin' }}
            </h1>
            <p class="mb-4 text-xs text-gray-500">Organizador Principal</p>
          </div>
        </div>

        <!-- ✅ bellzCup MANAGER -->
        <div class="overflow-hidden text-left brutal-card">
          <div class="p-4 border-b" :class="isDark ? 'border-white/10' : 'border-gray-200'">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--rankit-neon)]">Panel de Control</div>
                <div class="text-xl italic font-bold uppercase font-display">bellzCup</div>
              </div>
              <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_10px_#22c55e]"></div>
            </div>
          </div>

          <!-- Pestañas Internas -->
          <div class="flex border-b" :class="isDark ? 'border-white/10' : 'border-gray-200'">
            <button 
              @click="bellzTab = 'codes'"
              class="flex-1 py-2 text-[10px] font-bold uppercase tracking-wider text-center transition hover:bg-white/5"
              :class="bellzTab === 'codes' ? 'bg-[var(--rankit-neon)] text-white' : 'text-gray-500'"
            >
              Códigos
            </button>
            <button 
              @click="bellzTab = 'widget'"
              class="flex-1 py-2 text-[10px] font-bold uppercase tracking-wider text-center transition hover:bg-white/5"
              :class="bellzTab === 'widget' ? 'bg-[var(--rankit-neon)] text-white' : 'text-gray-500'"
            >
              Widget
            </button>
            <button 
              @click="bellzTab = 'matches'"
              class="flex-1 py-2 text-[10px] font-bold uppercase tracking-wider text-center transition hover:bg-white/5"
              :class="bellzTab === 'matches' ? 'bg-[var(--rankit-neon)] text-white' : 'text-gray-500'"
            >
              Partidas
            </button>
          </div>

          <!-- CONTENIDO: CÓDIGOS -->
          <div v-if="bellzTab === 'codes'" class="p-4 space-y-3 animate-fade-in">
            <div class="grid grid-cols-2 gap-3">
              <div v-for="i in 6" :key="i">
                <label class="text-[9px] font-bold uppercase text-gray-500 block mb-1">Partida {{ i }}</label>
                <input
                  v-model="matchCodes[`m${i}`]"
                  type="text"
                  placeholder="Código..."
                  class="w-full px-2 py-1.5 rounded border text-xs font-mono text-center focus:ring-1 focus:ring-[var(--rankit-neon)] outline-none"
                  :class="isDark ? 'bg-black/30 border-gray-700 text-white placeholder-gray-700' : 'bg-gray-50 border-gray-300 text-black'"
                />
              </div>
            </div>
            <button class="w-full mt-2 py-2 text-xs font-bold uppercase bg-white/5 border border-white/10 hover:bg-[var(--rankit-neon)] hover:text-white transition rounded">
              Actualizar Códigos
            </button>
          </div>

          <!-- CONTENIDO: WIDGET -->
          <div v-if="bellzTab === 'widget'" class="p-4 space-y-4 text-center animate-fade-in">
            <div class="p-3 border border-dashed rounded-lg" :class="isDark ? 'border-gray-700 bg-black/20' : 'border-gray-300 bg-gray-50'">
              <div class="text-[10px] uppercase font-bold text-gray-500 mb-2">URL del Scoreboard (OBS)</div>
              <div class="mb-3 font-mono text-xs break-all opacity-70">
                {{ props.scoreboardWidgetUrl || 'https://rankit.gg/overlay/bellzcup/v1' }}
              </div>
              <button
                @click="copyWidgetUrl"
                class="px-4 py-2 text-[10px] font-bold uppercase rounded border hover:border-[var(--rankit-neon)] transition flex items-center justify-center gap-2 mx-auto"
                :class="isDark ? 'border-gray-600 bg-gray-800 text-white' : 'border-gray-300 bg-white text-black'"
              >
                <i class="fas fa-copy"></i>
                {{ copied ? '¡Copiado!' : 'Copiar Link' }}
              </button>
            </div>
            <p class="text-[10px] text-gray-500">
              Pega este link en una fuente de navegador en OBS Studio. Resolución recomendada: 1920x1080.
            </p>
          </div>

          <!-- CONTENIDO: PARTIDAS (LISTA) -->
          <div v-if="bellzTab === 'matches'" class="p-2 animate-fade-in">
            <div class="space-y-1">
              <div 
                v-for="i in 6" :key="i"
                @click="openMatchDetails(i)"
                class="flex items-center justify-between p-3 rounded cursor-pointer group transition border border-transparent hover:border-[var(--rankit-neon)]"
                :class="isDark ? 'hover:bg-white/5' : 'hover:bg-gray-100'"
              >
                <div class="flex items-center gap-3">
                  <div class="w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold bg-gray-800 text-gray-400 group-hover:bg-[var(--rankit-neon)] group-hover:text-white transition">
                    {{ i }}
                  </div>
                  <div>
                    <div class="text-xs font-bold uppercase">Partida {{ i }}</div>
                    <div class="text-[9px] text-gray-500" v-if="i <= 3">Finalizada • Ver Resultados</div>
                    <div class="text-[9px] text-[var(--rankit-neon)]" v-else-if="i === 4">En Curso</div>
                    <div class="text-[9px] text-gray-600" v-else>Pendiente</div>
                  </div>
                </div>
                <i class="text-xs text-gray-600 transition fas fa-chevron-right group-hover:text-white"></i>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- CENTER COLUMN -->
      <div class="space-y-8 lg:col-span-8">
        <div class="flex gap-6 pb-1 border-b" :class="isDark ? 'border-gray-800' : 'border-gray-300'">
          <button class="font-bold text-sm pb-3 text-[var(--rankit-neon)] border-b-2 border-[var(--rankit-neon)] uppercase tracking-wider">
            Torneos Activos
          </button>
        </div>

        <!-- Ejemplo de torneo activo -->
        <div class="p-6 border-l-4 brutal-card border-l-[var(--rankit-neon)]">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="text-2xl italic font-bold uppercase font-display">bellzCup Season 1</h3>
              <p class="mt-1 text-sm text-gray-500">Fase de Grupos • Partida 3/6</p>
            </div>
            <span class="px-2 py-1 bg-red-500 text-white text-[10px] font-bold uppercase rounded animate-pulse">En Vivo</span>
          </div>
          <div class="flex items-center justify-center h-48 border rounded bg-black/50 border-white/5">
            <span class="text-xs tracking-widest text-gray-500 uppercase">Stream Preview Placeholder</span>
          </div>
        </div>
      </div>
    </main>

    <!-- === MODAL DETALLES DE PARTIDA === -->
    <Modal :show="showMatchModal" @close="showMatchModal = false" maxWidth="2xl">
      <div class="p-6 text-white" :class="isDark ? 'bg-[#101012]' : 'bg-white text-black'">
        <!-- Header Modal -->
        <div class="flex items-start justify-between mb-6">
          <div>
            <h2 class="text-2xl italic font-bold uppercase font-display">
              Detalles de Partida <span class="text-[var(--rankit-neon)]">#{{ selectedMatchId }}</span>
            </h2>
            <p class="text-xs tracking-wider text-gray-500 uppercase">bellzCup S1</p>
          </div>
          <button @click="showMatchModal = false" class="text-gray-500 transition hover:text-white">
            <i class="text-xl fas fa-times"></i>
          </button>
        </div>

        <!-- Tabla de Resultados -->
        <div class="mb-8">
          <h3 class="flex items-center gap-2 mb-3 text-sm font-bold uppercase">
            <i class="fas fa-table text-[var(--rankit-neon)]"></i> Tabla de Posiciones
          </h3>
          <div class="overflow-hidden border rounded" :class="isDark ? 'border-white/10 bg-black/30' : 'border-gray-200 bg-gray-50'">
            <table class="w-full text-sm text-left">
              <thead :class="isDark ? 'bg-white/5 text-gray-400' : 'bg-gray-200 text-gray-600'">
                <tr>
                  <th class="px-4 py-2 font-bold uppercase text-[10px]">Rank</th>
                  <th class="px-4 py-2 font-bold uppercase text-[10px]">Equipo</th>
                  <th class="px-4 py-2 font-bold uppercase text-[10px] text-right">Kills</th>
                  <th class="px-4 py-2 font-bold uppercase text-[10px] text-right">Puntos</th>
                </tr>
              </thead>
              <tbody class="divide-y" :class="isDark ? 'divide-white/5' : 'divide-gray-200'">
                <tr v-for="(row, idx) in mockTableData" :key="idx" class="transition hover:bg-white/5">
                  <td class="px-4 py-3 font-mono text-gray-500">#{{ idx + 1 }}</td>
                  <td class="px-4 py-3 font-bold">{{ row.team }}</td>
                  <td class="px-4 py-3 font-mono text-right">{{ row.kills }}</td>
                  <td class="px-4 py-3 text-right font-bold text-[var(--rankit-neon)]">{{ row.points }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Formulario de Subida -->
        <div class="bg-[var(--rankit-neon)]/5 border border-[var(--rankit-neon)]/20 p-4 rounded-lg">
          <h3 class="text-sm font-bold uppercase mb-3 flex items-center gap-2 text-[var(--rankit-neon)]">
            <i class="fas fa-cloud-upload-alt"></i> Subir Repetición
          </h3>
          <p class="mb-4 text-xs text-gray-400">
            Sube el archivo de repetición (.dem) para procesar las estadísticas en la base de datos.
          </p>

          <div class="flex items-end gap-4">
            <div class="flex-1">
              <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Archivo de Replay</label>
              <input 
                type="file" 
                @change="handleFileUpload"
                class="block w-full text-xs text-gray-400
                  file:mr-4 file:py-2 file:px-4
                  file:rounded file:border-0
                  file:text-xs file:font-bold file:uppercase
                  file:bg-[var(--rankit-neon)] file:text-white
                  hover:file:bg-purple-600 file:cursor-pointer file:transition
                  cursor-pointer bg-black/20 rounded border border-white/10"
              />
            </div>
            <button 
              @click="submitReplay"
              :disabled="!replayFile"
              class="px-6 py-2 bg-white text-black font-bold uppercase text-xs rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-lg h-[34px]"
            >
              Procesar
            </button>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>

<style scoped>
:root { --rankit-neon: #bf00ff; }
.font-display { font-family: 'Chakra Petch', sans-serif; }
body { font-family: 'Archivo', sans-serif; }

.brutal-card {
  transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  border: 1px solid;
}
.main-wrapper.bg-\[\#050505\] .brutal-card { background: #0a0a0a; border-color: #333; }
.main-wrapper:not(.bg-\[\#050505\]) .brutal-card { background: #ffffff; border-color: #e5e5e5; box-shadow: 4px 4px 0px #00000010; }
.brutal-card:hover { border-color: var(--rankit-neon); transform: translate(-2px, -2px); }

.btn-skew {
  background-color: var(--rankit-neon);
  color: white;
  transform: skewX(-10deg);
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  border: 0;
}
.btn-skew:hover { background-color: white; color: black; box-shadow: 0 0 15px var(--rankit-neon); }
.btn-content { transform: skewX(10deg); }

.profile-avatar-container {
  width: 96px; height: 96px;
  border-radius: 9999px; padding: 0.25rem;
  background: linear-gradient(to right, #06B6D4, var(--rankit-neon));
}
.profile-avatar-img { width: 100%; height: 100%; border-radius: 9999px; object-fit: cover; border: 4px solid; }
.main-wrapper.bg-\[\#050505\] .profile-avatar-img { border-color: #151725; }
.main-wrapper:not(.bg-\[\#050505\]) .profile-avatar-img { border-color: white; }

.animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>