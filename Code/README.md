# Cocagne : logiciel de gestion commerciale des Jardins de Cocagne

SAÉ 5, BUT Informatique, IUT de Saint-Dié-des-Vosges. RINALDIS Damien, STASSE Ethan.

Socle technique : Laravel (PHP 8.3), PostgreSQL 16, Docker. Plusieurs Jardins (structures) partagent une seule base ; chaque donnée métier est isolée par `structure_id`.

## Prérequis

- Docker et Docker Compose (Docker Desktop sous Windows ou macOS)
- Git
- Sous Windows : lancer les scripts depuis WSL ou Git Bash

## Première installation (une seule personne, une seule fois)

```bash
./demarrer.sh
```

Le script crée le squelette Laravel autour de nos fichiers, installe les paquets, configure `.env`, active les rôles par structure, crée la base, charge les données de démonstration et lance les tests. Comptez 5 à 10 minutes la première fois.

Ensuite, **committez tout** (dont `composer.json` et `composer.lock`) et poussez sur GitHub. Le fichier `.env` reste local (il est dans `.gitignore`).

## Installation pour le coéquipier

```bash
git clone <dépôt> && cd <dépôt>
./demarrer.sh
```

## Au quotidien

| Action | Commande |
| --- | --- |
| Démarrer | `docker compose up -d` |
| Arrêter | `docker compose down` |
| Commande artisan | `docker compose exec app php artisan <commande>` |
| Tests | `docker compose exec app ./vendor/bin/pest` |
| Corriger le style | `docker compose exec app ./vendor/bin/pint` |
| Analyse statique | `docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G` |
| Repartir d'une base propre | `docker compose exec app php artisan migrate:fresh --seed` |
| Compiler le CSS (Tailwind) | `docker compose run --rm node npm install` puis `docker compose run --rm node npm run build` |

| Service | Adresse |
| --- | --- |
| Application | http://localhost:8080 |
| E-mails envoyés (Mailpit) | http://localhost:8025 |
| PostgreSQL (DBeaver, pgAdmin...) | localhost:5433, base `cocagne`, utilisateur `cocagne`, mot de passe `cocagne` |

## Comptes de démonstration

Mot de passe : `password`. **Uniquement en local** : ces comptes ne doivent jamais exister sur le serveur de recette.

| Compte | Jardin | Rôle |
| --- | --- | --- |
| admin.thaon@cocagne.test | Thaon-les-Vosges | admin-structure |
| equipe.thaon@cocagne.test | Thaon-les-Vosges | equipe |
| insertion.thaon@cocagne.test | Thaon-les-Vosges | insertion |
| adherent.thaon@cocagne.test | Thaon-les-Vosges | adherent |
| admin.graine@cocagne.test | Graine de Cocagne | admin-structure |
| equipe.graine@cocagne.test | Graine de Cocagne | equipe |
| double@cocagne.test | les deux | equipe à Thaon, benevole à Graine |

La page de connexion n'existe pas encore : elle arrive avec le module D (Laravel Fortify).

## Isolation entre Jardins : la règle à suivre

Chaque table métier (légumes, paniers, serres, mesures...) :

1. a une colonne `structure_id` avec clé étrangère et index (modèle : `database/migrations/2026_11_16_000003_create_serres_table.php`) ;
2. a un modèle qui utilise le trait `AppartientAStructure` (modèle : `app/Models/Serre.php`) ;
3. a ses tests d'isolation (modèle : `tests/Feature/IsolationStructuresTest.php`).

Ce que fait le trait :

- **lecture** : `WHERE structure_id = <structure courante>` ajouté automatiquement ; sans structure courante, la requête ne renvoie rien ;
- **création** : `structure_id` rempli automatiquement ;
- **écriture dans un autre Jardin, changement de Jardin** : exception `StructureNonAutoriseeException`.

La structure courante est définie à chaque requête par le middleware `DefinirStructureCourante`, à partir des rôles du compte. Il s'exécute avant la liaison des modèles aux routes (`bootstrap/app.php`), si bien qu'une URL vers la donnée d'un autre Jardin répond 404.

Hors requête HTTP (seeders, tâches planifiées, tests), il faut être explicite :

```php
app(StructureCourante::class)->definir($structure->id);          // travailler dans un Jardin
app(StructureCourante::class)->sansFiltre(fn () => Serre::all()); // tout voir (admin réseau, seeders)
```

Les requêtes `DB::table(...)` brutes **ne sont pas filtrées** : utilisez Eloquent.

## Rôles et permissions

`spatie/laravel-permission` en mode « teams » : un rôle est attribué à un compte **dans** une structure.

```php
setPermissionsTeamId($structure->id);
$user->assignRole('equipe');
```

La matrice des droits est dans `database/seeders/RolesEtPermissionsSeeder.php` (section 2.3 du cahier des charges).

## Ce qui reste à faire sur le socle

- [ ] Module D : Laravel Fortify (connexion, 2FA, réinitialisation), politique de mot de passe
- [ ] Journal d'audit (`spatie/laravel-activitylog`), exigence EF-S-08
- [ ] Rôle administrateur réseau : écrans multi-structures avec `sansFiltre()`
- [ ] Choix de la structure active pour un compte membre de plusieurs Jardins (EF-S-06)
- [ ] Sécurité au niveau des lignes (RLS) PostgreSQL en filet de sécurité. Attention : le compte `cocagne` de Docker est superutilisateur et ignore la RLS. Il faudra un rôle applicatif dédié, non superutilisateur, avec `FORCE ROW LEVEL SECURITY`.
- [ ] Service `scheduler` dans Docker pour les tâches planifiées (alertes de serre)

## Intégration continue

`.github/workflows/ci.yml` lance, à chaque push : Pint, Larastan (niveau 5), les tests Pest sur PostgreSQL, puis `composer audit`.
