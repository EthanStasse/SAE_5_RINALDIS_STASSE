<?php

/*
 * EF-D-02 : compte créé par l'équipe ; lien d'activation à usage unique envoyé
 * par e-mail ; l'adhérent choisit son mot de passe (aucun mot de passe envoyé).
 * ENF-SEC-06 : jeton aléatoire, stocké haché, à usage unique, à durée limitée.
 */

use App\Models\Structure;
use App\Models\User;
use App\Notifications\ActivationCompte;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesEtPermissionsSeeder::class);

    $this->jardin = Structure::factory()->create(['nom_commercial' => 'Jardin de Thaon']);
    $this->admin = compteDans($this->jardin, 'admin-structure');
});

/** L'administrateur du Jardin crée un compte. */
function creerCompte(array $champs = [])
{
    return test()->from('/comptes/nouveau')->post('/comptes', array_merge([
        'name' => 'Camille Martin',
        'email' => 'camille@cocagne.test',
        'role' => 'adherent',
    ], $champs));
}

describe("création du compte par l'équipe (EF-D-02)", function () {
    it('refuse la page aux visiteurs', function () {
        $this->get('/comptes/nouveau')->assertRedirect('/login');
    });

    it('refuse la création aux comptes qui ne gèrent pas les comptes', function (string $role) {
        $this->actingAs(compteDans($this->jardin, $role))
            ->get('/comptes/nouveau')
            ->assertForbidden();
    })->with(['equipe', 'adherent']);

    it("affiche le formulaire à l'administrateur du Jardin", function () {
        $this->actingAs($this->admin)
            ->get('/comptes/nouveau')
            ->assertOk()
            ->assertSee('Créer un compte')
            ->assertSee('Adresse e-mail')
            ->assertSee('Rôle')
            ->assertDontSee('name="password"', false);
    });

    it("crée le compte dans le Jardin, avec son rôle, sans mot de passe connu, et envoie le lien d'activation", function () {
        $this->actingAs($this->admin);

        creerCompte()
            ->assertSessionHasNoErrors()
            ->assertRedirect('/comptes/nouveau')
            ->assertSessionHas('status');

        $compte = User::query()->where('email', 'camille@cocagne.test')->sole();
        expect($compte->name)->toBe('Camille Martin')
            ->and($compte->appartientA($this->jardin->id))->toBeTrue()
            ->and($compte->nomsDeRoles()->all())->toBe(['adherent'])
            ->and($compte->email_verified_at)->toBeNull();

        Notification::assertSentTo($compte, ActivationCompte::class);
    });

    it("rédige un e-mail d'activation sans mot de passe, avec un lien limité dans le temps", function () {
        $this->actingAs($this->admin);
        creerCompte();
        $compte = User::query()->where('email', 'camille@cocagne.test')->sole();

        Notification::assertSentTo($compte, ActivationCompte::class, function (ActivationCompte $notification) use ($compte) {
            $mail = $notification->toMail($compte);
            $texte = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            return $mail->subject === 'Activez votre compte des Jardins de Cocagne'
                && str_contains($texte, 'Jardin de Thaon')
                && str_contains($texte, '72 heures')
                && str_contains($texte, 'une seule fois')
                && str_starts_with($mail->actionUrl, rtrim((string) config('app.url'), '/').'/activation/');
        });
    });

    it('refuse une adresse qui a déjà un compte', function () {
        User::factory()->create(['email' => 'camille@cocagne.test']);
        $this->actingAs($this->admin);

        creerCompte()->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    });

    it("n'autorise pas un administrateur de Jardin à créer un administrateur du réseau", function () {
        $this->actingAs($this->admin);

        creerCompte(['role' => 'admin-reseau'])->assertSessionHasErrors('role');
        expect(User::query()->where('email', 'camille@cocagne.test')->exists())->toBeFalse();
    });
});
