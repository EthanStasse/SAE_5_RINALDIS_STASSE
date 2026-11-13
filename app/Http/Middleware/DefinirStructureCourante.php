<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\StructureCourante;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Définit la structure courante pour la requête d'un utilisateur connecté :
 * celle choisie en session si le compte y a un rôle, sinon la première.
 *
 * Doit s'exécuter AVANT SubstituteBindings (voir bootstrap/app.php), sinon les
 * modèles liés aux routes seraient chargés sans structure et renverraient 404.
 */
class DefinirStructureCourante
{
    public function __construct(private StructureCourante $courante) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $ids = $user->structureIds();

        if ($ids->isEmpty()) {
            abort(403, "Ce compte n'est rattaché à aucune structure.");
        }

        $demandee = $request->hasSession() ? (int) $request->session()->get('structure_id', 0) : 0;
        $id = $ids->contains($demandee) ? $demandee : $ids->first();

        $this->courante->definir($id);
        setPermissionsTeamId($id);

        if ($request->hasSession()) {
            $request->session()->put('structure_id', $id);
        }

        return $next($request);
    }
}
