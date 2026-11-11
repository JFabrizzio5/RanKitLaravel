<script setup lang="ts">
/**
 * Pantalla de carga: el logo de Rankit se arma, se vuelve control (JUEGA),
 * balón (ANOTA) y trofeo (GANA), regresa a ser la R y la cortina sube.
 * Una vez por sesión; un clic la acelera. El hero de la página espera a que
 * termine (esperarIntro en animaciones.ts).
 */
import { onMounted, ref } from 'vue'
import { gsap, nuevaIntro, sinMovimiento } from './animaciones'
import { FORMAS, morfar, PALABRA, type NombreForma } from './logoFormas'

const terminar = nuevaIntro()
const cortina = ref<HTMLElement | null>(null)
const mostrar = ref(true)
const contador = ref(0)
const palabra = ref('')

onMounted(() => {
  let vista = false
  try { vista = sessionStorage.getItem('rk-intro') === '1'; sessionStorage.setItem('rk-intro', '1') } catch { /* sin storage */ }
  if (vista || sinMovimiento() || !cortina.value) {
    mostrar.value = false
    terminar()
    return
  }

  const piezas = [...cortina.value.querySelectorAll('path')] as SVGPathElement[]
  const cuenta = { v: 0 }
  const tl = gsap.timeline({ onComplete: () => { mostrar.value = false } })
    .from(piezas, { xPercent: -60, skewX: -20, autoAlpha: 0, duration: 0.6, ease: 'expo.out', stagger: 0.08 })
  ;(['control', 'balon', 'trofeo', 'logo'] as NombreForma[]).forEach((forma) => {
    tl.call(() => { palabra.value = forma === 'logo' ? '' : PALABRA[forma] }, [], '+=0.12')
    morfar(tl, piezas, forma, '<', 0.5)
  })
  tl.to(cuenta, { v: 100, duration: tl.duration(), ease: 'power1.inOut', onUpdate: () => { contador.value = Math.round(cuenta.v) } }, 0)
    .to(cortina.value, { clipPath: 'inset(0% 0% 100% 0%)', duration: 0.9, ease: 'expo.inOut', onStart: terminar }, '+=0.1')
  cortina.value.addEventListener('click', () => tl.timeScale(4), { once: true })
})
</script>

<template>
  <div v-if="mostrar" ref="cortina" class="fixed inset-0 z-[80] flex cursor-pointer flex-col items-center justify-center gap-6 bg-[#050505] text-white" style="clip-path: inset(0% 0% 0% 0%)" aria-hidden="true">
    <svg class="h-28 w-28 overflow-visible sm:h-40 sm:w-40" viewBox="0 0 100 100">
      <path v-for="(p, i) in FORMAS.logo" :key="i" :d="p.d" :fill="p.color" />
    </svg>
    <p class="h-[1em] text-4xl font-bold uppercase italic sm:text-5xl" style="font-family: 'Chakra Petch', sans-serif">{{ palabra }}</p>
    <p class="text-xs font-semibold uppercase tabular-nums tracking-[0.12em] text-white/50">{{ String(contador).padStart(3, '0') }}</p>
  </div>
</template>
