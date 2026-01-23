<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps<{
    tournamentName: string;
    slug: string;
    tournamentId?: number;
}>();

const form = useForm({
    code: '',
});

const submitCode = () => {
    // Redirigir a la misma URL pero añadiendo el código como parámetro GET
    // El controlador verificará este código
    window.location.href = route('public.tournament.show', { 
        slug: props.slug, 
        code: form.code 
    });
};
</script>

<template>
    <Head title="Acceso Restringido" />

    <!-- NO IMPORTAMOS AuthenticatedLayout AQUÍ PARA EVITAR ERRORES -->
    <div class="flex flex-col items-center justify-center min-h-screen pt-6 text-gray-900 bg-gray-100 sm:pt-0 dark:bg-gray-900 dark:text-gray-100">
        
        <div class="w-full px-6 py-8 overflow-hidden bg-white shadow-xl sm:max-w-md dark:bg-gray-800 sm:rounded-lg">
            
            <div class="flex justify-center mb-6">
                <div class="p-4 bg-red-100 rounded-full dark:bg-red-900/20 animate-bounce">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
            </div>

            <h2 class="mb-2 text-2xl font-bold tracking-wide text-center uppercase">
                Torneo Privado
            </h2>
            <p class="mb-8 text-center text-gray-600 dark:text-gray-400">
                El torneo <span class="text-[var(--rankit-neon)] font-bold">"{{ tournamentName }}"</span> está protegido.
            </p>

            <form @submit.prevent="submitCode">
                <div class="mb-6">
                    <InputLabel for="code" value="Ingresa el Código de Acceso" class="sr-only" />
                    <TextInput
                        id="code"
                        type="text"
                        class="mt-1 block w-full text-center text-2xl tracking-[0.2em] uppercase font-mono py-3"
                        v-model="form.code"
                        required
                        autofocus
                        placeholder="CÓDIGO"
                    />
                </div>

                <div class="flex items-center justify-center">
                    <PrimaryButton 
                        :class="{ 'opacity-25': form.processing }" 
                        :disabled="form.processing" 
                        class="justify-center w-full py-4 text-sm font-bold tracking-widest uppercase"
                    >
                        Ingresar al Torneo
                    </PrimaryButton>
                </div>
            </form>
            
            <div class="pt-4 mt-8 text-center border-t border-gray-200 dark:border-gray-700">
                <a href="/" class="text-xs font-bold text-gray-400 uppercase transition hover:text-gray-600 dark:hover:text-gray-200">
                    &larr; Volver al Inicio
                </a>
            </div>
        </div>
    </div>
</template>