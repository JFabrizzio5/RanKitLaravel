<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AvatarUsuario from '@/Components/AvatarUsuario.vue';
import TarjetaPlan from '@/Components/TarjetaPlan.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="Mi cuenta" />

    <AuthenticatedLayout>
        <div class="px-4 py-12 sm:px-6 lg:px-8" style="font-family: 'Archivo', sans-serif;">
            <div class="mx-auto max-w-4xl space-y-6">
                <!-- Encabezado al estilo de la cuenta de Google -->
                <header class="flex flex-col items-center pb-4 text-center">
                    <AvatarUsuario :nombre="user.name" :semilla="user.email" tamano="xl" />
                    <h1 class="mt-4 text-3xl font-black text-gray-900 dark:text-white">{{ user.name }}</h1>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                </header>

                <TarjetaPlan contexto="perfil" />

                <div class="brutal-card p-6 sm:p-8 bg-white dark:bg-[#0a0a0a]">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="brutal-card p-6 sm:p-8 bg-white dark:bg-[#0a0a0a]">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
