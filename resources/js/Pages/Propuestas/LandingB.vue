<script setup lang="ts">
/**
 * Propuesta B — "Pantalla completa". El video del comercial ocupa todo el
 * hero; abajo, la idea en una frase y la marca a todo lo ancho. Luego una
 * cinta con los juegos en letra de contorno.
 *
 * Movimiento: el video se abre desde un recuadro al centro hasta llenar la
 * pantalla, RANKIT sube letra por letra y la frase entra por renglones. Al
 * bajar, el video se aleja y la marca sube más rápido (paralaje).
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import VideoBucle from '@/Components/Propuestas/VideoBucle.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionPasos from '@/Components/Propuestas/SeccionPasos.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { medios, type Eventos } from '@/Components/Propuestas/medios'
import { gsap, SplitText, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const juegos = ['Fortnite', 'Valorant', 'League of Legends', 'Fútbol 7']

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const fondo = ref<HTMLElement | null>(null)
const velo = ref<HTMLElement | null>(null)
const kicker = ref<HTMLElement | null>(null)
const frase = ref<HTMLElement | null>(null)
const botones = ref<HTMLElement | null>(null)
const marca = ref<HTMLElement | null>(null)

function hero(despues: Despues) {
  const h = heroEl.value!
  const video = fondo.value!.firstElementChild as HTMLElement
  const letras = SplitText.create(marca.value!, { type: 'lines,chars', mask: 'lines', linesClass: 'linea' }).chars
  const renglones = SplitText.create(frase.value!, { type: 'lines', mask: 'lines', linesClass: 'linea' }).lines

  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => despues(alDesplazar) })
    .fromTo(fondo.value, { clipPath: 'inset(24% 30% 24% 30%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.6, ease: 'expo.inOut' }, 0)
    .from(video, { scale: 1.35, duration: 2.2, ease: 'expo.out' }, 0)
    .from(velo.value, { autoAlpha: 0, duration: 1.2 }, 0.6)
    .from(letras, { yPercent: 115, duration: 1.2, stagger: 0.06 }, 0.9)
    .from(kicker.value, { autoAlpha: 0, y: 14, duration: 0.9 }, 1.1)
    .from(renglones, { yPercent: 105, duration: 1.1, stagger: 0.1 }, 1.15)
    .from(botones.value!.children, { autoAlpha: 0, y: 26, duration: 0.9, stagger: 0.08 }, 1.35)
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 1.2)

  const alDesplazar = () => gsap.timeline({ scrollTrigger: { trigger: h, start: 'top top', end: 'bottom top', scrub: true }, defaults: { ease: 'none' } })
    .to(video, { scale: 1.18, yPercent: 12 }, 0)
    .to(marca.value, { yPercent: -45 }, 0)
    .to([kicker.value, frase.value, botones.value], { y: -80, opacity: 0 }, 0)
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="b" titulo="Propuesta B · Pantalla completa">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="relative flex min-h-[100svh] flex-col overflow-hidden">
        <div ref="fondo" class="absolute inset-0 overflow-hidden" data-intro>
          <VideoBucle :src="medios.final.video" :poster="medios.final.poster" alt="Equipo levantando el trofeo en una arena" />
        </div>
        <!-- Oscurece abajo para que se lea el texto sin apagar el video -->
        <div ref="velo" class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#050505] via-[#050505]/40 to-black/30" data-intro></div>

        <NavEditorial variante="barra" flotante />

        <div class="relative mx-auto mt-auto w-full max-w-[1600px] px-5 sm:px-8">
          <div class="grid items-end gap-8 pb-6 md:grid-cols-[1.4fr_1fr]">
            <div>
              <p ref="kicker" class="kicker" data-intro>Fortnite · LoL · Valorant · Fútbol 7</p>
              <h1 ref="frase" class="mt-4 max-w-3xl text-4xl font-semibold leading-[1.05] tracking-tight sm:text-6xl" data-intro>
                Torneos con tabla en vivo.
                <span class="text-white/55">Tú organizas, Rankit cuenta los puntos.</span>
              </h1>
            </div>
            <div ref="botones" class="flex flex-col gap-3 md:items-end" data-intro>
              <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
              <Link href="/register" class="boton boton-linea bg-black/30 backdrop-blur"><span>Crear mi torneo</span></Link>
            </div>
          </div>
          <!-- Marca a todo lo ancho -->
          <p ref="marca" class="titular -mb-[0.08em] select-none text-center text-[min(30vw,38vh)] leading-[0.8]" aria-hidden="true" data-intro>Rankit</p>
        </div>
      </section>

      <!-- Cinta de juegos (se inclina con la velocidad del scroll) -->
      <div data-anim="cinta-velocidad" class="overflow-hidden border-y border-[var(--linea)] py-6" aria-label="Juegos: Fortnite, Valorant, League of Legends y Fútbol 7">
        <div class="cinta flex w-max gap-10 whitespace-nowrap" aria-hidden="true">
          <template v-for="n in 2" :key="n">
            <span v-for="j in juegos" :key="j + n" class="titular contorno flex items-center gap-10 text-7xl sm:text-8xl">
              {{ j }} <span class="text-4xl text-[var(--neon)]" style="-webkit-text-stroke: 0">✦</span>
            </span>
          </template>
        </div>
      </div>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionPasos />
      <SeccionCifras />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Tu torneo" abajo="empieza hoy" />
    </div>
  </BaseEditorial>
</template>
