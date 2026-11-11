export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    email_verified_at?: string;
    role?: string;
}

/** Meta de buscadores y redes que arma App\Support\Seo (solo páginas públicas). */
export interface Seo {
    title: string;
    description: string;
    canonical: string;
    image?: string | null;
    type?: string;
    robots?: string;
    jsonld?: Record<string, unknown>;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    seo?: Seo;
};
