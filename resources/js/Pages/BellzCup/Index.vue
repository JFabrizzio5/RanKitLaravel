<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

// --- ESTADO DE AUTENTICACIÓN LOCAL ---
const accessCode = ref('');
const isAuthorized = ref(false);
const errorMsg = ref('');

// CÓDIGO DE ACCESO
const SECRET_CODE = 'BELLZ2026'; 

const verifyCode = () => {
    if (accessCode.value === SECRET_CODE) {
        isAuthorized.value = true;
        errorMsg.value = '';
    } else {
        errorMsg.value = 'Código incorrecto. Intenta de nuevo.';
        accessCode.value = '';
    }
};

// --- DATOS (Una vez desbloqueado) ---
const matchCodes = reactive([
    { label: 'Partida 1', code: 'A1-BC-22' },
    { label: 'Partida 2', code: 'PENDIENTE' },
    { label: 'Partida 3', code: 'PENDIENTE' },
    { label: 'Partida 4', code: 'PENDIENTE' },
    { label: 'Partida 5', code: 'PENDIENTE' },
    { label: 'Partida 6', code: 'PENDIENTE' },
]);

const leaderboard = ref([
    { rank: 1, team: 'Team Liquid', wins: 2, score: 45 },
    { rank: 2, team: 'G2 Esports', wins: 1, score: 38 },
    { rank: 3, team: 'KRÜ', wins: 1, score: 32 },
    { rank: 4, team: 'Leviatán', wins: 0, score: 28 },
    { rank: 5, team: 'Sentinels', wins: 0, score: 20 },
]);
</script>

<template>
    <Head title="BellzCup Access" />

    <div class="flex flex-col items-center justify-center min-h-screen p-6 font-sans text-white bg-gray-950 selection:bg-yellow-500 selection:text-black">
        
        <div v-if="!isAuthorized" class="relative w-full max-w-md bg-gray-900 p-10 rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-gray-800 text-center overflow-hidden group">
            
            <div class="absolute top-10 left-1/2 -translate-x-1/2 w-32 h-32 bg-yellow-500/20 rounded-full blur-[60px] pointer-events-none group-hover:bg-yellow-500/30 transition duration-700"></div>

            <div class="relative z-10 mb-8 transition duration-500 transform cursor-pointer hover:scale-105 hover:-rotate-2">
                <img 
                    src="/BellzCupBeta/bellzcup.png" 
                    alt="BellzCup Trophy" 
                    class="w-48 h-auto mx-auto drop-shadow-[0_0_25px_rgba(234,179,8,0.3)] filter brightness-110"
                />
            </div>

            <h1 class="relative z-10 mb-2 text-4xl italic font-black tracking-tighter text-white uppercase">
                BellzCup <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-600">Admin</span>
            </h1>
            <p class="relative z-10 mb-8 text-sm font-medium text-gray-400">Introduce el código maestro para gestionar el torneo.</p>
            
            <form @submit.prevent="verifyCode" class="relative z-10 space-y-5">
                <TextInput 
                    v-model="accessCode"
                    type="password"
                    class="w-full text-center text-xl tracking-[0.5em] bg-black/50 border-gray-700 text-yellow-400 focus:border-yellow-500 focus:ring-yellow-500 rounded-lg py-3 placeholder-gray-700 transition-all"
                    placeholder="••••••••"
                    autofocus
                />
                
                <p v-if="errorMsg" class="text-xs font-bold tracking-wide text-red-500 uppercase animate-pulse">{{ errorMsg }}</p>

                <PrimaryButton class="w-full justify-center bg-gradient-to-r from-yellow-600 to-yellow-500 hover:from-yellow-500 hover:to-yellow-400 text-black font-black uppercase tracking-widest py-4 text-xs border-0 shadow-[0_0_20px_rgba(234,179,8,0.2)] hover:shadow-[0_0_30px_rgba(234,179,8,0.4)] transition-all transform hover:-translate-y-1">
                    Desbloquear Panel
                </PrimaryButton>
            </form>
        </div>

        <div v-else class="w-full max-w-6xl space-y-10 animate-fade-in">
            
            <div class="flex flex-col items-center justify-between gap-6 pb-6 border-b border-gray-800 md:flex-row">
                <div class="flex items-center gap-6">
                     <div class="relative group">
                        <div class="absolute inset-0 transition duration-500 rounded-full opacity-0 bg-yellow-500/20 blur-xl group-hover:opacity-100"></div>
                        <img src="/BellzCupBeta/bellzcup.png" alt="Logo" class="relative w-20 h-auto drop-shadow-[0_0_15px_rgba(234,179,8,0.4)] transform hover:rotate-6 transition" />
                     </div>
                     <div>
                        <h1 class="text-4xl italic font-black tracking-tighter text-white uppercase">
                            Panel <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-600">BellzCup</span>
                        </h1>
                        <p class="font-mono text-xs tracking-widest text-gray-500 uppercase">Gestión en tiempo real</p>
                     </div>
                </div>
                <button @click="isAuthorized = false" class="px-4 py-2 text-xs font-bold tracking-wider text-gray-400 uppercase transition-colors border border-gray-700 rounded hover:border-red-500 hover:text-red-500">
                    Cerrar Sesión
                </button>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div class="p-6 bg-gray-900 border border-gray-800 shadow-xl lg:col-span-1 rounded-2xl">
                    <h2 class="text-sm font-bold text-yellow-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-3">
                        <i class="text-lg ph-fill ph-key"></i> Códigos de Sala
                    </h2>
                    <div class="grid grid-cols-1 gap-3">
                        <div v-for="(match, index) in matchCodes" :key="index" class="relative p-4 overflow-hidden transition-all border border-gray-800 rounded-lg cursor-pointer group bg-black/40 hover:border-yellow-500/30">
                            <div class="relative z-10 flex items-end justify-between">
                                <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider group-hover:text-yellow-500 transition-colors">{{ match.label }}</span>
                                <i class="text-gray-600 transition-colors ph-bold ph-copy group-hover:text-white"></i>
                            </div>
                            <div class="mt-1 font-mono text-xl font-bold tracking-wider text-white transition-colors group-hover:text-yellow-400">
                                {{ match.code }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col overflow-hidden bg-gray-900 border border-gray-800 shadow-xl lg:col-span-2 rounded-2xl">
                    <div class="relative z-10 flex items-center justify-between p-5 shadow-lg bg-gradient-to-r from-yellow-700 via-yellow-600 to-yellow-800">
                        <div class="flex items-center gap-3">
                            <i class="text-2xl text-yellow-200 ph-fill ph-trophy"></i>
                            <h2 class="text-2xl italic font-black tracking-tighter text-white uppercase drop-shadow-sm">
                                Leaderboard
                            </h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse shadow-[0_0_10px_#ef4444]"></span>
                            <div class="text-[10px] font-black text-yellow-100 bg-black/20 px-3 py-1 rounded backdrop-blur-sm">
                                LIVE UPDATES
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-black/40 text-gray-400 text-[10px] uppercase tracking-[0.15em] font-bold">
                                    <th class="px-6 py-4 text-left">Rank</th>
                                    <th class="px-6 py-4 text-left">Equipo</th>
                                    <th class="px-6 py-4 text-center">Wins</th>
                                    <th class="px-6 py-4 text-right">Pts</th>
                                </tr>
                            </thead>
                            <tbody class="text-white divide-y divide-gray-800/50">
                                <tr v-for="(team, index) in leaderboard" :key="team.team" 
                                    class="relative transition-colors duration-200 hover:bg-white/5 group">
                                    
                                    <td class="w-16 px-6 py-4 text-lg font-bold text-gray-500">
                                        <div v-if="index === 0" class="flex items-center justify-center w-8 h-8 bg-yellow-500 text-black rounded font-black text-sm shadow-[0_0_15px_rgba(234,179,8,0.5)] transform group-hover:scale-110 transition">
                                            1
                                        </div>
                                        <span v-else class="pl-2">#{{ team.rank }}</span>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <span class="text-lg font-bold tracking-wide transition-colors group-hover:text-yellow-400">{{ team.team }}</span>
                                    </td>
                                    
                                    <td class="px-6 py-4 font-mono font-medium text-center text-gray-400">
                                        {{ team.wins }}
                                    </td>
                                    
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-mono text-2xl font-black tracking-tight text-white transition-colors group-hover:text-yellow-400">{{ team.score }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Animación suave de entrada */
.animate-fade-in {
    animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>