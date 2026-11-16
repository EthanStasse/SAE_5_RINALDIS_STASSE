# Guide de montage : boîtier de serre ESP32 et capteurs Grove

Nov 16, 2026 · @7

Le boîtier mesure toutes les 5 minutes la température, l'humidité de l'air et la luminosité d'une serre, puis envoie les mesures en Wi-Fi à l'API Laravel (`POST /api/v1/mesures`). Il réutilise vos modules Grove ; seule la carte change par rapport au montage Arduino + Raspberry Pi. Ce guide couvre l'exigence EF-C-15 du cahier des charges.

**La règle à retenir avant tout : l'ESP32 fonctionne en 3,3 V.** Ses broches ne supportent pas 5 V. Alimentez tous les modules Grove en **3,3 V**, jamais en 5 V : le signal qu'ils renvoient reste alors à 3,3 V et ne peut pas abîmer la carte. Vos quatre modules acceptent 3,3 V.

## 1. Matériel

Vous avez déjà les modules Grove. Il vous faut en plus une carte ESP32 et de quoi brancher les câbles Grove sans Base Shield.

| Élément | Référence | Rôle | Vous l'avez ? |
| --- | --- | --- | --- |
| Carte ESP32 | ESP32 DevKitC ou équivalent (ESP32-WROOM-32, 38 broches) | Lit les capteurs, envoie en Wi-Fi | À vérifier |
| Température et humidité | Grove - Temperature & Humidity Sensor Pro (DHT22 / AM2302) | Mesures de l'air | Oui |
| Luminosité | Grove - Digital Light Sensor (TSL2561, I2C) | Lux, de 0,1 à 40 000 lx | Oui |
| Signal visuel | Grove - LED | Clignote en cas d'erreur ou d'alerte | Oui |
| Signal sonore | Grove - Buzzer (actif) | Bip en cas d'alerte locale | Oui |
| Câbles | 4 câbles « Grove vers Dupont femelle » (Grove 4 broches vers 4 fils) | Relier les modules aux broches de l'ESP32 | À vérifier |
| Breadboard et fils | Breadboard + quelques fils mâle-mâle | Partager 3,3 V et GND entre les modules | Oui |
| Alimentation | Câble USB **de données** + chargeur USB 5 V (1 A suffit) | Programmer et alimenter la carte | À vérifier |

**Code couleur des câbles Grove** (le même sur tous les modules) :

| Fil | Module numérique (DHT22, LED, buzzer) | Module I2C (TSL2561) |
| --- | --- | --- |
| Jaune | Signal | SCL |
| Blanc | Non utilisé | SDA |
| Rouge | Alimentation (VCC) | Alimentation (VCC) |
| Noir | Masse (GND) | Masse (GND) |

## 2. Câblage

&#91;embedded content: câblage · ESP32 et 4 modules Grove\]

Le TSL2561 utilise deux fils de signal (I2C) ; les trois autres modules n'en utilisent qu'un, le jaune.

| Module | Fil jaune | Fil blanc | Fil rouge | Fil noir |
| --- | --- | --- | --- | --- |
| DHT22 | GPIO 4 | non branché | 3V3 | GND |
| TSL2561 | GPIO 22 (SCL) | GPIO 21 (SDA) | 3V3 | GND |
| LED | GPIO 25 | non branché | 3V3 | GND |
| Buzzer | GPIO 26 | non branché | 3V3 | GND |

**Broches à ne pas utiliser** sur une ESP32 DevKitC : GPIO 6 à 11 (reliées à la mémoire flash), GPIO 0, 2, 5, 12 et 15 (elles décident du mode de démarrage), GPIO 34 à 39 (entrées seulement, inutilisables pour la LED et le buzzer). Les broches choisies ci-dessus évitent tous ces cas.

Le numéro de GPIO est écrit à côté de chaque broche sur la carte, parfois sans le mot « GPIO » (par exemple « D4 » ou « 4 »). En cas de doute, cherchez le schéma des broches de votre modèle exact.

## 3. Préparer le poste

1. Installer l'Arduino IDE 2 depuis arduino.cc.
2. Ajouter les cartes ESP32 : **Fichier > Préférences**, champ « URL de gestionnaire de cartes supplémentaires », coller l'adresse stable d'Espressif : `https://espressif.github.io/arduino-esp32/package_esp32_index.json`
3. **Outils > Carte > Gestionnaire de cartes**, chercher « esp32 », installer **esp32 by Espressif Systems**.
4. **Croquis > Inclure une bibliothèque > Gérer les bibliothèques**, installer :
   - DHT sensor library (Adafruit) ;
   - Adafruit Unified Sensor ;
   - Adafruit TSL2561. Ce sont les mêmes que pour votre programme Arduino : le code de lecture des capteurs se réutilise presque tel quel.
5. Brancher l'ESP32 en USB et choisir **Outils > Carte > ESP32 Dev Module**, puis le bon port.
6. Si aucun port n'apparaît : installer le pilote de la puce USB de votre carte (CP210x ou CH340, le nom est écrit sur la puce à côté du connecteur), ou changer de câble : beaucoup de câbles USB ne font que charger.
7. Premier test sans rien brancher : ouvrir l'exemple **Fichier > Exemples > WiFi > WiFiScan** et le téléverser. Le moniteur série (115 200 bauds) doit lister les réseaux Wi-Fi visibles, dont celui de la serre.

**Attention** : l'ESP32 ne se connecte qu'aux réseaux **2,4 GHz**, pas 5 GHz. Vérifiez que le Wi-Fi de la serre en propose un.

## 4. Montage pas à pas

Branchez **un module à la fois** et testez-le avant de passer au suivant : si quelque chose ne marche pas, vous saurez tout de suite d'où ça vient. Faites chaque branchement **carte débranchée de l'USB**.

### Étape 1 : les rails d'alimentation

1. Planter l'ESP32 à cheval sur la rainure centrale de la breadboard.
2. Relier la broche **3V3** de l'ESP32 au rail rouge (+) et une broche **GND** au rail bleu (−).
3. Tous les fils rouges des modules iront sur le rail +, tous les noirs sur le rail −.

### Étape 2 : la LED (le plus simple, pour vérifier le principe)

1. Câble Grove de la LED : jaune sur **GPIO 25**, rouge sur rail +, noir sur rail −, blanc non branché.
2. Test : ouvrir **Fichier > Exemples > 01.Basics > Blink**, remplacer `LED_BUILTIN` par `25`, téléverser.
3. **Réussi si** la LED Grove clignote une fois par seconde.

### Étape 3 : le buzzer

1. Jaune sur **GPIO 26**, rouge sur rail +, noir sur rail −.
2. Test : le même exemple Blink avec `26` à la place de `25`. Le buzzer étant actif, il sonne dès que la broche est à l'état haut.
3. **Réussi si** le buzzer bipe une seconde sur deux. Si le son est trop faible en 3,3 V, ce n'est pas grave pour un prototype.

### Étape 4 : le capteur de température et d'humidité (DHT22)

1. Jaune sur **GPIO 4**, rouge sur rail +, noir sur rail −.
2. Test : ouvrir l'exemple **DHT sensor library > DHTtester**, régler `#define DHTPIN 4` et `#define DHTTYPE DHT22`, téléverser, ouvrir le moniteur série.
3. **Réussi si** la température et l'humidité s'affichent, avec des valeurs plausibles. Soufflez sur le capteur : l'humidité doit monter.
4. Le DHT22 ne se lit pas plus d'une fois toutes les 2 secondes : ce n'est pas un problème avec une mesure toutes les 5 minutes.

### Étape 5 : le capteur de luminosité (TSL2561)

1. Jaune (SCL) sur **GPIO 22**, blanc (SDA) sur **GPIO 21**, rouge sur rail +, noir sur rail −. Ce sont les broches I2C par défaut de l'ESP32.
2. Premier test, le scanner I2C : il doit trouver une adresse (en général 0x29, sinon 0x39 ou 0x49). Notez-la.

```cpp
#include <Wire.h>

void setup() {
  Serial.begin(115200);
  Wire.begin(21, 22); // SDA, SCL
}

void loop() {
  for (byte adresse = 1; adresse < 127; adresse++) {
    Wire.beginTransmission(adresse);
    if (Wire.endTransmission() == 0) {
      Serial.printf("Module I2C trouve a l'adresse 0x%02X\n", adresse);
    }
  }
  Serial.println("Fin du scan");
  delay(5000);
}
```

3. Deuxième test : l'exemple **Adafruit TSL2561 > sensorapi**, en mettant l'adresse trouvée dans la création du capteur.
4. **Réussi si** la valeur en lux augmente quand vous éclairez le capteur avec une lampe de téléphone et baisse quand vous le cachez.

### Étape 6 : le boîtier final

1. Fixer l'ESP32 et la breadboard dans un boîtier ventilé, à l'abri des projections d'eau.
2. Laisser le DHT22 **à l'extérieur du boîtier** ou derrière des aérations : enfermé, il mesurerait la chaleur de la carte.
3. Placer le TSL2561 face au ciel, sans ombre portée, et à l'abri du soleil direct sur l'ESP32.
4. Prendre des photos du montage pour la documentation et la soutenance.

## 5. Programme

Le programme se construit à partir de votre programme Arduino actuel : la lecture des capteurs reste la même, seuls l'envoi (Wi-Fi au lieu du port série) et la boucle changent. À ranger dans `materiel/esp32/boitier_serre/` du dépôt.

### 5.1 Configuration et secrets

Les identifiants Wi-Fi et le jeton du boîtier ne doivent jamais aller sur GitHub. Créez deux fichiers :

- `config.exemple.h`, versionné, avec des valeurs factices ;
- `config.h`, copié depuis l'exemple et rempli avec les vraies valeurs, ajouté au `.gitignore`.

```cpp
// config.exemple.h : copier en config.h et remplir. Ne jamais committer config.h.
#define WIFI_SSID      "nom-du-reseau-2-4GHz"
#define WIFI_MOT_PASSE "mot-de-passe-wifi"
#define API_URL        "http://192.168.1.20:8080/api/v1/mesures" // IP du PC en developpement
#define API_JETON      "jeton-affiche-a-la-creation-du-boitier"
#define INTERVALLE_S   300 // 5 minutes (EF-C-03)

#define BROCHE_DHT     4
#define BROCHE_LED     25
#define BROCHE_BUZZER  26
#define BROCHE_SDA     21
#define BROCHE_SCL     22
```

### 5.2 Ce que fait le programme à chaque réveil

1. Se connecter au Wi-Fi (abandonner au bout de 20 secondes et faire clignoter la LED).
2. Récupérer l'heure par NTP, en UTC (`configTime(0, 0, "pool.ntp.org")`), pour dater la mesure.
3. Lire le DHT22 et le TSL2561. Une valeur illisible devient `null` dans le JSON, comme dans le protocole série actuel.
4. Envoyer un `POST` JSON à `API_URL` avec l'en-tête `Authorization: Bearer API_JETON`, avec les champs attendus par votre API.
5. Lire la réponse : 201, tout va bien ; 401, jeton refusé ; 422, mesure rejetée. Afficher le code dans le moniteur série.
6. Se mettre en veille profonde pendant `INTERVALLE_S` secondes (`esp_sleep_enable_timer_wakeup`, puis `esp_deep_sleep_start`).

### 5.3 Trois choix à faire et à expliquer en soutenance

- **HTTP ou HTTPS.** En développement, l'ESP32 parle en HTTP à l'adresse IP de votre PC : il ne peut pas joindre `localhost`. Sur le serveur de recette, il faut HTTPS (EF-C-04) avec `WiFiClientSecure` et le certificat racine de l'autorité du serveur (`setCACert`). N'utilisez `setInsecure()` que pour un essai ponctuel : il désactive la vérification du serveur.
- **Veille profonde ou alerte locale.** En veille profonde, la LED et le buzzer s'éteignent. Si vous voulez une alerte sonore sur place, il faut soit rester éveillé (plus de consommation, mais le boîtier est sur secteur), soit sonner seulement au réveil si la mesure dépasse un seuil. Dans les deux cas, l'alerte qui compte pour le cahier des charges reste celle de l'application (EF-C-09 à EF-C-11).
- **Perte du Wi-Fi.** Si l'envoi échoue, la mesure est perdue. Garder les dernières mesures en mémoire RTC pour les renvoyer au réveil suivant est une amélioration (EF-C-06, souhaitable), pas une obligation.

### 5.4 Commits proposés

- `feat(esp32): lecture du DHT22 et du TSL2561 (EF-C-03)`
- `feat(esp32): connexion Wi-Fi et heure NTP`
- `feat(esp32): envoi des mesures a l'API avec le jeton du boitier (EF-C-04)`
- `feat(esp32): veille profonde de 5 minutes entre deux mesures`
- `docs(materiel): guide de montage ESP32 (EF-C-15)`

## 6. Dépannage

| Symptôme | Cause probable | Solution |
| --- | --- | --- |
| Aucun port dans l'Arduino IDE | Câble USB de charge seule, ou pilote absent | Changer de câble ; installer le pilote CP210x ou CH340 |
| « Connecting...... » puis échec du téléversement | La carte n'entre pas en mode programmation | Maintenir le bouton **BOOT** pendant le début du téléversement |
| La carte redémarre en boucle | Broche réservée utilisée, ou alimentation trop faible | N'utiliser que les broches du tableau ; essayer un autre chargeur USB |
| DHT22 : « Failed to read » ou `nan` | Mauvaise broche, fil jaune mal enfoncé, lecture trop rapide | Vérifier GPIO 4 et `DHT22` dans le code ; attendre 2 s entre deux lectures |
| Le scanner I2C ne trouve rien | SDA et SCL inversés, ou module non alimenté | Jaune = SCL = GPIO 22, blanc = SDA = GPIO 21 ; vérifier 3V3 et GND |
| Luminosité bloquée ou nulle en plein soleil | Saturation du TSL2561 au-delà de 40 000 lx environ | Normal : signaler la saturation au lieu d'envoyer une erreur |
| Pas de connexion Wi-Fi | Réseau en 5 GHz uniquement, ou trop loin | Utiliser un réseau 2,4 GHz ; rapprocher le boîtier ou ajouter un répéteur |
| L'API répond 401 | Jeton faux, révoqué ou mal recopié | Recréer le jeton du boîtier et mettre à jour `config.h` |
| L'API répond 422 | Valeur hors plage ou champ mal nommé | Lire le message d'erreur renvoyé et comparer avec la validation Laravel |
| L'envoi échoue sans code HTTP | Adresse `localhost`, pare-feu du PC, ou HTTPS sans certificat | Utiliser l'IP du PC sur le réseau local ; ouvrir le port 8080 ; configurer `setCACert` en HTTPS |

## Sources

- [Grove - Digital Light Sensor (TSL2561), wiki Seeed](https://wiki.seeedstudio.com/Grove-Digital_Light_Sensor/)
- [Grove TSL2561 : alimentation 3,3 à 5 V, plage 0,1 à 40 000 lx (fiche Botland)](https://botland.com.pl/en/grove-light-and-color-sensors/11303-grove-tsl2561-digital-i2c-ambient-light-sensor.html)
- [Grove - Temperature & Humidity Sensor Pro (DHT22), alimentation 3,3 à 6 V, lecture toutes les 2 s (The Pi Hut)](https://thepihut.com/products/grove-temperature-humidity-sensor-pro-dht22-am2302)
- [Code couleur Grove I2C, wiki Seeed](https://wiki.seeedstudio.com/ja/Grove-TemptureAndHumidity_Sensor-High-Accuracy_AndMini-v1.0/)
- [Installation du cœur Arduino-ESP32, documentation Espressif](https://docs.espressif.com/projects/arduino-esp32/en/latest/installing.html)
