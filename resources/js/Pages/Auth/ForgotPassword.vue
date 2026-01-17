<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

onMounted(() => {
    // Forzar modo oscuro en auth pages para consistencia con el estilo "Gamer"
    document.documentElement.classList.add('dark');
});
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-[#050505] relative overflow-hidden">
        
        <!-- Grid Background & Overlay -->
        <div class="fixed inset-0 z-0 bg-tech-grid-dark bg-[length:40px_40px] pointer-events-none opacity-40"></div>
        <div class="fixed inset-0 pointer-events-none bg-gradient-to-b from-transparent via-[#050505]/50 to-[#050505] z-0"></div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 z-10 relative">
            
            <!-- Logo Header -->
            <div class="flex flex-col items-center mb-8">
                <Link href="/" class="group">
                     <svg class="w-16 h-16 text-white group-hover:text-[var(--rankit-neon)] transition-colors duration-300 drop-shadow-[0_0_15px_rgba(191,0,255,0.5)]" viewBox="0 0 100 100" fill="none">
                        <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                        <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                        <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
                    </svg>
                </Link>
                <h2 class="mt-4 text-3xl font-black uppercase italic font-display text-white tracking-tighter">
                    Rankit <span class="text-neon">ID</span>
                </h2>
                <p class="text-gray-400 text-sm font-mono tracking-widest uppercase">Identifícate, Jugador</p>
            </div>

            <!-- Card Container -->
            <div class="brutal-card bg-white dark:bg-[#0a0a0a] p-8 sm:rounded-none shadow-2xl relative overflow-hidden group">
                <!-- Decorative Corner -->
                <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-bl from-[var(--rankit-neon)]/20 to-transparent pointer-events-none"></div>
                
                <slot />
            </div>

            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500 uppercase tracking-widest">Rankit Tournament System v2.0</p>
            </div>
        </div>
    </div>
</template>