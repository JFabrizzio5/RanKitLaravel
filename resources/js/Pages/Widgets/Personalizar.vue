<script setup lang="ts">
/**
 * Armar el widget de OBS: el organizador elige tabla o seguimiento, tema,
 * color, filas, título y logos; ve cómo queda sobre un stream de ejemplo con
 * los datos reales del torneo y copia el link para la fuente de navegador.
 * No guarda nada: todo va en el link (configWidget.ts).
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import TablaWidget from '@/Components/Widget/TablaWidget.vue'
import { ACENTO_RANKIT, armarQuery, configBase, normalizarFilas, tamanoSugerido, type FilaWidget, type Tema } from '@/Components/Widget/configWidget'
import { fondos } from '@/Components/Propuestas/medios'

const props = defineProps<{ tournamentId: number; torneo: { nombre: string; logo: string | null } }>()

const config = reactive(configBase())
const filas = ref<FilaWidget[]>([])
const cargando = ref(true)
const copiado = ref(false)

const temas: { valor: Tema; texto: string }[] = [
  { valor: 'oscuro', texto: 'Oscuro' },
  { valor: 'cristal', texto: 'Cristal' },
  { valor: 'claro', texto: 'Claro' },
  { valor: 'transparente', texto: 'Sin fondo' },
]
const colores = [ACENTO_RANKIT, '00e5ff', '22c55e', 'facc15', 'ff3b6b', 'ff7a00', 'ffffff']

const link = computed(() => `${window.location.origin}/widget/obs/global/${props.tournamentId}?${armarQuery(config)}`)
const tamano = computed(() => tamanoSugerido(config))

async function cargar() {
  cargando.value = true
  try {
    // @ts-ignore: route() global de Ziggy
    const r = await axios.get(route('api.widget.stats', props.tournamentId), {
      params: { type: config.tipo, sort: config.orden, limit: config.buscar ? 1 : config.filas, search: config.buscar || undefined },
    })
    filas.value = normalizarFilas(r.data)
  } catch {
    filas.value = []
  } finally {
    cargando.value = false
  }
}
let espera: ReturnType<typeof setTimeout> | undefined
watch(() => [config.tipo, config.orden, config.filas, config.buscar], () => {
  clearTimeout(espera)
  espera = setTimeout(cargar, 300)
})
onMounted(cargar)

async function copiar() {
  try { await navigator.clipboard.writeText(link.value) } catch { /* sin portapapeles: el link queda a la vista */ }
  copiado.value = true
  setTimeout(() => (copiado.value = false), 2000)
}
</script>

<template>
  <Head :title="`Widget OBS · ${torneo.nombre}`" />
  <div class="min-h-screen bg-[#070709] text-white antialiased" style="font-family: Archivo, ui-sans-serif, system-ui, sans-serif">
    <header class="flex items-center justify-between border-b border-white/10 px-5 py-4 sm:px-8">
      <Link href="/" class="flex items-center gap-2" aria-label="Rankit, inicio">
        <svg class="h-6 w-6" viewBox="0 0 100 100" aria-hidden="true">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff" />
        </svg>
        <span class="text-xl font-bold uppercase italic" style="font-family: 'Chakra Petch', sans-serif">Rankit</span>
      </Link>
      <p class="hidden text-xs font-semibold uppercase tracking-[0.14em] text-white/50 sm:block">Widget para OBS · {{ torneo.nombre }}</p>
    </header>

    <div class="mx-auto grid max-w-[1400px] gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[380px_1fr]">
      <!-- Controles -->
      <section class="space-y-7" aria-label="Opciones del widget">
        <div>
          <h1 class="text-3xl font-bold uppercase italic leading-none" style="font-family: 'Chakra Petch', sans-serif">Personaliza tu widget</h1>
          <p class="mt-2 text-sm text-white/55">Ajusta y copia el link. En OBS: Fuente → Navegador → pega el link.</p>
        </div>

        <fieldset>
          <legend class="etiqueta">Qué mostrar</legend>
          <div class="grupo">
            <button type="button" :class="{ si: !config.buscar && config.tipo === 'players' }" @click="config.buscar = ''; config.tipo = 'players'">Jugadores</button>
            <button type="button" :class="{ si: !config.buscar && config.tipo === 'teams' }" @click="config.buscar = ''; config.tipo = 'teams'">Equipos</button>
          </div>
          <label class="mt-3 block text-sm text-white/60">
            Seguir a un jugador (opcional)
            <input v-model.trim="config.buscar" type="text" placeholder="Nombre en el juego" class="campo mt-1.5" />
          </label>
        </fieldset>

        <fieldset>
          <legend class="etiqueta">Ordenar por</legend>
          <div class="grupo">
            <button type="button" :class="{ si: config.orden === 'points' }" @click="config.orden = 'points'">Puntos</button>
            <button type="button" :class="{ si: config.orden === 'kills' }" @click="config.orden = 'kills'">Kills</button>
          </div>
        </fieldset>

        <fieldset v-if="!config.buscar">
          <legend class="etiqueta">Filas <span class="text-white">{{ config.filas }}</span></legend>
          <input v-model.number="config.filas" type="range" min="3" max="15" class="w-full accent-[#bf00ff]" aria-label="Número de filas" />
        </fieldset>

        <fieldset>
          <legend class="etiqueta">Tema</legend>
          <div class="grid grid-cols-4 gap-2">
            <button v-for="t in temas" :key="t.valor" type="button" class="tema" :class="[{ si: config.tema === t.valor }, `muestra-${t.valor}`]" @click="config.tema = t.valor">{{ t.texto }}</button>
          </div>
        </fieldset>

        <fieldset>
          <legend class="etiqueta">Color</legend>
          <div class="flex flex-wrap items-center gap-2">
            <button v-for="c in colores" :key="c" type="button" class="h-9 w-9 rounded-full border-2 transition" :class="config.acento === c ? 'scale-110 border-white' : 'border-transparent'" :style="{ background: '#' + c }" :aria-label="`Color #${c}`" @click="config.acento = c"></button>
            <label class="relative h-9 w-9 cursor-pointer overflow-hidden rounded-full border-2 border-dashed border-white/40" title="Otro color">
              <input type="color" :value="'#' + config.acento" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" @input="config.acento = ($event.target as HTMLInputElement).value.slice(1)" />
              <span class="grid h-full place-items-center text-lg text-white/70">+</span>
            </label>
          </div>
        </fieldset>

        <fieldset>
          <legend class="etiqueta">Título</legend>
          <input v-model="config.titulo" type="text" maxlength="40" :placeholder="config.tipo === 'teams' ? 'Top equipos' : 'Top jugadores'" class="campo" />
        </fieldset>

        <fieldset class="space-y-3">
          <legend class="etiqueta">Detalles</legend>
          <label class="interruptor"><input v-model="config.logos" type="checkbox" /> Logos abajo (tu torneo + rankit.pro)</label>
          <label class="interruptor"><input v-model="config.compacto" type="checkbox" /> Compacto (ocupa menos pantalla)</label>
          <p v-if="!torneo.logo" class="text-xs text-white/45">Tu torneo aún no tiene logo: abajo saldrá su nombre. Súbelo desde tu panel, en la pestaña Widget.</p>
        </fieldset>
      </section>

      <!-- Vista previa sobre un stream de ejemplo -->
      <section class="space-y-4" aria-label="Vista previa">
        <div class="relative overflow-hidden rounded-2xl border border-white/10" style="aspect-ratio: 16 / 9">
          <img :src="fondos.gamer.grande" alt="" class="absolute inset-0 h-full w-full object-cover opacity-80" />
          <div class="absolute left-[3%] top-[5%] w-[min(46%,420px)] origin-top-left">
            <TablaWidget :filas="filas" :config="config" :torneo="torneo" :cargando="cargando" />
          </div>
          <span class="absolute bottom-3 right-3 rounded-md bg-black/70 px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-white/70">Vista previa con tus datos</span>
        </div>

        <div class="rounded-2xl border border-white/10 bg-white/[.03] p-5">
          <p class="etiqueta">Link para OBS</p>
          <div class="mt-2 flex flex-col gap-3 sm:flex-row">
            <input :value="link" readonly class="campo flex-1 font-mono !text-xs" aria-label="Link del widget" @focus="($event.target as HTMLInputElement).select()" />
            <button type="button" class="shrink-0 rounded-lg bg-[#bf00ff] px-5 py-3 text-sm font-bold uppercase tracking-wider transition hover:bg-white hover:text-black" @click="copiar">{{ copiado ? '¡Copiado!' : 'Copiar link' }}</button>
          </div>
          <p class="mt-3 text-sm text-white/55">Tamaño en OBS: <span class="text-white">{{ tamano.ancho }} × {{ tamano.alto }}</span> px. Se actualiza solo cada 30 segundos.</p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@1,700&family=Archivo:wght@500;600;700;800&display=swap');
.etiqueta { margin-bottom: 8px; font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
.grupo { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 4px; border-radius: 12px; background: rgba(255, 255, 255, 0.05); }
.grupo button { padding: 9px; border-radius: 9px; font-size: 14px; font-weight: 700; color: rgba(255, 255, 255, 0.6); transition: background 0.2s, color 0.2s; }
.grupo button.si { background: #bf00ff; color: #fff; }
.campo { width: 100%; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.12); background: rgba(255, 255, 255, 0.04); padding: 10px 12px; font-size: 14px; color: #fff; outline: none; }
.campo:focus { border-color: #bf00ff; }
.tema { padding: 12px 4px; border-radius: 10px; font-size: 12px; font-weight: 700; border: 2px solid transparent; transition: border-color 0.2s; }
.tema.si { border-color: #bf00ff; }
.muestra-oscuro { background: #0e0e12; }
.muestra-cristal { background: linear-gradient(135deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.04)); }
.muestra-claro { background: #f4f4f6; color: #111; }
.muestra-transparente { background: repeating-conic-gradient(#2a2a30 0 25%, #1a1a1f 0 50%) 0 0 / 14px 14px; }
.interruptor { display: flex; align-items: center; gap: 10px; font-size: 14px; color: rgba(255, 255, 255, 0.8); }
.interruptor input { width: 18px; height: 18px; accent-color: #bf00ff; }
</style>
