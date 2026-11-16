<?php

namespace App\Livewire\Serres;

use App\Models\Serre;
use App\Support\StructureCourante;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Liste et déclaration des serres du Jardin courant (EF-C-01).
 *
 * Lecture : permission « voir-serres ». Création, modification, suppression :
 * permission « parametrer-structure » (administrateur de la structure).
 *
 * Toutes les recherches passent par le modèle Serre, donc par le filtre par
 * structure : une serre d'un autre Jardin est introuvable (404), même si on
 * envoie son identifiant à la main.
 */
class GestionSerres extends Component
{
    /** Serre en cours de modification ; null pour une création. */
    public ?int $serreEnCours = null;

    public bool $formulaireOuvert = false;

    public string $nom = '';

    public string $emplacement = '';

    public ?string $confirmation = null;

    public function mount(): void
    {
        $this->authorize('voir-serres');
    }

    public function creer(): void
    {
        $this->authorize('parametrer-structure');
        $this->ouvrirFormulaire(null, '', '');
    }

    public function modifier(int $id): void
    {
        $this->authorize('parametrer-structure');
        $serre = Serre::findOrFail($id);
        $this->ouvrirFormulaire($serre->id, $serre->nom, (string) $serre->emplacement);
    }

    public function enregistrer(): void
    {
        $this->authorize('parametrer-structure');
        $donnees = $this->validate();

        $serre = $this->serreEnCours === null ? new Serre : Serre::findOrFail($this->serreEnCours);
        $serre->nom = trim($donnees['nom']);
        $serre->emplacement = trim($donnees['emplacement']) ?: null;
        // structure_id est rempli par le trait AppartientAStructure à la création.
        $serre->save();

        $this->confirmation = $this->serreEnCours === null
            ? "La serre « {$serre->nom} » a été déclarée."
            : "La serre « {$serre->nom} » a été modifiée.";
        $this->annuler();
    }

    public function supprimer(int $id): void
    {
        $this->authorize('parametrer-structure');
        $serre = Serre::withCount('boitiers')->findOrFail($id);

        // L'historique des mesures est précieux : on ne supprime pas une serre équipée.
        if ($serre->boitiers_count > 0) {
            $this->addError('suppression', "La serre « {$serre->nom} » a des boîtiers : révoquez-les plutôt que de supprimer la serre.");

            return;
        }

        $serre->delete();
        $this->confirmation = "La serre « {$serre->nom} » a été supprimée.";
    }

    public function annuler(): void
    {
        $this->reset(['serreEnCours', 'formulaireOuvert', 'nom', 'emplacement']);
        $this->resetValidation();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => [
                'required', 'string', 'max:100',
                // Le nom est unique dans le Jardin, pas dans toute la base.
                Rule::unique('serres', 'nom')
                    ->where('structure_id', app(StructureCourante::class)->id())
                    ->ignore($this->serreEnCours),
            ],
            'emplacement' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la serre est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'nom.unique' => 'Une serre porte déjà ce nom dans ce Jardin.',
            'emplacement.max' => "L'emplacement ne doit pas dépasser 255 caractères.",
        ];
    }

    public function render(): View
    {
        return view('livewire.serres.gestion-serres', [
            'serres' => Serre::withCount('boitiers')->orderBy('nom')->get(),
            'peutGerer' => auth()->user()?->can('parametrer-structure') ?? false,
        ]);
    }

    private function ouvrirFormulaire(?int $id, string $nom, string $emplacement): void
    {
        $this->resetValidation();
        $this->confirmation = null;
        $this->serreEnCours = $id;
        $this->nom = $nom;
        $this->emplacement = $emplacement;
        $this->formulaireOuvert = true;
    }
}
