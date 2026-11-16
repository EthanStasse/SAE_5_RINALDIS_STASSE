<?php

namespace App\Console\Commands;

use App\Services\SurveillanceSerres;
use Illuminate\Console\Command;

/**
 * Surveillance des serres de tous les Jardins (EF-C-09, EF-C-10).
 * Lancée chaque minute par le planificateur (routes/console.php).
 */
class SurveillerSerres extends Command
{
    protected $signature = 'serres:surveiller';

    protected $description = 'Ouvre et ferme les alertes des serres (seuils dépassés, capteurs muets)';

    public function handle(SurveillanceSerres $surveillance): int
    {
        $bilan = $surveillance->surveillerTout();

        $this->info(sprintf(
            '%d Jardin(s) surveillé(s) : %d alerte(s) ouverte(s), %d fermée(s).',
            $bilan['structures'],
            $bilan['ouvertes'],
            $bilan['fermees'],
        ));

        return self::SUCCESS;
    }
}
