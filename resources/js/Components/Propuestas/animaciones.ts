/**
 * Movimiento de las propuestas, con las mismas herramientas que la
 * referencia X-Axis: GSAP + ScrollTrigger + SplitText y Lenis para el scroll
 * suave.
 *
 * Las secciones piden su efecto con un atributo (data-anim="letras", etc.) y
 * cada hero arma su propia línea de tiempo con useAnimaciones(raiz, hero).
 * Con "reducir movimiento" no se anima nada y todo queda visible.
 */
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { SplitText } from 'gsap/SplitText'
import Lenis from 'lenis'
import 'lenis/dist/lenis.css'
import './animaciones.css'
import { onMounted, onUnmounted, type Ref } from 'vue'

gsap.registerPlugin(ScrollTrigger, SplitText)

export { gsap, ScrollTrigger, SplitText }

export const sinMovimiento = () =>
  typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches

// ── Intro (pantalla de carga de BaseEditorial) ─────────────────────────────
// El hero arranca cuando termina la intro; si no hay intro, de inmediato.
let terminarIntro: () => void = () => {}
let intro: Promise<void> = Promise.resolve()
export function nuevaIntro(): () => void {
  intro = new Promise((r) => { terminarIntro = r })
  return () => terminarIntro()
}
export const esperarIntro = () => intro

// ── Trabajo en pedazos ────────────────────────────────────────────────────
type ConIdle = { requestIdleCallback?: (f: () => void, o?: { timeout: number }) => number; scheduler?: { yield?: () => Promise<void> } }
/** Espera a que el navegador quede libre (máximo `max` ms). */
export const libre = (max = 1500) => new Promise<void>((r) => {
  const w = window as unknown as ConIdle
  if (w.requestIdleCallback) w.requestIdleCallback(() => r(), { timeout: max })
  else setTimeout(r, 120)
})
/** Cede el hilo para que el navegador pinte y atienda al usuario. */
export const ceder = () => {
  const w = window as unknown as ConIdle
  return w.scheduler?.yield ? w.scheduler.yield() : new Promise<void>((r) => setTimeout(r, 0))
}
/** Corre las tareas en pedazos de ~20 ms cediendo el hilo entre pedazos (nada de tareas largas). */
export async function enPedazos(tareas: (() => void)[], correr: (t: () => void) => void, vivo: () => boolean) {
  let inicio = performance.now()
  for (const t of tareas) {
    if (!vivo()) return
    correr(t)
    if (performance.now() - inicio > 20) {
      await ceder()
      inicio = performance.now()
    }
  }
}

// ── Efectos por atributo ──────────────────────────────────────────────────
const alEntrar = (el: Element, inicio = 'top 85%') => ({ trigger: el, start: inicio, once: true })

/**
 * Los efectos de las secciones de abajo, uno por elemento, como tareas sueltas:
 * se arman en pedazos cuando el navegador queda libre (ver enPedazos). Antes se
 * armaban todos de golpe al cargar (SplitText + ScrollTrigger miden el texto) y
 * eso eran segundos de hilo bloqueado en un celular.
 */
function tareasDeclarativas(raiz: HTMLElement): (() => void)[] {
  const tareas: (() => void)[] = []
  const cada = <T extends HTMLElement>(sel: string, fn: (el: T) => void) =>
    raiz.querySelectorAll<T>(sel).forEach((el) => tareas.push(() => fn(el)))

  // Letras que suben desde atrás de una máscara (títulos de sección).
  cada('[data-anim="letras"]', (el) => {
    SplitText.create(el, {
      // words: cada palabra queda junta. Solo con chars el navegador podía partir una palabra
      // entre dos letras cuando no cabía ("PREGUNTA / S" en celular).
      type: 'lines,words,chars', mask: 'lines', linesClass: 'linea', autoSplit: true,
      onSplit: (s) => gsap.from(s.chars, { yPercent: 115, duration: 1, ease: 'expo.out', stagger: 0.022, scrollTrigger: alEntrar(el) }),
    })
  })

  // Renglones que suben (párrafos y titulares de varias líneas).
  cada('[data-anim="lineas"]', (el) => {
    SplitText.create(el, {
      type: 'lines', mask: 'lines', linesClass: 'linea', autoSplit: true, aria: 'none',
      onSplit: (s) => gsap.from(s.lines, { yPercent: 105, duration: 1.1, ease: 'expo.out', stagger: 0.09, scrollTrigger: alEntrar(el) }),
    })
  })

  // Aparece subiendo.
  cada('[data-anim="subir"]', (el) => {
    gsap.from(el, { y: 50, autoAlpha: 0, duration: 1.1, ease: 'power3.out', scrollTrigger: alEntrar(el, 'top 90%') })
  })

  // Los hijos aparecen uno tras otro (tarjetas, listas).
  cada('[data-anim="escalonar"]', (el) => {
    gsap.from(el.children, { y: 70, autoAlpha: 0, duration: 1.1, ease: 'power3.out', stagger: 0.12, clearProps: 'transform,opacity,visibility', scrollTrigger: alEntrar(el) })
  })

  // Imagen que se destapa de abajo hacia arriba.
  cada('[data-anim="revelar"]', (el) => {
    gsap.fromTo(el, { clipPath: 'inset(100% 0% 0% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.4, ease: 'expo.inOut', scrollTrigger: alEntrar(el) })
  })

  // Texto que se "llena" de gris a blanco palabra por palabra con el scroll.
  cada('[data-anim="llenar"]', (el) => {
    SplitText.create(el, {
      type: 'words', autoSplit: true, aria: 'none',
      onSplit: (s) => gsap.fromTo(s.words, { opacity: 0.2 }, {
        opacity: 1, stagger: 0.1, ease: 'none',
        scrollTrigger: { trigger: el, start: 'top 85%', end: 'bottom 55%', scrub: true },
      }),
    })
  })

  // Se mueve más lento o más rápido que el scroll (data-velocidad en %).
  cada('[data-anim="paralaje"]', (el) => {
    gsap.to(el, {
      yPercent: Number(el.dataset.velocidad ?? -15), ease: 'none',
      scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true },
    })
  })

  // Palabras gigantes que llegan desde los lados mientras bajas (cierre).
  cada('[data-anim="desde-izq"], [data-anim="desde-der"]', (el) => {
    const signo = el.dataset.anim === 'desde-izq' ? -1 : 1
    gsap.fromTo(el, { xPercent: 28 * signo }, {
      xPercent: 0, ease: 'none',
      scrollTrigger: { trigger: el, start: 'top bottom', end: 'top 35%', scrub: true },
    })
  })

  // Cinta que se inclina según la velocidad del scroll (va en el contenedor:
  // la cinta misma ya se mueve con una animación CSS y le ganaría al skew).
  cada('[data-anim="cinta-velocidad"]', (el) => {
    const sesgo = gsap.quickTo(el, 'skewX', { duration: 0.5, ease: 'power3' })
    let quieto: ReturnType<typeof setTimeout> | undefined
    ScrollTrigger.create({
      trigger: el, start: 'top bottom', end: 'bottom top',
      onUpdate: (self) => {
        sesgo(gsap.utils.clamp(-14, 14, self.getVelocity() / -220))
        clearTimeout(quieto)
        quieto = setTimeout(() => sesgo(0), 140)
      },
    })
  })

  // Carril de lado: la sección se fija y su contenido [data-carril] se mueve a la izquierda.
  cada('[data-anim="carril"]', (el) => {
    const carril = el.querySelector<HTMLElement>('[data-carril]')
    if (!carril) return
    const recorrido = () => Math.max(0, carril.scrollWidth - el.clientWidth)
    gsap.to(carril, {
      x: () => -recorrido(), ease: 'none',
      scrollTrigger: { trigger: el, start: 'top top', end: () => `+=${recorrido()}`, scrub: 0.8, pin: true, anticipatePin: 1, invalidateOnRefresh: true },
    })
  })

  // Crece mientras entra (video del cierre).
  cada('[data-anim="crecer"]', (el) => {
    gsap.fromTo(el, { scale: 0.55 }, {
      scale: 1, ease: 'none',
      scrollTrigger: { trigger: el, start: 'top bottom', end: 'center center', scrub: true },
    })
  })
  return tareas
}

/**
 * Prende Lenis + efectos por atributo y, al terminar la intro, el hero.
 * Todo se limpia al salir de la página (Inertia cambia de propuesta sin recargar).
 */
/**
 * `despues(fn)`: para armar los efectos de scroll del hero cuando ya terminó su
 * entrada. Si se crean al mismo tiempo, guardan como punto de partida el estado
 * escondido de la entrada (opacidad 0) y el elemento se queda invisible. Lo que
 * se agrega así queda en el contexto y se limpia al salir.
 */
export type Despues = (fn: () => void) => void

// suave: false = scroll nativo, sin Lenis (responde al instante; la K lo usa porque con inercia se sentía lento).
export function useAnimaciones(raiz: Ref<HTMLElement | null>, hero?: (despues: Despues) => void, { suave = true }: { suave?: boolean } = {}) {
  let lenis: Lenis | null = null
  let ctx: gsap.Context | null = null
  const reloj = (t: number) => lenis?.raf(t * 1000)

  onMounted(async () => {
    const el = raiz.value
    if (!el) return
    if (sinMovimiento()) {
      el.classList.remove('preparando')
      return
    }

    // offset: lo que mide la barra fija de arriba (K), para que no tape el título.
    if (suave) lenis = new Lenis({ lerp: 0.13, wheelMultiplier: 1.15, anchors: { offset: -84 }, autoRaf: false })
    lenis?.on('scroll', ScrollTrigger.update)
    gsap.ticker.add(reloj)
    gsap.ticker.lagSmoothing(0)

    ctx = gsap.context(() => {}, el)

    await esperarIntro()
    // Los titulares entran con su letra: si entran con la de respaldo, al llegar la fuente
    // cambian de tamaño y la página brinca (CLS) y SplitText parte mal los renglones.
    // Se espera a las fuentes del hero, como mucho 0.8 s (el texto de venta ya se ve desde antes).
    await Promise.race([
      Promise.all([document.fonts?.load('700 1em "Chakra Petch"'), document.fonts?.load('600 1em Archivo')]).catch(() => {}),
      new Promise((r) => setTimeout(r, 800)),
    ])
    if (!ctx) return
    const despues: Despues = (fn) => {
      ctx?.add(fn)
      ScrollTrigger.sort()
      ScrollTrigger.refresh()
    }
    ctx.add(() => hero?.(despues))
    el.classList.remove('preparando')
    // El hero (con pin) se crea después que los efectos de abajo; sin ordenar,
    // esos disparadores no cuentan el espacio que agrega el pin y se disparan
    // donde no van. sort() los acomoda por posición antes de recalcular.
    ScrollTrigger.sort()
    ScrollTrigger.refresh()

    // Lo de abajo (fuera de la pantalla al cargar) se arma después, en pedazos.
    await libre()
    const vivo = () => ctx !== null
    await enPedazos(tareasDeclarativas(el), (t) => ctx?.add(t), vivo)
    if (!vivo()) return
    ScrollTrigger.sort()
    ScrollTrigger.refresh()
    // Las fuentes cambian alturas: recalcular otra vez cuando terminen de cargar.
    document.fonts?.ready.then(() => vivo() && ScrollTrigger.refresh())
  })

  onUnmounted(() => {
    ctx?.revert()
    ctx = null
    gsap.ticker.remove(reloj)
    lenis?.destroy()
  })
}

/**
 * Posición de un elemento respecto a un ancestro, sin contar transformaciones
 * (getBoundingClientRect sí las cuenta y falla a media animación).
 */
export function cajaRelativa(el: HTMLElement, ancestro: HTMLElement) {
  let x = 0
  let y = 0
  let n: HTMLElement | null = el
  while (n && n !== ancestro) {
    x += n.offsetLeft
    y += n.offsetTop
    n = n.offsetParent as HTMLElement | null
  }
  return { x, y, w: el.offsetWidth, h: el.offsetHeight }
}
