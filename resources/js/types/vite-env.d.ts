/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_PUSHER_APP_KEY: string
    readonly VITE_PUSHER_HOST: string
    readonly VITE_PUSHER_PORT: string
    readonly VITE_PUSHER_SCHEME: string
    readonly VITE_PUSHER_APP_CLUSTER: string
    readonly VITE_APP_NAME: string
    readonly VITE_REVERB_HOST: string
    readonly VITE_REVERB_PORT: string
    readonly VITE_REVERB_SCHEME: string
    
    // Variables originales
    readonly VITE_POINTS_SERVICE_URL: string
    readonly VITE_POINTS_SERVICE_WS_URL: string

    // Variables secundarias (servidor de producción/alternativo)
    readonly VITE_POINTS_SERVICE_URL2: string
    readonly VITE_POINTS_SERVICE_WS_URL2: string
}

interface ImportMeta {
    readonly env: ImportMetaEnv
}