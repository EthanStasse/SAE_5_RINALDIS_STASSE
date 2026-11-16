<?php

namespace App\Livewire\Serres;

use App\Models\Boitier;
use App\Models\Serre;
use App\Support\StructureCourante;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Boîtiers capteurs d'une serre (EF-C-02) : déclaration, régénération et
 * révocation du jeton. Réservé à l'administrateur du Jardin
 * (« parametrer-structure ») : un jeton donne le droit d'écrire des mesures.
 *
 * Le jeton en clair n'est affiché qu'une fois, juste après sa création ou sa
 * régénération : la base n'en garde que l'empreinte.
 */
class GestionBoitiers extends Component
{
    public Serre $serre;

    public string $nom = '';

    /** Jeton à afficher une seule fois ; null le reste du temps. */
    public ?string $jetonAffiche = null;

    public ?string $boitierDuJeton = null;

    public ?string $confirmation = null;

    public function mount(Serre $serre): void
    {
        $this->authorize('parametrer-structure');
        // La serre a été chargée avec le filtre par structure : une serre
        // d'un autre Jardin donne déjà un 404 avant d'arriver ici.
        $this->serre = $serre;
    }

    public function creer(): void
    {
        $this->authorize('parametrer-structure');
        $donnees = $this->validate();

        [$boitier, $jeton] = Boitier::creerPour($this->serre, trim($donnees['nom']));

        $this->afficherJeton($boitier, $jeton);
        $this->confirmation = "Le boîtier « {$boitier->nom} » a été déclaré.";
        $this->reset('nom');
    }

    public function regenerer(int $id): void
    {
        $this->authorize('parametrer-structure');
        $boitier = $this->boitierDeLaSerre($id);

        $jeton = $boitier->genererJeton();

        $this->afficherJeton($boitier, $jeton);
        $this->confirmation = "Nouveau jeton pour « {$boitier->nom} » : l'ancien ne fonctionne plus.";
    }

    public function revoquer(int $id): void
    {
        $this->authorize('parametrer-structure');
        $boitier = $this->boitierDeLaSerre($id);

        $boitier->revoquer();

        $this->masquerJeton();
        $this->confirmation = "Le boîtier « {$boitier->nom} » est révoqué : ses envois sont refusés.";
    }

    public function masquerJeton(): void
    {
        $this->jetonAffiche = null;
        $this->boitierDuJeton = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => [
                'required', 'string', 'max:100',
                // Même règle que la base : nom unique dans le Jardin.
                Rule::unique('boitiers', 'nom')->where('structure_id', app(StructureCourante::class)->id()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nom.required' => 'Le nom du boîtier est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'nom.unique' => 'Un boîtier porte déjà ce nom dans ce Jardin.',
        ];
    }

    public function render(): View
    {
        return view('livewire.serres.gestion-boitiers', [
            'boitiers' => $this->serre->boitiers()->orderBy('nom')->get(),
        ]);
    }

    /**
     * Un boîtier de CETTE serre : 404 pour un boîtier d'une autre serre ou d'un autre Jardin.
     */
    private function boitierDeLaSerre(int $id): Boitier
    {
        return $this->serre->boitiers()->findOrFail($id);
    }

    private function afficherJeton(Boitier $boitier, string $jeton): void
    {
        $this->jetonAffiche = $jeton;
        $this->boitierDuJeton = $boitier->nom;
    }
}
