<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
//import AppLogo from '@/components/AppLogo.vue'

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

// --- Organizer UI ---
const organizerEnabled = ref(!!props.organizer?.enabled)
const showOrganizer = ref(false)

const match1 = ref(props.organizer?.matchCodes?.match1 ?? '')
const match2 = ref(props.organizer?.matchCodes?.match2 ?? '')
const match3 = ref(props.organizer?.matchCodes?.match3 ?? '')

const copied = ref(false)
const copyWidgetUrl = async () => {
  try {
    await navigator.clipboard.writeText(props.scoreboardWidgetUrl)
    copied.value = true
    setTimeout(() => (copied.value = false), 1200)
  } catch (e) {
    // fallback súper simple
    copied.value = false
    alert('No se pudo copiar. Copia manualmente la URL.')
  }
}
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
            Partida en Curso
          </span>
        </div>
        <div class="w-px h-4 bg-gray-600"></div>
        <span class="text-sm font-bold">
          Neon City Cup - <span class="text-[var(--rankit-neon)]">Team Liquid</span>
        </span>
      </div>

      <div class="flex items-center gap-3">
        <Link
          :href="props.activeTournaments?.[0]?.lobby_url ?? '#'"
          class="px-4 py-1 text-[10px] font-bold tracking-wider uppercase btn-skew"
        >
          <span class="btn-content">Ir al Lobby</span>
        </Link>
      </div>
    </div>

    <!-- Navbar (Offset por Match Bar) -->
    <nav
      class="fixed z-50 flex items-center justify-between w-full h-20 px-6 transition-all duration-300 border-b lg:px-12 backdrop-blur-md top-14"
      :class="isDark ? 'bg-[#050505]/95 border-white/10' : 'bg-white/90 border-gray-200'"
    >
      <Link href="/" class="flex items-center gap-3 cursor-pointer group">
        <div class="w-10 h-10 transition-colors" :class="isDark ? 'text-white' : 'text-black group-hover:text-[var(--rankit-neon)]'">
          <AppLogo />
        </div>
        <span class="text-3xl italic font-bold tracking-tighter uppercase font-display" :class="isDark ? 'text-white' : 'text-black'">
          Rankit
        </span>
      </Link>

      <div class="flex items-center gap-4">
        <button
          @click="toggleLanguage"
          class="flex items-center gap-1 text-xs font-bold border px-2 py-1 rounded hover:border-[var(--rankit-neon)] transition-colors"
          :class="isDark ? 'border-gray-700 text-white' : 'border-gray-300 text-black'"
        >
          <span>{{ currentLang.toUpperCase() }}</span>
        </button>

        <button
          @click="toggleTheme"
          class="p-2 transition-colors border border-transparent rounded-lg"
          :class="isDark ? 'text-gray-400 hover:text-[var(--rankit-neon)] hover:border-gray-700' : 'text-gray-500 hover:text-[var(--rankit-neon)] hover:border-gray-300'"
        >
          <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
          <i v-else class="text-xl ph-fill ph-moon"></i>
        </button>
      </div>
    </nav>

    <main class="grid grid-cols-1 gap-8 px-6 py-8 mx-auto max-w-7xl lg:px-8 lg:grid-cols-12 pt-44">
      <!-- LEFT -->
      <aside class="space-y-6 lg:col-span-3">
        <!-- Profile Card -->
        <div class="relative p-6 overflow-hidden text-center brutal-card group">
          <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-[var(--rankit-neon)]/20 to-transparent"></div>
          <div class="relative z-10">
            <div class="relative flex items-center justify-center mx-auto mb-4 profile-avatar-container">
              <img :src="props.profile?.avatar" alt="Avatar" class="profile-avatar-img" />
              <div
                class="absolute w-5 h-5 bg-green-500 border-2 rounded-full bottom-1 right-1 animate-pulse"
                :class="isDark ? 'border-[#151725]' : 'border-white'"
              ></div>
            </div>

            <h1 class="text-2xl font-bold font-display" :class="isDark ? 'text-white' : 'text-black'">
              {{ props.profile?.username ?? 'user' }}
            </h1>

            <p class="flex items-center justify-center gap-1 mb-4 text-xs" :class="isDark ? 'text-gray-400' : 'text-gray-500'">
              {{ props.profile?.country ?? '—' }} • Miembro desde {{ props.profile?.member_since ?? '—' }}
            </p>

            <div class="flex justify-center gap-2 mb-6">
              <span class="text-[var(--rankit-neon)] px-2 py-1 rounded text-[10px] font-bold uppercase border border-[var(--rankit-neon)]/50">
                {{ props.profile?.badge ?? 'Player' }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 p-3 text-left border rounded-lg" :class="isDark ? 'bg-black/30 border-gray-800' : 'bg-gray-100 border-gray-200'">
              <div>
                <div class="text-[10px] uppercase font-bold text-gray-500">Win Rate</div>
                <div class="font-mono font-bold text-green-500">{{ props.profile?.win_rate ?? 0 }}%</div>
              </div>
              <div>
                <div class="text-[10px] uppercase font-bold text-gray-500">Torneos</div>
                <div class="font-mono font-bold" :class="isDark ? 'text-white' : 'text-black'">
                  {{ props.profile?.tournaments ?? 0 }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ✅ ORGANIZADOR: CTA -->
        <div class="p-6 brutal-card text-left">
          <div class="flex items-center justify-between mb-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Organizer</div>
              <div class="text-lg font-bold font-display" :class="isDark ? 'text-white' : 'text-black'">
                Herramientas de Torneo
              </div>
            </div>

            <button
              @click="organizerEnabled = !organizerEnabled"
              class="text-[10px] font-bold uppercase px-2 py-1 rounded border transition"
              :class="organizerEnabled ? 'border-green-500 text-green-400' : 'border-gray-600 text-gray-400'"
            >
              {{ organizerEnabled ? 'Activo' : 'Inactivo' }}
            </button>
          </div>

          <button
            class="block w-full py-3 text-xs font-bold tracking-wider text-center uppercase btn-skew"
            @click="showOrganizer = !showOrganizer"
            :disabled="!organizerEnabled"
            :class="!organizerEnabled ? 'opacity-50 cursor-not-allowed' : ''"
          >
            <span class="btn-content">Organizar Torneo</span>
          </button>

          <div v-if="showOrganizer" class="mt-4 space-y-3">
            <div class="text-[10px] uppercase font-bold text-gray-500">Códigos de partidas</div>

            <div class="space-y-2">
              <div>
                <label class="text-[10px] font-bold uppercase text-gray-500">Partida 1</label>
                <input
                  v-model="match1"
                  type="text"
                  placeholder="Ej: M-1001"
                  class="w-full mt-1 px-3 py-2 rounded border text-sm"
                  :class="isDark ? 'bg-black/30 border-gray-700 text-white' : 'bg-white border-gray-300 text-black'"
                />
              </div>

              <div>
                <label class="text-[10px] font-bold uppercase text-gray-500">Partida 2</label>
                <input
                  v-model="match2"
                  type="text"
                  placeholder="Ej: M-1002"
                  class="w-full mt-1 px-3 py-2 rounded border text-sm"
                  :class="isDark ? 'bg-black/30 border-gray-700 text-white' : 'bg-white border-gray-300 text-black'"
                />
              </div>

              <div>
                <label class="text-[10px] font-bold uppercase text-gray-500">Partida 3</label>
                <input
                  v-model="match3"
                  type="text"
                  placeholder="Ej: M-1003"
                  class="w-full mt-1 px-3 py-2 rounded border text-sm"
                  :class="isDark ? 'bg-black/30 border-gray-700 text-white' : 'bg-white border-gray-300 text-black'"
                />
              </div>
            </div>

            <div class="pt-2 border-t" :class="isDark ? 'border-gray-800' : 'border-gray-200'">
              <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <div class="text-[10px] uppercase font-bold text-gray-500">URL Widget Tabla (OBS)</div>
                  <div class="text-xs break-all" :class="isDark ? 'text-gray-300' : 'text-gray-700'">
                    {{ props.scoreboardWidgetUrl }}
                  </div>
                </div>

                <button
                  @click="copyWidgetUrl"
                  class="px-3 py-2 text-[10px] font-bold uppercase rounded border hover:border-[var(--rankit-neon)] transition"
                  :class="isDark ? 'border-gray-700 text-white' : 'border-gray-300 text-black'"
                >
                  {{ copied ? 'Copiado' : 'Copiar' }}
                </button>
              </div>
            </div>

            <!-- (Maqueta) Guardar -->
            <button
              class="block w-full py-3 text-xs font-bold tracking-wider text-center uppercase btn-skew"
              title="Maqueta: después lo conectamos a un POST"
            >
              <span class="btn-content">Guardar Config</span>
            </button>
          </div>
        </div>
      </aside>

      <!-- CENTER -->
      <div class="space-y-8 lg:col-span-6">
        <div class="flex gap-6 pb-1 border-b" :class="isDark ? 'border-gray-800' : 'border-gray-300'">
          <button
            @click="switchTab('active')"
            :class="[
              'font-bold text-sm pb-3 transition uppercase tracking-wider',
              activeTab === 'active'
                ? 'text-[var(--rankit-neon)] border-b-2 border-[var(--rankit-neon)]'
                : 'text-gray-500 hover:text-gray-800 dark:hover:text-white',
            ]"
          >
            Torneos Activos
          </button>

          <button
            @click="switchTab('history')"
            :class="[
              'font-bold text-sm pb-3 transition uppercase tracking-wider',
              activeTab === 'history'
                ? 'text-[var(--rankit-neon)] border-b-2 border-[var(--rankit-neon)]'
                : 'text-gray-500 hover:text-gray-800 dark:hover:text-white',
            ]"
          >
            Historial
          </button>
        </div>

        <div v-show="activeTab === 'active'" class="space-y-4">
          <div
            v-for="t in props.activeTournaments"
            :key="t.match_code"
            class="p-6 border-l-4 brutal-card border-l-green-500"
          >
            <div class="flex items-start justify-between mb-4">
              <div class="flex gap-4">
                <div class="flex items-center justify-center w-12 h-12 bg-gray-800 rounded">
                  <i class="text-2xl text-white fas fa-gamepad"></i>
                </div>
                <div>
                  <h3 class="text-lg font-bold uppercase font-display" :class="isDark ? 'text-white' : 'text-black'">
                    {{ t.name }}
                  </h3>
                  <p class="flex items-center gap-1 text-xs font-bold text-green-500">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                    {{ t.status }}
                  </p>
                </div>
              </div>
            </div>

            <Link :href="t.lobby_url" class="block w-full py-3 text-xs font-bold tracking-wider text-center uppercase btn-skew">
              <span class="btn-content">Ir a Sala de Juego</span>
            </Link>
          </div>
        </div>

        <div v-show="activeTab === 'history'" class="space-y-4">
          <div class="p-6 brutal-card">
            <div class="text-sm font-bold" :class="isDark ? 'text-white' : 'text-black'">Historial</div>
            <div class="text-xs text-gray-500 mt-1">Maqueta: aquí irían torneos terminados, stats, etc.</div>
          </div>
        </div>
      </div>

      <!-- RIGHT (placeholder) -->
      <div class="space-y-6 lg:col-span-3">
        <div class="p-6 brutal-card">
          <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Panel</div>
          <div class="text-sm mt-1" :class="isDark ? 'text-gray-300' : 'text-gray-700'">
            Maqueta: aquí puedes poner notificaciones, amigos, ranking, etc.
          </div>
        </div>
      </div>
    </main>
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
.brutal-card:hover { border-color: var(--rankit-neon); transform: translate(-4px, -4px); }
.main-wrapper.bg-\[\#050505\] .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon); }
.main-wrapper:not(.bg-\[\#050505\]) .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon), 6px 6px 0px 2px black; }

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
.main-wrapper:not(.bg-\[\#050505\]) .btn-skew:hover { background-color: black; color: white; box-shadow: 4px 4px 0px rgba(0,0,0,0.2); }
.btn-content { transform: skewX(10deg); }

.profile-avatar-container {
  width: 96px; height: 96px;
  border-radius: 9999px; padding: 0.25rem;
  background: linear-gradient(to right, #06B6D4, var(--rankit-neon));
}
.profile-avatar-img { width: 100%; height: 100%; border-radius: 9999px; object-fit: cover; border: 4px solid; }
.main-wrapper.bg-\[\#050505\] .profile-avatar-img { border-color: #151725; }
.main-wrapper:not(.bg-\[\#050505\]) .profile-avatar-img { border-color: white; }
</style>
