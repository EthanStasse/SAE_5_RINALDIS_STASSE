<?php

namespace App\Enums;

/**
 * Grandeurs mesurées dans une serre (EF-C-03).
 *
 * Source unique des libellés, unités et plages plausibles, utilisée par
 * l'API (EF-C-05), les seuils (EF-C-08), le tableau de bord et les alertes.
 *
 * Plages plausibles = plages de mesure des capteurs (fiches techniques) :
 * DHT22 de -40 à 80 °C et de 0 à 100 %, luminosité de 0 à 65 535 lx.
 */
enum Grandeur: string
{
    case Temperature = 'temperature';
    case Humidite = 'humidite';
    case Luminosite = 'luminosite';

    public function libelle(): string
    {
        return match ($this) {
            self::Temperature => 'Température',
            self::Humidite => "Humidité de l'air",
            self::Luminosite => 'Luminosité',
        };
    }

    public function unite(): string
    {
        return match ($this) {
            self::Temperature => '°C',
            self::Humidite => '%',
            self::Luminosite => 'lx',
        };
    }

    public function minimumPlausible(): int
    {
        return match ($this) {
            self::Temperature => -40,
            self::Humidite, self::Luminosite => 0,
        };
    }

    public function maximumPlausible(): int
    {
        return match ($this) {
            self::Temperature => 80,
            self::Humidite => 100,
            self::Luminosite => 65535,
        };
    }
}
