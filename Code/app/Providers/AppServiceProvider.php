<?php

namespace App\Providers;

use App\Models\Alerte;
use App\Models\JournalConnexion;
use App\Models\Structure;
use App\Models\User;
use App\Support\StructureCourante;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        // EF-D-11 : journal des connexions, à partir des événements de Laravel.
        // Connexion complète : après le code 2FA pour un administrateur.
        Event::listen(function (Login $evenement): void {
            JournalConnexion::noter(JournalConnexion::REUSSIE, (int) $evenement->user->getAuthIdentifier());
        });
        // Échec : on retrouve le compte visé par l'adresse saisie, sans garder l'adresse.
        Event::listen(function (Failed $evenement): void {
            $email = Str::lower((string) ($evenement->credentials['email'] ?? ''));
            $userId = User::query()->where('email', $email)->value('id');

            JournalConnexion::noter(JournalConnexion::ECHOUEE, $userId === null ? null : (int) $userId);
        });

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
