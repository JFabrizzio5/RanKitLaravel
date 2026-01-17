<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

// Props
defineProps<{
    equipos: Array<{ nombre: string; puntos: number; kills: number }>;
}>();
</script>

<template>
    <Head title="Panel de Torneo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-3xl font-black leading-tight text-black dark:text-white uppercase font-display">
                        Torneo <span class="text-neon">Ficticio 2024</span>
                    </h2>
                    <p class="text-xs font-mono text-gray-500 uppercase tracking-widest">Panel de Control de Operaciones</p>
                </div>
                
                <!-- Botón directo al Widget -->
                <a :href="route('tournament.widget')" target="_blank" class="btn-skew px-6 py-2 text-sm font-bold uppercase tracking-wider">
                    <span class="btn-content flex items-center gap-2">
                        <i class="ph-bold ph-broadcast"></i> Abrir Widget OBS
                    </span>
                </a>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                
                <!-- Sección de bienvenida estilo Gamer -->
                <div class="brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
                    <div class="relative z-10">
                        <h3 class="text-2xl font-bold mb-4 font-display uppercase text-black dark:text-white">Bienvenido, <span class="text-neon">Organizador</span></h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-2xl">
                            Estás en el área restringida de administración. Controla los puntajes, gestiona brackets y supervisa el estado del servidor para la transmisión en vivo.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tarjeta de Acción Rápida -->
                            <div class="p-4 border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-white/5 hover:border-neon transition group">
                                <h4 class="font-bold text-lg mb-2 text-black dark:text-white uppercase flex items-center gap-2">
                                    <i class="ph-fill ph-video-camera text-neon"></i> Transmisión
                                </h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">El widget se actualiza automáticamente con los datos de esta tabla.</p>
                                <a :href="route('tournament.widget')" target="_blank" class="text-sm font-bold text-neon hover:underline uppercase tracking-wider">
                                    Ver vista previa &rarr;
                                </a>
                            </div>

                            <!-- Tarjeta de Estado -->
                            <div class="p-4 border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-white/5 hover:border-green-500 transition">
                                <h4 class="font-bold text-lg mb-2 text-black dark:text-white uppercase flex items-center gap-2">
                                    <i class="ph-fill ph-cpu text-green-500"></i> Estado del Servidor
                                </h4>
                                <div class="flex items-center">
                                    <span class="h-2 w-2 bg-green-500 rounded-full mr-2 animate-pulse shadow-[0_0_10px_#22c55e]"></span>
                                    <span class="text-sm text-green-600 dark:text-green-400 font-bold uppercase">En Línea - Estable</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Equipos (Vista Previa) -->
                <div class="brutal-card bg-white dark:bg-[#0a0a0a] overflow-hidden">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                        <h3 class="text-xl font-bold text-black dark:text-white uppercase font-display">Clasificación en Vivo</h3>
                        <button class="text-xs font-bold uppercase text-gray-500 hover:text-neon transition flex items-center gap-1">
                            <i class="ph-bold ph-arrows-clockwise"></i> Actualizar
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-gray-800">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest">Equipo</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Kills</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-right">Puntos Totales</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-widest text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="equipo in equipos" :key="equipo.nombre" class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors group">
                                    <td class="px-6 py-4 font-bold text-black dark:text-white font-display uppercase tracking-wide">
                                        {{ equipo.nombre }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-gray-600 dark:text-gray-300">
                                        {{ equipo.kills }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-xl text-neon font-display">
                                        {{ equipo.puntos }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button class="text-[10px] font-bold uppercase tracking-wider text-gray-400 hover:text-white hover:bg-black dark:hover:bg-white dark:hover:text-black border border-gray-300 dark:border-gray-700 px-3 py-1 transition">
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>