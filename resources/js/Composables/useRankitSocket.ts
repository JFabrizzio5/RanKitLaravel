import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useRankitSocket(options = { autoConnect: true }) {
    const isConnected = ref(false);
    let socket: WebSocket | null = null;
    let pingInterval: ReturnType<typeof setInterval> | null = null;

    // CONFIGURACIÓN:
    const WS_URL = import.meta.env.VITE_RANKIT_WS_URL || 'ws://localhost:8011';

    const connect = () => {
        const user = usePage().props.auth.user;
        
        if (!user || !user.id) {
            console.log('Rankit: Usuario no autenticado, no se inicia conexión.');
            return;
        }

        const userId = user.id; 
        
        if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        console.log(`Rankit: Conectando a sala comunidad...`);

        try {
            // NOTA: Apuntamos al endpoint específico de comunidad
            socket = new WebSocket(`${WS_URL}/ws/community/${userId}`);

            socket.onopen = () => {
                console.log('Rankit: Conectado. Sumando puntos.');
                isConnected.value = true;
                startPing();
            };

            socket.onmessage = (event) => {
                if (event.data === 'pong') {
                    // Alive
                }
            };

            socket.onclose = () => {
                console.log('Rankit: Desconectado.');
                isConnected.value = false;
                stopPing();
                // Solo reconectar automáticamente si la intención era estar conectado
                // (Podrías agregar lógica aquí si se cae internet)
            };

            socket.onerror = (error) => {
                console.error('Rankit: Error en WebSocket', error);
                socket?.close();
            };

        } catch (e) {
            console.error('Rankit: Error al iniciar conexión', e);
        }
    };

    const startPing = () => {
        stopPing();
        pingInterval = setInterval(() => {
            if (socket && socket.readyState === WebSocket.OPEN) {
                socket.send('ping');
            }
        }, 20000);
    };

    const stopPing = () => {
        if (pingInterval) {
            clearInterval(pingInterval);
            pingInterval = null;
        }
    };

    const disconnect = () => {
        stopPing();
        if (socket) {
            socket.close();
            socket = null;
        }
        isConnected.value = false;
    };

    onMounted(() => {
        if (options.autoConnect) {
            connect();
        }
    });

    onUnmounted(() => {
        disconnect();
    });

    return {
        isConnected,
        connect,
        disconnect
    };
}