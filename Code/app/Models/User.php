<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte unique par personne (CDC §11). Les rôles sont attribués PAR structure
 * grâce au mode « teams » de spatie/laravel-permission (colonne structure_id).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'actif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
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
}
