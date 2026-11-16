# Câblage d'un boîtier capteur de serre

Base du guide de montage (EF-C-15). L'Arduino est alimenté par le câble USB du Raspberry Pi : aucune autre alimentation n'est nécessaire.

**Toujours débrancher le câble USB pendant le câblage.**

## Montage du projet (réglage par défaut du programme)

| Module | Prise de la Grove Base Shield (interrupteur sur **5V**) |
|---|---|
| Grove - Temperature & Humidity Sensor **Pro** (blanc, DHT22) | **D4** |
| Grove - Digital Light Sensor (TSL2561) | une prise **I2C** |
| Grove - LED | **D6** |
| Grove - Buzzer | **D8** |

Si le capteur blanc porte la mention **V2.0** (DHT20), il se branche sur une prise **I2C** et il faut mettre `TEMP_DHT20` dans le programme.

## Choisir ses capteurs dans le programme

En haut de `arduino/capteurs_serre/capteurs_serre.ino`, deux lignes indiquent les capteurs utilisés :

```cpp
#define CAPTEUR_TEMPERATURE TEMP_DHT22       // ou TEMP_DHT11, TEMP_DHT20, TEMP_THERMISTANCE
#define CAPTEUR_LUMIERE     LUMIERE_TSL2561  // ou LUMIERE_BH1750, LUMIERE_ANALOGIQUE
```

| Module | Valeur à mettre | Mesure | Bibliothèque |
|---|---|---|---|
| Grove - Temperature Sensor (Starter Kit, thermistance) | `TEMP_THERMISTANCE` | température seule | aucune |
| Grove - Temperature & Humidity Sensor (bleu) | `TEMP_DHT11` | température + humidité | DHT sensor library (Adafruit) |
| Grove - Temperature & Humidity Sensor **Pro** (blanc) ou DHT22 nu | `TEMP_DHT22` | température + humidité | DHT sensor library (Adafruit) |
| Grove - Temperature & Humidity Sensor **V2.0** (DHT20) | `TEMP_DHT20` | température + humidité | DHT20 (Rob Tillaart) |
| Grove - Light Sensor (Starter Kit, photorésistance) | `LUMIERE_ANALOGIQUE` | niveau relatif **en %** | aucune |
| Grove - **Digital** Light Sensor (TSL2561) | `LUMIERE_TSL2561` | luminosité **en lux** | Adafruit TSL2561 + Adafruit Unified Sensor |
| BH1750 (module GY-302) | `LUMIERE_BH1750` | luminosité **en lux** | BH1750 (Christopher Laws) |

Pour la thermistance, vérifier aussi `THERMISTANCE_B` : **4275** si le module est marqué v1.2, **3975** pour v1.0/v1.1.

---

## Option A — Modules Grove sur la Grove Base Shield (recommandé)

1. Mettre l'interrupteur de la Base Shield sur **5V**.
2. Enficher la Base Shield sur l'Arduino (Uno ou Leonardo).
3. Brancher chaque module avec un câble Grove sur la prise indiquée :

| Module | Prise de la Base Shield |
|---|---|
| Grove - Temperature Sensor (thermistance) | **A0** |
| Grove - Light Sensor | **A2** |
| Grove - Temperature & Humidity (DHT11 / Pro) | **D4** |
| Grove - Digital Light Sensor, Temperature & Humidity V2.0 (DHT20), BH1750 | n'importe quelle prise **I2C** |
| Grove - LED | **D6** |
| Grove - Buzzer | **D8** |

Pourquoi ces prises : chaque prise Grove porte deux broches (la sienne et la suivante). En laissant une prise libre entre deux modules (A0/A2, D4/D6/D8), aucun fil ne se partage. Les prises D2 et D3 sont évitées : sur le Leonardo, ce sont les broches I2C.

## Option B — Câbles Grove vers fils (sans Base Shield)

Code couleur d'un câble Grove : **jaune** = signal, **blanc** = 2ᵉ signal (SDA pour l'I2C), **rouge** = VCC (5V), **noir** = GND.

| Module | Jaune | Blanc | Rouge | Noir |
|---|---|---|---|---|
| Temperature Sensor (thermistance) | A0 | — | 5V | GND |
| Light Sensor | A2 | — | 5V | GND |
| Temp & Humi DHT11 / Pro | D4 | — | 5V | GND |
| LED | D6 | — | 5V | GND |
| Buzzer | D8 | — | 5V | GND |
| Module I2C (Digital Light Sensor, DHT20, BH1750) | SCL | SDA | 5V | GND |

Broches I2C : **A5 (SCL) / A4 (SDA)** sur l'Uno, **D3 (SCL) / D2 (SDA)** sur le Leonardo, ou les broches marquées SCL/SDA près d'AREF (identiques sur les deux cartes).

## Option C — Composants nus sur breadboard

```
DHT22 nu (grille face à soi, de gauche à droite)
  1 VCC  ─────────────── 5V
  2 DATA ──────┬──────── D4
               └─[10 kΩ]─ 5V      (résistance de tirage)
  3 NC          (rien)
  4 GND  ─────────────── GND

BH1750 (module GY-302) : VCC → 5V, GND → GND, SCL/SDA → voir ci-dessus, ADDR → GND (adresse 0x23)

LED : D6 ──[220 Ω]──►|── GND   (patte longue côté résistance)
Buzzer actif : D8 ── (+) buzzer (−) ── GND
```

Un buzzer **passif** (simple « clic » sous 5 V continu) se pilote avec `BUZZER_ACTIF = false`. Un buzzer magnétique ou consommant plus de 20 mA passe par un transistor NPN (BC547) avec une résistance de 1 kΩ sur la base.

---

## Raspberry Pi

- Arduino → Pi : câble USB (USB-B pour l'Uno, micro-USB pour le Leonardo).
- Le Pi 3 doit être alimenté par une alimentation **5 V 2,5 A**. Une alimentation trop faible provoque des déconnexions USB aléatoires (`vcgencmd get_throttled` ≠ `0x0`).
