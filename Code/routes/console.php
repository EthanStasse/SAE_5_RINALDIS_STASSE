<?php

use App\Models\JournalConnexion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Surveillance des serres (EF-C-09, EF-C-10) : chaque minute, pour détecter
// un capteur muet au plus tard une minute après ses 30 minutes de silence.
Schedule::command('serres:surveiller')->everyMinute()->withoutOverlapping();

// ENF-RGPD : le journal des connexions (EF-D-11) est conservé 12 mois, puis supprimé.
// La nuit, pour ne pas ralentir l'application en journée.
Schedule::command('model:prune', ['--model' => [JournalConnexion::class]])->dailyAt('03:00');
