<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import ConnectStripeForm from './Partials/ConnectStripeForm.vue';
import MyTournaments from './Partials/MyTournaments.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    joinedTournaments?: any[];
}>();
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Profile
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                
                <div v-if="joinedTournaments && joinedTournaments.length > 0"
                    class="bg-white dark:bg-gray-900 p-6 shadow-xl border border-gray-100 dark:border-gray-800 sm:rounded-xl sm:p-8 hover:shadow-2xl transition-shadow duration-300"
                >
                    <MyTournaments :tournaments="joinedTournaments" />
                </div>

                <div
                    class="bg-white dark:bg-gray-900 p-6 shadow-xl border border-gray-100 dark:border-gray-800 sm:rounded-xl sm:p-8 hover:shadow-2xl transition-shadow duration-300"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="bg-white dark:bg-gray-900 p-6 shadow-xl border border-gray-100 dark:border-gray-800 sm:rounded-xl sm:p-8 hover:shadow-2xl transition-shadow duration-300"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="bg-white dark:bg-gray-900 p-6 shadow-xl border border-gray-100 dark:border-gray-800 sm:rounded-xl sm:p-8 hover:shadow-2xl transition-shadow duration-300"
                >
                    <ConnectStripeForm class="max-w-xl" />
                </div>

                <div
                    class="bg-white dark:bg-gray-900 p-6 shadow-xl border border-gray-100 dark:border-gray-800 sm:rounded-xl sm:p-8 hover:shadow-2xl transition-shadow duration-300"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
