<script setup lang="ts">
/**
 * Propuesta H — "Arena". Piso de rejilla neón en perspectiva que avanza
 * hacia ti (estilo arcade/retro), el logo flotando sobre el horizonte y
 * transformándose. Al bajar, "vuelas" hacia él. Después, una sección donde
 * el scroll mueve los juegos de lado.
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import LogoMorph from '@/Components/Propuestas/LogoMorph.vue'
import VideoBucle from '@/Components/Propuestas/VideoBucle.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { medios, type Eventos } from '@/Components/Propuestas/medios'
import { gsap, SplitText, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const paneles = [
  { n: '01', nombre: 'Fortnite', linea: 'Semanales gratis, scrims y liga. Los puntos salen del replay.', media: medios.arena },
  { n: '02', nombre: 'LoL y Valorant', linea: 'Brackets y ligas por equipos, con widgets para tu stream.', media: medios.streamer },
  { n: '03', nombre: 'Fútbol 7', linea: 'Tu liga de barrio con tabla y resultados al día.', media: medios.futbol },
  { n: '04', nombre: 'La gran final', linea: 'Clasificatorios, repechaje y una final con premios.', media: medios.final },
]

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const piso = ref<HTMLElement | null>(null)
const flotante = ref<HTMLElement | null>(null)
const titulo = ref<HTMLElement | null>(null)
const abajo = ref<HTMLElement | null>(null)
const logo = ref<InstanceType<typeof LogoMorph> | null>(null)
const carrilSec = ref<HTMLElement | null>(null)
const carril = ref<HTMLElement | null>(null)

function hero(despues: Despues) {
  const h = heroEl.value!
  const letras = SplitText.create(titulo.value!, { type: 'lines,chars', mask: 'lines', linesClass: 'linea' })
  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => { letras.revert(); logo.value?.iniciar(); despues(alDesplazar) } })
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(piso.value, { rotationX: 90, autoAlpha: 0, duration: 1.6, ease: 'expo.inOut' }, 0)
    .from(flotante.value, { y: 120, scale: 0.4, autoAlpha: 0, duration: 1.5 }, 0.4)
    .from(letras.chars, { yPercent: 110, duration: 1, stagger: 0.03 }, 0.7)
    .from(abajo.value!.children, { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.08 }, 1)

  // Flota sube y baja sin parar (en el hijo: la entrada ya mueve al contenedor).
  gsap.to(flotante.value!.firstElementChild, { y: -14, duration: 2.2, ease: 'sine.inOut', yoyo: true, repeat: -1 })

  const alDesplazar = () => {
    // Al bajar: vuelas hacia el logo y el piso acelera.
    gsap.timeline({ scrollTrigger: { trigger: h, start: 'top top', end: 'bottom top', scrub: true }, defaults: { ease: 'none' } })
      .to(flotante.value!.firstElementChild, { scale: 2.6, opacity: 0 }, 0)
      .to([titulo.value, abajo.value], { y: -120, opacity: 0 }, 0)
      .to(piso.value, { '--velocidad': '0.35s' }, 0)

    // Juegos de lado: la sección se fija y el carril se mueve a la izquierda.
    const c = carril.value!
    gsap.to(c, {
      x: () => -(c.scrollWidth - window.innerWidth),
      ease: 'none',
      scrollTrigger: { trigger: carrilSec.value, start: 'top top', end: () => `+=${c.scrollWidth - window.innerWidth}`, scrub: 0.8, pin: true, anticipatePin: 1, invalidateOnRefresh: true },
    })
  }
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="h" titulo="Propuesta H · Arena">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="relative flex min-h-[100svh] flex-col overflow-hidden" style="background: linear-gradient(180deg, #050505 0%, #0b0412 45%, #1a0526 62%, #050505 100%)">
        <NavEditorial variante="barra" flotante />

        <!-- Brillo del horizonte -->
        <div class="pointer-events-none absolute inset-x-0 top-[52%] h-40 -translate-y-1/2" style="background: radial-gradient(ellipse at center, rgba(191,0,255,.55) 0%, rgba(191,0,255,0) 65%)" aria-hidden="true"></div>

        <!-- Piso de rejilla en perspectiva -->
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[48%] [perspective:320px]" aria-hidden="true">
          <div ref="piso" class="piso absolute inset-x-[-50%] bottom-0 h-[220%] origin-bottom" data-intro></div>
        </div>

        <div class="relative flex flex-1 flex-col items-center justify-center px-5 pb-[18vh] pt-24 text-center">
          <div ref="flotante" class="mb-6" data-intro>
            <div class="h-[min(24vh,40vw)] w-[min(24vh,40vw)]">
              <LogoMorph ref="logo" esperar class="h-full w-full drop-shadow-[0_0_40px_rgba(191,0,255,0.7)]" />
            </div>
          </div>
          <h1 ref="titulo" class="titular text-[14vw] sm:text-[10vw] lg:text-[min(130px,13vh)]" data-intro>Entra a la <span class="text-[var(--neon)]">arena</span></h1>
          <div ref="abajo" class="mt-6 flex flex-col items-center gap-6" data-intro>
            <p class="max-w-md text-lg text-gray-300">Torneos de Fortnite, LoL, Valorant y fútbol 7 con tabla en vivo.</p>
            <div class="flex flex-wrap justify-center gap-3">
              <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
              <Link href="/register" class="boton boton-linea bg-black/40"><span>Crear torneo</span></Link>
            </div>
          </div>
        </div>
      </section>

      <!-- Juegos que pasan de lado con el scroll -->
      <section ref="carrilSec" class="relative h-[100svh] overflow-hidden border-y border-[var(--linea)]">
        <div class="absolute left-5 top-6 z-10 sm:left-8 sm:top-10">
          <p class="kicker">Lo que se juega</p>
          <h2 class="titular mt-2 text-5xl sm:text-7xl">Tus juegos</h2>
        </div>
        <div ref="carril" class="flex h-full w-max items-center gap-6 pl-5 pr-[10vw] pt-24 sm:gap-10 sm:pl-8">
          <article v-for="p in paneles" :key="p.nombre" class="relative h-[62svh] w-[82vw] shrink-0 overflow-hidden border border-white/10 sm:w-[60vw] lg:w-[46vw]">
            <VideoBucle :src="p.media.video" :poster="p.media.poster" :alt="p.nombre" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
              <span class="titular text-6xl text-white/25 sm:text-8xl">{{ p.n }}</span>
              <h3 class="titular mt-1 text-4xl sm:text-6xl">{{ p.nombre }}</h3>
              <p class="mt-3 max-w-md text-gray-200">{{ p.linea }}</p>
            </div>
          </article>
        </div>
      </section>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionCifras />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Nos vemos" abajo="en la arena" />
    </div>
  </BaseEditorial>
</template>

<style scoped>
/* Rejilla que avanza: el fondo se desplaza hacia el frente sin parar. */
.piso {
  --velocidad: 1.1s;
  transform: rotateX(72deg);
  background-image:
    linear-gradient(to right, rgba(191, 0, 255, 0.75) 2px, transparent 2px),
    linear-gradient(to bottom, rgba(191, 0, 255, 0.75) 2px, transparent 2px);
  background-size: 64px 64px;
  -webkit-mask-image: linear-gradient(to top, #000 30%, transparent 92%);
  mask-image: linear-gradient(to top, #000 30%, transparent 92%);
  animation: avanzar var(--velocidad) linear infinite;
}
@keyframes avanzar { from { background-position: 0 0; } to { background-position: 0 64px; } }
</style>
