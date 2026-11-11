/**
 * Recorrido por escenas con el scroll (como el del pádel, pero en 2D y sin
 * video): la cámara se acerca a un punto de la imagen y por ahí se abre la
 * siguiente escena.
 *
 * Se dibuja en vivo en un canvas (FondoCuadros.vue). En la transición i → i+1:
 *  - la foto i se acerca al foco (crecimiento exponencial, con tope);
 *  - la foto i+1 se ve por un "portal" (rectángulo o círculo) que nace del
 *    foco y crece hasta cubrir la pantalla; su imagen crece con él.
 * Son dos drawImage y un recorte por cuadro, con las fotos a resolución
 * completa: el movimiento es continuo (antes eran 16 cuadros prerenderizados
 * por transición y el relleno entre cuadros daba tirones).
 */

export type Portal =
  | { tipo: 'rect'; x: number; y: number; w: number; h: number } // en 0..1 de la imagen
  | { tipo: 'circulo'; x: number; y: number; r: number } // r en fracción del ancho de la imagen

export type Escena = { grande: string; movil: string; portal?: { grande: Portal; movil: Portal } }

const TAMANO = { grande: [1920, 1086], movil: [720, 1280] } as const
export const CONSULTA_MOVIL = '(max-width: 767px)'

const limitar = (x: number) => Math.min(1, Math.max(0, x))
const suave = (x: number) => { const c = limitar(x); return c * c * (3 - 2 * c) }
const lerp = (a: number, b: number, t: number) => a + (b - a) * t

/** Portal en px de pantalla para una imagen con object-cover en W×H. */
export function medir(portal: Portal, W: number, H: number, movil: boolean) {
  const [iw, ih] = TAMANO[movil ? 'movil' : 'grande']
  const s = Math.max(W / iw, H / ih)
  const ox = (W - iw * s) / 2, oy = (H - ih * s) / 2
  const fx = ox + portal.x * iw * s, fy = oy + portal.y * ih * s
  const lejosX = Math.max(fx, W - fx), lejosY = Math.max(fy, H - fy)
  if (portal.tipo === 'rect') {
    const w0 = portal.w * iw * s, h0 = portal.h * ih * s
    return { tipo: 'rect' as const, fx, fy, w0, h0, r0: 0, Z: Math.max((2 * lejosX) / w0, (2 * lejosY) / h0) * 1.02, k0: Math.max(w0 / W, h0 / H) }
  }
  const r0 = portal.r * iw * s
  return { tipo: 'circulo' as const, fx, fy, w0: 0, h0: 0, r0, Z: Math.hypot(lejosX, lejosY) / r0 * 1.02, k0: (2 * r0) / Math.min(W, H) }
}

/**
 * Tramo y avance del recorrido en `t`: i = transición de la escena i a la i+1,
 * p = 0..1 dentro de ella (con una pausa corta al principio y al final de cada
 * tramo para que se alcance a leer cada escena). activo = escena que domina.
 */
export function fase(t: number, n: number) {
  const tt = Math.min(n, Math.max(0, t))
  const i = Math.min(n - 1, Math.floor(tt))
  return { i, p: suave((tt - i - 0.06) / 0.88), activo: Math.min(n, Math.floor(tt + 0.45)) }
}

/**
 * Pinta en `ctx` la transición de la foto `a` a la `b` por su portal, en el avance p (0..1).
 * Todo en píxeles del canvas. En p = 0 queda igual que `a` sola y en p = 1 igual que `b`
 * sola (sin saltos al entrar o salir de la transición).
 */
export function pintarTramo(ctx: CanvasRenderingContext2D, a: HTMLImageElement, b: HTMLImageElement | null, portal: Portal, p: number, movil: boolean) {
  const W = ctx.canvas.width, H = ctx.canvas.height
  const m = medir(portal, W, H, movil)
  // Crecimiento exponencial: se siente como avanzar a velocidad constante.
  const G = Math.pow(m.Z, p)
  // Con tope: más allá se pixela, y para entonces el portal ya tapa casi todo.
  dibujarCubriendo(ctx, a, Math.min(G, 3.2), m.fx, m.fy)
  if (!b) return

  // El portal nace de cero: en p = 0 se ve la foto sola, igual que en reposo.
  const abre = suave(p / 0.12)
  ctx.save()
  ctx.beginPath()
  if (m.tipo === 'rect') {
    const w = m.w0 * G * abre, h = m.h0 * G * abre
    const radio = lerp(6, 0, p) * (W / ctx.canvas.clientWidth || 1)
    ctx.roundRect(m.fx - w / 2, m.fy - h / 2, w, h, Math.min(radio, w / 2, h / 2))
  } else {
    ctx.arc(m.fx, m.fy, m.r0 * G * abre, 0, Math.PI * 2)
  }
  ctx.clip()
  // La foto siguiente llena el portal y termina a pantalla completa, centrada.
  const k = Math.min(1, m.k0 * G * 1.12)
  const q = suave((k - m.k0) / (1 - m.k0))
  const cx = lerp(m.fx, W / 2, q), cy = lerp(m.fy, H / 2, q)
  const s = Math.max(W / b.naturalWidth, H / b.naturalHeight)
  const bw = b.naturalWidth * s * k, bh = b.naturalHeight * s * k
  ctx.drawImage(b, cx - bw / 2, cy - bh / 2, bw, bh)
  ctx.restore()
}

/** Dibuja la foto cubriendo el canvas (object-cover), acercada g veces alrededor del punto (px, py). */
export function dibujarCubriendo(ctx: CanvasRenderingContext2D, im: HTMLImageElement, g = 1, px = 0, py = 0) {
  const W = ctx.canvas.width, H = ctx.canvas.height
  const s = Math.max(W / im.naturalWidth, H / im.naturalHeight)
  const w = im.naturalWidth * s, h = im.naturalHeight * s
  const ox = (W - w) / 2, oy = (H - h) / 2
  if (g === 1) ctx.drawImage(im, ox, oy, w, h)
  else ctx.drawImage(im, px + (ox - px) * g, py + (oy - py) * g, w * g, h * g)
}
