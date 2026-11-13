<?php

namespace App\Models\Scopes;

use App\Support\StructureCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Ajoute « WHERE structure_id = <structure courante> » à toute requête Eloquent.
 * Sans structure courante, la requête ne renvoie rien.
 */
class StructureScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $courante = app(StructureCourante::class);

        if ($courante->filtreDesactive()) {
            return;
        }

        if ($courante->id() === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('structure_id'), $courante->id());
    }
}
