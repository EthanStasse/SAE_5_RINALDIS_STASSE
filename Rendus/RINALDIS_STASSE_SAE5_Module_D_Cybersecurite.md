# SAÉ 5 · Module D · Cybersécurité : authentification sécurisée des adhérents

**Projet** : logiciel de gestion des Jardins de Cocagne (Laravel 13, PHP 8.3, PostgreSQL 16, Fortify, Livewire, Docker)
**Binôme** : Damien RINALDIS (module D, module C) · Ethan STASSE (module A)
**Date** : novembre 2026

Ce dossier rassemble ce qui a été fait pour le module D : les menaces visées, les protections mises en place, les choix et leurs alternatives, les preuves (tests automatiques) et les limites connues. Il sert de support à la soutenance.

---

## 1. Périmètre du module D

Le module D sécurise **l'accès des personnes au logiciel de gestion**. Tous les utilisateurs passent par le même formulaire (EF-D-15) : adhérents comme personnel. Les modules Paniers (A) et Serre (C) sont derrière cette porte.

```
Connexion (module D)          ->  qui êtes-vous ?
        |
Rôles et Jardin (socle)       ->  qu'avez-vous le droit de voir ?
        |
   Paniers (A)        Serre (C)
```

Défense en profondeur : chaque couche a ses propres protections.

| Protection | Où | Hors module D ? |
|---|---|---|
| Connexion, mots de passe, blocage, 2FA, sessions, journal | Module D | non |
| Un Jardin ne voit pas les données d'un autre (`AppartientAStructure`) | Socle | oui |
| Droits par rôle (spatie/permission, mode « teams ») | Socle | oui |
| Authentification des boîtiers de la serre (jeton `AuthentifierBoitier`) | Module C | oui |

---

## 2. Synthèse : exigences, état et preuves

| Exigence | Contenu | État | PR | Preuve (tests Pest) |
|---|---|---|---|---|
| EF-D-01 | Connexion e-mail + mot de passe | ✅ | socle | `ConnexionTest` (11) |
| EF-D-02 | Compte créé par l'équipe, lien d'activation à usage unique | ✅ | activation | `ActivationCompteTest` (17) |
| EF-D-03 | Changer son mot de passe en saisissant l'ancien | ✅ | #11 | `EspaceAdherentTest` (8) |
| EF-D-04 | Mot de passe oublié, lien valable 60 min | ✅ | #8 | `MotDePasseOublieTest` (17) |
| EF-D-05 | Politique de mot de passe (12 à 64, 4 types, espaces, pas d'expiration) | ✅ | #5 | `PolitiqueMotDePasseTest` (11) |
| EF-D-06 | Blocage 15 min après 5 échecs + limitation par IP | ✅ | #6 | `BlocageConnexionTest` (11) |
| EF-D-07 | Messages qui ne révèlent pas si un compte existe | ✅ | #6, #8 | `BlocageConnexionTest`, `MotDePasseOublieTest`, `ActivationCompteTest` |
| EF-D-08 | 2FA TOTP obligatoire pour les administrateurs | ✅ | #10 | `DoubleAuthentificationTest` (20) |
| EF-D-10 | Déconnexion, expiration 30 min / 2 h / 12 h max | ✅ | #9 | `SessionsTest` (8) |
| EF-D-11 | Journal des connexions consultable par l'administrateur | ✅ | #13 | `JournalConnexionsTest` (15) |
| EF-D-12 *(souhaitable)* | E-mail quand le mot de passe change | ✅ | #12 | `AlerteChangementMotDePasseTest` (5) |
| EF-D-14 | Page adhérent minimale | ✅ | #11 | `EspaceAdherentTest` |
| EF-D-15 | Même formulaire et mêmes protections pour le personnel | ✅ | — | tests multi-rôles (`SessionsTest`, `DoubleAuthentificationTest`) |
| EF-D-09 *(souhaitable)* | 2FA proposée à tous | ⏳ prêt, non intégré | — | — |
| EF-D-13 *(souhaitable)* | Voir et fermer ses sessions | ❌ | — | — |
| ENF-SEC-01 | HTTPS, HSTS, CSP, X-Frame-Options, nosniff | ✅ | #7 | `EntetesHttpTest` (4) + vérification `curl -I` |
| ENF-SEC-03 | Hachage bcrypt, jamais de mot de passe en clair | ✅ | socle | voir § 6 |
| ENF-SEC-04 | Cookie HttpOnly, Secure, SameSite=Lax, identifiant régénéré | ✅ | #9 | `SessionsTest` |
| ENF-SEC-05 | CSRF, requêtes préparées, échappement XSS | ✅ | — | voir § 7 |
| ENF-SEC-06 | Jetons aléatoires, hachés, usage unique, durée limitée | ✅ | #8, activation | `MotDePasseOublieTest`, `ActivationCompteTest` |
| ENF-RGPD | Données minimales, journaux 12 mois | ✅ | #13 | `JournalConnexionsTest` + § 8 |

**User stories de bout en bout** : US-D1 (activation) → `ActivationCompteTest` « active le compte… (US-D1) » ; US-D2 (blocage) → `BlocageConnexionTest` ; US-D3 (mot de passe oublié) → `MotDePasseOublieTest` + parcours Mailpit.

**127 tests de sécurité**, sur une suite complète d'environ 290 tests, tous verts en intégration continue.

---

## 3. Fiche de soutenance : menace → protection → preuve

| Menace | Protection | Où | Test qui le prouve |
|---|---|---|---|
| **Force brute** sur un compte | Blocage 15 min au 5e échec, même le bon mot de passe ensuite refusé | `App\Support\BlocageComptes` | « bloque le compte au 5e échec, même avec le bon mot de passe ensuite » |
| **Attaque distribuée** (botnet, une IP par essai) | Compteur par adresse e-mail, pas par IP | `BlocageComptes` | « bloque même quand les essais viennent d'adresses IP différentes » |
| **Credential stuffing / password spraying** | 10 tentatives par minute et par IP, tous comptes confondus (429 + `Retry-After`) | limiteur `login`, `FortifyServiceProvider` | « limite une même adresse IP à 10 tentatives par minute… » |
| **Énumération de comptes** (connexion) | Même message d'échec et de blocage, adresse connue ou non | `authenticateUsing`, `lang/fr/auth.php` | « bloque aussi une adresse inconnue, avec le même message » |
| **Énumération** (mot de passe oublié, activation) | Une seule réponse pour lien envoyé / adresse inconnue / demande trop rapide ; `user` = `token` | `LienReinitialisationDemande`, `lang/fr/passwords.php` | « donne le même message pour une adresse inconnue, sans rien envoyer » |
| **Mot de passe faible** | 12 à 64 caractères, majuscule, minuscule, chiffre, caractère spécial | `Password::defaults()` | `PolitiqueMotDePasseTest` (6 cas de refus) |
| **Lien de réinitialisation volé** | 60 min, usage unique, jeton haché | broker `users` | « refuse un lien déjà utilisé », « refuse un lien de plus de 60 minutes » |
| **Phishing par le lien** (Host header poisoning) | Lien construit depuis `APP_URL`, jamais depuis la requête | `ResetPassword::toMailUsing`, notifications | « construit le lien depuis APP_URL, pas depuis l'en-tête Host » |
| **Mot de passe envoyé en clair** | Compte créé sans mot de passe connu ; la personne le choisit via un lien de 72 h | `CompteController`, `ActivationController` | « affiche le formulaire… » (aucun champ `password`), US-D1 |
| **Élévation de privilèges** | Un admin de Jardin ne peut pas créer d'admin réseau (vérifié côté serveur) | `CompteController::rolesAttribuables` | « n'autorise pas un administrateur de Jardin à créer un administrateur du réseau » |
| **Mot de passe d'administrateur volé** | 2FA TOTP obligatoire, sinon renvoi vers l'activation sur toute page | `ExigerDoubleAuthentification` | « renvoie un administrateur sans double authentification vers l'activation… » |
| **Force brute sur le code 2FA** | 5 essais par minute (10⁶ codes → plus de 6 mois en moyenne) | limiteur `two-factor` | « limite les essais de code à 5 par minute » |
| **Téléphone perdu** | Codes de secours à usage unique, affichés une seule fois | Fortify | « accepte un code de secours, une seule fois » |
| **Vol de cookie par JavaScript** | Cookie `HttpOnly` | `config/session.php` | « pose un cookie de session HttpOnly et SameSite=Lax » |
| **Fixation de session** | Identifiant régénéré à la connexion | Fortify | « change l'identifiant de session à la connexion » |
| **Session oubliée** (poste partagé) | 30 min (personnel), 2 h (adhérents), 12 h maximum | `ExpirationSession` | tests 29/31 min, 119/121 min, 11 h 59/12 h 01 |
| **Contournement des 12 h** | « Rester connecté » supprimé ; champ `remember` ignoré côté serveur | `authenticateUsing` | « ignore « Rester connecté » même si le champ est envoyé » |
| **Attaquant déjà connecté** quand on change son mot de passe | Les autres sessions sont fermées | `AuthenticateSession` | « ferme une session ouverte ailleurs quand le mot de passe change » |
| **Changement de mot de passe à notre insu** | E-mail au propriétaire du compte | `User::booted`, `MotDePasseModifie` | `AlerteChangementMotDePasseTest` |
| **Prise de compte par changement d'e-mail** | Coordonnées en lecture seule pour l'adhérent | `compte/mon-compte.blade.php` | — (choix de conception) |
| **XSS** | Échappement `{{ }}` partout ; CSP `script-src 'self'` sans `'unsafe-inline'` | vues, `EntetesSecurite` | `EntetesHttpTest` |
| **Clickjacking** | `frame-ancestors 'none'` + `X-Frame-Options: DENY` | `EntetesSecurite` | « protège la page de connexion » |
| **Interception (Wi-Fi public)** | HSTS 1 an (en HTTPS), cookie `Secure` en production | `EntetesSecurite`, `config/session.php` | « impose HTTPS pendant un an… » |
| **CSRF** | Jeton `@csrf` + `SameSite=Lax` | middleware de Laravel | tous les formulaires `POST` |
| **Injection SQL** | Eloquent / Query Builder (requêtes préparées) | tout le code | § 7 |
| **Fuite de la base** | Mots de passe bcrypt ; jetons de lien hachés ; secret 2FA chiffré (`APP_KEY`) ; empreintes SHA-256 dans les clés de blocage | — | « stocke le jeton haché, jamais en clair », « le secret étant stocké chiffré » |
| **Attaque passée inaperçue** | Journal des connexions réussies, échouées et des blocages | `JournalConnexion` | `JournalConnexionsTest` |

---

## 4. Détail par exigence : principe, choix, alternatives

### EF-D-05 · Politique de mot de passe
- **Règle** : 12 à 64 caractères, au moins une majuscule, une minuscule, un chiffre, un caractère spécial ; espaces acceptés (l'espace compte comme caractère spécial) ; **pas d'expiration forcée**.
- **Référence** : CNIL, délibération 2022-100 (environ 80 bits d'entropie ; exemple : 12 caractères de 4 types).
- **Choix** : définie **une seule fois** (`Password::defaults()` dans `AppServiceProvider`), et appliquée automatiquement à la création, la réinitialisation, le changement et l'activation (trait `PasswordValidationRules`). Elle ne s'applique **jamais à la connexion**, pour ne pas bloquer les comptes existants.
- **Alternative** : le NIST (SP 800-63B) déconseille les règles de composition et préfère une liste noire de mots de passe compromis (`->uncompromised()`). Le CDC suit la CNIL ; la vérification de fuite est une amélioration possible.
- **Messages en français** : `lang/fr/validation.php`.

### EF-D-06 · Blocage et limitation par IP
| | Compteur par compte | Limitation par IP |
|---|---|---|
| Menace | force brute, attaque distribuée | credential stuffing, password spraying |
| Règle | 5 échecs → 15 min (à partir du 5e échec) | 10 tentatives / minute / IP |
| Clé | empreinte SHA-256 de l'adresse saisie | `connexion-ip:<ip>` |
| Réponse | message de blocage générique | page 429 en français + `Retry-After` |

- **Choix** : compteur dans le cache Laravel (stocké en base, `CACHE_STORE=database`), et non dans des colonnes de `users`. Des colonnes ne bloqueraient jamais une adresse **inconnue** : le message de blocage révélerait alors qu'un compte existe (énumération).
- **Blocage temporaire** (et non définitif) pour limiter le déni de service volontaire contre un adhérent (OWASP).
- **10 / min par IP** : marge pour un Jardin dont tous les postes sortent par la même IP (NAT).

### EF-D-07 · Messages génériques
- Connexion : un seul message (« Ces identifiants ne correspondent à aucun compte actif. ») pour adresse inconnue, mauvais mot de passe, compte désactivé.
- Blocage : même message qu'il y ait un compte ou non.
- Mot de passe oublié : `LienReinitialisationDemande` remplace les **deux** réponses de Fortify (succès et échec). Messages `sent` = `throttled` et `token` = `user` dans `lang/fr/passwords.php`.
- Activation : un seul message pour jeton faux, expiré, déjà utilisé ou adresse sans compte.
- 2FA : même message pour un code faux et un code de secours déjà utilisé (`lang/fr.json`).

### EF-D-04 · Mot de passe oublié
- Fonctionnalité `resetPasswords` de Fortify ; broker `users` : **60 min**, jeton aléatoire de 64 caractères **stocké haché** (`password_reset_tokens`), supprimé après usage.
- **Anti-phishing** : lien construit depuis `APP_URL` (`ResetPassword::toMailUsing`). Sans cela, un attaquant qui envoie `Host: pirate.test` ferait recevoir à la victime un **vrai** e-mail pointant vers son site. Le test « construit le lien depuis APP_URL » échouait avant la correction (preuve de la faille).
- La politique EF-D-05 est vérifiée **avant** de consommer le lien : une saisie refusée ne fait pas perdre le lien.
- Valeurs limites testées : 59 min (accepté) / 61 min (refusé).

### EF-D-02 · Activation des comptes
- L'administrateur (permission `gerer-comptes`) saisit nom, e-mail et rôle : **aucun champ de mot de passe**. Le compte reçoit un mot de passe aléatoire de 64 caractères que personne ne connaît.
- Lien d'activation par e-mail : **broker séparé `activations`, 72 h** (un adhérent n'ouvre pas forcément ses e-mails dans l'heure), même mécanisme que la réinitialisation (jeton haché, usage unique).
- Activer le compte **vaut vérification de l'adresse** (`email_verified_at`).
- **Moindre privilège** : seul un administrateur du réseau peut créer un administrateur du réseau ; la liste du formulaire et la validation serveur utilisent la même fonction.
- Création du compte et attribution du rôle dans une **transaction**.
- Écran **minimal**, réutilisable par le module « Socle : comptes ».

### EF-D-08 · Double authentification des administrateurs
- **TOTP** (application du téléphone, code à 6 chiffres renouvelé toutes les 30 s) : « ce que je sais + ce que je possède ».
- **Obligatoire** pour `admin-reseau` et `admin-structure` (protection proportionnée au risque) : le middleware `ExigerDoubleAuthentification` renvoie vers `/securite/double-authentification` depuis **toute** page, sauf l'activation, la confirmation du mot de passe, les routes 2FA de Fortify et la déconnexion.
- **Activation en 3 temps** : mot de passe redemandé (`confirmPassword`), QR code ou clé, **premier code obligatoire** (`confirm`) pour ne pas bloquer un compte dont le QR code aurait été mal scanné.
- **Codes de secours** affichés **une seule fois** ; régénérables.
- Secret et codes **chiffrés** avec `APP_KEY` et exclus des sérialisations (`$hidden`).
- Le QR code est le seul `{!! !!}` du projet : SVG produit par le serveur, sans aucune saisie d'utilisateur.
- Les rôles sont lus **dans tous les Jardins** (`User::nomsDeRoles()`), car `hasRole()` ne voit que le Jardin courant en mode « teams ».

### EF-D-10 · Sessions (avec ENF-SEC-04)
- Middleware `ExpirationSession` : **30 min** d'inactivité pour le personnel, **2 h** pour les adhérents, **12 h** au maximum depuis la connexion. Un compte à la fois adhérent et bénévole reçoit la règle la plus stricte.
- « Rester connecté » supprimé de l'écran **et** champ `remember` ignoré côté serveur (ne jamais faire confiance au client).
- Cookie `HttpOnly`, `SameSite=Lax`, `Secure` par défaut en production (`SESSION_SECURE_COOKIE`), identifiant régénéré à la connexion (fixation de session).
- `SESSION_LIFETIME` ≥ 120 documenté dans `.env.example`.

### EF-D-03 / EF-D-14 · Mon compte
- Changement de mot de passe : ancien mot de passe exigé, politique EF-D-05, confirmation.
- `AuthenticateSession` : changer son mot de passe **ferme les autres sessions** (si on le change parce qu'on le croit volé, l'attaquant est expulsé). La session courante reste ouverte.
- Coordonnées en **lecture seule** : l'adresse e-mail est l'identifiant **et** le canal de récupération ; la laisser modifier par une session volée permettrait une prise de compte définitive.

### EF-D-11 · Journal des connexions
- Événements : `connexion_reussie` (après le code 2FA pour un administrateur), `connexion_echouee`, `compte_bloque`.
- Captés par les **événements** `Login` et `Failed` de Laravel (et par `BlocageComptes` pour le blocage), sans modifier le code de connexion.
- Table `journal_connexions` **sans `updated_at`** : une ligne ne se modifie pas.
- **Pas de `structure_id`** (exception au socle, justifiée) : un compte peut appartenir à plusieurs Jardins, et un échec sur une adresse inconnue n'appartient à aucun. Le cloisonnement se fait à la consultation :
  - administrateur d'un Jardin : les comptes de son Jardin seulement ;
  - administrateur du réseau : tout, y compris les adresses inconnues.
- Consultation : `/securite/journal-connexions`, permission `voir-audit`.

### EF-D-12 · Alerte de changement de mot de passe
- E-mail envoyé à **chaque** changement (Mon compte, mot de passe oublié…), accroché au modèle `User` (`wasChanged('password')`) pour qu'aucun chemin ne puisse l'oublier.
- Pas d'e-mail à la connexion (qui met pourtant à jour `derniere_connexion_at`) ni à l'activation (premier choix, pas un changement).
- L'e-mail ne contient jamais le mot de passe.

### ENF-SEC-01 · En-têtes HTTP
Middleware global `EntetesSecurite` (testé, indépendant du serveur web) :

| En-tête | Valeur |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'` |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` (le jeton des liens ne fuit pas par le `Referer`) |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (seulement en HTTPS) |

- **Compromis CSP justifiés** : `'unsafe-eval'` pour Alpine.js (fourni avec Livewire) ; `'unsafe-inline'` pour les styles de Livewire. Pas de `'unsafe-inline'` pour les scripts : un `<script>` injecté reste bloqué. En développement seulement, le serveur Vite est autorisé (fichier `public/hot`).
- nginx : doublons supprimés et `server_tokens off` (version masquée).

---

## 5. Ordre des middlewares (groupe web)

```
EntetesSecurite              (global, toutes les réponses)
AuthenticateSession          EF-D-03 : session fermée si le mot de passe a changé
ExpirationSession            EF-D-10 : 30 min / 2 h / 12 h
DefinirStructureCourante     socle   : Jardin courant
ExigerDoubleAuthentification EF-D-08 : 2FA obligatoire des administrateurs
```

---

## 6. ENF-SEC-03 · Hachage des mots de passe

- **bcrypt**, coût 12 (`BCRYPT_ROUNDS=12`), via le cast `'password' => 'hashed'` du modèle `User` : aucun mot de passe n'est jamais stocké ni journalisé en clair.
- Les jetons de lien (réinitialisation, activation) sont aussi hachés ; le secret 2FA est **chiffré** (il doit pouvoir être relu pour calculer les codes).
- **Limite connue** : bcrypt ignore ce qui dépasse **72 octets**. Avec des caractères accentués (2 octets), un mot de passe de 64 caractères peut dépasser cette limite. **Amélioration recommandée** : passer à **Argon2id** (première intention du CDC), après vérification que l'image PHP le supporte (`defined('PASSWORD_ARGON2ID')`).

---

## 7. ENF-SEC-05 · CSRF, requêtes préparées, XSS

| Risque | Mesure | Vérification |
|---|---|---|
| **CSRF** | `@csrf` dans tous les formulaires `POST`/`PUT`/`DELETE` ; middleware de vérification de Laravel ; cookie `SameSite=Lax` | toutes les vues `auth/`, `compte/`, `comptes/`, `securite/` |
| **Injection SQL** | Eloquent et Query Builder : valeurs toujours passées en paramètres (requêtes préparées) | seul `whereRaw('1 = 0')` (constante, sans donnée) dans `StructureScope` |
| **XSS** | Échappement automatique `{{ }}` ; aucune saisie affichée avec `{!! !!}` ; CSP en deuxième barrière | seul `{!! !!}` : le QR code SVG généré par le serveur |

---

## 8. RGPD : données traitées par le module D

| Donnée | Où | Finalité | Durée | Minimisation |
|---|---|---|---|---|
| Nom, e-mail | `users` | identifier le compte, récupération | durée du compte | e-mail non modifiable par l'adhérent |
| Mot de passe | `users` (bcrypt) | authentification | durée du compte | jamais en clair, jamais envoyé |
| Secret 2FA, codes de secours | `users` (chiffrés) | double authentification | jusqu'à désactivation | exclus des sérialisations |
| Dernière connexion | `users` | suivi des comptes inactifs | durée du compte | — |
| Jetons de lien | `password_reset_tokens` (hachés) | réinitialisation, activation | 60 min / 72 h, supprimés après usage | — |
| Compteurs de blocage | cache (empreinte SHA-256 de l'adresse) | EF-D-06 | 15 min | adresse jamais stockée en clair |
| Journal des connexions | `journal_connexions` | sécurité, enquête | **12 mois**, purge automatique chaque nuit à 3 h (`model:prune`) | ni adresse saisie, ni mot de passe, ni navigateur ; compte supprimé → ligne anonymisée (`nullOnDelete`) |
| Session | `sessions` | rester connecté | 30 min à 12 h | — |

**Base légale** : exécution du service et intérêt légitime (sécurité du système d'information). **Durée des journaux** : 12 mois, dans la fourchette recommandée par la CNIL pour les journaux de sécurité.

---

## 9. Limites connues et améliorations possibles

| Limite | Impact | Amélioration |
|---|---|---|
| bcrypt tronque à 72 octets | mots de passe longs et accentués | Argon2id |
| E-mail de réinitialisation envoyé pendant la requête | une adresse existante répond un peu plus lentement (énumération par le temps) | envoi par la file d'attente |
| Pages à rafraîchissement automatique (`wire:poll`) | comptent comme une activité (limite des 12 h toujours appliquée) | ne pas compter les rafraîchissements |
| CSP avec `'unsafe-eval'` / `'unsafe-inline'` (styles) | protection XSS un peu moins stricte | build CSP d'Alpine, nonces |
| Pas de limitation par IP sur « mot de passe oublié » | envoi d'e-mails en masse vers des adresses différentes | limiteur dédié |
| Refus par la limite IP (429) et codes 2FA faux non journalisés | journal incomplet sur ces cas | événements supplémentaires |
| Pas de vérification des mots de passe compromis | mot de passe conforme mais déjà fuité | `->uncompromised()` (k-anonymat, Have I Been Pwned) |
| Une demande « mot de passe oublié » remplace un lien d'activation en cours | sans danger (même résultat) | table de jetons séparée |
| EF-D-09 et EF-D-13 non intégrées | souhaitables | branche EF-D-09 prête |
| Boîtiers de la serre : jeton maison au lieu de Sanctum (proposition technique) | écart à documenter (module C) | — |

---

## 10. Méthode de travail

- **Tests d'abord** : pour chaque protection, un commit `test(...)` qui échoue (preuve de la faille), puis un commit `feat(...)` qui le fait passer. Valeurs limites systématiques (29/31 min, 59/61 min, 71/73 h, 12 mois ± 1 jour…).
- **GitHub Flow** : une branche par exigence, une PR relue par le binôme, `main` protégée (approbation + CI obligatoires), fusion par merge commit pour garder chaque commit avec son auteur et sa date.
- **Convention de commits** : `type(portée): description (exigence)`.
- **Intégration continue** : Pint, Larastan, Pest sur PostgreSQL 16, `composer audit`.
- **PR du module D** : #5 politique · #6 blocage · #7 en-têtes · #8 mot de passe oublié · #9 sessions · #10 2FA · #11 Mon compte · #12 alerte e-mail · #13 journal · activation.

---

## 11. Démonstrations pour la soutenance

1. Test rouge puis vert d'une faille (ex. `BlocageConnexionTest` avant/après).
2. Connexion avec 5 échecs → message de blocage ; même message sur une adresse inconnue.
3. « Mot de passe oublié » : même message pour une adresse connue et inconnue ; e-mail dans Mailpit ; lien réutilisé refusé.
4. Création d'un compte par l'administrateur → e-mail d'activation → choix du mot de passe → connexion (US-D1).
5. 2FA : activation avec un vrai téléphone (QR code, codes de secours), puis connexion avec le code.
6. Mon compte : changement de mot de passe dans un navigateur → l'autre navigateur est déconnecté.
7. Journal des connexions vu par un administrateur de Jardin puis du réseau.
8. `curl -I http://localhost:8080/login` : en-têtes de sécurité, un seul `X-Frame-Options`, version de nginx masquée.

### Commandes utiles
```powershell
docker compose exec app ./vendor/bin/pest tests/Feature/Auth tests/Feature/Securite
docker compose exec app php artisan schedule:list
docker compose exec app php artisan config:show session
curl.exe -sI http://localhost:8080/login
```
