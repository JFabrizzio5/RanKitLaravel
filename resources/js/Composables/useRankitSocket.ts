import { ref, onMounted, onUnmounted } from 'vue';

// Prioridad: Variable URL2 > Variable URL > Localhost
const WS_URL = import.meta.env.VITE_POINTS_SERVICE_WS_URL2 || import.meta.env.VITE_POINTS_SERVICE_WS_URL || 'ws://localhost:8011';

interface RankitSocketOptions {
    autoConnect?: boolean;      // Conectar automáticamente al montar
    manageVisibility?: boolean; // Si el composable debe manejar document.hidden automáticamente
}

export function useRankitSocket(
    type: 'channel' | 'community', 
    id?: string | number, 
    options: RankitSocketOptions = {}
) {
    const { autoConnect = true, manageVisibility = true } = options;

    const isConnected = ref(false);
    const socket = ref<WebSocket | null>(null);
    const viewerCount = ref(0);
    const secondsConnected = ref(0);
    const messages = ref<any[]>([]);
    
    let pingInterval: number | undefined;

    const connect = () => {
        // Validación: si no hay ID, no intentamos conectar
        if (id === undefined || id === null) {
            console.log('[RankitNative] ID no proporcionado, omitiendo conexión.');
            return;
        }

        if (socket.value?.readyState === WebSocket.OPEN) {
            console.log('[RankitNative] Ya existe una conexión activa.');
            return;
        }

        const wsUrl = `${WS_URL}/ws/${type}/${id}`;
        console.log(`[RankitNative] Intentando conectar a: ${wsUrl}`);

        try {
            socket.value = new WebSocket(wsUrl);

            socket.value.onopen = () => {
                console.log('[RankitNative] Conectado exitosamente.');
                isConnected.value = true;
                startHeartbeat();
            };

            socket.value.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    // console.log('[RankitNative] Mensaje:', data.type); // Uncomment for debug

                    if (data.type === 'viewer_count') {
                        viewerCount.value = data.count;
                    } else if (data.type === 'progress') {
                        secondsConnected.value = data.seconds_connected;
                    } else {
                        messages.value.push(data);
                    }
                } catch (e) {
                    console.error('[RankitNative] Error parsing msg:', e);
                }
            };

            socket.value.onclose = (event) => {
                console.log(`[RankitNative] Desconectado (Code: ${event.code})`);
                isConnected.value = false;
                stopHeartbeat();
                
                // Reconexión automática solo si autoConnect es true y no fue cierre manual
                if (autoConnect && event.code !== 1000 && event.code !== 1005) {
                    setTimeout(() => {
                        console.log('[RankitNative] Reintentando conexión...');
                        connect();
                    }, 3000);
                }
            };

            socket.value.onerror = (error) => {
                console.error('[RankitNative] Error WS:', error);
            };

        } catch (error) {
            console.error('[RankitNative] Error crítico:', error);
        }
    };

    const disconnect = () => {
        if (socket.value) {
            console.log('[RankitNative] Cerrando conexión manualmente...');
            socket.value.close(1000, "Component unmounted or manual disconnect");
            socket.value = null;
            isConnected.value = false;
            stopHeartbeat();
        }
    };

    const startHeartbeat = () => {
        stopHeartbeat();
        pingInterval = window.setInterval(() => {
            if (socket.value?.readyState === WebSocket.OPEN) {
                // Keep-alive logic if needed
            }
        }, 30000); 
    };

    const stopHeartbeat = () => {
        if (pingInterval) {
            clearInterval(pingInterval);
            pingInterval = undefined;
        }
    };

    // Manejador interno de visibilidad (solo si manageVisibility es true)
    const handleInternalVisibility = () => {
        if (document.hidden) {
            disconnect();
        } else {
            connect();
        }
    };

    onMounted(() => {
        if (autoConnect) {
            connect();
        }
        
        if (manageVisibility) {
            document.addEventListener('visibilitychange', handleInternalVisibility);
        }
    });

    onUnmounted(() => {
        disconnect();
        if (manageVisibility) {
            document.removeEventListener('visibilitychange', handleInternalVisibility);
        }
    });

    return {
        isConnected,
        viewerCount,
        secondsConnected,
        messages,
        disconnect,
        connect
    };
}