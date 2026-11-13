<?php

namespace App\Http\Controllers;

use App\Models\Serre;
use Illuminate\Http\JsonResponse;

/**
 * Contrôleur minimal, utilisé par les tests d'isolation. Le module C le complétera.
 */
class SerreController extends Controller
{
    public function show(Serre $serre): JsonResponse
    {
        return response()->json([
            'id' => $serre->id,
            'nom' => $serre->nom,
            'emplacement' => $serre->emplacement,
        ]);
    }
}
