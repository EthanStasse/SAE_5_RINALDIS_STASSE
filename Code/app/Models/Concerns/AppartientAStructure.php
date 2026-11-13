<?php

namespace App\Models\Concerns;

use App\Exceptions\StructureNonAutoriseeException;
use App\Models\Scopes\StructureScope;
use App\Models\Structure;
use App\Support\StructureCourante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * À utiliser sur TOUS les modèles métier (serres, légumes, paniers, mesures...).
 *
 * - lecture : filtre automatique sur la structure courante (StructureScope) ;
 * - création : structure_id rempli automatiquement ;
 * - écriture dans une autre structure ou changement de structure : refusés.
 *
 * La table doit avoir une colonne structure_id (clé étrangère + index).
 */
trait AppartientAStructure
{
    public static function bootAppartientAStructure(): void
    {
        static::addGlobalScope(new StructureScope);

        static::creating(function (Model $model): void {
            $courante = app(StructureCourante::class);

            if ($courante->filtreDesactive()) {
                if ($model->getAttribute('structure_id') === null) {
                    throw new StructureNonAutoriseeException('structure_id est obligatoire hors filtre.');
                }

                return;
            }

            if ($courante->id() === null) {
                throw new StructureNonAutoriseeException('Aucune structure courante : création impossible.');
            }

            $demandee = $model->getAttribute('structure_id');

            if ($demandee !== null && (int) $demandee !== $courante->id()) {
                throw new StructureNonAutoriseeException('Création dans une autre structure interdite.');
            }

            $model->setAttribute('structure_id', $courante->id());
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('structure_id') && ! app(StructureCourante::class)->filtreDesactive()) {
                throw new StructureNonAutoriseeException('Changement de structure interdit.');
            }
        });
    }

    /**
     * @return BelongsTo<Structure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
