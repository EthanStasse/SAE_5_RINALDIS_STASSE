// Thème clair ou sombre de l'application.
//
// - Au chargement : on applique le choix enregistré dans le navigateur
//   (localStorage) ; à défaut, le réglage du système (mode sombre de Windows,
//   macOS ou du téléphone).
// - Le bouton lune / soleil (attribut data-bascule-theme) inverse le thème et
//   enregistre le choix.
//
// Fichier chargé dans <head> sans « defer » : le thème est posé avant
// l'affichage de la page, sans flash blanc. Fichier externe et non script en
// ligne : la politique de sécurité du contenu (CSP) n'autorise que 'self'.
//
// RGPD : seule la préférence « clair » ou « sombre » est enregistrée, dans le
// navigateur de la personne ; rien n'est envoyé au serveur.
(function () {
    var CLE = 'theme';
    var racine = document.documentElement;

    function lireChoix() {
        try {
            return localStorage.getItem(CLE);
        } catch (e) {
            return null; // navigation privée ou stockage désactivé
        }
    }

    function enregistrerChoix(theme) {
        try {
            localStorage.setItem(CLE, theme);
        } catch (e) {
            // le thème change quand même, il ne sera simplement pas retenu
        }
    }

    function appliquer(sombre) {
        racine.classList.toggle('dark', sombre);

        var bouton = document.querySelector('[data-bascule-theme]');
        if (bouton) {
            bouton.setAttribute('aria-pressed', sombre ? 'true' : 'false');
            var libelle = bouton.querySelector('[data-libelle-theme]');
            if (libelle) {
                libelle.textContent = sombre ? 'Activer le mode clair' : 'Activer le mode sombre';
            }
        }
    }

    var choix = lireChoix();
    var sombre = choix
        ? choix === 'dark'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;
    appliquer(sombre);

    // Le bouton n'existe pas encore pendant la lecture de <head> :
    // on met son libellé à jour une fois la page construite.
    document.addEventListener('DOMContentLoaded', function () {
        appliquer(racine.classList.contains('dark'));
    });

    document.addEventListener('click', function (evenement) {
        if (!evenement.target.closest('[data-bascule-theme]')) {
            return;
        }
        var sombre = !racine.classList.contains('dark');
        appliquer(sombre);
        enregistrerChoix(sombre ? 'dark' : 'light');
    });
})();
