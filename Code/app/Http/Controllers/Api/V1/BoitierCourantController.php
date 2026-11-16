<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthentifierBoitier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/boitier : « qui suis-je ? ».
 *
 * Permet à la passerelle (et à l'installateur) de vérifier qu'un jeton est
 * valide et de voir à quelle serre le boîtier est rattaché.
 */
class BoitierCourantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $boitier = AuthentifierBoitier::boitier($request);

        // La serre est chargée AVEC le filtre par structure : si elle s'affiche,
        // c'est que la structure courante est bien celle du boîtier.
        $serre = $boitier->serre;

        return response()->json([
            'id' => $boitier->id,
            'nom' => $boitier->nom,
            'serre' => $serre === null ? null : [
                'id' => $serre->id,
                'nom' => $serre->nom,
            ],
        ]);
    }
}
