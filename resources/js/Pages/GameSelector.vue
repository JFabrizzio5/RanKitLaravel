<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const games = [
  {
    id: 'fortnite',
    name: 'Fortnite',
    subtitle: 'Battle Royale',
    description: 'Panel completo de torneos Fortnite con análisis de replays y leaderboard en vivo.',
    color: '#bf00ff',
    gradient: 'from-purple-950 via-purple-900 to-indigo-950',
    borderColor: 'border-purple-500',
    glowColor: 'shadow-purple-500/40',
    neon: '#bf00ff',
    icon: '🏆',
    tag: 'ONLINE',
    tagColor: 'bg-purple-600',
    href: null,
    action: 'fortnite',
  },
  {
    id: 'lol',
    name: 'League of Legends',
    subtitle: 'MOBA • 5v5',
    description: 'Organiza torneos con formato Suizo o Eliminación Directa. Gestiona equipos, genera brackets y registra resultados.',
    color: '#bf00ff',
    gradient: 'from-purple-950 via-fuchsia-950 to-purple-950',
    borderColor: 'border-purple-500',
    glowColor: 'shadow-purple-500/40',
    neon: '#bf00ff',
    icon: '⚔️',
    tag: 'NUEVO',
    tagColor: 'bg-purple-600',
    href: null,
    action: 'lol',
  },
  {
    id: 'valorant',
    name: 'Valorant',
    subtitle: 'Tactical Shooter • 5v5',
    description: 'Organiza torneos con formato Suizo o Eliminación Directa. Brackets automáticos y gestión de equipos.',
    color: '#bf00ff',
    gradient: 'from-purple-950 via-indigo-950 to-purple-950',
    borderColor: 'border-purple-500',
    glowColor: 'shadow-purple-500/40',
    neon: '#bf00ff',
    icon: '🎯',
    tag: 'NUEVO',
    tagColor: 'bg-purple-600',
    href: null,
    action: 'valorant',
  },
  {
    id: 'football',
    name: 'Fútbol',
    subtitle: 'Torneos • Ligas',
    description: 'Organiza torneos de eliminación directa o ligas de fútbol. Controla equipos, goleadores y tarjetas.',
    color: '#10b981',
    gradient: 'from-emerald-950 via-teal-950 to-emerald-950',
    borderColor: 'border-emerald-500',
    glowColor: 'shadow-emerald-500/40',
    neon: '#10b981',
    icon: '⚽',
    tag: 'NUEVO',
    tagColor: 'bg-emerald-600',
    href: null,
    action: 'football',
  },
]


const hoveredGame = ref<string | null>(null)
const loading = ref<string | null>(null)

function navigate(game: typeof games[0]) {
  loading.value = game.id
  if (game.action === 'fortnite') {
    window.location.href = '/admin/jangel'
  } else if (game.action === 'football') {
    window.location.href = '/football'
  } else {
    window.location.href = `/lol?game=${game.action}`
  }
}
</script>

<template>

  <Head title="Seleccionar Juego — RanKit">
    <link
      href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,300;0,400;0,600;0,700;1,700&family=Archivo:wght@300;400;600;800&display=swap"
      rel="stylesheet" />
  </Head>

  <div class="min-h-screen bg-[#050505] text-white overflow-hidden selection:bg-yellow-500 selection:text-black"
    style="font-family: 'Archivo', sans-serif;">

    <!-- Background grid -->
    <div class="fixed inset-0 pointer-events-none opacity-[0.03]"
      style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;">
    </div>

    <!-- Top bar -->
    <div
      class="fixed top-0 left-0 w-full z-50 flex items-center justify-between px-6 lg:px-16 h-16 border-b border-white/5 bg-[#050505]/80 backdrop-blur-md">
      <div class="flex items-center gap-3">
        <svg class="w-7 h-7 text-white" viewBox="0 0 100 100" fill="none">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff" />

        </svg>
        <span class="text-xl font-bold uppercase tracking-tighter"
          style="font-family: 'Chakra Petch', sans-serif">RanKit</span>
      </div>
      <Link href="/profile"
        class="text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white transition px-3 py-1 border border-white/10 hover:border-white/30 rounded">
        Mi Perfil
      </Link>
    </div>

    <!-- Main content -->
    <main class="flex flex-col items-center justify-center min-h-screen px-6 pt-20 pb-12">

      <div class="text-center mb-16 space-y-3">
        <p class="text-xs font-bold tracking-[0.3em] uppercase text-gray-500">Panel de Torneos</p>
        <h1 class="text-5xl md:text-6xl font-black uppercase leading-none tracking-tighter"
          style="font-family: 'Chakra Petch', sans-serif">
          Elige tu
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-fuchsia-500"> juego</span>

        </h1>
        <p class="text-gray-400 text-sm max-w-md mx-auto">Selecciona el juego para gestionar tus torneos</p>
      </div>

      <!-- Banner Rankit League -->
      <div class="w-full max-w-6xl mb-12">
        <div @click="router.visit('/league')" class="relative overflow-hidden rounded-3xl cursor-pointer group border border-purple-500/30 hover:border-purple-500/80 transition-all duration-500" style="background: #030108;">
            <div class="absolute inset-0 bg-[url('https://s2.elespanol.com/2022/02/17/invertia/disruptores/ecosistema-startup/startups/650945168_221956806_1706x1280.jpg')] bg-cover bg-center opacity-30 group-hover:opacity-40 transition-opacity duration-700 blur-[2px] group-hover:blur-0"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#030108] via-[#030108]/80 to-transparent"></div>
            <div class="relative z-10 p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-8">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="bg-purple-600 text-white text-[10px] font-black uppercase px-3 py-1 rounded-full tracking-widest animate-pulse shadow-[0_0_15px_#9b30ff]">OFICIAL</span>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest" style="font-family: 'Chakra Petch', sans-serif">Circuito Competitivo</span>
                    </div>
                    <h2 class="text-4xl md:text-6xl font-black uppercase italic tracking-tighter mb-2" style="font-family: 'Black Ops One', impact, sans-serif;">
                        RANKIT<span class="text-[#9b30ff]">.PRO</span> <span class="text-white">LEAGUE</span>
                    </h2>
                    <p class="text-sm md:text-base text-gray-300 max-w-xl font-light">
                        El ecosistema competitivo oficial de Fortnite en solitario. Descubre el formato, las reglas y regístrate para la próxima temporada.
                    </p>
                </div>
                <div class="shrink-0">
                    <button class="px-8 py-4 bg-[#9b30ff] text-white font-black uppercase tracking-widest text-sm rounded transition-all duration-300 transform group-hover:scale-105 group-hover:shadow-[0_0_30px_-5px_#9b30ff] flex items-center gap-2" style="transform: skewX(-10deg);">
                        <span style="transform: skewX(10deg);" class="flex items-center gap-2">Explorar Liga <span class="group-hover:translate-x-2 transition-transform">→</span></span>
                    </button>
                </div>
            </div>
        </div>
      </div>

      <!-- Banner Mundial 2026 -->
      <div class="w-full max-w-6xl mb-12">
        <div @click="router.visit('/mundial')" class="relative overflow-hidden rounded-3xl cursor-pointer group border border-emerald-500/30 hover:border-emerald-500/80 transition-all duration-500 bg-[#001f11]">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1518605368461-1ee7c532066d?auto=format&fit=crop&q=80')] bg-cover bg-center opacity-20 group-hover:opacity-30 transition-opacity duration-700 blur-[1px]"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#001f11] via-[#001f11]/80 to-transparent"></div>
            <div class="relative z-10 p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="bg-emerald-600 text-white text-[10px] font-black uppercase px-3 py-1 rounded-full tracking-widest animate-pulse shadow-[0_0_15px_#10b981]">EN VIVO</span>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest" style="font-family: 'Chakra Petch', sans-serif">Fútbol Global</span>
                    </div>
                    <h2 class="text-3xl md:text-5xl font-black uppercase italic tracking-tighter mb-2" style="font-family: 'Black Ops One', impact, sans-serif;">
                        COPA <span class="text-[#10b981]">MUNDIAL</span> <span class="text-white">2026</span>
                    </h2>
                    <p class="text-sm md:text-base text-gray-300 max-w-xl font-light">
                        Sigue todas las estadísticas, tablas de grupos y resultados del mundial en tiempo real impulsado por API Oficial.
                    </p>
                </div>
                <div class="shrink-0">
                    <button class="px-6 py-3 bg-[#10b981] text-white font-black uppercase tracking-widest text-sm rounded transition-all duration-300 transform group-hover:scale-105 group-hover:shadow-[0_0_30px_-5px_#10b981] flex items-center gap-2" style="transform: skewX(-10deg);">
                        <span style="transform: skewX(10deg);" class="flex items-center gap-2">Ver Estadísticas <span class="group-hover:translate-x-2 transition-transform">→</span></span>
                    </button>
                </div>
            </div>
        </div>
      </div>

      <!-- Game cards grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 w-full max-w-6xl">
        <button v-for="game in games" :key="game.id" @click="navigate(game)" @mouseenter="hoveredGame = game.id"
          @mouseleave="hoveredGame = null" :disabled="loading !== null"
          class="relative group text-left overflow-hidden rounded-2xl border transition-all duration-300 cursor-pointer focus:outline-none"
          :class="[
            `border-${game.id === 'fortnite' ? 'purple' : game.id === 'lol' ? 'yellow' : 'red'}-500/30`,
            hoveredGame === game.id
              ? `border-${game.id === 'fortnite' ? 'purple' : game.id === 'lol' ? 'yellow' : 'red'}-500/70 shadow-xl shadow-${game.id === 'fortnite' ? 'purple' : game.id === 'lol' ? 'yellow' : 'red'}-500/20 -translate-y-1`
              : 'hover:-translate-y-1',
            loading === game.id ? 'opacity-70' : ''
          ]" style="background: #0a0a0a;">
          <!-- Gradient overlay -->
          <div class="absolute inset-0 opacity-20 transition-opacity duration-300 pointer-events-none"
            :class="[`bg-gradient-to-br`, game.gradient, hoveredGame === game.id ? 'opacity-30' : 'opacity-10']"></div>

          <!-- Neon top bar -->
          <div class="absolute top-0 left-0 right-0 h-[2px] transition-all duration-300"
            :style="{ background: hoveredGame === game.id ? game.neon : 'transparent', boxShadow: hoveredGame === game.id ? `0 0 20px ${game.neon}` : 'none' }">
          </div>

          <!-- Content -->
          <div class="relative z-10 p-7 flex flex-col gap-5">
            <!-- Tag -->
            <div class="flex items-center justify-between">
              <span class="text-3xl">{{ game.icon }}</span>
              <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full text-white"
                :class="game.tagColor">
                {{ game.tag }}
              </span>
            </div>

            <!-- Title -->
            <div>
              <h2 class="text-xl font-black uppercase leading-tight tracking-tight"
                style="font-family: 'Chakra Petch', sans-serif">
                {{ game.name }}
              </h2>
              <p class="text-xs font-bold uppercase tracking-widest mt-1" :style="{ color: game.neon }">
                {{ game.subtitle }}
              </p>
            </div>

            <!-- Description -->
            <p class="text-xs text-gray-400 leading-relaxed">
              {{ game.description }}
            </p>

            <!-- CTA -->
            <div class="flex items-center gap-2 mt-2">
              <span v-if="loading === game.id" class="flex items-center gap-2 text-xs font-bold uppercase"
                :style="{ color: game.neon }">
                <span class="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                Cargando...
              </span>
              <span v-else
                class="text-xs font-bold uppercase tracking-wider transition-all duration-300 flex items-center gap-1"
                :style="{ color: game.neon }">
                Ir al panel
                <span class="inline-block transition-transform duration-200"
                  :class="hoveredGame === game.id ? 'translate-x-1' : ''">→</span>
              </span>
            </div>
          </div>
        </button>
      </div>

    </main>
  </div>
</template>
