<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};

// --- LOGICA DE TEMA ---
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
    <Head title="Register">
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen overflow-x-hidden bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white font-sans transition-colors duration-300 relative flex flex-col">
        
        <!-- Background Grid Effect -->
        <div class="absolute inset-0 z-0 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px] pointer-events-none opacity-50"></div>
        <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-transparent via-gray-50/80 to-gray-50 dark:bg-gradient-to-b dark:from-transparent dark:via-[#050505]/50 dark:to-[#050505] z-0"></div>

        <!-- Navbar Simplificada -->
        <nav class="fixed w-full z-50 transition-colors duration-300 bg-white/90 border-b border-gray-200 dark:bg-[#050505]/95 dark:border-white/10 backdrop-blur-md h-20 flex items-center px-6 lg:px-12 justify-between">
            <Link :href="route('Inicio')" class="flex items-center gap-3 cursor-pointer group">
                <svg class="w-10 h-10 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
                    <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                    <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                    <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
                </svg>
                <span class="text-3xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">Rankit</span>
            </Link>

            <button @click="toggleTheme" class="p-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
                <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
                <i v-else class="text-xl ph-fill ph-moon"></i>
            </button>
        </nav>

        <!-- Main Content -->
        <div class="relative z-10 flex flex-col items-center justify-center flex-1 px-6 py-24 sm:px-0">
            
            <div class="w-full max-w-md brutal-card p-8 bg-white dark:bg-[#0a0a0a]">
                <div class="mb-8 text-center">
                    <h2 class="text-3xl font-black text-black uppercase font-display dark:text-white">
                        Nuevo <span class="text-neon">Agente</span>
                    </h2>
                    <p class="text-xs font-mono text-gray-500 mt-2">CREAR PERFIL DE ORGANIZADOR</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                     <!-- Google Button Adaptado -->
                     <a :href="route('google.redirect')" class="flex w-full items-center justify-center gap-3 py-3 text-sm font-bold text-gray-700 transition bg-gray-50 border border-gray-300 hover:bg-white hover:border-neon dark:bg-[#111] dark:border-gray-700 dark:text-gray-200 dark:hover:border-neon">
                        <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.2l5.7-5.7C34.1 4.1 29.3 2 24 2 12.9 2 4 10.9 4 22s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-4z"/>
                            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16.1 19 13 24 13c3.1 0 5.9 1.2 8 3.2l5.7-5.7C34.1 7.1 29.3 5 24 5 16.1 5 9.3 9.5 6.3 14.7z"/>
                            <path fill="#4CAF50" d="M24 42c5.1 0 9.8-2 13.3-5.2l-6.1-5.2C29.3 33.6 26.8 34.6 24 34.6c-5.2 0-9.6-3.3-11.2-7.9l-6.6 5.1C9.1 38.2 16.1 42 24 42z"/>
                            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1 2.7-3 4.9-5.7 6.4l.1.1 6.1 5.2C39.7 36.1 44 30.7 44 22c0-1.3-.1-2.7-.4-4z"/>
                        </svg>
                        <span>REGISTRO CON GOOGLE</span>
                    </a>

                    <div class="relative flex items-center justify-center w-full my-6 border-t border-gray-300 dark:border-gray-700">
                         <span class="px-3 text-xs font-mono text-gray-500 bg-white dark:bg-[#0a0a0a]">O DATOS MANUALES</span>
                    </div>

                    <div>
                        <InputLabel for="name" value="NOMBRE" class="font-display font-bold text-xs tracking-widest" />
                        <input
                            id="name"
                            type="text"
                            class="brutal-input mt-1 block w-full"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Tu Nombre"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="email" value="EMAIL" class="font-display font-bold text-xs tracking-widest" />
                        <input
                            id="email"
                            type="email"
                            class="brutal-input mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="usuario@ejemplo.com"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" value="PASSWORD" class="font-display font-bold text-xs tracking-widest" />
                        <input
                            id="password"
                            type="password"
                            class="brutal-input mt-1 block w-full"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password_confirmation" value="CONFIRMAR PASSWORD" class="font-display font-bold text-xs tracking-widest" />
                        <input
                            id="password_confirmation"
                            type="password"
                            class="brutal-input mt-1 block w-full"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                        />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center justify-end mt-8">
                        <button 
                            class="px-8 py-3 w-full text-sm font-bold tracking-wider uppercase btn-skew"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            <span class="btn-content">CREAR CUENTA</span>
                        </button>
                    </div>
                </form>
            </div>
            
             <div class="mt-8 text-center">
                 <p class="text-sm text-gray-500">
                    ¿Ya tienes cuenta? 
                    <Link :href="route('login')" class="text-neon font-bold hover:underline">INGRESA AQUÍ</Link>
                </p>
            </div>
        </div>
    </div>
</template>

<style>
/* Estilos consistentes con Home y Login */
:root {
  --rankit-neon: #bf00ff;
}

.font-display {
  font-family: "Chakra Petch", ui-sans-serif, system-ui;
}
.font-sans {
    font-family: "Archivo", ui-sans-serif, system-ui;
}

.text-neon { color: var(--rankit-neon); }
.bg-neon { background-color: var(--rankit-neon); }

.bg-tech-grid-dark {
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
}
.bg-tech-grid-light {
  background-image:
    linear-gradient(to right, rgba(0, 0, 0, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(0, 0, 0, 0.05) 1px, transparent 1px);
}

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
.brutal-card:hover {
  border-color: var(--rankit-neon);
}
.dark .brutal-card:hover {
  box-shadow: 0 0 20px rgba(191, 0, 255, 0.1);
}

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
html:not(.dark) .btn-skew:hover {
  background-color: black;
  color: white;
  box-shadow: 4px 4px 0px rgba(0,0,0,0.2);
}
.btn-content { transform: skewX(10deg); }

.brutal-input {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 2px solid #333;
  padding: 1rem 0;
  font-family: "Archivo", ui-sans-serif;
  font-weight: 600;
  outline: none;
  transition: all 0.3s;
}
.dark .brutal-input { color: white; border-color: #333; }
html:not(.dark) .brutal-input { color: black; border-color: #e5e5e5; }
.brutal-input:focus { border-color: var(--rankit-neon); padding-left: 1rem; box-shadow: none; ring: 0; }
</style>