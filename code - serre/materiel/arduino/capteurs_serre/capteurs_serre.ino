/*
 * Boîtier capteur de serre — module « surveillance de serre connectée »
 *
 * Rôle : lire le DHT22 (température, humidité de l'air) et le BH1750
 * (luminosité), envoyer une ligne JSON par mesure sur le port série USB,
 * et recevoir des commandes texte de la passerelle (Raspberry Pi).
 *
 * Compatible Arduino Uno et Arduino Leonardo (aucune modification).
 *
 * ---------------------------------------------------------------------------
 * PROTOCOLE SÉRIE — 115200 bauds, 8N1, une ligne terminée par '\n'
 * ---------------------------------------------------------------------------
 * Arduino -> Pi (toujours du JSON, un objet par ligne)
 *   {"type":"demarrage","version":1}
 *   {"type":"mesure","seq":12,"temperature":21.4,"humidite":55.2,"luminosite":340.0}
 *        -> un champ vaut null si le capteur correspondant est en erreur
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
 * Bibliothèques (Gestionnaire de bibliothèques de l'IDE Arduino) :
 *   - « DHT sensor library » (Adafruit) + « Adafruit Unified Sensor »
 *   - « BH1750 » (Christopher Laws)
 */

#include <Wire.h>
#include <DHT.h>
#include <BH1750.h>

// --- Broches ----------------------------------------------------------------
// D4 pour le DHT22 : D2/D3 sont les broches I2C du Leonardo (SDA/SCL).
// Sur l'Uno, l'I2C est sur A4/A5. Le BH1750 n'a donc pas de broche à déclarer.
const uint8_t PIN_DHT = 4;
const uint8_t PIN_BUZZER = 8;
const uint8_t PIN_LED = 9;

// true  = buzzer ACTIF  (sonne dès qu'on lui met 5 V)
// false = buzzer PASSIF (il faut lui envoyer une fréquence avec tone())
// À vérifier ce soir : si en 5 V continu tu n'entends qu'un « clic », il est passif.
const bool BUZZER_ACTIF = true;
const unsigned int FREQUENCE_BUZZER_HZ = 2000;  // utilisé seulement si passif

// --- Paramètres ---------------------------------------------------------------
const uint8_t VERSION_PROTOCOLE = 1;
const unsigned long INTERVALLE_DEFAUT_S = 300;  // EF-C-03 : 5 minutes par défaut
const unsigned long INTERVALLE_MIN_S = 5;       // le DHT22 exige au moins 2 s
const unsigned long INTERVALLE_MAX_S = 3600;
const unsigned long DELAI_PREMIERE_MESURE_MS = 2500;  // préchauffage du DHT22

// Signal d'alerte local : LED qui clignote, bip court toutes les 10 s
// (un buzzer continu serait insupportable pour les salariés en serre).
const unsigned long DEMI_PERIODE_LED_MS = 500;
const unsigned long PERIODE_BIP_MS = 10000;
const unsigned long DUREE_BIP_MS = 200;

// --- Objets et état -----------------------------------------------------------
DHT dht(PIN_DHT, DHT22);
BH1750 luxmetre;  // adresse 0x23 par défaut (broche ADDR à GND ou en l'air)

bool bh1750Pret = false;
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
  dht.begin();
  bh1750Pret = luxmetre.begin(BH1750::CONTINUOUS_HIGH_RES_MODE);

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

// --- Mesures ------------------------------------------------------------------
void envoyerMesure() {
  float temperature = dht.readTemperature();  // °C, NAN si erreur
  float humidite = dht.readHumidity();        // %, NAN si erreur

  // Si le BH1750 a été débranché ou n'a pas démarré, on retente à chaque mesure.
  if (!bh1750Pret) {
    bh1750Pret = luxmetre.begin(BH1750::CONTINUOUS_HIGH_RES_MODE);
  }
  float luminosite = NAN;
  if (bh1750Pret) {
    float lux = luxmetre.readLightLevel();  // valeur négative si erreur I2C
    if (lux >= 0) {
      luminosite = lux;
    } else {
      bh1750Pret = false;
    }
  }

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

// Un capteur en erreur donne NAN : on écrit null en JSON. On n'envoie jamais
// une fausse valeur (par exemple 0) qui pourrait déclencher une alerte à tort.
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
