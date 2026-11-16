<?php

namespace App\Http\Controllers;

use App\Models\Serre;
use Illuminate\Contracts\View\View;
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

    /**
     * Page des boîtiers d'une serre (EF-C-02). La serre est chargée avec le
     * filtre par structure : une serre d'un autre Jardin donne un 404.
     */
    public function boitiers(Serre $serre): View
    {
        return view('serres.boitiers', ['serre' => $serre]);
    }

    /**
     * Page des seuils d'une serre (EF-C-08).
     */
    public function seuils(Serre $serre): View
    {
        return view('serres.seuils', ['serre' => $serre]);
    }
}
