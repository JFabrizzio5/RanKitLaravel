import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useRankitSocket(options = { autoConnect: true }) {
    const isConnected = ref(false);
    let socket: WebSocket | null = null;
    let pingInterval: ReturnType<typeof setInterval> | null = null;

    // CONFIGURACIÓN:
    // Vite requiere que las variables empiecen con VITE_ para ser visibles en el cliente
    const WS_URL = import.meta.env.VITE_RANKIT_WS_URL || 'ws://localhost:8011';

    const connect = () => {
        // Casting a 'any' para evitar errores de TS si los tipos de Inertia no están definidos globalmente
        const user = (usePage().props as any).auth?.user;
        
        if (!user || !user.id) {
            console.warn('[RankitNative] Usuario no autenticado (o sin ID), no se inicia conexión WS.');
            return;
        }

        const userId = user.id; 
        
        if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
            console.log('[RankitNative] Ya existe una conexión activa o en proceso.');
            return;
        }

        console.log(`[RankitNative] Intentando conectar a: ${WS_URL}/ws/community/${userId}`);

        try {
            // Conexión directa al WebSocket Python
            socket = new WebSocket(`${WS_URL}/ws/community/${userId}`);

            socket.onopen = () => {
                console.log('%c[RankitNative] Conectado exitosamente.', 'color: green; font-weight: bold;');
                isConnected.value = true;
                startPing();
            };

            socket.onmessage = (event) => {
                // Si el servidor manda algo, lo vemos aquí
                if (event.data === 'pong') {
                    // Respuesta del ping, todo bien
                    // console.debug('pong received');
                } else {
                    console.log('[RankitNative] Mensaje recibido:', event.data);
                }
            };

            socket.onclose = (event) => {
                console.log(`[RankitNative] Desconectado. Código: ${event.code}, Razón: ${event.reason}`);
                isConnected.value = false;
                stopPing();
                socket = null;
            };

            socket.onerror = (error) => {
                console.error('[RankitNative] Error en el socket:', error);
                // No cerramos manualmente aquí, onclose se disparará usualmente
            };

        } catch (e) {
            console.error('[RankitNative] Excepción al crear WebSocket:', e);
            isConnected.value = false;
        }
    };

    const startPing = () => {
        stopPing();
        // Ping cada 20s para mantener la conexión viva (heartbeat)
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
            console.log('[RankitNative] Cerrando conexión intencionalmente...');
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