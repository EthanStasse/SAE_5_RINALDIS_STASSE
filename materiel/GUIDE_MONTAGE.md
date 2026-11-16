# Guide de montage et de mise en service — boîtier capteur de serre (ESP32)

Exigence couverte : **EF-C-15** (câblage, flashage, configuration).
Durée : environ 1 h 30 la première fois.

```
Capteurs Grove ──fils──> ESP32 ──Wi-Fi + HTTPS + jeton──> API Laravel (PC, puis serveur de recette)
```

C'est l'architecture de la proposition technique : l'ESP32 mesure et envoie lui-même chaque mesure à `POST /api/v1/mesures`. Une solution de secours sans ESP32 (Arduino + Raspberry Pi) est décrite dans [`GUIDE_MONTAGE_ARDUINO_PI.md`](GUIDE_MONTAGE_ARDUINO_PI.md).

1. [Matériel](#1-matériel)
2. [Câbler](#2-câbler)
3. [Préparer l'IDE Arduino](#3-préparer-lide-arduino)
4. [Préparer l'API sur le PC et créer le jeton](#4-préparer-lapi-sur-le-pc-et-créer-le-jeton)
5. [Configurer et téléverser](#5-configurer-et-téléverser)
6. [Vérifier](#6-vérifier)
7. [Tests de robustesse](#7-tests-de-robustesse)
8. [Passer en HTTPS (recette)](#8-passer-en-https-recette)
9. [Dépannage](#9-dépannage)

---

## 1. Matériel

| Élément | Rôle |
|---|---|
| ESP32 DevKit / DevKitC (WROOM-32) + câble USB **de données** | Mesure et envoi en Wi-Fi |
| Grove - Temperature & Humidity Sensor **Pro** (blanc, DHT22) | Température + humidité de l'air |
| Grove - Digital Light Sensor (TSL2561) | Luminosité en lux |
| Grove - LED, Grove - Buzzer | Signal d'alerte local (facultatif) |
| 4 câbles **Grove vers fils mâles** + une breadboard | Raccordement (l'ESP32 n'a qu'une broche 3V3) |

Couverture d'EF-C-03 : température, humidité de l'air et luminosité.

---

## 2. Câbler

**ESP32 débranché pendant le câblage.** L'ESP32 fonctionne en **3,3 V** : tous les modules sont alimentés par la broche **3V3**, jamais par 5V.

1. Enficher l'ESP32 à cheval sur la rainure centrale de la breadboard.
2. Relier la broche **3V3** de l'ESP32 à la ligne rouge (+) de la breadboard, et une broche **GND** à la ligne bleue (−).
3. Brancher chaque câble Grove (code couleur : **jaune** = signal, **blanc** = 2ᵉ signal, **rouge** = alimentation, **noir** = masse) :

| Module | Jaune | Blanc | Rouge | Noir |
|---|---|---|---|---|
| Temperature & Humidity Sensor **Pro** | **GPIO 4** | — | ligne 3V3 | ligne GND |
| Digital Light Sensor (I2C) | **GPIO 22** (SCL) | **GPIO 21** (SDA) | ligne 3V3 | ligne GND |
| LED | **GPIO 25** | — | ligne 3V3 | ligne GND |
| Buzzer | **GPIO 26** | — | ligne 3V3 | ligne GND |

Les numéros GPIO sont imprimés à côté des broches (parfois sous la forme « D4 », « D21 », « D22 », « D25 », « D26 », ou « IO4 »…).

**Capteur blanc marqué V2.0 (DHT20)** : il est en I2C. Le brancher comme le Digital Light Sensor (jaune sur GPIO 22, blanc sur GPIO 21 : deux modules I2C peuvent partager ces broches via la breadboard) et mettre `TEMP_DHT20` dans le programme (étape 5.2).

Broches évitées volontairement : GPIO 0, 2, 5, 12, 15 (elles influencent le démarrage) et GPIO 34 à 39 (entrée seule).

---

## 3. Préparer l'IDE Arduino

1. Installer **Arduino IDE 2** (arduino.cc/en/software).
2. *Fichier → Préférences → URL de gestionnaire de cartes supplémentaires*, ajouter :
   ```
   https://espressif.github.io/arduino-esp32/package_esp32_index.json
   ```
3. *Outils → Carte → Gestionnaire de cartes* : chercher **esp32** et installer **esp32 by Espressif Systems**.
4. *Outils → Gérer les bibliothèques*, installer :
   - **DHT sensor library** (Adafruit) → accepter **Adafruit Unified Sensor** ;
   - **Adafruit TSL2561** ;
   - **DHT20** (Rob Tillaart), seulement pour un capteur V2.0.
5. Brancher l'ESP32. Si aucun port COM n'apparaît, installer le pilote de la puce USB de la carte (inscrite près du port USB) : **CP2102** (pilote « CP210x » de Silicon Labs) ou **CH340** (pilote WCH). Vérifier aussi que le câble USB transmet les données (certains câbles ne font que charger).

---

## 4. Préparer l'API sur le PC et créer le jeton

Pour les premiers tests, l'ESP32 envoie à l'application Laravel qui tourne sur le PC.

1. Démarrer l'application : dans `Code\`, `docker compose up -d`.
2. Adresse IP du PC sur le Wi-Fi : `ipconfig` → carte « Wi-Fi » → **Adresse IPv4** (ex. `192.168.1.42`).
3. Ouvrir le port 8080 dans le pare-feu Windows (PowerShell **en administrateur**, une seule fois) :
   ```powershell
   New-NetFirewallRule -DisplayName "Cocagne API 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
   ```
   Le réseau Wi-Fi du PC doit être en profil **Privé**.
4. Créer le boîtier et son jeton : sur http://localhost:8080, se connecter avec `admin.thaon@cocagne.test` / `password`, menu **Serres**, lien **Boîtiers** de la « Serre nord », puis **Déclarer le boîtier**.
   **Copier le jeton affiché** (48 caractères) : il n'est stocké nulle part en clair et ne sera plus jamais affiché. En cas d'oubli, bouton **Nouveau jeton**.

   Sans l'écran (dépannage), avec Tinker : `docker compose exec app php artisan tinker`, puis
   ```php
   app(App\Support\StructureCourante::class)->sansFiltre(fn () => App\Models\Boitier::creerPour(App\Models\Serre::where('nom', 'Serre nord')->first(), 'Boîtier ESP32')[1]);
   ```

**Réseau Wi-Fi** : l'ESP32 ne fonctionne qu'en **2,4 GHz** et ne sait pas se connecter à un Wi-Fi d'entreprise (eduroam, identifiant + mot de passe). Le plus simple : le **partage de connexion d'un téléphone** (en 2,4 GHz ou « compatibilité maximale »), avec le PC connecté au même partage.

---

## 5. Configurer et téléverser

### 5.1 Le fichier de secrets

1. Ouvrir `materiel/esp32/capteur_serre_esp32/capteur_serre_esp32.ino` dans l'IDE.
2. Dans le même dossier, **copier `secrets.exemple.h` sous le nom `secrets.h`** (il apparaît alors comme un onglet dans l'IDE).
3. Remplir `secrets.h` :
   ```cpp
   #define WIFI_SSID         "MonPartage"
   #define WIFI_MOT_DE_PASSE "motdepasse"
   #define API_URL "http://192.168.1.42:8080/api/v1/mesures"   // IP du PC (étape 4)
   #define JETON_BOITIER "le-jeton-de-48-caracteres"
   #define AUTORISER_HTTP_POUR_TESTS                           // décommenter pour le test sur le PC
   static const char API_CERTIFICAT_CA[] = "";                // vide en HTTP
   #define INTERVALLE_MESURE_S 10                              // 10 s pour les tests, 300 en vrai
   ```

`secrets.h` est ignoré par Git (fichier `.gitignore` du dossier) : le mot de passe Wi-Fi et le jeton ne sont jamais publiés.

### 5.2 Le capteur de température

En haut de `capteur_serre_esp32.ino` :
```cpp
#define CAPTEUR_TEMPERATURE TEMP_DHT22   // capteur « Pro » ; TEMP_DHT20 pour un capteur « V2.0 »
```

### 5.3 Téléverser

1. *Outils → Carte → esp32* : **ESP32 Dev Module**. *Outils → Port* : le port COM de l'ESP32.
2. Cliquer sur **Téléverser**.
3. Si l'IDE reste bloqué sur `Connecting....___....` : maintenir le bouton **BOOT** de l'ESP32 jusqu'à ce que le pourcentage d'écriture apparaisse.

---

## 6. Vérifier

### 6.1 Moniteur série

*Outils → Moniteur série*, réglé sur **115200 bauds** et **Nouvelle ligne**. Appuyer sur le bouton **EN** (ou RST) de l'ESP32 pour le redémarrer. Attendu :

```
=== Boîtier capteur de serre (ESP32) ===
Capteur de lumière : OK
ATTENTION : envoi en HTTP non chiffré (mode test)
API : http://192.168.1.42:8080/api/v1/mesures — mesure toutes les 10 s
Wi-Fi : connexion à « MonPartage »...
Wi-Fi : connecté, adresse 192.168.43.120
Mesure : T=21.4 °C  H=48.7 %  L=312.5 lx
Envoi : mesure du 2026-11-16T20:45:12Z acceptée (HTTP 201), 0 en attente
```

| Test | Action | Résultat attendu |
|---|---|---|
| État | Taper `ETAT` | Wi-Fi connecté, heure UTC, mesures en attente, intervalle |
| Humidité | Souffler sur le capteur blanc | `H=` monte nettement |
| Température | Pincer le capteur blanc 30 s | `T=` monte de quelques degrés |
| Luminosité | Couvrir le Digital Light Sensor, puis l'éclairer avec un téléphone | `L=` proche de 0, puis plusieurs centaines ou milliers |
| Alerte locale | Taper `ALERTE 1`, puis `ALERTE 0` | LED qui clignote + bip toutes les 10 s, puis arrêt |
| Mesure immédiate | Taper `MESURE` | Une mesure tout de suite |
| Capteur débranché | Débrancher le fil jaune du capteur blanc | `T=nan  H=nan` ; l'API reçoit `null`, la luminosité continue |

### 6.2 Dans Laravel (sur le PC)

```powershell
docker compose exec app php artisan tinker
```
```php
app(App\Support\StructureCourante::class)->sansFiltre(fn () => App\Models\Mesure::latest('id')->take(5)->get(['mesure_le', 'temperature', 'humidite', 'luminosite'])->toArray());
```

Les mesures apparaissent, avec `mesure_le` en UTC (1 h de moins que l'heure de Paris en hiver).

Une fois validé : remettre `INTERVALLE_MESURE_S 300` dans `secrets.h` et téléverser à nouveau.

---

## 7. Tests de robustesse

| Scénario | Action | Comportement attendu |
|---|---|---|
| Coupure de l'API | Sur le PC : `docker compose stop web` | `API injoignable ... N en attente`, l'attente double à chaque essai (5, 10, 20 s…) |
| Retour de l'API | `docker compose start web` | Toutes les mesures en attente partent, dans l'ordre ; aucune perdue |
| Coupure du Wi-Fi | Couper le partage de connexion 1 min, puis le rallumer | `Wi-Fi : connexion perdue`, mesures gardées, puis reconnexion et envoi |
| Jeton révoqué | Écran **Boîtiers** → **Révoquer** | `jeton refusé (HTTP 401)`, mesures **conservées**, nouvel essai toutes les 5 min |
| Doublon | (automatique) si la réponse se perd, la mesure est renvoyée avec le même `uuid` | L'API répond 200 sans créer de doublon |

Pour réactiver le boîtier après le test de révocation : bouton **Réactiver (nouveau jeton)**, mettre le nouveau jeton dans `secrets.h`, téléverser.

**Limite connue** : les mesures en attente sont gardées en mémoire vive (jusqu'à 288, soit 24 h à 5 min). Si l'ESP32 perd son alimentation pendant une coupure de l'API, celles-ci sont perdues. Amélioration possible : les écrire dans la mémoire flash (LittleFS).

---

## 8. Passer en HTTPS (recette)

EF-C-04 impose HTTPS. Sur le serveur de recette :

1. Dans `secrets.h` :
   - `API_URL` en `https://<serveur-de-recette>/api/v1/mesures` ;
   - **commenter** `AUTORISER_HTTP_POUR_TESTS` (le programme refuse alors tout envoi en HTTP) ;
   - coller le certificat de l'autorité de certification du serveur dans `API_CERTIFICAT_CA`. Pour un certificat Let's Encrypt, c'est **ISRG Root X1** (https://letsencrypt.org/certs/isrgrootx1.pem) — voir l'exemple dans `secrets.exemple.h`.
2. Créer le boîtier sur la recette (écran **Serres → Boîtiers**), mettre son jeton dans `JETON_BOITIER`.
3. Téléverser.

Avec le certificat, l'ESP32 vérifie qu'il parle bien au vrai serveur avant d'envoyer son jeton : un faux point d'accès Wi-Fi ne peut pas le récupérer.

---

## 9. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Aucun port COM | Pilote USB absent ou câble de charge seule | Pilote CP210x ou CH340 ; autre câble |
| Bloqué sur `Connecting...` | L'ESP32 n'entre pas en mode programmation | Maintenir **BOOT** pendant le téléversement |
| `secrets.h: No such file or directory` | Fichier de secrets pas créé | Copier `secrets.exemple.h` en `secrets.h` (étape 5.1) |
| Rien de lisible au Moniteur série | Mauvaise vitesse | 115200 bauds |
| `Wi-Fi : connexion à ...` qui se répète | Réseau 5 GHz, Wi-Fi d'entreprise ou mauvais mot de passe | Partage de connexion en 2,4 GHz ; vérifier `secrets.h` |
| `Envoi : en attente de l'heure (NTP)` | Pas d'accès Internet | Le réseau doit donner accès à Internet (serveurs NTP) |
| `ERREUR config : HTTP refusé` | URL en `http://` sans le mode test | Décommenter `AUTORISER_HTTP_POUR_TESTS` (tests seulement) |
| `API injoignable (connection refused)` | PC éteint, Docker arrêté, mauvaise IP ou pare-feu | Vérifier depuis un téléphone du même réseau : `http://<ip-du-pc>:8080/up` |
| `mesure refusée par l'API (HTTP 422)` | Valeur hors plage ou heure fausse | Lire le message renvoyé : il nomme le champ en cause |
| `T=nan  H=nan` | Capteur blanc mal branché ou mauvais `CAPTEUR_TEMPERATURE` | Jaune sur GPIO 4, rouge sur 3V3 ; `TEMP_DHT20` si V2.0 |
| `Capteur de lumière : introuvable` | SDA/SCL inversés ou non alimenté | Jaune sur GPIO 22, blanc sur GPIO 21, rouge sur 3V3 |
| `L=nan` en plein soleil | Capteur saturé (au-delà d'environ 40 000 lx) | Normal : la valeur revient quand la lumière baisse |
| Redémarrages en boucle (`Brownout detector`) | Alimentation USB trop faible | Autre câble ou autre port USB |
