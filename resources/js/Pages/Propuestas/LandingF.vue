<script setup lang="ts">
/**
 * Propuesta F — "Elige tu juego". Como la pantalla de selección de un
 * videojuego: cuatro tarjetas altas; la que tocas se ensancha, arranca su
 * video y el logo de arriba se convierte en su ícono (control, balón,
 * trofeo o la R de Rankit).
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
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
import { medios, type Eventos } from '@/Components/Propuestas/medios'
import { gsap, SplitText, useAnimaciones } from '@/Components/Propuestas/animaciones'
import type { NombreForma } from '@/Components/Propuestas/logoFormas'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const juegos: { forma: NombreForma; nombre: string; linea: string; media: { video: string; poster: string }; boton: string; href: string }[] = [
  { forma: 'control', nombre: 'Fortnite', linea: 'Semanales gratis y scrims con resultados del replay.', media: medios.mirada, boton: 'Reservar lugar', href: '#eventos' },
  { forma: 'balon', nombre: 'Fútbol 7', linea: 'Tu liga de barrio con tabla y resultados al día.', media: medios.futbol, boton: 'Crear mi liga', href: '/register' },
  { forma: 'trofeo', nombre: 'Pro League', linea: 'Clasificatorios, repechaje y gran final.', media: medios.arena, boton: 'Ver la liga', href: '/league' },
  { forma: 'logo', nombre: 'Tu torneo', linea: 'LoL, Valorant o lo que juegues. Tabla en vivo y widgets.', media: medios.streamer, boton: 'Crear torneo', href: '/register' },
]

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const logo = ref<InstanceType<typeof LogoMorph> | null>(null)
const titulo = ref<HTMLElement | null>(null)
const tarjetas = ref<HTMLElement[]>([])
const activa = ref(0)

function elegir(i: number) {
  if (activa.value === i) return
  activa.value = i
  logo.value?.irA(juegos[i].forma)
  tarjetas.value.forEach((t, j) => {
    const v = t.querySelector('video')
    if (!v) return
    if (j === i) v.play().catch(() => {})
    else v.pause()
  })
}

function hero() {
  const h = heroEl.value!
  const letras = SplitText.create(titulo.value!, { type: 'lines,chars', mask: 'lines', linesClass: 'linea' })
  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => letras.revert() })
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(logo.value!.piezas(), { autoAlpha: 0, scale: 0.4, transformOrigin: '50% 50%', duration: 0.9, stagger: 0.08 }, 0)
    .from(letras.chars, { yPercent: 110, duration: 1, stagger: 0.03 }, 0.2)
    .from(tarjetas.value, { yPercent: 40, autoAlpha: 0, duration: 1.2, stagger: 0.1 }, 0.4)
  tarjetas.value[0]?.querySelector('video')?.play().catch(() => {})
  // La R se vuelve el ícono de la tarjeta activa en cuanto entran las tarjetas.
  gsap.delayedCall(1.1, () => logo.value?.irA(juegos[activa.value].forma))
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="f" titulo="Propuesta F · Elige tu juego">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="reticula relative flex min-h-[100svh] flex-col">
        <NavEditorial variante="barra" />

        <div class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col px-5 pb-10 pt-6 sm:px-8">
          <div class="flex flex-col items-center text-center">
            <LogoMorph ref="logo" modo="manual" class="h-16 w-16 sm:h-20 sm:w-20" />
            <h1 ref="titulo" class="titular mt-4 text-[13vw] sm:text-7xl lg:text-[min(110px,11vh)]" data-intro>Elige tu juego</h1>
          </div>

          <!-- Tarjetas: en escritorio se ensancha la elegida; en celular van apiladas -->
          <div class="mt-8 flex flex-1 flex-col gap-3 lg:min-h-[52vh] lg:flex-row">
            <article
              v-for="(j, i) in juegos"
              :key="j.nombre"
              ref="tarjetas"
              class="tarjeta-juego relative min-h-[150px] cursor-pointer overflow-hidden border transition-[flex-grow,border-color] duration-700 ease-[cubic-bezier(.2,.8,.2,1)]"
              :class="activa === i ? 'min-h-[300px] border-[var(--neon)] lg:min-h-0 lg:grow-[2.8]' : 'border-white/15 lg:grow'"
              style="flex-basis: 0"
              tabindex="0"
              @mouseenter="elegir(i)"
              @focus="elegir(i)"
              @click="elegir(i)"
            >
              <video :src="j.media.video" :poster="j.media.poster" muted loop playsinline preload="metadata" class="absolute inset-0 h-full w-full object-cover transition duration-700" :class="activa === i ? 'opacity-90 scale-100' : 'opacity-40 scale-110 grayscale'" aria-hidden="true"></video>
              <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>

              <div class="relative flex h-full flex-col justify-end p-5 sm:p-6">
                <span class="ui text-white/60">0{{ i + 1 }}</span>
                <h2 class="titular mt-1 transition-[font-size] duration-500" :class="activa === i ? 'whitespace-nowrap text-4xl sm:text-5xl' : 'text-3xl sm:text-4xl lg:text-2xl xl:text-3xl'">{{ j.nombre }}</h2>
                <div class="grid transition-[grid-template-rows,opacity] duration-500" :class="activa === i ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 lg:grid-rows-[0fr]'">
                  <div class="overflow-hidden">
                    <p class="mt-3 max-w-sm text-gray-200">{{ j.linea }}</p>
                    <component :is="j.href.startsWith('#') ? 'a' : Link" :href="j.href" class="boton boton-lleno mt-5 !px-6 !py-3" @click.stop><span>{{ j.boton }}</span></component>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionCifras />
      <SeccionCapacidades />
      <SeccionPasos />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Elige" abajo="y juega" />
    </div>
  </BaseEditorial>
</template>
