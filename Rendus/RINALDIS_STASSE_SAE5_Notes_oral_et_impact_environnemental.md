# SAÉ 5 – Notes : oral du devis et impact environnemental

RINALDIS Damien – STASSE Ethan – 2026-2027

---

## 1. Oral d'avancement du devis (environ 5 minutes)

**1. Contexte (30 s)**
Nous sommes développeurs juniors dans une ENS d'environ 20 salariés. Le client est le Réseau Cocagne. Notre périmètre : le socle (base multi-structures), la composition des paniers, la surveillance de serre et l'authentification sécurisée. Tout est exclu du reste (formulaire de commande, recettes, calendrier, carte, SEPA).

**2. Estimation des heures (1 min)**
- 12 tâches, estimées à 3 points : (O + 4P + Pe) ÷ 6.
- Total : **361 h**, pour un volume de 360 h (2 × 180 h).
- Répartition : études et conception 112 h, développement 166 h, qualité et mise en service 83 h.

**3. Méthode du coût complet (2 min)**
- Coût direct : MOD = 33 000 € × 1,42 ÷ 1 446 h = **32,40 €/h**.
- Coûts indirects : 252 000 €/an regroupés en 5 centres d'analyse. Deux auxiliaires (administration et direction ; locaux et services généraux), trois principaux (études, développement, qualité).
- Répartition primaire par clés en %, puis secondaire : administration selon les effectifs, locaux selon la surface.
- Unité d'œuvre : l'heure de MOD. Coût de l'unité d'œuvre : **10,50 / 10,90 / 11,38 €/h**.
- Coût complet horaire : 42,90 / 43,30 / 43,78 €/h.

**4. Résultat (1 min)**
- Coût de revient : **15 891,17 €** (MOD 11 696,37 + indirects 3 929,80 + directs spécifiques 265).
- Marge 20 %, invisible pour le client : intégrée aux taux horaires (51,48 / 51,96 / 52,53 €/h) et aux lignes de matériel.
- **19 069,11 € HT, soit 22 882,93 € TTC.**

**5. Sources et limites (30 s)**
- Salaire et charges sourcés (jobrise.io, salairebrut-en-net.fr, moicombien.fr).
- Les charges indirectes et les clés de répartition sont des **hypothèses de jeu de rôle**.
- Reste à faire : ajuster après le Rendu 3, suivi des temps réels, mesure d'impact écologique.

**Questions probables et réponses**
- *Pourquoi 33 000 € ?* Bas de fourchette junior en province : 30-34 k€ pour une petite ville, 32-45 k€ au niveau national.
- *Pourquoi l'heure de MOD comme unité d'œuvre ?* Notre activité est du temps de développement ; le coût des centres varie avec les heures.
- *Où est la marge ?* Dans le taux horaire facturé et les forfaits ; elle n'apparaît pas dans le PDF client. La partie interne la montre (20 %).
- *Pourquoi ne pas utiliser le taux apprenti ?* Le sujet demande de se placer en développeurs juniors d'une ENS.

---

## 2. Partie III – Impact environnemental (bonus, rendu le 22 janvier 2027)

Objectif du sujet : chercher **et mettre en œuvre** un outil qui mesure l'impact écologique du développement.

> Les outils ci-dessous viennent de mes connaissances, pas d'une vérification en ligne. Avant de choisir, lisez la documentation à jour de chaque outil.

### Pistes d'outils

| Outil | Ce qu'il mesure | Adapté à |
|---|---|---|
| **CodeCarbon** | Énergie et CO₂e d'un code Python | Moteur d'optimisation des paniers (si en Python) |
| **Scaphandre** | Consommation électrique de processus sur une machine | Serveur de test, sous Linux |
| **GreenFrame / Green Metrics Tool** | Consommation d'une application en conteneurs | Application complète dockerisée |
| **EcoIndex / GreenIT-Analysis, Lighthouse** | Poids et impact d'une page web | Formulaire d'authentification |

### Protocole proposé (avant / après)

1. **Choisir un scénario** reproductible, par exemple : composer 200 paniers avec l'algorithme naïf, puis avec l'algorithme optimisé.
2. **Mesurer avant optimisation** : au moins 5 exécutions, noter durée et énergie.
3. **Optimiser** (structures de données, moins de requêtes, cache, pages plus légères).
4. **Mesurer après** avec les mêmes conditions (même machine, même jeu de données).
5. **Convertir** l'énergie en CO₂e avec le facteur d'émission de l'électricité en France (à vérifier sur la Base Empreinte de l'ADEME). La feuille *Mesures_environnement* du fichier de suivi le fait.
6. **Documenter** : outil, version, machine, limites (la mesure d'un poste de travail est approximative).

### Idées de rendu

- Un tableau avant / après et un graphique.
- Une page sur les limites de la mesure (un résultat partiel et honnête peut suffire pour le bonus).
- Un lien avec l'écoconception : pages légères, requêtes limitées, données de capteurs de serre envoyées à fréquence raisonnable (surveillance de serre).

---

## 3. Rappels de pénalités (chaque rendu)

- Première page : noms, année universitaire, titre du rendu, logo de l'Université de Lorraine.
- Format PDF si demandé ; noms du binôme dans le nom du fichier.
- Retard : −1 pt par tranche de 6 h entamée.
