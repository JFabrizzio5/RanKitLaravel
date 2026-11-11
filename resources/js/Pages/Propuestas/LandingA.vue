<script setup lang="ts">
/**
 * Propuesta A — "Clásica". La más fiel a la referencia: palabra gigante,
 * video real en medio, palabra gigante; cifras chicas abajo a la izquierda y
 * la idea en una frase abajo a la derecha. Retícula de líneas finas detrás.
 *
 * Movimiento: al cargar se dibujan la cruz y el círculo, el marco del video
 * se abre desde el centro y las letras suben. Al bajar, el hero se queda fijo
 * y el video crece hasta llenar la pantalla mientras las palabras se van.
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import VideoBucle from '@/Components/Propuestas/VideoBucle.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionCapacidades from '@/Components/Propuestas/SeccionCapacidades.vue'
import SeccionPasos from '@/Components/Propuestas/SeccionPasos.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { cifras, medios, type Eventos } from '@/Components/Propuestas/medios'
import { cajaRelativa, gsap, SplitText, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const lineaH = ref<HTMLElement | null>(null)
const lineaV = ref<HTMLElement | null>(null)
const circulo = ref<HTMLElement | null>(null)
const kicker = ref<HTMLElement | null>(null)
const palabra1 = ref<HTMLElement | null>(null)
const marco = ref<HTMLElement | null>(null)
const palabra2 = ref<HTMLElement | null>(null)
const deMexico = ref<HTMLElement | null>(null)
const esquinaIzq = ref<HTMLElement | null>(null)
const esquinaDer = ref<HTMLElement | null>(null)

function hero(despues: Despues) {
  const h = heroEl.value!, m = marco.value!
  const letras = (el: HTMLElement) => SplitText.create(el, { type: 'lines,chars', mask: 'lines', linesClass: 'linea' }).chars

  // Entrada
  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => despues(alDesplazar) })
    .from(lineaH.value, { scaleX: 0, duration: 1.3, ease: 'expo.inOut' }, 0)
    .from(lineaV.value, { scaleY: 0, duration: 1.3, ease: 'expo.inOut' }, 0)
    .from(circulo.value, { scale: 0.55, autoAlpha: 0, duration: 1.6 }, 0.1)
    .fromTo(m, { clipPath: 'inset(50% 50% 50% 50%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.2, ease: 'expo.inOut' }, 0.15)
    .from(letras(palabra1.value!), { yPercent: 115, duration: 1.1, stagger: 0.045 }, 0.5)
    .from(letras(palabra2.value!), { yPercent: 115, duration: 1.1, stagger: 0.045 }, 0.65)
    .from([kicker.value, deMexico.value], { autoAlpha: 0, y: 14, duration: 0.9 }, 0.85)
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0.9)
    .from([...esquinaIzq.value!.children, ...esquinaDer.value!.children], { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.06 }, 1)

  // Scroll: el hero se fija y el video crece hasta llenar la pantalla.
  const caja = () => cajaRelativa(m, h)
  const alDesplazar = () => gsap.timeline({
    scrollTrigger: { trigger: h, start: 'top top', end: '+=110%', scrub: 1, pin: true, anticipatePin: 1, invalidateOnRefresh: true },
    defaults: { ease: 'none' },
  })
    .to(m, {
      x: () => window.innerWidth / 2 - (caja().x + caja().w / 2),
      y: () => window.innerHeight / 2 - (caja().y + caja().h / 2),
      scale: () => Math.max(window.innerWidth / caja().w, window.innerHeight / caja().h) * 1.02,
    }, 0)
    .to(m.querySelector('button'), { autoAlpha: 0, duration: 0.2 }, 0)
    .to(palabra1.value, { yPercent: -160, opacity: 0 }, 0)
    .to([palabra2.value, deMexico.value], { yPercent: 160, opacity: 0 }, 0)
    .to([kicker.value, esquinaIzq.value, esquinaDer.value, circulo.value], { opacity: 0, duration: 0.4 }, 0)
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="a" titulo="Propuesta A · Clásica">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="reticula relative flex min-h-[100svh] flex-col overflow-hidden">
        <!-- Retícula: cruz y círculo finos -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true" data-intro>
          <div ref="lineaH" class="absolute inset-x-0 top-1/2 h-px origin-center bg-[var(--linea)]"></div>
          <div ref="lineaV" class="absolute inset-y-0 left-1/2 w-px origin-center bg-[var(--linea)]"></div>
          <div ref="circulo" class="absolute left-1/2 top-1/2 aspect-square w-[min(92vw,900px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[var(--linea)]"></div>
        </div>

        <NavEditorial variante="lateral" flotante />

        <div class="relative mx-auto flex w-full max-w-[1600px] flex-1 flex-col justify-center px-5 pb-10 pt-28 sm:px-8 lg:pb-4 lg:pt-24">
          <div class="mx-auto w-full max-w-[1100px]">
            <p ref="kicker" class="ui text-white/70" data-intro>Somos la plataforma de</p>
            <h1 ref="palabra1" class="titular mt-1 text-[17vw] sm:text-[15vw] lg:text-[min(200px,19vh)]" data-intro>Torneos</h1>

            <div ref="marco" class="relative z-10 my-3 aspect-video w-full will-change-transform sm:mx-auto sm:w-[60%] lg:w-[min(520px,48vh)]" data-intro>
              <VideoBucle :src="medios.arena.video" :poster="medios.arena.poster" boton alt="Arena de esports con la tabla en pantalla" />
            </div>

            <p ref="palabra2" class="titular text-right text-[17vw] sm:text-[15vw] lg:text-[min(200px,19vh)]" aria-hidden="true" data-intro>En vivo</p>
            <p ref="deMexico" class="ui text-right text-white/70" data-intro>De México</p>
          </div>
        </div>

        <!-- Esquinas inferiores -->
        <div class="relative mx-auto grid w-full max-w-[1600px] gap-8 px-5 pb-10 sm:grid-cols-2 sm:px-8">
          <dl ref="esquinaIzq" class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 self-end" data-intro>
            <dt class="ui text-white">{{ cifras.torneos }}+</dt><dd class="ui text-[var(--gris)]">Torneos</dd>
            <dt class="ui text-white">{{ cifras.jugadores.toLocaleString('es-MX') }}</dt><dd class="ui text-[var(--gris)]">Jugadores</dd>
            <dt class="ui text-white">{{ cifras.partidas }}</dt><dd class="ui text-[var(--gris)]">Partidas procesadas</dd>
          </dl>
          <div ref="esquinaDer" class="max-w-sm self-end sm:justify-self-end" data-intro>
            <p class="ui leading-relaxed">
              Sin Excel ni capturas.
              <span class="text-[var(--gris)]">Tú creas el torneo, ellos juegan y la tabla se actualiza sola con cada partida.</span>
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
              <a href="#eventos" class="boton boton-lleno !px-6 !py-3"><span>Reservar lugar</span></a>
              <Link href="/register" class="boton boton-linea !px-6 !py-3"><span>Crear torneo</span></Link>
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
      <SeccionCierre />
    </div>
  </BaseEditorial>
</template>
