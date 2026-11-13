<?php

namespace App\Providers;

use App\Support\StructureCourante;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une seule instance par requête (ou par tâche de file d'attente).
        $this->app->scoped(StructureCourante::class);
    }

    public function boot(): void
    {
        //
    }
}
