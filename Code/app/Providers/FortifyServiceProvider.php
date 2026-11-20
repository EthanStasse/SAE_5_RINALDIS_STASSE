<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LienReinitialisationDemande;
use App\Models\User;
use App\Support\BlocageComptes;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // EF-D-07 : la demande de lien répond toujours la même chose, que
        // l'adresse ait un compte ou non (remplace les deux réponses de Fortify).
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, LienReinitialisationDemande::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, LienReinitialisationDemande::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('auth.connexion'));
        // EF-D-04 : demande du lien de réinitialisation.
        Fortify::requestPasswordResetLinkView(fn () => view('auth.mot-de-passe-oublie'));
        // EF-D-04 : formulaire du nouveau mot de passe (lien reçu par e-mail).
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reinitialisation', ['request' => $request]));
        // EF-D-08 : deuxième étape de la connexion (code TOTP ou code de secours).
        Fortify::twoFactorChallengeView(fn () => view('auth.code-double-authentification'));
        // EF-D-04 : e-mail de réinitialisation en français.
        // Mot de passe redemandé avant une action sensible (activer la 2FA...).
        Fortify::confirmPasswordView(fn () => view('auth.confirmation-mot-de-passe'));
        // Anti-phishing : le lien est construit depuis APP_URL (configuration),
        // jamais depuis l'en-tête Host de la requête, que l'attaquant contrôle.
        ResetPassword::toMailUsing(function (User $notifiable, string $token): MailMessage {
            $lien = rtrim((string) config('app.url'), '/').route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false);

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe')
                ->greeting('Bonjour,')
                ->line('Vous avez demandé à choisir un nouveau mot de passe pour le logiciel de gestion des Jardins de Cocagne.')
                ->action('Choisir un nouveau mot de passe', $lien)
                ->line("Ce lien est valable 60 minutes et ne fonctionne qu'une seule fois.")
                ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.")
                ->salutation('Les Jardins de Cocagne');
        });

        // Vérification du compte : un compte désactivé ne peut pas se connecter.
        // Même message d'erreur dans tous les cas : on ne révèle pas si l'adresse existe.
        Fortify::authenticateUsing(function (Request $request): ?User {
            // EF-D-10 : pas de connexion longue durée. Le champ « remember » est
            // ignoré même s'il est envoyé à la main : une session ne doit
            // jamais dépasser 12 heures.
            $request->merge(['remember' => false]);

            $email = Str::lower((string) $request->input(Fortify::username()));
            $blocage = app(BlocageComptes::class);

            // EF-D-06 : adresse bloquée, même le bon mot de passe est refusé.
            // EF-D-07 : même message que l'adresse existe ou non.
            if ($blocage->estBloque($email)) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('auth.blocked', ['minutes' => $blocage->minutesRestantes($email)]),
                ]);
            }

            $user = User::where('email', $email)->first();

            if ($user === null || ! $user->actif || ! Hash::check((string) $request->input('password'), $user->password)) {
                $blocage->enregistrerEchec($email);

                return null;
            }

            $blocage->reinitialiser($email);
            $user->forceFill(['derniere_connexion_at' => now()])->save();

            return $user;
        });

        // EF-D-06 : limitation par adresse IP, tous comptes confondus, contre le
        // credential stuffing et le password spraying (un même poste qui essaie
        // beaucoup de comptes). Au-delà : réponse 429 avec l'en-tête Retry-After.
        // 10 par minute laisse de la marge à un Jardin dont tous les postes
        // partagent la même adresse IP (NAT). Le blocage par compte est à part.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by('connexion-ip:'.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
