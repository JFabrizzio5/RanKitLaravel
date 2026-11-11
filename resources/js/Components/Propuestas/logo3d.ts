/**
 * Logo de Rankit en 3D (Three.js) y las tres figuras en las que se transforma:
 * control, balón y trofeo. Son modelos de verdad, no íconos extruidos:
 *
 *  - Logo: la R de la marca (mismos trazos que logoFormas.ts) con volumen.
 *  - Control: cuerpo acolchonado, sticks con anillo morado, cruceta, botones
 *    en domo, touchpad, gatillos y barra de luz.
 *  - Balón: esfera de 32 gajos inflados (icosaedro truncado), con costuras,
 *    pentágonos negros y el del frente morado.
 *  - Trofeo: copa torneada de metal pulido, asas, aros morados con luz,
 *    la R al frente y pedestal negro con placa morada.
 *
 * Cambio de figura: el objeto da una vuelta hacia atrás en diagonal; a media
 * vuelta la figura se abre en piezas y se encoge, y la siguiente se arma
 * desde sus piezas. Todo lo maneja `avance(t)` (t = 0..n, uno por figura).
 *
 * Acabado: laca (clearcoat) con reflejos de un estudio virtual
 * (RoomEnvironment). Se carga aparte (import dinámico): pesa ~150 KB gz.
 */
import * as THREE from 'three'
import { SVGLoader } from 'three/examples/jsm/loaders/SVGLoader.js'
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js'
import { gsap } from 'gsap'
import { FORMAS, type NombreForma } from './logoFormas'

export type Escena3D = {
  /** Muestra una sola figura, sin transformaciones. */
  forma: (nombre: NombreForma) => void
  /** Figuras en orden para avance(). */
  recorrido: (formas: NombreForma[]) => void
  /** Posición en el recorrido: 1.5 = a media transformación de la figura 1 a la 2. */
  avance: (t: number) => void
  /** true: además del vaivén, sigue al mouse. */
  vivo: (activo: boolean) => void
  /** Centro (en px del lienzo) y alto en px que deben ocupar 100 unidades. */
  encuadre: (cx: number, cy: number, alto: number) => void
  /** Entrada: las piezas del logo llegan desde el fondo y se arman. */
  armar: (retraso?: number) => void
  /** true: deja de dibujar (el lienzo no se ve aunque esté en pantalla). */
  pausar: (p: boolean) => void
  destruir: () => void
}

type Materiales = Record<'blanco' | 'negro' | 'morado' | 'metal' | 'costura', THREE.Material>

// ── Utilidades de geometría ────────────────────────────────────────────────
const V3 = (x: number, y: number, z: number) => new THREE.Vector3(x, y, z)

function rectRedondo(w: number, h: number, r: number) {
  const s = new THREE.Shape()
  const x = -w / 2, y = -h / 2
  s.moveTo(x + r, y)
  s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r)
  s.lineTo(x + w, y + h - r); s.quadraticCurveTo(x + w, y + h, x + w - r, y + h)
  s.lineTo(x + r, y + h); s.quadraticCurveTo(x, y + h, x, y + h - r)
  s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y)
  return s
}

/** Piezas del logo (SVG 100×100) extruidas, centradas en el origen y con y hacia arriba. */
function piezasLogo(profundo: number, biselT: number, biselS: number, escala = 1) {
  const cargador = new SVGLoader()
  return FORMAS.logo.map((pieza) => {
    const datos = cargador.parse(`<svg xmlns="http://www.w3.org/2000/svg"><path d="${pieza.d}"/></svg>`)
    const formas = datos.paths.flatMap((p) => SVGLoader.createShapes(p))
    const geo = new THREE.ExtrudeGeometry(formas, { depth: profundo, bevelEnabled: true, bevelThickness: biselT, bevelSize: biselS, bevelSegments: 8, curveSegments: 24 })
    geo.translate(-50, -50, -profundo / 2)
    geo.scale(escala, -escala, 1)
    // El volteo en y invierte las caras: regresar el orden de los triángulos.
    const pos = geo.getAttribute('position') as THREE.BufferAttribute
    const tmp = V3(0, 0, 0)
    for (let i = 0; i < pos.count; i += 3) {
      tmp.fromBufferAttribute(pos, i)
      pos.setXYZ(i, pos.getX(i + 2), pos.getY(i + 2), pos.getZ(i + 2))
      pos.setXYZ(i + 2, tmp.x, tmp.y, tmp.z)
    }
    geo.deleteAttribute('normal')
    geo.computeVertexNormals()
    return { geo, color: pieza.color }
  })
}

// ── Modelos ────────────────────────────────────────────────────────────────
function modeloLogo(m: Materiales) {
  const g = new THREE.Group()
  piezasLogo(18, 2.2, 1.2).forEach(({ geo, color }) => {
    g.add(new THREE.Mesh(geo, color === '#bf00ff' ? m.morado : m.blanco))
  })
  return g
}

function modeloControl(m: Materiales) {
  const g = new THREE.Group()
  const s = new THREE.Shape()
  s.moveTo(-26, 22); s.lineTo(26, 22)
  s.bezierCurveTo(40, 22, 48, 12, 48, -2)
  s.lineTo(48, -16)
  s.bezierCurveTo(48, -31, 37, -35, 30, -27)
  s.lineTo(22.5, -18.2); s.quadraticCurveTo(19.5, -14.8, 15, -14.8)
  s.lineTo(-15, -14.8); s.quadraticCurveTo(-19.5, -14.8, -22.5, -18.2)
  s.lineTo(-30, -27)
  s.bezierCurveTo(-37, -35, -48, -31, -48, -16)
  s.lineTo(-48, -2)
  s.bezierCurveTo(-48, 12, -40, 22, -26, 22)
  const cuerpo = new THREE.ExtrudeGeometry(s, { depth: 10, bevelEnabled: true, bevelThickness: 7, bevelSize: 4.5, bevelSegments: 12, curveSegments: 64 })
  cuerpo.translate(0, 0, -5) // cara frontal en z = 12
  g.add(new THREE.Mesh(cuerpo, m.blanco))
  const Z = 12

  // Gatillos (detrás, asoman arriba)
  const gatillo = new THREE.ExtrudeGeometry(rectRedondo(22, 8, 3.5), { depth: 12, bevelEnabled: true, bevelThickness: 1.5, bevelSize: 1.2, bevelSegments: 6, curveSegments: 12 })
  gatillo.translate(0, 0, -9)
  ;[-1, 1].forEach((lado) => {
    const t = new THREE.Mesh(gatillo, m.negro)
    t.position.set(lado * 31, 24.5, 0)
    t.rotation.z = lado * -0.12
    g.add(t)
  })

  // Barra de luz arriba
  const barra = new THREE.TubeGeometry(new THREE.CatmullRomCurve3([V3(-21, 18.6, Z + 0.4), V3(0, 19.6, Z + 0.4), V3(21, 18.6, Z + 0.4)]), 48, 0.9, 12)
  g.add(new THREE.Mesh(barra, m.morado))

  // Touchpad
  const pad = new THREE.ExtrudeGeometry(rectRedondo(28, 12, 3), { depth: 0.8, bevelEnabled: true, bevelThickness: 0.6, bevelSize: 0.6, bevelSegments: 4, curveSegments: 12 })
  const touchpad = new THREE.Mesh(pad, m.negro)
  touchpad.position.set(0, 7.5, Z)
  g.add(touchpad)

  // Cruceta: hueco y cruz
  const pozo = new THREE.CylinderGeometry(10.5, 10.5, 1, 48)
  pozo.rotateX(Math.PI / 2)
  const pozoIzq = new THREE.Mesh(pozo, m.negro)
  pozoIzq.position.set(-30, 3, Z + 0.2)
  g.add(pozoIzq)
  const cruz = new THREE.Shape()
  const a = 2.7, l = 8.2
  cruz.moveTo(-a, l); cruz.lineTo(a, l); cruz.lineTo(a, a); cruz.lineTo(l, a); cruz.lineTo(l, -a); cruz.lineTo(a, -a)
  cruz.lineTo(a, -l); cruz.lineTo(-a, -l); cruz.lineTo(-a, -a); cruz.lineTo(-l, -a); cruz.lineTo(-l, a); cruz.lineTo(-a, a); cruz.lineTo(-a, l)
  const cruceta = new THREE.Mesh(new THREE.ExtrudeGeometry(cruz, { depth: 2, bevelEnabled: true, bevelThickness: 0.7, bevelSize: 0.6, bevelSegments: 5 }), m.negro)
  cruceta.position.set(-30, 3, Z + 0.7)
  g.add(cruceta)

  // Botones en domo, morados con luz
  const pozoDer = new THREE.Mesh(pozo, m.negro)
  pozoDer.position.set(30, 3, Z + 0.2)
  g.add(pozoDer)
  const domo = new THREE.SphereGeometry(3.3, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2)
  domo.rotateX(Math.PI / 2)
  ;[[0, 6.4], [6.4, 0], [0, -6.4], [-6.4, 0]].forEach(([x, y]) => {
    const b = new THREE.Mesh(domo, m.morado)
    b.position.set(30 + x, 3 + y, Z + 0.6)
    g.add(b)
  })

  // Sticks: base, tallo, tapa cóncava y anillo morado
  const base = new THREE.CylinderGeometry(8.6, 8.6, 1, 48)
  base.rotateX(Math.PI / 2)
  const tallo = new THREE.CylinderGeometry(3.6, 4.2, 3.4, 24)
  tallo.rotateX(Math.PI / 2)
  const tapa = new THREE.LatheGeometry([[0, 3.2], [3.5, 3.6], [5.8, 4.3], [6.7, 3.6], [6.9, 2.2], [6.3, 0.5], [4.2, 0]].map(([x, y]) => new THREE.Vector2(x, y)), 48)
  tapa.rotateX(Math.PI / 2)
  const anillo = new THREE.TorusGeometry(6.75, 0.55, 12, 64)
  ;[-13, 13].forEach((x) => {
    const y = -5.5
    const b = new THREE.Mesh(base, m.negro); b.position.set(x, y, Z + 0.2); g.add(b)
    const t = new THREE.Mesh(tallo, m.negro); t.position.set(x, y, Z + 2); g.add(t)
    const c = new THREE.Mesh(tapa, m.negro); c.position.set(x, y, Z + 3.6); g.add(c)
    const r = new THREE.Mesh(anillo, m.morado); r.position.set(x, y, Z + 6); g.add(r)
  })

  // Botón central con la R
  piezasLogo(1.4, 0.4, 3, 0.075).forEach(({ geo, color }) => {
    const p = new THREE.Mesh(geo, color === '#bf00ff' ? m.morado : m.negro)
    p.position.set(0, -5.5, Z + 1.1)
    g.add(p)
  })

  g.position.y = 4 // centrar en alto (gatillos arriba, agarraderas abajo)
  return g
}

function modeloBalon(m: Materiales) {
  const g = new THREE.Group()
  const R = 46
  const phi = (1 + Math.sqrt(5)) / 2
  const crudos = [
    [-1, phi, 0], [1, phi, 0], [-1, -phi, 0], [1, -phi, 0],
    [0, -1, phi], [0, 1, phi], [0, -1, -phi], [0, 1, -phi],
    [phi, 0, -1], [phi, 0, 1], [-phi, 0, -1], [-phi, 0, 1],
  ].map(([x, y, z]) => V3(x, y, z).normalize())
  // Un pentágono justo al frente.
  const giro = new THREE.Quaternion().setFromUnitVectors(crudos[5], V3(0, 0, 1))
  const ico = crudos.map((v) => v.clone().applyQuaternion(giro))
  const arista = Math.min(...ico.slice(1).map((v) => v.distanceTo(ico[0])))
  const vecinos = ico.map((v) => ico.map((_, j) => j).filter((j) => Math.abs(v.distanceTo(ico[j]) - arista) < 1e-4))
  const tercio = (a: THREE.Vector3, b: THREE.Vector3) => a.clone().lerp(b, 1 / 3).normalize()

  // Gajo inflado sobre la esfera: rejilla polar del centro al borde.
  const gajo = (poligono: THREE.Vector3[]) => {
    const S = 7, E = 7, inflado = 2.2, hueco = 0.05
    const c = poligono.reduce((s, p) => s.add(p), V3(0, 0, 0)).normalize()
    const borde = poligono.map((p) => p.clone().lerp(c, hueco).normalize())
    const nrm = V3(0, 0, 0).subVectors(borde[0], c).cross(V3(0, 0, 0).subVectors(borde[1], c))
    if (nrm.dot(c) < 0) borde.reverse()
    const contorno: THREE.Vector3[] = []
    borde.forEach((p, i) => {
      const q = borde[(i + 1) % borde.length]
      for (let e = 0; e < E; e++) contorno.push(p.clone().lerp(q, e / E).normalize())
    })
    const M = contorno.length
    const radio = (k: number) => R + inflado * (1 - Math.pow(k / S, 3))
    const pos: number[] = []
    const centro = c.clone().multiplyScalar(radio(0))
    pos.push(centro.x, centro.y, centro.z)
    for (let k = 1; k <= S; k++) {
      for (let j = 0; j < M; j++) {
        const d = c.clone().lerp(contorno[j], k / S).normalize().multiplyScalar(radio(k))
        pos.push(d.x, d.y, d.z)
      }
    }
    const en = (k: number, j: number) => (k === 0 ? 0 : 1 + (k - 1) * M + (j % M))
    const idx: number[] = []
    for (let j = 0; j < M; j++) idx.push(0, en(1, j), en(1, j + 1))
    for (let k = 1; k < S; k++) {
      for (let j = 0; j < M; j++) idx.push(en(k, j), en(k + 1, j), en(k + 1, j + 1), en(k, j), en(k + 1, j + 1), en(k, j + 1))
    }
    const geo = new THREE.BufferGeometry()
    geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3))
    geo.setIndex(idx)
    geo.computeVertexNormals()
    return { geo, centro: c }
  }

  // 12 pentágonos (uno por vértice del icosaedro)
  ico.forEach((v, i) => {
    const ref = Math.abs(v.y) < 0.9 ? V3(0, 1, 0) : V3(1, 0, 0)
    const t1 = ref.cross(v).normalize()
    const t2 = v.clone().cross(t1)
    const angulo = (p: THREE.Vector3) => Math.atan2(p.clone().sub(v).dot(t2), p.clone().sub(v).dot(t1))
    const puntos = vecinos[i].map((j) => tercio(v, ico[j])).sort((p, q) => angulo(p) - angulo(q))
    const { geo, centro } = gajo(puntos)
    g.add(new THREE.Mesh(geo, centro.z > 0.99 ? m.morado : m.negro))
  })
  // 20 hexágonos (uno por cara)
  for (let a = 0; a < 12; a++) {
    for (const b of vecinos[a]) {
      if (b <= a) continue
      for (const c of vecinos[b]) {
        if (c <= b || !vecinos[a].includes(c)) continue
        const A = ico[a], B = ico[b], C = ico[c]
        g.add(new THREE.Mesh(gajo([tercio(A, B), tercio(B, A), tercio(B, C), tercio(C, B), tercio(C, A), tercio(A, C)]).geo, m.blanco))
      }
    }
  }
  // Esfera interior oscura: se ve en las costuras.
  g.add(new THREE.Mesh(new THREE.SphereGeometry(R - 0.5, 64, 48), m.costura))
  return g
}

function modeloTrofeo(m: Materiales) {
  const g = new THREE.Group()
  // Copa, tallo y pie en una sola pieza torneada.
  const perfil = [
    [0, -37.5], [11.5, -37.5], [12.2, -36.4], [11, -35.2], [6.5, -34], [5, -30], [4.3, -26], [7.4, -22.5], [7.4, -19.5], [4.6, -16.5],
    [5.4, -11], [10, -7], [17, -3], [23, 2], [27, 9], [29.4, 18], [30.4, 28], [31, 38], [32.8, 44], [33.6, 45.6], [32.6, 46.8],
    [29.8, 46], [28.6, 38], [28, 28], [27, 18], [24.8, 10], [20.8, 3], [14, -2.6], [6, -5], [0, -5.6],
  ].map(([x, y]) => new THREE.Vector2(x, y))
  const copa = new THREE.LatheGeometry(new THREE.SplineCurve(perfil).getPoints(260), 112)
  g.add(new THREE.Mesh(copa, m.metal))

  // Aros con luz: borde de la copa y nudo del tallo
  const aroBorde = new THREE.TorusGeometry(31.5, 0.9, 16, 112)
  aroBorde.rotateX(Math.PI / 2)
  const borde = new THREE.Mesh(aroBorde, m.morado)
  borde.position.y = 38
  g.add(borde)
  const aroNudo = new THREE.TorusGeometry(7.6, 1, 16, 64)
  aroNudo.rotateX(Math.PI / 2)
  const nudo = new THREE.Mesh(aroNudo, m.morado)
  nudo.position.y = -21
  g.add(nudo)

  // Asas
  const asa = new THREE.TubeGeometry(new THREE.CatmullRomCurve3([V3(29.2, 37, 0), V3(38, 38.5, 0), V3(44.5, 31, 0), V3(43, 19, 0), V3(34, 10.5, 0), V3(26.4, 8, 0)]), 80, 2.6, 18)
  ;[1, -1].forEach((lado) => {
    const a = new THREE.Mesh(asa, m.metal)
    a.scale.x = lado
    g.add(a)
  })

  // La R al frente, curvada sobre la copa (el radio de la copa crece con la altura)
  piezasLogo(2.2, 0.5, 1.5, 0.2).forEach(({ geo, color }) => {
    const pos = geo.getAttribute('position') as THREE.BufferAttribute
    for (let i = 0; i < pos.count; i++) {
      const x = pos.getX(i), y = pos.getY(i) + 22, z = pos.getZ(i) + 1.1
      const r = 28 + (y - 12) * 0.13 + 0.15
      const ang = x / r
      pos.setXYZ(i, (r + z) * Math.sin(ang), y, (r + z) * Math.cos(ang))
    }
    geo.computeVertexNormals()
    g.add(new THREE.Mesh(geo, color === '#bf00ff' ? m.morado : m.negro))
  })

  // Pedestal negro con placa morada
  const pedestal = new THREE.ExtrudeGeometry(rectRedondo(46, 46, 5), { depth: 8, bevelEnabled: true, bevelThickness: 1.4, bevelSize: 1.4, bevelSegments: 6, curveSegments: 16 })
  pedestal.rotateX(-Math.PI / 2)
  const ped = new THREE.Mesh(pedestal, m.negro)
  ped.position.y = -49
  g.add(ped)
  const placa = new THREE.ExtrudeGeometry(rectRedondo(34, 34, 4), { depth: 1.4, bevelEnabled: true, bevelThickness: 0.6, bevelSize: 0.6, bevelSegments: 4, curveSegments: 16 })
  placa.rotateX(-Math.PI / 2)
  const pl = new THREE.Mesh(placa, m.morado)
  pl.position.y = -39.6
  g.add(pl)

  g.position.y = 1.8
  return g
}

const MODELOS: Record<NombreForma, (m: Materiales) => THREE.Group> = {
  logo: modeloLogo,
  control: modeloControl,
  balon: modeloBalon,
  trofeo: modeloTrofeo,
}

// ── Escena ─────────────────────────────────────────────────────────────────
const suave = (x: number) => { const c = Math.min(1, Math.max(0, x)); return c * c * (3 - 2 * c) }
// Eje de la vuelta: hacia atrás (x) y en diagonal (algo de y y z).
const EJE = V3(1, 0.55, 0.15).normalize()
const ABRIR = 55 // cuánto se separan las piezas al transformarse

export function crearEscena(lienzo: HTMLCanvasElement, distancia = 230): Escena3D {
  const render = new THREE.WebGLRenderer({ canvas: lienzo, alpha: true, antialias: true, powerPreference: 'low-power' })
  // Resolución con tope: en escritorio el lienzo es grande y a 2x la tarjeta trabaja de más.
  render.setPixelRatio(Math.min(window.devicePixelRatio, window.innerWidth < 768 ? 2 : 1.5))
  render.outputColorSpace = THREE.SRGBColorSpace
  // Neutral respeta el color de marca (ACES lo lava hacia rosa).
  render.toneMapping = THREE.NeutralToneMapping
  render.toneMappingExposure = 1.05

  const escena = new THREE.Scene()
  const camara = new THREE.PerspectiveCamera(32, 1, 1, 3000)
  camara.position.set(0, 0, distancia)

  // Reflejos de estudio: es lo que hace que se vea "de producto" y no de plástico gris.
  const pmrem = new THREE.PMREMGenerator(render)
  const estudio = new RoomEnvironment()
  const reflejos = pmrem.fromScene(estudio, 0.04).texture
  estudio.traverse((o) => {
    const mm = o as THREE.Mesh
    mm.geometry?.dispose()
    ;(mm.material as THREE.Material | undefined)?.dispose?.()
  })
  pmrem.dispose()
  escena.environment = reflejos
  escena.environmentIntensity = 0.85

  // Clave blanca arriba-izquierda y contraluces morados que dibujan la silueta.
  const clave = new THREE.DirectionalLight(0xffffff, 1.6)
  clave.position.set(-90, 140, 180)
  escena.add(clave)
  const contra = new THREE.DirectionalLight(0xd25bff, 2.4)
  contra.position.set(140, 40, -160)
  escena.add(contra)
  const contra2 = new THREE.DirectionalLight(0xbf00ff, 1.6)
  contra2.position.set(-160, -80, -120)
  escena.add(contra2)

  const laca = (color: number, extra: THREE.MeshPhysicalMaterialParameters = {}) =>
    new THREE.MeshPhysicalMaterial({ color, metalness: 0, roughness: 0.24, clearcoat: 1, clearcoatRoughness: 0.05, ...extra })
  const materiales: Materiales = {
    blanco: laca(0xf4f4f8),
    negro: laca(0x0d0d12, { roughness: 0.32 }),
    // El morado es casi todo luz propia sobre una base oscura: si se ilumina
    // como el blanco, se pasa de brillo y el tone mapping lo lava a lila.
    morado: laca(0x4a0066, { emissive: 0xbf00ff, emissiveIntensity: 0.85, roughness: 0.35, envMapIntensity: 0.35, clearcoat: 0.5, clearcoatRoughness: 0.2 }),
    metal: new THREE.MeshPhysicalMaterial({ color: 0xeeeef4, metalness: 1, roughness: 0.13, clearcoat: 0.4, clearcoatRoughness: 0.08 }),
    costura: new THREE.MeshStandardMaterial({ color: 0x18181d, roughness: 0.7 }),
  }

  // Cada figura: pivote (escala de la transformación) → modelo → mallas.
  // Se arman hasta que se necesitan: si la página solo usa el logo, el resto no existe.
  const grupos = {} as Partial<Record<NombreForma, THREE.Group>>
  const mallas = {} as Partial<Record<NombreForma, THREE.Mesh[]>>
  const geometrias = new Set<THREE.BufferGeometry>()
  const asegurar = (nombre: NombreForma) => {
    if (grupos[nombre]) return grupos[nombre]!
    const n = (Object.keys(MODELOS) as NombreForma[]).indexOf(nombre)
    const pivote = new THREE.Group()
    pivote.add(MODELOS[nombre](materiales))
    pivote.updateMatrixWorld(true)
    mallas[nombre] = []
    let k = 0
    pivote.traverse((o) => {
      const mm = o as THREE.Mesh
      if (!mm.isMesh) return
      geometrias.add(mm.geometry)
      // Dirección en la que sale la pieza: del centro hacia afuera y un poco hacia atrás.
      const centro = new THREE.Box3().setFromObject(mm).getCenter(V3(0, 0, 0))
      const dir = centro.lengthSq() > 9 ? centro.clone().normalize() : V3(0, 0, 0)
      dir.z -= 0.55
      dir.normalize()
      const padre = mm.parent!
      const a = padre.worldToLocal(centro.clone())
      const b = padre.worldToLocal(centro.clone().add(dir))
      const azar = (s: number) => Math.sin((k + 1) * 12.9898 * s + n * 78.233) * 0.9
      mm.userData = {
        base: mm.position.clone(),
        rot: mm.rotation.clone(),
        dir: b.sub(a),
        giro: V3(azar(1), azar(2), azar(3)),
      }
      mallas[nombre]!.push(mm)
      k++
    })
    pivote.userData.e = -1
    pivote.visible = false
    grupos[nombre] = pivote
    raiz.add(pivote)
    return pivote
  }

  // marco: dónde y de qué tamaño · raiz: la vuelta de la transformación · meneo: mouse y vaivén
  const marco = new THREE.Group()
  const raiz = new THREE.Group()
  const meneo = new THREE.Group()
  meneo.add(raiz)
  marco.add(meneo)
  escena.add(marco)

  let mouseX = 0, mouseY = 0, activo = true, pausado = false, cambio = performance.now()
  const t0 = performance.now()
  let orden: NombreForma[] = ['logo']
  let tObjetivo = 0, tActual = 0
  const armado = { e: 0 }
  let encuadre: { cx: number; cy: number; alto: number } | null = null
  const alMover = (e: PointerEvent) => {
    mouseX = (e.clientX / window.innerWidth - 0.5) * 2
    mouseY = (e.clientY / window.innerHeight - 0.5) * 2
    if (activo) cambio = performance.now()
  }
  window.addEventListener('pointermove', alMover)

  /** escala 0 = oculta · abierto 0 = armada, 1 = piezas separadas */
  const poner = (nombre: NombreForma, escala: number, abierto: number) => {
    const p = asegurar(nombre)
    p.visible = escala > 0.001
    if (!p.visible) return
    p.scale.setScalar(escala)
    if (Math.abs(p.userData.e - abierto) < 1e-4) return
    p.userData.e = abierto
    for (const mm of mallas[nombre]!) {
      const u = mm.userData
      mm.position.copy(u.base).addScaledVector(u.dir, abierto * ABRIR)
      mm.rotation.set(u.rot.x + u.giro.x * abierto, u.rot.y + u.giro.y * abierto, u.rot.z + u.giro.z * abierto)
    }
  }

  const aplicarEncuadre = () => {
    const w = lienzo.clientWidth, h = lienzo.clientHeight
    if (!encuadre || !w || !h) { marco.position.set(0, 0, 0); marco.scale.setScalar(1); return }
    const altoMundo = 2 * distancia * Math.tan(THREE.MathUtils.degToRad(camara.fov / 2))
    const px = h / altoMundo // píxeles por unidad a la profundidad del logo
    marco.scale.setScalar(encuadre.alto / (100 * px))
    marco.position.set((encuadre.cx - w / 2) / px, -(encuadre.cy - h / 2) / px, 0)
  }

  const medir = () => {
    const w = lienzo.clientWidth, h = lienzo.clientHeight
    if (!w || !h) return
    render.setSize(w, h, false)
    camara.aspect = w / h
    camara.updateProjectionMatrix()
    aplicarEncuadre()
  }
  const obsTam = new ResizeObserver(medir)
  obsTam.observe(lienzo)
  medir()

  // Solo dibuja cuando se ve: ahorra batería.
  let visible = true
  const obsVis = new IntersectionObserver(([e]) => { visible = e.isIntersecting })
  obsVis.observe(lienzo)

  let cuadro = 0
  const dibujar = (ahora: number) => {
    cuadro = requestAnimationFrame(dibujar)
    if (!visible || pausado || document.hidden) return
    // A la frecuencia de la pantalla: a 30 cuadros el vaivén se veía a saltos en monitores de
    // 60/120 Hz. Es un solo objeto; lo que ahorra de verdad es pausarlo cuando ya no se ve.
    const t = (ahora - t0) / 1000

    // Vaivén (siempre) + mouse (si está vivo). Inclinado hacia el frente para que se vea el volumen.
    const objY = Math.sin(t * 0.6) * 0.24 + (activo ? mouseX * 0.4 : 0)
    const objX = 0.14 + Math.cos(t * 0.5) * 0.08 + (activo ? mouseY * 0.22 : 0)
    meneo.rotation.y += (objY - meneo.rotation.y) * 0.07
    meneo.rotation.x += (objX - meneo.rotation.x) * 0.07
    meneo.position.y = Math.sin(t * 1.1) * 2.5

    // Transformación según el avance (el scroll ya viene suavizado por GSAP).
    tActual += (tObjetivo - tActual) * 0.25
    const n = orden.length - 1
    const tt = Math.min(n, Math.max(0, tActual))
    const i = n > 0 ? Math.min(n - 1, Math.floor(tt)) : 0
    const de = orden[i]
    // Si el tramo repite figura (dos escenas con el control), no se transforma.
    const a = n > 0 && orden[i + 1] !== de ? orden[i + 1] : null
    const v = a ? suave((tt - i - 0.08) / 0.84) : 0
    const salida = suave((v - 0.3) / 0.2) // 0.30 → 0.50: se abre y se encoge
    const entrada = suave((v - 0.5) / 0.22) // 0.50 → 0.72: se arma la siguiente
    ;(Object.keys(grupos) as NombreForma[]).forEach((k) => { if (k !== de && k !== a) grupos[k]!.visible = false })
    poner(de, salida >= 1 ? 0 : 1 - 0.65 * salida, Math.max(salida, de === 'logo' ? armado.e : 0))
    if (a) poner(a, entrada <= 0 ? 0 : 0.35 + 0.65 * entrada, 1 - entrada)
    raiz.quaternion.setFromAxisAngle(EJE, -v * Math.PI * 2)

    render.render(escena, camara)
  }
  cuadro = requestAnimationFrame(dibujar)

  return {
    forma(nombre) { orden = [nombre]; tObjetivo = tActual = 0 },
    recorrido(formas) { orden = formas.length ? formas : ['logo'] },
    avance(t) { if (t !== tObjetivo) cambio = performance.now(); tObjetivo = t },
    vivo(x) { activo = x },
    encuadre(cx, cy, alto) { encuadre = { cx, cy, alto }; aplicarEncuadre(); cambio = performance.now() },
    pausar(p) { pausado = p; if (!p) cambio = performance.now() },
    armar(retraso = 0) {
      gsap.killTweensOf(armado)
      gsap.fromTo(armado, { e: 1 }, { e: 0, duration: 1.7, ease: 'expo.out', delay: retraso })
      // Llega de espaldas y el suavizado del cuadro lo pone de frente.
      meneo.rotation.y = -Math.PI * 0.85
    },
    destruir() {
      cancelAnimationFrame(cuadro)
      gsap.killTweensOf(armado)
      window.removeEventListener('pointermove', alMover)
      obsTam.disconnect()
      obsVis.disconnect()
      geometrias.forEach((g) => g.dispose())
      Object.values(materiales).forEach((mm) => mm.dispose())
      reflejos.dispose()
      render.dispose()
    },
  }
}

export function hayWebGL() {
  try {
    const c = document.createElement('canvas')
    return !!(c.getContext('webgl2') || c.getContext('webgl'))
  } catch {
    return false
  }
}
