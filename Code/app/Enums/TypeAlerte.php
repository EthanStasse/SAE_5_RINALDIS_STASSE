<?php

namespace App\Enums;

/**
 * Types d'alerte d'une serre.
 */
enum TypeAlerte: string
{
    /** EF-C-09 : une grandeur sort de son seuil sur 2 mesures consécutives. */
    case Seuil = 'seuil';

    /** EF-C-10 : un boîtier n'a rien envoyé depuis 30 minutes. */
    case CapteurMuet = 'capteur_muet';

    public function libelle(): string
    {
        return match ($this) {
            self::Seuil => 'Seuil dépassé',
            self::CapteurMuet => 'Capteur muet',
        };
    }
}
