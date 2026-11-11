<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Meta para buscadores y redes de las páginas públicas.
 *
 * Las páginas son Inertia sin SSR: Google ejecuta el JS, pero WhatsApp,
 * Facebook y X solo leen el HTML del servidor. Cada página pública manda un
 * prop `seo` (Seo::pagina() o Seo::torneo()) y resources/views/app.blade.php
 * lo pinta con Seo::resolver(). Sin prop salen los valores por defecto.
 *
 * El título que se arma aquí es el definitivo: resources/js/app.ts lo usa tal
 * cual en el cliente, así el <title> no cambia cuando arranca Vue.
 */
final class Seo
{
    public const SITIO = 'Rankit';

    /**
     * Páginas fijas. Títulos de 50 a 60 caracteres y descripciones de unos 150.
     * 'componente' es la página Vue: su fecha de modificación da el lastmod del
     * sitemap. Las que llevan 'robots' => 'noindex…' no van al sitemap.
     */
    private const PAGINAS = [
        'inicio' => [
            'ruta' => '/',
            'componente' => 'Propuestas/LandingK',
            'title' => 'Rankit: torneos de Fortnite en México con tabla en vivo',
            'description' => 'Crea y juega torneos y scrims de Fortnite en México con inscripciones, resultados automáticos y tabla en vivo. También ligas de LoL, Valorant y fútbol.',
        ],
        'league' => [
            'ruta' => '/league',
            'componente' => 'RankitLeague',
            'title' => 'Rankit League: liga competitiva de Fortnite en México',
            'description' => 'Circuito de 4 semanas de Fortnite en Solitario: dos clasificatorios, repechaje y gran final. Inscríbete en la liga de Rankit y sigue la tabla en vivo.',
        ],
        'semanales' => [
            'ruta' => '/semanales',
            'componente' => 'Semanales/Index',
            'title' => 'Torneos semanales de Fortnite gratis con premios | Rankit',
            'description' => 'Cada semana un torneo de Fortnite con entrada gratis, cupo abierto y premios en efectivo. Inscríbete en línea y recibe por correo tus códigos de partida.',
        ],
        'duels' => [
            'ruta' => '/duels',
            'componente' => 'Duels/Index',
            'title' => 'Retas 1v1 y wagers de Fortnite: muy pronto en Rankit',
            'description' => 'Cobro de inscripciones en la plataforma, duelos 1v1 con bolsa resguardada y wagers entre equipos. Lo que viene en Rankit para organizadores y streamers.',
        ],
        'mundial' => [
            'ruta' => '/mundial',
            'componente' => 'WorldCup',
            'title' => 'Mundial 2026: campeón, bracket y estadísticas | Rankit',
            'description' => 'Resumen del Mundial 2026 en Rankit: campeón, camino al título, tablas de posiciones, goleadores y récords del torneo, todo en una sola página.',
        ],
        // Es un panel con código de acceso y datos de ejemplo, no una página
        // para buscadores: lleva meta para compartir, pero no se indexa.
        'torneos' => [
            'ruta' => '/torneos',
            'componente' => 'Torneos/Index',
            'title' => 'Torneos de Fortnite y esports abiertos en México | Rankit',
            'description' => 'Todos los torneos públicos de Rankit: inscripciones abiertas, próximos y ya jugados, con su tabla en vivo. Encuentra dónde competir.',
        ],
        'bellzcup' => [
            'ruta' => '/BellzCup',
            'componente' => 'BellzCup/Index',
            'title' => 'BellzCup: códigos de sala y tabla del torneo | Rankit',
            'description' => 'Panel de la BellzCup en Rankit con los códigos de sala y la tabla de posiciones del torneo de Fortnite. Se entra con el código de los organizadores.',
            'robots' => 'noindex, follow',
        ],
    ];

    /** Meta de una página fija. $cambios sobrescribe lo que haga falta. */
    public static function pagina(string $clave, array $cambios = []): array
    {
        $p = self::PAGINAS[$clave];

        $meta = [
            'title' => $p['title'],
            'description' => $p['description'],
            'canonical' => self::url($p['ruta']),
            'type' => 'website',
        ];

        if (isset($p['robots'])) {
            $meta['robots'] = $p['robots'];
        }

        if ($clave === 'inicio') {
            $meta['jsonld'] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => self::SITIO,
                'url' => self::url('/'),
                'inLanguage' => 'es-MX',
            ];
        }

        return array_merge($meta, $cambios);
    }

    /**
     * Meta de un torneo de Fortnite (fila de la tabla tournaments).
     *
     * El canonical es siempre /t/{id}: es la página real con la tabla en vivo,
     * la misma que se abre por /live/{id} y /t/{slug}, y no lleva ?code=.
     * Los privados no se indexan ni llevan JSON-LD.
     */
    public static function torneo(object $torneo): array
    {
        $nombre = trim((string) ($torneo->name ?? '')) ?: 'Torneo de Fortnite';
        $canonical = self::url('/t/'.(int) $torneo->id);
        $privado = (bool) ($torneo->is_private ?? false);
        $inicio = self::fechaInicio($torneo->start_date ?? null);

        $fecha = $inicio ? ' Se juega el '.$inicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY').'.' : '';
        $abiertas = ($torneo->registration_status ?? null) === 'open' ? ' Inscripciones abiertas.' : '';
        $larga = "Sigue {$nombre} en Rankit: tabla en vivo, resultados de cada partida y clasificación del torneo de Fortnite.";
        $corta = "{$nombre}: torneo de Fortnite con tabla en vivo en Rankit.";

        // La primera que quepa en 160; con nombres largos se sacrifica la frase
        // antes que la fecha, y solo al final se corta el nombre.
        $description = collect([$larga.$fecha.$abiertas, $corta.$fecha.$abiertas, $corta.$fecha, $corta])
            ->first(fn (string $texto) => mb_strlen($texto) <= 160)
            ?? Str::limit($corta, 159, '…', preserveWords: true);

        $banner = trim((string) ($torneo->banner_image ?? ''));
        $imagen = $banner === '' ? null : self::imagen(self::esUrl($banner) ? $banner : 'public/'.ltrim($banner, '/'));

        $meta = [
            'title' => self::tituloTorneo($nombre),
            'description' => $description,
            'canonical' => $canonical,
            'type' => 'website',
            'image' => $imagen,
        ];

        if ($privado) {
            $meta['robots'] = 'noindex, nofollow';

            return $meta;
        }

        $jsonld = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $nombre,
            'description' => $meta['description'],
            'url' => $canonical,
            'sport' => 'Fortnite',
            'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
            'location' => ['@type' => 'VirtualLocation', 'url' => $canonical],
            'organizer' => ['@type' => 'Organization', 'name' => self::SITIO, 'url' => self::url('/')],
        ];

        if ($inicio) {
            // Sin hora capturada (medianoche) solo se publica el día.
            $jsonld['startDate'] = $inicio->format('H:i:s') === '00:00:00'
                ? $inicio->toDateString()
                : $inicio->toIso8601String();
        }
        if ($imagen) {
            $jsonld['image'] = [$imagen];
        }
        if ((bool) ($torneo->is_free ?? false)) {
            $jsonld['isAccessibleForFree'] = true;
        }

        $precio = self::precio($torneo->ticket_price ?? null);
        if ($precio !== null) {
            $jsonld['offers'] = [
                '@type' => 'Offer',
                'price' => $precio,
                'priceCurrency' => 'MXN',
                'url' => $canonical,
            ];
        }

        $meta['jsonld'] = $jsonld;

        return $meta;
    }

    /**
     * Completa el prop `seo` con los valores por defecto. Lo usa app.blade.php.
     *
     * @return array{title: string, description: string, canonical: string, image: string, type: string, robots: ?string, jsonld: ?array}
     */
    public static function resolver(mixed $seo): array
    {
        $seo = is_array($seo) ? $seo : [];
        $texto = fn (string $clave) => trim((string) ($seo[$clave] ?? ''));

        return [
            'title' => $texto('title') ?: self::SITIO,
            'description' => $texto('description') ?: self::PAGINAS['inicio']['description'],
            'canonical' => $texto('canonical') ?: self::url(request()->path()),
            'image' => self::imagen($texto('image') ?: null),
            'type' => $texto('type') ?: 'website',
            'robots' => $texto('robots') ?: null,
            'jsonld' => ! empty($seo['jsonld']) && is_array($seo['jsonld']) ? $seo['jsonld'] : null,
        ];
    }

    /** JSON-LD listo para ir dentro de <script>: escapa < y > para que un nombre no cierre la etiqueta. */
    public static function json(array $datos): string
    {
        return (string) json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * Páginas fijas que van al sitemap: [url absoluta => timestamp del lastmod o null].
     *
     * @return array<string, int|null>
     */
    public static function paginasSitemap(): array
    {
        $paginas = [];

        foreach (self::PAGINAS as $p) {
            if (str_starts_with($p['robots'] ?? '', 'noindex')) {
                continue;
            }

            $archivo = resource_path('js/Pages/'.$p['componente'].'.vue');
            $paginas[self::url($p['ruta'])] = is_file($archivo) ? (filemtime($archivo) ?: null) : null;
        }

        return $paginas;
    }

    /** URL absoluta contra el dominio canónico (config seo.url). */
    public static function url(string $ruta = '/'): string
    {
        $base = rtrim((string) (config('seo.url') ?: url('/')), '/');
        $ruta = '/'.ltrim($ruta, '/');

        return $ruta === '/' ? $base.'/' : $base.$ruta;
    }

    /** URL absoluta de una imagen; sin ruta usa la imagen por defecto (config seo.imagen). */
    public static function imagen(?string $ruta): string
    {
        $ruta = trim((string) $ruta) ?: (string) config('seo.imagen');

        return self::esUrl($ruta) ? $ruta : self::url($ruta);
    }

    /** "{nombre}: tabla en vivo de Fortnite | Rankit", o solo "{nombre} | Rankit" si no cabe en 60. */
    private static function tituloTorneo(string $nombre): string
    {
        $largo = $nombre.': tabla en vivo de Fortnite | '.self::SITIO;
        if (mb_strlen($largo) <= 60) {
            return $largo;
        }

        $sufijo = ' | '.self::SITIO;

        return Str::limit($nombre, 60 - mb_strlen($sufijo) - 1, '…', preserveWords: true).$sufijo;
    }

    private static function fechaInicio(mixed $valor): ?Carbon
    {
        if (empty($valor)) {
            return null;
        }

        try {
            return Carbon::parse((string) $valor, 'America/Mexico_City');
        } catch (\Throwable) {
            return null;
        }
    }

    /** ticket_price es texto libre ("150", "$150 MXN"…): solo se usa si trae un número claro. */
    private static function precio(mixed $valor): ?string
    {
        $limpio = str_replace([',', '$', 'MXN', 'mxn', ' '], '', (string) $valor);

        return is_numeric($limpio) && (float) $limpio > 0
            ? number_format((float) $limpio, 2, '.', '')
            : null;
    }

    private static function esUrl(string $ruta): bool
    {
        return (bool) preg_match('#^https?://#i', $ruta);
    }
}
