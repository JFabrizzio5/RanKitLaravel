<script setup lang="ts">
/**
 * Propuesta C — "Producto". Titular apilado a la izquierda; a la derecha un
 * video vertical con capturas REALES encima (la tabla en vivo de la
 * EYMencha Cup y el widget de OBS). Debajo, una cinta con las cifras reales
 * y una sección que enseña la app tal cual.
 *
 * Movimiento: los tres renglones del titular suben uno tras otro, el video
 * se destapa de abajo hacia arriba y las capturas llegan girando desde los
 * lados. Al bajar, cada capa se mueve a distinta velocidad (profundidad).
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import VideoBucle from '@/Components/Propuestas/VideoBucle.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionCapacidades from '@/Components/Propuestas/SeccionCapacidades.vue'
import SeccionPasos from '@/Components/Propuestas/SeccionPasos.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { cifras, medios, type Eventos } from '@/Components/Propuestas/medios'
import { gsap, SplitText, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const cinta = [
  `${cifras.torneos}+ torneos`,
  `${cifras.jugadores.toLocaleString('es-MX')} jugadores`,
  `${cifras.partidas} partidas procesadas`,
  `${cifras.eliminaciones.toLocaleString('es-MX')} eliminaciones contadas`,
]

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const kicker = ref<HTMLElement | null>(null)
const titulo = ref<HTMLElement | null>(null)
const parrafo = ref<HTMLElement | null>(null)
const botones = ref<HTMLElement | null>(null)
const marco = ref<HTMLElement | null>(null)
const tabla = ref<HTMLElement | null>(null)
const widget = ref<HTMLElement | null>(null)

function hero(despues: Despues) {
  const h = heroEl.value!
  const video = marco.value!.firstElementChild as HTMLElement
  const renglones = SplitText.create(titulo.value!, { type: 'lines', mask: 'lines', linesClass: 'linea' }).lines

  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => despues(alDesplazar) })
    .from(raiz.value!.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(kicker.value, { autoAlpha: 0, y: 14, duration: 0.9 }, 0.1)
    .from(renglones, { yPercent: 108, duration: 1.2, stagger: 0.13 }, 0.15)
    .fromTo(marco.value, { clipPath: 'inset(100% 0% 0% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.4, ease: 'expo.inOut' }, 0.2)
    .from(video, { scale: 1.3, duration: 2 }, 0.2)
    .from(parrafo.value, { autoAlpha: 0, y: 24, duration: 1 }, 0.6)
    .from(botones.value!.children, { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.08 }, 0.7)
    .from(tabla.value, { autoAlpha: 0, x: -90, y: 50, rotate: -7, duration: 1.4 }, 0.95)
    .from(widget.value, { autoAlpha: 0, x: 70, y: -50, rotate: 9, duration: 1.4 }, 1.05)

  const alDesplazar = () => gsap.timeline({ scrollTrigger: { trigger: h, start: 'top top', end: 'bottom top', scrub: true }, defaults: { ease: 'none' } })
    .to(video, { scale: 1.12 }, 0)
    .to(tabla.value, { yPercent: -35 }, 0)
    .to(widget.value, { yPercent: 40 }, 0)
    .to(titulo.value, { y: -60 }, 0)
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="c" titulo="Propuesta C · Producto">
    <div ref="raiz" class="preparando">
      <NavEditorial variante="barra" />

      <section ref="heroEl" class="mx-auto grid max-w-[1600px] items-center gap-12 px-5 pb-16 pt-12 sm:px-8 lg:grid-cols-[1.25fr_1fr] lg:pb-24 lg:pt-16">
        <div>
          <p ref="kicker" class="kicker" data-intro>Ligas · Torneos · Scrims</p>
          <h1 ref="titulo" class="titular mt-4 text-[13vw] sm:text-[10vw] lg:text-[min(118px,12.5vh)]" data-intro>
            Tu liga.<br />Tus reglas.<br /><span class="text-[var(--neon)]">Tu tabla.</span>
          </h1>
          <p ref="parrafo" class="mt-8 max-w-md text-lg leading-snug text-[var(--gris)]" data-intro>
            Organiza torneos de Fortnite, LoL, Valorant y fútbol 7. <span class="text-white">La tabla se actualiza sola con cada partida.</span>
          </p>
          <div ref="botones" class="mt-8 flex flex-wrap gap-3" data-intro>
            <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
            <Link href="/register" class="boton boton-linea"><span>Crear mi torneo</span></Link>
          </div>
        </div>

        <!-- Video vertical + capturas reales encima -->
        <div class="relative mx-auto w-full max-w-[520px]">
          <div ref="marco" class="aspect-[4/5] overflow-hidden" data-intro>
            <VideoBucle :src="medios.mirada.video" :poster="medios.mirada.poster" alt="Jugador concentrado frente a la pantalla" />
          </div>
          <figure ref="tabla" class="absolute -bottom-8 -left-4 w-[78%] border border-white/15 bg-[#0d0d0d] shadow-2xl sm:-left-10" data-intro>
            <img :src="medios.tabla" alt="Tabla en vivo real de la EYMencha Cup en Rankit" class="w-full" />
            <figcaption class="ui flex items-center gap-2 border-t border-white/10 px-3 py-2 text-[10px] text-[var(--gris)]">
              <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span> EYMencha Cup · tabla real
            </figcaption>
          </figure>
          <img ref="widget" :src="medios.widget" alt="Widget de OBS con el top de jugadores" class="absolute -right-3 -top-6 w-[38%] border border-white/15 shadow-2xl sm:-right-8" data-intro />
        </div>
      </section>

      <!-- Cinta con cifras reales (se inclina con la velocidad del scroll) -->
      <div data-anim="cinta-velocidad" class="mt-10 overflow-hidden border-y border-[var(--linea)] py-5" :aria-label="cinta.join(', ')">
        <div class="cinta flex w-max gap-12 whitespace-nowrap" aria-hidden="true">
          <template v-for="n in 2" :key="n">
            <span v-for="t in cinta" :key="t + n" class="titular flex items-center gap-12 text-5xl sm:text-6xl">
              {{ t }} <span class="text-2xl text-[var(--neon)]">✦</span>
            </span>
          </template>
        </div>
      </div>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionCapacidades />

      <!-- La app tal cual -->
      <section class="mx-auto max-w-[1600px] px-5 py-24 sm:px-8 lg:py-32">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
          <h2 data-anim="letras" class="titular text-[13vw] sm:text-8xl lg:text-[110px]">Así se ve</h2>
          <p data-anim="subir" class="ui max-w-xs text-[var(--gris)]">Captura real de un torneo en Rankit. Sin maquetas.</p>
        </div>
        <div data-anim="revelar" class="mt-12 overflow-hidden border border-white/10">
          <div class="flex items-center gap-2 border-b border-white/10 bg-white/[0.03] px-4 py-3">
            <span class="h-2.5 w-2.5 rounded-full bg-white/20"></span><span class="h-2.5 w-2.5 rounded-full bg-white/20"></span><span class="h-2.5 w-2.5 rounded-full bg-white/20"></span>
            <span class="ui ml-3 text-[10px] text-[var(--gris)]">rankit.pro/t/64</span>
          </div>
          <img :src="medios.tabla" alt="Página pública de la EYMencha Cup con la tabla de jugadores" class="w-full" loading="lazy" />
        </div>
      </section>

      <SeccionPasos />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Tu tabla" abajo="en vivo" />
    </div>
  </BaseEditorial>
</template>
