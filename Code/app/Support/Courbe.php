<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Calcule le dessin SVG d'une courbe « valeur en fonction du temps » (EF-C-07).
 *
 * Le dessin est fait côté serveur : aucune bibliothèque JavaScript, page
 * légère, et le résultat se vérifie dans les tests. La vue Blade
 * (components/courbe.blade.php) ne fait qu'afficher les coordonnées calculées ici.
 *
 * Quand deux mesures sont séparées de plus de COUPURE_SECONDES (boîtier muet,
 * coupure Wi-Fi), la ligne est interrompue : on ne relie pas des points entre
 * lesquels on ne sait rien.
 */
final class Courbe
{
    public const LARGEUR = 640;

    public const HAUTEUR = 200;

    public const MARGE_GAUCHE = 52;

    public const MARGE_DROITE = 12;

    public const MARGE_HAUT = 12;

    public const MARGE_BAS = 28;

    public const COUPURE_SECONDES = 1800;

    private float $valeurMin;

    private float $valeurMax;

    /**
     * @param  list<array{0: int, 1: float}>  $points  [horodatage Unix, valeur], triés par date
     */
    public function __construct(
        private readonly array $points,
        private readonly int $debut,
        private readonly int $fin,
        private readonly ?float $seuilMin = null,
        private readonly ?float $seuilMax = null,
        private readonly int $decimales = 1,
        private readonly string $fuseau = 'Europe/Paris',
    ) {
        $valeurs = array_column($points, 1);
        foreach ([$seuilMin, $seuilMax] as $seuil) {
            if ($seuil !== null) {
                $valeurs[] = $seuil;
            }
        }

        $min = $valeurs === [] ? 0.0 : (float) min($valeurs);
        $max = $valeurs === [] ? 1.0 : (float) max($valeurs);
        if ($max - $min < 1.0) {
            // Courbe presque plate : on ouvre un peu l'échelle pour qu'elle reste lisible.
            $min -= 0.5;
            $max += 0.5;
        }
        $marge = ($max - $min) * 0.1;
        $this->valeurMin = $min - $marge;
        $this->valeurMax = $max + $marge;
    }

    public function estVide(): bool
    {
        return $this->points === [];
    }

    /**
     * Morceaux de courbe à tracer, coupés aux trous de mesure.
     *
     * @return list<list<array{x: float, y: float}>>
     */
    public function segments(): array
    {
        $segments = [];
        $courant = [];
        $precedent = null;

        foreach ($this->points as [$horodatage, $valeur]) {
            if ($precedent !== null && $horodatage - $precedent > self::COUPURE_SECONDES) {
                $segments[] = $courant;
                $courant = [];
            }
            $courant[] = ['x' => $this->x($horodatage), 'y' => $this->y($valeur)];
            $precedent = $horodatage;
        }
        if ($courant !== []) {
            $segments[] = $courant;
        }

        return $segments;
    }

    /**
     * Graduations de l'axe vertical (5 valeurs régulières).
     *
     * @return list<array{y: float, libelle: string}>
     */
    public function graduationsY(): array
    {
        $graduations = [];
        for ($i = 0; $i <= 4; $i++) {
            $valeur = $this->valeurMin + ($this->valeurMax - $this->valeurMin) * $i / 4;
            $graduations[] = [
                'y' => $this->y($valeur),
                'libelle' => number_format($valeur, $this->decimales, ',', "\u{202F}"),
            ];
        }

        return $graduations;
    }

    /**
     * Graduations de l'axe horizontal : toutes les 6 heures, à l'heure pile.
     *
     * @return list<array{x: float, libelle: string}>
     */
    public function graduationsX(): array
    {
        $graduations = [];
        $premiere = (int) ceil($this->debut / 3600) * 3600;
        for ($horodatage = $premiere; $horodatage <= $this->fin; $horodatage += 6 * 3600) {
            $graduations[] = [
                'x' => $this->x($horodatage),
                'libelle' => CarbonImmutable::createFromTimestamp($horodatage, $this->fuseau)->format('H\hi'),
            ];
        }

        return $graduations;
    }

    /**
     * Lignes horizontales des seuils.
     *
     * @return list<array{y: float, libelle: string}>
     */
    public function lignesSeuils(): array
    {
        $lignes = [];
        if ($this->seuilMin !== null) {
            $lignes[] = ['y' => $this->y($this->seuilMin), 'libelle' => 'min'];
        }
        if ($this->seuilMax !== null) {
            $lignes[] = ['y' => $this->y($this->seuilMax), 'libelle' => 'max'];
        }

        return $lignes;
    }

    public function x(int $horodatage): float
    {
        $largeurUtile = self::LARGEUR - self::MARGE_GAUCHE - self::MARGE_DROITE;
        $position = ($horodatage - $this->debut) / max(1, $this->fin - $this->debut);

        return round(self::MARGE_GAUCHE + $position * $largeurUtile, 1);
    }

    public function y(float $valeur): float
    {
        $hauteurUtile = self::HAUTEUR - self::MARGE_HAUT - self::MARGE_BAS;
        $position = ($this->valeurMax - $valeur) / ($this->valeurMax - $this->valeurMin);

        return round(self::MARGE_HAUT + $position * $hauteurUtile, 1);
    }
}
