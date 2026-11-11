<script setup lang="ts">
/**
 * "Entra y compite": lo primero para quien llega a jugar. Eventos reales con
 * su estado (fecha, lugares apartados, bolsa) y el botón para reservar lugar;
 * la cuarta tarjeta es para quien quiere organizar el suyo.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { medios, type Eventos } from './medios'

const props = defineProps<{ eventos: Eventos; precioBase: number }>()

const page = usePage()
const conSesion = computed(() => !!(page.props.auth as { user?: unknown } | undefined)?.user)

const s = computed(() => props.eventos.semanal)
const lugares = computed(() => Math.min(100, Math.round((s.value.inscritos / Math.max(1, s.value.cupo)) * 100)))
const fechaSemanal = computed(() => {
  if (s.value.fecha_label) return s.value.fecha_label
  if (!s.value.fecha) return 'Fecha por anunciar'
  return new Date(s.value.fecha).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })
})
const pesos = (n: number) => '$' + n.toLocaleString('es-MX')
</script>

<template>
  <section id="eventos" class="reticula relative scroll-mt-20 border-y border-[var(--linea)]">
    <div class="mx-auto max-w-[1600px] px-5 py-24 sm:px-8 lg:py-28">
      <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
        <div>
          <p class="kicker" data-anim="subir">Para jugar</p>
          <h2 data-anim="letras" class="titular mt-3 text-[13vw] sm:text-8xl lg:text-[110px]">Entra y <span class="text-[var(--neon)]">compite</span></h2>
        </div>
        <div data-anim="subir" class="max-w-sm">
          <p class="text-lg text-[var(--gris)]">Elige un evento, aparta tu lugar y juega. Los resultados salen en la tabla en vivo.</p>
          <Link href="/torneos" class="ui mt-4 inline-flex items-center gap-2 text-white underline decoration-[var(--neon)] decoration-2 underline-offset-8 hover:text-[var(--neon)]">Ver todos los torneos <i class="ph-bold ph-arrow-right"></i></Link>
        </div>
      </div>

      <div data-anim="escalonar" class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <!-- Semanales -->
        <article class="tarjeta flex flex-col !border-[var(--neon)]">
          <div class="relative aspect-[16/10] overflow-hidden">
            <img :src="medios.mirada.poster" alt="Jugador de Fortnite concentrado" class="h-full w-full object-cover" loading="lazy" />
            <span class="absolute left-3 top-3 bg-[var(--neon)] px-2 py-1 font-mono text-[11px] font-bold uppercase">Gratis</span>
          </div>
          <div class="flex flex-1 flex-col p-5">
            <h3 class="titular text-3xl">Semanales</h3>
            <p class="mt-2 font-mono text-[11px] uppercase leading-relaxed text-gray-400">Fortnite · Cada semana · Bolsa {{ pesos(s.premio_base) }}, sube a {{ pesos(s.premio_lleno) }} si se llena</p>

            <div class="mt-5 space-y-2">
              <p class="flex items-center gap-2 text-sm font-semibold capitalize"><i class="ph-bold ph-calendar-blank text-[var(--neon)]"></i>{{ fechaSemanal }}</p>
              <template v-if="s.abierto">
                <div class="h-1.5 overflow-hidden bg-white/10"><div class="h-full bg-[var(--neon)]" :style="{ width: lugares + '%' }"></div></div>
                <p class="font-mono text-[11px] uppercase text-gray-400">{{ s.inscritos }} de {{ s.cupo }} lugares apartados</p>
              </template>
            </div>

            <div class="mt-auto pt-6">
              <Link :href="s.url" class="boton boton-lleno w-full">
                <span>{{ s.abierto ? 'Reservar lugar' : 'Ver semanales' }}</span>
              </Link>
            </div>
          </div>
        </article>

        <!-- Liga -->
        <article class="tarjeta flex flex-col">
          <div class="relative aspect-[16/10] overflow-hidden">
            <img :src="medios.arena.poster" alt="Arena de la Rankit Pro League" class="h-full w-full object-cover" loading="lazy" />
            <span class="absolute left-3 top-3 bg-white px-2 py-1 font-mono text-[11px] font-bold uppercase text-black">Liga</span>
          </div>
          <div class="flex flex-1 flex-col p-5">
            <h3 class="titular text-3xl">Rankit Pro League</h3>
            <p class="mt-2 font-mono text-[11px] uppercase leading-relaxed text-gray-400">Fortnite solos · Clasificatorios, repechaje y gran final</p>
            <p class="mt-5 flex items-center gap-2 text-sm font-semibold">
              <span class="h-2 w-2 rounded-full" :class="eventos.liga.abierta ? 'bg-emerald-400' : 'bg-gray-500'"></span>
              {{ eventos.liga.abierta ? `Inscripciones abiertas · ${eventos.liga.fase}` : 'Próxima fase por anunciar' }}
            </p>
            <div class="mt-auto pt-6">
              <Link :href="eventos.liga.url" class="boton boton-linea w-full"><span>{{ eventos.liga.abierta ? 'Inscribirme' : 'Ver la liga' }}</span></Link>
            </div>
          </div>
        </article>

        <!-- BellzCup -->
        <article class="tarjeta flex flex-col">
          <div class="relative aspect-[16/10] overflow-hidden bg-black">
            <img :src="medios.bellz" alt="BellzCup" class="h-full w-full object-cover" loading="lazy" />
            <span class="absolute left-3 top-3 bg-white px-2 py-1 font-mono text-[11px] font-bold uppercase text-black">Esports</span>
          </div>
          <div class="flex flex-1 flex-col p-5">
            <h3 class="titular text-3xl">BellzCup</h3>
            <p class="mt-2 font-mono text-[11px] uppercase leading-relaxed text-gray-400">Battle royale · Crossplay · Premios en efectivo</p>
            <div class="mt-auto pt-6">
              <Link href="/BellzCup" class="boton boton-linea w-full"><span>Entrar</span></Link>
            </div>
          </div>
        </article>

        <!-- Crea el tuyo -->
        <article class="flex flex-col justify-between border border-dashed border-white/25 p-6 transition hover:border-[var(--neon)]">
          <div>
            <span class="flex h-14 w-14 items-center justify-center border border-white/20 text-3xl text-[var(--neon)]"><i class="ph-bold ph-plus"></i></span>
            <h3 class="titular mt-6 text-4xl">¿Organizas tú?</h3>
            <p class="mt-3 text-gray-400">Monta tu torneo con inscripciones, tabla en vivo y widgets para tu stream.</p>
          </div>
          <div class="pt-6">
            <p class="mb-3 font-mono text-[11px] uppercase text-gray-400">Desde {{ pesos(precioBase) }} por 2 torneos</p>
            <Link :href="conSesion ? '/planes' : '/register'" class="boton boton-lleno w-full"><span>Crear mi torneo</span></Link>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>
