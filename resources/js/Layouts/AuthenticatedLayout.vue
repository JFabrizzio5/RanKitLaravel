<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, Head } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

// --- Lógica de Usuario (Breeze) ---
const showingNavigationDropdown = ref(false);
const page = usePage();
const user = computed(() => page.props.auth?.user || null);

// --- Lógica de Tema y Diseño (RanKit) ---
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
  // Inicializar Tema (Preferimos Dark por defecto para Rankit)
  const savedTheme = localStorage.getItem('theme');
  const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true;
  
  if (savedTheme === 'light') applyTheme(false);
  else if (savedTheme === 'dark') applyTheme(true);
  else applyTheme(true); // Default to dark for this design

  // Cargar Iconos Phosphor dinámicamente si no existen
  if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/@phosphor-icons/web';
    script.async = true;
    document.head.appendChild(script);
  }
});
</script>

<template>
    <Head>
        <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen font-sans text-gray-900 bg-gray-50 dark:bg-[#050505] dark:text-white transition-colors duration-300 selection:bg-[var(--rankit-neon)] selection:text-white">
        
        <nav class="fixed w-full z-50 transition-colors duration-300 bg-white/90 border-b border-gray-200 dark:bg-[#050505]/95 dark:border-white/10 backdrop-blur-md h-20">
            <div class="h-full px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-full">
                    
                    <div class="flex items-center gap-12">
                        <Link :href="route('dashboard')" class="flex items-center gap-3 group">
                            <svg class="w-8 h-8 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
                                <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                                <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                                <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
                            </svg>
                            <span class="text-2xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">Rankit</span>
                        </Link>

                        <div class="hidden space-x-8 sm:flex">
                            <Link :href="route('dashboard')" :class="{'text-[var(--rankit-neon)]': route().current('dashboard')}" class="text-sm font-bold tracking-widest text-gray-500 uppercase transition hover:text-[var(--rankit-neon)] dark:text-gray-400">
                                Dashboard
                            </Link>
                            </div>
                    </div>

                    <div class="hidden gap-4 sm:flex sm:items-center sm:ms-6">
                        
                        <button @click="toggleTheme" class="p-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-[var(--rankit-neon)] dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
                            <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
                            <i v-else class="text-xl ph-fill ph-moon"></i>
                        </button>

                        <div class="relative ms-3">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold tracking-wider text-black uppercase transition duration-150 ease-in-out bg-white border border-gray-200 rounded-md dark:text-white dark:bg-[#0a0a0a] dark:border-gray-700 hover:border-[var(--rankit-neon)] focus:outline-none"
                                        >
                                            {{ user?.name }}
                                            <i class="ph-bold ph-caret-down"></i>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="p-1">
                                        <DropdownLink :href="route('profile.edit')">
                                            <span class="flex items-center gap-2"><i class="ph ph-user"></i> Profile</span>
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            <span class="flex items-center gap-2 text-red-500"><i class="ph ph-sign-out"></i> Log Out</span>
                                        </DropdownLink>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div class="flex items-center -me-2 sm:hidden">
                        <button
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center p-2 text-gray-400 transition duration-150 ease-in-out rounded-md hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none"
                        >
                            <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path
                                    :class="{ hidden: showingNavigationDropdown, inline: !showingNavigationDropdown }"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{ hidden: !showingNavigationDropdown, inline: showingNavigationDropdown }"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden border-b border-gray-700 bg-[#050505]">
                <div class="pt-2 pb-3 space-y-1">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                        Dashboard
                    </ResponsiveNavLink>
                </div>

                <div class="pt-4 pb-1 border-t border-gray-700">
                    <div class="px-4">
                        <div class="text-base font-medium text-white">{{ user?.name }}</div>
                        <div class="text-sm font-medium text-gray-400">{{ user?.email }}</div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button"> Log Out </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <header class="pt-24 pb-6 bg-white dark:bg-[#050505] shadow-sm" v-if="$slots.header">
            <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main :class="{ 'pt-24': !$slots.header }" class="min-h-screen bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px]">
            <div class="relative w-full h-full">
                <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-transparent via-gray-50/80 to-gray-50 dark:bg-gradient-to-b dark:from-transparent dark:via-[#050505]/50 dark:to-[#050505] z-0"></div>
                
                <div class="relative z-10">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>

<style>
/* --- ESTILOS GLOBALES DE RANKIT --- */
:root {
  --rankit-neon: #bf00ff;
}

/* Tipografías */
.font-display {
  font-family: "Chakra Petch", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
}
.font-sans {
    font-family: "Archivo", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
}

/* Fondos Tecnológicos */
.bg-tech-grid-dark {
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
}
.bg-tech-grid-light {
  background-image:
    linear-gradient(to right, rgba(0, 0, 0, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(0, 0, 0, 0.05) 1px, transparent 1px);
}

/* Brutal Card / Utilitarios */
.brutal-card {
  position: relative;
  transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.dark .brutal-card {
  background: #0a0a0a;
  border: 1px solid #333;
}
html:not(.dark) .brutal-card {
  background: #ffffff;
  border: 1px solid #e5e5e5;
  box-shadow: 4px 4px 0px #00000010;
}

/* Botones Skew */
.btn-skew {
  background-color: var(--rankit-neon);
  color: white;
  transform: skewX(-10deg);
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.btn-skew:hover {
  background-color: white;
  color: black;
  box-shadow: 0 0 15px var(--rankit-neon);
}
.btn-content { transform: skewX(10deg); }

.text-stroke {
    -webkit-text-stroke: 1px white;
    color: transparent;
}
</style>