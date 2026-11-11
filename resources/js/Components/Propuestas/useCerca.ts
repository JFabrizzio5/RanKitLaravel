import { onMounted, onUnmounted, ref, type Ref } from 'vue'

/**
 * true cuando el elemento ya está cerca de la pantalla (y se queda en true).
 * Para pedir imágenes y videos de abajo solo cuando hacen falta: el
 * loading="lazy" del navegador los pedía al cargar porque, antes de que se
 * arme el pin del recorrido, quedan a menos de una pantalla.
 */
export function useCerca(el: Ref<HTMLElement | null>, margen = '800px 0px') {
  const cerca = ref(false)
  let obs: IntersectionObserver | undefined
  onMounted(() => {
    if (!el.value) return
    obs = new IntersectionObserver(([e]) => {
      if (!e.isIntersecting) return
      cerca.value = true
      obs?.disconnect()
    }, { rootMargin: margen })
    obs.observe(el.value)
  })
  onUnmounted(() => obs?.disconnect())
  return cerca
}
