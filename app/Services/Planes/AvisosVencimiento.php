<?php

namespace App\Services\Planes;

use App\Models\PlanPeriod;
use App\Models\User;
use App\Notifications\PlanPorVencer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Correo "se te van a vencer los tickets / el Gestor Pro", una sola vez por
 * periodo. Corre con `php artisan planes:avisar-vencimientos` (programado a
 * diario) y, como en el hosting no hay cron confirmado, también se despierta
 * solo con la primera visita de cada día (middleware DespertarAvisosVencimiento),
 * después de mandar la respuesta.
 */
class AvisosVencimiento
{
    public function __construct(private PlanOrganizador $planes)
    {
    }

    /** Mismo patrón que PlanOrganizador::asegurarTablas(). */
    public static function asegurarTabla(): void
    {
        if (Schema::hasTable('plan_period_avisos')) {
            return;
        }

        Schema::create('plan_period_avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_period_id')->unique()->constrained('plan_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('sent_at')->nullable();
        });
    }

    /** A lo más una vez al día por servidor; el trabajo va después de responder. */
    public static function despertar(): void
    {
        if ((int) config('planes.aviso_vencimiento_dias', 0) <= 0) {
            return;
        }

        if (!Cache::add('planes:avisos-vencimiento:'.now()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        app()->terminating(function () {
            try {
                app(self::class)->enviar();
            } catch (Throwable $e) {
                Log::error('Planes: falló la revisión de vencimientos', ['error' => $e->getMessage()]);
            }
        });
    }

    /** @return int a cuántos usuarios se les mandó correo */
    public function enviar(): int
    {
        $dias = (int) config('planes.aviso_vencimiento_dias', 0);
        if ($dias <= 0) {
            return 0;
        }

        PlanOrganizador::asegurarTablas();
        self::asegurarTabla();

        $avisados = DB::table('plan_period_avisos')->pluck('plan_period_id')->all();

        $usuarios = PlanPeriod::vigentes()
            ->where('ends_at', '<=', now()->addDays($dias))
            ->whereNotIn('id', $avisados)
            ->distinct()
            ->pluck('user_id');

        $enviados = 0;
        foreach ($usuarios as $userId) {
            $user = User::find($userId);
            if (!$user || $this->planes->esExento($user)) {
                continue;
            }

            $aviso = $this->planes->porVencer($this->planes->periodosVigentes($user));
            $nuevos = $aviso ? array_values(array_diff($aviso['periodos'], $avisados)) : [];
            if (!$nuevos) {
                continue;
            }

            try {
                $user->notify(new PlanPorVencer($aviso['tickets'], $aviso['vence'], $aviso['dias']));
            } catch (Throwable $e) {
                // Sin marcar: mañana se vuelve a intentar.
                Log::error('Planes: no se pudo mandar el aviso de vencimiento', ['user' => $user->id, 'error' => $e->getMessage()]);
                continue;
            }

            DB::table('plan_period_avisos')->insertOrIgnore(array_map(fn (int $id) => [
                'plan_period_id' => $id,
                'user_id' => $user->id,
                'sent_at' => now(),
            ], $aviso['periodos']));

            $enviados++;
        }

        if ($enviados) {
            Log::info('Planes: avisos de vencimiento enviados', ['usuarios' => $enviados]);
        }

        return $enviados;
    }
}
