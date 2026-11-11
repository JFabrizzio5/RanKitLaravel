<script setup lang="ts">
/**
 * El widget de la tabla que va en OBS (y en la vista previa de "Personalizar"
 * y en la landing). Solo pinta: los datos y la configuración llegan por props.
 *
 * Pie: el logo del torneo (o su nombre) junto al de rankit.pro, siempre los
 * dos a la vista (antes se turnaban y el de Rankit casi no se veía).
 */
import { computed } from 'vue'
import type { ConfigWidget, FilaWidget } from './configWidget'

const props = defineProps<{
  filas: FilaWidget[]
  config: ConfigWidget
  torneo: { nombre: string; logo: string | null } | null
  cargando?: boolean
}>()

const acento = computed(() => '#' + props.config.acento)
const nombre = (f: FilaWidget) => (props.config.tipo === 'teams' ? (f.member_names ?? []).join(' · ') : f.player_name ?? '')
const valor = (f: FilaWidget) => (props.config.orden === 'kills' ? f.total_kills : f.total_points)
const maxKills = computed(() => Math.max(0, ...props.filas.map((f) => f.total_kills || 0)))
const titulo = computed(() => {
  if (props.config.titulo) return props.config.titulo
  if (props.config.buscar) return 'Seguimiento'
  return (props.config.tipo === 'teams' ? 'Top equipos' : 'Top jugadores') + (props.config.orden === 'kills' ? ' · kills' : '')
})
const uno = computed(() => props.filas[0])
const fmt = (n: number) => (Number.isInteger(n) ? n : Number(n.toFixed(2))).toLocaleString('es-MX')
</script>

<template>
  <div class="rk-widget" :class="[`tema-${config.tema}`, config.compacto ? 'compacto' : '']" :style="{ '--acento': acento }">
    <div class="caja">
      <!-- Encabezado -->
      <header class="cabeza">
        <div class="min-w-0">
          <p class="titulo">{{ titulo }}</p>
          <p v-if="torneo" class="subtitulo">{{ torneo.nombre }}</p>
        </div>
        <span class="vivo"><span class="punto"></span>En vivo</span>
      </header>

      <!-- Seguimiento de un jugador -->
      <div v-if="config.buscar && uno" class="seguimiento">
        <div class="flex items-end justify-between gap-4">
          <p class="nombre-grande">{{ nombre(uno) }}</p>
          <p class="puesto">#{{ uno.rank }}</p>
        </div>
        <div class="datos">
          <div><span class="cifra acento">{{ fmt(uno.total_points) }}</span><span class="etq">Puntos</span></div>
          <div><span class="cifra">{{ uno.total_kills }}</span><span class="etq">Kills</span></div>
          <div><span class="cifra">{{ uno.games_played }}</span><span class="etq">Partidas</span></div>
        </div>
      </div>

      <!-- Tabla -->
      <TransitionGroup v-else tag="ol" name="fila" class="lista">
        <li v-for="(f, i) in filas" :key="nombre(f) || i" class="fila" :class="{ primero: i === 0 }">
          <span class="lugar" :class="i < 3 ? `podio-${i + 1}` : ''">{{ f.rank }}</span>
          <span class="min-w-0 flex-1">
            <span class="nombre">{{ nombre(f) }}</span>
            <span class="meta">{{ f.games_played }} {{ f.games_played === 1 ? 'partida' : 'partidas' }} · <span :class="{ 'kills-max': f.total_kills === maxKills && maxKills > 0 }">{{ f.total_kills }} kills</span></span>
          </span>
          <span class="puntos">{{ fmt(valor(f)) }}<span class="etq">{{ config.orden === 'kills' ? 'kills' : 'pts' }}</span></span>
        </li>
        <li v-if="!filas.length" key="vacio" class="vacio">{{ cargando ? 'Cargando tabla…' : 'Aún no hay partidas procesadas' }}</li>
      </TransitionGroup>

      <!-- Pie: logo del torneo + rankit.pro -->
      <footer v-if="config.logos" class="pie">
        <span class="torneo">
          <img v-if="torneo?.logo" :src="torneo.logo" :alt="torneo.nombre" />
          <span v-else-if="torneo" class="torneo-nombre">{{ torneo.nombre }}</span>
        </span>
        <span class="separador"></span>
        <span class="rankit">
          <svg viewBox="0 0 100 100" aria-hidden="true">
            <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
            <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
            <path d="M50 55 L80 55 L95 90 L65 90 Z" :fill="acento" />
          </svg>
          rankit.pro
        </span>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.rk-widget {
  --fondo: rgba(10, 10, 14, 0.96);
  --fondo-fila: rgba(255, 255, 255, 0.03);
  --texto: #fff;
  --suave: rgba(255, 255, 255, 0.55);
  --borde: rgba(255, 255, 255, 0.1);
  width: 100%;
  max-width: 420px;
  font-family: 'Archivo', ui-sans-serif, system-ui, sans-serif;
  color: var(--texto);
}
.rk-widget.compacto { max-width: 340px; }
.tema-cristal { --fondo: rgba(12, 10, 18, 0.62); --fondo-fila: rgba(255, 255, 255, 0.05); }
.tema-claro { --fondo: rgba(255, 255, 255, 0.97); --fondo-fila: rgba(0, 0, 0, 0.03); --texto: #0b0b0e; --suave: rgba(0, 0, 0, 0.55); --borde: rgba(0, 0, 0, 0.1); }
.tema-transparente { --fondo: transparent; --fondo-fila: rgba(0, 0, 0, 0.35); --borde: transparent; text-shadow: 0 1px 6px rgba(0, 0, 0, 0.9); }

.caja { background: var(--fondo); border: 1px solid var(--borde); border-top: 3px solid var(--acento); border-radius: 14px; overflow: hidden; backdrop-filter: blur(10px); }
.tema-transparente .caja { border-top-color: transparent; backdrop-filter: none; }

.cabeza { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px 10px; }
.titulo { font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-style: italic; text-transform: uppercase; font-size: 18px; line-height: 1; letter-spacing: -0.01em; }
.subtitulo { margin-top: 4px; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--suave); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vivo { display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; padding: 4px 9px; border-radius: 999px; background: rgba(239, 68, 68, 0.14); color: #ff6b6b; font-size: 10px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; }
.punto { width: 7px; height: 7px; border-radius: 999px; background: #ef4444; animation: latido 1.6s ease-in-out infinite; }
@keyframes latido { 50% { opacity: 0.35; } }

.lista { padding: 0 8px 8px; display: grid; gap: 4px; }
.fila { display: flex; align-items: center; gap: 12px; padding: 9px 10px; border-radius: 10px; background: var(--fondo-fila); }
.compacto .fila { padding: 6px 8px; gap: 10px; }
.fila.primero { background: color-mix(in srgb, var(--acento) 22%, transparent); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--acento) 45%, transparent); }
.lugar { display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; border-radius: 8px; background: color-mix(in srgb, var(--texto) 8%, transparent); font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-size: 14px; color: var(--suave); }
.compacto .lugar { width: 26px; height: 26px; font-size: 13px; }
.podio-1 { background: linear-gradient(160deg, #ffe07a, #e0a800); color: #2a1d00; }
.podio-2 { background: linear-gradient(160deg, #f1f1f4, #a9aab3); color: #1d1d22; }
.podio-3 { background: linear-gradient(160deg, #f0a774, #b8612a); color: #2b1203; }
.nombre { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 800; font-size: 15px; letter-spacing: -0.01em; }
.compacto .nombre { font-size: 14px; }
.meta { display: block; margin-top: 2px; font-size: 11px; color: var(--suave); }
.compacto .meta { display: none; }
.kills-max { color: #ff6b6b; font-weight: 700; }
.puntos { display: flex; align-items: baseline; gap: 4px; font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-size: 22px; font-variant-numeric: tabular-nums; color: var(--acento); }
.tema-claro .puntos { color: color-mix(in srgb, var(--acento) 85%, black); }
.compacto .puntos { font-size: 18px; }
.etq { font-family: 'Archivo', sans-serif; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--suave); }
.vacio { padding: 18px 10px; text-align: center; font-size: 13px; color: var(--suave); }

.seguimiento { padding: 4px 16px 16px; }
.nombre-grande { font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-style: italic; text-transform: uppercase; font-size: 26px; line-height: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.puesto { font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-size: 30px; color: var(--acento); }
.datos { margin-top: 14px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.datos > div { display: flex; flex-direction: column; align-items: center; padding: 10px 4px; border-radius: 10px; background: var(--fondo-fila); }
.cifra { font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-size: 24px; font-variant-numeric: tabular-nums; }
.cifra.acento { color: var(--acento); }

.pie { display: flex; align-items: center; justify-content: center; gap: 14px; padding: 10px 16px 12px; border-top: 1px solid var(--borde); }
.torneo { display: flex; align-items: center; min-width: 0; }
.torneo img { height: 26px; max-width: 140px; object-fit: contain; }
.torneo-nombre { font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-style: italic; text-transform: uppercase; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px; }
.separador { width: 1px; height: 20px; background: var(--borde); flex-shrink: 0; }
.tema-transparente .separador { background: rgba(255, 255, 255, 0.4); }
.rankit { display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0; font-family: 'Chakra Petch', sans-serif; font-weight: 700; font-style: italic; text-transform: uppercase; font-size: 13px; letter-spacing: 0.02em; }
.rankit svg { width: 18px; height: 18px; }

.fila-move, .fila-enter-active, .fila-leave-active { transition: transform 0.6s cubic-bezier(.2, .8, .2, 1), opacity 0.4s; }
.fila-enter-from, .fila-leave-to { opacity: 0; transform: translateX(-12px); }
.fila-leave-active { position: absolute; }
</style>
