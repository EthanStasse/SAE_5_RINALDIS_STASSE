/*
 * Boîtier capteur de serre — module « surveillance de serre connectée »
 *
 * Rôle : lire la température, l'humidité de l'air et la luminosité, envoyer
 * une ligne JSON par mesure sur le port série USB, et recevoir des commandes
 * texte de la passerelle (Raspberry Pi).
 *
 * Compatible Arduino Uno et Arduino Leonardo, avec ou sans Grove Base Shield.
 *
 * ===========================================================================
 *  CONFIGURATION : CHOISIR LES CAPTEURS UTILISÉS (2 lignes à adapter)
 * ===========================================================================
 */
#define TEMP_DHT22         1  // DHT22 / AM2302 (Grove Temp&Humi Pro, blanc) : température + humidité
#define TEMP_DHT11         2  // DHT11 (Grove Temp&Humi, bleu) : température + humidité
#define TEMP_DHT20         3  // DHT20 (Grove Temp&Humi V2.0, I2C) : température + humidité
#define TEMP_THERMISTANCE  4  // Grove Temperature Sensor (Starter Kit) : température seule

#define LUMIERE_BH1750     1  // BH1750 (I2C) : luminosité en lux
#define LUMIERE_ANALOGIQUE 2  // Grove Light Sensor (Starter Kit) : niveau relatif en %
#define LUMIERE_TSL2561    3  // Grove Digital Light Sensor (TSL2561, I2C) : luminosité en lux

#define CAPTEUR_TEMPERATURE TEMP_DHT22
#define CAPTEUR_LUMIERE     LUMIERE_TSL2561

/*
 * ---------------------------------------------------------------------------
 * PROTOCOLE SÉRIE — 115200 bauds, 8N1, une ligne terminée par '\n'
 * ---------------------------------------------------------------------------
 * Arduino -> Pi (toujours du JSON, un objet par ligne)
 *   {"type":"demarrage","version":1}
 *   {"type":"mesure","seq":12,"temperature":21.4,"humidite":55.2,"luminosite":340.0}
 *        -> un champ vaut null si le capteur est absent ou en erreur
 *        -> seq repart à 1 après chaque redémarrage de la carte
 *   {"type":"pong","version":1,"intervalle":300,"alerte":0}
 *   {"type":"ack","commande":"INTERVALLE"}
 *   {"type":"erreur","message":"commande inconnue"}
 *
 * Pi -> Arduino (texte simple, une commande par ligne)
 *   PING                  -> répond pong (test de vie + état)
 *   MESURE                -> mesure immédiate (pratique pour les tests)
 *   INTERVALLE <secondes> -> change la période de mesure (5 à 3600 s)
 *   ALERTE <0|1>          -> éteint / allume le signal local (LED + buzzer)
 * ---------------------------------------------------------------------------
 *
 * Bibliothèques (Gestionnaire de bibliothèques de l'IDE), seulement si utilisées :
 *   - DHT22 ou DHT11 : « DHT sensor library » (Adafruit) + « Adafruit Unified Sensor »
 *   - DHT20          : « DHT20 » (Rob Tillaart)
 *   - BH1750         : « BH1750 » (Christopher Laws)
 *   - TSL2561        : « Adafruit TSL2561 » + « Adafruit Unified Sensor »
 *   - Thermistance et capteur de lumière analogique : aucune
 */

#include <Wire.h>

#if CAPTEUR_TEMPERATURE == TEMP_DHT22 || CAPTEUR_TEMPERATURE == TEMP_DHT11
#include <DHT.h>
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
#include <DHT20.h>
#endif

#if CAPTEUR_LUMIERE == LUMIERE_BH1750
#include <BH1750.h>
#elif CAPTEUR_LUMIERE == LUMIERE_TSL2561
#include <Adafruit_TSL2561_U.h>
#endif

// --- Broches ------------------------------------------------------------------
// Choisies pour correspondre aux prises de la Grove Base Shield. On évite
// D2/D3 : ce sont les broches I2C du Leonardo. Un module Grove n'utilise que
// le fil jaune de sa prise : prise D4 -> broche 4, prise A0 -> broche A0...
const uint8_t PIN_DHT = 4;            // prise D4 (DHT22 / DHT11)
const uint8_t PIN_THERMISTANCE = A0;  // prise A0 (Grove Temperature Sensor)
const uint8_t PIN_LUMIERE = A2;       // prise A2 (Grove Light Sensor)
const uint8_t PIN_LED = 6;            // prise D6 (Grove LED)
const uint8_t PIN_BUZZER = 8;         // prise D8 (Grove Buzzer)
// I2C (DHT20, BH1750, TSL2561) : n'importe quelle prise « I2C » de la Base Shield.

// true  = buzzer ACTIF  (sonne dès qu'on lui met 5 V ; c'est le cas du Grove Buzzer)
// false = buzzer PASSIF (il faut lui envoyer une fréquence avec tone())
const bool BUZZER_ACTIF = true;
const unsigned int FREQUENCE_BUZZER_HZ = 2000;  // utilisé seulement si passif

// Constante B de la thermistance Grove : 4275 pour la v1.2, 3975 pour la v1.0/v1.1
// (la version est imprimée sur le module).
const float THERMISTANCE_B = 4275.0;

// --- Paramètres ---------------------------------------------------------------
const uint8_t VERSION_PROTOCOLE = 1;
const unsigned long INTERVALLE_DEFAUT_S = 300;  // EF-C-03 : 5 minutes par défaut
const unsigned long INTERVALLE_MIN_S = 5;       // les DHT exigent au moins 2 s
const unsigned long INTERVALLE_MAX_S = 3600;
const unsigned long DELAI_PREMIERE_MESURE_MS = 2500;  // préchauffage des capteurs

// Signal d'alerte local : LED qui clignote, bip court toutes les 10 s
// (un buzzer continu serait insupportable pour les salariés en serre).
const unsigned long DEMI_PERIODE_LED_MS = 500;
const unsigned long PERIODE_BIP_MS = 10000;
const unsigned long DUREE_BIP_MS = 200;

// --- Objets des capteurs ----------------------------------------------------------
#if CAPTEUR_TEMPERATURE == TEMP_DHT22
DHT dht(PIN_DHT, DHT22);
#elif CAPTEUR_TEMPERATURE == TEMP_DHT11
DHT dht(PIN_DHT, DHT11);
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
DHT20 dht20(&Wire);
#endif

#if CAPTEUR_LUMIERE == LUMIERE_BH1750
BH1750 luxmetre;  // adresse 0x23 par défaut (broche ADDR à GND ou en l'air)
bool bh1750Pret = false;
#elif CAPTEUR_LUMIERE == LUMIERE_TSL2561
// L'adresse I2C dépend du module (0x29 sur le Grove Digital Light Sensor) :
// on la détecte au démarrage, puis on crée l'objet une seule fois.
Adafruit_TSL2561_Unified *tsl2561 = nullptr;
uint8_t adresseTsl2561 = 0;
bool tsl2561Pret = false;
#endif

// --- État ---------------------------------------------------------------------------
unsigned long intervalleMs = INTERVALLE_DEFAUT_S * 1000UL;
unsigned long delaiProchaineMesureMs = DELAI_PREMIERE_MESURE_MS;
unsigned long derniereMesureMs = 0;
unsigned long numeroSequence = 0;

bool alerteActive = false;
unsigned long debutAlerteMs = 0;
bool buzzerEnMarche = false;

// Réception des commandes
char ligneRecue[32];
uint8_t longueurLigne = 0;
bool ligneTropLongue = false;

// -----------------------------------------------------------------------------
void setup() {
  pinMode(PIN_LED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_LED, LOW);
  digitalWrite(PIN_BUZZER, LOW);

  Serial.begin(115200);
  Wire.begin();
  initialiserCapteurs();

  // Petit test visuel au démarrage : la LED s'allume 300 ms.
  digitalWrite(PIN_LED, HIGH);
  delay(300);
  digitalWrite(PIN_LED, LOW);

  derniereMesureMs = millis();
  Serial.print(F("{\"type\":\"demarrage\",\"version\":"));
  Serial.print(VERSION_PROTOCOLE);
  Serial.println(F("}"));
}

void loop() {
  lireCommandes();

  // millis() plutôt que delay() : la boucle n'est jamais bloquée, donc
  // l'Arduino reste à l'écoute des commandes et fait clignoter la LED
  // pendant qu'il attend la prochaine mesure.
  // La soustraction reste correcte après le débordement de millis() (49 jours).
  if (millis() - derniereMesureMs >= delaiProchaineMesureMs) {
    derniereMesureMs = millis();
    delaiProchaineMesureMs = intervalleMs;
    envoyerMesure();
  }

  gererSignalAlerte();
}

// --- Capteurs -------------------------------------------------------------------
void initialiserCapteurs() {
#if CAPTEUR_TEMPERATURE == TEMP_DHT22 || CAPTEUR_TEMPERATURE == TEMP_DHT11
  dht.begin();
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
  dht20.begin();
#endif
#if CAPTEUR_LUMIERE == LUMIERE_BH1750
  bh1750Pret = luxmetre.begin(BH1750::CONTINUOUS_HIGH_RES_MODE);
#elif CAPTEUR_LUMIERE == LUMIERE_TSL2561
  tsl2561Pret = demarrerTsl2561();
#endif
}

// Vrai si un périphérique répond à cette adresse sur le bus I2C.
bool presentSurI2c(uint8_t adresse) {
  Wire.beginTransmission(adresse);
  return Wire.endTransmission() == 0;
}

#if CAPTEUR_LUMIERE == LUMIERE_TSL2561
bool demarrerTsl2561() {
  if (tsl2561 == nullptr) {
    const uint8_t adresses[] = {TSL2561_ADDR_LOW, TSL2561_ADDR_FLOAT, TSL2561_ADDR_HIGH};
    for (uint8_t i = 0; i < 3 && tsl2561 == nullptr; i++) {
      if (presentSurI2c(adresses[i])) {
        adresseTsl2561 = adresses[i];
        tsl2561 = new Adafruit_TSL2561_Unified(adresseTsl2561);
      }
    }
  }
  if (tsl2561 == nullptr || !presentSurI2c(adresseTsl2561) || !tsl2561->begin()) {
    return false;
  }
  tsl2561->enableAutoRange(true);  // gain choisi automatiquement (ombre ou plein soleil)
  tsl2561->setIntegrationTime(TSL2561_INTEGRATIONTIME_101MS);
  return true;
}
#endif

// Chaque fonction renvoie NAN si la grandeur n'est pas mesurée ou si le
// capteur est en erreur : on n'envoie jamais une fausse valeur (par exemple 0)
// qui pourrait déclencher une alerte à tort.
void lireTemperatureHumidite(float &temperature, float &humidite) {
  temperature = NAN;
  humidite = NAN;
#if CAPTEUR_TEMPERATURE == TEMP_DHT22 || CAPTEUR_TEMPERATURE == TEMP_DHT11
  temperature = dht.readTemperature();
  humidite = dht.readHumidity();
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
  if (dht20.read() == DHT20_OK) {
    temperature = dht20.getTemperature();
    humidite = dht20.getHumidity();
  }
#elif CAPTEUR_TEMPERATURE == TEMP_THERMISTANCE
  temperature = lireThermistance();
  // Pas de capteur d'humidité : humidite reste NAN, envoyée en null.
#endif
}

#if CAPTEUR_TEMPERATURE == TEMP_THERMISTANCE
// Grove Temperature Sensor : thermistance NTC 100 kΩ dans un pont diviseur.
// Formule de Steinhart-Hart simplifiée (paramètre B), donnée par Seeed.
float lireThermistance() {
  long somme = 0;
  for (uint8_t i = 0; i < 8; i++) {  // moyenne de 8 lectures : moins de bruit
    somme += analogRead(PIN_THERMISTANCE);
    delay(2);
  }
  float valeur = somme / 8.0;
  // Valeur collée à 0 ou à 1023 : module débranché ou en court-circuit.
  if (valeur < 10 || valeur > 1013) {
    return NAN;
  }
  float rapport = 1023.0 / valeur - 1.0;  // R_thermistance / R0
  return 1.0 / (log(rapport) / THERMISTANCE_B + 1.0 / 298.15) - 273.15;
}
#endif

float lireLuminosite() {
#if CAPTEUR_LUMIERE == LUMIERE_BH1750
  // Si le BH1750 a été débranché ou n'a pas démarré, on retente à chaque mesure.
  if (!bh1750Pret) {
    bh1750Pret = luxmetre.begin(BH1750::CONTINUOUS_HIGH_RES_MODE);
  }
  if (!bh1750Pret) {
    return NAN;
  }
  float lux = luxmetre.readLightLevel();  // valeur négative si erreur I2C
  if (lux < 0) {
    bh1750Pret = false;
    return NAN;
  }
  return lux;
#elif CAPTEUR_LUMIERE == LUMIERE_ANALOGIQUE
  // Photorésistance : niveau relatif de 0 % (noir) à 100 % (pleine lumière).
  // Ce n'est PAS une mesure en lux (réponse non linéaire, non étalonnée).
  long somme = 0;
  for (uint8_t i = 0; i < 8; i++) {
    somme += analogRead(PIN_LUMIERE);
    delay(2);
  }
  return (somme / 8.0) * 100.0 / 1023.0;
#elif CAPTEUR_LUMIERE == LUMIERE_TSL2561
  // Si le capteur a été débranché, on retente à chaque mesure.
  if (!tsl2561Pret || !presentSurI2c(adresseTsl2561)) {
    tsl2561Pret = demarrerTsl2561();
  }
  if (!tsl2561Pret) {
    return NAN;
  }
  sensors_event_t evenement;
  if (!tsl2561->getEvent(&evenement)) {
    return NAN;  // capteur saturé (lumière trop forte pour le calcul)
  }
  return evenement.light;
#else
  return NAN;
#endif
}

void envoyerMesure() {
  float temperature;
  float humidite;
  lireTemperatureHumidite(temperature, humidite);
  float luminosite = lireLuminosite();

  numeroSequence++;
  Serial.print(F("{\"type\":\"mesure\",\"seq\":"));
  Serial.print(numeroSequence);
  Serial.print(F(",\"temperature\":"));
  imprimerValeur(temperature, 1);
  Serial.print(F(",\"humidite\":"));
  imprimerValeur(humidite, 1);
  Serial.print(F(",\"luminosite\":"));
  imprimerValeur(luminosite, 1);
  Serial.println(F("}"));
}

// NAN -> null en JSON.
void imprimerValeur(float valeur, uint8_t decimales) {
  if (isnan(valeur)) {
    Serial.print(F("null"));
  } else {
    Serial.print(valeur, decimales);
  }
}

// --- Commandes reçues du Pi -----------------------------------------------------
void lireCommandes() {
  while (Serial.available() > 0) {
    char c = (char)Serial.read();
    if (c == '\r') {
      continue;
    }
    if (c == '\n') {
      if (ligneTropLongue) {
        envoyerErreur(F("commande trop longue"));
      } else if (longueurLigne > 0) {
        ligneRecue[longueurLigne] = '\0';
        traiterCommande(ligneRecue);
      }
      longueurLigne = 0;
      ligneTropLongue = false;
      continue;
    }
    if (longueurLigne < sizeof(ligneRecue) - 1) {
      ligneRecue[longueurLigne++] = c;
    } else {
      ligneTropLongue = true;  // on ignore la suite jusqu'au '\n'
    }
  }
}

void traiterCommande(const char *commande) {
  if (strcmp(commande, "PING") == 0) {
    Serial.print(F("{\"type\":\"pong\",\"version\":"));
    Serial.print(VERSION_PROTOCOLE);
    Serial.print(F(",\"intervalle\":"));
    Serial.print(intervalleMs / 1000UL);
    Serial.print(F(",\"alerte\":"));
    Serial.print(alerteActive ? 1 : 0);
    Serial.println(F("}"));
  } else if (strcmp(commande, "MESURE") == 0) {
    derniereMesureMs = millis();
    delaiProchaineMesureMs = intervalleMs;
    envoyerMesure();
  } else if (strncmp(commande, "INTERVALLE ", 11) == 0) {
    long secondes = atol(commande + 11);
    if (secondes < (long)INTERVALLE_MIN_S || secondes > (long)INTERVALLE_MAX_S) {
      envoyerErreur(F("intervalle hors plage (5-3600)"));
      return;
    }
    intervalleMs = (unsigned long)secondes * 1000UL;
    delaiProchaineMesureMs = intervalleMs;
    envoyerAck(F("INTERVALLE"));
  } else if (strcmp(commande, "ALERTE 1") == 0) {
    if (!alerteActive) {
      alerteActive = true;
      debutAlerteMs = millis();
    }
    envoyerAck(F("ALERTE"));
  } else if (strcmp(commande, "ALERTE 0") == 0) {
    alerteActive = false;
    envoyerAck(F("ALERTE"));
  } else {
    envoyerErreur(F("commande inconnue"));
  }
}

void envoyerAck(const __FlashStringHelper *commande) {
  Serial.print(F("{\"type\":\"ack\",\"commande\":\""));
  Serial.print(commande);
  Serial.println(F("\"}"));
}

void envoyerErreur(const __FlashStringHelper *message) {
  Serial.print(F("{\"type\":\"erreur\",\"message\":\""));
  Serial.print(message);
  Serial.println(F("\"}"));
}

// --- Signal local (LED + buzzer) ---------------------------------------------------
void gererSignalAlerte() {
  if (!alerteActive) {
    digitalWrite(PIN_LED, LOW);
    commanderBuzzer(false);
    return;
  }
  unsigned long ecoule = millis() - debutAlerteMs;
  bool ledAllumee = ((ecoule / DEMI_PERIODE_LED_MS) % 2) == 0;
  digitalWrite(PIN_LED, ledAllumee ? HIGH : LOW);
  commanderBuzzer((ecoule % PERIODE_BIP_MS) < DUREE_BIP_MS);
}

void commanderBuzzer(bool enMarche) {
  if (enMarche == buzzerEnMarche) {
    return;  // pas de changement : on évite de rappeler tone() en boucle
  }
  buzzerEnMarche = enMarche;
  if (BUZZER_ACTIF) {
    digitalWrite(PIN_BUZZER, enMarche ? HIGH : LOW);
  } else if (enMarche) {
    tone(PIN_BUZZER, FREQUENCE_BUZZER_HZ);
  } else {
    noTone(PIN_BUZZER);
  }
}
