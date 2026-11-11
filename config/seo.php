<?php

/*
|--------------------------------------------------------------------------
| SEO y tarjetas para compartir
|--------------------------------------------------------------------------
|
| Lo usa App\Support\Seo. WhatsApp, Facebook y X no ejecutan JavaScript:
| solo leen lo que pinta resources/views/app.blade.php desde el servidor.
|
*/

return [

    // Dominio canónico. https://www.rankit.pro también responde (sin
    // redirigir), así que canonical, og:url y el sitemap se arman siempre
    // contra esta base y no contra el host de la petición.
    'url' => env('SEO_URL', 'https://rankit.pro'),

    // Imagen para compartir cuando la página no trae una propia. Ruta desde
    // la raíz del sitio o URL completa. En el hosting la raíz es el proyecto,
    // no public/, por eso lleva "public/" (igual que los banners de torneo).
    // Lo ideal es una imagen de 1200x630; hoy no hay ninguna y se usa el
    // póster de la gran final (vertical, las redes lo recortan al centro).
    'imagen' => env('SEO_IMAGEN', 'public/league/gran-final.png'),

];
