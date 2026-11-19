<?php

namespace App\Providers;

use App\Models\Alerte;
use App\Models\Structure;
use App\Support\StructureCourante;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une seule instance par requête (ou par tâche de file d'attente).
        $this->app->scoped(StructureCourante::class);
    }

    public function boot(): void
    {
        // EF-D-05 : politique de mot de passe (CNIL, délibération 2022-100).
        // Appliquée à la création, la réinitialisation et au changement,
        // jamais à la connexion. Pas d'expiration forcée.
        Password::defaults(fn () => Password::min(12)
            ->max(64)
            ->mixedCase()
            ->numbers()
            ->symbols());

        // Le Jardin courant est affiché dans l'en-tête de toutes les pages.
        View::composer(['components.layouts.app', 'accueil'], function ($view): void {
            $id = app(StructureCourante::class)->id();
            $view->with('structureCourante', $id === null ? null : Structure::find($id));

            // EF-C-11 : nombre d'alertes en cours, affiché dans le menu.
            $view->with('nombreAlertes', $id !== null && (auth()->user()?->can('voir-serres') ?? false)
                ? Alerte::query()->ouvertes()->count()
                : 0);
        });
    }
}