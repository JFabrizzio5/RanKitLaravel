<script setup lang="ts">
/**
 * Menú de la cuenta al estilo Google: un círculo con la inicial y, al
 * abrirlo, nombre, correo, el plan con su cupo y los atajos de la cuenta.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import AvatarUsuario from '@/Components/AvatarUsuario.vue'
import ContadorTickets from '@/Components/ContadorTickets.vue'
import { usePlan, useRecarga } from '@/Composables/usePlan'

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const nombreCorto = computed(() => (user.value?.name ?? '').trim().split(/\s+/)[0] ?? '')

const { plan, cupo, tieneBase, etiqueta, chip, detalle, porVencer, avisoVence } = usePlan()
const { recargando, recargar } = useRecarga()

const abierto = ref(false)
const raiz = ref<HTMLElement | null>(null)
const cerrar = () => { abierto.value = false }
const conEscape = (e: KeyboardEvent) => { if (e.key === 'Escape') cerrar() }
// Sin capa "fixed inset-0": el backdrop-blur del nav la encierra en la barra
// y un clic en la página no cerraría el menú.
const clicAfuera = (e: PointerEvent) => {
  if (abierto.value && raiz.value && !raiz.value.contains(e.target as Node)) cerrar()
}
onMounted(() => {
  document.addEventListener('keydown', conEscape)
  document.addEventListener('pointerdown', clicAfuera)
})
onUnmounted(() => {
  document.removeEventListener('keydown', conEscape)
  document.removeEventListener('pointerdown', clicAfuera)
})

const tonoChip = computed(() => {
  if (!plan.value?.activo || porVencer.value) return 'border-amber-500/50 text-amber-600 dark:text-amber-400'
  if (plan.value.ilimitado) return 'border-[var(--rankit-neon)] text-[var(--rankit-neon)]'
  return cupo.value.restantes === 0
    ? 'border-red-500/50 text-red-600 dark:text-red-400'
    : 'border-gray-300 text-gray-600 dark:border-white/20 dark:text-gray-300'
})
</script>

<template>
  <div ref="raiz" class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-full p-1 transition hover:bg-gray-200/70 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--rankit-neon)]"
      :aria-expanded="abierto"
      aria-haspopup="true"
      :title="`Cuenta de Rankit\n${user?.name ?? ''}\n${user?.email ?? ''}`"
      @click="abierto = !abierto"
    >
      <span v-if="chip" class="hidden sm:inline-flex items-center gap-1 ps-2.5 pe-2.5 py-1 text-[11px] font-bold uppercase tracking-wider rounded-full border" :class="tonoChip">
        <i v-if="tieneBase" class="ph-fill ph-ticket text-sm"></i>{{ chip }}
      </span>
      <AvatarUsuario :nombre="user?.name" :semilla="user?.email" tamano="md" />
    </button>

    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="abierto"
        class="absolute right-0 z-50 mt-3 w-[22rem] max-w-[calc(100vw-2rem)] origin-top-right rounded-3xl border border-gray-200 bg-gray-100 p-2 shadow-2xl dark:border-white/10 dark:bg-[#151515]"
        role="menu"
      >
        <!-- Encabezado: correo y cerrar -->
        <div class="relative px-10 pt-2 text-center">
          <p class="truncate text-sm text-gray-600 dark:text-gray-300">{{ user?.email }}</p>
          <button type="button" class="absolute right-1 top-0 flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-white/10" aria-label="Cerrar" @click="cerrar">
            <i class="ph ph-x text-lg"></i>
          </button>
        </div>

        <div class="flex flex-col items-center px-4 pb-4 pt-5 text-center">
          <AvatarUsuario :nombre="user?.name" :semilla="user?.email" tamano="xl" />
          <p class="mt-3 text-xl text-gray-900 dark:text-white">¡Hola, {{ nombreCorto }}!</p>
          <Link :href="route('profile.edit')" class="mt-3 rounded-full border border-gray-400 px-5 py-2 text-sm font-medium text-[var(--rankit-neon)] transition hover:bg-[var(--rankit-neon)]/10 dark:border-gray-500" @click="cerrar">
            Administrar tu cuenta
          </Link>
        </div>

        <!-- Plan -->
        <div v-if="plan" class="rounded-2xl bg-white p-4 dark:bg-[#0a0a0a]">
          <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Tu plan</p>
            <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider" :class="plan.exento ? 'border-emerald-500/50 text-emerald-600 dark:text-emerald-400' : tonoChip">
              {{ etiqueta }}
            </span>
          </div>
          <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ detalle }}</p>

          <ContadorTickets v-if="tieneBase" compacto class="mt-3" />
          <p v-if="avisoVence" class="mt-3 flex gap-2 rounded-xl bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300">
            <i class="ph-fill ph-warning mt-0.5"></i><span>{{ avisoVence }}</span>
          </p>

          <div v-if="!plan.exento" class="mt-4 flex flex-wrap gap-2">
            <template v-if="!plan.activo">
              <Link :href="route('planes.index')" class="flex-1 rounded-full bg-[var(--rankit-neon)] px-4 py-2 text-center text-sm font-bold text-white hover:brightness-110" @click="cerrar">
                Elegir plan
              </Link>
            </template>
            <template v-else-if="tieneBase">
              <button type="button" :disabled="recargando" class="flex-1 whitespace-nowrap rounded-full bg-[var(--rankit-neon)] px-2 py-2 text-center text-[13px] font-bold text-white hover:brightness-110 disabled:opacity-60" @click="recargar">
                <template v-if="recargando">Abriendo pago…</template>
                <template v-else>Recargar +{{ plan.recarga?.tickets }} · ${{ plan.recarga?.precio }}</template>
              </button>
              <Link :href="route('planes.index', { quiero: 'pro' })" class="flex-1 rounded-full border border-gray-300 px-3 py-2 text-center text-sm font-medium hover:border-[var(--rankit-neon)] dark:border-white/20" @click="cerrar">
                Subir a Pro
              </Link>
            </template>
            <Link v-else :href="route('planes.index')" class="flex-1 rounded-full border border-gray-300 px-4 py-2 text-center text-sm font-medium hover:border-[var(--rankit-neon)] dark:border-white/20" @click="cerrar">
              Administrar plan
            </Link>
          </div>
        </div>

        <!-- Atajos -->
        <div class="mt-1 overflow-hidden rounded-2xl bg-white dark:bg-[#0a0a0a]">
          <Link :href="route('dashboard')" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-gray-100 dark:hover:bg-white/5 sm:hidden" @click="cerrar">
            <i class="ph ph-squares-four text-lg text-gray-500"></i> Dashboard
          </Link>
          <Link :href="route('mis-torneos')" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-gray-100 dark:hover:bg-white/5" @click="cerrar">
            <i class="ph ph-trophy text-lg text-gray-500"></i> Mis torneos
          </Link>
          <Link :href="route('profile.edit')" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-gray-100 dark:hover:bg-white/5" @click="cerrar">
            <i class="ph ph-user-circle text-lg text-gray-500"></i> Mi cuenta
          </Link>
          <Link v-if="!plan?.exento" :href="route('planes.index')" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-gray-100 dark:hover:bg-white/5" @click="cerrar">
            <i class="ph ph-credit-card text-lg text-gray-500"></i> Planes y pagos
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 px-5 py-3 text-left text-sm hover:bg-gray-100 dark:hover:bg-white/5" @click="cerrar">
            <i class="ph ph-sign-out text-lg text-gray-500"></i> Cerrar sesión
          </Link>
        </div>
      </div>
    </Transition>
  </div>
</template>
