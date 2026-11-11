<script setup lang="ts">
/**
 * Propuesta I — "Cartas". Tres cartas coleccionables holográficas con
 * rareza, como las de Fortnite o FIFA. Cada carta es algo que te da Rankit,
 * con el logo convertido en su emblema (control, balón, trofeo). Se reparten
 * volteándose, se inclinan con el mouse y el brillo holográfico lo sigue.
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
import { gsap, SplitText, sinMovimiento, useAnimaciones } from '@/Components/Propuestas/animaciones'
import type { NombreForma } from '@/Components/Propuestas/logoFormas'

defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

type Carta = { forma: NombreForma; rareza: string; color: string; nombre: string; imagen: string; stats: [string, string][]; giro: number }
const cartas: Carta[] = [
  { forma: 'balon', rareza: 'Rara', color: '#2f8cff', nombre: 'Liga de barrio', imagen: medios.futbol.poster, stats: [['Juego', 'Fútbol 7'], ['Tabla', 'Al día'], ['Equipos', 'Los que quieras']], giro: -9 },
  { forma: 'control', rareza: 'Épica', color: '#bf00ff', nombre: 'Scrim de Fortnite', imagen: medios.mirada.poster, stats: [['Puntos', 'Del replay'], ['Tabla', 'En vivo'], ['Stream', 'Widget OBS']], giro: 0 },
  { forma: 'trofeo', rareza: 'Legendaria', color: '#f5a524', nombre: 'Gran final', imagen: medios.final.poster, stats: [['Fases', '4'], ['Premios', 'En efectivo'], ['Clasificados', 'Automáticos']], giro: 9 },
]

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const titulo = ref<HTMLElement | null>(null)
const texto = ref<HTMLElement | null>(null)
const mazo = ref<HTMLElement | null>(null)
const cartasEl = ref<HTMLElement[]>([])
const emblemas = ref<InstanceType<typeof LogoMorph>[]>([])

// Inclinación y brillo holográfico siguiendo el mouse.
function mover(e: PointerEvent) {
  const el = e.currentTarget as HTMLElement
  const r = el.getBoundingClientRect()
  const x = (e.clientX - r.left) / r.width
  const y = (e.clientY - r.top) / r.height
  el.style.setProperty('--mx', `${x * 100}%`)
  el.style.setProperty('--my', `${y * 100}%`)
  if (sinMovimiento()) return
  gsap.to(el, { rotationY: (x - 0.5) * 22, rotationX: (0.5 - y) * 22, duration: 0.5, ease: 'power3.out' })
}
function soltar(e: PointerEvent) {
  gsap.to(e.currentTarget as HTMLElement, { rotationY: 0, rotationX: 0, duration: 0.8, ease: 'elastic.out(1, 0.5)' })
}

function hero() {
  const h = heroEl.value!
  const letras = SplitText.create(titulo.value!, { type: 'lines,chars', mask: 'lines', linesClass: 'linea' })
  gsap.timeline({ defaults: { ease: 'expo.out' }, onComplete: () => letras.revert() })
    .from(h.querySelector('[data-intro="barra"]'), { autoAlpha: 0, y: -18, duration: 0.9 }, 0)
    .from(letras.chars, { yPercent: 110, duration: 1, stagger: 0.03 }, 0.1)
    .from(texto.value!.children, { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.08 }, 0.5)
    // Se reparten: llegan de abajo volteadas (se ve el reverso) y se dan la vuelta.
    .from(cartasEl.value.map((c) => c.parentElement!), { y: 400, rotation: 0, autoAlpha: 0, duration: 1.1, stagger: 0.14, ease: 'power4.out' }, 0.3)
    .from(cartasEl.value.map((c) => c.querySelector('.giro')!), { rotationY: 180, duration: 1, stagger: 0.14, ease: 'back.out(1.4)' }, 0.6)
    .call(() => emblemas.value.forEach((em, i) => em?.irA(cartas[i].forma, 0.9)), [], 1.2)
}

useAnimaciones(raiz, hero)
</script>

<template>
  <BaseEditorial actual="i" titulo="Propuesta I · Cartas">
    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="reticula relative flex min-h-[100svh] flex-col overflow-hidden">
        <NavEditorial variante="barra" flotante />

        <div class="mx-auto grid w-full max-w-[1600px] flex-1 items-center gap-10 px-5 pb-12 pt-28 sm:px-8 lg:grid-cols-[1fr_1.2fr]">
          <div class="text-center lg:text-left">
            <h1 ref="titulo" class="titular text-[15vw] sm:text-[11vw] lg:text-[min(140px,14vh)]" data-intro>Sube de <span class="text-[var(--neon)]">nivel</span></h1>
            <div ref="texto" data-intro>
              <p class="mx-auto mt-6 max-w-md text-lg text-[var(--gris)] lg:mx-0">Cada torneo en Rankit viene con su tabla en vivo, sus inscripciones y su final. Elige tu carta.</p>
              <div class="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
                <a href="#eventos" class="boton boton-lleno"><span>Reservar lugar</span></a>
                <Link href="/register" class="boton boton-linea"><span>Crear torneo</span></Link>
              </div>
            </div>
          </div>

          <!-- Mazo -->
          <div ref="mazo" class="flex snap-x snap-mandatory justify-start gap-4 overflow-x-auto pb-6 [perspective:1200px] sm:justify-center sm:overflow-visible lg:gap-0" data-intro>
            <div v-for="(c, i) in cartas" :key="c.nombre" class="shrink-0 snap-center lg:-mx-6" :style="{ transform: `rotate(${c.giro}deg) translateY(${Math.abs(c.giro) * 2}px)`, zIndex: c.giro === 0 ? 2 : 1 }">
              <article
                ref="cartasEl"
                class="carta relative w-[64vw] max-w-[260px] cursor-pointer [transform-style:preserve-3d] sm:w-[220px] lg:w-[min(260px,22vw)]"
                :style="{ '--rareza': c.color }"
                @pointermove="mover"
                @pointerleave="soltar"
              >
                <div class="giro relative aspect-[5/7] [transform-style:preserve-3d]">
                  <!-- Frente -->
                  <div class="cara absolute inset-0 flex flex-col overflow-hidden rounded-2xl border-2 bg-[#0c0c0c] p-3 [backface-visibility:hidden]" :style="{ borderColor: c.color }">
                    <div class="flex items-center justify-between">
                      <span class="rounded px-2 py-0.5 font-mono text-[10px] font-bold uppercase text-black" :style="{ background: c.color }">{{ c.rareza }}</span>
                      <LogoMorph ref="emblemas" modo="manual" class="h-7 w-7" />
                    </div>
                    <div class="relative mt-2 flex-1 overflow-hidden rounded-lg">
                      <img :src="c.imagen" :alt="c.nombre" class="h-full w-full object-cover" />
                      <div class="absolute inset-0" :style="{ background: `linear-gradient(to top, ${c.color}55, transparent 60%)` }"></div>
                    </div>
                    <h3 class="titular mt-3 text-2xl leading-none">{{ c.nombre }}</h3>
                    <dl class="mt-2 space-y-1">
                      <div v-for="[k, v] in c.stats" :key="k" class="flex justify-between border-t border-white/10 pt-1 font-mono text-[10px] uppercase">
                        <dt class="text-white/50">{{ k }}</dt><dd class="font-bold">{{ v }}</dd>
                      </div>
                    </dl>
                    <!-- Brillo holográfico que sigue al mouse -->
                    <div class="holo pointer-events-none absolute inset-0 rounded-2xl"></div>
                  </div>
                  <!-- Reverso -->
                  <div class="cara absolute inset-0 flex items-center justify-center rounded-2xl border-2 border-white/20 bg-[#0c0c0c] [backface-visibility:hidden] [transform:rotateY(180deg)]" aria-hidden="true">
                    <div class="reticula absolute inset-0 rounded-2xl opacity-60"></div>
                    <svg class="relative h-16 w-16" viewBox="0 0 100 100"><path d="M15 10 L40 10 L30 90 L5 90 Z" fill="#fff" /><path d="M45 10 L95 10 L75 50 L45 50 Z" fill="#fff" /><path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff" /></svg>
                  </div>
                </div>
              </article>
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
      <SeccionCierre arriba="Juega" abajo="tu carta" />
    </div>
  </BaseEditorial>
</template>

<style scoped>
.carta { --mx: 50%; --my: 30%; }
.carta:hover .cara:first-child { box-shadow: 0 0 40px color-mix(in srgb, var(--rareza) 55%, transparent); }
/* Holográfico: arcoíris suave + destello en la posición del mouse. */
.holo {
  background:
    radial-gradient(circle at var(--mx) var(--my), rgba(255, 255, 255, 0.35), transparent 40%),
    linear-gradient(115deg, transparent 20%, rgba(255, 0, 200, 0.18) 35%, rgba(0, 220, 255, 0.18) 50%, rgba(255, 230, 0, 0.16) 65%, transparent 80%);
  background-size: 100% 100%, 220% 220%;
  background-position: center, var(--mx) var(--my);
  mix-blend-mode: color-dodge;
  opacity: 0.55;
  transition: opacity 0.3s;
}
.carta:hover .holo { opacity: 1; }
</style>
