<?php

/*
 * EF-D-02 : compte créé par l'équipe ; lien d'activation à usage unique envoyé
 * par e-mail ; l'adhérent choisit son mot de passe (aucun mot de passe envoyé).
 * ENF-SEC-06 : jeton aléatoire, stocké haché, à usage unique, à durée limitée.
 */

use App\Models\Structure;
use App\Models\User;
use App\Notifications\ActivationCompte;
use App\Notifications\MotDePasseModifie;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
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
describe('activation par le lien reçu (EF-D-02, US-D1)', function () {
    beforeEach(function () {
        // L'administrateur crée le compte, puis se déconnecte :
        // la personne ouvre ensuite le lien de chez elle.
        $this->actingAs($this->admin);
        creerCompte();
        $this->nouveau = User::query()->where('email', 'camille@cocagne.test')->sole();

        Notification::assertSentTo($this->nouveau, ActivationCompte::class, function (ActivationCompte $notification) {
            $this->lien = $notification->toMail($this->nouveau)->actionUrl;

            return true;
        });
        $this->post('/logout');

        $this->messageLienInvalide = "Ce lien d'activation est invalide ou a expiré. Demandez-en un nouveau à l'équipe de votre Jardin.";
    });

    /** Envoie le formulaire d'activation avec le jeton et l'adresse du lien reçu. */
    function activer(string $lien, string $motDePasse, ?string $email = null)
    {
        parse_str((string) parse_url($lien, PHP_URL_QUERY), $parametres);

        return test()->post('/activation', [
            'token' => basename((string) parse_url($lien, PHP_URL_PATH)),
            'email' => $email ?? $parametres['email'],
            'password' => $motDePasse,
            'password_confirmation' => $motDePasse,
        ]);
    }

    it('affiche la page depuis le lien reçu', function () {
        $this->get($this->lien)
            ->assertOk()
            ->assertSee('Activer mon compte')
            ->assertSee('camille@cocagne.test');
    });

    it('active le compte : le mot de passe choisi permet ensuite de se connecter (US-D1)', function () {
        activer($this->lien, 'Mon-Jardin-Bio-2026')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Votre compte est activé. Vous pouvez vous connecter.');

        expect($this->nouveau->fresh()->email_verified_at)->not->toBeNull();

        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'Mon-Jardin-Bio-2026'])
            ->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->nouveau);
    });

    it("n'envoie pas l'alerte « mot de passe modifié » à l'activation (EF-D-12)", function () {
        activer($this->lien, 'Mon-Jardin-Bio-2026')->assertSessionHasNoErrors();

        Notification::assertNotSentTo($this->nouveau, MotDePasseModifie::class);
    });

    it('refuse un lien déjà utilisé (usage unique)', function () {
        activer($this->lien, 'Mon-Jardin-Bio-2026');

        activer($this->lien, 'Pirate-Mot-De-Passe-2026')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);
        expect(Hash::check('Mon-Jardin-Bio-2026', $this->nouveau->fresh()->password))->toBeTrue();
    });

    it('accepte encore un lien de 71 heures', function () {
        $this->travel(71)->hours();

        activer($this->lien, 'Mon-Jardin-Bio-2026')->assertSessionHasNoErrors();
    });

    it('refuse un lien de plus de 72 heures', function () {
        $this->travel(73)->hours();

        activer($this->lien, 'Mon-Jardin-Bio-2026')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);
        expect($this->nouveau->fresh()->email_verified_at)->toBeNull();
    });

    it('refuse un jeton inventé', function () {
        activer('http://localhost/activation/'.str_repeat('a', 64).'?email=camille@cocagne.test', 'Mon-Jardin-Bio-2026')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);
    });

    it('donne le même message pour une adresse sans compte (EF-D-07)', function () {
        activer($this->lien, 'Mon-Jardin-Bio-2026', 'inconnu@cocagne.test')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);
    });

    it('applique la politique de mot de passe, sans consommer le lien (EF-D-05)', function () {
        activer($this->lien, 'jardin')->assertSessionHasErrors('password');
        expect($this->nouveau->fresh()->email_verified_at)->toBeNull();

        activer($this->lien, 'Mon-Jardin-Bio-2026')->assertSessionHasNoErrors();
    });
});
