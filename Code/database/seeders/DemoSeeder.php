<?php

namespace Database\Seeders;

use App\Models\Serre;
use App\Models\Structure;
use App\Models\User;
use App\Support\StructureCourante;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration : 2 Jardins, des comptes par rôle, quelques serres.
 * Mot de passe de tous les comptes : "password" (développement local uniquement).
 * Idempotent : peut être relancé.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        app(StructureCourante::class)->sansFiltre(function (): void {
            $thaon = Structure::firstOrCreate(
                ['nom_commercial' => 'Jardins de Cocagne de Thaon-les-Vosges'],
                ['email' => 'contact@thaon.cocagne.test', 'assujettie_tva' => false],
            );
            $graine = Structure::firstOrCreate(
                ['nom_commercial' => 'Graine de Cocagne'],
                ['email' => 'contact@graine.cocagne.test', 'assujettie_tva' => false],
            );

            $this->compte('admin.thaon@cocagne.test', 'Admin Thaon', $thaon, 'admin-structure');
            $this->compte('equipe.thaon@cocagne.test', 'Équipe Thaon', $thaon, 'equipe');
            $this->compte('insertion.thaon@cocagne.test', 'Salarié Thaon', $thaon, 'insertion');
            $this->compte('adherent.thaon@cocagne.test', 'Adhérent Thaon', $thaon, 'adherent');
            $this->compte('admin.graine@cocagne.test', 'Admin Graine', $graine, 'admin-structure');
            $this->compte('equipe.graine@cocagne.test', 'Équipe Graine', $graine, 'equipe');

            // Un compte présent dans les deux Jardins (CDC §11 : un compte, plusieurs rôles).
            $this->compte('double@cocagne.test', 'Compte double', $thaon, 'equipe');
            $this->compte('double@cocagne.test', 'Compte double', $graine, 'benevole');

            Serre::firstOrCreate(['structure_id' => $thaon->id, 'nom' => 'Serre nord'], ['emplacement' => 'Parcelle 1']);
            Serre::firstOrCreate(['structure_id' => $thaon->id, 'nom' => 'Serre sud'], ['emplacement' => 'Parcelle 2']);
            Serre::firstOrCreate(['structure_id' => $graine->id, 'nom' => 'Tunnel 1'], ['emplacement' => 'Entrée']);
        });

        setPermissionsTeamId(null);
    }

    private function compte(string $email, string $nom, Structure $structure, string $role): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nom, 'password' => Hash::make('password')],
        );

        setPermissionsTeamId($structure->id);
        $user->unsetRelation('roles');

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
