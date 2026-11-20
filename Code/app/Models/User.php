<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte unique par personne (CDC §11). Les rôles sont attribués PAR structure
 * grâce au mode « teams » de spatie/laravel-permission (colonne structure_id).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'actif',
    ];

    /**
     * Jamais renvoyés dans un tableau ou du JSON. EF-D-08 : le secret de double
     * authentification et les codes de secours (chiffrés en base) en font partie.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }

    /**
     * Identifiants des structures dans lesquelles ce compte a au moins un rôle.
     *
     * @return Collection<int, int>
     */
    public function structureIds(): Collection
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $this->getMorphClass())
            ->where(config('permission.column_names.model_morph_key'), $this->getKey())
            ->whereNotNull('structure_id')
            ->distinct()
            ->orderBy('structure_id')
            ->pluck('structure_id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function appartientA(int $structureId): bool
    {
        return $this->structureIds()->contains($structureId);
    }

    /**
     * EF-D-10 : un compte qui a au moins un rôle autre qu'« adherent », dans
     * n'importe quel Jardin, est du personnel (règle la plus stricte).
     */
    public function estPersonnel(): bool
    {
        $roles = config('permission.table_names.roles');
        $pivot = config('permission.table_names.model_has_roles');
        $cleRole = config('permission.column_names.role_pivot_key') ?? 'role_id';

        return DB::table($pivot)
            ->join($roles, "{$roles}.id", '=', "{$pivot}.{$cleRole}")
            ->where("{$pivot}.model_type", $this->getMorphClass())
            ->where("{$pivot}.".config('permission.column_names.model_morph_key'), $this->getKey())
            ->where("{$roles}.name", '!=', 'adherent')
            ->exists();
    }
}
