# Passerelle Raspberry Pi : Arduino (USB) → API Laravel (HTTPS)

## Dépendances (Raspberry Pi OS Trixie)

Python 3.13 est fourni avec le système. Les deux bibliothèques s'installent par apt, sans pip ni environnement virtuel :

```bash
sudo apt update
sudo apt install -y python3-serial python3-requests sqlite3
```

## Test manuel (sans Laravel)

```bash
sudo usermod -aG dialout $USER   # accès aux ports série, puis se déconnecter/reconnecter
ls -l /dev/serial/by-id/          # repérer l'Arduino

# Terminal 1 : fausse API (alerte si T > 26 °C)
python3 outils/faux_api.py --port 8000 --jeton test123 --seuil-temperature 26

# Terminal 2 : passerelle (corriger le port dans le fichier si besoin)
python3 passerelle.py --config outils/config.test-local.toml -v
```

## Tests automatisés

```bash
python3 -m unittest discover -s tests -v
```

## Installation en service

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin passerelle
sudo mkdir -p /opt/passerelle-serre /etc/passerelle-serre
sudo cp passerelle.py protocole.py tampon.py client_api.py liaison_serie.py /opt/passerelle-serre/

# Configuration : contient les jetons, donc lisible uniquement par root et le service
sudo cp config.exemple.toml /etc/passerelle-serre/config.toml
sudo chown root:passerelle /etc/passerelle-serre/config.toml
sudo chmod 640 /etc/passerelle-serre/config.toml
sudo nano /etc/passerelle-serre/config.toml

sudo cp passerelle-serre.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now passerelle-serre
journalctl -u passerelle-serre -f       # suivre les journaux
```

## Vérifications utiles

```bash
timedatectl                       # « System clock synchronized: yes » (dates UTC justes)
sqlite3 /var/lib/passerelle-serre/tampon.sqlite3 "select count(*) from mesures_en_attente"
systemctl status passerelle-serre
```

## Dépannage

| Symptôme | Cause probable |
|---|---|
| `Permission denied: /dev/ttyACM0` | l'utilisateur n'est pas dans le groupe `dialout` |
| `could not exclusively lock port` | l'IDE Arduino ou un autre programme utilise déjà le port (moniteur série ouvert ?) |
| la 1re mesure de l'Uno n'apparaît pas | normal : l'Uno redémarre à l'ouverture du port et les octets reçus pendant l'attente sont jetés |
| déconnexions USB répétées | alimentation du Pi insuffisante (`vcgencmd get_throttled`) |
| port ttyUSB qui disparaît (clone CH340) | `sudo apt remove brltty` |
