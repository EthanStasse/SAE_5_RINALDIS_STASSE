// MODÈLE de configuration — ce fichier-ci est versionné dans Git.
//
// 1. Copier ce fichier sous le nom « secrets.h », dans le même dossier.
// 2. Remplir les valeurs ci-dessous.
// « secrets.h » contient le mot de passe Wi-Fi et le jeton du boîtier :
// il est ignoré par Git (voir .gitignore) et ne doit JAMAIS être commité.

#pragma once

// --- Wi-Fi -----------------------------------------------------------------------
#define WIFI_SSID         "NOM_DU_RESEAU"
#define WIFI_MOT_DE_PASSE "MOT_DE_PASSE_WIFI"

// --- API Laravel -------------------------------------------------------------------
// Recette : "https://<serveur-de-recette>/api/v1/mesures"
// Test sur le PC du développeur : "http://<ip-du-pc>:8080/api/v1/mesures"
#define API_URL "http://192.168.1.42:8080/api/v1/mesures"

// Jeton affiché UNE seule fois par l'application à la création du boîtier (EF-C-02).
#define JETON_BOITIER "REMPLACER_PAR_LE_JETON_DU_BOITIER"

// EF-C-04 impose HTTPS. Le HTTP n'est accepté que si cette ligne est décommentée,
// pour tester sur le réseau local avec le PC du développeur.
// #define AUTORISER_HTTP_POUR_TESTS

// Certificat de l'autorité qui a signé le certificat HTTPS du serveur de recette
// (format PEM), pour que l'ESP32 vérifie qu'il parle bien au vrai serveur.
// Laisser vide en HTTP. Pour un certificat Let's Encrypt, coller le contenu de
// https://letsencrypt.org/certs/isrgrootx1.pem entre R"PEM( et )PEM" :
//
// static const char API_CERTIFICAT_CA[] = R"PEM(
// -----BEGIN CERTIFICATE-----
// MIIFazCCA1OgAwIBAgIRAIIQz7DSQONZRGPgu2OCiwAwDQYJKoZIhvcNAQELBQAw
// ... (toutes les lignes du fichier) ...
// -----END CERTIFICATE-----
// )PEM";
static const char API_CERTIFICAT_CA[] = "";

// --- Mesures -----------------------------------------------------------------------
// EF-C-03 : toutes les 5 minutes par défaut. Mettre 10 pour les tests.
#define INTERVALLE_MESURE_S 300
