/**
 * Las figuras en las que se convierte el logo de Rankit. El logo son tres
 * piezas (dos blancas y una morada); cada figura también tiene tres, así
 * cada pieza del logo se transforma en una parte de la figura y los colores
 * de la marca se conservan. Todas en un lienzo de 100×100.
 */
import { gsap } from './animaciones'
import { MorphSVGPlugin } from 'gsap/MorphSVGPlugin'

gsap.registerPlugin(MorphSVGPlugin)

export type NombreForma = 'logo' | 'control' | 'balon' | 'trofeo'

type Pieza = { d: string; color: string }

const BLANCO = '#ffffff'
const NEGRO = '#050505'
const MORADO = '#bf00ff'

export const FORMAS: Record<NombreForma, [Pieza, Pieza, Pieza]> = {
  // La R de Rankit tal cual (mismo trazo que el logo de la barra).
  logo: [
    { d: 'M15 10 L40 10 L30 90 L5 90 Z', color: BLANCO },
    { d: 'M45 10 L95 10 L75 50 L45 50 Z', color: BLANCO },
    { d: 'M50 55 L80 55 L95 90 L65 90 Z', color: MORADO },
  ],
  // Control: cuerpo blanco, cruceta negra, botones morados.
  control: [
    { d: 'M24 28 L76 28 C88 28 96 38 96 53 L96 66 C96 77 88 81 81 76 L69 66 L31 66 L19 76 C12 81 4 77 4 66 L4 53 C4 38 12 28 24 28 Z', color: BLANCO },
    { d: 'M22 39 L29 39 L29 45 L35 45 L35 52 L29 52 L29 58 L22 58 L22 52 L16 52 L16 45 L22 45 Z', color: NEGRO },
    { d: 'M72 38 C75 38 77 40 77 43 C77 46 75 48 72 48 C69 48 67 46 67 43 C67 40 69 38 72 38 Z M81 47 C84 47 86 49 86 52 C86 55 84 57 81 57 C78 57 76 55 76 52 C76 49 78 47 81 47 Z', color: MORADO },
  ],
  // Balón: círculo blanco, parches negros alrededor y pentágono morado al centro.
  balon: [
    { d: 'M50 6 C74 6 94 26 94 50 C94 74 74 94 50 94 C26 94 6 74 6 50 C6 26 26 6 50 6 Z', color: BLANCO },
    { d: 'M41 7 L59 7 L56 17 L44 17 Z M86 30 L93 44 L86 52 L78 44 Z M14 30 L22 44 L14 52 L7 44 Z M28 82 L38 74 L46 84 L38 92 Z M72 82 L62 74 L54 84 L62 92 Z', color: NEGRO },
    { d: 'M50 33 L66 45 L60 64 L40 64 L34 45 Z', color: MORADO },
  ],
  // Trofeo: copa con asas, tallo y base morada.
  trofeo: [
    { d: 'M24 10 L76 10 L76 14 L84 14 C90 14 93 18 92 24 C91 31 85 37 74 41 L75 35 C81 32 86 28 86 24 C86 21 85 20 82 20 L76 20 C76 42 65 60 50 60 C35 60 24 42 24 20 L18 20 C15 20 14 21 14 24 C14 28 19 32 25 35 L26 41 C15 37 9 31 8 24 C7 18 10 14 16 14 L24 14 Z', color: BLANCO },
    { d: 'M45 60 L55 60 L58 76 L42 76 Z', color: BLANCO },
    { d: 'M28 78 L72 78 L77 92 L23 92 Z', color: MORADO },
  ],
}

/** Agrega a una línea de tiempo el cambio de las tres piezas hacia `forma`. */
export function morfar(tl: gsap.core.Timeline, piezas: SVGPathElement[], forma: NombreForma, posicion?: gsap.Position, duracion = 0.8) {
  FORMAS[forma].forEach((p, i) => {
    tl.to(piezas[i], { morphSVG: { shape: p.d, shapeIndex: 'auto' }, fill: p.color, duration: duracion, ease: 'expo.inOut' }, i === 0 ? posicion : '<')
  })
  return tl
}

/** Palabra que acompaña a cada figura (propuestas D y E). */
export const PALABRA: Record<NombreForma, string> = {
  logo: 'Rankit',
  control: 'Juega',
  balon: 'Anota',
  trofeo: 'Gana',
}
