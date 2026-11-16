<?php

namespace App\Livewire\Serres;

use App\Enums\Grandeur;
use App\Models\Mesure;
use App\Models\Serre;
use App\Models\Seuil;
use App\Support\Courbe;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Tableau de bord d'une serre (EF-C-07) : dernières valeurs et courbes sur 24 h.
 *
 * Accessible à tous ceux qui peuvent voir les serres (« voir-serres »).
 * Rafraîchi automatiquement chaque minute (wire:poll dans la vue).
 */
class TableauDeBordSerre extends Component
{
    /** Au-delà, la dernière valeur est signalée comme ancienne (préfigure EF-C-10). */
    public const MESURE_ANCIENNE_MINUTES = 30;

    public Serre $serre;

    public function mount(Serre $serre): void
    {
        $this->authorize('voir-serres');
        $this->serre = $serre;
    }

    public function render(): View
    {
        $fin = now()->toImmutable();
        $debut = $fin->subDay();

        // Toutes les requêtes passent par le filtre par structure (trait AppartientAStructure).
        $mesures24h = $this->serre->mesures()
            ->where('mesure_le', '>=', $debut)
            ->orderBy('mesure_le')
            ->get(['mesure_le', 'temperature', 'humidite', 'luminosite']);

        $seuils = $this->serre->seuils()->get()->keyBy(fn (Seuil $seuil) => $seuil->grandeur->value);

        $cartes = [];
        foreach (Grandeur::cases() as $grandeur) {
            $champ = $grandeur->value;
            $seuil = $seuils->get($champ);

            // Dernière valeur connue pour CETTE grandeur (un capteur peut être en panne).
            $derniere = $this->serre->mesures()
                ->whereNotNull($champ)
                ->latest('mesure_le')
                ->first(['mesure_le', $champ]);

            $points = $mesures24h
                ->filter(fn (Mesure $mesure) => $mesure->{$champ} !== null)
                ->map(fn (Mesure $mesure) => [$mesure->mesure_le->getTimestamp(), (float) $mesure->{$champ}])
                ->values()
                ->all();

            $valeur = $derniere?->{$champ};

            $cartes[] = [
                'grandeur' => $grandeur,
                'valeur' => $valeur,
                'mesureLe' => $derniere?->mesure_le,
                'ancienne' => $derniere !== null
                    && $derniere->mesure_le->lt($fin->subMinutes(self::MESURE_ANCIENNE_MINUTES)),
                'seuil' => $seuil,
                'horsSeuil' => $seuil !== null && $seuil->estDepassePar($valeur),
                'courbe' => new Courbe(
                    $points,
                    $debut->getTimestamp(),
                    $fin->getTimestamp(),
                    $seuil?->minimum,
                    $seuil?->maximum,
                    $grandeur->decimales(),
                ),
                'nombreMesures' => count($points),
            ];
        }

        return view('livewire.serres.tableau-de-bord-serre', [
            'alertes' => $this->serre->alertes()->ouvertes()->orderByDesc('debut_le')->get(),
            'cartes' => $cartes,
            'boitiers' => $this->serre->boitiers()->orderBy('nom')->get(),
        ]);
    }
}
