<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use App\Models\User;
use App\Notifications\ActivationCompte;
use App\Support\StructureCourante;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * EF-D-02 : création d'un compte par l'équipe du Jardin (permission gerer-comptes).
 * L'administrateur ne choisit aucun mot de passe : la personne reçoit un lien
 * d'activation et le choisit elle-même.
 */
class CompteController extends Controller
{
    /** Libellés des rôles, dans l'ordre d'affichage. */
    public const LIBELLES_ROLES = [
        'admin-reseau' => 'Administrateur du réseau',
        'admin-structure' => 'Administrateur du Jardin',
        'equipe' => 'Équipe (salarié permanent)',
        'insertion' => 'Salarié en insertion',
        'benevole' => 'Bénévole',
        'adherent' => 'Adhérent',
    ];

    public function create(Request $request): View
    {
        return view('comptes.nouveau', ['roles' => $this->rolesAttribuables($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Même règle que la connexion : l'adresse est comparée en minuscules.
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys($this->rolesAttribuables($request)))],        ]);

        $structureId = app(StructureCourante::class)->id();
        abort_if($structureId === null, 403);

        $compte = DB::transaction(function () use ($donnees, $structureId): User {
            $compte = User::query()->create([
                'name' => $donnees['name'],
                'email' => $donnees['email'],
                // Mot de passe aléatoire que personne ne connaît : avant l'activation,
                // le compte existe mais personne ne peut s'y connecter.
                'password' => Str::random(64),
                'actif' => true,
            ]);

            setPermissionsTeamId($structureId);
            $compte->assignRole($donnees['role']);

            return $compte;
        });

        $jeton = Password::broker('activations')->createToken($compte);
        $jardin = Structure::query()->findOrFail($structureId)->nom_commercial;
        $compte->notify(new ActivationCompte($jeton, $jardin));

        return redirect()->route('comptes.create')
            ->with('status', "Compte créé. Un lien d'activation a été envoyé à {$compte->email}.");
    }

    /**
     * Moindre privilège : on n'attribue jamais un rôle plus élevé que les siens.
     * Seul l'administrateur du réseau peut créer un administrateur du réseau.
     *
     * @return array<string, string>
     */
    private function rolesAttribuables(Request $request): array
    {
        $roles = self::LIBELLES_ROLES;

        if (! $request->user()?->can('gerer-structures')) {
            unset($roles['admin-reseau']);
        }

        return $roles;
    }
}
