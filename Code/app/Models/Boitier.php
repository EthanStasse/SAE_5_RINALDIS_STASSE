<?php

namespace App\Models;

// À ADAPTER : remplace par le vrai namespace de ton trait.
use App\Models\Concerns\AppartientAStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Boitier extends Model
{
    use AppartientAStructure;

    public const LONGUEUR_JETON = 48;

    protected $table = 'boitiers';

    protected $fillable = ['nom'];

    // L'empreinte ne sort jamais du modèle (JSON, API, logs).
    protected $hidden = ['jeton_hash'];

    protected function casts(): array
    {
        return [
            'revoque_le' => 'immutable_datetime',
            'derniere_communication_le' => 'immutable_datetime',
        ];
    }

    /**
     * Crée un boîtier dans une serre et retourne [boîtier, jeton en clair].
     * Le jeton en clair n'est affiché qu'une seule fois : il n'est stocké nulle part.
     *
     * La structure est TOUJOURS celle de la serre, jamais une valeur reçue du formulaire.
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
     * Régénérer un jeton réactive aussi un boîtier révoqué.
     */
    public function genererJeton(): string
    {
        // Str::random utilise random_bytes() : générateur cryptographiquement sûr.
        $jeton = Str::random(self::LONGUEUR_JETON);
        $this->jeton_hash = self::empreinte($jeton);
        $this->jeton_prefixe = substr($jeton, 0, 8);
        $this->revoque_le = null;
        $this->save();

        return $jeton;
    }

    public function revoquer(): void
    {
        $this->revoque_le = now();
        $this->save();
    }

    public function estRevoque(): bool
    {
        return $this->revoque_le !== null;
    }

    /**
     * SHA-256 et non bcrypt : le jeton est aléatoire et très long (≈ 285 bits),
     * donc impossible à deviner par force brute. Un hachage rapide suffit, et
     * il permet de retrouver le boîtier par une simple recherche indexée.
     * bcrypt sert aux mots de passe humains, qui sont courts et devinables.
     */
    public static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    public function scopeActifs(Builder $requete): void
    {
        $requete->whereNull('revoque_le');
    }

    public function serre(): BelongsTo
    {
        return $this->belongsTo(Serre::class);
    }

    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }
}
