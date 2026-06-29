<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    tournaments: Array,
});

const showModal = ref(false);
const form = useForm({
    name: '',
    format: 'elimination',
});

const submit = () => {
    form.post(route('football.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Mis Torneos de Fútbol" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-white leading-tight flex items-center gap-2">
                    <span class="text-emerald-500">⚽</span> Torneos de Fútbol
                </h2>
                <button @click="showModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-bold transition-colors">
                    + Nuevo Torneo
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div v-if="tournaments.length === 0" class="text-center py-20 bg-gray-900 rounded-xl border border-gray-800">
                    <div class="text-5xl mb-4">🏟️</div>
                    <h3 class="text-xl text-gray-300 font-bold">No tienes torneos de fútbol</h3>
                    <p class="text-gray-500 mt-2">Crea tu primer torneo de liga o eliminación.</p>
                    <button @click="showModal = true" class="mt-4 bg-emerald-600 text-white px-4 py-2 rounded font-bold">
                        Crear Torneo
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="t in tournaments" :key="t.id" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-emerald-500/50 transition-all relative overflow-hidden group">
                        <!-- Glow effect -->
                        <div class="absolute -right-10 -top-10 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
                        
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <span class="text-xs font-bold uppercase px-2 py-1 rounded bg-gray-800 text-emerald-400 border border-gray-700">
                                {{ t.format === 'elimination' ? 'Eliminación' : 'Liga' }}
                            </span>
                            <span :class="[
                                'text-xs font-bold uppercase px-2 py-1 rounded',
                                t.phase === 'pending' ? 'bg-yellow-500/10 text-yellow-500' :
                                t.phase === 'done' ? 'bg-blue-500/10 text-blue-500' : 'bg-emerald-500/10 text-emerald-500'
                            ]">
                                {{ t.phase === 'pending' ? 'Configuración' : t.phase === 'done' ? 'Finalizado' : 'En Curso' }}
                            </span>
                        </div>
                        
                        <h3 class="text-xl font-bold text-white mb-2 relative z-10 truncate">{{ t.name }}</h3>
                        
                        <div class="mt-6 pt-4 border-t border-gray-800 flex justify-between items-center relative z-10">
                            <span class="text-xs text-gray-500">ID: #{{ t.id }}</span>
                            <Link :href="route('football.show', t.id)" class="text-emerald-400 hover:text-emerald-300 font-bold text-sm">
                                Gestionar &rarr;
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Nuevo Torneo -->
        <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-gray-900 border border-emerald-500/30 rounded-2xl w-full max-w-md p-6 shadow-[0_0_50px_rgba(16,185,129,0.1)]">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-white">Crear Torneo de Fútbol</h3>
                    <button @click="showModal = false" class="text-gray-500 hover:text-white">&times;</button>
                </div>
                
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre del Torneo</label>
                        <input v-model="form.name" type="text" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="Ej: Copa Bimbo 2026">
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Formato de Competición</label>
                        <select v-model="form.format" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="elimination">Eliminación Directa (Llaves)</option>
                            <option value="league">Liga (Todos contra Todos)</option>
                        </select>
                    </div>

                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" @click="showModal = false" class="px-4 py-2 text-gray-400 hover:text-white">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg font-bold disabled:opacity-50">
                            Crear Torneo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
