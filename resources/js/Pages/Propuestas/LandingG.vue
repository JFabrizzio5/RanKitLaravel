<script setup lang="ts">
/**
 * Propuesta G — "Marcador". Tablero de estadio: TORNEOS fijo y abajo una
 * palabra que gira letra por letra al ritmo del logo (EN VIVO → FORTNITE →
 * FÚTBOL 7 → PREMIOS) dentro de una pantalla LED. Reflectores barriendo y
 * una cinta con las posiciones reales de la liga.
 */
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { onMounted, ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import LogoMorph from '@/Components/Propuestas/LogoMorph.vue'
import TableroLetras from '@/Components/Propuestas/TableroLetras.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionCapacidades from '@/Components/Propuestas/SeccionCapacidades.vue'
import SeccionPasos from '@/Components/Propuestas/SeccionPasos.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import type { Eventos } from '@/Components/Propuestas/medios'
import { gsap, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'
import type { NombreForma } from '@/Components/Propuestas/logoFormas'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const ABAJO: Record<NombreForma, string> = { logo: 'EN VIVO', control: 'FORTNITE', balon: 'FÚTBOL 7', trofeo: 'PREMIOS' }
const abajo = ref('EN VIVO')

// Cinta: posiciones reales de la última fase de la liga que tenga jugadores.
// Sin datos, dice lo que hace Rankit (no se inventan jugadores).
type Fila = { player_name: string; total_kills: number | string }
const cinta = ref<string[]>(['Tabla en vivo', 'Inscripciones en línea', 'Resultados desde el replay', 'Widgets para OBS', 'Premios y clasificados'])
const enVivo = ref(false)
onMounted(async () => {
  try {
    const { data } = await axios.get<{ tournament: { name: string; scoring_format?: string | null }; standings: Fila[] }[]>('/api/league/standings')
    const fase = [...data].reverse().find((b) => b.standings.length)
    if (!fase) return
    let porKill = 10
    try { porKill = Number(JSON.parse(fase.tournament.scoring_format ?? '{}').kill_points) || 10 } catch { /* formato viejo */ }
    const nombreFase = fase.tournament.name.replace(/^rankit\s*\.?\s*pro\s*league\s*[-–—:·]\s*/i, '').replace(/\s*\(v[\d.]+\)\s*$/i, '')
    cinta.value = [
      `${nombreFase}`,
      ...fase.standings.slice(0, 10).map((f, i) => `${i + 1}. ${f.player_name} · ${Number(f.total_kills) * porKill} pts`),
    ]
    enVivo.value = true
  } catch { /* se queda la cinta de respaldo */ }
})

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const pantalla = ref<HTMLElement | null>(null)
const tablero = ref<HTMLElement | null>(null)
const botones = ref<HTMLElement | null>(null)
const reflectores = ref<HTMLElement | null>(null)
const logo = ref<InstanceType<typeof LogoMorph> | null>(null)

function hero(despues: Despues) {
  const h = heroEl.value!
  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => { logo.value?.iniciar(); despues(alDesplazar) } })
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(reflectores.value!.children, { autoAlpha: 0, duration: 1.4, stagger: 0.15 }, 0)
    .fromTo(pantalla.value, { clipPath: 'inset(50% 0% 50% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1, ease: 'expo.inOut' }, 0.1)
    .from(tablero.value!.querySelectorAll('.casilla'), { rotationX: -90, autoAlpha: 0, transformOrigin: '50% 0%', duration: 0.6, stagger: 0.03, ease: 'back.out(1.6)' }, 0.5)
    .from(botones.value!.children, { autoAlpha: 0, y: 24, duration: 0.8, stagger: 0.08 }, 1)

  const alDesplazar = () => gsap.to(pantalla.value, { yPercent: -18, scale: 0.92, ease: 'none', scrollTrigger: { trigger: h, start: 'top top', end: 'bottom top', scrub: true } })
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="g" titulo="Propuesta G · Marcador">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="relative flex min-h-[100svh] flex-col overflow-hidden bg-[#050505]">
        <!-- Reflectores de estadio barriendo -->
        <div ref="reflectores" class="pointer-events-none absolute inset-0" aria-hidden="true" data-intro>
          <div class="reflector absolute -left-[20%] -top-[30%] h-[140%] w-[70%]"></div>
          <div class="reflector reflector-der absolute -right-[20%] -top-[30%] h-[140%] w-[70%]"></div>
        </div>

        <NavEditorial variante="barra" flotante />

        <div class="relative flex flex-1 flex-col items-center justify-center px-4 pb-6 pt-24 text-center">
          <!-- Pantalla LED con el logo -->
          <div ref="pantalla" class="led relative mb-8 flex h-[min(26vh,40vw)] w-[min(26vh,40vw)] items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-[#0b0b0b]" data-intro>
            <LogoMorph ref="logo" esperar class="h-[62%] w-[62%] drop-shadow-[0_0_24px_rgba(191,0,255,0.6)]" @forma="(f) => (abajo = ABAJO[f])" />
            <span class="absolute left-3 top-3 flex items-center gap-1.5 font-mono text-[10px] font-bold uppercase text-red-400"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span>Live</span>
          </div>

          <!-- Tablero -->
          <h1 ref="tablero" class="flex flex-col gap-[0.12em] text-[11vw] sm:text-[8vw] lg:text-[min(96px,9vh)]" data-intro>
            <span class="sr-only">Torneos en vivo de Fortnite, fútbol 7 y con premios</span>
            <TableroLetras texto="TORNEOS" :casillas="8" />
            <TableroLetras :texto="abajo" :casillas="8" acento />
          </h1>

          <div ref="botones" class="mt-10 flex flex-wrap justify-center gap-3" data-intro>
            <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
            <Link href="/register" class="boton boton-linea"><span>Crear torneo</span></Link>
          </div>
        </div>

        <!-- Cinta en vivo -->
        <div class="relative flex items-center border-t border-[var(--linea)] bg-black/60">
          <span class="flex shrink-0 items-center gap-2 border-r border-[var(--linea)] bg-[var(--neon)] px-4 py-3 font-display text-sm font-bold italic uppercase">
            <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span>{{ enVivo ? 'Liga en vivo' : 'Rankit' }}
          </span>
          <div class="overflow-hidden py-3" :aria-label="cinta.join(', ')">
            <div class="cinta flex w-max gap-10 whitespace-nowrap pl-6" aria-hidden="true">
              <template v-for="n in 2" :key="n">
                <span v-for="t in cinta" :key="t + n" class="ui flex items-center gap-10 text-sm">{{ t }} <span class="text-[var(--neon)]">✦</span></span>
              </template>
            </div>
          </div>
        </div>
      </section>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionCifras />
      <SeccionCapacidades />
      <SeccionPasos />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Tu nombre" abajo="en la tabla" />
    </div>
  </BaseEditorial>
</template>

<style scoped>
/* Haz de luz que barre lento, como reflector de estadio. */
.reflector {
  background: conic-gradient(from 200deg at 50% 0%, transparent 0deg, rgba(191, 0, 255, 0.22) 14deg, rgba(255, 255, 255, 0.08) 20deg, transparent 34deg);
  transform-origin: 50% 0%;
  animation: barrer 7s ease-in-out infinite alternate;
  filter: blur(6px);
}
.reflector-der { animation-delay: -3.5s; animation-direction: alternate-reverse; }
@keyframes barrer { from { transform: rotate(-18deg); } to { transform: rotate(22deg); } }
/* Líneas de pantalla LED. */
.led::after {
  content: ""; position: absolute; inset: 0; pointer-events: none;
  background: repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.04) 0 1px, transparent 1px 4px);
}
</style>
