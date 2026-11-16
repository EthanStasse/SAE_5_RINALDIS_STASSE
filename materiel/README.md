# Matériel — module « surveillance de serre connectée »

| Dossier / fichier | Contenu |
|---|---|
| [`GUIDE_MONTAGE.md`](GUIDE_MONTAGE.md) | **Solution principale** : guide de montage ESP32 (EF-C-15) |
| [`esp32/capteur_serre_esp32/`](esp32/capteur_serre_esp32/) | Programme de l'ESP32 (mesure + envoi direct à l'API en Wi-Fi) |
| [`GUIDE_MONTAGE_ARDUINO_PI.md`](GUIDE_MONTAGE_ARDUINO_PI.md) | Solution de secours : Arduino + Raspberry Pi |
| [`arduino/capteurs_serre/`](arduino/capteurs_serre/) | Programme Arduino de la solution de secours |
| [`passerelle/`](passerelle/) | Passerelle Raspberry Pi de la solution de secours |
| [`CABLAGE.md`](CABLAGE.md) | Câblage détaillé de la solution de secours (Arduino) |

Les deux solutions envoient exactement le même message à la même API :

```
POST /api/v1/mesures
Authorization: Bearer <jeton du boîtier>

{"uuid": "...", "mesure_le": "2026-11-16T20:45:12Z", "temperature": 21.4, "humidite": 48.7, "luminosite": 312.5}
```

Côté Laravel, rien ne dépend du matériel utilisé.
