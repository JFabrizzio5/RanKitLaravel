<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const user = usePage().props.auth.user;

const connectStripe = () => {
    window.location.href = route('stripe.connect');
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Pagos y Cobros (Stripe)
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Administra tu conexión con Stripe para recibir pagos de torneos y premios.
            </p>
        </header>

        <div class="mt-6 flex items-center gap-4">
            <div v-if="user.role === 'organizer' || user.role === 'admin'">
                <div v-if="user.stripe_connect_id" class="flex items-center gap-2 text-green-600 font-bold">
                    <i class="ph-bold ph-check-circle text-xl"></i>
                    <span>Cuenta de Stripe Conectada ({{ user.stripe_connect_id }})</span>
                </div>
                <div v-else>
                    <PrimaryButton @click="connectStripe" class="bg-[#635BFF] hover:bg-[#534ac2]">
                        <i class="ph-bold ph-stripe-logo mr-2 text-lg"></i>
                        Conectar con Stripe
                    </PrimaryButton>
                    <p class="mt-2 text-xs text-gray-500">
                        Necesario para recibir el dinero de las entradas de tus torneos.
                    </p>
                </div>
            </div>
            <div v-else class="text-sm text-gray-500 italic">
                Solo los Organizadores pueden conectar cuentas de Stripe.
            </div>
        </div>
    </section>
</template>
