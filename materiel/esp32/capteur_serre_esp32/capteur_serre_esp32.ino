/*
 * Boîtier capteur de serre connecté — ESP32 (module « surveillance de serre »)
 *
 * L'ESP32 mesure la température, l'humidité de l'air et la luminosité, puis
 * envoie chaque mesure directement à l'API Laravel en Wi-Fi, avec le jeton
 * du boîtier (EF-C-03, EF-C-04). Pas de passerelle : c'est l'architecture de
 * la proposition technique.
 *
 * Carte : ESP32 DevKit / DevKitC (WROOM-32) — dans l'IDE : « ESP32 Dev Module ».
 *
 * Fonctionnement :
 *   1. toutes les INTERVALLE_MESURE_S secondes, une mesure est lue et rangée
 *      dans un tampon en mémoire, avec un identifiant unique (uuid) ;
 *   2. dès que le Wi-Fi et l'heure (NTP) sont disponibles, les mesures du
 *      tampon partent une par une vers POST /api/v1/mesures, la plus ancienne
 *      d'abord. Une mesure n'est retirée du tampon que si l'API l'a acceptée ;
 *   3. la réponse de l'API indique si une alerte est active : la LED clignote
 *      et le buzzer bipe.
 *
 * Commandes au Moniteur série (115200 bauds, « Nouvelle ligne ») :
 *   ETAT                  -> Wi-Fi, heure, mesures en attente, alerte
 *   MESURE                -> mesure immédiate
 *   INTERVALLE <secondes> -> change la période de mesure (5 à 3600 s)
 *   ALERTE <0|1>          -> teste le signal local (LED + buzzer)
 *
 * Bibliothèques (Outils -> Gérer les bibliothèques) :
 *   - « DHT sensor library » (Adafruit) + « Adafruit Unified Sensor »
 *   - « Adafruit TSL2561 »
 *   - « DHT20 » (Rob Tillaart), seulement pour un capteur V2.0
 */

#include <WiFi.h>
#include <WiFiClient.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <time.h>
#include <esp_random.h>
#include <Adafruit_TSL2561_U.h>

#include "secrets.h"  // copier secrets.exemple.h sous ce nom et le remplir

// ===========================================================================
//  CONFIGURATION DU CAPTEUR DE TEMPÉRATURE ET D'HUMIDITÉ
// ===========================================================================
#define TEMP_DHT22 1  // Grove - Temperature & Humidity Sensor Pro (blanc) : fil jaune sur GPIO 4
#define TEMP_DHT20 2  // Grove - Temperature & Humidity Sensor V2.0 : sur le bus I2C
#define CAPTEUR_TEMPERATURE TEMP_DHT22

#if CAPTEUR_TEMPERATURE == TEMP_DHT22
#include <DHT.h>
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
#include <DHT20.h>
#endif

// --- Broches (ESP32 DevKit) -----------------------------------------------------
// On évite les broches de démarrage (GPIO 0, 2, 5, 12, 15) et les broches
// en entrée seule (GPIO 34 à 39).
const uint8_t PIN_DHT = 4;     // Grove Temp & Humi Pro : fil jaune
const uint8_t PIN_SDA = 21;    // bus I2C : fil blanc des modules I2C
const uint8_t PIN_SCL = 22;    // bus I2C : fil jaune des modules I2C
const uint8_t PIN_LED = 25;    // Grove LED : fil jaune
const uint8_t PIN_BUZZER = 26; // Grove Buzzer : fil jaune

// --- Paramètres ---------------------------------------------------------------------
const unsigned long INTERVALLE_MIN_S = 5;
const unsigned long INTERVALLE_MAX_S = 3600;
const unsigned long DELAI_PREMIERE_MESURE_MS = 3000;  // préchauffage des capteurs
const unsigned long DELAI_RECONNEXION_WIFI_MS = 30000;
const unsigned long ATTENTE_ENVOI_MIN_MS = 5000;
const unsigned long ATTENTE_ENVOI_MAX_MS = 300000;    // recul exponentiel plafonné à 5 min
const unsigned long SUSPENSION_JETON_MS = 300000;     // jeton refusé : nouvel essai dans 5 min
const uint16_t DELAI_HTTP_MS = 10000;
const time_t HEURE_VALIDE_APRES = 1700000000;         // une heure avant 2023 = horloge pas réglée

// Tampon en mémoire : 288 mesures = 24 h à raison d'une mesure toutes les 5 min.
// Il est perdu si l'ESP32 redémarre (limite connue, voir le guide).
const size_t CAPACITE_TAMPON = 288;

// Signal d'alerte local : LED qui clignote, bip court toutes les 10 s.
const unsigned long DEMI_PERIODE_LED_MS = 500;
const unsigned long PERIODE_BIP_MS = 10000;
const unsigned long DUREE_BIP_MS = 200;

// --- Capteurs ---------------------------------------------------------------------
#if CAPTEUR_TEMPERATURE == TEMP_DHT22
DHT dht(PIN_DHT, DHT22);
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
DHT20 dht20(&Wire);
#endif

// L'adresse I2C du TSL2561 dépend du module (0x29 sur le Grove Digital Light
// Sensor) : on la détecte, puis on crée l'objet une seule fois.
Adafruit_TSL2561_Unified *tsl2561 = nullptr;
uint8_t adresseTsl2561 = 0;
bool tsl2561Pret = false;

// --- Tampon des mesures à envoyer ----------------------------------------------------
struct Mesure {
  char uuid[37];
  unsigned long millisMesure;  // instant de la mesure (horloge interne)
  time_t horodatage;           // instant UTC ; 0 tant que l'heure n'est pas connue
  float temperature;
  float humidite;
  float luminosite;
};

Mesure tampon[CAPACITE_TAMPON];
size_t debutTampon = 0;
size_t nombreEnAttente = 0;

// --- État ---------------------------------------------------------------------------
unsigned long intervalleMs = (unsigned long)INTERVALLE_MESURE_S * 1000UL;
unsigned long delaiProchaineMesureMs = DELAI_PREMIERE_MESURE_MS;
unsigned long derniereMesureMs = 0;

unsigned long dernierEssaiWifiMs = 0;
bool wifiConnecte = false;

unsigned long dernierEnvoiMs = 0;
unsigned long attenteEnvoiMs = 0;
bool envoiAutorise = true;  // faux si la configuration est invalide

bool alerteActive = false;
unsigned long debutAlerteMs = 0;
bool buzzerEnMarche = false;

String ligneRecue;

// --- Déclarations des fonctions (définies plus bas) -------------------------------------
void verifierConfiguration();
void connecterWifi();
void surveillerWifi();
bool heureConnue();
void initialiserCapteurs();
bool presentSurI2c(uint8_t adresse);
bool demarrerTsl2561();
void lireTemperatureHumidite(float &temperature, float &humidite);
float lireLuminosite();
void genererUuid(char *sortie);
void mesurer();
void retirerPlusAncienne();
void formaterValeur(char *sortie, size_t taille, float valeur);
void envoyerPlusAncienne();
void lireCommandesSerie();
void traiterCommande(const String &commande);
void definirAlerte(bool active);
void gererSignalAlerte();
void commanderBuzzer(bool enMarche);

// =============================================================================
void setup() {
  pinMode(PIN_LED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_LED, LOW);
  digitalWrite(PIN_BUZZER, LOW);

  Serial.begin(115200);
  delay(500);
  Serial.println();
  Serial.println(F("=== Boîtier capteur de serre (ESP32) ==="));

  Wire.begin(PIN_SDA, PIN_SCL);
  initialiserCapteurs();
  verifierConfiguration();

  // Heure UTC par NTP (EF-C-04 : les mesures sont datées en UTC).
  configTime(0, 0, "pool.ntp.org", "time.google.com");

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  connecterWifi();

  // Petit test visuel au démarrage : la LED s'allume 300 ms.
  digitalWrite(PIN_LED, HIGH);
  delay(300);
  digitalWrite(PIN_LED, LOW);

  derniereMesureMs = millis();
}

void loop() {
  lireCommandesSerie();
  surveillerWifi();

  // millis() plutôt que delay() : la boucle n'est jamais bloquée (sauf
  // pendant un envoi HTTP), la LED clignote et les commandes sont lues.
  if (millis() - derniereMesureMs >= delaiProchaineMesureMs) {
    derniereMesureMs = millis();
    delaiProchaineMesureMs = intervalleMs;
    mesurer();
  }

  if (nombreEnAttente > 0 && millis() - dernierEnvoiMs >= attenteEnvoiMs) {
    envoyerPlusAncienne();
  }

  gererSignalAlerte();
}

// --- Configuration ------------------------------------------------------------------
void verifierConfiguration() {
  bool https = strncmp(API_URL, "https://", 8) == 0;
  bool http = strncmp(API_URL, "http://", 7) == 0;

  if (!https && !http) {
    Serial.println(F("ERREUR config : API_URL doit commencer par https://"));
    envoiAutorise = false;
  }
#ifndef AUTORISER_HTTP_POUR_TESTS
  if (http) {
    Serial.println(F("ERREUR config : HTTP refusé (EF-C-04). Utiliser https:// ou AUTORISER_HTTP_POUR_TESTS."));
    envoiAutorise = false;
  }
#else
  if (http) {
    Serial.println(F("ATTENTION : envoi en HTTP non chiffré (mode test)"));
  }
#endif
  if (https && strlen(API_CERTIFICAT_CA) == 0) {
    Serial.println(F("ERREUR config : API_CERTIFICAT_CA est vide (obligatoire en HTTPS)."));
    envoiAutorise = false;
  }
  if (INTERVALLE_MESURE_S < INTERVALLE_MIN_S || INTERVALLE_MESURE_S > INTERVALLE_MAX_S) {
    Serial.println(F("ERREUR config : INTERVALLE_MESURE_S doit être entre 5 et 3600. 300 s utilisées."));
    intervalleMs = 300000UL;
  }
  Serial.printf("API : %s — mesure toutes les %lu s\n", API_URL, intervalleMs / 1000UL);
}

// --- Wi-Fi ------------------------------------------------------------------------------
void connecterWifi() {
  Serial.printf("Wi-Fi : connexion à « %s »...\n", WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_MOT_DE_PASSE);
  dernierEssaiWifiMs = millis();
}

void surveillerWifi() {
  bool connecte = WiFi.status() == WL_CONNECTED;
  if (connecte != wifiConnecte) {
    wifiConnecte = connecte;
    if (connecte) {
      Serial.printf("Wi-Fi : connecté, adresse %s\n", WiFi.localIP().toString().c_str());
      attenteEnvoiMs = 0;  // on vide le tampon sans attendre
    } else {
      Serial.println(F("Wi-Fi : connexion perdue, les mesures sont gardées en mémoire"));
    }
  }
  if (!connecte && millis() - dernierEssaiWifiMs >= DELAI_RECONNEXION_WIFI_MS) {
    WiFi.disconnect();
    connecterWifi();
  }
}

bool heureConnue() {
  return time(nullptr) > HEURE_VALIDE_APRES;
}

// --- Capteurs ---------------------------------------------------------------------------
void initialiserCapteurs() {
#if CAPTEUR_TEMPERATURE == TEMP_DHT22
  dht.begin();
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
  dht20.begin();
#endif
  tsl2561Pret = demarrerTsl2561();
  Serial.println(tsl2561Pret ? F("Capteur de lumière : OK") : F("Capteur de lumière : introuvable sur le bus I2C"));
}

bool presentSurI2c(uint8_t adresse) {
  Wire.beginTransmission(adresse);
  return Wire.endTransmission() == 0;
}

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
  if (tsl2561 == nullptr || !presentSurI2c(adresseTsl2561) || !tsl2561->begin(&Wire)) {
    return false;
  }
  tsl2561->enableAutoRange(true);  // gain choisi automatiquement (ombre ou plein soleil)
  tsl2561->setIntegrationTime(TSL2561_INTEGRATIONTIME_101MS);
  return true;
}

// NAN = grandeur non mesurée (capteur débranché ou en erreur) : envoyée en null.
// On n'envoie jamais une fausse valeur qui pourrait déclencher une alerte à tort.
void lireTemperatureHumidite(float &temperature, float &humidite) {
  temperature = NAN;
  humidite = NAN;
#if CAPTEUR_TEMPERATURE == TEMP_DHT22
  temperature = dht.readTemperature();
  humidite = dht.readHumidity();
#elif CAPTEUR_TEMPERATURE == TEMP_DHT20
  if (dht20.read() == DHT20_OK) {
    temperature = dht20.getTemperature();
    humidite = dht20.getHumidity();
  }
#endif
}

float lireLuminosite() {
  if (!tsl2561Pret || !presentSurI2c(adresseTsl2561)) {
    tsl2561Pret = demarrerTsl2561();  // capteur rebranché ?
  }
  if (!tsl2561Pret) {
    return NAN;
  }
  sensors_event_t evenement;
  if (!tsl2561->getEvent(&evenement)) {
    return NAN;  // capteur saturé (lumière trop forte pour le calcul)
  }
  return evenement.light;
}

// --- Mesure et tampon --------------------------------------------------------------------
void genererUuid(char *sortie) {
  // UUID version 4 : 122 bits aléatoires (générateur matériel de l'ESP32).
  uint8_t o[16];
  for (uint8_t i = 0; i < 16; i += 4) {
    uint32_t aleatoire = esp_random();
    memcpy(o + i, &aleatoire, 4);
  }
  o[6] = (o[6] & 0x0F) | 0x40;
  o[8] = (o[8] & 0x3F) | 0x80;
  snprintf(sortie, 37, "%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x",
           o[0], o[1], o[2], o[3], o[4], o[5], o[6], o[7],
           o[8], o[9], o[10], o[11], o[12], o[13], o[14], o[15]);
}

void mesurer() {
  Mesure m;
  lireTemperatureHumidite(m.temperature, m.humidite);
  m.luminosite = lireLuminosite();
  m.millisMesure = millis();
  m.horodatage = heureConnue() ? time(nullptr) : 0;
  genererUuid(m.uuid);

  Serial.printf("Mesure : T=%.1f °C  H=%.1f %%  L=%.1f lx\n", m.temperature, m.humidite, m.luminosite);

  if (isnan(m.temperature) && isnan(m.humidite) && isnan(m.luminosite)) {
    Serial.println(F("  -> aucun capteur n'a répondu, mesure ignorée"));
    return;
  }

  if (nombreEnAttente == CAPACITE_TAMPON) {
    // Tampon plein : on sacrifie la plus ancienne mesure.
    debutTampon = (debutTampon + 1) % CAPACITE_TAMPON;
    nombreEnAttente--;
    Serial.println(F("  -> tampon plein : la plus ancienne mesure est perdue"));
  }
  tampon[(debutTampon + nombreEnAttente) % CAPACITE_TAMPON] = m;
  nombreEnAttente++;
  // L'envoi est déclenché par loop() ; en cas de panne, on garde le recul en cours.
}

void retirerPlusAncienne() {
  debutTampon = (debutTampon + 1) % CAPACITE_TAMPON;
  nombreEnAttente--;
}

// --- Envoi à l'API ----------------------------------------------------------------------------
void formaterValeur(char *sortie, size_t taille, float valeur) {
  if (isnan(valeur)) {
    snprintf(sortie, taille, "null");
  } else {
    snprintf(sortie, taille, "%.1f", valeur);
  }
}

void envoyerPlusAncienne() {
  dernierEnvoiMs = millis();
  if (!envoiAutorise || !wifiConnecte) {
    attenteEnvoiMs = ATTENTE_ENVOI_MIN_MS;
    return;
  }
  if (!heureConnue()) {
    Serial.println(F("Envoi : en attente de l'heure (NTP)"));
    attenteEnvoiMs = ATTENTE_ENVOI_MIN_MS;
    return;
  }

  Mesure &m = tampon[debutTampon];
  if (m.horodatage == 0) {
    // Mesure prise avant la synchronisation NTP : on recalcule son heure
    // à partir du temps écoulé depuis la mesure.
    m.horodatage = time(nullptr) - (time_t)((millis() - m.millisMesure) / 1000UL);
  }

  char date[25];
  struct tm utc;
  gmtime_r(&m.horodatage, &utc);
  strftime(date, sizeof(date), "%Y-%m-%dT%H:%M:%SZ", &utc);

  char t[12], h[12], l[12];
  formaterValeur(t, sizeof(t), m.temperature);
  formaterValeur(h, sizeof(h), m.humidite);
  formaterValeur(l, sizeof(l), m.luminosite);

  char corps[200];
  snprintf(corps, sizeof(corps),
           "{\"uuid\":\"%s\",\"mesure_le\":\"%s\",\"temperature\":%s,\"humidite\":%s,\"luminosite\":%s}",
           m.uuid, date, t, h, l);

  int code = 0;
  String reponse;
  bool https = strncmp(API_URL, "https://", 8) == 0;
  HTTPClient http;
  WiFiClient clientHttp;
  WiFiClientSecure clientHttps;

  if (https) {
    clientHttps.setCACert(API_CERTIFICAT_CA);
    http.begin(clientHttps, API_URL);
  } else {
    http.begin(clientHttp, API_URL);
  }
  http.setTimeout(DELAI_HTTP_MS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Authorization", String("Bearer ") + JETON_BOITIER);
  code = http.POST((uint8_t *)corps, strlen(corps));
  if (code > 0) {
    reponse = http.getString();
  }
  http.end();

  if (code >= 200 && code < 300) {
    Serial.printf("Envoi : mesure du %s acceptée (HTTP %d), %u en attente\n", date, code, (unsigned)(nombreEnAttente - 1));
    retirerPlusAncienne();
    attenteEnvoiMs = 0;
    if (reponse.indexOf("\"alerte_active\":true") >= 0) {
      definirAlerte(true);
    } else if (reponse.indexOf("\"alerte_active\":false") >= 0) {
      definirAlerte(false);
    }
  } else if (code == 401 || code == 403) {
    // Jeton révoqué ou incorrect : on garde les mesures, on réessaie plus tard.
    Serial.printf("Envoi : jeton refusé (HTTP %d), révoqué ou incorrect ? Nouvel essai dans 5 min\n", code);
    attenteEnvoiMs = SUSPENSION_JETON_MS;
  } else if (code == 408 || code == 429 || code >= 500 || code <= 0) {
    attenteEnvoiMs = attenteEnvoiMs < ATTENTE_ENVOI_MIN_MS ? ATTENTE_ENVOI_MIN_MS : min(attenteEnvoiMs * 2, ATTENTE_ENVOI_MAX_MS);
    Serial.printf("Envoi : API injoignable (%s), %u en attente, nouvel essai dans %lu s\n",
                  code <= 0 ? http.errorToString(code).c_str() : String(code).c_str(),
                  (unsigned)nombreEnAttente, attenteEnvoiMs / 1000UL);
  } else {
    // 400, 404, 422... : renvoyer la même mesure donnerait la même erreur.
    Serial.printf("Envoi : mesure refusée par l'API (HTTP %d) : %s\n", code, reponse.c_str());
    retirerPlusAncienne();
    attenteEnvoiMs = 0;
  }
}

// --- Commandes au Moniteur série ------------------------------------------------------------
void lireCommandesSerie() {
  while (Serial.available() > 0) {
    char c = (char)Serial.read();
    if (c == '\r') {
      continue;
    }
    if (c == '\n') {
      ligneRecue.trim();
      if (ligneRecue.length() > 0) {
        traiterCommande(ligneRecue);
      }
      ligneRecue = "";
    } else if (ligneRecue.length() < 40) {
      ligneRecue += c;
    }
  }
}

void traiterCommande(const String &commande) {
  if (commande == "ETAT") {
    char heure[25] = "inconnue";
    if (heureConnue()) {
      time_t maintenant = time(nullptr);
      struct tm utc;
      gmtime_r(&maintenant, &utc);
      strftime(heure, sizeof(heure), "%Y-%m-%dT%H:%M:%SZ", &utc);
    }
    Serial.printf("Wi-Fi : %s (%s)\n", wifiConnecte ? "connecté" : "déconnecté", WiFi.localIP().toString().c_str());
    Serial.printf("Heure UTC : %s\n", heure);
    Serial.printf("Mesures en attente : %u / %u\n", (unsigned)nombreEnAttente, (unsigned)CAPACITE_TAMPON);
    Serial.printf("Intervalle : %lu s — alerte : %s\n", intervalleMs / 1000UL, alerteActive ? "oui" : "non");
  } else if (commande == "MESURE") {
    derniereMesureMs = millis();
    delaiProchaineMesureMs = intervalleMs;
    mesurer();
  } else if (commande.startsWith("INTERVALLE ")) {
    long secondes = commande.substring(11).toInt();
    if (secondes < (long)INTERVALLE_MIN_S || secondes > (long)INTERVALLE_MAX_S) {
      Serial.println(F("Erreur : intervalle hors plage (5-3600)"));
      return;
    }
    intervalleMs = (unsigned long)secondes * 1000UL;
    delaiProchaineMesureMs = intervalleMs;
    Serial.printf("Intervalle : %ld s\n", secondes);
  } else if (commande == "ALERTE 1" || commande == "ALERTE 0") {
    definirAlerte(commande == "ALERTE 1");
  } else {
    Serial.println(F("Commandes : ETAT, MESURE, INTERVALLE <s>, ALERTE <0|1>"));
  }
}

// --- Signal local (LED + buzzer) -------------------------------------------------------------
void definirAlerte(bool active) {
  if (active && !alerteActive) {
    debutAlerteMs = millis();
    Serial.println(F("Alerte : ACTIVE (LED + buzzer)"));
  } else if (!active && alerteActive) {
    Serial.println(F("Alerte : terminée"));
  }
  alerteActive = active;
}

void gererSignalAlerte() {
  if (!alerteActive) {
    digitalWrite(PIN_LED, LOW);
    commanderBuzzer(false);
    return;
  }
  unsigned long ecoule = millis() - debutAlerteMs;
  digitalWrite(PIN_LED, ((ecoule / DEMI_PERIODE_LED_MS) % 2) == 0 ? HIGH : LOW);
  commanderBuzzer((ecoule % PERIODE_BIP_MS) < DUREE_BIP_MS);
}

void commanderBuzzer(bool enMarche) {
  if (enMarche != buzzerEnMarche) {
    buzzerEnMarche = enMarche;
    digitalWrite(PIN_BUZZER, enMarche ? HIGH : LOW);  // Grove Buzzer : buzzer actif
  }
}
