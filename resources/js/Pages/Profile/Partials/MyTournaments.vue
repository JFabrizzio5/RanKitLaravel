<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    tournaments: any[];
}>();

const copyCode = (code: string) => {
    navigator.clipboard.writeText(code);
    alert('Código copiado: ' + code);
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Mis Torneos
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Torneos en los que te has inscrito y tus códigos de acceso.
            </p>
        </header>

        <div class="mt-6 space-y-4">
            <div v-if="tournaments.length === 0" class="text-sm text-gray-500 italic">
                No te has unido a ningún torneo aún.
            </div>

            <div v-for="t in tournaments" :key="t.id" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                     <div class="font-bold text-lg text-gray-900 dark:text-white">{{ t.name }}</div>
                     <div class="text-xs text-gray-500">Unido el: {{ new Date(t.created_at).toLocaleDateString() }}</div>
                     <div v-if="t.has_paid" class="mt-1 text-xs font-bold text-green-600 uppercase flex items-center gap-1">
                        <i class="ph-bold ph-check-circle"></i> Entrada Pagada
                     </div>
                     <div v-else class="mt-1 text-xs font-bold text-red-500 uppercase flex items-center gap-1">
                        <i class="ph-bold ph-warning"></i> Pago Pendiente
                     </div>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div v-if="t.has_paid || t.access_code" class="flex-1 md:flex-none">
                         <button @click="copyCode(t.access_code)" class="w-full md:w-auto px-4 py-2 bg-gray-200 dark:bg-black text-xs font-bold uppercase rounded hover:bg-gray-300 dark:hover:bg-gray-800 transition flex items-center justify-center gap-2" title="Copiar Código">
                            <span>Código:</span>
                            <span class="font-mono text-base">{{ t.access_code }}</span>
                            <i class="ph-bold ph-copy"></i>
                         </button>
                    </div>

                    <Link :href="route('public.tournament.show', t.slug || t.id)" class="px-4 py-2 bg-[var(--rankit-neon)] text-white text-xs font-bold uppercase rounded hover:opacity-80 transition">
                        Ver Torneo
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
