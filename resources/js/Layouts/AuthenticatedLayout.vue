<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

const showingNavigationDropdown = ref(false);
const user = usePage().props.auth.user;

const isDark = ref(true);

function applyTheme(nextDark: boolean) {
    isDark.value = nextDark;
    const html = document.documentElement;
    if (nextDark) {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
}

function toggleTheme() {
    applyTheme(!isDark.value);
}

onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true;
    
    if (savedTheme === 'light') applyTheme(false);
    else if (savedTheme === 'dark') applyTheme(true);
    else applyTheme(systemPrefersDark);

    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/@phosphor-icons/web';
        script.async = true;
        document.head.appendChild(script);
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white font-sans transition-colors duration-300 relative flex flex-col">
        
        <!-- FONDO DE GRID (ESENCIAL PARA EL LOOK) -->
        <div class="fixed inset-0 z-0 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px] pointer-events-none opacity-50"></div>
        <div class="fixed inset-0 pointer-events-none bg-gradient-to-b from-transparent via-gray-50/80 to-gray-50 dark:bg-gradient-to-b dark:from-transparent dark:via-[#050505]/50 dark:to-[#050505] z-0"></div>

        <!-- Navbar -->
        <nav class="sticky top-0 w-full z-50 transition-colors duration-300 bg-white/90 border-b border-gray-200 dark:bg-[#050505]/95 dark:border-white/10 backdrop-blur-md h-20">
            <div class="mx-auto w-full px-4 sm:px-6 lg:px-8 h-full">
                <div class="flex h-full justify-between items-center">
                    <div class="flex items-center">
                        <Link :href="route('dashboard')" class="flex items-center gap-3 cursor-pointer group mr-10">
                            <svg class="w-8 h-8 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
                                <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                                <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                                <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
                            </svg>
                            <span class="text-2xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white hidden sm:block">Rankit</span>
                        </Link>

                        <div class="hidden space-x-8 sm:-my-px sm:flex h-full items-center">
                            <Link :href="route('dashboard')" :class="{'text-neon border-b-2 border-neon': route().current('dashboard')}" class="font-display font-bold uppercase tracking-wider text-sm text-gray-500 hover:text-black dark:hover:text-white transition py-2 h-full flex items-center border-b-2 border-transparent hover:border-gray-300 dark:hover:border-gray-700">
                                Dashboard
                            </Link>
                             <Link :href="route('tournament.dashboard')" :class="{'text-neon border-b-2 border-neon': route().current('tournament.dashboard')}" class="font-display font-bold uppercase tracking-wider text-sm text-gray-500 hover:text-black dark:hover:text-white transition py-2 h-full flex items-center border-b-2 border-transparent hover:border-gray-300 dark:hover:border-gray-700">
                                Torneo
                            </Link>
                        </div>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                        <button @click="toggleTheme" class="p-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
                            <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
                            <i v-else class="text-xl ph-fill ph-moon"></i>
                        </button>

                        <div class="relative ms-3">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-bold font-display uppercase rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-[#111] hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150 border-gray-200 dark:border-gray-800">
                                            {{ user.name }}
                                            <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="border-t border-neon/50"></div>
                                    <DropdownLink :href="route('profile.edit')"> Perfil </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button"> Cerrar Sesión </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div class="-me-2 flex items-center sm:hidden">
                        <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800 transition">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden bg-white dark:bg-[#0a0a0a] border-b border-gray-200 dark:border-gray-800">
                <div class="pt-2 pb-3 space-y-1">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')"> Dashboard </ResponsiveNavLink>
                     <ResponsiveNavLink :href="route('tournament.dashboard')" :active="route().current('tournament.dashboard')"> Torneo </ResponsiveNavLink>
                </div>
                <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
                    <div class="px-4">
                        <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ user.name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ user.email }}</div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')"> Perfil </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button"> Cerrar Sesión </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <main class="relative z-10 flex-1">
            <slot />
        </main>
    </div>
</template>