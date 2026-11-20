<?php

/*
 * EF-D-12 : e-mail au propriétaire du compte quand son mot de passe change,
 * quel que soit le chemin (Mon compte, mot de passe oublié...).
 */

use App\Models\Structure;
use App\Models\User;
use App\Notifications\MotDePasseModifie;
use Database\Seeders\RolesEtPermissionsSeeder;
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
});

describe('alerte de changement de mot de passe (EF-D-12)', function () {
    it('prévient par e-mail quand le mot de passe change', function () {
        $this->compte->update(['password' => 'Nouveau-Mot-Jardin-2026']);

        Notification::assertSentTo($this->compte, MotDePasseModifie::class);
    });

    it('prévient aussi après un mot de passe oublié', function () {
        $jeton = Password::createToken($this->compte);

        $this->post('/reset-password', [
            'token' => $jeton,
            'email' => 'camille@cocagne.test',
            'password' => 'Nouveau-Mot-Jardin-2026',
            'password_confirmation' => 'Nouveau-Mot-Jardin-2026',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($this->compte, MotDePasseModifie::class);
    });

    it("n'envoie rien quand une autre information du compte change", function () {
        $this->compte->update(['name' => 'Camille Durand']);

        Notification::assertNotSentTo($this->compte, MotDePasseModifie::class);
    });

    it("n'envoie rien à une simple connexion", function () {
        $this->post('/login', ['email' => 'camille@cocagne.test', 'password' => 'MotDePasse-Solide-2026'])
            ->assertRedirect('/accueil');

        Notification::assertNotSentTo($this->compte, MotDePasseModifie::class);
    });

    it('rédige un e-mail en français, sans jamais contenir le mot de passe', function () {
        $this->compte->update(['password' => 'Nouveau-Mot-Jardin-2026']);

        Notification::assertSentTo($this->compte, MotDePasseModifie::class, function (MotDePasseModifie $notification) {
            $mail = $notification->toMail($this->compte);
            $texte = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            return $mail->subject === 'Votre mot de passe a été modifié'
                && str_contains($texte, "Si ce n'est pas vous")
                && ! str_contains($texte, 'Nouveau-Mot-Jardin-2026')
                && str_starts_with($mail->actionUrl, rtrim((string) config('app.url'), '/').'/forgot-password');
        });
    });
});
