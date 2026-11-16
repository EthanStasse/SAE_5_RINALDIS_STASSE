#include <DHT.h>

const uint8_t PIN_DHT = 4;                  // D4 : laisse D2/D3 libres (I2C du Leonardo)
const unsigned long INTERVALLE_MS = 2500;   // DHT22 : 1 lecture max toutes les 2 s

DHT dht(PIN_DHT, DHT22);
unsigned long derniereLecture = 0;

void setup() {
  Serial.begin(115200);
  dht.begin();
}

void loop() {
  // millis() plutôt que delay() : la boucle reste libre pour, plus tard,
  // écouter des commandes venant du Pi (LED, buzzer).
  if (millis() - derniereLecture < INTERVALLE_MS) {
    return;
  }
  derniereLecture = millis();

  float temperature = dht.readTemperature();  // en °C
  float humidite = dht.readHumidity();        // en %

  if (isnan(temperature) || isnan(humidite)) {
    Serial.println(F("ERREUR lecture DHT22"));
    return;
  }

  Serial.print(F("T="));
  Serial.print(temperature, 1);
  Serial.print(F(" C  H="));
  Serial.print(humidite, 1);
  Serial.println(F(" %"));
}