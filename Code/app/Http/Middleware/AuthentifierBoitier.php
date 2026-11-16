<?php

namespace App\Http\Middleware;

use App\Models\Boitier;
use App\Support\StructureCourante;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie un boîtier capteur par son jeton (EF-C-02, EF-C-04).
 *
 * En-tête attendu : « Authorization: Bearer <jeton> ».
 *
 * Un boîtier n'est pas un utilisateur connecté : au début de la requête, il
 * n'y a donc aucune structure courante, et le filtre par structure cacherait
 * tous les boîtiers. On cherche donc le boîtier SANS filtre (c'est le seul
 * endroit de l'API où on le fait), puis on fixe la structure courante sur
 * celle du boîtier. Toute la suite de la requête est alors automatiquement
 * limitée à son Jardin : un boîtier ne peut ni lire ni écrire ailleurs.
 */
class AuthentifierBoitier
{
    private const ATTRIBUT = 'boitier';

    public function __construct(private StructureCourante $courante) {}

    public function handle(Request $request, Closure $next): Response
    {
        // On part toujours de zéro : rien ne doit rester d'une requête précédente.
        $this->courante->oublier();

        $jeton = $request->bearerToken();

        if ($jeton === null || $jeton === '') {
            return $this->refuser('Jeton de boîtier manquant.');
        }

        $boitier = $this->courante->sansFiltre(
            fn () => Boitier::query()->where('jeton_hash', Boitier::empreinte($jeton))->first()
        );

        // Même réponse pour un jeton inconnu ou révoqué : on ne renseigne pas un attaquant.
        if (! $boitier instanceof Boitier || $boitier->estRevoque()) {
            return $this->refuser('Jeton de boîtier invalide ou révoqué.');
        }

        $this->courante->definir($boitier->structure_id);
        $request->attributes->set(self::ATTRIBUT, $boitier);

        return $next($request);
    }

    /**
     * Boîtier authentifié de la requête, pour les contrôleurs de l'API.
     */
    public static function boitier(Request $request): Boitier
    {
        $boitier = $request->attributes->get(self::ATTRIBUT);

        if (! $boitier instanceof Boitier) {
            throw new LogicException('Cette route doit être protégée par AuthentifierBoitier.');
        }

        return $boitier;
    }

    private function refuser(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
