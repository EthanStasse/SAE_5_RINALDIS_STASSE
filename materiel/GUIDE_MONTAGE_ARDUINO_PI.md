# Solution de secours : Arduino + Raspberry Pi

> La solution principale est l'**ESP32** (voir [`GUIDE_MONTAGE.md`](GUIDE_MONTAGE.md)).
> Cette variante sert si aucun ESP32 n'est disponible : l'Arduino lit les capteurs
> et le Raspberry Pi sert de passerelle Wi-Fi vers la même API.


Exigence couverte : **EF-C-15** (câblage, flashage, configuration).
Durée : environ 2 h la première fois.

```
Capteurs ──fils──> Arduino ──câble USB──> Raspberry Pi ──Wi-Fi──> API Laravel (PC, puis serveur de recette)
```

Ordre conseillé : on valide chaque maillon avant d'ajouter le suivant.

1. [Câbler l'Arduino](#1-câbler-larduino)
2. [Flasher et tester l'Arduino seul (sur le PC)](#2-flasher-et-tester-larduino-seul)
3. [Préparer le Raspberry Pi](#3-préparer-le-raspberry-pi)
4. [Tester la passerelle avec la fausse API](#4-tester-la-passerelle-avec-la-fausse-api)
5. [Relier la passerelle à l'API Laravel du PC](#5-relier-la-passerelle-à-lapi-laravel-du-pc)
6. [Installer la passerelle en service](#6-installer-la-passerelle-en-service)
7. [Tests de robustesse](#7-tests-de-robustesse)
8. [Dépannage](#8-dépannage)

---

## 1. Câbler l'Arduino

**Toujours débrancher le câble USB pendant le câblage.** Tous les cas (Grove avec ou sans Base Shield, composants nus) sont détaillés dans [`CABLAGE.md`](CABLAGE.md).

Avec la **Grove Base Shield** :

1. Interrupteur de la Base Shield sur **5V**, puis l'enficher sur l'Arduino.
2. Brancher les modules avec les câbles Grove :

| Module | Prise |
|---|---|
| Grove - Temperature & Humidity Sensor **Pro** (blanc) | **D4** |
| Grove - Digital Light Sensor | une prise **I2C** |
| Grove - LED | **D6** |
| Grove - Buzzer | **D8** |

**Vérifier le capteur blanc** : lire le texte imprimé dessus.

- « Temperature & Humidity Sensor **Pro** » (ou AM2302 / DHT22) → prise **D4**, `TEMP_DHT22` (réglage par défaut).
- « Temperature & Humidity Sensor **V2.0** » (ou DHT20) → prise **I2C**, `TEMP_DHT20`.

Avec ces deux capteurs, les trois grandeurs d'EF-C-03 sont mesurées : température, humidité de l'air et luminosité en lux.

---

## 2. Flasher et tester l'Arduino seul

### 2.1 Installer l'IDE et les bibliothèques (sur le PC)

1. Installer **Arduino IDE 2** (arduino.cc/en/software).
2. *Outils → Gérer les bibliothèques*, installer :
   - **DHT sensor library** (Adafruit) → accepter **Adafruit Unified Sensor** ;
   - **Adafruit TSL2561** (pour le Grove - Digital Light Sensor).

   Seulement pour d'autres capteurs : **DHT20** (Rob Tillaart) pour un DHT20, **BH1750** (Christopher Laws) pour un BH1750. Aucune bibliothèque pour la thermistance ni le capteur de lumière analogique du Starter Kit.

### 2.2 Téléverser

1. Ouvrir `materiel/arduino/capteurs_serre/capteurs_serre.ino`.
2. **Choisir les capteurs** (en haut du fichier). Valeurs par défaut, pour le capteur blanc **Pro** et le **Digital Light Sensor** :
   ```cpp
   #define CAPTEUR_TEMPERATURE TEMP_DHT22
   #define CAPTEUR_LUMIERE     LUMIERE_TSL2561
   ```
   Si le capteur blanc est un **V2.0** : `TEMP_DHT20`. Pour d'autres capteurs, voir le tableau de [`CABLAGE.md`](CABLAGE.md). Le Grove Buzzer est actif : laisser `BUZZER_ACTIF = true`.
3. Brancher l'Arduino en USB. *Outils → Type de carte* : **Arduino Uno** (ou **Arduino Leonardo**). *Outils → Port* : le port COM qui apparaît.
4. Cliquer sur **Téléverser** (flèche →). Attendre « Téléversement terminé ».
   - Leonardo : le numéro de port COM peut changer pendant le téléversement, c'est normal.

### 2.3 Tester avec le Moniteur série

*Outils → Moniteur série*, puis en bas à droite : **115200 bauds** et **Nouvelle ligne** (« New Line ») — sans cela les commandes ne sont pas reçues.

| Test | Action | Résultat attendu |
|---|---|---|
| Démarrage | Appuyer sur le bouton RESET de la carte | La LED s'allume 0,3 s puis `{"type":"demarrage","version":1}` |
| Première mesure | Attendre 3 s | `{"type":"mesure","seq":1,"temperature":21.3,"humidite":48.7,"luminosite":312.5}` |
| Mesures rapides | Taper `INTERVALLE 10` puis Entrée | `{"type":"ack","commande":"INTERVALLE"}` puis une mesure toutes les 10 s |
| Température | Pincer le capteur blanc entre deux doigts 30 s | `temperature` monte de quelques degrés |
| Humidité | Souffler sur le capteur blanc | `humidite` monte nettement |
| Luminosité | Couvrir le Digital Light Sensor avec la main, puis l'éclairer avec la lampe d'un téléphone | `luminosite` proche de 0, puis plusieurs centaines ou milliers de lux |
| État | Taper `PING` | `{"type":"pong","version":1,"intervalle":10,"alerte":0}` |
| Alerte locale | Taper `ALERTE 1` | LED qui clignote + bip court toutes les 10 s |
| Fin d'alerte | Taper `ALERTE 0` | LED éteinte, silence |
| Capteur débranché | Débrancher le câble du capteur blanc | `"temperature":null,"humidite":null` (la luminosité continue) ; en le rebranchant, les valeurs reviennent |
| Commande inconnue | Taper `BONJOUR` | `{"type":"erreur","message":"commande inconnue"}` |

**Fermer le Moniteur série** avant de brancher l'Arduino sur le Pi (sinon le port est occupé).

Recommencer avec la deuxième carte si vous l'utilisez.

---

## 3. Préparer le Raspberry Pi

### 3.1 Installer le système (sur le PC)

1. Installer **Raspberry Pi Imager** (raspberrypi.com/software).
2. Appareil : **Raspberry Pi 3** · Système : **Raspberry Pi OS (other) → Raspberry Pi OS Lite (64-bit)** · Stockage : la carte microSD.
3. Dans **Modifier les réglages** :
   - nom d'hôte : `serre-pi` ;
   - nom d'utilisateur et mot de passe (à noter) ;
   - Wi-Fi : nom et mot de passe du réseau, pays **FR** ;
   - fuseau : **Europe/Paris** ;
   - onglet Services : **activer SSH** (authentification par mot de passe).
4. Écrire la carte, la mettre dans le Pi, brancher l'alimentation (**5 V 2,5 A**). Attendre 2 à 3 minutes.

### 3.2 Se connecter en SSH (PowerShell sur le PC)

```powershell
ssh <utilisateur>@serre-pi.local
```

Si `serre-pi.local` n'est pas trouvé : chercher l'adresse IP du Pi dans l'interface de la box ou du partage de connexion, puis `ssh <utilisateur>@<ip-du-pi>`.

### 3.3 Installer les outils (sur le Pi)

```bash
sudo apt update
sudo apt install -y git python3-serial python3-requests sqlite3
git clone https://github.com/EthanStasse/SAE_5_RINALDIS_STASSE.git
cd SAE_5_RINALDIS_STASSE/materiel/passerelle
python3 -m unittest discover -s tests -v      # 16 tests, tous « ok »
timedatectl                                    # « System clock synchronized: yes »
groups                                         # doit contenir « dialout »
```

Si `dialout` n'apparaît pas : `sudo usermod -aG dialout $USER`, puis se déconnecter (`exit`) et se reconnecter.

### 3.4 Brancher l'Arduino sur le Pi

```bash
ls -l /dev/serial/by-id/
```

Résultat attendu (exemples) :

```
usb-Arduino__www.arduino.cc__0043_7563331363335141F0A1-if00 -> ../../ttyACM0   (Uno)
usb-Arduino_LLC_Arduino_Leonardo-if00 -> ../../ttyACM1                          (Leonardo)
```

On utilise toujours le chemin **/dev/serial/by-id/...** : il ne change pas quand on rebranche, contrairement à `ttyACM0`.

---

## 4. Tester la passerelle avec la fausse API

But : vérifier la chaîne Arduino → Pi sans Laravel. Ouvrir **deux** fenêtres PowerShell connectées en SSH au Pi.

**Fenêtre 1** — fausse API (alerte si T > 26 °C) :

```bash
cd ~/SAE_5_RINALDIS_STASSE/materiel/passerelle
python3 outils/faux_api.py --port 8000 --jeton test123 --seuil-temperature 26
```

**Fenêtre 2** — passerelle :

```bash
cd ~/SAE_5_RINALDIS_STASSE/materiel/passerelle
nano outils/config.test-local.toml     # remplacer port = "/dev/ttyACM0" par le chemin /dev/serial/by-id/...
python3 passerelle.py --config outils/config.test-local.toml -v
```

Attendu dans la fenêtre 2 :

```
INFO boitier.serre-test: ouverture de /dev/serial/by-id/...
INFO boitier.serre-test: Arduino OK (protocole v1, intervalle 10 s)
INFO passerelle: [serre-test] mesure n°1 : T=21.4 °C  H=48.7 %  L=312.5 lx
```

Attendu dans la fenêtre 1 :

```
2026-11-16T20:15:00Z  T=21.4  H=48.7  L=312.5  alerte=False
```

**Test de l'alerte** : pincer le capteur blanc entre deux doigts. Au-delà de 26 °C, la fausse API répond `alerte=True` et la LED + le buzzer de l'Arduino s'activent. En relâchant, ils s'arrêtent à la mesure suivante sous 26 °C.

Arrêter avec **Ctrl+C** dans les deux fenêtres.

---

## 5. Relier la passerelle à l'API Laravel du PC

### 5.1 Sur le PC : rendre l'API joignable

1. Démarrer l'application : dans `Code\`, `docker compose up -d`.
2. Trouver l'adresse IP du PC sur le Wi-Fi : `ipconfig` → carte « Wi-Fi » → **Adresse IPv4** (ex. `192.168.1.42`).
3. Ouvrir le port 8080 dans le pare-feu Windows (PowerShell **en administrateur**, une seule fois) :

   ```powershell
   New-NetFirewallRule -DisplayName "Cocagne API 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
   ```

   Le réseau Wi-Fi doit être en profil **Privé** (*Paramètres → Réseau → Wi-Fi → Propriétés*).

### 5.2 Créer un boîtier et son jeton (sur le PC)

Tant que l'écran des boîtiers n'existe pas, on passe par Tinker :

```powershell
docker compose exec app php artisan tinker
```

puis coller cette ligne (crée « Boîtier 1 » dans la « Serre nord » du Jardin de Thaon) :

```php
app(App\Support\StructureCourante::class)->sansFiltre(fn () => App\Models\Boitier::creerPour(App\Models\Serre::where('nom', 'Serre nord')->first(), 'Boîtier 1')[1]);
```

**Noter le jeton affiché** (48 caractères) : il n'est stocké nulle part en clair et ne sera plus jamais affiché. Taper `exit` pour quitter.

### 5.3 Vérifier depuis le Pi

```bash
curl http://<ip-du-pc>:8080/up
curl -H "Authorization: Bearer <jeton>" http://<ip-du-pc>:8080/api/v1/boitier
```

Attendu : une page « Application up », puis `{"id":1,"nom":"Boîtier 1","serre":{"id":1,"nom":"Serre nord"}}`.

### 5.4 Configurer et lancer la passerelle (sur le Pi)

```bash
cd ~/SAE_5_RINALDIS_STASSE/materiel/passerelle
cp config.exemple.toml config.toml        # config.toml est ignoré par Git : le jeton n'est jamais commité
nano config.toml
```

Modifier :

```toml
[api]
url = "http://<ip-du-pc>:8080/api/v1/mesures"
autoriser_http_pour_tests = true          # à retirer en recette (HTTPS)

[passerelle]
intervalle_mesure_s = 10                  # 10 s pour le test ; remettre 300 ensuite
tampon = "/home/<utilisateur>/tampon.sqlite3"

[[boitiers]]
nom = "serre-nord"
port = "/dev/serial/by-id/<le chemin vu à l'étape 3.4>"
jeton = "<le jeton noté à l'étape 5.2>"
```

Lancer :

```bash
python3 passerelle.py --config config.toml -v
```

### 5.5 Vérifier dans Laravel (sur le PC)

```powershell
docker compose exec app php artisan tinker
```

```php
app(App\Support\StructureCourante::class)->sansFiltre(fn () => App\Models\Mesure::latest('id')->take(5)->get(['mesure_le', 'temperature', 'humidite', 'luminosite'])->toArray());
```

Les mesures doivent apparaître, avec `mesure_le` en UTC (1 h de moins que l'heure de Paris en hiver).

---

## 6. Installer la passerelle en service

Le service démarre avec le Pi et redémarre tout seul en cas de plantage.

```bash
cd ~/SAE_5_RINALDIS_STASSE/materiel/passerelle
sudo useradd --system --no-create-home --shell /usr/sbin/nologin passerelle
sudo mkdir -p /opt/passerelle-serre /etc/passerelle-serre
sudo cp passerelle.py protocole.py tampon.py client_api.py liaison_serie.py /opt/passerelle-serre/

sudo cp config.toml /etc/passerelle-serre/config.toml
sudo sed -i 's#^tampon = .*#tampon = "/var/lib/passerelle-serre/tampon.sqlite3"#' /etc/passerelle-serre/config.toml
sudo sed -i 's#^intervalle_mesure_s = .*#intervalle_mesure_s = 300#' /etc/passerelle-serre/config.toml
sudo chown root:passerelle /etc/passerelle-serre/config.toml
sudo chmod 640 /etc/passerelle-serre/config.toml

sudo cp passerelle-serre.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now passerelle-serre
journalctl -u passerelle-serre -f       # Ctrl+C pour quitter l'affichage
```

Le jeton n'est lisible que par root et par le service (`chmod 640`).

Pour une démonstration, on peut garder `intervalle_mesure_s = 10` le temps de la présentation.

---

## 7. Tests de robustesse

À faire avec le service lancé et `journalctl -u passerelle-serre -f` ouvert.

| Scénario | Action | Comportement attendu |
|---|---|---|
| Câble USB | Débrancher l'Arduino 20 s, rebrancher | `liaison série perdue`, puis `ouverture de ...` et `Arduino OK` |
| Coupure de l'API | Sur le PC : `docker compose stop nginx` | `API injoignable ... mesure(s) en attente`, l'attente double à chaque essai (5, 10, 20 s…) |
| Retour de l'API | `docker compose start nginx` | Les mesures en attente partent toutes, dans l'ordre ; aucune perdue |
| Redémarrage du Pi | `sudo reboot` | Le service repart seul ; les mesures en attente sont conservées (tampon SQLite) |
| Jeton révoqué | Dans Tinker : `App\Models\Boitier::withoutGlobalScopes()->find(1)->revoquer();` | `jeton refusé (HTTP 401)`, mesures **conservées** sur le Pi |
| Valeur absurde | (simulé par les tests automatiques) | `mesure refusée par l'API (HTTP 422)` avec le message explicite |

Pour réactiver le boîtier après le test de révocation : générer un nouveau jeton (`->genererJeton()` dans Tinker) et le mettre dans `/etc/passerelle-serre/config.toml`, puis `sudo systemctl restart passerelle-serre`.

---

## 8. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Rien dans le Moniteur série | Mauvaise vitesse | 115200 bauds |
| Les commandes ne répondent pas | Pas de fin de ligne | Moniteur série : « Nouvelle ligne » |
| `temperature` et `humidite` toujours `null` | Mauvaise prise ou mauvais `CAPTEUR_TEMPERATURE` | Capteur Pro (DHT22) sur **D4** ; V2.0 (DHT20) sur **I2C** avec `TEMP_DHT20` |
| Température fausse de plusieurs degrés (thermistance) | Mauvaise constante B | `THERMISTANCE_B` : 4275 (v1.2) ou 3975 (v1.0/v1.1) |
| Valeurs incohérentes avec la Base Shield | Interrupteur sur 3,3V | Le mettre sur **5V** |
| `luminosite` toujours `null` | Digital Light Sensor pas sur une prise I2C, ou bibliothèque manquante | Le brancher sur une prise **I2C** ; lancer le scanner I2C ci-dessous |
| `luminosite` à `null` en plein soleil | Capteur saturé (au-delà d'environ 40 000 lx) | Normal : la valeur revient quand la lumière baisse |
| Téléversement impossible (Leonardo) | Port non détecté | Appuyer sur RESET juste après avoir cliqué sur Téléverser |
| `Permission denied: /dev/ttyACM0` | Utilisateur hors du groupe `dialout` | `sudo usermod -aG dialout $USER`, se reconnecter |
| `could not exclusively lock port` | Port déjà utilisé | Fermer l'autre programme (ou `sudo systemctl stop passerelle-serre`) |
| `API injoignable (ConnectionError)` | PC éteint, Docker arrêté, mauvaise IP ou pare-feu | `curl http://<ip-du-pc>:8080/up` depuis le Pi |
| `curl` bloque depuis le Pi | Réseau Wi-Fi qui isole les appareils (Wi-Fi d'école, invité) | Mettre le PC et le Pi sur le partage de connexion d'un téléphone |
| `horloge NON synchronisée` | Pas d'accès NTP | Vérifier Internet : `timedatectl`, `ping fr.pool.ntp.org` |
| Déconnexions USB aléatoires | Alimentation du Pi trop faible | `vcgencmd get_throttled` doit donner `0x0` |

### Scanner I2C (vérifier que le BH1750 répond)

Utile seulement pour un capteur I2C. Téléverser temporairement ce programme, ouvrir le Moniteur série à 115200 bauds : il doit afficher `0x29` pour le Grove - Digital Light Sensor (ou `0x39`/`0x49` selon le module), `0x38` pour un DHT20, `0x23` pour un BH1750.

```cpp
#include <Wire.h>

void setup() {
  Serial.begin(115200);
  while (!Serial && millis() < 3000) {}
  Wire.begin();
}

void loop() {
  Serial.println(F("Recherche I2C..."));
  for (uint8_t adresse = 1; adresse < 127; adresse++) {
    Wire.beginTransmission(adresse);
    if (Wire.endTransmission() == 0) {
      Serial.print(F("Trouvé : 0x"));
      Serial.println(adresse, HEX);
    }
  }
  delay(3000);
}
```

Puis retéléverser `capteurs_serre.ino`.
