<?php

namespace App\Models;

use App\Models\Concerns\AppartientAStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Boîtier capteur rattaché à une serre (module C, EF-C-02).
 *
 * Il s'authentifie auprès de l'API avec un jeton qui lui est propre. Seule
 * l'empreinte SHA-256 du jeton est stockée : le jeton en clair n'est affiché
 * qu'une fois, à la création ou à la régénération.
 */
class Boitier extends Model
{
    use AppartientAStructure;

    public const LONGUEUR_JETON = 48;

    protected $table = 'boitiers';

    protected $fillable = [
        'nom',
    ];

    /** L'empreinte ne sort jamais du modèle (JSON, API, journaux). */
    protected $hidden = [
        'jeton_hash',
    ];

    protected function casts(): array
    {
        return [
            'revoque_le' => 'immutable_datetime',
            'derniere_communication_le' => 'immutable_datetime',
        ];
    }

    /**
     * Crée un boîtier dans une serre et retourne [boîtier, jeton en clair].
     *
     * La structure est toujours celle de la serre. Si la serre appartient à un
     * autre Jardin que la structure courante, le trait AppartientAStructure
     * refuse la création (StructureNonAutoriseeException).
     *
     * @return array{0: self, 1: string}
     */
    public static function creerPour(Serre $serre, string $nom): array
    {
        $boitier = new self(['nom' => $nom]);
        $boitier->serre_id = $serre->id;
        $boitier->structure_id = $serre->structure_id;
        $jeton = $boitier->genererJeton();

        return [$boitier, $jeton];
    }

    /**
     * Génère (ou régénère) le jeton : l'ancien devient immédiatement invalide.
     * Régénérer le jeton réactive aussi un boîtier révoqué.
     */
    public function genererJeton(): string
    {
        // Str::random() s'appuie sur random_bytes() : générateur cryptographiquement sûr.
        $jeton = Str::random(self::LONGUEUR_JETON);
        $this->jeton_hash = self::empreinte($jeton);
        $this->jeton_prefixe = substr($jeton, 0, 8);
        $this->revoque_le = null;
        $this->save();

        return $jeton;
    }

    public function revoquer(): void
    {
        $this->revoque_le = now()->toImmutable();
        $this->save();
    }

    public function estRevoque(): bool
    {
        return $this->revoque_le !== null;
    }

    /**
     * SHA-256 et non bcrypt : le jeton est aléatoire et long (environ 285 bits),
     * donc impossible à deviner. Un hachage rapide suffit et permet de retrouver
     * le boîtier par une recherche indexée. bcrypt protège les mots de passe
     * humains, courts et devinables.
     */
    public static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    /**
     * @param  Builder<Boitier>  $requete
     */
    public function scopeActifs(Builder $requete): void
    {
        $requete->whereNull('revoque_le');
    }

    /**
     * @return BelongsTo<Serre, $this>
     */
    public function serre(): BelongsTo
    {
        return $this->belongsTo(Serre::class);
    }

    /**
     * @return HasMany<Mesure, $this>
     */
    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }
}
