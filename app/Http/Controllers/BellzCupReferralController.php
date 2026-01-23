<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BellzCupReferralController extends Controller
{
    public function redeem(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user(); // invitado (quien mete el código)
        $code = trim(strtolower($request->string('code')->value()));

        // Formato esperado: {digits}rankit  => 22rankit
        if (!preg_match('/^(\d+)rankit$/', $code, $m)) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido. Ejemplo válido: 22rankit',
            ]);
        }

        $invitadorId = (int) $m[1];
        $invitadoId  = (int) $user->id;

        // Reglas básicas (evitar abuso)
        if ($invitadorId <= 0) {
            throw ValidationException::withMessages(['code' => 'Código inválido.']);
        }
        if ($invitadorId === $invitadoId) {
            throw ValidationException::withMessages(['code' => 'No puedes usar tu propio código.']);
        }

        DB::transaction(function () use ($invitadorId, $invitadoId, $code) {

            // 1) Validar que invitador exista como usuario
            $invitador = DB::table('users')->where('id', $invitadorId)->first();
            if (!$invitador) {
                throw ValidationException::withMessages(['code' => 'Ese código no corresponde a un usuario válido.']);
            }

            // 2) Evitar que el invitado use más de un código (1 sola vez)
            $alreadyUsed = DB::table('bellzcup_referidospoints')
                ->where('invitado', $invitadoId)
                ->exists();

            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'code' => 'Ya ingresaste un código anteriormente.',
                ]);
            }

            // 3) Evitar duplicado exacto (por si acaso)
            $duplicate = DB::table('bellzcup_referidospoints')
                ->where('invitador', $invitadorId)
                ->where('invitado', $invitadoId)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'Este referido ya fue registrado.',
                ]);
            }

            // 4) Insert en referidos
            DB::table('bellzcup_referidospoints')->insert([
                'invitador'  => $invitadorId,
                'invitado'   => $invitadoId,
                'code'       => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5) Upsert en rifapoints: +2 al invitador
            //    Si no existe => crea con totalpoints=2
            $rifaRow = DB::table('bellzcup_rifapoints')
                ->where('userid', $invitadorId)
                ->lockForUpdate()
                ->first();

            if ($rifaRow) {
                DB::table('bellzcup_rifapoints')
                    ->where('userid', $invitadorId)
                    ->update([
                        'totalpoints' => (int) $rifaRow->totalpoints + 2,
                        'updated_at'  => now(),
                    ]);
            } else {
                DB::table('bellzcup_rifapoints')->insert([
                    'userid'      => $invitadorId,
                    'username'    => $invitador->name ?? ('user_'.$invitadorId),
                    'totalpoints' => 2,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        });

        return back()->with('success', '¡Código aplicado! Se sumaron +2 puntos al invitador.');
    }
}
