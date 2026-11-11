/**
 * Objetos 3D del recorrido (control, balón y trofeo), diseñados aquí y
 * dibujados con Three.js en un canvas chico (el hueco del objeto, no toda la
 * pantalla). Reemplazan a los giros por cuadros: pesan casi nada (las
 * texturas se generan en código), giran suave a cualquier velocidad y solo
 * se dibujan mientras se ven.
 *
 * Acabado:
 * - Estudio de foto de producto horneado con PMREM (caja de luz, tiras blancas
 *   y paneles morados): el cromo y las lacas reflejan la luz de la marca.
 * - Texturas procedurales: metal cepillado (rugosidad), cuero granulado del
 *   balón, puntos de agarre del control y estrías de goma de los sticks.
 * - Brillos: piezas emisivas + un halo aditivo (bloom barato, sin postproceso).
 *
 * Diseños originales: el control no copia ninguna consola ni lleva sus
 * símbolos; el trofeo no es el de ningún torneo real.
 */
import * as THREE from 'three'
import { ceder, libre } from './animaciones'

export type NombreObjeto = 'control' | 'balon' | 'trofeo'
export type Objetos3D = {
  /** Qué objeto se ve (null = ninguno). Se arma la primera vez que se pide. */
  ver: (n: NombreObjeto | null) => void
  /** Vueltas que pone el scroll (se suman al giro en reposo). */
  girar: (vueltas: number) => void
  /** true: dibuja y gira solo; false: deja de dibujar. */
  vivo: (si: boolean) => void
  destruir: () => void
}

const V3 = (x: number, y: number, z: number) => new THREE.Vector3(x, y, z)
const MORADO = new THREE.Color(0xbf00ff)

// ── Texturas procedurales ──────────────────────────────────────────────────
function datos(w: number, h: number, pixel: (x: number, y: number) => [number, number, number]) {
  const d = new Uint8Array(w * h * 4)
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const [r, g, b] = pixel(x, y)
      const i = (y * w + x) * 4
      d[i] = r; d[i + 1] = g; d[i + 2] = b; d[i + 3] = 255
    }
  }
  const t = new THREE.DataTexture(d, w, h)
  t.wrapS = t.wrapT = THREE.RepeatWrapping
  t.generateMipmaps = true
  t.minFilter = THREE.LinearMipmapLinearFilter
  t.magFilter = THREE.LinearFilter
  t.anisotropy = 4
  t.needsUpdate = true
  return t
}

/** Mapa de normales a partir de un campo de alturas (que se repite en los bordes). */
function normales(alto: Float32Array, w: number, h: number, fuerza: number) {
  const A = (x: number, y: number) => alto[((y + h) % h) * w + ((x + w) % w)]
  return datos(w, h, (x, y) => {
    const dx = (A(x + 1, y) - A(x - 1, y)) * fuerza
    const dy = (A(x, y + 1) - A(x, y - 1)) * fuerza
    const l = Math.hypot(dx, dy, 1)
    return [((-dx / l) * 0.5 + 0.5) * 255, ((-dy / l) * 0.5 + 0.5) * 255, ((1 / l) * 0.5 + 0.5) * 255]
  })
}

let semilla = 7
const azar = () => { semilla = (semilla * 16807) % 2147483647; return semilla / 2147483647 }

/** Cuero granulado: cientos de "granos" suaves. */
function cuero() {
  const w = 256, h = 256, alto = new Float32Array(w * h)
  for (let k = 0; k < 2600; k++) {
    const cx = azar() * w, cy = azar() * h, r = 1.6 + azar() * 3
    for (let y = Math.floor(cy - r); y <= cy + r; y++) {
      for (let x = Math.floor(cx - r); x <= cx + r; x++) {
        const d = Math.hypot(x - cx, y - cy) / r
        if (d < 1) alto[((y + h) % h) * w + ((x + w) % w)] += (1 - d * d) * 0.5
      }
    }
  }
  return normales(alto, w, h, 2.2)
}

/** Agarre: puntos en filas alternadas (textura de los mangos del control). */
function agarre() {
  const w = 256, h = 256, alto = new Float32Array(w * h), paso = 16
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const fila = Math.round(y / paso)
      const desfase = fila % 2 ? paso / 2 : 0
      const cx = Math.round((x - desfase) / paso) * paso + desfase, cy = fila * paso
      const d = Math.hypot(x - cx, y - cy) / (paso * 0.36)
      alto[y * w + x] = d < 1 ? Math.cos(d * Math.PI / 2) : 0
    }
  }
  return normales(alto, w, h, 3)
}

/** Estrías (goma de los sticks y anillos): franjas en v. */
function estrias() {
  const w = 64, h = 256, alto = new Float32Array(w * h)
  for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) alto[y * w + x] = Math.sin((y / h) * Math.PI * 2 * 18) * 0.5
  return normales(alto, w, h, 4)
}

/** Metal cepillado: rugosidad baja con rayas finas a lo largo de u. */
function cepillado() {
  const filas = Array.from({ length: 256 }, () => azar())
  return datos(256, 256, (x, y) => {
    const v = 0.05 + filas[y] * 0.07 + (azar() - 0.5) * 0.02
    const g = Math.max(0, Math.min(1, v)) * 255
    return [g, g, g]
  })
}

// ── Materiales ─────────────────────────────────────────────────────────────
function materiales() {
  const tCuero = cuero(), tAgarre = agarre(), tEstrias = estrias(), tCepillado = cepillado()
  tCepillado.repeat.set(1, 3)
  tAgarre.repeat.set(0.03, 0.03)
  tEstrias.repeat.set(1, 1)
  const brilloMorado = MORADO.clone().multiplyScalar(2.2)
  const m = {
    cromo: new THREE.MeshPhysicalMaterial({ color: 0xf1f2f8, metalness: 1, roughness: 1, roughnessMap: tCepillado, clearcoat: 0.35, clearcoatRoughness: 0.06, envMapIntensity: 1.25 }),
    gema: new THREE.MeshPhysicalMaterial({ color: 0x6a12ff, emissive: 0x2c0070, emissiveIntensity: 0.9, metalness: 0.1, roughness: 0.05, clearcoat: 1, iridescence: 0.55, iridescenceIOR: 1.35, flatShading: true, envMapIntensity: 2.2 }),
    negro: new THREE.MeshPhysicalMaterial({ color: 0x0b0b10, roughness: 0.26, clearcoat: 1, clearcoatRoughness: 0.04, envMapIntensity: 1.1 }),
    placa: new THREE.MeshPhysicalMaterial({ color: 0xd9dbe4, metalness: 1, roughness: 1, roughnessMap: tCepillado, envMapIntensity: 1 }),
    luz: new THREE.MeshBasicMaterial({ color: brilloMorado }),
    halo: new THREE.MeshBasicMaterial({ color: MORADO, transparent: true, opacity: 0.22, blending: THREE.AdditiveBlending, depthWrite: false }),
    cueroBlanco: new THREE.MeshPhysicalMaterial({ color: 0xf3f3f1, roughness: 0.46, normalMap: tCuero, normalScale: new THREE.Vector2(0.4, 0.4), clearcoat: 0.55, clearcoatRoughness: 0.32 }),
    cueroNegro: new THREE.MeshPhysicalMaterial({ color: 0x131318, roughness: 0.42, normalMap: tCuero, normalScale: new THREE.Vector2(0.35, 0.35), clearcoat: 0.55, clearcoatRoughness: 0.3 }),
    costura: new THREE.MeshStandardMaterial({ color: 0x2a2a30, roughness: 0.8 }),
    carcasa: new THREE.MeshPhysicalMaterial({ color: 0x121019, roughness: 0.38, clearcoat: 0.9, clearcoatRoughness: 0.12, envMapIntensity: 1 }),
    mango: new THREE.MeshPhysicalMaterial({ color: 0x5d17d6, emissive: 0x3b0b94, emissiveIntensity: 0.45, roughness: 0.3, normalMap: tAgarre, normalScale: new THREE.Vector2(0.6, 0.6), clearcoat: 0.6, clearcoatRoughness: 0.2, envMapIntensity: 1.3 }),
    panel: new THREE.MeshPhysicalMaterial({ color: 0x5d17d6, emissive: 0x3b0b94, emissiveIntensity: 0.55, roughness: 0.08, clearcoat: 1, clearcoatRoughness: 0.03, transparent: true, opacity: 0.9, envMapIntensity: 1.6 }),
    goma: new THREE.MeshStandardMaterial({ color: 0x1b1b21, roughness: 0.82, normalMap: tEstrias, normalScale: new THREE.Vector2(0.7, 0.7) }),
  }
  const texturas = [tCuero, tAgarre, tEstrias, tCepillado]
  return { m, texturas }
}
type Mats = ReturnType<typeof materiales>['m']

/** Pieza que brilla + su halo (un anillo más grueso, aditivo y transparente). */
function anilloDeLuz(m: Mats, radio: number, grueso: number, segmentos = 96) {
  const g = new THREE.Group()
  g.add(new THREE.Mesh(new THREE.TorusGeometry(radio, grueso, 12, segmentos), m.luz))
  g.add(new THREE.Mesh(new THREE.TorusGeometry(radio, grueso * 3.2, 10, segmentos), m.halo))
  return g
}

/** Cristal facetado alargado (bipirámide). */
function cristal(m: Mats, alto: number, ancho: number, caras = 6) {
  const g = new THREE.LatheGeometry([new THREE.Vector2(0, -alto * 0.4), new THREE.Vector2(ancho, 0), new THREE.Vector2(0, alto * 0.6)], caras)
  g.computeVertexNormals()
  return new THREE.Mesh(g, m.gema)
}

// ── Trofeo de campeón ──────────────────────────────────────────────────────
function trofeo(m: Mats) {
  const g = new THREE.Group()
  const perfil = [
    [0, -30], [10, -30], [11, -29], [10, -27.5], [6, -26.5], [4.6, -23], [4.2, -19], [6.8, -16.5], [6.8, -14], [4.4, -11.5],
    [4.8, -7], [8, -4], [14, -1], [20, 3], [25, 10], [28, 19], [29.5, 29], [30.5, 38], [32.8, 44], [33.6, 45.4], [32.6, 46.4],
    [30.2, 45.6], [28.8, 38], [27.8, 29], [26.4, 19], [23.2, 10], [18.5, 4], [12.5, 0.3], [6, -1.6], [0, -2.2],
  ].map(([x, y]) => new THREE.Vector2(x, y))
  g.add(new THREE.Mesh(new THREE.LatheGeometry(new THREE.SplineCurve(perfil).getPoints(180), 96), m.cromo))

  // Aro de luz en el borde y en el nudo del tallo
  const aro = anilloDeLuz(m, 31.4, 0.55); aro.rotation.x = Math.PI / 2; aro.position.y = 40.5; g.add(aro)
  const nudo = anilloDeLuz(m, 7.2, 0.45, 48); nudo.rotation.x = Math.PI / 2; nudo.position.y = -15.2; g.add(nudo)

  // Cristales: uno grande al frente, dos a los lados y uno atrás (siguen la inclinación de la copa)
  ;[0, Math.PI / 2, Math.PI, -Math.PI / 2].forEach((ang, k) => {
    const c = cristal(m, k === 0 ? 26 : 20, k === 0 ? 4.2 : 3.4)
    const r = 27.6
    c.position.set(Math.sin(ang) * r, 22, Math.cos(ang) * r)
    c.rotation.y = ang
    c.rotation.x = -0.16
    g.add(c)
  })
  const gemaNudo = cristal(m, 11, 4.8, 8); gemaNudo.position.y = -15.4; g.add(gemaNudo)

  // Asas angulares de metal con un cristal en la punta
  const asa = new THREE.CurvePath<THREE.Vector3>()
  const pts = [V3(29.5, 38, 0), V3(41, 47, 0), V3(45, 41, 0), V3(38, 22, 0), V3(25, 11, 0)]
  for (let k = 0; k < pts.length - 1; k++) asa.add(new THREE.LineCurve3(pts[k], pts[k + 1]))
  const geoAsa = new THREE.TubeGeometry(asa, 120, 2.1, 10)
  ;[1, -1].forEach((lado) => {
    const a = new THREE.Mesh(geoAsa, m.cromo); a.scale.x = lado; g.add(a)
    const punta = cristal(m, 9, 2.6); punta.position.set(lado * 42.5, 46.5, 0); punta.rotation.z = -lado * 0.5; g.add(punta)
  })

  // Base octagonal de dos pisos: laca negra, plato de luz y placa de metal cepillado
  const piso1 = new THREE.Mesh(new THREE.CylinderGeometry(15, 17, 6, 8), m.negro); piso1.rotation.y = Math.PI / 8; piso1.position.y = -33; g.add(piso1)
  const plato = new THREE.Mesh(new THREE.CylinderGeometry(17.6, 17.6, 0.9, 8), m.luz); plato.rotation.y = Math.PI / 8; plato.position.y = -36.4; g.add(plato)
  const piso2 = new THREE.Mesh(new THREE.CylinderGeometry(19, 21.5, 10, 8), m.negro); piso2.rotation.y = Math.PI / 8; piso2.position.y = -41.8; g.add(piso2)
  const placa = new THREE.Mesh(new THREE.BoxGeometry(14, 4.6, 0.6), m.placa)
  placa.position.set(0, -41.8, 20.15 * Math.cos(Math.PI / 8) - 0.6); placa.rotation.x = -0.12; g.add(placa)

  g.position.y = 2
  return g
}

// ── Control épico (diseño original) ────────────────────────────────────────
function control(m: Mats) {
  const g = new THREE.Group()
  const s = new THREE.Shape()
  s.moveTo(-24, 24)
  s.bezierCurveTo(-10, 27, 10, 27, 24, 24)
  s.bezierCurveTo(40, 21, 49, 10, 50, -4)
  s.bezierCurveTo(51, -20, 46, -34, 36, -36)
  s.bezierCurveTo(29, -37, 25, -31, 21, -24)
  s.quadraticCurveTo(17, -17, 10, -17)
  s.lineTo(-10, -17)
  s.quadraticCurveTo(-17, -17, -21, -24)
  s.bezierCurveTo(-25, -31, -29, -37, -36, -36)
  s.bezierCurveTo(-46, -34, -51, -20, -50, -4)
  s.bezierCurveTo(-49, 10, -40, 21, -24, 24)
  const cuerpo = new THREE.ExtrudeGeometry(s, { depth: 6, bevelEnabled: true, bevelThickness: 5, bevelSize: 4.5, bevelSegments: 10, curveSegments: 56 })
  cuerpo.translate(0, 0, -3)
  g.add(new THREE.Mesh(cuerpo, m.carcasa))
  const Z = 8

  // Panel morado traslúcido arriba (banda) y en cada mango
  const banda = new THREE.Shape()
  banda.moveTo(-36, 14); banda.bezierCurveTo(-20, 22, 20, 22, 36, 14)
  banda.bezierCurveTo(30, 8, 20, 6, 12, 7); banda.lineTo(-12, 7); banda.bezierCurveTo(-20, 6, -30, 8, -36, 14)
  const geoBanda = new THREE.ExtrudeGeometry(banda, { depth: 0.8, bevelEnabled: true, bevelThickness: 0.5, bevelSize: 0.5, bevelSegments: 3, curveSegments: 32 })
  const pBanda = new THREE.Mesh(geoBanda, m.panel); pBanda.position.z = Z - 0.2; g.add(pBanda)
  ;[1, -1].forEach((lado) => {
    const mango = new THREE.Shape()
    mango.moveTo(32, -8); mango.bezierCurveTo(42, -10, 46, -22, 40, -31); mango.bezierCurveTo(35, -35, 28, -30, 26, -24); mango.quadraticCurveTo(28, -14, 32, -8)
    const geo = new THREE.ExtrudeGeometry(mango, { depth: 0.8, bevelEnabled: true, bevelThickness: 0.5, bevelSize: 0.5, bevelSegments: 3, curveSegments: 24 })
    const p = new THREE.Mesh(geo, m.mango); p.position.z = Z - 0.2; p.scale.x = lado; g.add(p)
  })

  // Tira de luz bajo la banda
  const tira = new THREE.CatmullRomCurve3([V3(-34, 12.6, Z + 0.9), V3(-12, 6.8, Z + 0.9), V3(12, 6.8, Z + 0.9), V3(34, 12.6, Z + 0.9)])
  g.add(new THREE.Mesh(new THREE.TubeGeometry(tira, 80, 0.45, 8), m.luz))
  g.add(new THREE.Mesh(new THREE.TubeGeometry(tira, 80, 1.5, 8), m.halo))

  // Cruceta en su pozo con anillo de luz
  const pozo = new THREE.CylinderGeometry(10, 10, 1.2, 48); pozo.rotateX(Math.PI / 2)
  const pozoI = new THREE.Mesh(pozo, m.negro); pozoI.position.set(-29, 0, Z + 0.4); g.add(pozoI)
  const anI = anilloDeLuz(m, 10.3, 0.25, 64); anI.position.set(-29, 0, Z + 1); g.add(anI)
  const cruz = new THREE.Shape(); const a = 2.6, l = 7.8
  cruz.moveTo(-a, l); cruz.lineTo(a, l); cruz.lineTo(a, a); cruz.lineTo(l, a); cruz.lineTo(l, -a); cruz.lineTo(a, -a)
  cruz.lineTo(a, -l); cruz.lineTo(-a, -l); cruz.lineTo(-a, -a); cruz.lineTo(-l, -a); cruz.lineTo(-l, a); cruz.lineTo(-a, a); cruz.lineTo(-a, l)
  const cruceta = new THREE.Mesh(new THREE.ExtrudeGeometry(cruz, { depth: 1.8, bevelEnabled: true, bevelThickness: 0.7, bevelSize: 0.6, bevelSegments: 4 }), m.negro)
  cruceta.position.set(-29, 0, Z + 1); g.add(cruceta)

  // Botones en domo (cristal morado con luz propia), sin símbolos
  const pozoD = new THREE.Mesh(pozo, m.negro); pozoD.position.set(29, 0, Z + 0.4); g.add(pozoD)
  const anD = anilloDeLuz(m, 10.3, 0.25, 64); anD.position.set(29, 0, Z + 1); g.add(anD)
  const domo = new THREE.SphereGeometry(3.2, 32, 16, 0, Math.PI * 2, 0, Math.PI / 2); domo.rotateX(Math.PI / 2)
  ;[[0, 6.2], [6.2, 0], [0, -6.2], [-6.2, 0]].forEach(([x, y]) => {
    const b = new THREE.Mesh(domo, m.gema); b.position.set(29 + x, y, Z + 1); g.add(b)
  })

  // Sticks: base, aro de cromo, tapa de goma con estrías y anillo de luz
  const base = new THREE.CylinderGeometry(8.6, 8.6, 1.2, 48); base.rotateX(Math.PI / 2)
  const tallo = new THREE.CylinderGeometry(3.4, 4, 3.4, 24); tallo.rotateX(Math.PI / 2)
  const tapa = new THREE.LatheGeometry([[0, 3.2], [2.5, 3.5], [4.8, 4.2], [6.4, 3.9], [6.9, 2.6], [6.6, 0.9], [4.4, 0]].map(([x, y]) => new THREE.Vector2(x, y)), 48)
  tapa.rotateX(Math.PI / 2)
  const aroCromo = new THREE.TorusGeometry(8.6, 0.65, 12, 64)
  ;[-13, 13].forEach((x) => {
    const y = -10
    const b = new THREE.Mesh(base, m.negro); b.position.set(x, y, Z + 0.4); g.add(b)
    const r = new THREE.Mesh(aroCromo, m.cromo); r.position.set(x, y, Z + 1); g.add(r)
    const t = new THREE.Mesh(tallo, m.negro); t.position.set(x, y, Z + 2.4); g.add(t)
    const c = new THREE.Mesh(tapa, m.goma); c.position.set(x, y, Z + 3.8); g.add(c)
    const luz = anilloDeLuz(m, 7, 0.28, 64); luz.position.set(x, y, Z + 4.4); g.add(luz)
  })

  // Touchpad negro, botón central de luz y gatillos con filo de cromo
  const pad = new THREE.Shape(); const pw = 13, ph = 5.2, pr = 2.4
  pad.moveTo(-pw + pr, -ph); pad.lineTo(pw - pr, -ph); pad.quadraticCurveTo(pw, -ph, pw, -ph + pr); pad.lineTo(pw, ph - pr); pad.quadraticCurveTo(pw, ph, pw - pr, ph)
  pad.lineTo(-pw + pr, ph); pad.quadraticCurveTo(-pw, ph, -pw, ph - pr); pad.lineTo(-pw, -ph + pr); pad.quadraticCurveTo(-pw, -ph, -pw + pr, -ph)
  const touch = new THREE.Mesh(new THREE.ExtrudeGeometry(pad, { depth: 0.6, bevelEnabled: true, bevelThickness: 0.4, bevelSize: 0.4, bevelSegments: 3, curveSegments: 12 }), m.negro)
  touch.position.set(0, 14.5, Z + 0.6); g.add(touch)
  const centro = anilloDeLuz(m, 1.8, 0.5, 32); centro.position.set(0, -3, Z + 0.8); g.add(centro)
  const gatillo = new THREE.Shape(); const gw = 9, gh = 3.2, gr = 3
  gatillo.moveTo(-gw + gr, -gh); gatillo.lineTo(gw - gr, -gh); gatillo.quadraticCurveTo(gw, -gh, gw, -gh + gr); gatillo.lineTo(gw, gh - gr); gatillo.quadraticCurveTo(gw, gh, gw - gr, gh)
  gatillo.lineTo(-gw + gr, gh); gatillo.quadraticCurveTo(-gw, gh, -gw, gh - gr); gatillo.lineTo(-gw, -gh + gr); gatillo.quadraticCurveTo(-gw, -gh, -gw + gr, -gh)
  const geoGat = new THREE.ExtrudeGeometry(gatillo, { depth: 8, bevelEnabled: true, bevelThickness: 2, bevelSize: 1.6, bevelSegments: 6, curveSegments: 12 })
  geoGat.translate(0, 0, -4)
  ;[1, -1].forEach((lado) => {
    const gat = new THREE.Mesh(geoGat, m.carcasa)
    gat.position.set(lado * 30, 24.5, -3); gat.rotation.z = -lado * 0.12; g.add(gat)
    const luz = new THREE.Mesh(new THREE.BoxGeometry(14, 0.6, 0.6), m.luz)
    luz.position.set(lado * 30, 27.4, 3.6); luz.rotation.z = -lado * 0.12; g.add(luz)
  })

  g.rotation.x = -0.18
  g.position.y = 3
  return g
}

// ── Balón (icosaedro truncado de gajos inflados con cuero) ─────────────────
function balon(m: Mats) {
  const g = new THREE.Group()
  const R = 44
  const phi = (1 + Math.sqrt(5)) / 2
  const crudos = [[-1, phi, 0], [1, phi, 0], [-1, -phi, 0], [1, -phi, 0], [0, -1, phi], [0, 1, phi], [0, -1, -phi], [0, 1, -phi], [phi, 0, -1], [phi, 0, 1], [-phi, 0, -1], [-phi, 0, 1]]
    .map(([x, y, z]) => V3(x, y, z).normalize())
  const giro = new THREE.Quaternion().setFromUnitVectors(crudos[5], V3(0, 0.35, 1).normalize())
  const ico = crudos.map((v) => v.clone().applyQuaternion(giro))
  const arista = Math.min(...ico.slice(1).map((v) => v.distanceTo(ico[0])))
  const vecinos = ico.map((v) => ico.map((_, j) => j).filter((j) => Math.abs(v.distanceTo(ico[j]) - arista) < 1e-4))
  const tercio = (a: THREE.Vector3, b: THREE.Vector3) => a.clone().lerp(b, 1 / 3).normalize()

  const gajo = (poligono: THREE.Vector3[]) => {
    const S = 7, E = 7, inflado = 2, hueco = 0.045
    const c = poligono.reduce((s, p) => s.add(p), V3(0, 0, 0)).normalize()
    const borde = poligono.map((p) => p.clone().lerp(c, hueco).normalize())
    const nrm = V3(0, 0, 0).subVectors(borde[0], c).cross(V3(0, 0, 0).subVectors(borde[1], c))
    if (nrm.dot(c) < 0) borde.reverse()
    const contorno: THREE.Vector3[] = []
    borde.forEach((p, i) => { const q = borde[(i + 1) % borde.length]; for (let e = 0; e < E; e++) contorno.push(p.clone().lerp(q, e / E).normalize()) })
    // Coordenadas de textura planas por gajo (sin costura de la esfera).
    const t1 = V3(0, 1, 0).cross(c).normalize(); if (t1.lengthSq() < 1e-6) t1.set(1, 0, 0)
    const t2 = c.clone().cross(t1)
    const M = contorno.length
    const radio = (k: number) => R + inflado * (1 - Math.pow(k / S, 3))
    const pos: number[] = [], uv: number[] = []
    const poner = (d: THREE.Vector3, r: number) => { const p = d.clone().multiplyScalar(r); pos.push(p.x, p.y, p.z); uv.push(d.dot(t1) * 1.6, d.dot(t2) * 1.6) }
    poner(c, radio(0))
    for (let k = 1; k <= S; k++) for (let j = 0; j < M; j++) poner(c.clone().lerp(contorno[j], k / S).normalize(), radio(k))
    const en = (k: number, j: number) => (k === 0 ? 0 : 1 + (k - 1) * M + (j % M))
    const idx: number[] = []
    for (let j = 0; j < M; j++) idx.push(0, en(1, j), en(1, j + 1))
    for (let k = 1; k < S; k++) for (let j = 0; j < M; j++) idx.push(en(k, j), en(k + 1, j), en(k + 1, j + 1), en(k, j), en(k + 1, j + 1), en(k, j + 1))
    const geo = new THREE.BufferGeometry()
    geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3))
    geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2))
    geo.setIndex(idx)
    geo.computeVertexNormals()
    return geo
  }
  ico.forEach((v, i) => {
    const ref = Math.abs(v.y) < 0.9 ? V3(0, 1, 0) : V3(1, 0, 0)
    const t1 = ref.cross(v).normalize(), t2 = v.clone().cross(t1)
    const ang = (p: THREE.Vector3) => Math.atan2(p.clone().sub(v).dot(t2), p.clone().sub(v).dot(t1))
    g.add(new THREE.Mesh(gajo(vecinos[i].map((j) => tercio(v, ico[j])).sort((p, q) => ang(p) - ang(q))), m.cueroNegro))
  })
  for (let a = 0; a < 12; a++) for (const b of vecinos[a]) {
    if (b <= a) continue
    for (const c of vecinos[b]) {
      if (c <= b || !vecinos[a].includes(c)) continue
      const A = ico[a], B = ico[b], C = ico[c]
      g.add(new THREE.Mesh(gajo([tercio(A, B), tercio(B, A), tercio(B, C), tercio(C, B), tercio(C, A), tercio(A, C)]), m.cueroBlanco))
    }
  }
  g.add(new THREE.Mesh(new THREE.SphereGeometry(R - 0.4, 64, 48), m.costura))
  return g
}

/** Escena que solo sirve para los reflejos (se hornea una vez con PMREM). */
function estudioDeProducto() {
  const s = new THREE.Scene()
  const cielo = new THREE.SphereGeometry(60, 32, 16)
  // Cielo oscuro arriba, franja clara en el horizonte y piso gris claro que se apaga hacia abajo:
  // las paredes de la copa miran un poco hacia abajo y reflejan el piso (con piso negro, el cromo
  // se veía negro). Es el "infinito" gris de las fotos de trofeos.
  const arriba = new THREE.Color(0x221c30), horizonte = new THREE.Color(0x9a94ae), abajo = new THREE.Color(0x2a2633)
  const pos = cielo.attributes.position
  const colores: number[] = []
  for (let i = 0; i < pos.count; i++) {
    const y = pos.getY(i) / 60
    const c = y >= 0 ? horizonte.clone().lerp(arriba, Math.min(1, y / 0.45)) : horizonte.clone().lerp(abajo, Math.min(1, -y / 0.85))
    colores.push(c.r, c.g, c.b)
  }
  cielo.setAttribute('color', new THREE.Float32BufferAttribute(colores, 3))
  s.add(new THREE.Mesh(cielo, new THREE.MeshBasicMaterial({ vertexColors: true, side: THREE.BackSide })))
  const luz = (color: THREE.Color, x: number, y: number, z: number, w: number, h: number) => {
    const p = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color, side: THREE.DoubleSide }))
    p.position.set(x, y, z); p.lookAt(0, 0, 0); s.add(p)
  }
  const blanco = (k: number) => new THREE.Color(1, 1, 1).multiplyScalar(k)
  const morado = (k: number) => MORADO.clone().multiplyScalar(k)
  luz(blanco(3), 0, 42, 10, 46, 30) // caja de luz arriba
  luz(blanco(2.2), -44, 6, 22, 26, 50) // cajas grandes a los lados
  luz(blanco(1.6), 44, 4, 14, 22, 46)
  luz(blanco(8), -30, 4, 40, 4, 48) // tiras: la línea de brillo vertical
  luz(blanco(5), 34, 2, 32, 3, 44)
  luz(morado(6), -34, 6, -32, 18, 40) // paneles morados atrás
  luz(morado(5), 36, -6, -28, 14, 34)
  luz(blanco(0.7), 0, -6, 52, 50, 16) // relleno suave de frente
  luz(blanco(1.3), 0, -44, 26, 70, 40) // tarjeta de rebote en el piso
  return s
}

const MODELOS: Record<NombreObjeto, (m: Mats) => THREE.Group> = { control, balon, trofeo }

// ── Escena ─────────────────────────────────────────────────────────────────
/**
 * Arma la escena en pasos cortos (cede el hilo entre uno y otro) y luego, en
 * segundo plano, arma los tres modelos y compila sus shaders con compileAsync.
 * Antes todo pasaba en el cuadro en que empezaba el scroll o en el que aparecía
 * cada objeto, y se sentía un tirón de 50–80 ms.
 */
export async function crearObjetos(lienzo: HTMLCanvasElement): Promise<Objetos3D> {
  const render = new THREE.WebGLRenderer({ canvas: lienzo, alpha: true, antialias: true, powerPreference: 'high-performance' })
  render.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2))
  render.outputColorSpace = THREE.SRGBColorSpace
  render.toneMapping = THREE.NeutralToneMapping
  render.toneMappingExposure = 1.05

  const escena = new THREE.Scene()
  const camara = new THREE.PerspectiveCamera(30, 1, 1, 2000)
  camara.position.set(0, 4, 230)
  camara.lookAt(0, 0, 0)
  await ceder()

  // Estudio de foto de producto: fondo oscuro, una caja de luz arriba, tiras blancas a los lados
  // (la línea de brillo vertical en el cromo) y paneles morados atrás (la luz de la marca).
  const pmrem = new THREE.PMREMGenerator(render)
  const estudio = estudioDeProducto()
  const reflejos = pmrem.fromScene(estudio, 0.02).texture
  estudio.traverse((o) => { const mm = o as THREE.Mesh; mm.geometry?.dispose(); (mm.material as THREE.Material | undefined)?.dispose?.() })
  pmrem.dispose()
  escena.environment = reflejos
  escena.environmentIntensity = 0.9
  await ceder()

  const clave = new THREE.DirectionalLight(0xffffff, 1.5); clave.position.set(-90, 140, 180); escena.add(clave)
  const contra = new THREE.DirectionalLight(0xd25bff, 2.2); contra.position.set(140, 40, -160); escena.add(contra)
  const contra2 = new THREE.DirectionalLight(0x8a2bff, 1.4); contra2.position.set(-160, -80, -120); escena.add(contra2)

  const { m, texturas } = materiales()
  const modelos: Partial<Record<NombreObjeto, THREE.Group>> = {}
  const pivote = new THREE.Group()
  escena.add(pivote)
  let actual: NombreObjeto | null = null
  let scroll = 0, solo = 0, activo = false, cuadro = 0, previo = 0
  const t0 = performance.now()
  let encendido = true

  function asegurar(n: NombreObjeto) {
    if (!modelos[n]) {
      const g = MODELOS[n](m)
      // Encuadre: que el objeto mida ~84 % del alto del lienzo.
      const caja = new THREE.Box3().setFromObject(g)
      const tam = caja.getSize(V3(0, 0, 0))
      const centro = caja.getCenter(V3(0, 0, 0))
      const contenedor = new THREE.Group()
      g.position.sub(centro)
      contenedor.add(g)
      contenedor.userData.escala = 100 / Math.max(tam.y, tam.x * 0.78)
      contenedor.visible = false
      pivote.add(contenedor)
      modelos[n] = contenedor
    }
    return modelos[n]!
  }

  const medir = () => {
    const w = lienzo.clientWidth, h = lienzo.clientHeight
    if (!w || !h) return
    render.setSize(w, h, false)
    camara.aspect = w / h
    camara.updateProjectionMatrix()
    dibujarUnaVez()
  }
  const obs = new ResizeObserver(medir)
  obs.observe(lienzo)

  function dibujarUnaVez() {
    const t = (performance.now() - t0) / 1000
    pivote.rotation.y = (scroll + solo) * Math.PI * 2
    pivote.rotation.x = 0.12 + Math.sin(t * 0.7) * 0.05
    pivote.position.y = Math.sin(t * 1.1) * 1.6
    if (actual) {
      const c = modelos[actual]!
      c.scale.setScalar(c.userData.escala * 0.84)
    }
    render.render(escena, camara)
  }
  const latido = (ahora: number) => {
    if (!activo) return
    const dt = previo ? Math.min(0.1, (ahora - previo) / 1000) : 0
    previo = ahora
    if (!document.hidden) {
      solo += dt * 0.22 // giro en reposo: una vuelta cada ~4.5 s, suave porque es 3D real
      dibujarUnaVez()
    }
    cuadro = requestAnimationFrame(latido)
  }

  // En segundo plano y en el orden en que aparecen: armar cada modelo y compilar sus shaders
  // sin bloquear (compileAsync usa la compilación en paralelo del navegador cuando existe).
  ;(async () => {
    for (const n of ['control', 'balon', 'trofeo'] as NombreObjeto[]) {
      await libre(1000)
      if (!encendido) return
      const c = asegurar(n)
      c.visible = true
      try { await render.compileAsync(escena, camara) } catch { /* se compila al dibujar */ }
      if (!encendido) return
      c.visible = actual === n
    }
  })()

  return {
    ver(n) {
      if (n === actual) return
      if (actual && modelos[actual]) modelos[actual]!.visible = false
      actual = n
      if (n) asegurar(n).visible = true
      dibujarUnaVez()
    },
    girar(v) { scroll = v; if (!activo) dibujarUnaVez() },
    vivo(si) {
      if (si === activo) return
      activo = si
      if (si) { previo = 0; cuadro = requestAnimationFrame(latido) } else cancelAnimationFrame(cuadro)
    },
    destruir() {
      encendido = false
      cancelAnimationFrame(cuadro)
      obs.disconnect()
      pivote.traverse((o) => { const mm = o as THREE.Mesh; mm.geometry?.dispose() })
      Object.values(m).forEach((x) => x.dispose())
      texturas.forEach((t) => t.dispose())
      reflejos.dispose()
      render.dispose()
    },
  }
}
