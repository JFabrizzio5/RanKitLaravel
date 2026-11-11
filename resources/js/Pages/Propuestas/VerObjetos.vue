<script setup lang="ts">
/**
 * Solo local: los tres objetos 3D del recorrido de la K lado a lado, para
 * revisarlos (y capturarlos sin ventana). window.__girar(v) los pone a v vueltas.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import type { NombreObjeto, Objetos3D } from '@/Components/Propuestas/objetos3d'

const nombres: NombreObjeto[] = ['control', 'balon', 'trofeo']
const lienzos = ref<HTMLCanvasElement[]>([])
let motores: Objetos3D[] = []

onMounted(async () => {
  const { crearObjetos } = await import('@/Components/Propuestas/objetos3d')
  motores = await Promise.all(lienzos.value.map(async (c, i) => {
    const m = await crearObjetos(c)
    m.ver(nombres[i])
    return m
  }))
  const w = window as unknown as { __girar: (v: number) => void; __listo: boolean }
  w.__girar = (v) => motores.forEach((m) => m.girar(v))
  w.__listo = true
  if (!new URLSearchParams(location.search).has('quieto')) motores.forEach((m) => m.vivo(true))
})
onUnmounted(() => motores.forEach((m) => m.destruir()))
</script>

<template>
  <div class="grid min-h-screen grid-cols-3 items-center gap-4 p-6" style="background: radial-gradient(circle at 50% 40%, #2a1240, #07060a 70%)">
    <canvas v-for="n in nombres" :key="n" ref="lienzos" class="aspect-square w-full"></canvas>
  </div>
</template>
