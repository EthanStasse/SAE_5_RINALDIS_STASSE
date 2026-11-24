# Contribuer au projet Cocagne

Ce document fixe les règles de travail du binôme (Damien RINALDIS, Ethan STASSE). Toute modification du dépôt les respecte.

## 1. Stratégie de branches : GitHub Flow

- `main` est la seule branche permanente. Elle est **protégée** et doit toujours être stable : la CI y est au vert et le code y est déployable.
- Chaque tâche se fait sur une **branche courte**, créée depuis un `main` à jour, puis fusionnée par une pull request et supprimée.
- Personne ne pousse directement sur `main`, administrateur compris.

Nous avons écarté Git Flow, trop lourd pour deux personnes sur trois semaines (branches `develop` et `release` inutiles), et le trunk-based development, qui ne prévoit pas de relecture avant intégration.

## 2. Nommage des branches

Format : `type/portee-description`, en minuscules, avec des tirets, sans accents ni espaces.

| Exemple | Utilisation |
| --- | --- |
| `feat/paniers-algo` | nouvelle fonctionnalité |
| `fix/serre-alerte-doublon` | correction d'un bogue |
| `test/paniers-jeu-essai` | ajout ou correction de tests |
| `docs/contributing` | documentation |
| `ci/tests-passerelle` | intégration continue |
| `build/docker-prod` | images Docker, paquetage |
| `chore/maj-dependances` | outillage, maintenance |

Une branche correspond à **une seule tâche**. Elle vit au plus deux ou trois jours.

## 3. Messages de commit

Format : `type(portee): description`, suivi si possible de l'exigence entre parenthèses.

```
feat(paniers): algorithme v1 de remplissage jusqu'au prix cible (EF-A-06)
fix(serre): ignore une mesure déjà reçue avec le même uuid
test(auth): blocage du compte après 5 échecs (EF-D-06)
```

- **Types** : `feat`, `fix`, `test`, `docs`, `refactor`, `perf`, `ci`, `build`, `chore`.
- **Portées** : `paniers`, `serre`, `auth`, `securite`, `socle`, `materiel`, `depot`, `ci`, `docker`.
- La description est au présent, en français, sans point final, et dit **ce que fait** le commit.
- Un commit contient une étape qui fonctionne, avec ses tests. Chacun commite avec son propre compte GitHub.

## 4. Pull requests

1. Avant d'ouvrir la PR, mettre sa branche à jour avec `main` (`git pull origin main`) et lancer les tests en local.
2. Ouvrir la PR vers `main` et remplir le modèle (description, exigences, comment tester).
3. Désigner **l'autre membre du binôme** comme reviewer.
4. La PR ne peut être fusionnée que si :
   - la CI (`qualite`) est au vert ;
   - l'autre membre l'a **approuvée** ;
   - la branche est à jour avec `main`.
5. Une PR reste petite : idéalement moins de 400 lignes modifiées. Au-delà, la découper.

## 5. Relecture

Le relecteur vérifie :

- que le code fait ce que décrit la PR et correspond à l'exigence citée ;
- que les tests couvrent le nouveau comportement ;
- que chaque nouveau modèle métier utilise le trait `AppartientAStructure` (isolation entre Jardins) ;
- qu'aucun secret n'est versionné (mot de passe, jeton, fichier `.env`, `config.toml` de la passerelle) ;
- que le code reste lisible : noms clairs, pas de code mort.

Il laisse au moins un commentaire, puis choisit **Approve** ou **Request changes**. Nous nous relisons au moins une fois par jour.

| Auteur de la PR | Relecteur |
| --- | --- |
| Ethan (paniers, modèles de PR et d'issues) | Damien |
| Damien (serre, cybersécurité, CI, Docker, livraison) | Ethan |

## 6. Fusion

Nous fusionnons avec **« Create a merge commit »**. Ce mode garde chaque commit avec son auteur et sa date, donc la progression du projet reste lisible dans `main`, et chaque commit de fusion renvoie à sa pull request. En contrepartie, chaque commit doit avoir un message propre.

C'est l'auteur de la PR qui fusionne, après approbation. La branche est ensuite supprimée automatiquement sur GitHub. En local, on la supprime avec `git branch -d <branche>`.

## 7. Secrets

Aucun secret dans le dépôt. Les valeurs locales vont dans `Code/.env` ou `materiel/passerelle/config.toml`, tous deux ignorés par Git. Les secrets de la CI et du déploiement sont rangés dans **Settings → Secrets and variables → Actions** sur GitHub.
