<?php

use Illuminate\Foundation\Inspiring;
use App\Services\Planes\AvisosVencimiento;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Correo a quien va a perder tickets sin usar o su Gestor Pro (ver AvisosVencimiento).
// Si no hay cron, la primera visita del día lo dispara igual.
Artisan::command('planes:avisar-vencimientos', function (AvisosVencimiento $avisos) {
    $this->info('Avisos enviados: '.$avisos->enviar());
})->purpose('Avisa por correo de tickets y planes por vencer');

Schedule::command('planes:avisar-vencimientos')->dailyAt('16:00'); // 10:00 en CDMX
