import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import protocole  # noqa: E402
from protocole import LigneInvalide  # noqa: E402


class AnalyseLigneTest(unittest.TestCase):
    def test_mesure_complete(self):
        m = protocole.analyser_ligne(
            '{"type":"mesure","seq":3,"temperature":21.4,"humidite":55.2,"luminosite":340.0}\r\n'
        )
        self.assertEqual(m["seq"], 3)
        self.assertEqual(
            protocole.extraire_grandeurs(m),
            {"temperature": 21.4, "humidite": 55.2, "luminosite": 340.0},
        )

    def test_capteur_en_erreur_donne_null(self):
        m = protocole.analyser_ligne(
            '{"type":"mesure","seq":1,"temperature":null,"humidite":null,"luminosite":12.5}'
        )
        self.assertIsNone(m["temperature"])

    def test_tous_capteurs_en_erreur_refuse(self):
        with self.assertRaises(LigneInvalide):
            protocole.analyser_ligne(
                '{"type":"mesure","seq":1,"temperature":null,"humidite":null,"luminosite":null}'
            )

    def test_champ_manquant_refuse(self):
        with self.assertRaises(LigneInvalide):
            protocole.analyser_ligne('{"type":"mesure","seq":1,"temperature":20}')

    def test_valeur_non_numerique_refusee(self):
        with self.assertRaises(LigneInvalide):
            protocole.analyser_ligne(
                '{"type":"mesure","seq":1,"temperature":"20","humidite":50,"luminosite":1}'
            )

    def test_booleen_refuse(self):
        with self.assertRaises(LigneInvalide):
            protocole.analyser_ligne(
                '{"type":"mesure","seq":1,"temperature":true,"humidite":50,"luminosite":1}'
            )

    def test_seq_invalide_refuse(self):
        with self.assertRaises(LigneInvalide):
            protocole.analyser_ligne(
                '{"type":"mesure","seq":0,"temperature":20,"humidite":50,"luminosite":1}'
            )

    def test_octets_parasites_refuses(self):
        for ligne in ["", "   ", "\x00\xff{", "T=21.3 C", "[1,2]", '{"type":"inconnu"}']:
            with self.subTest(ligne=ligne), self.assertRaises(LigneInvalide):
                protocole.analyser_ligne(ligne)

    def test_messages_de_service(self):
        self.assertEqual(
            protocole.analyser_ligne('{"type":"demarrage","version":1}')["type"],
            "demarrage",
        )
        self.assertEqual(
            protocole.analyser_ligne('{"type":"pong","version":1,"intervalle":300,"alerte":0}')[
                "intervalle"
            ],
            300,
        )


class CommandesTest(unittest.TestCase):
    def test_commandes(self):
        self.assertEqual(protocole.commande_intervalle(300), "INTERVALLE 300\n")
        self.assertEqual(protocole.commande_alerte(True), "ALERTE 1\n")
        self.assertEqual(protocole.commande_alerte(False), "ALERTE 0\n")

    def test_intervalle_hors_plage(self):
        for valeur in (0, 4, 3601):
            with self.subTest(valeur=valeur), self.assertRaises(ValueError):
                protocole.commande_intervalle(valeur)


if __name__ == "__main__":
    unittest.main()
