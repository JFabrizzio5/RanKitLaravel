<script setup lang="ts">
/**
 * Recuadro flotante cuando se van a perder tickets sin usar o termina un
 * Gestor Pro que no se renueva. Se puede cerrar; vuelve a salir si cambia la
 * fecha (otra recarga) o al día siguiente.
 */
import { Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { usePlan } from '@/Composables/usePlan'

const { porVencer, avisoVence } = usePlan()

// Conveniencia por navegador: si no hay localStorage, simplemente no se recuerda.
const llave = computed(() => porVencer.value ? `rankit:aviso-vence:${porVencer.value.vence}:${new Date().toDateString()}` : '')
const cerrado = ref(false)
try { cerrado.value = !!llave.value && localStorage.getItem(llave.value) === '1' } catch { /* sin storage */ }

function cerrar() {
  cerrado.value = true
  try { localStorage.setItem(llave.value, '1') } catch { /* sin storage */ }
}
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
  >
    <div
      v-if="porVencer && !cerrado"
      role="status"
      class="fixed bottom-4 left-4 right-4 z-40 mx-auto max-w-md rounded-2xl border border-amber-500/40 bg-white p-4 shadow-2xl sm:left-auto sm:right-6 sm:mx-0 dark:bg-[#151515]"
    >
      <div class="flex items-start gap-3">
        <i class="ph-fill ph-warning text-2xl text-amber-500"></i>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold text-gray-900 dark:text-white">{{ porVencer.tickets === null ? 'Tu plan está por terminar' : 'Tus tickets están por vencer' }}</p>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ avisoVence }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <Link v-if="porVencer.tickets !== null" :href="route('game.selector')" class="rounded-full bg-[var(--rankit-neon)] px-4 py-1.5 text-xs font-bold text-white hover:brightness-110">
              Crear torneo
            </Link>
            <Link v-else :href="route('planes.index', { quiero: 'pro' })" class="rounded-full bg-[var(--rankit-neon)] px-4 py-1.5 text-xs font-bold text-white hover:brightness-110">
              Seguir con Gestor Pro
            </Link>
          </div>
        </div>
        <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10" aria-label="Cerrar aviso" @click="cerrar">
          <i class="ph ph-x"></i>
        </button>
      </div>
    </div>
  </Transition>
</template>
