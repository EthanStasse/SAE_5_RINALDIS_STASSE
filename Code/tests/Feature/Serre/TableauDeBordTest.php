<?php

/*
 * Tableau de bord d'une serre : EF-C-07 (dernières valeurs et courbe sur 24 h),
 * droits et isolation entre Jardins.
 */

use App\Livewire\Serres\TableauDeBordSerre;
use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use App\Support\StructureCourante;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16T12:00:00Z'));
    $this->seed(RolesEtPermissionsSeeder::class);

    [$this->a, $this->b, $this->serreA, $this->serreB, $this->boitierA, $this->boitierB] =
        app(StructureCourante::class)->sansFiltre(function () {
            $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
            $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);
            $serreA = Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre nord', 'emplacement' => 'Parcelle 1']);
            $serreB = Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Tunnel B']);
            [$boitierA] = Boitier::creerPour($serreA, 'Boîtier nord');
            [$boitierB] = Boitier::creerPour($serreB, 'Boîtier B');

            return [$a, $b, $serreA, $serreB, $boitierA, $boitierB];
        });
});

/**
 * Enregistre une mesure pour un boîtier, il y a $minutes minutes.
 *
 * @param  array<string, float|null>  $valeurs
 */
function mesureIlYA(Boitier $boitier, int $minutes, array $valeurs): void
{
    DB::table('mesures')->insert(array_merge([
        'structure_id' => $boitier->structure_id,
        'serre_id' => $boitier->serre_id,
        'boitier_id' => $boitier->id,
        'uuid' => (string) Str::uuid(),
        'mesure_le' => now()->subMinutes($minutes),
        'temperature' => null,
        'humidite' => null,
        'luminosite' => null,
    ], $valeurs));
}

describe('page du tableau de bord', function () {
    it('est accessible aux salariés en insertion', function () {
        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get("/serres/{$this->serreA->id}")
            ->assertOk()
            ->assertSee('Serre nord')
            ->assertSee('Parcelle 1')
            ->assertSee('wire:poll.60s', false);
    });

    it('est refusée aux adhérents', function () {
        $this->actingAs(compteDans($this->a, 'adherent'))
            ->get("/serres/{$this->serreA->id}")
            ->assertForbidden();
    });

    it("ne trouve pas la serre d'un autre Jardin", function () {
        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get("/serres/{$this->serreB->id}")
            ->assertNotFound();
    });

    it('est accessible depuis la liste des serres', function () {
        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get('/serres')
            ->assertSee("/serres/{$this->serreA->id}\"", false);
    });
});

describe('dernières valeurs (EF-C-07)', function () {
    it('affiche la dernière valeur de chaque grandeur, à la française', function () {
        agirDans($this->a, 'insertion');
        mesureIlYA($this->boitierA, 10, ['temperature' => 19.0, 'humidite' => 70.0, 'luminosite' => 900.0]);
        mesureIlYA($this->boitierA, 5, ['temperature' => 21.4, 'humidite' => 65.5, 'luminosite' => 12500.0]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('21,4 °C', false)
            ->assertSee('65,5 %', false)
            ->assertSee("12\u{202F}500 lx", false)
            ->assertSee('Mesuré le 16/11 à 12h55');
    });

    it("garde la dernière valeur connue d'un capteur en panne", function () {
        agirDans($this->a, 'insertion');
        mesureIlYA($this->boitierA, 20, ['temperature' => 18.2, 'luminosite' => 300.0]);
        mesureIlYA($this->boitierA, 5, ['temperature' => null, 'luminosite' => 310.0]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('18,2 °C', false);
    });

    it('signale une dernière mesure ancienne', function () {
        agirDans($this->a, 'insertion');
        mesureIlYA($this->boitierA, 45, ['temperature' => 20.0]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('Dernière mesure ancienne');
    });

    it('indique une serre sans mesure', function () {
        agirDans($this->a, 'insertion');

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('Aucune mesure')
            ->assertSee('Aucune mesure sur les dernières 24 heures.');
    });

    it('compare la dernière valeur au seuil', function () {
        agirDans($this->a, 'insertion');
        $this->serreA->seuils()->create(['grandeur' => 'temperature', 'minimum' => 10, 'maximum' => 30]);
        $this->serreA->seuils()->create(['grandeur' => 'humidite', 'maximum' => 90]);
        mesureIlYA($this->boitierA, 5, ['temperature' => 31.0, 'humidite' => 60.0]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSeeInOrder(['Température', 'Hors seuil', "Humidité de l'air", 'Dans le seuil', 'Luminosité', 'Pas de seuil']);
    });
});

describe('courbes sur 24 h (EF-C-07)', function () {
    it('trace les mesures des dernières 24 heures seulement', function () {
        agirDans($this->a, 'insertion');
        mesureIlYA($this->boitierA, 25 * 60, ['temperature' => 5.0]);
        mesureIlYA($this->boitierA, 20, ['temperature' => 20.0]);
        mesureIlYA($this->boitierA, 15, ['temperature' => 21.0]);
        mesureIlYA($this->boitierA, 10, ['temperature' => 22.0]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('3 mesure(s)')
            ->assertSee('<polyline', false)
            ->assertSee('Température sur les dernières 24 heures');
    });

    it('dessine les seuils sur la courbe', function () {
        agirDans($this->a, 'insertion');
        $this->serreA->seuils()->create(['grandeur' => 'temperature', 'minimum' => 10, 'maximum' => 30]);
        mesureIlYA($this->boitierA, 10, ['temperature' => 20.0]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('data-seuil="min"', false)
            ->assertSee('data-seuil="max"', false);
    });
});

describe('isolation entre Jardins', function () {
    it("n'affiche jamais les mesures d'un autre Jardin", function () {
        agirDans($this->a, 'insertion');
        mesureIlYA($this->boitierA, 5, ['temperature' => 20.0]);
        mesureIlYA($this->boitierB, 2, ['temperature' => 77.7]);

        Livewire::test(TableauDeBordSerre::class, ['serre' => $this->serreA])
            ->assertSee('20,0 °C', false)
            ->assertDontSee('77,7');
    });
});
