<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthentifierBoitier;
use App\Http\Requests\Api\V1\EnregistrerMesureRequest;
use App\Models\Boitier;
use App\Models\Mesure;
use App\Services\SurveillanceSerres;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * POST /api/v1/mesures : réception d'une mesure d'un boîtier (EF-C-04, EF-C-05).
 *
 * Réponses :
 *   201 mesure enregistrée
 *   200 mesure déjà reçue (même uuid) : la passerelle a renvoyé après un timeout
 *   401 jeton absent, invalide ou révoqué (middleware)
 *   422 valeur hors plage ou donnée mal formée, avec le détail par champ
 */
class MesureController extends Controller
{
    public function __construct(private SurveillanceSerres $surveillance) {}

    public function store(EnregistrerMesureRequest $request): JsonResponse
    {
        $boitier = AuthentifierBoitier::boitier($request);
        $donnees = $request->validated();

        // Idempotence : renvoyer la même mesure ne crée pas de doublon.
        $existante = $boitier->mesures()->where('uuid', $donnees['uuid'])->first();

        if ($existante !== null) {
            return $this->repondre($boitier, $existante, 200);
        }

        $mesure = new Mesure([
            'uuid' => $donnees['uuid'],
            // Stockage en UTC, quel que soit le fuseau envoyé (EF-C-04).
            'mesure_le' => Carbon::parse($donnees['mesure_le'])->utc(),
            'temperature' => $donnees['temperature'] ?? null,
            'humidite' => $donnees['humidite'] ?? null,
            'luminosite' => $donnees['luminosite'] ?? null,
        ]);
        // La serre vient du boîtier, jamais de la requête. structure_id est
        // rempli par le trait AppartientAStructure avec le Jardin du boîtier.
        $mesure->serre_id = $boitier->serre_id;
        $mesure->boitier_id = $boitier->id;

        try {
            $mesure->save();
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés du même uuid : le second arrive ici.
            $deja = $boitier->mesures()->where('uuid', $donnees['uuid'])->firstOrFail();

            return $this->repondre($boitier, $deja, 200);
        }

        // EF-C-09 : vérification immédiate des seuils, sans attendre la tâche
        // planifiée, pour que la réponse indique déjà l'alerte au boîtier.
        if ($boitier->serre !== null) {
            $this->surveillance->verifierSeuils($boitier->serre);
        }

        return $this->repondre($boitier, $mesure, 201);
    }

    private function repondre(Boitier $boitier, Mesure $mesure, int $statut): JsonResponse
    {
        // Sert à l'alerte « capteur muet » (EF-C-10) : le boîtier vient de parler.
        $boitier->derniere_communication_le = now()->toImmutable();
        $boitier->save();
        $this->surveillance->signalerCommunication($boitier);

        return response()->json([
            'id' => $mesure->id,
            // Une alerte est ouverte dans la serre : le boîtier allume sa LED et son buzzer.
            'alerte_active' => $boitier->serre !== null && $this->surveillance->serreEnAlerte($boitier->serre),
        ], $statut);
    }
}
