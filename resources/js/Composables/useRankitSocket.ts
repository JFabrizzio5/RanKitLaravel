import { ref, onUnmounted } from 'vue';

export function useRankitSocket(channelName: string, userId: number | null, options: { autoConnect?: boolean, manageVisibility?: boolean } = {}) {
    const isConnected = ref(false);
    let socket: WebSocket | null = null;
    let reconnectInterval: number | null = null;

    const connect = () => {
        if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) return;

        // Replace with your actual WebSocket URL logic
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = '6001'; // Default Laravel Echo / Reverb port, adjust as needed
        const url = `${protocol}//${host}:${port}/app/rankit_key?channel=${channelName}&user=${userId || ''}`;

        console.log('Connecting to WebSocket:', url);

        try {
            socket = new WebSocket(url);

            socket.onopen = () => {
                console.log('WebSocket Connected');
                isConnected.value = true;
                if (reconnectInterval) {
                    clearInterval(reconnectInterval);
                    reconnectInterval = null;
                }
            };

            socket.onclose = () => {
                console.log('WebSocket Disconnected');
                isConnected.value = false;
                // Auto reconnect logic could go here
                if (!reconnectInterval && options.autoConnect !== false) {
                    reconnectInterval = window.setInterval(connect, 5000);
                }
            };

            socket.onerror = (error) => {
                console.error('WebSocket Error:', error);
            };

            socket.onmessage = (event) => {
                // Handle incoming messages
                // You might want to expose a way to register handlers
                console.log('WS Message:', event.data);
            };

        } catch (e) {
            console.error('WebSocket Connection Failed:', e);
        }
    };

    const disconnect = () => {
        if (socket) {
            socket.close();
            socket = null;
        }
        isConnected.value = false;
        if (reconnectInterval) {
            clearInterval(reconnectInterval);
            reconnectInterval = null;
        }
    };

    if (options.autoConnect) {
        connect();
    }

    onUnmounted(() => {
        disconnect();
    });

    return {
        connect,
        disconnect,
        isConnected
    };
}