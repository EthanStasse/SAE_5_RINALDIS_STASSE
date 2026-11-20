<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EF-D-08 : double authentification obligatoire pour les administrateurs
 * (admin-reseau, admin-structure, dans n'importe quel Jardin).
 * Tant qu'elle n'est pas confirmée, toute page renvoie vers l'activation.
 */
class ExigerDoubleAuthentification
{
    /** Ce qu'un administrateur sans 2FA peut encore faire : l'activer, ou se déconnecter. */
    private const CHEMINS_AUTORISES = [
        'securite/double-authentification',
        'user/two-factor-*',
        'user/confirmed-two-factor-authentication',
        'user/confirm-password',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || $user->hasEnabledTwoFactorAuthentication()
            || $request->is(...self::CHEMINS_AUTORISES)
            || ! $user->estAdministrateur()) {
            return $next($request);
        }

        return redirect()->route('double-authentification')
            ->with('avertissement', 'Pour protéger les données des Jardins, activez la double authentification avant de continuer.');
    }
}
