<script setup lang="ts">
/**
 * Propuesta K — enfocada a vender.
 *
 * Hero: la composición de la A (TORNEOS / logo 3D / EN VIVO) sobre la arena,
 * con "Organizar mi torneo" y "Cotizar evento" a la vista, y la barra fija con
 * "Contratar" (NavVentas).
 *
 * Al bajar, la pantalla se fija y hace un recorrido por 4 escenas (fotos reales retocadas)
 * (recorridoEscenas.ts): la cámara se acerca a un punto y por ahí se abre la
 * siguiente (el escenario de la arena, el monitor del gamer, el balón…).
 * Enfrente va el modelo 3D, que se transforma al cambiar de escena (R →
 * control → balón → trofeo), con una frase de venta y chips tipo overlay de
 * stream (en vivo, puntos, bolsa).
 *
 * Después, solo lo que ayuda a vender: qué incluye, cifras, planes (con "A la
 * medida"), torneos abiertos para jugadores, partners, preguntas y contacto.
 */
import { Link } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import BaseEditorial from '@/Components/Propuestas/BaseEditorial.vue'
import NavVentas from '@/Components/Propuestas/NavVentas.vue'
import Logo3D from '@/Components/Propuestas/Logo3D.vue'
import SeccionIncluye from '@/Components/Propuestas/SeccionIncluye.vue'
import SeccionCifras from '@/Components/Propuestas/SeccionCifras.vue'
import SeccionPlanes from '@/Components/Propuestas/SeccionPlanes.vue'
import SeccionEventos from '@/Components/Propuestas/SeccionEventos.vue'
import SeccionAlianza from '@/Components/Propuestas/SeccionAlianza.vue'
import SeccionPreguntas from '@/Components/Propuestas/SeccionPreguntas.vue'
import SeccionMedida from '@/Components/Propuestas/SeccionMedida.vue'
import SeccionCierre from '@/Components/Propuestas/SeccionCierre.vue'
import { cifras, objetos, type Eventos } from '@/Components/Propuestas/medios'
import Objeto3D from '@/Components/Propuestas/Objeto3D.vue'
import type { NombreObjeto } from '@/Components/Propuestas/objetos3d'
import { cajaRelativa, gsap, nuevaIntro, ScrollTrigger, sinMovimiento, SplitText, useAnimaciones, type Despues } from '@/Components/Propuestas/animaciones'
import { fase } from '@/Components/Propuestas/recorridoEscenas'
import { escenasK } from '@/Components/Propuestas/escenasK'
import FondoCuadros from '@/Components/Propuestas/FondoCuadros.vue'
import { FORMAS } from '@/Components/Propuestas/logoFormas'
import type { Escena3D } from '@/Components/Propuestas/logo3d'

const props = defineProps<{ precioBase: number; precioPro: number; eventos: Eventos }>()

const waVentas = 'https://wa.me/525532351392?text=' + encodeURIComponent('Hola Rankit, quiero cotizar un torneo o evento.')
const cupo = computed(() => props.eventos?.semanal?.cupo || 100)
const bolsa = computed(() => (props.eventos?.semanal?.premio_lleno || 500).toLocaleString('es-MX'))

// Escenas: fotos reales retocadas (ver medios.ts). El portal es por dónde se entra a la siguiente.
// Fotos de Unsplash: arena photo-1770067665792, gamer photo-1534423861386, fútbol photo-1604651684549, trofeo photo-1720299580598.
// Escenas (fotos y portales): escenasK.ts. Aquí solo se dibujan sus cuadros (FondoCuadros).

// Nota: punto + línea + texto, sin caja. x/y = dónde cae el punto (en % del hueco del objeto);
// lado = hacia dónde sale el texto.
type Nota = { x: number; y: number; lado: 'izq' | 'der'; etiqueta: string; texto: string; vivo?: boolean; soloGrande?: boolean }
// modelo: el objeto 3D que va enfrente de la escena (objetos3d.ts).
type Paso = { titulo: string; sub: string; cta: { texto: string; href: string }; modelo: NombreObjeto | null; notas: Nota[] }
const pasos = computed<Paso[]>(() => [
  {
    titulo: 'Crea tu torneo', sub: 'Reglas, puntos y premios a tu manera. Listo en minutos.',
    cta: { texto: 'Contratar', href: '/contratar/base' },
    modelo: null,
    notas: [
      { x: 30, y: 28, lado: 'izq', etiqueta: 'Semanales', texto: `Cupo ${cupo.value}` },
      { x: 70, y: 72, lado: 'der', etiqueta: 'Bolsa', texto: `hasta $${bolsa.value}`, soloGrande: true },
    ],
  },
  {
    titulo: 'Sube de viewers', sub: 'Tu tabla en vivo dentro del stream y cada eliminación suma sola.',
    cta: { texto: 'Ver qué incluye', href: '#plataforma' },
    modelo: 'control',
    notas: [
      { x: 26, y: 24, lado: 'izq', etiqueta: 'Stream', texto: 'En vivo', vivo: true },
      { x: 74, y: 74, lado: 'der', etiqueta: 'Widget OBS', texto: 'Tabla en tu stream', soloGrande: true },
    ],
  },
  {
    titulo: 'Arma tu liga', sub: 'Fútbol 7, Fortnite, LoL o Valorant: jornadas, resultados y tabla al día.',
    cta: { texto: 'Ver planes', href: '#pricing' },
    modelo: 'balon',
    notas: [
      { x: 22, y: 30, lado: 'izq', etiqueta: 'Liga', texto: 'Jornada 5' },
      { x: 80, y: 70, lado: 'der', etiqueta: 'Tabla', texto: 'Al día', soloGrande: true },
    ],
  },
  {
    titulo: 'Corona al campeón', sub: 'Clasificados, final y premios en vivo. ¿Vas a jugar? Aparta tu lugar.',
    cta: { texto: 'Organizar mi torneo', href: '/contratar/base' },
    modelo: 'trofeo',
    notas: [
      { x: 20, y: 16, lado: 'izq', etiqueta: 'Gran final', texto: 'Campeón' },
      { x: 60, y: 66, lado: 'der', etiqueta: 'Bolsa', texto: `hasta $${bolsa.value}`, soloGrande: true },
    ],
  },
])
const n = escenasK.length - 1

const raiz = ref<HTMLElement | null>(null)
const heroEl = ref<HTMLElement | null>(null)
const fondo = ref<InstanceType<typeof FondoCuadros> | null>(null)
const velo = ref<HTMLElement | null>(null)
const degradados = ref<HTMLElement | null>(null)
const adornos = ref<HTMLElement | null>(null)
const lineaH = ref<HTMLElement | null>(null)
const lineaV = ref<HTMLElement | null>(null)
const circulo = ref<HTMLElement | null>(null)
const brillo = ref<HTMLElement | null>(null)
const lienzo3d = ref<HTMLElement | null>(null)
const logoPlano = ref<HTMLElement | null>(null)
const kicker = ref<HTMLElement | null>(null)
const palabra1 = ref<HTMLElement | null>(null)
const palabra2 = ref<HTMLElement | null>(null)
const deMexico = ref<HTMLElement | null>(null)
const esquinaIzq = ref<HTMLElement | null>(null)
const esquinaDer = ref<HTMLElement | null>(null)
const slotHero = ref<HTMLElement | null>(null)
const slotHistoria = ref<HTMLElement | null>(null)
const historia = ref<HTMLElement | null>(null)
const activo = ref(0)
const modo = ref<'cargando' | '3d' | 'plano'>('cargando')
const estatico = ref(false)

// ── El hero arranca de inmediato con el logo plano. El 3D llega cuando la página ya cargó
// (Logo3D diferido) y se arma encima mientras el plano se desvanece. Antes la entrada lo
// esperaba hasta 1.2 s y eso retrasaba todo lo que se ve primero.
nuevaIntro()()
let escena3d: Escena3D | null = null
let heroArrancado = false
let scrollTl: gsap.core.Timeline | null = null
const avance = { t: 0 }

// Dónde va el logo 3D (px dentro del hero). Al bajar crece: la cámara "entra" al logo.
const enc = { cx: 0, cy: 0, alto: 0 }
function enHero() {
  if (!slotHero.value || !heroEl.value) return { cx: 0, cy: 0, alto: 0 }
  const c = cajaRelativa(slotHero.value, heroEl.value)
  // En celular las palabras quedan pegadas al logo: ahí no debe pasarse del hueco.
  return { cx: c.x + c.w / 2, cy: c.y + c.h / 2, alto: c.h * (window.innerWidth < 1024 ? 0.95 : 1.2) }
}

function pintarLogo() {
  escena3d?.encuadre(enc.cx, enc.cy, enc.alto)
  const b = brillo.value
  if (b) {
    const d = enc.alto * 2.1
    b.style.left = `${enc.cx}px`
    b.style.top = `${enc.cy}px`
    b.style.width = b.style.height = `${d}px`
  }
}

const suave = (x: number) => { const c = Math.min(1, Math.max(0, x)); return c * c * (3 - 2 * c) }
/**
 * Objetos 3D enfrente de cada escena (control, balón, trofeo: objetos3d.ts).
 * El de la escena k llega cuando su escena ya casi cubre la pantalla y se va
 * hacia la cámara (crece) cuando arranca el acercamiento a la siguiente: como
 * si los pasaras de largo. Nunca hay dos a la vez, así que comparten un lienzo.
 */
const objeto3d = ref<InstanceType<typeof Objeto3D> | null>(null)
const objetoEl = ref<HTMLElement | null>(null)
const datosObjetos = {
  control: { alt: 'Control de videojuegos', respaldo: objetos.control },
  balon: { alt: 'Balón de fútbol', respaldo: objetos.balon },
  trofeo: { alt: 'Trofeo de campeón', respaldo: objetos.trofeo },
}
/** Una vuelta completa mientras dura la escena (desde que entra hasta que se va). */
function vueltaDe(k: number, t: number) {
  const ini = k - 0.6
  const fin = k < n ? k + 0.5 : n
  return Math.min(1, Math.max(0, (t - ini) / (fin - ini)))
}

let visible = false
function pintarObjetos(t: number) {
  const el = objetoEl.value
  const o3d = objeto3d.value
  if (!el || !o3d) return
  let k = -1, entra = 0, sale = 0
  for (let i = 0; i < pasos.value.length; i++) {
    if (!pasos.value[i].modelo) continue
    // Entra cuando su escena ya casi cubre la pantalla y se va cuando arranca el siguiente acercamiento:
    // así el objeto nunca queda sobre el fondo de otra escena.
    const e = i === 0 ? 1 : suave((t - (i - 1) - 0.55) / 0.25)
    const s = i < n ? suave((t - i - 0.08) / 0.25) : 0
    if (e * (1 - s) > 0.002) { k = i; entra = e; sale = s; break }
  }
  const se = k >= 0
  o3d.vivo(se)
  if (se !== visible) {
    visible = se
    el.dataset.ve = se ? '1' : ''
    el.style.visibility = se ? 'visible' : 'hidden'
  }
  if (!se) return
  o3d.ver(pasos.value[k].modelo)
  o3d.girar(vueltaDe(k, t) * 1.5) // vuelta y media por escena, más el giro en reposo
  // Solo transform y opacidad (los filtros como blur se recalculan en cada cuadro y alentaban todo).
  const escala = (0.62 + 0.38 * entra) * (1 + 0.75 * sale)
  const giro = (1 - entra) * -9 + sale * 5
  el.style.opacity = String(entra * (1 - sale))
  el.style.transform = `translateY(${(1 - entra) * 46}px) scale(${escala}) rotate(${giro}deg)`
}

function pintarRecorrido() {
  fondo.value?.pintar(avance.t)
  const i = fase(avance.t, n).activo
  if (i !== activo.value) activo.value = i
  pintarObjetos(avance.t)
}
function alRecalcular() {
  if (!scrollTl) Object.assign(enc, enHero())
  pintarLogo()
  pintarRecorrido()
}

// Inclinación con el mouse (solo escritorio): variables CSS en el hueco de los objetos.
function alMover(e: PointerEvent) {
  if (e.pointerType !== 'mouse' || !slotHistoria.value) return
  const x = (e.clientX / window.innerWidth - 0.5) * 2
  const y = (e.clientY / window.innerHeight - 0.5) * 2
  slotHistoria.value.style.setProperty('--ry', `${(x * 10).toFixed(2)}deg`)
  slotHistoria.value.style.setProperty('--rx', `${(-y * 8).toFixed(2)}deg`)
}

function listo(e: Escena3D) {
  escena3d = e
  e.forma('logo')
  alRecalcular()
  // Si ya va bajando, el logo queda como lo pide el scroll (sin armarse ni dibujar si ya no se ve).
  const bajando = (scrollTl?.scrollTrigger?.progress ?? 0) > 0.001
  if (bajando) e.pausar(Number(lienzo3d.value?.style.opacity || '1') < 0.01)
  else if (heroArrancado) e.armar()
  modo.value = '3d'
}
function plano() {
  modo.value = 'plano'
}

function hero(despues: Despues) {
  heroArrancado = true
  alRecalcular()
  escena3d?.armar(0.1)
  gsap.set(brillo.value, { xPercent: -50, yPercent: -50 })
  const letras = (el: HTMLElement) => SplitText.create(el, { type: 'lines,chars', mask: 'lines', linesClass: 'linea' }).chars

  // Entrada corta (~1 s). Antes eran ~2 s y el scroll no respondía hasta que terminaba.
  gsap.timeline({ defaults: { ease: 'expo.out' } })
    .from(lineaH.value, { scaleX: 0, duration: 0.9, ease: 'expo.inOut' }, 0)
    .from(lineaV.value, { scaleY: 0, duration: 0.9, ease: 'expo.inOut' }, 0)
    .from(circulo.value, { scale: 0.6, autoAlpha: 0, duration: 1 }, 0.05)
    .from(brillo.value, { scale: 0.3, autoAlpha: 0, duration: 1.1, ease: 'power2.out' }, 0.1)
    .from(letras(palabra1.value!), { yPercent: 115, duration: 0.8, stagger: 0.03 }, 0.15)
    .from(letras(palabra2.value!), { yPercent: 115, duration: 0.8, stagger: 0.03 }, 0.25)
    .from([kicker.value, deMexico.value], { autoAlpha: 0, y: 14, duration: 0.6 }, 0.35)

  // Scroll: el hero se fija, la cámara entra al logo y empieza el recorrido.
  const alDesplazar = () => {
    const logo = { o: 1 }
    scrollTl = gsap.timeline({
      scrollTrigger: {
        trigger: heroEl.value, start: 'top top', end: () => `+=${window.innerHeight * (0.55 + n * 0.65)}`,
        scrub: 0.25, pin: true, anticipatePin: 1, invalidateOnRefresh: true,
        onUpdate: (self) => {
          // Durante refresh() ScrollTrigger deshace el pin y avisa con un avance que no es real
          // (con la página arriba del todo): eso disparaba la descarga del recorrido al cargar.
          // El hero es lo primero de la página, así que sin scroll el avance es 0.
          const avanceReal = window.scrollY > 0 ? self.progress : 0
          recorridoTerminado.value = avanceReal > 0.985
          // Three.js y los modelos se piden al empezar a bajar, no al cargar la página.
          if (avanceReal > 0) empezarAMoverse()
        },
      },
      defaults: { ease: 'none' },
    })
      .fromTo(palabra1.value, { yPercent: 0, opacity: 1 }, { yPercent: -160, opacity: 0, duration: 0.8, immediateRender: false }, 0)
      .fromTo([palabra2.value, deMexico.value], { yPercent: 0, opacity: 1 }, { yPercent: 160, opacity: 0, duration: 0.8, immediateRender: false }, 0)
      .fromTo([kicker.value, esquinaIzq.value, esquinaDer.value], { opacity: 1 }, { opacity: 0, duration: 0.4, immediateRender: false }, 0)
      // El logo plano (mientras llega el 3D) también se va hacia la cámara.
      .fromTo(logoPlano.value, { scale: 1, opacity: 1 }, { scale: 3, opacity: 0, duration: 0.6, ease: 'power2.in', immediateRender: false }, 0)
      // La cámara entra al logo: crece mucho y se desvanece; luego el 3D deja de dibujar.
      .fromTo(enc,
        { cx: () => enHero().cx, cy: () => enHero().cy, alto: () => enHero().alto },
        { cx: () => enHero().cx, cy: () => enHero().cy, alto: () => enHero().alto * 7, duration: 1, ease: 'power2.in', onUpdate: pintarLogo }, 0)
      .to(logo, {
        o: 0, duration: 0.45,
        onUpdate: () => {
          if (lienzo3d.value) lienzo3d.value.style.opacity = String(logo.o)
          if (brillo.value) brillo.value.style.opacity = String(logo.o)
          escena3d?.pausar(logo.o < 0.01)
        },
      }, 0.55)
      .to(velo.value, { opacity: 0.15, duration: 1 }, 0)
      .to(adornos.value, { opacity: 0, duration: 0.6 }, 0)
      .to(degradados.value, { opacity: 1, duration: 0.5 }, 0.6)
      .to(historia.value, { autoAlpha: 1, duration: 0.4 }, 0.75)
      .to(avance, { t: n, duration: n, onUpdate: pintarRecorrido }, 1)
      .to({}, { duration: 0.2 })
  }
  // El scroll queda listo desde el inicio. Los fromTo con immediateRender: false no guardan
  // la opacidad 0 de la entrada como punto de partida (el bug por el que antes se esperaba).
  despues(alDesplazar)
}

// El botón de WhatsApp no aparece hasta terminar el recorrido (en el hero tapaba los botones).
const recorridoTerminado = ref(false)

/**
 * Lo del recorrido se pide cuando la persona empieza a moverse: al cargar solo baja lo que
 * se ve en el hero. Las fotos y cuadros del fondo (solo red) también 3 s después de cargar,
 * para tenerlos listos; los objetos 3D (red + armar la escena) solo al moverse.
 */
let enMovimiento = false
function empezarAMoverse() {
  if (enMovimiento) return
  enMovimiento = true
  fondo.value?.activar()
  objeto3d.value?.preparar()
}
// pointermove: en escritorio el mouse se mueve antes de bajar, y crear el WebGL ahí no se nota.
const TOQUES = ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart'] as const
let tras: ReturnType<typeof setTimeout> | undefined
const alCargar = () => { tras = setTimeout(() => fondo.value?.activar(), 3000) }

useAnimaciones(raiz, hero, { suave: false })

onMounted(() => {
  estatico.value = sinMovimiento()
  TOQUES.forEach((e) => window.addEventListener(e, empezarAMoverse, { passive: true, once: true }))
  if (document.readyState === 'complete') alCargar()
  else window.addEventListener('load', alCargar, { once: true })
  ScrollTrigger.addEventListener('refresh', alRecalcular)
  window.addEventListener('pointermove', alMover, { passive: true })
  pintarRecorrido()
})
onUnmounted(() => {
  clearTimeout(tras)
  window.removeEventListener('load', alCargar)
  TOQUES.forEach((e) => window.removeEventListener(e, empezarAMoverse))
  ScrollTrigger.removeEventListener('refresh', alRecalcular)
  window.removeEventListener('pointermove', alMover)
})
</script>

<template>
  <BaseEditorial actual="k" titulo="Propuesta K · Ventas" sin-intro :contacto="recorridoTerminado || estatico">
    <NavVentas :precio-base="props.precioBase" :precio-pro="props.precioPro" />

    <div ref="raiz" class="preparando">
      <section ref="heroEl" class="relative flex min-h-[100svh] flex-col overflow-hidden">
        <!-- Fondo del recorrido en un solo canvas (la primera escena es el fondo del hero) -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[100svh]" aria-hidden="true">
          <FondoCuadros ref="fondo" :estaticas="escenasK" />
        </div>
        <!-- Velo: oscuro en el hero (se leen las letras), claro en el recorrido -->
        <div ref="velo" class="pointer-events-none absolute inset-x-0 top-0 h-[100svh] bg-[#050505] opacity-[.68]" aria-hidden="true"></div>
        <div ref="degradados" class="pointer-events-none absolute inset-x-0 top-0 h-[100svh] opacity-0" aria-hidden="true">
          <div class="absolute inset-0" style="background: linear-gradient(to top, #050505 0%, rgba(5,5,5,.78) 28%, rgba(5,5,5,0) 58%), linear-gradient(to bottom, rgba(5,5,5,.7) 0%, rgba(5,5,5,0) 16%)"></div>
          <div class="absolute inset-0 hidden lg:block" style="background: linear-gradient(to right, rgba(5,5,5,.82) 0%, rgba(5,5,5,.35) 38%, rgba(5,5,5,0) 58%)"></div>
        </div>

        <!-- Retícula, cruz y círculo (solo en el hero) -->
        <div ref="adornos" class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true" data-intro>
          <div class="reticula absolute inset-0"></div>
          <div ref="lineaH" class="absolute inset-x-0 top-1/2 h-px origin-center bg-[var(--linea)]"></div>
          <div ref="lineaV" class="absolute inset-y-0 left-1/2 w-px origin-center bg-[var(--linea)]"></div>
          <div ref="circulo" class="absolute left-1/2 top-1/2 aspect-square w-[min(92vw,900px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[var(--linea)]"></div>
        </div>

        <!-- Luz morada detrás del logo y el logo 3D (el lienzo cubre el hero; solo vive en el hero) -->
        <div ref="brillo" class="pointer-events-none absolute left-1/2 top-1/2 rounded-full" style="background: radial-gradient(circle, rgba(191,0,255,.34) 0%, rgba(191,0,255,.1) 38%, rgba(191,0,255,0) 66%)" aria-hidden="true" data-intro></div>
        <div ref="lienzo3d" class="pointer-events-none absolute inset-0 z-[5]" data-intro>
          <Logo3D :respaldo="false" diferir @listo="listo" @plano="plano" />
        </div>

        <div class="relative z-10 mx-auto flex w-full max-w-[1600px] flex-1 flex-col justify-center px-5 pb-6 pt-24 sm:px-8 lg:pb-4 lg:pt-28">
          <div class="mx-auto w-full max-w-[1100px]">
            <p ref="kicker" class="ui text-white/70" data-intro>Somos la plataforma de</p>
            <h1 ref="palabra1" class="titular mt-1 text-[17vw] sm:text-[13vw] lg:text-[min(200px,19vh)]" data-intro>Torneos</h1>
            <!-- Hueco del logo (sin 3D, aquí va el logo plano) -->
            <div ref="slotHero" class="my-2 h-[25svh] sm:h-[25vh] lg:h-[min(280px,26vh)]">
              <!-- El plano se ve desde el primer cuadro y se desvanece cuando llega el 3D. -->
              <div ref="logoPlano" class="h-full">
                <svg viewBox="0 0 100 100" class="mx-auto h-full transition-opacity duration-700" :class="modo === '3d' ? 'opacity-0' : 'opacity-100'" role="img" aria-label="Logo de Rankit">
                  <path v-for="(p, i) in FORMAS.logo" :key="i" :d="p.d" :fill="p.color" />
                </svg>
              </div>
            </div>
            <p ref="palabra2" class="titular text-right text-[17vw] sm:text-[13vw] lg:text-[min(200px,19vh)]" aria-hidden="true" data-intro>En vivo</p>
            <p ref="deMexico" class="ui text-right text-white/70" data-intro>De México</p>
          </div>
        </div>

        <!-- Esquinas: prueba social a la izquierda, compra a la derecha -->
        <div class="relative z-10 mx-auto grid w-full max-w-[1600px] gap-8 px-5 pb-8 sm:grid-cols-2 sm:px-8 sm:pb-10">
          <div ref="esquinaIzq" class="hidden self-end sm:block">
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1">
              <dt class="ui text-white">{{ cifras.torneos }}+</dt><dd class="ui text-[var(--gris)]">Torneos</dd>
              <dt class="ui text-white">{{ cifras.jugadores.toLocaleString('es-MX') }}</dt><dd class="ui text-[var(--gris)]">Jugadores</dd>
              <dt class="ui text-white">{{ cifras.partidas }}</dt><dd class="ui text-[var(--gris)]">Partidas procesadas</dd>
            </dl>
          </div>
          <div ref="esquinaDer" class="max-w-md self-end sm:justify-self-end">
            <p class="ui leading-relaxed">
              Organiza tu torneo hoy.
              <span class="text-[var(--gris)]">Inscripciones, tabla en vivo y resultados sin Excel. Desde ${{ props.precioBase.toLocaleString('es-MX') }}, pago único.</span>
            </p>
            <div class="mt-5 flex flex-wrap items-center gap-3">
              <Link href="/contratar/base" class="boton boton-lleno !px-6 !py-3"><span>Organizar mi torneo</span></Link>
              <a :href="waVentas" target="_blank" rel="noopener" class="boton boton-linea !px-6 !py-3"><span>Cotizar evento</span></a>
            </div>
            <Link href="/torneos" class="ui mt-4 inline-flex items-center gap-2 text-white/60 hover:text-white">¿Vas a jugar? Ver todos los torneos <i class="ph-bold ph-arrow-right"></i></Link>
          </div>
        </div>

        <!-- Recorrido: frase de venta a la izquierda (abajo en celular), objeto real y notas a la derecha -->
        <div v-if="!estatico" ref="historia" class="pointer-events-none absolute inset-x-0 top-0 z-10 h-[100svh]" style="visibility: hidden; opacity: 0">
          <div class="mx-auto grid h-full max-w-[1600px] grid-rows-[1fr_auto] px-5 pb-24 pt-20 sm:px-8 lg:grid-cols-[1.1fr_1fr] lg:grid-rows-1 lg:items-center lg:gap-10 lg:pb-12 lg:pt-24">
            <!-- Objeto real (foto recortada) y notas finas -->
            <div class="relative order-1 flex items-center justify-center lg:order-2 lg:h-full">
              <div ref="slotHistoria" class="relative h-[30svh] w-[min(70vw,400px)] lg:h-[min(54vh,480px)] lg:w-[min(32vw,480px)]">
                <div class="pointer-events-none absolute inset-[-25%] rounded-full" style="background: radial-gradient(circle, rgba(191,0,255,.28) 0%, rgba(191,0,255,.08) 40%, rgba(191,0,255,0) 68%)" aria-hidden="true"></div>
                <div ref="objetoEl" data-objeto class="absolute inset-0" style="visibility: hidden; opacity: 0">
                  <div class="sombra" aria-hidden="true"></div>
                  <div class="inclinar h-full w-full">
                    <div class="flotar h-full w-full">
                      <Objeto3D ref="objeto3d" :objetos="datosObjetos" />
                    </div>
                  </div>
                </div>
                <template v-for="(p, i) in pasos" :key="`notas-${p.titulo}`">
                  <div
                    v-for="(a, j) in p.notas" :key="`${i}-${j}`"
                    class="nota absolute flex items-center gap-2.5 transition-all sm:gap-3"
                    :class="[a.lado === 'izq' ? 'flex-row-reverse text-right' : '', 'hidden sm:flex', activo === i ? 'opacity-100 duration-300' : 'opacity-0 duration-150']"
                    :style="{
                      top: `${a.y}%`,
                      ...(a.lado === 'izq' ? { right: `${100 - a.x}%` } : { left: `${a.x}%` }),
                      transform: `translateY(-50%) translateX(${activo === i ? 0 : a.lado === 'izq' ? 14 : -14}px)`,
                      transitionDelay: activo === i ? `${120 + j * 90}ms` : '0ms',
                    }"
                  >
                    <span class="relative h-2.5 w-2.5 shrink-0 rounded-full" :class="a.vivo ? 'bg-red-500' : 'bg-white'">
                      <span class="absolute -inset-1.5 rounded-full border" :class="a.vivo ? 'animate-ping border-red-500/70' : 'border-white/40'"></span>
                    </span>
                    <span class="h-px w-8 shrink-0 bg-white/60 sm:w-14"></span>
                    <span class="whitespace-nowrap leading-tight">
                      <span class="ui block !text-[10px] text-white/65">{{ a.etiqueta }}</span>
                      <span class="block text-sm font-semibold sm:text-lg">{{ a.texto }}</span>
                    </span>
                  </div>
                </template>
              </div>
            </div>

            <!-- Frase de venta -->
            <div class="order-2 self-end lg:order-1 lg:self-center">
              <p class="ui text-white/60"><span class="text-[var(--neon)]">{{ String(activo + 1).padStart(2, '0') }}</span> / {{ String(n + 1).padStart(2, '0') }}</p>
              <div class="relative mt-2 h-[1.76em] text-[13vw] sm:text-7xl lg:text-[min(118px,8vw)]">
                <h2 v-for="(p, i) in pasos" :key="p.titulo" class="titular absolute inset-x-0 bottom-0 transition-all" :class="activo === i ? 'translate-y-0 opacity-100 duration-300' : 'translate-y-6 opacity-0 duration-150'" :aria-hidden="activo !== i">{{ p.titulo }}</h2>
              </div>
              <div class="relative mt-4 min-h-[3em] max-w-md text-lg leading-snug text-white/80 sm:text-xl">
                <p v-for="(p, i) in pasos" :key="p.titulo" class="absolute inset-x-0 top-0 transition-all" :class="activo === i ? 'translate-y-0 opacity-100 delay-75 duration-300' : 'translate-y-3 opacity-0 duration-150'" :aria-hidden="activo !== i">{{ p.sub }}</p>
              </div>
              <div class="relative mt-5 h-12">
                <!-- La transición va en el envoltorio: si el .boton (inclinado y con overflow) cambia de
                     opacidad, Chrome pinta el texto recortado a la mitad. -->
                <div
                  v-for="(p, i) in pasos" :key="p.titulo"
                  class="absolute left-0 top-0 transition-opacity" :class="activo === i ? 'pointer-events-auto opacity-100 delay-100 duration-300' : 'invisible opacity-0 duration-150'"
                  :aria-hidden="activo !== i"
                >
                  <component
                    :is="p.cta.href.startsWith('#') ? 'a' : Link" :href="p.cta.href"
                    class="boton !px-6 !py-3" :class="i === 0 || i === n ? 'boton-lleno' : 'boton-linea'"
                    :tabindex="activo === i ? 0 : -1"
                  ><span>{{ p.cta.texto }}</span></component>
                </div>
              </div>
              <div class="mt-6 flex gap-2" aria-hidden="true">
                <span v-for="(p, i) in pasos" :key="p.titulo" class="h-1 w-8 skew-x-[-10deg] transition-colors duration-300 sm:w-10" :class="i <= activo ? 'bg-[var(--neon)]' : 'bg-white/20'"></span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Sin movimiento: el recorrido como lista, sin fijar la pantalla -->
      <section v-if="estatico" class="mx-auto grid max-w-7xl gap-8 px-6 py-20 md:grid-cols-2 lg:grid-cols-3">
        <div v-for="p in pasos" :key="p.titulo">
          <h2 class="titular text-4xl">{{ p.titulo }}</h2>
          <p class="mt-2 text-[var(--gris)]">{{ p.sub }}</p>
        </div>
      </section>

      <SeccionIncluye :precio-base="props.precioBase" />
      <SeccionCifras />
      <SeccionPlanes :precio-base="props.precioBase" :precio-pro="props.precioPro" medida />
      <SeccionEventos :eventos="props.eventos" :precio-base="props.precioBase" />
      <SeccionAlianza />
      <SeccionPreguntas :precio-base="props.precioBase" :precio-pro="props.precioPro" />
      <SeccionMedida />
      <SeccionCierre />
    </div>
  </BaseEditorial>
</template>

<style scoped>
/* Objetos: sombra fija en el piso (un degradado, no drop-shadow: ese filtro se recalculaba
   en cada cuadro del giro), flotan solos y se inclinan con el mouse. */
.sombra { position: absolute; left: 20%; right: 20%; bottom: -3%; height: 8%; border-radius: 50%; background: radial-gradient(ellipse at center, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0) 70%); }
[data-objeto]:not([data-ve='1']) .flotar { animation-play-state: paused; }
.inclinar { transform: perspective(900px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg)); transition: transform 0.6s cubic-bezier(.2, .8, .2, 1); }
.flotar { animation: flotar 6s ease-in-out infinite; }
@keyframes flotar { 0%, 100% { transform: translateY(0) rotate(-1.5deg); } 50% { transform: translateY(-12px) rotate(1.5deg); } }
/* Notas sobre la foto: sin caja, con sombra de texto para que se lean. */
.nota { text-shadow: 0 1px 14px rgba(0, 0, 0, 0.85), 0 0 2px rgba(0, 0, 0, 0.6); }
</style>
