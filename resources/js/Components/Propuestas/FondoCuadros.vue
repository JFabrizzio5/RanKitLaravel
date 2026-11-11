<script setup lang="ts">
/**
 * Fondo del recorrido en UN solo canvas (como las páginas de producto de Apple
 * o el recorrido del pádel).
 *
 * - Escena quieta: su foto, cubriendo la pantalla.
 * - Transición: se dibuja en vivo con las dos fotos (recorridoEscenas.pintarTramo):
 *   la actual se acerca al portal y la siguiente crece dentro de él. Antes eran
 *   16 cuadros prerenderizados por transición (2.7 MB) y el relleno entre uno y
 *   otro hacía que la imagen "pulsara": ahora el movimiento es continuo y a
 *   resolución completa.
 * - Al cargar solo baja la primera foto, cuando ya se pintó el texto (aparece con
 *   un fundido). Las demás esperan a activar() (al empezar a moverse o un rato
 *   después de cargar): así no le quitan red a lo primero que se ve.
 *
 * Se dibuja en el mismo cuadro en que cambia el scroll (no al siguiente), para que
 * el fondo vaya junto con el texto y el objeto 3D.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { libre } from './animaciones'
import { CONSULTA_MOVIL, dibujarCubriendo, fase, pintarTramo, type Escena } from './recorridoEscenas'

const props = defineProps<{ estaticas: Escena[] }>()

const lienzo = ref<HTMLCanvasElement | null>(null)
const visible = ref(false)
const n = props.estaticas.length - 1
let movil = false
// <img> ya decodificadas: el navegador administra esa memoria y las sube a la tarjeta una vez.
let fotos: (HTMLImageElement | null)[] = []
const pedidos = new Set<string>()
let t = 0
let activo = false
let ultimo = ''
let ctx: CanvasRenderingContext2D | null = null
let obs: ResizeObserver | undefined
let consulta: MediaQueryList | null = null

async function bajar(src: string): Promise<HTMLImageElement | null> {
  const im = new Image()
  im.decoding = 'async'
  im.src = src
  try {
    await im.decode()
    return im
  } catch {
    return null
  }
}

function cargarFoto(k: number) {
  const src = movil ? props.estaticas[k].movil : props.estaticas[k].grande
  if (pedidos.has(src)) return
  pedidos.add(src)
  bajar(src).then((im) => {
    // Si mientras bajaba cambió de celular a escritorio, esta ya no sirve.
    if ((movil ? props.estaticas[k].movil : props.estaticas[k].grande) !== src) return
    fotos[k] = im
    // Subirla ya a la tarjeta (un dibujo de 1 px que el siguiente tapa): si no, la primera vez
    // que sale en una transición se sube en ese cuadro y se nota un tirón.
    if (im && ctx) ctx.drawImage(im, 0, 0, 1, 1)
    dibujar(true)
  })
}

function dibujar(forzar = false) {
  const c = lienzo.value
  if (!c || !c.width || !ctx) return
  const { i, p } = fase(t, n)
  const clave = `${i}:${p.toFixed(4)}`
  if (!forzar && clave === ultimo) return
  ultimo = clave

  const portal = props.estaticas[i].portal
  const a = fotos[i], b = fotos[i + 1]
  if (p <= 0.0005 || p >= 0.9995 || !portal || !a) {
    const im = p >= 0.9995 ? (b ?? a) : (a ?? b)
    if (!im) return
    dibujarCubriendo(ctx, im)
  } else {
    // Sin la siguiente foto todavía (red lenta): la actual se sigue acercando y el portal espera.
    pintarTramo(ctx, a, b, movil ? portal.movil : portal.grande, p, movil)
  }
  visible.value = true
}

function medir() {
  const c = lienzo.value
  if (!c) return
  // 1.5x basta: las fotos miden 1920 de ancho (720 en celular).
  const dpr = Math.min(window.devicePixelRatio || 1, 1.5)
  c.width = Math.round(c.clientWidth * dpr)
  c.height = Math.round(c.clientHeight * dpr)
  ctx = c.getContext('2d', { alpha: false })
  if (ctx) {
    // Al alejar mucho la foto que va dentro del portal, el filtro simple deja brillitos.
    ctx.imageSmoothingEnabled = true
    ctx.imageSmoothingQuality = 'medium'
  }
  dibujar(true)
}

function cargarResto() {
  props.estaticas.forEach((_, k) => cargarFoto(k))
}

function preparar() {
  movil = window.matchMedia(CONSULTA_MOVIL).matches
  fotos = props.estaticas.map(() => null)
  pedidos.clear()
  // La primera foto, cuando el navegador queda libre tras el primer pintado (máx. 0.6 s).
  libre(600).then(() => {
    cargarFoto(0)
    if (activo) cargarResto()
  })
}

onMounted(() => {
  preparar()
  obs = new ResizeObserver(medir)
  if (lienzo.value) obs.observe(lienzo.value)
  consulta = window.matchMedia(CONSULTA_MOVIL)
  consulta.addEventListener('change', preparar)
})
onUnmounted(() => {
  obs?.disconnect()
  consulta?.removeEventListener('change', preparar)
})

defineExpose({
  /** t = posición del recorrido (0..n). */
  pintar(nuevo: number) {
    t = nuevo
    dibujar()
  },
  /** Ya se pueden bajar las fotos de las otras escenas. */
  activar() {
    if (activo) return
    activo = true
    cargarResto()
  },
})
</script>

<template>
  <canvas ref="lienzo" class="block h-full w-full bg-[#050505] transition-opacity duration-700" :class="visible ? 'opacity-100' : 'opacity-0'" aria-hidden="true"></canvas>
</template>
