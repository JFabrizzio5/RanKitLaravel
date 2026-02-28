<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

const props = defineProps<{
  tournaments: any[]
  game: string  // 'lol' | 'valorant'
}>()

const gameLabel = computed(() => props.game === 'valorant' ? 'Valorant' : 'League of Legends')
const gameIcon = computed(() => props.game === 'valorant' ? '🎯' : '⚔️')
const gameNeon = computed(() => '#bf00ff')

const gameBack = computed(() => props.game === 'valorant' ? '#game-selector' : '#')

const showCreate = ref(false)

const form = useForm({
  name: '',
  game: props.game,
  format: 'swiss_elimination' as 'elimination' | 'swiss_elimination',
  swiss_rounds_total: 3,
  elimination_teams: 4,
})

function createTournament() {
  form.post(route('lol.store'), {
    onSuccess: () => {
      showCreate.value = false
      form.reset()
      form.game = props.game
      form.format = 'swiss_elimination'
      form.swiss_rounds_total = 3
      form.elimination_teams = 4
    }
  })
}

function deleteTournament(id: number) {
  if (!confirm('¿Eliminar este torneo y todos sus datos?')) return
  router.delete(route('lol.destroy', id), { preserveScroll: true })
}

function phaseLabel(t: any) {
  if (t.format === 'elimination') {
    return t.phase === 'done' ? '🏆 Finalizado' : '🔵 Eliminación'
  }
  if (t.phase === 'pending') return '⏳ Sin iniciar'
  if (t.phase === 'swiss') return '⚔️ Fase Suiza'
  if (t.phase === 'elimination') return '🔵 Eliminación'
  if (t.phase === 'done') return '🏆 Finalizado'
  return t.phase
}

function formatLabel(f: string) {
  return f === 'swiss_elimination' ? 'Suiza + Eliminación' : 'Eliminación Directa'
}
</script>

<template>

  <Head :title="`Torneos ${gameLabel} — RanKit`">
    <link
      href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,300;0,400;0,600;0,700;1,700&family=Archivo:wght@300;400;600;800&display=swap"
      rel="stylesheet" />
  </Head>

  <div class="min-h-screen bg-[#050505] text-white pb-20 overflow-x-hidden" style="font-family: 'Archivo', sans-serif;">

    <!-- Background grid -->
    <div class="fixed inset-0 pointer-events-none opacity-[0.03]"
      style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;">
    </div>

    <!-- Top bar -->
    <div
      class="fixed top-0 left-0 w-full z-50 flex items-center justify-between px-6 lg:px-16 h-14 border-b border-white/5 bg-[#050505]/90 backdrop-blur-md">
      <div class="flex items-center gap-4">
        <Link href="/game-selector"
          class="flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white transition">
          <span>←</span> Juegos
        </Link>
        <div class="w-px h-4 bg-white/10"></div>
        <span class="text-xs font-bold uppercase tracking-widest" :style="{ color: gameNeon }">
          {{ gameIcon }} {{ gameLabel }}
        </span>
      </div>
      <button @click="showCreate = true"
        class="px-4 py-1.5 text-[11px] font-black uppercase tracking-wider rounded border transition-all duration-200"
        :style="{ color: gameNeon, borderColor: gameNeon, boxShadow: `0 0 0 0 ${gameNeon}` }"
        @mouseenter="(e: any) => e.currentTarget.style.boxShadow = `0 0 12px ${gameNeon}40`"
        @mouseleave="(e: any) => e.currentTarget.style.boxShadow = ''">
        + Crear torneo
      </button>
    </div>

    <!-- Content -->
    <main class="max-w-5xl mx-auto px-6 pt-28">

      <!-- Header -->
      <div class="mb-10">
        <h1 class="text-4xl font-black uppercase tracking-tighter" style="font-family: 'Chakra Petch', sans-serif">
          Mis torneos
        </h1>
        <p class="text-gray-500 text-sm mt-1">{{ props.tournaments.length }} torneo(s) de {{ gameLabel }}</p>
      </div>

      <!-- Empty state -->
      <div v-if="props.tournaments.length === 0"
        class="border border-dashed border-white/10 rounded-2xl p-16 text-center space-y-4">
        <div class="text-5xl">{{ gameIcon }}</div>
        <p class="text-gray-400 text-sm">No tienes torneos de {{ gameLabel }} aún.</p>
        <button @click="showCreate = true"
          class="px-6 py-2 text-sm font-bold uppercase rounded border text-white transition-all"
          :style="{ borderColor: gameNeon, color: gameNeon }">
          Crear primer torneo
        </button>
      </div>

      <!-- Tournaments grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="t in props.tournaments" :key="t.id"
          class="group relative bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden hover:border-white/20 transition-all duration-300 hover:-translate-y-0.5">
          <!-- Neon top bar -->
          <div class="h-[2px] w-full" :style="{ background: gameNeon, opacity: 0.6 }"></div>

          <div class="p-5 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <h3 class="font-black text-base uppercase leading-tight tracking-tight flex-1"
                style="font-family: 'Chakra Petch', sans-serif">
                {{ t.name }}
              </h3>
              <button @click.stop="deleteTournament(t.id)"
                class="text-gray-600 hover:text-red-400 transition text-sm flex-shrink-0"
                title="Eliminar torneo">✕</button>
            </div>

            <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase">
              <span class="px-2 py-0.5 rounded bg-white/5 text-gray-400">{{ formatLabel(t.format) }}</span>
              <span class="px-2 py-0.5 rounded" :style="{ background: `${gameNeon}20`, color: gameNeon }">
                {{ phaseLabel(t) }}
              </span>
            </div>

            <p class="text-[10px] text-gray-600 font-mono">
              {{ t.format === 'swiss_elimination' ? `${t.swiss_rounds_total} rondas Swiss → Top ${t.elimination_teams}`
                : `Bracket desde inicio` }}
            </p>

            <Link :href="route('lol.show', t.id)"
              class="block w-full text-center py-2 text-[11px] font-bold uppercase rounded border transition-all duration-200 mt-2"
              :style="{ borderColor: `${gameNeon}50`, color: gameNeon }"
              @mouseenter="(e: any) => e.currentTarget.style.background = `${gameNeon}15`"
              @mouseleave="(e: any) => e.currentTarget.style.background = 'transparent'">
              Abrir panel →
            </Link>
          </div>
        </div>
      </div>
    </main>

    <!-- Create Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showCreate" class="fixed inset-0 z-[100] flex items-center justify-center p-4"
          @click.self="showCreate = false">
          <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>

          <div
            class="relative z-10 w-full max-w-md bg-[#0e0e0e] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
              <h2 class="font-black uppercase text-lg tracking-tight" style="font-family: 'Chakra Petch', sans-serif">
                Nuevo torneo
              </h2>
              <button @click="showCreate = false"
                class="text-gray-500 hover:text-white transition text-xl leading-none">×</button>
            </div>

            <form @submit.prevent="createTournament" class="p-6 space-y-5">

              <!-- Nombre -->
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">Nombre del
                  torneo</label>
                <input v-model="form.name" type="text" placeholder="Ej: Copa Verano 2026"
                  class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-purple-500 transition"
                  required />
                <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
              </div>

              <!-- Formato -->
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Formato del
                  torneo</label>
                <div class="grid grid-cols-2 gap-2">
                  <button type="button" @click="form.format = 'swiss_elimination'"
                    class="p-3 rounded-lg border text-xs font-bold uppercase text-center transition-all" :class="form.format === 'swiss_elimination'
                      ? 'border-purple-500 bg-purple-500/10 text-purple-400'
                      : 'border-white/10 text-gray-500 hover:border-white/30 hover:text-gray-300'">
                    <div class="text-xl mb-1">⚔️🔵</div>
                    Suiza + Eliminación
                  </button>
                  <button type="button" @click="form.format = 'elimination'"
                    class="p-3 rounded-lg border text-xs font-bold uppercase text-center transition-all" :class="form.format === 'elimination'
                      ? 'border-purple-500 bg-purple-500/10 text-purple-400'
                      : 'border-white/10 text-gray-500 hover:border-white/30 hover:text-gray-300'">
                    <div class="text-xl mb-1">🔵</div>
                    Solo Eliminación
                  </button>
                </div>
              </div>

              <!-- Config Swiss (solo si aplica) -->
              <div v-if="form.format === 'swiss_elimination'" class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">Rondas
                    Swiss</label>
                  <input v-model.number="form.swiss_rounds_total" type="number" min="1" max="10"
                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-purple-500 transition" />
                </div>
                <div>
                  <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">Top equipos →
                    Elim.</label>
                  <input v-model.number="form.elimination_teams" type="number" min="2" max="64"
                    class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-purple-500 transition" />

                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showCreate = false"
                  class="flex-1 py-2 text-xs font-bold uppercase border border-white/10 text-gray-400 rounded-lg hover:border-white/30 transition">
                  Cancelar
                </button>
                <button type="submit" :disabled="form.processing"
                  class="flex-1 py-2 text-xs font-bold uppercase rounded-lg text-white transition-all"
                  :style="{ background: gameNeon, opacity: form.processing ? 0.7 : 1 }">
                  {{ form.processing ? 'Creando...' : 'Crear torneo' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
