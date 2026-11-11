/**
 * Configuración del widget de OBS. Todo viaja en el link (cada fuente de OBS
 * lleva su propio estilo), así que no se guarda nada en el servidor.
 *
 * Parámetros de siempre (los links que ya están en streams siguen igual):
 *   type=players|teams · sort=points|kills · mode · limit · search
 * Nuevos:
 *   tema=oscuro|cristal|claro|transparente · acento=bf00ff · titulo=…
 *   logos=1|0 · compacto=1|0
 */
export type Tema = 'oscuro' | 'cristal' | 'claro' | 'transparente'

export type ConfigWidget = {
  tipo: 'players' | 'teams'
  orden: 'points' | 'kills'
  modo: string
  filas: number
  buscar: string
  tema: Tema
  acento: string // sin "#"
  titulo: string
  logos: boolean
  compacto: boolean
}

export const ACENTO_RANKIT = 'bf00ff'

export const configBase = (): ConfigWidget => ({
  tipo: 'players',
  orden: 'points',
  modo: 'all',
  filas: 10,
  buscar: '',
  tema: 'oscuro',
  acento: ACENTO_RANKIT,
  titulo: '',
  logos: true,
  compacto: false,
})

const TEMAS: Tema[] = ['oscuro', 'cristal', 'claro', 'transparente']
const limpiarHex = (v: string | null) => (v && /^[0-9a-f]{6}$/i.test(v.replace('#', '')) ? v.replace('#', '').toLowerCase() : ACENTO_RANKIT)

export function leerConfig(q: URLSearchParams, buscarPorDefecto = ''): ConfigWidget {
  const c = configBase()
  c.tipo = q.get('type') === 'teams' ? 'teams' : 'players'
  c.orden = q.get('sort') === 'kills' ? 'kills' : 'points'
  c.modo = q.get('mode') || 'all'
  c.filas = Math.min(20, Math.max(1, parseInt(q.get('limit') || '10', 10) || 10))
  c.buscar = q.get('search') || buscarPorDefecto
  const tema = q.get('tema') as Tema | null
  c.tema = tema && TEMAS.includes(tema) ? tema : 'oscuro'
  c.acento = limpiarHex(q.get('acento'))
  c.titulo = (q.get('titulo') || '').slice(0, 40)
  c.logos = q.get('logos') !== '0'
  c.compacto = q.get('compacto') === '1'
  return c
}

/** Link para OBS con solo lo que cambia respecto a lo de fábrica (más corto y legible). */
export function armarQuery(c: ConfigWidget): string {
  const q = new URLSearchParams()
  q.set('type', c.tipo)
  q.set('sort', c.orden)
  if (c.modo !== 'all') q.set('mode', c.modo)
  q.set('limit', String(c.buscar ? 1 : c.filas))
  if (c.buscar) q.set('search', c.buscar)
  if (c.tema !== 'oscuro') q.set('tema', c.tema)
  if (c.acento !== ACENTO_RANKIT) q.set('acento', c.acento)
  if (c.titulo) q.set('titulo', c.titulo)
  if (!c.logos) q.set('logos', '0')
  if (c.compacto) q.set('compacto', '1')
  return q.toString()
}

/** Tamaño sugerido para la fuente de navegador en OBS. */
export function tamanoSugerido(c: ConfigWidget) {
  const ancho = c.compacto ? 360 : 440
  const fila = c.compacto ? 44 : 54
  const alto = c.buscar ? 260 : 150 + (c.logos ? 40 : 10) + fila * c.filas
  return { ancho, alto }
}

export type FilaWidget = {
  rank: number
  player_name?: string
  member_names?: string[]
  total_points: number
  total_kills: number
  games_played: number
}
