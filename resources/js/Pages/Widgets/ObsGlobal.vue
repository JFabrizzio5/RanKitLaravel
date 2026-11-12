<script setup lang="ts">
/**
 * Fuente de navegador para OBS: la tabla del torneo con fondo transparente.
 * El estilo sale del link (configWidget.ts); los links viejos siguen igual.
 * Se actualiza cada 30 s.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import TablaWidget from '@/Components/Widget/TablaWidget.vue'
import { leerConfig, normalizarFilas, type FilaWidget } from '@/Components/Widget/configWidget'

const props = withDefaults(defineProps<{
  tournamentId: number
  defaultSearch?: string
  torneo?: { nombre: string; logo: string | null } | null
}>(), { defaultSearch: '', torneo: null })

const config = leerConfig(new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search), props.defaultSearch)
const filas = ref<FilaWidget[]>([])
const cargando = ref(true)
let fallos = 0
let reloj: ReturnType<typeof setInterval> | undefined

async function actualizar() {
  if (fallos > 3) return
  try {
    // @ts-ignore: route() global de Ziggy
    const r = await axios.get(route('api.widget.stats', props.tournamentId), {
      params: { type: config.tipo, sort: config.orden, mode: config.modo, limit: config.filas, search: config.buscar || undefined },
    })
    filas.value = normalizarFilas(r.data)
    fallos = 0
  } catch {
    fallos++
  } finally {
    cargando.value = false
  }
}

onMounted(() => {
  document.documentElement.style.background = 'transparent'
  document.body.style.background = 'transparent'
  actualizar()
  reloj = setInterval(actualizar, 30000)
})
onUnmounted(() => clearInterval(reloj))
</script>

<template>
  <div class="min-h-screen p-5 antialiased select-none">
    <TablaWidget :filas="filas" :config="config" :torneo="torneo" :cargando="cargando" class="entrada" />
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@1,700&family=Archivo:wght@500;600;700;800&display=swap');
html, body { background: transparent !important; overflow: hidden; }
.entrada { animation: entrada 0.5s cubic-bezier(.2, .8, .2, 1) both; }
@keyframes entrada { from { transform: translateX(-16px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>
