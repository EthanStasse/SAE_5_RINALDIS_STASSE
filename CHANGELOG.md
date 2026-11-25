# Journal des versions

Toutes les évolutions notables du logiciel Cocagne sont décrites ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et les numéros de version suivent la [gestion sémantique de version](https://semver.org/lang/fr/) : `MAJEUR.MINEUR.CORRECTIF`. Tant que la version commence par 0, le logiciel est en cours de développement.

## [Non publié]

## [0.1.0] - 2026-11-25

Première version livrée : socle technique, cybersécurité (module D), surveillance de serre (module C, sans essai sur le matériel réel) et chaîne de production complète.

### Ajouté

**Socle**
- Plusieurs Jardins dans une seule base, données isolées par Jardin (`structure_id`).
- Rôles par Jardin : administrateur réseau, administrateur de structure, équipe, insertion, bénévole, adhérent.

**Cybersécurité et authentification (module D)**
- Connexion par e-mail et mot de passe (EF-D-01).
- Activation du compte par un lien à usage unique valable 72 heures ; la personne choisit elle-même son mot de passe (EF-D-02).
- Page « Mon compte » et changement de mot de passe avec l'ancien ; les autres sessions sont fermées (EF-D-03, EF-D-14).
- Mot de passe oublié avec un message identique que le compte existe ou non (EF-D-04, EF-D-07).
- Mots de passe de 12 à 64 caractères avec 4 types de caractères (EF-D-05).
- Blocage du compte 15 minutes après 5 échecs et limitation des tentatives par adresse IP (EF-D-06, EF-D-07).
- Double authentification obligatoire pour les administrateurs, avec QR code et codes de secours (EF-D-08).
- Expiration des sessions selon le rôle : 30 min, 2 h, 12 h au maximum (EF-D-10).
- Journal des connexions consultable par Jardin, purgé après 12 mois (EF-D-11, RGPD).
- E-mail d'alerte au propriétaire quand son mot de passe change (EF-D-12).
- En-têtes HTTP de sécurité : CSP, anti-clickjacking, nosniff, HSTS (ENF-SEC-01).
- Dossier de sécurité et RGPD du module D.

**Surveillance de serre (module C)**
- Boîtiers authentifiés par jeton, réception des mesures par l'API.
- Seuils par serre, alertes de seuil et de capteur muet, envoi par e-mail, surveillance planifiée.
- Programmes Arduino, ESP32 et passerelle Raspberry Pi, guides de montage.

**Dépôt et chaîne de production**
- README, règles de contribution (GitHub Flow, relecture croisée, fusion par merge commit), branche `main` protégée.
- Intégration continue : style (Pint), analyse statique (Larastan), tests Pest sur PostgreSQL, audit des dépendances, tests de la passerelle (pytest), compilation du front (Vite) ; cache composer et npm.
- Image Docker de production multi-stage (utilisateur non root, opcache, healthcheck) et image nginx.
- Compose de production : HTTPS par Caddy, base de données non exposée, secrets hors du dépôt.
- Livraison continue : images publiées sur ghcr.io à chaque fusion dans `main` et à chaque version.

### Sécurité
- Mise à jour de `web-auth/webauthn-lib` en 5.3.17 (faille publiée le 17/11/2026, détectée par `composer audit` dans la CI).
- La version de PHP n'est plus annoncée dans les réponses HTTP.

### Limites connues
- Module A (composition des paniers) : pas encore commencé.
- Module C : pas encore testé sur le matériel réel.
- Pas encore de déploiement automatique sur un serveur de recette.

[Non publié]: https://github.com/EthanStasse/SAE_5_RINALDIS_STASSE/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/EthanStasse/SAE_5_RINALDIS_STASSE/releases/tag/v0.1.0
