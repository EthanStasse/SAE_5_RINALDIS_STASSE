<?php

/*
 * Écran des boîtiers : EF-C-02 (boîtier rattaché à une serre, jeton propre et
 * révocable), droits et isolation entre Jardins.
 */

use App\Livewire\Serres\GestionBoitiers;
use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use App\Support\StructureCourante;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesEtPermissionsSeeder::class);

    [$this->a, $this->b, $this->serreA, $this->autreSerreA, $this->serreB, $this->boitierB] =
        app(StructureCourante::class)->sansFiltre(function () {
            $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
            $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);
            $serreA = Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre nord']);
            $autreSerreA = Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre sud']);
            $serreB = Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Tunnel B']);
            [$boitierB] = Boitier::creerPour($serreB, 'Boîtier B');

            return [$a, $b, $serreA, $autreSerreA, $serreB, $boitierB];
        });
});

/** Empreinte du jeton actuellement enregistrée en base. */
function empreinteEnBase(int $boitierId): string
{
    return DB::table('boitiers')->where('id', $boitierId)->value('jeton_hash');
}

describe('page des boîtiers', function () {
    it("ouvre la page à l'administrateur du Jardin", function () {
        $this->actingAs(compteDans($this->a, 'admin-structure'))
            ->get("/serres/{$this->serreA->id}/boitiers")
            ->assertOk()
            ->assertSee('Boîtiers de la serre « Serre nord »');
    });

    it("refuse la page à l'équipe : un jeton donne le droit d'écrire des mesures", function () {
        $this->actingAs(compteDans($this->a, 'equipe'))
            ->get("/serres/{$this->serreA->id}/boitiers")
            ->assertForbidden();
    });

    it("ne trouve pas la serre d'un autre Jardin", function () {
        $this->actingAs(compteDans($this->a, 'admin-structure'))
            ->get("/serres/{$this->serreB->id}/boitiers")
            ->assertNotFound();
    });

    it('propose les boîtiers depuis la liste des serres', function () {
        $this->actingAs(compteDans($this->a, 'admin-structure'))
            ->get('/serres')
            ->assertSee("/serres/{$this->serreA->id}/boitiers");
    });
});

describe('déclaration et jeton (EF-C-02)', function () {
    it('déclare un boîtier et affiche son jeton une seule fois', function () {
        agirDans($this->a, 'admin-structure');

        $test = Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->set('nom', ' Boîtier ESP32 nord ')
            ->call('creer')
            ->assertHasNoErrors()
            ->assertSee('Il ne sera plus jamais affiché');

        $jeton = $test->get('jetonAffiche');
        $ligne = DB::table('boitiers')->where('nom', 'Boîtier ESP32 nord')->sole();

        expect($jeton)->toHaveLength(Boitier::LONGUEUR_JETON)
            ->and($ligne->structure_id)->toBe($this->a->id)
            ->and($ligne->serre_id)->toBe($this->serreA->id)
            ->and($ligne->jeton_hash)->toBe(Boitier::empreinte($jeton));

        $test->call('masquerJeton')
            ->assertSet('jetonAffiche', null)
            ->assertDontSee($jeton)
            ->assertSee(substr($jeton, 0, 8).'…');
    });

    it('exige un nom unique dans le Jardin', function () {
        agirDans($this->a, 'admin-structure');
        Boitier::creerPour($this->autreSerreA, 'Boîtier 1');

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->set('nom', '')
            ->call('creer')
            ->assertHasErrors(['nom' => 'required'])
            ->set('nom', 'Boîtier 1')
            ->call('creer')
            ->assertHasErrors(['nom' => 'unique']);
    });

    it("accepte le nom d'un boîtier d'un autre Jardin", function () {
        agirDans($this->a, 'admin-structure');

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->set('nom', 'Boîtier B')
            ->call('creer')
            ->assertHasNoErrors();
    });

    it("régénère le jeton : l'ancien ne fonctionne plus", function () {
        agirDans($this->a, 'admin-structure');
        [$boitier, $ancien] = Boitier::creerPour($this->serreA, 'Boîtier 1');

        $nouveau = Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->call('regenerer', $boitier->id)
            ->get('jetonAffiche');

        expect($nouveau)->not->toBe($ancien)
            ->and(empreinteEnBase($boitier->id))->toBe(Boitier::empreinte($nouveau));
    });

    it('révoque un boîtier', function () {
        agirDans($this->a, 'admin-structure');
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier 1');

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->call('revoquer', $boitier->id)
            ->assertSee('Révoqué le')
            ->assertSee('Réactiver (nouveau jeton)');

        expect(DB::table('boitiers')->where('id', $boitier->id)->value('revoque_le'))->not->toBeNull();
    });

    it('réactive un boîtier révoqué en lui donnant un nouveau jeton', function () {
        agirDans($this->a, 'admin-structure');
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier 1');
        $boitier->revoquer();

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->call('regenerer', $boitier->id);

        expect(DB::table('boitiers')->where('id', $boitier->id)->value('revoque_le'))->toBeNull();
    });

    it("n'affiche jamais l'empreinte du jeton", function () {
        agirDans($this->a, 'admin-structure');
        [$boitier] = Boitier::creerPour($this->serreA, 'Boîtier 1');

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->assertDontSee(empreinteEnBase($boitier->id));
    });
});

describe('droits et isolation', function () {
    it("interdit la gestion à l'équipe", function () {
        agirDans($this->a, 'equipe');

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->assertForbidden();
    });

    it("n'agit pas sur le boîtier d'une autre serre du même Jardin", function (string $action) {
        agirDans($this->a, 'admin-structure');
        [$boitierSud] = Boitier::creerPour($this->autreSerreA, 'Boîtier sud');
        $empreinte = empreinteEnBase($boitierSud->id);

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->call($action, $boitierSud->id)
            ->assertNotFound();

        expect(empreinteEnBase($boitierSud->id))->toBe($empreinte);
    })->with(['regenerer', 'revoquer']);

    it("n'agit pas sur le boîtier d'un autre Jardin", function (string $action) {
        agirDans($this->a, 'admin-structure');
        $empreinte = empreinteEnBase($this->boitierB->id);

        Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->call($action, $this->boitierB->id)
            ->assertNotFound();

        expect(empreinteEnBase($this->boitierB->id))->toBe($empreinte)
            ->and(DB::table('boitiers')->where('id', $this->boitierB->id)->value('revoque_le'))->toBeNull();
    })->with(['regenerer', 'revoquer']);

    it('le boîtier créé reçoit bien des mesures avec son jeton', function () {
        agirDans($this->a, 'admin-structure');

        $jeton = Livewire::test(GestionBoitiers::class, ['serre' => $this->serreA])
            ->set('nom', 'Boîtier ESP32')
            ->call('creer')
            ->get('jetonAffiche');

        app(StructureCourante::class)->oublier();

        $this->withToken($jeton)
            ->getJson('/api/v1/boitier')
            ->assertOk()
            ->assertJsonPath('serre.nom', 'Serre nord');
    });
});
