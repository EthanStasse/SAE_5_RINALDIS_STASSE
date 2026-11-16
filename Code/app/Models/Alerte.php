<?php

namespace App\Models;

use App\Enums\Grandeur;
use App\Enums\TypeAlerte;
use App\Models\Concerns\AppartientAStructure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerte d'une serre (EF-C-09, EF-C-10). Ouverte tant que fin_le est vide.
 *
 * @property TypeAlerte $type
 * @property Grandeur|null $grandeur
 * @property float|null $valeur
 * @property CarbonImmutable $debut_le
 * @property CarbonImmutable|null $fin_le
 * @property CarbonImmutable|null $notifiee_le
 */
class Alerte extends Model
{
    use AppartientAStructure;

    protected $table = 'alertes';

    protected $fillable = [
        'boitier_id',
        'type',
        'grandeur',
        'valeur',
        'message',
        'debut_le',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeAlerte::class,
            'grandeur' => Grandeur::class,
            'valeur' => 'float',
            'debut_le' => 'immutable_datetime',
            'fin_le' => 'immutable_datetime',
            'notifiee_le' => 'immutable_datetime',
        ];
    }

    public function estOuverte(): bool
    {
        return $this->fin_le === null;
    }

    public function fermer(): void
    {
        $this->fin_le = now()->toImmutable();
        $this->save();
    }

    /**
     * @param  Builder<Alerte>  $requete
     */
    public function scopeOuvertes(Builder $requete): void
    {
        $requete->whereNull('fin_le');
    }

    /**
     * @return BelongsTo<Serre, $this>
     */
    public function serre(): BelongsTo
    {
        return $this->belongsTo(Serre::class);
    }

    /**
     * @return BelongsTo<Boitier, $this>
     */
    public function boitier(): BelongsTo
    {
        return $this->belongsTo(Boitier::class);
    }
}
