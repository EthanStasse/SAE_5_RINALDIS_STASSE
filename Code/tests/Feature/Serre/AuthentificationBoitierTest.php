<?php

/*
 * Authentification des boîtiers par jeton : EF-C-02, EF-C-04 et isolation entre Jardins.
 */

use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Structure;
use App\Support\StructureCourante;

beforeEach(function () {
    $this->courante = app(StructureCourante::class);

    [$this->boitierA, $this->jetonA, $this->jetonB] = $this->courante->sansFiltre(function () {
        $a = Structure::factory()->create(['nom_commercial' => 'Jardin A']);
        $b = Structure::factory()->create(['nom_commercial' => 'Jardin B']);
        $serreA = Serre::factory()->create(['structure_id' => $a->id, 'nom' => 'Serre A1']);
        $serreB = Serre::factory()->create(['structure_id' => $b->id, 'nom' => 'Serre B1']);

        [$boitierA, $jetonA] = Boitier::creerPour($serreA, 'Boîtier nord');
        [, $jetonB] = Boitier::creerPour($serreB, 'Boîtier tunnel');

        return [$boitierA, $jetonA, $jetonB];
    });
});

describe('refus', function () {
    it('refuse une requête sans jeton', function () {
        $this->getJson('/api/v1/boitier')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertJsonPath('message', 'Jeton de boîtier manquant.');
    });

    it('refuse un jeton inconnu', function () {
        $this->withToken('jeton-invente')
            ->getJson('/api/v1/boitier')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Jeton de boîtier invalide ou révoqué.');
    });

    it('refuse un autre schéma que Bearer', function () {
        $this->withHeader('Authorization', 'Basic '.base64_encode('a:'.$this->jetonA))
            ->getJson('/api/v1/boitier')
            ->assertUnauthorized();
    });

    it("refuse le jeton d'un boîtier révoqué", function () {
        $this->boitierA->revoquer();

        $this->withToken($this->jetonA)
            ->getJson('/api/v1/boitier')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Jeton de boîtier invalide ou révoqué.');
    });

    it("refuse l'ancien jeton après une régénération", function () {
        $nouveau = $this->boitierA->genererJeton();

        $this->withToken($this->jetonA)->getJson('/api/v1/boitier')->assertUnauthorized();
        $this->withToken($nouveau)->getJson('/api/v1/boitier')->assertOk();
    });

    it("répond en JSON même sans l'en-tête Accept", function () {
        $this->get('/api/v1/boitier')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');

        $this->withToken($this->jetonA)
            ->get('/api/v1/route-inexistante')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    });
});

describe('accès autorisé', function () {
    it('reconnaît le boîtier et travaille dans son Jardin', function () {
        $this->withToken($this->jetonA)
            ->getJson('/api/v1/boitier')
            ->assertOk()
            ->assertExactJson([
                'id' => $this->boitierA->id,
                'nom' => 'Boîtier nord',
                'serre' => ['id' => $this->boitierA->serre_id, 'nom' => 'Serre A1'],
            ]);
    });

    it('place chaque boîtier dans son propre Jardin', function () {
        $this->withToken($this->jetonB)
            ->getJson('/api/v1/boitier')
            ->assertOk()
            ->assertJsonPath('serre.nom', 'Serre B1');
    });

    it("ne garde pas la structure d'une requête précédente", function () {
        $this->withToken($this->jetonA)->getJson('/api/v1/boitier')->assertOk();

        $this->withToken('jeton-invente')->getJson('/api/v1/boitier')->assertUnauthorized();

        expect($this->courante->id())->toBeNull();
    });

    it("n'expose jamais l'empreinte du jeton", function () {
        $reponse = $this->withToken($this->jetonA)->getJson('/api/v1/boitier');

        expect($reponse->getContent())
            ->not->toContain('jeton')
            ->not->toContain(Boitier::empreinte($this->jetonA));
    });
});
