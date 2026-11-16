<?php

namespace App\Livewire\Alertes;

use App\Models\Alerte;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Alertes des serres du Jardin courant (EF-C-11) : en cours, puis historique
 * des 7 derniers jours. Rafraîchi chaque minute (wire:poll dans la vue).
 */
class ListeAlertes extends Component
{
    public const JOURS_HISTORIQUE = 7;

    public function mount(): void
    {
        $this->authorize('voir-serres');
    }

    public function render(): View
    {
        // Filtre par structure automatique : seulement les alertes du Jardin courant.
        return view('livewire.alertes.liste-alertes', [
            'enCours' => Alerte::query()->ouvertes()
                ->with(['serre', 'boitier'])
                ->orderByDesc('debut_le')
                ->get(),
            'historique' => Alerte::query()
                ->whereNotNull('fin_le')
                ->where('fin_le', '>=', now()->subDays(self::JOURS_HISTORIQUE))
                ->with('serre')
                ->orderByDesc('fin_le')
                ->get(),
        ]);
    }
}
