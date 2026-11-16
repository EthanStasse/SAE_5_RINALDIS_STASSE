<?php

/*
 * Boîtiers capteurs et mesures : EF-C-02, EF-C-04 et isolation entre Jardins.
 *
 * Les tests « en base » utilisent volontairement DB::table() (non filtré) pour
 * contourner les modèles : ils prouvent que PostgreSQL protège les données
 * même si le code PHP se trompe.
 */

use App\Exceptions\StructureNonAutoriseeException;
use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use App\Support\StructureCourante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->courante = app(StructureCourante::class);

    [$this->a, $this->b, $this->serreA, $this->serreB] = $this->courante->sansFiltre(function () {
        $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
        $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);

        return [
            $a,
            $b,
            Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre A1']),
            Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Serre B1']),
        ];
    });
});

/**
 * @param  array<string, mixed>  $valeurs
 */
function insererMesureBrute(Boitier $boitier, array $valeurs = []): void
{
    DB::table('mesures')->insert(array_merge([
        'structure_id' => $boitier->structure_id,
        'serre_id' => $boitier->serre_id,
        'boitier_id' => $boitier->id,
        'uuid' => (string) Str::uuid(),
        'mesure_le' => now(),
        'temperature' => 21.5,
    ], $valeurs));
}

describe('jeton du boîtier', function () {
    it('crée un boîtier dans la structure de sa serre', function () {
        $this->courante->definir($this->a->id);

        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        expect($boitier->structure_id)->toBe($this->a->id)
            ->and($boitier->serre_id)->toBe($this->serreA->id);
    });

    it("ne stocke que l'empreinte du jeton, jamais le jeton en clair", function () {
        $this->courante->definir($this->a->id);

        [$boitier, $jeton] = Boitier::creerPour($this->serreA, 'Boîtier nord');
        $ligne = DB::table('boitiers')->where('id', $boitier->id)->first();

        expect($jeton)->toHaveLength(Boitier::LONGUEUR_JETON)
            ->and($ligne->jeton_hash)->toBe(hash('sha256', $jeton))
            ->and($ligne->jeton_prefixe)->toBe(substr($jeton, 0, 8))
            ->and($boitier->toArray())->not->toHaveKey('jeton_hash');
    });

    it("invalide l'ancien jeton quand on en génère un nouveau", function () {
        $this->courante->definir($this->a->id);
        [$boitier, $ancien] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        $nouveau = $boitier->genererJeton();

        expect($nouveau)->not->toBe($ancien)
            ->and(Boitier::where('jeton_hash', Boitier::empreinte($ancien))->exists())->toBeFalse()
            ->and(Boitier::where('jeton_hash', Boitier::empreinte($nouveau))->exists())->toBeTrue();
    });

    it('révoque un boîtier puis le réactive avec un nouveau jeton', function () {
        $this->courante->definir($this->a->id);
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        $boitier->revoquer();
        expect($boitier->fresh()?->estRevoque())->toBeTrue()
            ->and(Boitier::actifs()->count())->toBe(0);

        $boitier->genererJeton();
        expect($boitier->fresh()?->estRevoque())->toBeFalse()
            ->and(Boitier::actifs()->count())->toBe(1);
    });
});

describe('isolation entre Jardins', function () {
    it("refuse de créer un boîtier dans la serre d'un autre Jardin", function () {
        $this->courante->definir($this->a->id);

        expect(fn () => Boitier::creerPour($this->serreB, 'Boîtier pirate'))
            ->toThrow(StructureNonAutoriseeException::class);
    });

    it("ne voit pas les boîtiers d'un autre Jardin", function () {
        $this->courante->definir($this->a->id);
        [$boitierA] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        $this->courante->definir($this->b->id);

        expect(Boitier::find($boitierA->id))->toBeNull();
    });

    it("refuse en base un boîtier rattaché à la serre d'un autre Jardin", function () {
        expect(fn () => DB::table('boitiers')->insert([
            'structure_id' => $this->b->id,
            'serre_id' => $this->serreA->id,
            'nom' => 'Boîtier pirate',
            'jeton_hash' => str_repeat('a', 64),
            'jeton_prefixe' => 'aaaaaaaa',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it("refuse en base une mesure attribuée au Jardin d'un autre boîtier", function () {
        $this->courante->definir($this->a->id);
        [$boitierA] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        expect(fn () => insererMesureBrute($boitierA, [
            'structure_id' => $this->b->id,
            'serre_id' => $this->serreB->id,
        ]))->toThrow(QueryException::class);
    });
});

describe('mesures', function () {
    it('accepte une mesure dont un capteur est en erreur', function () {
        $this->courante->definir($this->a->id);
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        insererMesureBrute($boitier, ['temperature' => null, 'humidite' => 55.2]);

        expect($boitier->mesures()->count())->toBe(1);
    });

    it('refuse en base une mesure sans aucune valeur', function () {
        $this->courante->definir($this->a->id);
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier nord');

        expect(fn () => insererMesureBrute($boitier, ['temperature' => null]))
            ->toThrow(QueryException::class);
    });

    it('refuse en base deux mesures avec le même uuid pour un boîtier', function () {
        $this->courante->definir($this->a->id);
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier nord');
        $uuid = (string) Str::uuid();

        insererMesureBrute($boitier, ['uuid' => $uuid]);

        expect(fn () => insererMesureBrute($boitier, ['uuid' => $uuid]))
            ->toThrow(QueryException::class);
    });
});
