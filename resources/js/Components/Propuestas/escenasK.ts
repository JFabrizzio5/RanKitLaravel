/**
 * Escenas del recorrido de la propuesta K: fotos reales de Unsplash retocadas
 * (ver medios.ts). El portal es por dónde se entra a la siguiente escena.
 * Fotos: arena photo-1770067665792, gamer photo-1534423861386,
 * fútbol photo-1604651684549, trofeo photo-1720299580598.
 *
 * FondoCuadros.vue lo dibuja en vivo en un canvas (recorridoEscenas.pintarTramo).
 */
import { fondos } from './medios'
import type { Escena } from './recorridoEscenas'

export const escenasK: Escena[] = [
  // Arena: se entra por el marcador que cuelga del techo (ya muestra la siguiente escena).
  { ...fondos.arena, portal: { grande: { tipo: 'rect', x: 0.497, y: 0.336, w: 0.065, h: 0.074 }, movil: { tipo: 'rect', x: 0.491, y: 0.336, w: 0.204, h: 0.074 } } },
  // Gamer: se entra por el paracaidista del juego en el monitor (recorte de celular cargado a la izquierda: x 134).
  { ...fondos.gamer, portal: { grande: { tipo: 'circulo', x: 0.295, y: 0.53, r: 0.03 }, movil: { tipo: 'circulo', x: 0.77, y: 0.53, r: 0.094 } } },
  // Fútbol: se entra por el balón (recorte de celular desde x 672).
  { ...fondos.futbol, portal: { grande: { tipo: 'circulo', x: 0.335, y: 0.713, r: 0.03 }, movil: { tipo: 'circulo', x: 0.267, y: 0.713, r: 0.094 } } },
  { ...fondos.final },
]
