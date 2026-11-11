<script setup lang="ts">
/**
 * /torneos: todos los torneos públicos para encontrar dónde jugar.
 * Arriba las competencias fijas de Rankit; abajo la lista con búsqueda y
 * filtros (abiertos, próximos, jugados). Va en renglones, no en tarjetas:
 * se lee rápido en celular y aguanta cientos de torneos.
 */
import { Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavVentas from '@/Components/Propuestas/NavVentas.vue'

type Torneo = {
  id: number
  nombre: string
  url: string
  imagen: string | null
  logo: string | null
  estado: 'abierto' | 'proximo' | 'jugado'
  fecha: string | null
  fecha_texto: string | null
  gratis: boolean
  precio: string | null
  inscritos: number
  partidas: number
}

const props = defineProps<{ torneos: Torneo[]; precioBase: number; precioPro: number }>()

const fijas = [
  { href: '/semanales', icon: 'ph-calendar-star', titulo: 'Semanales', desc: 'Gratis, con premios' },
  { href: '/league', icon: 'ph-trophy', titulo: 'Rankit Pro League', desc: 'La liga por temporadas' },
  { href: '/mundial', icon: 'ph-globe-hemisphere-west', titulo: 'Mundial 2026', desc: 'Bracket y estadísticas' },
  { href: '/BellzCup', icon: 'ph-crown-simple', titulo: 'BellzCup', desc: 'De creadores y actores' },
]

const filtros = [
  { valor: 'todos', texto: 'Todos' },
  { valor: 'abierto', texto: 'Inscripciones abiertas' },
  { valor: 'proximo', texto: 'Próximos' },
  { valor: 'jugado', texto: 'Jugados' },
] as const
const inicial = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search).get('estado')
const filtro = ref<(typeof filtros)[number]['valor']>(filtros.some((f) => f.valor === inicial) ? (inicial as 'abierto') : 'todos')
const buscar = ref('')

const cuenta = computed(() => ({
  todos: props.torneos.length,
  abierto: props.torneos.filter((t) => t.estado === 'abierto').length,
  proximo: props.torneos.filter((t) => t.estado === 'proximo').length,
  jugado: props.torneos.filter((t) => t.estado === 'jugado').length,
}))

const sinAcentos = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
const lista = computed(() => {
  const q = sinAcentos(buscar.value.trim())
  return props.torneos.filter((t) => (filtro.value === 'todos' || t.estado === filtro.value) && (!q || sinAcentos(t.nombre).includes(q)))
})

const estadoTexto = { abierto: 'Inscripciones abiertas', proximo: 'Próximo', jugado: 'Jugado' }
function fecha(t: Torneo) {
  if (t.fecha_texto) return t.fecha_texto
  if (!t.fecha) return null
  return new Date(t.fecha).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
}
const inicialNombre = (n: string) => n.trim().charAt(0).toUpperCase() || 'R'
</script>

<template>
  <BaseEditorial titulo="Torneos" sin-intro>
    <NavVentas :precio-base="precioBase" :precio-pro="precioPro" />

    <main class="mx-auto max-w-[1200px] px-5 pb-24 pt-28 sm:px-8 lg:pt-36">
      <p class="kicker">Competencias</p>
      <h1 class="titular mt-3 text-[15vw] sm:text-8xl lg:text-[110px]">Todos los <span class="text-[var(--neon)]">torneos</span></h1>
      <p class="mt-4 max-w-xl text-lg text-[var(--gris)]">Encuentra dónde competir: aparta tu lugar en los abiertos o revisa la tabla de los que ya se jugaron.</p>

      <!-- Competencias fijas de Rankit -->
      <nav class="mt-10 grid gap-px overflow-hidden rounded-xl border border-[var(--linea)] bg-[var(--linea)] sm:grid-cols-2 lg:grid-cols-4" aria-label="Competencias de Rankit">
        <Link v-for="f in fijas" :key="f.href" :href="f.href" class="group flex items-center gap-3 bg-[#0a0a0a] p-4 transition-colors hover:bg-[#111]">
          <i class="ph-fill text-2xl text-[var(--neon)]" :class="f.icon"></i>
          <span class="min-w-0 flex-1">
            <span class="block font-bold uppercase tracking-wide">{{ f.titulo }}</span>
            <span class="block text-sm text-white/50">{{ f.desc }}</span>
          </span>
          <i class="ph-bold ph-arrow-right text-white/30 transition group-hover:translate-x-1 group-hover:text-white"></i>
        </Link>
      </nav>

      <!-- Buscar y filtrar -->
      <div class="mt-12 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Filtrar torneos">
          <button
            v-for="f in filtros" :key="f.valor" type="button" role="tab" :aria-selected="filtro === f.valor"
            class="shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition"
            :class="filtro === f.valor ? 'border-[var(--neon)] bg-[var(--neon)] text-white' : 'border-white/15 text-white/70 hover:text-white'"
            @click="filtro = f.valor"
          >{{ f.texto }} <span class="ml-1 opacity-60">{{ cuenta[f.valor] }}</span></button>
        </div>
        <label class="relative block lg:w-80">
          <i class="ph-bold ph-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-white/40"></i>
          <input v-model="buscar" type="search" placeholder="Buscar torneo" class="w-full rounded-full border border-white/15 bg-white/[.04] py-2.5 pl-10 pr-4 text-sm text-white outline-none placeholder:text-white/40 focus:border-[var(--neon)]" />
        </label>
      </div>

      <!-- Lista -->
      <ul v-if="lista.length" class="mt-6 divide-y divide-[var(--linea)] border-y border-[var(--linea)]">
        <li v-for="t in lista" :key="t.id">
          <Link :href="t.url" class="group flex items-center gap-4 py-4 transition-colors hover:bg-white/[.02] sm:gap-5">
            <span class="relative h-14 w-24 shrink-0 overflow-hidden rounded-lg bg-[#111] sm:h-16 sm:w-28">
              <img v-if="t.imagen" :src="t.imagen" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async" />
              <img v-else-if="t.logo" :src="t.logo" alt="" class="h-full w-full object-contain p-2" loading="lazy" decoding="async" />
              <span v-else class="titular grid h-full w-full place-items-center bg-gradient-to-br from-[#2a0040] to-[#0a0a0a] text-3xl text-white/80">{{ inicialNombre(t.nombre) }}</span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-lg font-bold group-hover:text-[var(--neon)]">{{ t.nombre }}</span>
              <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/50">
                <span v-if="fecha(t)" class="flex items-center gap-1.5"><i class="ph ph-calendar-blank"></i>{{ fecha(t) }}</span>
                <span v-if="t.inscritos" class="flex items-center gap-1.5"><i class="ph ph-users"></i>{{ t.inscritos }} inscritos</span>
                <span v-if="t.partidas" class="flex items-center gap-1.5"><i class="ph ph-game-controller"></i>{{ t.partidas }} partidas</span>
                <span v-if="t.gratis" class="text-emerald-400">Gratis</span>
              </span>
            </span>
            <span class="hidden shrink-0 items-center gap-2 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider sm:flex"
                  :class="t.estado === 'abierto' ? 'bg-emerald-500/15 text-emerald-400' : t.estado === 'proximo' ? 'bg-[var(--neon)]/15 text-[#d77bff]' : 'bg-white/5 text-white/50'">
              <span v-if="t.estado === 'abierto'" class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>{{ estadoTexto[t.estado] }}
            </span>
            <i class="ph-bold ph-arrow-right shrink-0 text-white/30 transition group-hover:translate-x-1 group-hover:text-white"></i>
          </Link>
        </li>
      </ul>
      <div v-else class="mt-6 rounded-xl border border-dashed border-white/15 px-6 py-16 text-center">
        <p class="text-lg font-semibold">{{ buscar ? 'No hay torneos con ese nombre' : 'No hay torneos en esta lista por ahora' }}</p>
        <p class="mt-2 text-white/50">Mira los <Link href="/semanales" class="text-white underline decoration-[var(--neon)] underline-offset-4">semanales</Link>: hay uno cada semana y es gratis.</p>
      </div>

      <!-- Para organizadores -->
      <div class="mt-16 flex flex-col items-start justify-between gap-6 rounded-2xl border border-[var(--linea)] bg-[#0a0a0a] p-8 sm:flex-row sm:items-center">
        <div>
          <p class="titular text-3xl sm:text-4xl">¿Organizas un torneo?</p>
          <p class="mt-2 text-white/55">Inscripciones, tabla en vivo y widget para tu stream. Desde ${{ precioBase.toLocaleString('es-MX') }}, pago único.</p>
        </div>
        <Link href="/contratar/base" class="boton boton-lleno shrink-0"><span>Crear mi torneo</span></Link>
      </div>
    </main>
  </BaseEditorial>
</template>
