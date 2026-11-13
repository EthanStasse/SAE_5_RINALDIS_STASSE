<?php

namespace App\Support;

/**
 * Structure (Jardin) dans laquelle travaille la requête en cours.
 *
 * Par défaut, aucune structure n'est définie : les modèles filtrés ne renvoient alors
 * rien (fermeture par défaut). Le middleware DefinirStructureCourante la définit pour
 * chaque requête authentifiée. Les seeders et l'administration réseau utilisent
 * sansFiltre() de façon explicite.
 */
final class StructureCourante
{
    private ?int $id = null;

    private bool $filtreDesactive = false;

    public function definir(int $id): void
    {
        $this->id = $id;
    }

    public function oublier(): void
    {
        $this->id = null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function filtreDesactive(): bool
    {
        return $this->filtreDesactive;
    }

    /**
     * Exécute le code donné sans filtre par structure. À réserver aux seeders,
     * aux tâches planifiées et aux écrans de l'administrateur réseau.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function sansFiltre(callable $callback): mixed
    {
        $precedent = $this->filtreDesactive;
        $this->filtreDesactive = true;

        try {
            return $callback();
        } finally {
            $this->filtreDesactive = $precedent;
        }
    }
}
