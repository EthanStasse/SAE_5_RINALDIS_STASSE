<?php

namespace App\Http\Controllers;

use App\Models\Serre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pages d'une serre (module C).
 */
class SerreController extends Controller
{
    /**
     * Tableau de bord de la serre (EF-C-07). En JSON si le client le demande
     * (utilisé par les tests d'isolation).
     */
    public function show(Request $request, Serre $serre): JsonResponse|View
    {
        if (! $request->expectsJson()) {
            return view('serres.show', ['serre' => $serre]);
        }

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
