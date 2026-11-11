import '../css/app.css';
import './bootstrap';

import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Fijo: VITE_APP_NAME se hornea al compilar con el .env de quien compila
// (local decía "Laravel") y las pestañas salían "Mi cuenta - Laravel".
const appName = 'Rankit';

// usePage() no depende de un componente: lee la página actual del adaptador.
const pagina = usePage();

createInertiaApp({
    // Si la página manda el prop `seo` (App\Support\Seo), su título es el que
    // ya pintó el servidor y se usa tal cual: así no cambia al arrancar Vue.
    // Si no, "{título} - Rankit", sin repetir la marca cuando ya la trae.
    title: (title) => {
        const seo = pagina.props?.seo;
        if (seo?.title) return seo.title;
        if (!title) return appName;
        return /rankit/i.test(title) ? title : `${title} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
