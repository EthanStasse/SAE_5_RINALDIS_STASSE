<?php

use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Retire cette ligne si RefreshDatabase est déjà activé pour tout tests/Feature dans tests/Pest.php.
uses(RefreshDatabase::class);

// On relit la base avec DB::table pour ne pas dépendre du filtre automatique
// par structure (hors requête HTTP, aucune structure courante n'est définie).
function ligneBoitier(int $id): object
{
    return DB::table('boitiers')->where('id', $id)->first();
}

it('crée un boîtier dans la structure de sa serre', function () {
    $serre = Serre::factory()->create();

    [$boitier] = Boitier::creerPour($serre, 'Boîtier nord');

    expect(ligneBoitier($boitier->id)->structure_id)->toBe($serre->structure_id);
});

it('ne stocke que l\'empreinte du jeton, jamais le jeton en clair', function () {
    [$boitier, $jeton] = Boitier::creerPour(Serre::factory()->create(), 'Boîtier nord');

    $ligne = ligneBoitier($boitier->id);

    expect($jeton)->toHaveLength(Boitier::LONGUEUR_JETON)
        ->and($ligne->jeton_hash)->toBe(hash('sha256', $jeton))
        ->and($ligne->jeton_hash)->not->toContain($jeton)
        ->and($ligne->jeton_prefixe)->toBe(substr($jeton, 0, 8))
        ->and($boitier->toArray())->not->toHaveKey('jeton_hash');
});

it('invalide l\'ancien jeton quand on en génère un nouveau', function () {
    [$boitier, $ancien] = Boitier::creerPour(Serre::factory()->create(), 'Boîtier nord');

    $nouveau = $boitier->genererJeton();

    expect($nouveau)->not->toBe($ancien)
        ->and(ligneBoitier($boitier->id)->jeton_hash)->toBe(hash('sha256', $nouveau));
});

it('révoque un boîtier, puis le réactive avec un nouveau jeton', function () {
    [$boitier] = Boitier::creerPour(Serre::factory()->create(), 'Boîtier nord');

    $boitier->revoquer();
    expect($boitier->estRevoque())->toBeTrue()
        ->and(ligneBoitier($boitier->id)->revoque_le)->not->toBeNull();

    $boitier->genererJeton();
    expect($boitier->estRevoque())->toBeFalse();
});

it('refuse en base un boîtier rattaché à la serre d\'une autre structure', function () {
    $serreDuJardinA = Serre::factory()->create();
    $jardinB = Structure::factory()->create();

    // On contourne volontairement le modèle pour prouver que c'est la BASE qui protège.
    $insertion = fn () => DB::table('boitiers')->insert([
        'structure_id' => $jardinB->id,
        'serre_id' => $serreDuJardinA->id,
        'nom' => 'Boîtier pirate',
        'jeton_hash' => str_repeat('a', 64),
        'jeton_prefixe' => 'aaaaaaaa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($insertion)->toThrow(QueryException::class);
});

it('refuse en base une mesure sans aucune valeur', function () {
    [$boitier] = Boitier::creerPour(Serre::factory()->create(), 'Boîtier nord');

    $insertion = fn () => DB::table('mesures')->insert([
        'structure_id' => $boitier->structure_id,
        'serre_id' => $boitier->serre_id,
        'boitier_id' => $boitier->id,
        'uuid' => (string) str()->uuid(),
        'mesure_le' => now(),
    ]);

    expect($insertion)->toThrow(QueryException::class);
});

it('refuse en base deux mesures avec le même uuid pour un boîtier', function () {
    [$boitier] = Boitier::creerPour(Serre::factory()->create(), 'Boîtier nord');
    $mesure = [
        'structure_id' => $boitier->structure_id,
        'serre_id' => $boitier->serre_id,
        'boitier_id' => $boitier->id,
        'uuid' => (string) str()->uuid(),
        'mesure_le' => now(),
        'temperature' => 21.5,
    ];

    DB::table('mesures')->insert($mesure);

    expect(fn () => DB::table('mesures')->insert($mesure))->toThrow(QueryException::class);
});
