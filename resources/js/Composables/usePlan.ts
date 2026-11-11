import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

/**
 * Plan de organizador del usuario en sesión (prop compartido `plan`, ver
 * PlanOrganizador::resumen) y los textos que se repiten en el menú de la
 * cuenta, el perfil y /planes.
 */

export type PeriodoPlan = {
  id: number
  plan: 'base' | 'pro'
  proveedor: string
  limite: number | null
  usados: number
  inicio: string | null
  vence: string
  renueva: boolean
  suscripcion: string | null
}

export type ResumenPlan = {
  exento: boolean
  activo: boolean
  // Puede entrar al panel: con plan vigente o en la gracia de sus torneos.
  administra: boolean
  gracia_hasta: string | null
  // Un pase Base: cuántos tickets da y cuánto cuesta (null para exentos).
  recarga: { tickets: number; precio: number } | null
  // Tickets sin usar (o Gestor Pro sin renovación) que vencen pronto.
  por_vencer: { tickets: number | null; vence: string; dias: number } | null
  ilimitado: boolean
  plan: 'base' | 'pro' | null
  torneos_restantes: number | null
  vence: string | null
  renueva: boolean
  periodos: PeriodoPlan[]
}

export function fechaLarga(iso: string | null | undefined) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'long' })
}

export function usePlan() {
  const page = usePage()
  const plan = computed(() => (page.props.plan as ResumenPlan | null) ?? null)

  // Suscripción de Stripe vigente: la única que se cancela o se reanuda.
  const suscripcion = computed(() => plan.value?.periodos.find((p) => p.suscripcion) ?? null)

  // Pases Base vigentes sumados: si compró dos, son 4 torneos.
  const cupo = computed(() => {
    const pases = plan.value?.periodos.filter((p) => p.limite !== null) ?? []
    const total = pases.reduce((s, p) => s + (p.limite ?? 0), 0)
    const usados = pases.reduce((s, p) => s + Math.min(p.usados, p.limite ?? 0), 0)
    return { total, usados, restantes: Math.max(0, total - usados) }
  })

  // Tickets de torneo: 1 ticket = 1 torneo. Vence el primero que se acabe.
  const tickets = computed(() => cupo.value.restantes)
  const proximoVence = computed(() => {
    const conTickets = (plan.value?.periodos ?? [])
      .filter((p) => p.limite !== null && p.usados < p.limite)
      .map((p) => p.vence)
      .sort()
    return conTickets[0] ?? null
  })

  const tieneBase = computed(() => !!plan.value?.activo && !plan.value.ilimitado && !plan.value.exento)

  const etiqueta = computed(() => {
    const p = plan.value
    if (!p) return ''
    if (p.exento) return 'Acceso ilimitado'
    if (!p.activo) return p.gracia_hasta ? 'Plan vencido' : 'Sin plan'
    return p.ilimitado ? 'Gestor Pro' : 'Base'
  })

  // Lo que va en el chip junto al avatar.
  const chip = computed(() => {
    const p = plan.value
    if (!p || p.exento) return ''
    if (!p.activo) return p.gracia_hasta ? 'Vencido' : 'Sin plan'
    if (p.ilimitado) return 'Pro'
    return `${tickets.value} ${tickets.value === 1 ? 'ticket' : 'tickets'}`
  })

  const detalle = computed(() => {
    const p = plan.value
    if (!p) return ''
    if (p.exento) return 'Tu cuenta crea torneos sin límite y sin pagar plan.'
    if (!p.activo && p.gracia_hasta) return `Tu plan venció. Puedes seguir administrando tus torneos hasta el ${fechaLarga(p.gracia_hasta)}; para crear otro necesitas un plan.`
    if (!p.activo) return 'Necesitas un plan para crear y administrar torneos.'
    if (p.ilimitado) {
      return p.renueva
        ? `Torneos ilimitados. Se renueva el ${fechaLarga(p.vence)}.`
        : `Torneos ilimitados hasta el ${fechaLarga(p.vence)}.`
    }
    const n = tickets.value
    if (n === 0) return 'Ya usaste tus tickets. Recarga para crear otro torneo.'
    return `Cada ticket es un torneo. ${n === 1 ? 'Vence' : 'El primero vence'} el ${fechaLarga(proximoVence.value)}.`
  })

  const porVencer = computed(() => plan.value?.por_vencer ?? null)

  const avisoVence = computed(() => {
    const v = porVencer.value
    if (!v) return ''
    const cuando = v.dias <= 0 ? 'hoy' : v.dias === 1 ? 'mañana' : `en ${v.dias} días`
    if (v.tickets === null) return `Tu Gestor Pro termina ${cuando} (${fechaLarga(v.vence)}) y no se renueva.`
    return v.tickets === 1
      ? `1 ticket sin usar vence ${cuando} (${fechaLarga(v.vence)}). Úsalo o se pierde.`
      : `${v.tickets} tickets sin usar vencen ${cuando} (${fechaLarga(v.vence)}). Úsalos o se pierden.`
  })

  return { plan, suscripcion, cupo, tickets, proximoVence, tieneBase, etiqueta, chip, detalle, porVencer, avisoVence }
}

/**
 * "Recargar": compra otro pase Base directo en Stripe desde donde esté
 * (menú, perfil, panel). Si algo falla, el backend lo explica en /planes.
 */
export function useRecarga() {
  const recargando = ref(false)

  function recargar() {
    if (recargando.value) return
    recargando.value = true
    router.post(route('planes.pagar', { plan: 'base', pasarela: 'stripe' }), {}, {
      onFinish: () => { recargando.value = false },
    })
  }

  return { recargando, recargar }
}

/** Inicial del nombre, como el avatar de Google. */
export function inicial(nombre: string | null | undefined) {
  return (nombre ?? '').trim().charAt(0).toLocaleUpperCase('es-MX') || '?'
}

// Colores fijos por cuenta (mismo correo, mismo color), todos con texto blanco legible.
const COLORES = ['#7c3aed', '#c026d3', '#2563eb', '#0891b2', '#059669', '#d97706', '#dc2626', '#4f46e5']

export function colorAvatar(semilla: string | null | undefined) {
  let h = 0
  for (const c of semilla ?? '') h = (h * 31 + c.charCodeAt(0)) >>> 0
  return COLORES[h % COLORES.length]
}
