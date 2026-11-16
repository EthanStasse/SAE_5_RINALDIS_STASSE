<?php

/*
 * Seuils minimum et maximum par serre et par grandeur : EF-C-08, droits et
 * isolation entre Jardins.
 */

use App\Enums\Grandeur;
use App\Livewire\Serres\ReglageSeuils;
use App\Models\Serre;
use App\Models\Seuil;
use App\Models\Structure;
use App\Support\StructureCourante;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);

    [$this->a, $this->b, $this->serreA, $this->serreB] = app(StructureCourante::class)->sansFiltre(function () {
        $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
        $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);

        return [
            $a,
            $b,
            Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre nord']),
            Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Tunnel B']),
        ];
    });
});

/** Seuils enregistrés en base pour une serre, indexés par grandeur. */
function seuilsEnBase(int $serreId): array
{
    return DB::table('seuils')->where('serre_id', $serreId)->orderBy('id')->get()
        ->mapWithKeys(fn ($ligne) => [$ligne->grandeur => [
            'minimum' => $ligne->minimum === null ? null : (float) $ligne->minimum,
            'maximum' => $ligne->maximum === null ? null : (float) $ligne->maximum,
            'structure_id' => $ligne->structure_id,
        ]])->all();
}

describe('page des seuils', function () {
    it("ouvre la page à l'équipe", function () {
        $this->actingAs(compteDans($this->a, 'equipe'))
            ->get("/serres/{$this->serreA->id}/seuils")
            ->assertOk()
            ->assertSee('Seuils de la serre « Serre nord »')
            ->assertSee('Luminosité');
    });

    it('refuse la page aux salariés en insertion', function () {
        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get("/serres/{$this->serreA->id}/seuils")
            ->assertForbidden();
    });

    it("ne trouve pas la serre d'un autre Jardin", function () {
        $this->actingAs(compteDans($this->a, 'equipe'))
            ->get("/serres/{$this->serreB->id}/seuils")
            ->assertNotFound();
    });

    it('propose les seuils depuis la liste des serres', function () {
        $this->actingAs(compteDans($this->a, 'equipe'))
            ->get('/serres')
            ->assertSee("/serres/{$this->serreA->id}/seuils");
    });
});

describe('réglage (EF-C-08)', function () {
    it('enregistre les seuils dans le Jardin de la serre', function () {
        agirDans($this->a, 'equipe');

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->set('valeurs.temperature.minimum', '10')
            ->set('valeurs.temperature.maximum', '30,5')
            ->set('valeurs.humidite.maximum', '90')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Les seuils de la serre ont été enregistrés.');

        expect(seuilsEnBase($this->serreA->id))->toBe([
            'temperature' => ['minimum' => 10.0, 'maximum' => 30.5, 'structure_id' => $this->a->id],
            'humidite' => ['minimum' => null, 'maximum' => 90.0, 'structure_id' => $this->a->id],
        ]);
    });

    it('affiche les seuils existants', function () {
        agirDans($this->a, 'equipe');
        $this->serreA->seuils()->create(['grandeur' => 'luminosite', 'minimum' => 200, 'maximum' => 40000]);

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->assertSet('valeurs.luminosite.minimum', '200')
            ->assertSet('valeurs.luminosite.maximum', '40000')
            ->assertSet('valeurs.temperature.minimum', '');
    });

    it('modifie un seuil existant sans en créer un second', function () {
        agirDans($this->a, 'equipe');
        $this->serreA->seuils()->create(['grandeur' => 'temperature', 'minimum' => 5, 'maximum' => 35]);

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->set('valeurs.temperature.maximum', '28')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(seuilsEnBase($this->serreA->id)['temperature']['maximum'])->toBe(28.0)
            ->and(DB::table('seuils')->count())->toBe(1);
    });

    it('supprime le seuil quand les deux champs sont vidés', function () {
        agirDans($this->a, 'equipe');
        $this->serreA->seuils()->create(['grandeur' => 'temperature', 'minimum' => 5, 'maximum' => 35]);

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->set('valeurs.temperature.minimum', '')
            ->set('valeurs.temperature.maximum', '')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(seuilsEnBase($this->serreA->id))->toBe([]);
    });

    it('refuse un minimum supérieur ou égal au maximum', function () {
        agirDans($this->a, 'equipe');

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->set('valeurs.temperature.minimum', '30')
            ->set('valeurs.temperature.maximum', '30')
            ->call('enregistrer')
            ->assertHasErrors('valeurs.temperature.maximum')
            ->assertSee('Le maximum doit être supérieur au minimum (Température).');

        expect(seuilsEnBase($this->serreA->id))->toBe([]);
    });

    it('refuse une borne hors de la plage plausible ou non numérique', function (string $champ, string $saisie) {
        agirDans($this->a, 'equipe');

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->set("valeurs.{$champ}", $saisie)
            ->call('enregistrer')
            ->assertHasErrors("valeurs.{$champ}");

        expect(seuilsEnBase($this->serreA->id))->toBe([]);
    })->with([
        'température impossible' => ['temperature.maximum', '120'],
        'humidité négative' => ['humidite.minimum', '-5'],
        'luminosité impossible' => ['luminosite.maximum', '70000'],
        'texte' => ['temperature.minimum', 'froid'],
    ]);
});

describe('droits et isolation', function () {
    it('interdit le réglage aux salariés en insertion', function () {
        agirDans($this->a, 'insertion');

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->assertForbidden();
    });

    it("ne touche pas aux seuils d'un autre Jardin", function () {
        DB::table('seuils')->insert([
            'structure_id' => $this->b->id,
            'serre_id' => $this->serreB->id,
            'grandeur' => 'temperature',
            'minimum' => 1,
            'maximum' => 2,
        ]);
        agirDans($this->a, 'equipe');

        Livewire::test(ReglageSeuils::class, ['serre' => $this->serreA])
            ->set('valeurs.temperature.minimum', '10')
            ->call('enregistrer');

        expect(seuilsEnBase($this->serreB->id)['temperature']['minimum'])->toBe(1.0);
    });

    it("refuse en base un seuil rattaché à la serre d'un autre Jardin", function () {
        expect(fn () => DB::table('seuils')->insert([
            'structure_id' => $this->a->id,
            'serre_id' => $this->serreB->id,
            'grandeur' => 'temperature',
            'maximum' => 30,
        ]))->toThrow(QueryException::class);
    });

    it('refuse en base un minimum supérieur au maximum', function () {
        expect(fn () => DB::table('seuils')->insert([
            'structure_id' => $this->a->id,
            'serre_id' => $this->serreA->id,
            'grandeur' => 'temperature',
            'minimum' => 30,
            'maximum' => 10,
        ]))->toThrow(QueryException::class);
    });
});

describe('dépassement de seuil', function () {
    it('détecte une valeur hors du seuil', function (?float $minimum, ?float $maximum, ?float $valeur, bool $attendu) {
        $seuil = new Seuil(['grandeur' => Grandeur::Temperature, 'minimum' => $minimum, 'maximum' => $maximum]);

        expect($seuil->estDepassePar($valeur))->toBe($attendu);
    })->with([
        'dans le seuil' => [10, 30, 20, false],
        'égal au minimum' => [10, 30, 10, false],
        'égal au maximum' => [10, 30, 30, false],
        'trop froid' => [10, 30, 9.9, true],
        'trop chaud' => [10, 30, 30.1, true],
        'maximum seul' => [null, 30, -40, false],
        'minimum seul' => [10, null, 80, false],
        'capteur en panne' => [10, 30, null, false],
    ]);
});
