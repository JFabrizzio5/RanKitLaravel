<script setup lang="ts">
/**
 * Propuesta D — "Logo vivo". El logo de Rankit gigante al centro se vuelve
 * control, balón y trofeo sin parar, y una palabra enorme cambia con él
 * (JUEGA · ANOTA · GANA · RANKIT) junto con una línea de qué es cada cosa.
 * Se inclina siguiendo el mouse y un brillo late en cada cambio.
 */
import { Link } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import LogoMorph from '@/Components/Propuestas/LogoMorph.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionCapacidades from '@/Components/Propuestas/SeccionCapacidades.vue'
import SeccionPasos from '@/Components/Propuestas/SeccionPasos.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { cifras, type Eventos } from '@/Components/Propuestas/medios'
import { gsap, SplitText, sinMovimiento, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'
import { PALABRA, type NombreForma } from '@/Components/Propuestas/logoFormas'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const LINEA: Record<NombreForma, string> = {
  logo: 'Torneos con tabla en vivo',
  control: 'Fortnite, LoL y Valorant',
  balon: 'Fútbol 7 y ligas de barrio',
  trofeo: 'Premios, clasificados y gran final',
}

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const escena = ref<HTMLElement | null>(null)
const brillo = ref<HTMLElement | null>(null)
const palabraEl = ref<HTMLElement | null>(null)
const lineaEl = ref<HTMLElement | null>(null)
const abajo = ref<HTMLElement | null>(null)
const forma = ref<NombreForma>('logo')
const logo = ref<InstanceType<typeof LogoMorph> | null>(null)
let listo = false

// Cambia la palabra: la vieja sube y se va, la nueva sube desde abajo.
async function alCambiar(nueva: NombreForma) {
  if (!listo || !palabraEl.value) { forma.value = nueva; return }
  const viejas = SplitText.create(palabraEl.value, { type: 'chars', mask: 'chars' })
  await gsap.to(viejas.chars, { yPercent: -110, duration: 0.35, ease: 'power3.in', stagger: 0.02 })
  viejas.revert()
  forma.value = nueva
  await nextTick()
  const nuevas = SplitText.create(palabraEl.value, { type: 'chars', mask: 'chars' })
  gsap.from(nuevas.chars, { yPercent: 110, duration: 0.7, ease: 'expo.out', stagger: 0.035, onComplete: () => nuevas.revert() })
  gsap.fromTo(lineaEl.value, { autoAlpha: 0, y: 10 }, { autoAlpha: 1, y: 0, duration: 0.5, delay: 0.2 })
  gsap.fromTo(brillo.value, { scale: 0.85, opacity: 0.9 }, { scale: 1.15, opacity: 0.45, duration: 1.2, ease: 'expo.out' })
}

function hero(despues: Despues) {
  const h = heroEl.value!
  const inicial = SplitText.create(palabraEl.value!, { type: 'chars', mask: 'chars' })
  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => { inicial.revert(); listo = true; logo.value?.iniciar(); despues(alDesplazar) } })
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(escena.value, { scale: 0.6, autoAlpha: 0, rotate: -12, duration: 1.4 }, 0)
    .from(brillo.value, { scale: 0.3, autoAlpha: 0, duration: 1.6 }, 0)
    .from(inicial.chars, { yPercent: 110, duration: 1, stagger: 0.05 }, 0.3)
    .from([lineaEl.value, ...abajo.value!.children], { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.08 }, 0.6)

  // Se inclina hacia el mouse (solo con mouse).
  if (!sinMovimiento() && matchMedia('(pointer: fine)').matches) {
    const rx = gsap.quickTo(escena.value, 'rotationX', { duration: 0.8, ease: 'power3' })
    const ry = gsap.quickTo(escena.value, 'rotationY', { duration: 0.8, ease: 'power3' })
    h.addEventListener('mousemove', (e) => {
      ry((e.clientX / innerWidth - 0.5) * 24)
      rx((e.clientY / innerHeight - 0.5) * -18)
    })
  }

  // Al bajar, el logo se aleja y se desvanece.
  const alDesplazar = () => gsap.to(escena.value, { scale: 0.7, yPercent: -20, opacity: 0, ease: 'none', scrollTrigger: { trigger: h, start: 'top top', end: 'bottom top', scrub: true } })
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="d" titulo="Propuesta D · Logo vivo">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="reticula relative flex min-h-[100svh] flex-col overflow-hidden">
        <NavEditorial variante="barra" flotante />

        <div class="relative flex flex-1 flex-col items-center justify-center px-5 pb-8 pt-24 text-center">
          <!-- Brillo morado detrás del logo -->
          <div ref="brillo" class="pointer-events-none absolute left-1/2 top-[42%] aspect-square w-[min(110vw,900px)] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-45" style="background: radial-gradient(circle, rgba(191,0,255,.45) 0%, rgba(191,0,255,0) 62%)" data-intro></div>

          <div class="relative [perspective:900px]">
            <div ref="escena" class="h-[min(42vh,62vw)] w-[min(42vh,62vw)] [transform-style:preserve-3d]" data-intro>
              <LogoMorph ref="logo" esperar class="h-full w-full drop-shadow-[0_20px_60px_rgba(191,0,255,0.35)]" @forma="alCambiar" />
            </div>
          </div>

          <h1 class="mt-6 sm:mt-8">
            <span class="sr-only">Rankit: juega, anota y gana. Torneos con tabla en vivo.</span>
            <span ref="palabraEl" class="titular block text-[22vw] leading-[0.85] sm:text-[16vw] lg:text-[min(190px,18vh)]" aria-hidden="true" data-intro>{{ PALABRA[forma] }}</span>
          </h1>
          <p ref="lineaEl" class="kicker mt-4" data-intro>{{ LINEA[forma] }}</p>

          <div ref="abajo" class="mt-8 flex flex-wrap justify-center gap-3" data-intro>
            <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
            <Link href="/register" class="boton boton-linea"><span>Crear torneo</span></Link>
          </div>
        </div>

        <div class="relative mx-auto flex w-full max-w-[1600px] justify-between gap-6 px-5 pb-8 sm:px-8">
          <p class="ui hidden text-white/50 sm:block">Fortnite · LoL · Valorant · Fútbol 7</p>
          <p class="ui text-white/50">{{ cifras.torneos }}+ torneos · {{ cifras.jugadores.toLocaleString('es-MX') }} jugadores</p>
        </div>
      </section>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionCifras />
      <SeccionCapacidades />
      <SeccionPasos />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Juega" abajo="y gana" />
    </div>
  </BaseEditorial>
</template>
