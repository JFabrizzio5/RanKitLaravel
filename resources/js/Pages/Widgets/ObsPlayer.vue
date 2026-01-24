<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ 
    tournamentId: Number, 
    playerName: String 
});

const stats = ref<any>({ rank: '-', total_points: 0, total_kills: 0 });
const activeLogo = ref(0); // 0 = BellzCup, 1 = Rankit

const update = async () => {
    try {
        // @ts-ignore
        const res = await axios.get(route('api.widget.stats', { 
            id: props.tournamentId, 
            player: props.playerName 
        }));
        stats.value = res.data;
    } catch (e) {
        // Silencioso
    }
};

onMounted(() => { 
    document.body.style.backgroundColor = 'transparent';
    update(); 
    
    // Intervalo de actualización de datos
    const updateInterval = setInterval(update, 60000); 
    
    // Intervalo de cambio de logo (cada 5 segundos)
    const logoInterval = setInterval(() => {
        activeLogo.value = activeLogo.value === 0 ? 1 : 0;
    }, 5000);

    onUnmounted(() => {
        clearInterval(updateInterval);
        clearInterval(logoInterval);
    });
});
</script>

<template>
    <div class="relative !overflow-visible p-4 inline-block">
        <!-- Contenedor Principal -->
        <div class="inline-flex items-center gap-6 bg-[#050505] text-white px-6 py-4 border-2 border-[#bf00ff] shadow-neo-purple relative animate-slide-in z-10">
            
            <!-- Sección de Rango -->
            <div class="flex flex-col items-center pr-6 border-r-2 border-[#bf00ff]/30">
                <span class="text-[9px] text-[#bf00ff] uppercase font-black tracking-[0.2em] font-display">Rank</span>
                <span class="text-4xl italic font-black text-white font-display">#{{ stats.rank }}</span>
            </div>

            <div class="flex flex-col justify-center gap-2">
                <div class="flex items-center gap-3">
                    <span class="text-xl italic font-black leading-none tracking-tighter uppercase font-display">
                        {{ playerName }}
                    </span>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-[#111] border border-white/10 px-3 py-1 skew-x-[-10deg]">
                        <span class="text-[10px] font-bold text-gray-500 uppercase btn-content">PTS:</span>
                        <span class="text-lg font-black text-[#bf00ff] font-display btn-content">{{ stats.total_points }}</span>
                    </div>
                    <div class="flex items-center gap-2 bg-[#111] border border-white/10 px-3 py-1 skew-x-[-10deg]">
                        <span class="text-[10px] font-bold text-gray-500 uppercase btn-content">KILLS:</span>
                        <span class="text-lg font-black text-red-500 font-display btn-content">{{ stats.total_kills }}</span>
                    </div>
                </div>
            </div>

            <!-- Marca lateral -->
            <div class="absolute right-0 top-0 bottom-0 w-1 bg-[#bf00ff]"></div>
        </div>

        <!-- Footer Flotante que sobresale -->
        <div class="absolute z-50 flex justify-center w-full -translate-x-1/2 pointer-events-none left-1/2 -bottom-2">
            <div class="bg-[#050505] border-2 border-white px-5 py-1.5 shadow-[4px_4px_0px_0px_rgba(191,0,255,1)] flex items-center justify-center min-w-[130px] h-[34px] relative overflow-hidden">
                <transition name="fade-slide">
                    <img v-if="activeLogo === 0" src="https://rankit.pro/public/BellzCup.png" class="h-3.5 absolute" alt="BellzCup">
                    <div v-else class="absolute flex items-center gap-2">
                        <div class="w-3.5 h-3.5">
                            <svg viewBox="0 0 100 100" fill="none">
                                <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white"/> 
                                <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white"/>
                                <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="#bf00ff"/>
                            </svg>
                        </div>
                        <span class="text-[8px] font-black uppercase tracking-widest text-white font-display">Rankit.pro</span>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@700&family=Archivo:wght@600;800&display=swap');

html, body { background-color: transparent !important; overflow: visible; }
.font-display { font-family: 'Chakra Petch', sans-serif; }
.shadow-neo-purple { box-shadow: 6px 6px 0px 0px #000, 8px 8px 0px 0px #bf00ff; }
.btn-content { transform: skewX(10deg); display: inline-block; }
.animate-slide-in { animation: slideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) both; }

@keyframes slideIn { from { transform: translateX(-30px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* Animación de los logos intercambiables */
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
.fade-slide-enter-from { opacity: 0; transform: translateY(10px); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-10px); }
</style>