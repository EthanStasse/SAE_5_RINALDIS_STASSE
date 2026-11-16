<?php

/*
 * Écran des serres : EF-C-01 (déclarer les serres d'un Jardin), droits par rôle
 * et isolation entre Jardins.
 */

use App\Livewire\Serres\GestionSerres;
use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use App\Models\User;
use App\Support\StructureCourante;
use Database\Seeders\RolesEtPermissionsSeeder;
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
            Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre nord', 'emplacement' => 'Parcelle 1']),
            Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Tunnel B']),
        ];
    });
});

/** Crée un compte ayant ce rôle dans ce Jardin. */
function compteDans(Structure $structure, string $role): User
{
    $user = User::factory()->create();
    setPermissionsTeamId($structure->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

/**
 * Pour les tests Livewire, qui ne passent pas par les middlewares HTTP :
 * on se connecte et on fixe le Jardin courant à la main.
 */
function agirDans(Structure $structure, string $role): User
{
    $user = compteDans($structure, $role);
    test()->actingAs($user);
    app(StructureCourante::class)->definir($structure->id);
    setPermissionsTeamId($structure->id);

    return $user;
}

describe('page des serres', function () {
    it('envoie un visiteur vers la connexion', function () {
        $this->get('/serres')->assertRedirect('/login');
    });

    it('montre aux salariés les serres de leur Jardin seulement, sans les boutons de gestion', function () {
        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get('/serres')
            ->assertOk()
            ->assertSee('Serre nord')
            ->assertSee('Parcelle 1')
            ->assertDontSee('Tunnel B')
            ->assertDontSee('Déclarer une serre');
    });

    it("propose la déclaration à l'administrateur du Jardin", function () {
        $this->actingAs(compteDans($this->a, 'admin-structure'))
            ->get('/serres')
            ->assertOk()
            ->assertSee('Déclarer une serre');
    });

    it('refuse la page aux adhérents', function () {
        $this->actingAs(compteDans($this->a, 'adherent'))
            ->get('/serres')
            ->assertForbidden();
    });
});

describe('déclaration (EF-C-01)', function () {
    it('déclare une serre dans le Jardin courant', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('creer')
            ->set('nom', '  Serre sud ')
            ->set('emplacement', 'Parcelle 2')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('formulaireOuvert', false)
            ->assertSee('La serre « Serre sud » a été déclarée.');

        $ligne = DB::table('serres')->where('nom', 'Serre sud')->sole();
        expect($ligne->structure_id)->toBe($this->a->id)
            ->and($ligne->emplacement)->toBe('Parcelle 2');
    });

    it('enregistre un emplacement vide comme absent', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('creer')
            ->set('nom', 'Serre sud')
            ->set('emplacement', '   ')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(DB::table('serres')->where('nom', 'Serre sud')->value('emplacement'))->toBeNull();
    });

    it('exige un nom', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('creer')
            ->set('nom', '')
            ->call('enregistrer')
            ->assertHasErrors(['nom' => 'required'])
            ->assertSee('Le nom de la serre est obligatoire.');
    });

    it('refuse deux serres du même nom dans un Jardin', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('creer')
            ->set('nom', 'Serre nord')
            ->call('enregistrer')
            ->assertHasErrors(['nom' => 'unique']);
    });

    it("accepte le nom d'une serre d'un autre Jardin", function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('creer')
            ->set('nom', 'Tunnel B')
            ->call('enregistrer')
            ->assertHasNoErrors();
    });

    it('modifie une serre', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('modifier', $this->serreA->id)
            ->assertSet('nom', 'Serre nord')
            ->set('nom', 'Serre nord-est')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(DB::table('serres')->where('id', $this->serreA->id)->value('nom'))->toBe('Serre nord-est');
    });

    it('garde son propre nom lors d\'une modification', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('modifier', $this->serreA->id)
            ->set('emplacement', 'Parcelle 3')
            ->call('enregistrer')
            ->assertHasNoErrors();
    });

    it('supprime une serre sans boîtier', function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call('supprimer', $this->serreA->id)
            ->assertSee('La serre « Serre nord » a été supprimée.');

        expect(DB::table('serres')->where('id', $this->serreA->id)->exists())->toBeFalse();
    });

    it('refuse de supprimer une serre équipée de boîtiers', function () {
        agirDans($this->a, 'admin-structure');
        Boitier::creerPour($this->serreA, 'Boîtier nord');

        Livewire::test(GestionSerres::class)
            ->call('supprimer', $this->serreA->id)
            ->assertHasErrors('suppression');

        expect(DB::table('serres')->where('id', $this->serreA->id)->exists())->toBeTrue();
    });
});

describe('droits et isolation', function () {
    it("interdit la gestion à l'équipe", function (string $action, array $arguments) {
        agirDans($this->a, 'equipe');

        Livewire::test(GestionSerres::class)
            ->call($action, ...$arguments)
            ->assertForbidden();
    })->with([
        'créer' => ['creer', []],
        'modifier' => ['modifier', [1]],
        'enregistrer' => ['enregistrer', []],
        'supprimer' => ['supprimer', [1]],
    ]);

    it("ne trouve pas la serre d'un autre Jardin", function (string $action) {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->call($action, $this->serreB->id)
            ->assertNotFound();

        expect(DB::table('serres')->where('id', $this->serreB->id)->exists())->toBeTrue();
    })->with(['modifier', 'supprimer']);

    it("n'écrit jamais dans un autre Jardin, même en forçant l'identifiant", function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionSerres::class)
            ->set('serreEnCours', $this->serreB->id)
            ->set('nom', 'Piratée')
            ->call('enregistrer')
            ->assertNotFound();

        expect(DB::table('serres')->where('id', $this->serreB->id)->value('nom'))->toBe('Tunnel B');
    });
});
