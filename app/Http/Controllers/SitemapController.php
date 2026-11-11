<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GET /sitemap.xml — páginas públicas fijas + torneos públicos (/t/{id}).
 *
 * La tabla tournaments la crea el código en el primer uso
 * (TournamentParserController::ensureDatabaseIsReady), así que puede no
 * existir: en ese caso, o si la consulta falla, salen solo las páginas fijas.
 */
class SitemapController extends Controller
{
    /** Tope por si algún día hay miles: el estándar admite 50,000 URLs por archivo. */
    private const MAX_TORNEOS = 5000;

    public function __invoke(): Response
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach (Seo::paginasSitemap() as $url => $modificada) {
            $this->url($xml, $url, $modificada ? date('Y-m-d', $modificada) : null);
        }

        foreach ($this->torneosPublicos() as $torneo) {
            $fecha = $torneo->updated_at ?? $torneo->created_at ?? null;
            $this->url($xml, Seo::url('/t/'.$torneo->id), $fecha ? substr((string) $fecha, 0, 10) : null);
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function url(\XMLWriter $xml, string $loc, ?string $lastmod): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $loc);
        if ($lastmod) {
            $xml->writeElement('lastmod', $lastmod);
        }
        $xml->endElement();
    }

    /** @return iterable<object> */
    private function torneosPublicos(): iterable
    {
        try {
            if (!Schema::hasTable('tournaments')) {
                return [];
            }

            $columnas = ['id'];
            foreach (['updated_at', 'created_at'] as $columna) {
                if (Schema::hasColumn('tournaments', $columna)) {
                    $columnas[] = $columna;
                }
            }

            $consulta = DB::table('tournaments')->select($columnas);

            // is_private nulo cuenta como público, igual que en PublicTournamentController.
            if (Schema::hasColumn('tournaments', 'is_private')) {
                $consulta->where(fn ($q) => $q->where('is_private', false)->orWhereNull('is_private'));
            }

            if (in_array('updated_at', $columnas, true)) {
                $consulta->orderByDesc('updated_at');
            }

            return $consulta->orderByDesc('id')->limit(self::MAX_TORNEOS)->get();
        } catch (\Throwable $e) {
            Log::warning('[Sitemap] No se pudieron leer los torneos: '.$e->getMessage());

            return [];
        }
    }
}
