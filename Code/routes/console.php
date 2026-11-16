<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Surveillance des serres (EF-C-09, EF-C-10) : chaque minute, pour détecter
// un capteur muet au plus tard une minute après ses 30 minutes de silence.
Schedule::command('serres:surveiller')->everyMinute()->withoutOverlapping();
