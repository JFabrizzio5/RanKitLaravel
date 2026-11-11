<script setup lang="ts">
/**
 * Mis torneos (jugador): todo lo que el jugador tiene inscrito, en tarjetas
 * grandes y con poco texto. Primero lo que viene, luego lo que ya se jugó.
 * La lista ya llega ordenada y resuelta desde MisInscripciones.
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import EstadoInscripcion from '@/Components/EstadoInscripcion.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import type { EstadoInscripcion as Estado, TorneoInscrito } from '@/types/inscripciones'

const props = defineProps<{ torneos: TorneoInscrito[] }>()

const proximos = computed(() => props.torneos.filter((t) => t.proximo))
const pasados = computed(() => props.torneos.filter((t) => !t.proximo))

const TIPOS: Record<TorneoInscrito['tipo'], string> = {
  semanal: 'Semanal',
  liga: 'Liga',
  torneo: 'Torneo',
}

// Una línea de qué hacer, solo cuando el jugador puede hacer algo.
const AYUDA: Partial<Record<Estado, string>> = {
  pending: 'El organizador tiene que aceptarte.',
  rejected: 'Habla con el organizador.',
}

// 'YYYY-MM-DD' se arma con la hora local: new Date('2026-08-15') la toma como
// UTC y en México saldría el día anterior.
function aFecha(iso: string) {
  const [a, m, d] = iso.split('-').map(Number)
  return new Date(a, m - 1, d)
}

function textoFecha(t: TorneoInscrito) {
  if (!t.fecha) return t.proximo ? 'Fecha por anunciar' : 'Sin fecha'

  const dia = aFecha(t.fecha)
  const hoy = new Date()
  hoy.setHours(0, 0, 0, 0)
  const diferencia = Math.round((dia.getTime() - hoy.getTime()) / 86_400_000)

  if (diferencia === 0) return '¡Hoy!'
  if (diferencia === 1) return 'Mañana'

  // Sin fecha programada, la fecha es la de la última partida que se jugó.
  if (t.fecha_tipo === 'jugada') {
    return `Jugado el ${dia.toLocaleDateString('es-MX', { day: 'numeric', month: 'long' })}`
  }

  const larga = dia.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })
  return larga.charAt(0).toUpperCase() + larga.slice(1)
}

// Una sección vacía no se muestra: si todo ya se jugó, no aparece "Próximos (0)".
const secciones = computed(() => [
  { clave: 'proximos', titulo: 'Próximos', icono: 'ph-rocket-launch', lista: proximos.value },
  { clave: 'pasados', titulo: 'Ya jugados', icono: 'ph-flag-checkered', lista: pasados.value },
].filter((s) => s.lista.length > 0))
</script>

<template>
  <Head title="Mis torneos — Rankit" />

  <AuthenticatedLayout>
    <div class="min-h-[calc(100vh-80px)] bg-gray-50 px-4 py-12 text-gray-900 dark:bg-[#050505] dark:text-white sm:px-6 sm:py-16 lg:px-16" style="font-family: 'Archivo', sans-serif;">
      <div class="mx-auto max-w-5xl">
        <header class="mb-10">
          <p class="text-xs font-bold uppercase tracking-[0.3em] text-neon">Jugador</p>
          <h1 class="mt-2 text-4xl font-black uppercase italic tracking-tighter font-display md:text-5xl">
            Mis <span class="text-neon">torneos</span>
          </h1>
          <p class="mt-3 text-gray-500 dark:text-gray-400">Todos los torneos donde estás inscrito.</p>
        </header>

        <!-- Sin inscripciones -->
        <div v-if="torneos.length === 0" class="brutal-card flex flex-col items-center px-6 py-14 text-center">
          <i class="ph-fill ph-trophy text-7xl text-neon"></i>
          <h2 class="mt-5 text-2xl font-bold uppercase font-display">Aún no tienes torneos</h2>
          <p class="mt-2 text-gray-500 dark:text-gray-400">Inscríbete a uno y aparecerá aquí.</p>
          <div class="mt-8 flex w-full max-w-sm flex-col gap-3">
            <Link href="/semanales" class="flex items-center justify-center gap-2 rounded-full bg-[var(--rankit-neon)] px-6 py-4 text-base font-bold uppercase tracking-wider text-white transition hover:brightness-110">
              <i class="ph-bold ph-lightning"></i> Ver semanales gratis
            </Link>
            <Link :href="route('dashboard')" class="flex items-center justify-center gap-2 rounded-full border border-gray-300 px-6 py-4 text-base font-bold uppercase tracking-wider transition hover:border-[var(--rankit-neon)] dark:border-white/20">
              <i class="ph-bold ph-magnifying-glass"></i> Explorar torneos
            </Link>
          </div>
        </div>

        <template v-else>
          <section v-for="seccion in secciones" :key="seccion.clave" class="mb-14">
            <h2 class="mb-6 flex items-center gap-3 text-2xl font-black uppercase tracking-tight font-display">
              <i class="ph-bold text-neon" :class="seccion.icono"></i>
              {{ seccion.titulo }}
              <span class="rounded-full bg-gray-200 px-3 py-0.5 text-sm text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ seccion.lista.length }}</span>
            </h2>

            <div class="grid gap-6 md:grid-cols-2">
              <article
                v-for="t in seccion.lista"
                :key="t.id"
                class="brutal-card flex flex-col overflow-hidden"
                :class="{ 'opacity-80 hover:opacity-100': !t.proximo }"
              >
                <!-- Banner (o un fondo con trofeo si el torneo no tiene imagen) -->
                <div class="relative flex h-36 items-center justify-center overflow-hidden bg-gradient-to-br from-[#2a0040] via-[#12001c] to-[#050505] sm:h-44">
                  <img v-if="t.banner" :src="t.banner" alt="" class="h-full w-full object-cover" loading="lazy" />
                  <i v-else class="ph-fill ph-trophy text-7xl text-[var(--rankit-neon)] opacity-60"></i>

                  <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                    <span class="rounded-full bg-black/70 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">{{ TIPOS[t.tipo] }}</span>
                    <span class="rounded-full bg-black/70 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">{{ t.juego }}</span>
                    <span v-if="t.privado" class="rounded-full bg-black/70 px-2.5 py-1 text-xs text-white" title="Torneo privado">
                      <i class="ph-fill ph-lock-simple"></i>
                    </span>
                  </div>
                </div>

                <div class="flex flex-1 flex-col p-5">
                  <h3 class="line-clamp-2 text-2xl font-bold uppercase leading-tight font-display">{{ t.nombre }}</h3>

                  <p class="mt-2 flex items-center gap-2 text-base text-gray-600 dark:text-gray-300">
                    <i class="ph-bold ph-calendar-blank text-lg text-neon"></i>
                    {{ textoFecha(t) }}
                  </p>

                  <div class="mt-4">
                    <EstadoInscripcion :estado="t.estado" />
                    <p v-if="AYUDA[t.estado] && t.proximo" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                      {{ AYUDA[t.estado] }}
                    </p>
                  </div>

                  <!-- El botón queda abajo aunque las tarjetas vecinas tengan más texto -->
                  <div class="mt-auto pt-6">
                    <Link
                      :href="t.url"
                      class="flex items-center justify-center gap-2 rounded-full px-6 py-4 text-base font-bold uppercase tracking-wider transition"
                      :class="t.proximo
                        ? 'bg-[var(--rankit-neon)] text-white hover:brightness-110'
                        : 'border border-gray-300 hover:border-[var(--rankit-neon)] dark:border-white/20'"
                    >
                      {{ t.proximo ? 'Ver torneo' : 'Ver resultados' }} <i class="ph-bold ph-arrow-right"></i>
                    </Link>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </template>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
