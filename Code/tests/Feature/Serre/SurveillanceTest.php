<?php

/*
 * Surveillance des serres : EF-C-09 (seuil dépassé sur 2 mesures consécutives),
 * EF-C-10 (capteur muet depuis 30 minutes), tâche planifiée et isolation.
 */

use App\Enums\TypeAlerte;
use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use App\Support\StructureCourante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16T12:00:00Z'));

    [$this->a, $this->b, $this->serreA, $this->serreB, $this->boitierA, $this->jetonA, $this->boitierB] =
        app(StructureCourante::class)->sansFiltre(function () {
            $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
            $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);
            $serreA = Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre nord']);
            $serreB = Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Tunnel B']);
            [$boitierA, $jetonA] = Boitier::creerPour($serreA, 'Boîtier nord');
            [$boitierB] = Boitier::creerPour($serreB, 'Boîtier B');

            return [$a, $b, $serreA, $serreB, $boitierA, $jetonA, $boitierB];
        });

    // Les boîtiers viennent de communiquer : aucun n'est muet au départ.
    DB::table('boitiers')->update(['derniere_communication_le' => now()]);

    // Seuil de température 10 à 30 °C dans la serre du Jardin A.
    DB::table('seuils')->insert([
        'structure_id' => $this->a->id,
        'serre_id' => $this->serreA->id,
        'grandeur' => 'temperature',
        'minimum' => 10,
        'maximum' => 30,
    ]);
});

/** Ajoute une mesure de température pour un boîtier, il y a $minutes minutes. */
function temperatureIlYA(Boitier $boitier, int $minutes, ?float $temperature, ?float $humidite = null): void
{
    DB::table('mesures')->insert([
        'structure_id' => $boitier->structure_id,
        'serre_id' => $boitier->serre_id,
        'boitier_id' => $boitier->id,
        'uuid' => (string) Str::uuid(),
        'mesure_le' => now()->subMinutes($minutes),
        'temperature' => $temperature,
        'humidite' => $humidite ?? 50.0,
    ]);
}

/** @return list<object> */
function alertesOuvertes(?string $type = null): array
{
    return DB::table('alertes')->whereNull('fin_le')
        ->when($type, fn ($requete) => $requete->where('type', $type))
        ->orderBy('id')->get()->all();
}

function surveiller(): void
{
    test()->artisan('serres:surveiller')->assertSuccessful();
}

describe('seuil dépassé (EF-C-09)', function () {
    it('ne déclenche rien sur une seule mesure hors seuil', function () {
        temperatureIlYA($this->boitierA, 10, 20.0);
        temperatureIlYA($this->boitierA, 5, 35.0);

        surveiller();

        expect(alertesOuvertes())->toBe([]);
    });

    it('déclenche une alerte sur 2 mesures consécutives hors seuil', function () {
        temperatureIlYA($this->boitierA, 10, 31.0);
        temperatureIlYA($this->boitierA, 5, 32.5);

        surveiller();

        $alertes = alertesOuvertes();
        expect($alertes)->toHaveCount(1)
            ->and($alertes[0]->type)->toBe('seuil')
            ->and($alertes[0]->grandeur)->toBe('temperature')
            ->and((float) $alertes[0]->valeur)->toBe(32.5)
            ->and($alertes[0]->structure_id)->toBe($this->a->id)
            ->and($alertes[0]->serre_id)->toBe($this->serreA->id)
            ->and($alertes[0]->message)->toBe(
                'Température trop haute dans la serre « Serre nord » : 32,5 °C (seuil de 10,0 °C à 30,0 °C).'
            );
    });

    it('détecte aussi une valeur trop basse', function () {
        temperatureIlYA($this->boitierA, 10, 8.0);
        temperatureIlYA($this->boitierA, 5, 7.5);

        surveiller();

        expect(alertesOuvertes()[0]->message)->toContain('Température trop basse');
    });

    it('ignore les mesures où le capteur était en panne', function () {
        temperatureIlYA($this->boitierA, 15, 31.0);
        temperatureIlYA($this->boitierA, 10, null);
        temperatureIlYA($this->boitierA, 5, 31.5);

        surveiller();

        expect(alertesOuvertes())->toHaveCount(1);
    });

    it("n'ouvre pas deux fois la même alerte", function () {
        temperatureIlYA($this->boitierA, 10, 31.0);
        temperatureIlYA($this->boitierA, 5, 32.0);

        surveiller();
        surveiller();

        expect(DB::table('alertes')->count())->toBe(1);
    });

    it('ferme l\'alerte dès que la température revient dans le seuil', function () {
        temperatureIlYA($this->boitierA, 10, 31.0);
        temperatureIlYA($this->boitierA, 5, 32.0);
        surveiller();

        temperatureIlYA($this->boitierA, 1, 25.0);
        surveiller();

        expect(alertesOuvertes())->toBe([])
            ->and(DB::table('alertes')->whereNotNull('fin_le')->count())->toBe(1);
    });

    it('ferme les alertes d\'un seuil supprimé', function () {
        temperatureIlYA($this->boitierA, 10, 31.0);
        temperatureIlYA($this->boitierA, 5, 32.0);
        surveiller();

        DB::table('seuils')->delete();
        surveiller();

        expect(alertesOuvertes())->toBe([]);
    });
});

describe('capteur muet (EF-C-10)', function () {
    it('déclenche une alerte après 30 minutes de silence', function () {
        $this->travel(31)->minutes();

        surveiller();

        $alertes = alertesOuvertes(TypeAlerte::CapteurMuet->value);
        expect($alertes)->toHaveCount(2)
            ->and(collect($alertes)->pluck('boitier_id')->all())->toBe([$this->boitierA->id, $this->boitierB->id])
            ->and($alertes[0]->message)->toBe(
                "Le boîtier « Boîtier nord » de la serre « Serre nord » n'a rien envoyé depuis le 16/11 à 13h00."
            );
    });

    it('ne déclenche rien avant 30 minutes', function () {
        $this->travel(29)->minutes();

        surveiller();

        expect(alertesOuvertes())->toBe([]);
    });

    it('ignore un boîtier révoqué', function () {
        $this->boitierA->revoquer();
        $this->travel(31)->minutes();

        surveiller();

        expect(collect(alertesOuvertes())->pluck('boitier_id')->all())->toBe([$this->boitierB->id]);
    });

    it('ferme l\'alerte dès que le boîtier envoie une mesure', function () {
        $this->travel(31)->minutes();
        surveiller();

        app(StructureCourante::class)->oublier();
        $this->withToken($this->jetonA)->postJson('/api/v1/mesures', [
            'uuid' => (string) Str::uuid(),
            'mesure_le' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'temperature' => 20.0,
        ])->assertCreated();

        expect(collect(alertesOuvertes())->pluck('boitier_id')->all())->toBe([$this->boitierB->id]);
    });
});

describe('réponse au boîtier (LED et buzzer)', function () {
    it('indique alerte_active dès la 2ᵉ mesure hors seuil, puis la retire', function () {
        $envoyer = fn (float $temperature) => $this->withToken($this->jetonA)->postJson('/api/v1/mesures', [
            'uuid' => (string) Str::uuid(),
            'mesure_le' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'temperature' => $temperature,
        ]);

        $envoyer(31.0)->assertCreated()->assertJsonPath('alerte_active', false);
        $this->travel(5)->minutes();
        $envoyer(32.0)->assertCreated()->assertJsonPath('alerte_active', true);
        $this->travel(5)->minutes();
        $envoyer(24.0)->assertCreated()->assertJsonPath('alerte_active', false);
    });
});

describe('isolation entre Jardins', function () {
    it('range chaque alerte dans le Jardin de sa serre', function () {
        $this->travel(31)->minutes();

        surveiller();

        expect(DB::table('alertes')->where('boitier_id', $this->boitierB->id)->value('structure_id'))->toBe($this->b->id)
            ->and(DB::table('alertes')->where('boitier_id', $this->boitierA->id)->value('structure_id'))->toBe($this->a->id);
    });

    it("refuse en base une alerte sur la serre d'un autre Jardin", function () {
        expect(fn () => DB::table('alertes')->insert([
            'structure_id' => $this->a->id,
            'serre_id' => $this->serreB->id,
            'type' => 'seuil',
            'grandeur' => 'temperature',
            'message' => 'test',
            'debut_le' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('refuse en base deux alertes ouvertes pour la même cause', function () {
        $alerte = [
            'structure_id' => $this->a->id,
            'serre_id' => $this->serreA->id,
            'type' => 'seuil',
            'grandeur' => 'temperature',
            'message' => 'test',
            'debut_le' => now(),
        ];
        DB::table('alertes')->insert($alerte);

        expect(fn () => DB::table('alertes')->insert($alerte))->toThrow(QueryException::class);
    });
});

it('est planifiée chaque minute', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('serres:surveiller')
        ->assertSuccessful();
});
