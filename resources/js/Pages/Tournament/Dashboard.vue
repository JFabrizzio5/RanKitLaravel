<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps<{
    equipos: Array<{ nombre: string; puntos: number; kills: number }>;
}>();
</script>

<template>
    <Head title="Panel de Torneo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold leading-tight text-red-600 dark:text-red-400 uppercase tracking-wider">
                    Panel de Control - Torneo Ficticio 2024
                </h2>
                <!-- Botón directo al Widget -->
                <a :href="route('tournament.widget')" target="_blank">
                    <PrimaryButton class="bg-purple-600 hover:bg-purple-700">
                        Abrir Widget OBS
                    </PrimaryButton>
                </a>
            </div>
        </template>

        <div class="py-12 bg-gray-900 min-h-screen text-white">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                
                <!-- Sección de bienvenida estilo Gamer -->
                <div class="bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-6 border border-gray-700">
                    <div class="p-6">
                        <h3 class="text-2xl font-bold mb-4">Bienvenido, Organizador</h3>
                        <p class="text-gray-400 mb-6">
                            Estás en el área restringida de administración del torneo. Desde aquí puedes controlar los puntajes que se verán en la transmisión en vivo.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tarjeta de Acción Rápida -->
                            <div class="bg-gray-700 p-4 rounded-lg border-l-4 border-blue-500">
                                <h4 class="font-bold text-lg mb-2">Transmisión en Vivo</h4>
                                <p class="text-sm text-gray-300 mb-4">El widget se actualiza automáticamente (simulado).</p>
                                <a :href="route('tournament.widget')" target="_blank" class="text-blue-400 hover:underline">
                                    Ver vista previa del Widget &rarr;
                                </a>
                            </div>

                            <!-- Tarjeta de Estado -->
                            <div class="bg-gray-700 p-4 rounded-lg border-l-4 border-green-500">
                                <h4 class="font-bold text-lg mb-2">Estado del Servidor</h4>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                    <span class="text-sm text-green-400">En Línea - Estable</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Equipos (Vista Previa) -->
                <div class="bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg border border-gray-700">
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-4 text-gray-200">Clasificación Actual</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b border-gray-600 bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-gray-400">Equipo</th>
                                        <th class="px-4 py-3 text-gray-400 text-right">Kills</th>
                                        <th class="px-4 py-3 text-gray-400 text-right">Puntos Totales</th>
                                        <th class="px-4 py-3 text-gray-400 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <tr v-for="equipo in equipos" :key="equipo.nombre" class="hover:bg-gray-700 transition-colors">
                                        <td class="px-4 py-3 font-medium">{{ equipo.nombre }}</td>
                                        <td class="px-4 py-3 text-right text-yellow-500">{{ equipo.kills }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-xl">{{ equipo.puntos }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <button class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded text-white">Editar</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>