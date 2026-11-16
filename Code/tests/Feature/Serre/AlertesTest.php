<?php

/*
 * EF-C-11 : alertes affichées dans l'application et envoyées par e-mail.
 */

use App\Livewire\Alertes\ListeAlertes;
use App\Models\Alerte;
use App\Models\Serre;
use App\Models\Structure;
use App\Notifications\AlerteSerre;
use App\Services\SurveillanceSerres;
use App\Support\StructureCourante;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-16T12:00:00Z'));
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

    // Seuil de température de la serre nord : sans lui, la surveillance
    // fermerait les alertes de température créées par les tests.
    DB::table('seuils')->insert([
        'structure_id' => $this->a->id,
        'serre_id' => $this->serreA->id,
        'grandeur' => 'temperature',
        'minimum' => 10,
        'maximum' => 30,
    ]);
});

/** Ouvre (ou ferme si $fermee) une alerte de seuil dans une serre. */
function alerteSeuil(Serre $serre, string $message, bool $fermee = false): int
{
    return DB::table('alertes')->insertGetId([
        'structure_id' => $serre->structure_id,
        'serre_id' => $serre->id,
        'type' => 'seuil',
        'grandeur' => $fermee ? 'humidite' : 'temperature',
        'valeur' => 35,
        'message' => $message,
        'debut_le' => now()->subHour(),
        'fin_le' => $fermee ? now()->subMinutes(10) : null,
    ]);
}

describe("affichage dans l'application", function () {
    it('liste les alertes en cours et terminées du Jardin', function () {
        agirDans($this->a, 'insertion');
        alerteSeuil($this->serreA, 'Température trop haute dans la serre nord');
        alerteSeuil($this->serreA, 'Humidité trop basse hier', fermee: true);
        alerteSeuil($this->serreB, 'Alerte du Jardin B');

        Livewire::test(ListeAlertes::class)
            ->assertSeeInOrder(['En cours', 'Température trop haute dans la serre nord', 'Terminées', 'Humidité trop basse hier'])
            ->assertDontSee('Alerte du Jardin B')
            ->assertSee('wire:poll.60s', false);
    });

    it("indique qu'il n'y a aucune alerte", function () {
        agirDans($this->a, 'insertion');

        Livewire::test(ListeAlertes::class)
            ->assertSee('Aucune alerte en cours');
    });

    it('refuse la page aux adhérents', function () {
        $this->actingAs(compteDans($this->a, 'adherent'))
            ->get('/alertes')
            ->assertForbidden();
    });

    it('affiche le nombre d\'alertes en cours dans le menu et sur l\'accueil', function () {
        alerteSeuil($this->serreA, 'Alerte 1');
        DB::table('alertes')->insert([
            'structure_id' => $this->a->id,
            'serre_id' => $this->serreA->id,
            'type' => 'seuil',
            'grandeur' => 'luminosite',
            'message' => 'Alerte 2',
            'debut_le' => now(),
        ]);
        alerteSeuil($this->serreB, 'Alerte du Jardin B');

        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get('/accueil')
            ->assertOk()
            ->assertSee('2 alerte(s) en cours')
            ->assertSee('/alertes', false);
    });

    it('affiche les alertes en cours sur le tableau de bord de la serre', function () {
        alerteSeuil($this->serreA, 'Température trop haute dans la serre nord');

        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get("/serres/{$this->serreA->id}")
            ->assertOk()
            ->assertSee('1 alerte(s) en cours')
            ->assertSee('Température trop haute dans la serre nord');
    });

    it('signale les serres en alerte dans la liste', function () {
        alerteSeuil($this->serreA, 'Alerte 1');

        $this->actingAs(compteDans($this->a, 'insertion'))
            ->get('/serres')
            ->assertSee('1 alerte(s)</span>', false);
    });
});

describe('envoi par e-mail', function () {
    it('envoie la nouvelle alerte aux comptes du Jardin qui règlent les seuils', function () {
        Notification::fake();
        $admin = compteDans($this->a, 'admin-structure');
        $equipe = compteDans($this->a, 'equipe');
        $insertion = compteDans($this->a, 'insertion');
        $equipeB = compteDans($this->b, 'equipe');
        $inactif = compteDans($this->a, 'equipe');
        $inactif->update(['actif' => false]);
        alerteSeuil($this->serreA, 'Température trop haute dans la serre nord');

        $this->artisan('serres:surveiller')->assertSuccessful();

        Notification::assertSentTo([$admin, $equipe], AlerteSerre::class);
        Notification::assertNotSentTo([$insertion, $equipeB, $inactif], AlerteSerre::class);
        expect(DB::table('alertes')->value('notifiee_le'))->not->toBeNull();
    });

    it("n'envoie qu'une fois chaque alerte", function () {
        Notification::fake();
        $equipe = compteDans($this->a, 'equipe');
        alerteSeuil($this->serreA, 'Température trop haute');

        $this->artisan('serres:surveiller')->assertSuccessful();
        $this->artisan('serres:surveiller')->assertSuccessful();

        Notification::assertSentToTimes($equipe, AlerteSerre::class, 1);
    });

    it("n'envoie pas une alerte déjà terminée", function () {
        Notification::fake();
        compteDans($this->a, 'equipe');
        alerteSeuil($this->serreA, 'Ancienne alerte', fermee: true);

        $this->artisan('serres:surveiller')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('rédige un e-mail clair, avec un lien vers la serre', function () {
        $equipe = compteDans($this->a, 'equipe');
        alerteSeuil($this->serreA, 'Température trop haute dans la serre « Serre nord » : 35,0 °C.');
        app(StructureCourante::class)->definir($this->a->id);
        $alerte = Alerte::query()->firstOrFail();

        $mail = (new AlerteSerre($alerte))->toMail($equipe);

        expect($mail->subject)->toBe('Alerte serre « Serre nord » : Seuil dépassé')
            ->and($mail->introLines)->toContain('Température trop haute dans la serre « Serre nord » : 35,0 °C.')
            ->and($mail->introLines)->toContain("Début de l'alerte : le 16/11/2026 à 12h00 (heure de Paris).")
            ->and($mail->actionUrl)->toEndWith("/serres/{$this->serreA->id}");
    });

    it('envoie un vrai e-mail par le serveur de messagerie', function () {
        $equipe = compteDans($this->a, 'equipe');
        alerteSeuil($this->serreA, 'Température trop haute');

        $this->artisan('serres:surveiller')->assertSuccessful();

        $envoyes = app('mailer')->getSymfonyTransport()->messages();
        expect($envoyes)->toHaveCount(1)
            ->and($envoyes[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe($equipe->email);
    });

    it('trouve les destinataires dans le bon Jardin seulement', function () {
        $adminA = compteDans($this->a, 'admin-structure');
        $equipeB = compteDans($this->b, 'equipe');
        $multi = compteDans($this->a, 'insertion');
        setPermissionsTeamId($this->b->id);
        $multi->assignRole('equipe');
        setPermissionsTeamId(null);

        $destinatairesA = app(SurveillanceSerres::class)->destinataires($this->a->id)->pluck('id')->all();
        $destinatairesB = app(SurveillanceSerres::class)->destinataires($this->b->id)->pluck('id')->all();

        expect($destinatairesA)->toBe([$adminA->id])
            ->and($destinatairesB)->toBe([$equipeB->id, $multi->id]);
    });
});

it('ne garde pas de Jardin courant après la tâche planifiée', function () {
    $this->artisan('serres:surveiller')->assertSuccessful();

    expect(app(StructureCourante::class)->id())->toBeNull()
        ->and(getPermissionsTeamId())->toBeNull();
});
