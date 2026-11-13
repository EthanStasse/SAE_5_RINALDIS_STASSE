<?php

/*
 * Tests d'isolation entre structures : EF-S-01, EF-S-02, US-S1 du cahier des charges.
 * Tout nouveau modèle métier doit ajouter ses propres tests sur ce modèle.
 */

use App\Exceptions\StructureNonAutoriseeException;
use App\Models\Serre;
use App\Models\Structure;
use App\Models\User;
use App\Support\StructureCourante;
use Database\Seeders\RolesEtPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);
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

function membre(Structure $structure, string $role = 'equipe'): User
{
    $user = User::factory()->create();
    setPermissionsTeamId($structure->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

describe('filtre Eloquent', function () {
    it('ne renvoie que les données de la structure courante', function () {
        $this->courante->definir($this->a->id);

        expect(Serre::pluck('nom')->all())->toBe(['Serre A1']);
    });

    it("ne trouve pas une donnée d'une autre structure par son identifiant", function () {
        $this->courante->definir($this->a->id);

        expect(Serre::find($this->serreB->id))->toBeNull();
    });

    it('ne renvoie rien sans structure courante', function () {
        expect(Serre::count())->toBe(0);
    });

    it('rattache une nouvelle donnée à la structure courante', function () {
        $this->courante->definir($this->a->id);

        $serre = Serre::create(['nom' => 'Serre A2']);

        expect($serre->structure_id)->toBe($this->a->id);
    });

    it('refuse de créer une donnée dans une autre structure', function () {
        $this->courante->definir($this->a->id);

        expect(fn () => Serre::create(['nom' => 'Intrus', 'structure_id' => $this->b->id]))
            ->toThrow(StructureNonAutoriseeException::class);
    });

    it('refuse de créer une donnée sans structure courante', function () {
        expect(fn () => Serre::create(['nom' => 'Orpheline']))
            ->toThrow(StructureNonAutoriseeException::class);
    });

    it('refuse de déplacer une donnée vers une autre structure', function () {
        $this->courante->definir($this->a->id);
        $serre = Serre::findOrFail($this->serreA->id);

        expect(fn () => $serre->update(['structure_id' => $this->b->id]))
            ->toThrow(StructureNonAutoriseeException::class);
    });
});

describe('requêtes HTTP', function () {
    it('affiche une serre de sa structure', function () {
        $this->actingAs(membre($this->a))
            ->getJson("/serres/{$this->serreA->id}")
            ->assertOk()
            ->assertJsonPath('nom', 'Serre A1');
    });

    it("répond 404 pour une serre d'une autre structure", function () {
        $this->actingAs(membre($this->a))
            ->getJson("/serres/{$this->serreB->id}")
            ->assertNotFound();
    });

    it('refuse un compte rattaché à aucune structure', function () {
        $this->actingAs(User::factory()->create())
            ->getJson("/serres/{$this->serreA->id}")
            ->assertForbidden();
    });

    it('refuse un visiteur non connecté', function () {
        $this->getJson("/serres/{$this->serreA->id}")->assertUnauthorized();
    });

    it('ne laisse pas choisir en session une structure dont on n\'est pas membre', function () {
        $this->actingAs(membre($this->a))
            ->withSession(['structure_id' => $this->b->id])
            ->getJson("/serres/{$this->serreB->id}")
            ->assertNotFound();
    });
});

describe('rôles par structure', function () {
    it('donne des droits différents selon la structure', function () {
        $user = membre($this->a, 'equipe');
        setPermissionsTeamId($this->b->id);
        $user->assignRole('benevole');

        setPermissionsTeamId($this->a->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        expect($user->can('gerer-seuils'))->toBeTrue();

        setPermissionsTeamId($this->b->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        expect($user->can('gerer-seuils'))->toBeFalse()
            ->and($user->can('voir-serres'))->toBeTrue()
            ->and($user->structureIds()->all())->toBe([$this->a->id, $this->b->id]);
    });
});
