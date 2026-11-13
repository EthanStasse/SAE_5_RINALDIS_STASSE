# Plan des commits : semaines 6 à 8

Nov 13, 2026 · @7

Il vous reste 3 semaines, soit environ 135 h à deux : ce plan prévoit 42 commits, environ un par jour et par personne, et ne garde que le cœur de chaque module. Il remplace le planning détaillé sur 9 semaines, qui n'est plus valable.

**Répartition** (à échanger si vous préférez) : Dev 1 fait l'authentification puis la serre ; Dev 2 fait les paniers puis la recette.

**Règles de travail**

- Un commit = une tâche terminée **avec ses tests**, poussée le jour même.
- Chacun commite avec son propre compte GitHub.
- Une branche par fonctionnalité (`feat/auth-connexion`, `feat/paniers-algo`...), fusionnée par une pull request relue par l'autre.
- Messages au format `type(module): description (exigence)`. Types : `feat` (fonctionnalité), `fix` (correction), `test`, `docs`, `chore` (outillage), `perf`.
- Les messages ci-dessous sont des propositions : adaptez-les à ce que vous avez réellement fait.
- Si une tâche prend plus d'une journée, committez les étapes intermédiaires qui fonctionnent.

## Planning des 8 semaines

Les cases cochées correspondent à ce que vous m'avez montré. Complétez ce qui manque, notamment les rendus 1 et 2, que je ne connais pas.

### Semaines 1 à 5 : études et cadrage (terminées)

- [ ] Rendus 1 et 2 : à compléter
- [x] Étude du cahier des charges du Réseau Cocagne et attribution des sujets A, C, D
- [x] Rendu 3 : proposition technique (15 octobre 2026)
- [x] Rendu 4 : devis D-2026-001 (30 octobre 2026)
- [x] Cahier des charges fonctionnel et technique (13 novembre 2026)
- [x] Socle technique qui tourne en local : Docker, Laravel, PostgreSQL, isolation par structure, rôles
- [x] Plan des commits des semaines 6 à 8
- [ ] Socle poussé sur GitHub et premier commit de chacun (à faire ce soir)

### Semaine 6 : connexion et algorithme des paniers

- [ ] Module D : connexion, mot de passe, blocage, activation, page adhérent (8 commits, Dev 1)
- [ ] Module A : données, jeu d'essai, algorithme v1, stock insuffisant (7 commits, Dev 2)

### Semaine 7 : capteurs et écrans de composition

- [ ] Module C : tables, API des mesures, programme ESP32, tableau de bord (7 commits, Dev 1)
- [ ] Module A : optimisation, écrans, validation (7 commits, Dev 2)

### Semaine 8 : alertes, finitions et recette

- [ ] Module C : seuils, alertes, e-mails, guide de montage ; module D : double authentification (6 commits, Dev 1)
- [ ] Socle : comptes, journal d'audit ; tests de bout en bout, serveur de recette, documentation (6 commits, Dev 2)
- [ ] Version 1.0 et soutenance

Le détail commit par commit des semaines 6 à 8 suit.

## Semaine 6 : connexion et algorithme

Objectif de fin de semaine : on se connecte, et l'algorithme compose les paniers du jeu d'essai sans interface. 15 commits.

**Dev 1 : authentification (module D)**

- [ ] `chore: socle initial (Docker, Laravel, isolation par structure)`, à pousser dès aujourd'hui
- [ ] `feat(auth): connexion par e-mail et mot de passe avec Fortify (EF-D-01, EF-D-10)`
- [ ] `feat(auth): politique de mot de passe de 12 caractères (EF-D-05)`
- [ ] `feat(auth): blocage après 5 échecs et messages génériques (EF-D-06, EF-D-07)`
- [ ] `feat(auth): mot de passe oublié avec lien valable 60 minutes (EF-D-04)`
- [ ] `feat(auth): activation du compte par lien à usage unique (EF-D-02)`
- [ ] `feat(auth): changement de mot de passe et page adhérent (EF-D-03, EF-D-14)`
- [ ] `test(auth): user stories US-D1 à US-D3`

**Dev 2 : paniers (module A)**

- [ ] `docs: README avec l'équipe et les conventions de commit`, à pousser dès aujourd'hui
- [ ] `feat(paniers): légumes et types de paniers (EF-A-01, EF-A-02)`
- [ ] `feat(paniers): périodes, stock et nombre de paniers (EF-A-03 à EF-A-05)`
- [ ] `test(paniers): jeu d'essai de référence de 797 €`
- [ ] `feat(paniers): algorithme v1 de remplissage jusqu'au prix cible (EF-A-06)`
- [ ] `feat(paniers): contrôles de valeur et de stock, tolérance de 10 % (EF-A-07, EF-A-08)`
- [ ] `feat(paniers): message chiffré si le stock est insuffisant (EF-A-11)`

## Semaine 7 : capteurs et écrans de composition

Objectif de fin de semaine : le boîtier envoie de vraies mesures, et l'équipe compose et valide les paniers depuis l'interface. 14 commits.

**Dev 1 : serre (module C)**

- [ ] `feat(serre): tables des boîtiers, mesures, seuils et alertes`
- [ ] `feat(serre): déclaration des boîtiers avec jeton Sanctum (EF-C-01, EF-C-02)`
- [ ] `feat(serre): API POST /api/v1/mesures avec contrôle des plages (EF-C-04, EF-C-05)`
- [ ] `feat(esp32): lecture de la température, de l'humidité et de la luminosité (EF-C-03)`, code dans un dossier `esp32/` du dépôt
- [ ] `feat(esp32): envoi HTTPS des mesures toutes les 5 minutes`
- [ ] `feat(serre): tableau de bord avec dernières valeurs et courbe sur 24 h (EF-C-07)`
- [ ] `test(serre): réception, rejet et isolation des mesures`

**Dev 2 : paniers (module A)**

- [ ] `feat(paniers): échanges locaux pour réduire le reste de stock (EF-A-09)`
- [ ] `feat(paniers): composition identique pour un même type de panier (EF-A-10)`
- [ ] `perf(paniers): calcul en moins de 10 secondes (ENF-PERF-01)`
- [ ] `feat(paniers): écrans des légumes et des types de paniers`
- [ ] `feat(paniers): saisie du stock et lancement du calcul`
- [ ] `feat(paniers): tableau de résultat avec écarts et reste (EF-A-12)`
- [ ] `feat(paniers): validation et historique des compositions (EF-A-13)`

## Semaine 8 : alertes, finitions et recette

Objectif de fin de semaine : une version 1.0 déployée et documentée, prête pour la soutenance. 13 commits.

**Dev 1 : serre et sécurité**

- [ ] `feat(serre): réglage des seuils par serre (EF-C-08)`
- [ ] `feat(serre): alerte de seuil sur 2 mesures consécutives (EF-C-09)`
- [ ] `feat(serre): alerte de capteur muet après 30 minutes (EF-C-10)`
- [ ] `feat(serre): envoi des alertes par e-mail et tâche planifiée dans Docker (EF-C-11)`
- [ ] `feat(auth): double authentification pour les administrateurs (EF-D-08)`
- [ ] `docs(serre): guide de montage du boîtier (EF-C-15)`

**Dev 2 : socle, recette et documentation**

- [ ] `feat(socle): gestion des comptes par l'administrateur de structure (EF-S-05)`
- [ ] `feat(socle): journal d'audit et journal des connexions (EF-S-08, EF-D-11)`
- [ ] `test: scénarios de bout en bout US-A1 à US-A3 et US-S1`
- [ ] `chore(deploy): configuration du serveur de recette`
- [ ] `docs: guide utilisateur court (équipe, administrateur, adhérent)`
- [ ] `docs: cahier de recette`

**À deux**

- [ ] Derniers `fix` issus de la recette, puis le tag `v1.0` sur la branche principale

## Sujet A : composition des paniers, ce qui reste

**État actuel** : rien n'est fait en dehors du socle (structures, comptes, rôles, isolation). Il reste les 13 exigences indispensables EF-A-01 à EF-A-13, en 7 étapes.

### A1. Les données (semaine 6)

- Créer 5 tables, toutes avec `structure_id` et le trait `AppartientAStructure`, comme `serres` :
  - `legumes` : nom, unité (kg, pièce, botte...), prix unitaire, périssable (oui/non) ;
  - `types_paniers` : libellé, prix cible ;
  - `periodes` : type (semaine ou mois), date de début, date de fin ;
  - `lignes_stock` : période, légume, quantité disponible ;
  - `besoins_paniers` : période, type de panier, nombre de paniers.
- **Attention** : stockez les prix en `decimal(8,2)`, jamais en `float`. Dans l'algorithme, calculez en centimes (entiers) pour éviter les erreurs d'arrondi.
- **Fini quand** : un test d'isolation passe pour chaque table.

### A2. Le jeu d'essai (semaine 6)

- Un seeder ou une fonction de test qui crée : carottes 120 kg à 1,80 €, pommes de terre 150 kg à 1,50 €, courges 80 kg à 2,20 €, poireaux 60 kg à 3,00 € (797 € au total) ; 25 paniers simples à 13,80 € et 15 familiaux à 23,70 € (700,50 €).
- Il servira à tous les tests de l'algorithme et à la démonstration.

### A3. L'algorithme v1 (semaine 6)

- Une classe de service, par exemple `app/Paniers/ComposeurPaniers.php`, **sans accès à la base** : elle reçoit des tableaux (stock, besoins, paramètres) et renvoie un résultat. C'est ce qui la rend facile à tester.
- Principe : pour chaque type de panier, ajouter un pas de légume à la fois (0,1 kg ou 1 pièce) jusqu'à atteindre le prix cible. Choisir à chaque pas le légume dont il reste le plus en valeur.
- **Attention** : la quantité d'un légume dans un type de panier est multipliée par le nombre de paniers de ce type. Le contrôle de stock porte sur ce total, tous types confondus.
- **Fini quand** : sur le jeu d'essai, chaque panier vaut au moins son prix cible et au plus +10 %, et aucun légume ne dépasse son stock.

### A4. Le stock insuffisant (semaine 6)

- Si la valeur totale du stock est inférieure à la somme des prix cibles, renvoyer un échec qui chiffre le manque (600 € de stock pour 700,50 € de cibles : il manque 100,50 €).
- Gérer aussi le cas où la valeur suffit mais où les quantités ne tombent pas juste.

### A5. L'optimisation (semaine 7)

- Échanges locaux : essayer de remplacer un pas d'un légume par un pas d'un autre, et garder l'échange s'il réduit le reste de stock sans sortir de la tolérance (EF-A-09).
- **Point à comprendre pour la soutenance** : « écouler le stock » pousse à remplir davantage les paniers, « respecter le prix cible » pousse à rester au plus près. La tolérance de 10 % est le compromis entre les deux.
- Un test de performance : 30 légumes et 5 types de paniers en moins de 10 secondes.

### A6. Les écrans (semaine 7)

- Pages Livewire : liste des légumes, liste des types de paniers, puis une page « Période » avec la saisie du stock, du nombre de paniers, un bouton « Calculer » et le tableau du résultat (légumes, valeur par panier, écart au prix cible, reste).
- Protéger les pages par la permission `gerer-paniers` ; le rôle `insertion` voit le résultat en lecture seule.

### A7. La validation (semaine 7)

- Tables `compositions` (période, statut proposée ou validée, reste, validée par, validée le) et `lignes_composition` (type de panier, légume, quantité par panier).
- Une composition validée ne doit plus pouvoir être modifiée : le refuser dans le modèle, pas seulement dans l'écran. Une seule composition validée par période.
- **Fini quand** : les user stories US-A1, US-A2 et US-A3 du cahier des charges passent.

## Sujet C : surveillance de serre, ce qui reste

**État actuel** : la table `serres`, son modèle et une route d'affichage existent dans le socle, et le matériel est disponible. Il reste 11 exigences indispensables, en 7 étapes.

### C1. Les données (semaine 7)

- 4 tables avec `structure_id` :
  - `boitiers` : serre, identifiant, empreinte du jeton, intervalle de mesure, dernière réception, actif ;
  - `mesures` : boîtier, date en UTC, température, humidité, luminosité ;
  - `seuils` : serre, grandeur (température, humidité, luminosité), minimum, maximum ;
  - `alertes` : serre, boîtier, type (seuil haut, seuil bas, capteur muet), valeur, ouverte le, fermée le, acquittée par.
- Index sur `mesures (boitier_id, mesure_at)` : c'est la table qui grossira le plus.

### C2. L'authentification des boîtiers (semaine 7)

- **Piège à connaître** : un boîtier n'est pas un utilisateur. Le middleware `DefinirStructureCourante` ne le gère pas, et le filtre par structure empêcherait même de retrouver le boîtier à partir de son jeton.
- Solution la plus simple : un jeton aléatoire par boîtier, montré une seule fois à sa création, stocké en base sous forme d'empreinte SHA-256. Un middleware `AuthentifierBoitier` lit l'en-tête `Authorization: Bearer`, retrouve le boîtier avec `sansFiltre()`, puis définit la structure courante à partir du boîtier.
- C'est un écart avec la proposition technique, qui prévoyait Sanctum : notez-le dans la documentation.

### C3. L'API des mesures (semaine 7)

- Route `POST /api/v1/mesures` dans `routes/api.php` (fichier créé par `php artisan install:api`), protégée par `AuthentifierBoitier` et par une limite de débit.
- Une classe `FormRequest` pour les plages plausibles, par exemple : température de -20 à 60 °C, humidité de 0 à 100 %, luminosité de 0 à 200 000 lux.
- Réponses : 201 si enregistrée, 422 si invalide, 401 si jeton absent ou faux. Mettre à jour la date de dernière réception du boîtier.
- **Fini quand** : des tests couvrent ces trois réponses, et un boîtier ne peut pas écrire dans la serre d'un autre Jardin.

### C4. Le programme de l'ESP32 (semaine 7)

- Un projet PlatformIO dans le dossier `esp32/` du dépôt, avec les bibliothèques de vos capteurs.
- Un fichier `config.h`, exclu de Git, pour le Wi-Fi, l'adresse de l'API et le jeton.
- Boucle : se connecter au Wi-Fi, récupérer l'heure par NTP, lire les capteurs, envoyer le JSON, puis veille profonde pendant 5 minutes.
- **Attention** : l'ESP32 ne peut pas joindre `localhost`. En développement, utilisez l'adresse IP de votre PC sur le réseau local (`http://192.168.x.x:8080`) ; HTTPS ne viendra que sur le serveur de recette.

### C5. Le tableau de bord (semaine 7)

- Une page Livewire par serre : dernière valeur de chaque grandeur avec son heure, et une courbe sur 24 heures (Chart.js).
- Visible par tous les rôles du Jardin, sauf les adhérents (permission `voir-serres`).

### C6. Les seuils et les alertes (semaine 8)

- Une page de réglage des seuils, protégée par la permission `gerer-seuils`.
- Une commande `php artisan serre:surveiller`, planifiée chaque minute dans `routes/console.php` :
  - pour chaque seuil, regarder les 2 dernières mesures et ouvrir une alerte si les deux sont hors limites, sauf si une alerte du même type est déjà ouverte ;
  - ouvrir une alerte « capteur muet » si un boîtier n'a rien envoyé depuis 30 minutes ;
  - fermer les alertes quand tout revient à la normale.
- **Piège** : la commande tourne sans utilisateur, donc sans structure courante. Elle doit parcourir les Jardins avec `sansFiltre()`, sinon elle ne verra aucune donnée.
- Ajouter un service `scheduler` dans `docker-compose.yml` (même image que `app`, commande `php artisan schedule:work`).
- Envoyer chaque nouvelle alerte par e-mail (une `Notification` Laravel) aux comptes du Jardin qui ont la permission `gerer-seuils`. En local, vérifiez la réception dans Mailpit.
- **Fini quand** : les user stories US-C1 et US-C2 passent, en test et en démonstration avec le vrai boîtier.

### C7. Le guide de montage (semaine 8)

- Schéma de câblage (broches utilisées, alimentation 3,3 V), procédure de flashage, configuration du Wi-Fi et du jeton, avec des photos de votre prototype.

## Ce qui est coupé

Le devis prévoyait encore 249 h ; il en reste environ 135. Ces éléments sont retirés du plan et sont à annoncer au client comme un périmètre réduit :

| Élément retiré | Exigences | Remplacé par |
| --- | --- | --- |
| Toutes les exigences souhaitables | EF-S-04, EF-S-06, EF-S-07, EF-A-14 à EF-A-19, EF-C-06, EF-C-12 à EF-C-14, EF-D-09, EF-D-12, EF-D-13 | Rien |
| Écran de création des structures par l'administrateur réseau | EF-S-03 | Création par seeder |
| Courbes sur 7 et 30 jours | Partie d'EF-C-07 | Courbe sur 24 h seulement |
| Solveur Python de secours | Proposition technique | Heuristique PHP seule |
| Audit d'accessibilité, scan OWASP ZAP, mesures de performance hors composition | ENF-ACC, ENF-PERF-02 à 04 | Vérifications manuelles rapides |
| Documentation technique complète et séance de formation | Livrables L6, L9 | README détaillé et guide utilisateur court |

Si vous prenez de l'avance, réintégrez d'abord les courbes sur 7 et 30 jours, puis l'écran des structures, puis les exigences souhaitables.
