/**
 * Material propio de Rankit para las propuestas de landing: tomas del
 * comercial (Rankit-Promo, ya en 720p sin audio) y capturas reales de la app.
 * Pasan por Vite, así que salen con hash en build/ y funcionan igual en local
 * y en el hosting (donde public/ no es la raíz).
 */
import arenaMp4 from '../../../media/landing/arena.mp4'
import arenaImg from '../../../media/landing/arena.webp'
import finalMp4 from '../../../media/landing/final.mp4'
import finalImg from '../../../media/landing/final.webp'
import miradaMp4 from '../../../media/landing/mirada.mp4'
import miradaImg from '../../../media/landing/mirada.webp'
import streamerMp4 from '../../../media/landing/streamer.mp4'
import streamerImg from '../../../media/landing/streamer.webp'
import futbolMp4 from '../../../media/landing/futbol7.mp4'
import futbolImg from '../../../media/landing/futbol7.webp'
import tablaImg from '../../../media/landing/tabla-en-vivo.webp'
import widgetImg from '../../../media/landing/widget-obs.webp'
import bellzImg from '../../../media/landing/bellzcup.webp'
// Escenas del recorrido: fotos REALES de Unsplash (licencia gratuita, uso comercial)
// retocadas con GPT Image 2.5 sobre la misma foto (2026-11-11): color morado, sin
// logos, monitor con un juego original y trofeo genérico. IDs en LandingK.vue.
// *-movil = recorte vertical 720×1280.
import fondoArena from '../../../media/landing/fondo-arena.webp'
import fondoArenaMovil from '../../../media/landing/fondo-arena-movil.webp'
import fondoGamer from '../../../media/landing/fondo-gamer.webp'
import fondoGamerMovil from '../../../media/landing/fondo-gamer-movil.webp'
import fondoGamer960 from '../../../media/landing/fondo-gamer-960.webp'
import fondoFutbol from '../../../media/landing/fondo-futbol.webp'
import fondoFutbolMovil from '../../../media/landing/fondo-futbol-movil.webp'
import fondoFinal from '../../../media/landing/fondo-final.webp'
// Objetos reales: fotos de Unsplash recortadas con Vision de macOS (los píxeles son los de la foto).
import objetoControl from '../../../media/landing/objeto-control.webp'
import objetoBalon from '../../../media/landing/objeto-balon.webp'
import objetoTrofeo from '../../../media/landing/objeto-trofeo.webp'
import fondoFinalMovil from '../../../media/landing/fondo-final-movil.webp'

export const medios = {
  arena: { video: arenaMp4, poster: arenaImg },
  final: { video: finalMp4, poster: finalImg },
  mirada: { video: miradaMp4, poster: miradaImg },
  streamer: { video: streamerMp4, poster: streamerImg },
  futbol: { video: futbolMp4, poster: futbolImg },
  tabla: tablaImg,
  widget: widgetImg,
  bellz: bellzImg,
}

/** Escritorio 1920×1086 y celular 720×1280. */
export const fondos = {
  arena: { grande: fondoArena, movil: fondoArenaMovil },
  gamer: { grande: fondoGamer, movil: fondoGamerMovil, medio: fondoGamer960 },
  futbol: { grande: fondoFutbol, movil: fondoFutbolMovil },
  final: { grande: fondoFinal, movil: fondoFinalMovil },
}

/**
 * Cifras reales de producción (consulta de solo lectura, 10-nov-2026).
 * Si se elige una propuesta, conviene calcularlas en la ruta en vez de fijarlas.
 */
export const cifras = {
  torneos: 60,
  jugadores: 1002,
  partidas: 122,
  eliminaciones: 5273,
  cuentas: 271,
}

export const contacto = {
  whatsapp: 'https://wa.me/525532351392?text=' + encodeURIComponent('Hola Rankit, quiero organizar un torneo.'),
  instagram: 'https://instagram.com/rankit.pro',
}

/** Lo que manda la ruta (App\Services\Landing\EventosAbiertos). */
export type Eventos = {
  semanal: {
    nombre: string
    fecha: string | null
    fecha_label: string | null
    inscritos: number
    cupo: number
    premio_base: number
    premio_lleno: number
    abierto: boolean
    url: string
  }
  liga: { fase: string | null; abierta: boolean; url: string }
}

/** Objetos con fondo transparente para poner enfrente de las escenas. */
export const objetos = { control: objetoControl, balon: objetoBalon, trofeo: objetoTrofeo }
