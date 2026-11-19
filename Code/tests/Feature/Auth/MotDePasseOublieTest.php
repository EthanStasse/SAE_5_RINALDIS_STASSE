<?php

/*
 * EF-D-04 : mot de passe oublié, lien à usage unique valable 60 minutes.
 * EF-D-07 : la réponse ne révèle pas si un compte existe.
 * ENF-SEC-06 : jeton aléatoire, stocké haché, à usage unique, à durée limitée.
 */

use App\Models\Structure;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesEtPermissionsSeeder::class);

    $this->jardin = Structure::factory()->create();
    $this->compte = User::factory()->create([
        'email' => 'camille@cocagne.test',
        'password' => 'MotDePasse-Solide-2026',
    ]);
    setPermissionsTeamId($this->jardin->id);
    $this->compte->assignRole('adherent');
    setPermissionsTeamId(null);

    $this->messageEnvoi = "Si un compte correspond à cette adresse, un lien de réinitialisation vient d'y être envoyé. Il est valable 60 minutes.";
    $this->messageLienInvalide = 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.';
});

describe('demande du lien (EF-D-04)', function () {
    it('propose le lien depuis la page de connexion', function () {
        $this->get('/login')
            ->assertSee('Mot de passe oublié ?')
            ->assertSee('/forgot-password');
    });

    it('affiche le formulaire en français', function () {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Mot de passe oublié')
            ->assertSee('Adresse e-mail')
            ->assertSee('Recevoir le lien');
    });

    it('envoie un lien au compte', function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $this->messageEnvoi);

        Notification::assertSentTo($this->compte, ResetPassword::class);
    });
});

describe('messages qui ne révèlent pas les comptes (EF-D-07)', function () {
    it('donne le même message pour une adresse inconnue, sans rien envoyer', function () {
        $this->post('/forgot-password', ['email' => 'inconnu@cocagne.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $this->messageEnvoi);

        Notification::assertNothingSent();
    });

    it('donne le même message quand on redemande trop vite, sans renvoyer de lien', function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test']);

        $this->post('/forgot-password', ['email' => 'camille@cocagne.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', $this->messageEnvoi);

        Notification::assertSentToTimes($this->compte, ResetPassword::class, 1);
    });
});

describe('jeton (ENF-SEC-06)', function () {
    it('stocke le jeton haché, jamais en clair', function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test']);

        $jeton = null;
        Notification::assertSentTo($this->compte, ResetPassword::class, function (ResetPassword $notification) use (&$jeton) {
            $jeton = $notification->token;

            return true;
        });

        $enBase = DB::table('password_reset_tokens')->where('email', 'camille@cocagne.test')->value('token');

        expect($enBase)->not->toBe($jeton)
            ->and(Hash::check($jeton, $enBase))->toBeTrue()
            ->and(strlen($jeton))->toBeGreaterThanOrEqual(40);
    });
});

describe('nouveau mot de passe (EF-D-04)', function () {
    /** Envoie le formulaire de nouveau mot de passe. */
    function reinitialiser(string $jeton, string $motDePasse, string $email = 'camille@cocagne.test')
    {
        return test()->post('/reset-password', [
            'token' => $jeton,
            'email' => $email,
            'password' => $motDePasse,
            'password_confirmation' => $motDePasse,
        ]);
    }

    it('affiche le formulaire depuis le lien reçu', function () {
        $jeton = Password::createToken($this->compte);

        $this->get("/reset-password/{$jeton}?email=camille@cocagne.test")
            ->assertOk()
            ->assertSee('Nouveau mot de passe')
            ->assertSee('camille@cocagne.test')
            ->assertSee('Enregistrer le mot de passe');
    });

    it('change le mot de passe avec un lien valide', function () {
        $jeton = Password::createToken($this->compte);

        reinitialiser($jeton, 'Nouveau-Mot-Jardin-2026')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

        expect(Hash::check('Nouveau-Mot-Jardin-2026', $this->compte->fresh()->password))->toBeTrue();
    });

    it("permet de se connecter avec le nouveau mot de passe, plus avec l'ancien", function () {
        reinitialiser(Password::createToken($this->compte), 'Nouveau-Mot-Jardin-2026');

        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'Nouveau-Mot-Jardin-2026'])
            ->assertRedirect('/accueil');
        $this->assertAuthenticatedAs($this->compte);
    });

    it('refuse un lien déjà utilisé (usage unique)', function () {
        $jeton = Password::createToken($this->compte);
        reinitialiser($jeton, 'Nouveau-Mot-Jardin-2026');

        reinitialiser($jeton, 'Pirate-Mot-De-Passe-2026')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);

        expect(Hash::check('Nouveau-Mot-Jardin-2026', $this->compte->fresh()->password))->toBeTrue();
    });

    it('accepte encore un lien de 59 minutes', function () {
        $this->freezeTime();
        $jeton = Password::createToken($this->compte);

        $this->travel(59)->minutes();

        reinitialiser($jeton, 'Nouveau-Mot-Jardin-2026')->assertSessionHasNoErrors();
    });

    it('refuse un lien de plus de 60 minutes', function () {
        $this->freezeTime();
        $jeton = Password::createToken($this->compte);

        $this->travel(61)->minutes();

        reinitialiser($jeton, 'Nouveau-Mot-Jardin-2026')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);

        expect(Hash::check('MotDePasse-Solide-2026', $this->compte->fresh()->password))->toBeTrue();
    });

    it('refuse un jeton inventé', function () {
        reinitialiser(str_repeat('a', 64), 'Nouveau-Mot-Jardin-2026')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);

        expect(Hash::check('MotDePasse-Solide-2026', $this->compte->fresh()->password))->toBeTrue();
    });

    it('donne le même message pour une adresse inconnue (EF-D-07)', function () {
        $jeton = Password::createToken($this->compte);

        reinitialiser($jeton, 'Nouveau-Mot-Jardin-2026', 'inconnu@cocagne.test')
            ->assertSessionHasErrors(['email' => $this->messageLienInvalide]);
    });

    it('applique la politique de mot de passe, sans consommer le lien (EF-D-05)', function () {
        $jeton = Password::createToken($this->compte);

        reinitialiser($jeton, 'jardin')->assertSessionHasErrors('password');
        expect(Hash::check('MotDePasse-Solide-2026', $this->compte->fresh()->password))->toBeTrue();

        // L'adhérent corrige sa saisie : le même lien fonctionne encore.
        reinitialiser($jeton, 'Nouveau-Mot-Jardin-2026')->assertSessionHasNoErrors();
    });
});
describe('e-mail de réinitialisation', function () {
    it("rédige l'e-mail en français, avec la durée et l'usage unique du lien", function () {
        $this->post('/forgot-password', ['email' => 'camille@cocagne.test']);

        Notification::assertSentTo($this->compte, ResetPassword::class, function (ResetPassword $notification) {
            $mail = $notification->toMail($this->compte);
            $texte = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            return $mail->subject === 'Réinitialisation de votre mot de passe'
                && $mail->actionText === 'Choisir un nouveau mot de passe'
                && str_contains($texte, '60 minutes')
                && str_contains($texte, 'une seule fois')
                && str_contains($texte, "Si vous n'êtes pas à l'origine de cette demande");
        });
    });

    it("construit le lien depuis APP_URL, pas depuis l'en-tête Host (anti-phishing)", function () {
        // L'attaquant envoie la demande en se faisant passer pour pirate.test.
        $this->post('http://pirate.test/forgot-password', ['email' => 'camille@cocagne.test']);

        Notification::assertSentTo($this->compte, ResetPassword::class, function (ResetPassword $notification) {
            $lien = $notification->toMail($this->compte)->actionUrl;

            return str_starts_with($lien, rtrim((string) config('app.url'), '/').'/reset-password/')
                && ! str_contains($lien, 'pirate.test');
        });
    });
});
