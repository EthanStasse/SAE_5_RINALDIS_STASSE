# Cocagne : logiciel de gestion des Jardins de Cocagne

[![CI](https://github.com/EthanStasse/SAE_5_RINALDIS_STASSE/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/EthanStasse/SAE_5_RINALDIS_STASSE/actions/workflows/ci.yml)

SAÉ 5, BUT Informatique 3e année, IUT de Saint-Dié-des-Vosges.
Binôme : **Damien RINALDIS** et **Ethan STASSE**.

Les Jardins de Cocagne sont des jardins d'insertion qui vendent des paniers de légumes à leurs adhérents. Ce logiciel permet à plusieurs Jardins de partager une même application, chacun ne voyant que ses propres données.

## Modules

| Module | Sujet | Responsable | État |
| --- | --- | --- | --- |
| A | Composition des paniers à partir du stock | Ethan STASSE | en cours |
| C | Surveillance de serre connectée (capteurs, alertes) | Damien RINALDIS | code fait, essais sur matériel en attente |
| D | Cybersécurité : authentification des adhérents | Damien RINALDIS | connexion faite, reste en cours |

## Technologies

Laravel (PHP 8.3), Livewire, Tailwind CSS, PostgreSQL 16, Docker Compose, tests Pest, intégration continue GitHub Actions. Côté matériel : Arduino, ESP32 et passerelle Python sur Raspberry Pi.

## Structure du dépôt

| Dossier | Contenu |
| --- | --- |
| [`Code/`](Code/) | Application Laravel (voir son [README](Code/README.md) pour l'installation détaillée) |
| [`materiel/`](materiel/) | Programmes Arduino et ESP32, passerelle Raspberry Pi, guides de montage |
| [`Planning/`](Planning/) | Planning et plan des commits |
| [`Rendus/`](Rendus/) | Documents rendus : proposition technique, devis, benchmark |
| [`.github/`](.github/) | Intégration continue (GitHub Actions) |

## Démarrage rapide

Prérequis : Docker, Docker Compose et Git (sous Windows, utiliser WSL ou Git Bash).

```bash
git clone https://github.com/EthanStasse/SAE_5_RINALDIS_STASSE.git
cd SAE_5_RINALDIS_STASSE/Code
./demarrer.sh
```

L'application est ensuite disponible sur http://localhost:8080 et les e-mails envoyés sont visibles dans Mailpit sur http://localhost:8025. Les commandes du quotidien et les comptes de démonstration sont décrits dans [`Code/README.md`](Code/README.md).

## Contribuer

Nous suivons **GitHub Flow** : la branche `main` est protégée et toute modification passe par une branche courte, une pull request, l'intégration continue au vert et la relecture de l'autre membre du binôme. Les règles détaillées seront décrites dans `CONTRIBUTING.md`.
