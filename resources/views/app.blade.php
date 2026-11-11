<!DOCTYPE html>
<html lang="es-MX" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- SEO del lado del servidor: WhatsApp, Facebook y X no ejecutan JS.
             Cada página pública manda el prop `seo` (App\Support\Seo); sin él salen los valores por defecto.
             El título es el mismo que pone resources/js/app.ts en el cliente. --}}
        @php($seo = \App\Support\Seo::resolver($page['props']['seo'] ?? null))
        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        @if ($seo['robots'])
        <meta name="robots" content="{{ $seo['robots'] }}">
        @endif
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:site_name" content="{{ \App\Support\Seo::SITIO }}">
        <meta property="og:locale" content="es_MX">
        <meta property="og:type" content="{{ $seo['type'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $seo['image'] }}">
        @if ($seo['jsonld'])
        <script type="application/ld+json">{!! \App\Support\Seo::json($seo['jsonld']) !!}</script>
        @endif

        <!-- Fonts: Chakra Petch & Archivo -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        @php($fuentes = 'https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap')
        @if (str_starts_with($page['component'] ?? '', 'Propuestas/'))
        {{-- Landings: la hoja de Google no bloquea el primer pintado (en celular eran ~0.8 s).
             El hero espera a la fuente antes de mostrar el titular, así que no brinca. --}}
        <link rel="preload" as="style" href="{{ $fuentes }}">
        <link rel="stylesheet" href="{{ $fuentes }}" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="{{ $fuentes }}"></noscript>
        @else
        <link href="{{ $fuentes }}" rel="stylesheet">
        @endif
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-[#050505] text-gray-900 dark:text-gray-100">
        @inertia
    </body>
</html>