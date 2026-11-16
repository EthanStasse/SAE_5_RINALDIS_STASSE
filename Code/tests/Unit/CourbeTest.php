<?php

/*
 * Calcul du dessin des courbes du tableau de bord (EF-C-07).
 */

use App\Support\Courbe;

beforeEach(function () {
    $this->debut = strtotime('2026-11-15T12:00:00Z');
    $this->fin = $this->debut + 86400;
});

it('place le début et la fin de la période aux bords du graphique', function () {
    $courbe = new Courbe([[$this->debut, 20.0]], $this->debut, $this->fin);

    expect($courbe->x($this->debut))->toBe((float) Courbe::MARGE_GAUCHE)
        ->and($courbe->x($this->fin))->toBe((float) (Courbe::LARGEUR - Courbe::MARGE_DROITE));
});

it('met les grandes valeurs en haut', function () {
    $courbe = new Courbe([[$this->debut, 10.0], [$this->debut + 600, 30.0]], $this->debut, $this->fin);

    expect($courbe->y(30.0))->toBeLessThan($courbe->y(10.0));
});

it('coupe la courbe quand il manque des mesures pendant plus de 30 minutes', function () {
    $courbe = new Courbe([
        [$this->debut + 600, 18.0],
        [$this->debut + 900, 19.0],
        [$this->debut + 900 + Courbe::COUPURE_SECONDES + 1, 21.0],
        [$this->debut + 900 + Courbe::COUPURE_SECONDES + 301, 22.0],
    ], $this->debut, $this->fin);

    expect(array_map('count', $courbe->segments()))->toBe([2, 2]);
});

it('garde les seuils visibles dans l\'échelle', function () {
    $courbe = new Courbe([[$this->debut, 20.0]], $this->debut, $this->fin, 5.0, 35.0);
    $haut = Courbe::MARGE_HAUT;
    $bas = Courbe::HAUTEUR - Courbe::MARGE_BAS;

    foreach ($courbe->lignesSeuils() as $ligne) {
        expect($ligne['y'])->toBeGreaterThan($haut)->toBeLessThan($bas);
    }
    expect($courbe->lignesSeuils())->toHaveCount(2);
});

it('reste lisible pour une valeur constante', function () {
    $courbe = new Courbe([[$this->debut, 50.0], [$this->debut + 300, 50.0]], $this->debut, $this->fin);

    $y = $courbe->y(50.0);
    expect($y)->toBeGreaterThan((float) Courbe::MARGE_HAUT)
        ->toBeLessThan((float) (Courbe::HAUTEUR - Courbe::MARGE_BAS));
});

it("gradue l'axe du temps toutes les 6 heures, à l'heure de Paris", function () {
    $courbe = new Courbe([], $this->debut, $this->fin);

    expect(array_column($courbe->graduationsX(), 'libelle'))
        ->toBe(['13h00', '19h00', '01h00', '07h00', '13h00']);
});

it('signale une courbe vide', function () {
    $courbe = new Courbe([], $this->debut, $this->fin);

    expect($courbe->estVide())->toBeTrue()
        ->and($courbe->segments())->toBe([]);
});
