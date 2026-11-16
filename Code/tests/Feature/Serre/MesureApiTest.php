<?php

/*
 * POST /api/v1/mesures : EF-C-04 (envoi avec jeton, date UTC), EF-C-05 (plages
 * plausibles, 422 explicite) et isolation entre Jardins.
 *
 * On relit la base avec DB::table() (non filtré) pour voir exactement ce qui
 * a été écrit, quel que soit le Jardin courant.
 */

use App\Models\Boitier;
use App\Models\Mesure;
use App\Models\Serre;
use App\Models\Structure;
use App\Support\StructureCourante;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    // Heure figée : les tests sur les dates ne dépendent pas du jour où ils tournent.
    $this->travelTo(Carbon::parse('2026-11-16T12:00:00Z'));
    $this->courante = app(StructureCourante::class);

    [$this->a, $this->b, $this->serreA, $this->serreB, $this->boitierA, $this->jetonA, $this->jetonB]
        = $this->courante->sansFiltre(function () {
            $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
            $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);
            $serreA = Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre A1']);
            $serreB = Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Serre B1']);
            [$boitierA, $jetonA] = Boitier::creerPour($serreA, 'Boîtier nord');
            [, $jetonB] = Boitier::creerPour($serreB, 'Boîtier tunnel');

            return [$a, $b, $serreA, $serreB, $boitierA, $jetonA, $jetonB];
        });
});

/**
 * Corps de requête tel que l'envoie la passerelle.
 *
 * @param  array<string, mixed>  $surcharge
 * @return array<string, mixed>
 */
function mesureValide(array $surcharge = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'mesure_le' => '2026-11-16T11:55:00Z',
        'temperature' => 21.4,
        'humidite' => 55.2,
        'luminosite' => 340.0,
    ], $surcharge);
}

describe('enregistrement', function () {
    it('enregistre une mesure valide dans la serre et le Jardin du boîtier', function () {
        $corps = mesureValide();

        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', $corps)
            ->assertCreated()
            ->assertJsonStructure(['id', 'alerte_active']);

        $ligne = DB::table('mesures')->sole();
        expect($ligne->uuid)->toBe($corps['uuid'])
            ->and($ligne->structure_id)->toBe($this->a->id)
            ->and($ligne->serre_id)->toBe($this->serreA->id)
            ->and($ligne->boitier_id)->toBe($this->boitierA->id)
            ->and((float) $ligne->temperature)->toBe(21.4)
            ->and((float) $ligne->humidite)->toBe(55.2)
            ->and((float) $ligne->luminosite)->toBe(340.0);
    });

    it('stocke la date en UTC quel que soit le fuseau envoyé', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['mesure_le' => '2026-11-16T12:55:00+01:00']))
            ->assertCreated();

        $mesureLe = Carbon::parse(DB::table('mesures')->value('mesure_le'));
        expect($mesureLe->utc()->toIso8601String())->toBe('2026-11-16T11:55:00+00:00');
    });

    it('accepte une mesure dont un capteur est en erreur', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['temperature' => null, 'humidite' => null]))
            ->assertCreated();

        expect(DB::table('mesures')->value('temperature'))->toBeNull();
    });

    it('accepte les valeurs aux limites des plages', function (string $champ, float $valeur) {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide([$champ => $valeur]))
            ->assertCreated();
    })->with([
        'température minimale' => ['temperature', -40],
        'température maximale' => ['temperature', 80],
        'humidité 0 %' => ['humidite', 0],
        'humidité 100 %' => ['humidite', 100],
        'obscurité totale' => ['luminosite', 0],
        'luminosité maximale' => ['luminosite', 65535],
    ]);

    it('ne crée pas de doublon quand la passerelle renvoie la même mesure', function () {
        $corps = mesureValide();

        $premiere = $this->withToken($this->jetonA)->postJson('/api/v1/mesures', $corps)->assertCreated();
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', $corps)
            ->assertOk()
            ->assertJsonPath('id', $premiere->json('id'));

        expect(DB::table('mesures')->count())->toBe(1);
    });

    it('accepte le même uuid venant de deux boîtiers différents', function () {
        $corps = mesureValide();

        $this->withToken($this->jetonA)->postJson('/api/v1/mesures', $corps)->assertCreated();
        $this->withToken($this->jetonB)->postJson('/api/v1/mesures', $corps)->assertCreated();

        expect(DB::table('mesures')->count())->toBe(2);
    });

    it('note la date de dernière communication du boîtier', function () {
        $this->withToken($this->jetonA)->postJson('/api/v1/mesures', mesureValide())->assertCreated();

        $derniere = DB::table('boitiers')->where('id', $this->boitierA->id)->value('derniere_communication_le');
        expect(Carbon::parse($derniere)->equalTo(now()))->toBeTrue();
    });
});

describe('refus (EF-C-05)', function () {
    it('refuse une valeur hors des plages plausibles avec un message explicite', function (string $champ, float $valeur, string $message) {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide([$champ => $valeur]))
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$champ}.0", $message);

        expect(DB::table('mesures')->count())->toBe(0);
    })->with([
        'température trop basse' => ['temperature', -40.1, 'La température doit être comprise entre -40 et 80 °C.'],
        'température trop haute' => ['temperature', 999, 'La température doit être comprise entre -40 et 80 °C.'],
        'humidité négative' => ['humidite', -0.1, "L'humidité doit être comprise entre 0 et 100 %."],
        'humidité au-delà de 100 %' => ['humidite', 100.1, "L'humidité doit être comprise entre 0 et 100 %."],
        'luminosité négative' => ['luminosite', -1, 'La luminosité doit être comprise entre 0 et 65 535 lx.'],
        'luminosité impossible' => ['luminosite', 65536, 'La luminosité doit être comprise entre 0 et 65 535 lx.'],
    ]);

    it('refuse une mesure sans aucune valeur', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['temperature' => null, 'humidite' => null, 'luminosite' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.temperature.0', 'La mesure doit contenir au moins une valeur (température, humidité ou luminosité).');
    });

    it('refuse une valeur qui n\'est pas un nombre', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['temperature' => 'chaud']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['temperature']);
    });

    it('refuse une date mal formée ou sans fuseau', function (string $date) {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['mesure_le' => $date]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mesure_le']);
    })->with([
        'sans fuseau' => '2026-11-16T11:55:00',
        'format français' => '16/11/2026 11:55',
        'expression relative' => 'yesterday',
        'date inexistante' => '2026-02-30T10:00:00Z',
    ]);

    it('refuse une date dans le futur', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['mesure_le' => '2026-11-16T12:10:00Z']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mesure_le']);
    });

    it('tolère une horloge de passerelle en avance de moins de 5 minutes', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['mesure_le' => '2026-11-16T12:04:00Z']))
            ->assertCreated();
    });

    it('refuse un uuid absent ou invalide', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide(['uuid' => 'pas-un-uuid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['uuid']);
    });

    it("refuse une mesure sans jeton et n'enregistre rien", function () {
        $this->postJson('/api/v1/mesures', mesureValide())->assertUnauthorized();

        expect(DB::table('mesures')->count())->toBe(0);
    });
});

describe('isolation entre Jardins', function () {
    it('ignore une serre ou un Jardin indiqués dans la requête', function () {
        $this->withToken($this->jetonA)
            ->postJson('/api/v1/mesures', mesureValide([
                'structure_id' => $this->b->id,
                'serre_id' => $this->serreB->id,
            ]))
            ->assertCreated();

        $ligne = DB::table('mesures')->sole();
        expect($ligne->structure_id)->toBe($this->a->id)
            ->and($ligne->serre_id)->toBe($this->serreA->id);
    });

    it('range les mesures de chaque boîtier dans son propre Jardin', function () {
        $this->withToken($this->jetonA)->postJson('/api/v1/mesures', mesureValide())->assertCreated();
        $this->withToken($this->jetonB)->postJson('/api/v1/mesures', mesureValide())->assertCreated();

        $this->courante->definir($this->a->id);
        expect($this->serreA->mesures()->count())->toBe(1)
            ->and(Mesure::count())->toBe(1);

        $this->courante->definir($this->b->id);
        expect(Mesure::count())->toBe(1)
            ->and(Mesure::first()?->serre_id)->toBe($this->serreB->id);
    });
});
