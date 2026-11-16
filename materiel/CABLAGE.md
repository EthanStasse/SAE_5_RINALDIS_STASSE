# Câblage d'un boîtier capteur de serre

Base du guide de montage (EF-C-15). Toutes les masses (GND) sont communes.
L'Arduino est alimenté par le câble USB du Raspberry Pi : aucune autre alimentation n'est nécessaire.

## Récapitulatif des broches

| Élément | Broche | Uno | Leonardo | Alimentation |
|---|---|---|---|---|
| DHT22 DATA | numérique | D4 | D4 | 5 V |
| BH1750 SDA | I2C | **A4** | **D2** (ou broche « SDA ») | 5 V si module GY-302, sinon 3,3 V |
| BH1750 SCL | I2C | **A5** | **D3** (ou broche « SCL ») | |
| LED | numérique | D9 | D9 | via 220 Ω |
| Buzzer | numérique | D8 | D8 | voir ci-dessous |

Le DHT22 est sur D4 et pas sur D2 : sur le Leonardo, D2 et D3 servent à l'I2C.
Les broches SDA et SCL situées près d'AREF fonctionnent sur les deux cartes. Les utiliser évite de changer de câblage entre l'Uno et le Leonardo.

## DHT22 (température + humidité)

```
DHT22 nu (4 broches, grille face à soi, de gauche à droite)
  1 VCC  ─────────────── 5V
  2 DATA ──────┬──────── D4
               └─[10 kΩ]─ 5V      (résistance de tirage)
  3 NC          (rien)
  4 GND  ─────────────── GND
```
Sur un module à **3 broches** (petite carte avec « + out - »), la résistance est déjà intégrée : brancher + sur 5V, out sur D4 et - sur GND.

## BH1750 (luminosité, I2C, adresse 0x23)

```
Module GY-302          Uno        Leonardo
  VCC  ─────────────── 5V         5V
  GND  ─────────────── GND        GND
  SCL  ─────────────── A5         D3 (SCL)
  SDA  ─────────────── A4         D2 (SDA)
  ADDR ─────────────── GND (ou non connectée) -> adresse 0x23
```
Le GY-302 a un régulateur 3,3 V intégré et accepte 5 V. Un capteur **sans régulateur** (puce seule, ou carte marquée 3,3 V uniquement) doit être alimenté en **3,3 V**.

## LED (signal d'alerte local)

```
D9 ──[220 Ω]──►|── GND
          anode (patte longue)   cathode (patte courte, côté plat)
```
Courant : (5 V − 2 V) / 220 Ω ≈ 14 mA, ce qui reste bien en dessous des 20 mA conseillés par broche.

## Buzzer

**Buzzer actif piézo** (il sonne dès qu'on lui met 5 V ; un autocollant sur le dessus, des pattes de longueurs différentes) :
```
D8 ──── (+) buzzer (-) ──── GND          BUZZER_ACTIF = true
```
**Buzzer passif** (avec 5 V continu, il ne fait qu'un « clic ») :
```
D8 ──[100 Ω]── buzzer ──── GND           BUZZER_ACTIF = false dans le .ino
```
**Buzzer magnétique, ou consommation supérieure à 20 mA** : ne pas le brancher directement sur une broche. Il faut passer par un transistor :
```
D8 ──[1 kΩ]── base   NPN (BC547 / 2N2222)
              émetteur ── GND
              collecteur ── (-) buzzer (+) ── 5V
              diode 1N4148 en parallèle sur le buzzer (cathode côté 5V)
```

## Raspberry Pi

- Arduino → Pi : câble USB (USB-B pour l'Uno, micro-USB pour le Leonardo).
- Le Pi 3 doit être alimenté par une alimentation **5 V 2,5 A**. Une alimentation trop faible provoque des déconnexions USB aléatoires (icône d'éclair, `vcgencmd get_throttled` ≠ 0x0).
