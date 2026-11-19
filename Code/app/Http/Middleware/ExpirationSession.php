<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * EF-D-10 : expiration de la session.
 * - après 30 minutes sans activité pour le personnel (postes partagés, droits larges) ;
 * - après 2 heures sans activité pour les adhérents ;
 * - 12 heures au plus après la connexion, même avec une activité continue.
 *
 * Limite connue : les pages qui se rafraîchissent seules (wire:poll) comptent
 * comme une activité ; la limite des 12 heures s'applique toujours.
 */
class ExpirationSession
{
    public const INACTIVITE_PERSONNEL_MINUTES = 30;

    public const INACTIVITE_ADHERENT_MINUTES = 120;

    public const DUREE_MAX_HEURES = 12;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $maintenant = now()->getTimestamp();

        // Première requête après la connexion : on note le début et la règle du compte.
        if (! $session->has('session_debut')) {
            $session->put('session_debut', $maintenant);
            $session->put('session_inactivite_max', $user->estPersonnel()
                ? self::INACTIVITE_PERSONNEL_MINUTES
                : self::INACTIVITE_ADHERENT_MINUTES);
            $session->put('session_derniere_activite', $maintenant);

            return $next($request);
        }

        $inactif = $maintenant - (int) $session->get('session_derniere_activite');
        $depuisConnexion = $maintenant - (int) $session->get('session_debut');

        if ($inactif > (int) $session->get('session_inactivite_max') * 60
            || $depuisConnexion > self::DUREE_MAX_HEURES * 3600) {
            // Déconnexion complète : la session est détruite, le jeton CSRF renouvelé.
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('status', __('auth.expired'));
        }

        $session->put('session_derniere_activite', $maintenant);

        return $next($request);
    }
}
