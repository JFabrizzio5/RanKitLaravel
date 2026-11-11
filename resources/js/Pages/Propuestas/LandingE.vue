<script setup lang="ts">
/**
 * Propuesta E — "Recorrido". La historia se cuenta con el scroll: la
 * pantalla se queda fija y, conforme bajas, el logo se transforma y cambian
 * el capítulo y la imagen.
 *   01 Crea tu torneo   (logo   · tabla real)
 *   02 Juegan en línea  (control · arena)
 *   03 O en la cancha   (balón  · fútbol 7)
 *   04 Alguien gana     (trofeo · la final)
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavEditorial from '@/Components/Propuestas/NavEditorial.vue'
import LogoMorph from '@/Components/Propuestas/LogoMorph.vue'
import VideoBucle from '@/Components/Propuestas/VideoBucle.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { medios, type Eventos } from '@/Components/Propuestas/medios'
import { gsap, SplitText, useAnimaciones } from '@/Components/Propuestas/animaciones'
import { morfar, type NombreForma } from '@/Components/Propuestas/logoFormas'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

type Capitulo = { forma: NombreForma; titulo: string; texto: string; video?: { video: string; poster: string }; imagen?: string }
const capitulos: Capitulo[] = [
  { forma: 'logo', titulo: 'Crea tu torneo', texto: 'Nombre, reglas y cuántos puntos vale cada cosa. Rankit arma la tabla.', imagen: medios.tabla },
  { forma: 'control', titulo: 'Juegan en línea', texto: 'Fortnite, LoL y Valorant. Los resultados salen del replay.', video: medios.arena },
  { forma: 'balon', titulo: 'O en la cancha', texto: 'Ligas de fútbol 7 con tabla y resultados al día.', video: medios.futbol },
  { forma: 'trofeo', titulo: 'Alguien gana', texto: 'Premios, clasificados y gran final, todo en vivo.', video: medios.final },
]

const raiz = ref<HTMLElement | null>(null)
const escenario = ref<HTMLElement | null>(null)
const logo = ref<InstanceType<typeof LogoMorph> | null>(null)
const textos = ref<HTMLElement[]>([])
const capas = ref<HTMLElement[]>([])
const puntos = ref<HTMLElement[]>([])
const numero = ref('01')
const portada = ref<HTMLElement | null>(null)

function hero() {
  const e = escenario.value!
  const piezas = logo.value!.piezas()

  // Entrada: la portada y el primer capítulo.
  const titulo = SplitText.create(portada.value!.querySelector('h1')!, { type: 'lines', mask: 'lines', linesClass: 'linea' })
  gsap.timeline({ defaults: { ease: 'expo.out' } })
    .from(raiz.value!.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(titulo.lines, { yPercent: 108, duration: 1.2, stagger: 0.12 }, 0.1)
    .from(portada.value!.querySelectorAll('[data-sube]'), { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.08 }, 0.5)
    .from(piezas, { autoAlpha: 0, xPercent: -40, skewX: -20, duration: 0.9, stagger: 0.08 }, 0.3)

  // Capítulos atados al scroll: el escenario se fija y cada tramo cambia figura, texto e imagen.
  gsap.set(textos.value.slice(1), { autoAlpha: 0, y: 40 })
  gsap.set(capas.value.slice(1), { clipPath: 'inset(100% 0% 0% 0%)' })
  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: e, start: 'top top', end: `+=${(capitulos.length - 1) * 100}%`, scrub: 0.8, pin: true, anticipatePin: 1,
      onUpdate: (self) => {
        const i = Math.min(capitulos.length - 1, Math.round(self.progress * (capitulos.length - 1)))
        numero.value = String(i + 1).padStart(2, '0')
        puntos.value.forEach((p, j) => p.classList.toggle('activo', j <= i))
      },
    },
  })
  capitulos.slice(1).forEach((c, k) => {
    const i = k + 1
    tl.to(textos.value[i - 1], { autoAlpha: 0, y: -40, duration: 0.4 }, i - 0.6)
      .to(textos.value[i], { autoAlpha: 1, y: 0, duration: 0.4 }, i - 0.3)
      .to(capas.value[i], { clipPath: 'inset(0% 0% 0% 0%)', duration: 0.7, ease: 'power2.inOut' }, i - 0.7)
    morfar(tl, piezas, c.forma, i - 0.7, 0.6)
  })
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="e" titulo="Propuesta E · Recorrido">
    <div ref="raiz" class="preparando">
      <!-- Portada corta antes del recorrido -->
      <section ref="portada" class="reticula relative overflow-hidden">
        <NavEditorial variante="barra" />
        <div class="mx-auto max-w-[1600px] px-5 pb-16 pt-10 sm:px-8 lg:pb-20 lg:pt-16">
          <p class="kicker" data-sube>Así funciona Rankit</p>
          <h1 class="titular mt-4 text-[14vw] sm:text-[11vw] lg:text-[150px]" data-intro>De la idea<br /><span class="text-[var(--neon)]">a la copa.</span></h1>
          <div class="mt-8 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <p class="max-w-md text-lg text-[var(--gris)]" data-sube>Baja y mira cómo un torneo pasa de la nada a tener campeón.</p>
            <div class="flex flex-wrap gap-3" data-sube>
              <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
              <Link href="/register" class="boton boton-linea"><span>Crear torneo</span></Link>
            </div>
          </div>
        </div>
      </section>

      <!-- Recorrido fijo -->
      <section ref="escenario" class="relative h-[100svh] overflow-hidden border-y border-[var(--linea)] bg-[#050505]">
        <div class="mx-auto grid h-full max-w-[1600px] grid-rows-[auto_1fr_auto] gap-4 px-5 py-6 sm:px-8 lg:grid-cols-[1fr_1.15fr] lg:grid-rows-1 lg:items-center lg:gap-12 lg:py-10">
          <!-- Izquierda: número, logo y texto del capítulo -->
          <div class="flex items-center gap-5 lg:flex-col lg:items-start lg:gap-8 lg:self-start lg:pt-[6vh]">
            <div class="flex items-baseline gap-3">
              <span class="titular text-6xl text-[var(--neon)] sm:text-8xl">{{ numero }}</span>
              <span class="ui text-white/40">/ 0{{ capitulos.length }}</span>
            </div>
            <LogoMorph ref="logo" modo="manual" class="h-16 w-16 shrink-0 sm:h-24 sm:w-24 lg:h-[min(30vh,280px)] lg:w-[min(30vh,280px)]" />
          </div>

          <!-- Derecha: imagen del capítulo (se destapan una sobre otra) -->
          <div class="relative min-h-0 overflow-hidden border border-white/10 lg:order-none lg:aspect-[4/3] lg:h-auto">
            <div v-for="(c, i) in capitulos" :key="c.titulo" ref="capas" class="absolute inset-0">
              <VideoBucle v-if="c.video" :src="c.video.video" :poster="c.video.poster" :alt="c.titulo" />
              <img v-else :src="c.imagen" :alt="c.titulo" class="h-full w-full bg-[#0d0d0d] object-cover object-left-top" />
            </div>
          </div>

          <!-- Textos de los capítulos (encimados, se cambian con el scroll) -->
          <div class="relative min-h-[150px] lg:absolute lg:bottom-[12%] lg:left-8 lg:w-[38%] xl:left-[max(2rem,calc((100vw-1600px)/2+2rem))]">
            <div v-for="c in capitulos" :key="c.titulo" ref="textos" class="absolute inset-x-0 top-0">
              <h2 class="titular text-5xl sm:text-6xl lg:text-7xl">{{ c.titulo }}</h2>
              <p class="mt-3 max-w-md text-lg text-[var(--gris)]">{{ c.texto }}</p>
            </div>
          </div>
        </div>

        <!-- Progreso -->
        <div class="absolute right-4 top-1/2 hidden -translate-y-1/2 flex-col gap-3 lg:flex" aria-hidden="true">
          <span v-for="(c, i) in capitulos" :key="i" ref="puntos" class="punto h-10 w-[3px] bg-white/15 transition-colors duration-300" :class="{ activo: i === 0 }"></span>
        </div>
      </section>

      <SeccionEventos :eventos="eventos" :precio-base="precioBase" />
      <SeccionCifras />
      <SeccionPlanes :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionPreguntas :precio-base="precioBase" :precio-pro="precioPro" />
      <SeccionCierre arriba="Tu copa" abajo="te espera" />
    </div>
  </BaseEditorial>
</template>

<style scoped>
.punto.activo { background: var(--neon); }
</style>
