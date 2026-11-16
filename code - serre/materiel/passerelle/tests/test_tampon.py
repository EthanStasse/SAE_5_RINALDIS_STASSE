import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from tampon import Tampon  # noqa: E402


class TamponTest(unittest.TestCase):
    def setUp(self):
        self.dossier = tempfile.TemporaryDirectory()
        self.chemin = str(Path(self.dossier.name) / "t.sqlite3")
        self.tampon = Tampon(self.chemin, taille_max=3)

    def tearDown(self):
        self.tampon.fermer()
        self.dossier.cleanup()

    def test_fifo(self):
        self.tampon.ajouter("a", {"n": 1})
        self.tampon.ajouter("a", {"n": 2})
        lot = self.tampon.plus_anciennes(10)
        self.assertEqual([m.corps["n"] for m in lot], [1, 2])
        self.tampon.supprimer(lot[0].id)
        self.assertEqual(self.tampon.compter(), 1)

    def test_taille_max_supprime_les_plus_anciennes(self):
        for n in range(5):
            self.tampon.ajouter("a", {"n": n})
        self.assertEqual(self.tampon.compter(), 3)
        self.assertEqual([m.corps["n"] for m in self.tampon.plus_anciennes(10)], [2, 3, 4])

    def test_exclure_un_boitier(self):
        self.tampon.ajouter("a", {"n": 1})
        self.tampon.ajouter("b", {"n": 2})
        lot = self.tampon.plus_anciennes(10, exclure={"a"})
        self.assertEqual([m.boitier for m in lot], ["b"])

    def test_survit_a_un_redemarrage(self):
        self.tampon.ajouter("a", {"n": 42})
        self.tampon.fermer()
        self.tampon = Tampon(self.chemin, taille_max=3)
        self.assertEqual(self.tampon.plus_anciennes(1)[0].corps, {"n": 42})

    def test_tentatives(self):
        self.tampon.ajouter("a", {})
        identifiant = self.tampon.plus_anciennes(1)[0].id
        self.tampon.noter_tentative(identifiant)
        self.assertEqual(self.tampon.plus_anciennes(1)[0].tentatives, 1)


if __name__ == "__main__":
    unittest.main()
