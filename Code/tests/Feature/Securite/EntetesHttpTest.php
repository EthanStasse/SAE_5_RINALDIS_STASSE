<?php

/*
 * ENF-SEC-01 : en-têtes de sécurité HTTP sur toutes les réponses.
 * Ils sont posés par l'application, et pas seulement par nginx :
 * on peut donc les tester, et ils restent en place quel que soit le serveur.
 */

describe('en-têtes de sécurité (ENF-SEC-01)', function () {
    it('protège la page de connexion', function () {
        $reponse = $this->get('/login');

        $reponse->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        expect((string) $reponse->headers->get('Content-Security-Policy'))
            ->toContain("default-src 'self'")
            ->toContain("object-src 'none'")
            ->toContain("frame-ancestors 'none'")
            ->toContain("base-uri 'self'")
            ->toContain("form-action 'self'");
    });

    it("protège aussi les pages d'erreur", function () {
        $this->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    });
});

describe('HSTS (ENF-SEC-01)', function () {
    it('impose HTTPS pendant un an quand la page est servie en HTTPS', function () {
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    });

    it("n'envoie pas HSTS en HTTP, où il serait ignoré", function () {
        $this->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');
    });
});