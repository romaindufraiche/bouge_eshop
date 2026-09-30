<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;

/**
 * Envoi des courriels de la boutique.
 *
 * Deux modes, choisis par la configuration :
 *
 *   - **SMTP**, si `mail.smtp.host` est renseigné. C'est le mode à préférer :
 *     le courriel part de la boîte du commerçant, signé par son domaine, et
 *     arrive dans les boîtes de réception plutôt que dans les indésirables.
 *   - **`mail()`**, sinon. Disponible partout, mais le courrier part du
 *     serveur web sans authentification : Gmail et Outlook le rangent souvent
 *     en indésirable. Acceptable pour essayer, pas pour ouvrir.
 *
 * Un envoi qui échoue ne fait jamais tomber la page : il est journalisé et la
 * commande suit son cours. Perdre un accusé de réception est ennuyeux ; perdre
 * la commande parce que le serveur de courriel ne répondait pas le serait bien
 * davantage.
 */
final class Mailer
{
    /**
     * @param array<string, mixed> $donnees Variables passées au gabarit.
     * @return bool Vrai si le courriel est parti.
     */
    public static function send(string $destinataire, string $sujet, string $gabarit, array $donnees = []): bool
    {
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            error_log("Courriel non envoyé : « {$destinataire} » n'est pas une adresse valide.");

            return false;
        }

        try {
            $html = View::partial('courriels/' . $gabarit, $donnees + [
                'shop' => Config::shopAll(),
                'sujet' => $sujet,
            ]);
        } catch (\Throwable $e) {
            error_log("Gabarit de courriel « {$gabarit} » illisible : " . $e);

            return false;
        }

        $texte = self::versTexte($html);

        try {
            return self::expedier($destinataire, $sujet, $html, $texte);
        } catch (\Throwable $e) {
            // Journalisé, jamais remonté : voir le commentaire de classe.
            error_log("Envoi du courriel « {$sujet} » à {$destinataire} en échec : " . $e->getMessage());

            return false;
        }
    }

    // --- Composition ---------------------------------------------------------

    private static function expedier(string $a, string $sujet, string $html, string $texte): bool
    {
        $de = (string) Config::get('mail.from', '');
        $nom = (string) Config::get('mail.from_name', (string) Config::shop('name', 'Boutique'));

        if ($de === '') {
            error_log("Courriel non envoyé : « mail.from » n'est pas renseigné dans config/config.php.");

            return false;
        }

        // Une frontière qui ne peut pas apparaître dans le corps.
        $limite = '=_bouge_' . bin2hex(random_bytes(12));

        $corps = "--{$limite}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texte)) . "\r\n"
            . "--{$limite}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$limite}--\r\n";

        $entetes = [
            'From' => self::adresse($nom, $de),
            'Reply-To' => self::adresse($nom, (string) Config::get('mail.reply_to', $de)),
            'MIME-Version' => '1.0',
            'Content-Type' => "multipart/alternative; boundary=\"{$limite}\"",
            'Date' => date('r'),
            // Les courriels de la boutique sont transactionnels : ni liste de
            // diffusion, ni réponse automatique en retour.
            'Auto-Submitted' => 'auto-generated',
            'X-Auto-Response-Suppress' => 'All',
        ];

        return (string) Config::get('mail.smtp.host', '') !== ''
            ? self::parSmtp($a, $sujet, $corps, $entetes, $de)
            : self::parFonctionMail($a, $sujet, $corps, $entetes);
    }

    /** Encode un nom d'expéditeur, accents compris. */
    private static function adresse(string $nom, string $email): string
    {
        return $nom === ''
            ? $email
            : '=?UTF-8?B?' . base64_encode($nom) . '?= <' . $email . '>';
    }

    /** @param array<string, string> $entetes */
    private static function parFonctionMail(string $a, string $sujet, string $corps, array $entetes): bool
    {
        $lignes = [];

        foreach ($entetes as $cle => $valeur) {
            $lignes[] = "{$cle}: {$valeur}";
        }

        return mail(
            $a,
            '=?UTF-8?B?' . base64_encode($sujet) . '?=',
            $corps,
            implode("\r\n", $lignes)
        );
    }

    // --- SMTP ----------------------------------------------------------------

    /**
     * Un client SMTP minimal : connexion, éventuel STARTTLS, authentification,
     * envoi. Écrit à la main plutôt qu'avec PHPMailer, qui chargerait une
     * cinquantaine de fichiers pour trois commandes de protocole.
     *
     * @param array<string, string> $entetes
     * @throws RuntimeException
     */
    private static function parSmtp(string $a, string $sujet, string $corps, array $entetes, string $de): bool
    {
        $hote = (string) Config::get('mail.smtp.host');
        $port = (int) Config::get('mail.smtp.port', 587);
        $chiffrement = (string) Config::get('mail.smtp.encryption', 'tls');
        $utilisateur = (string) Config::get('mail.smtp.user', '');
        $motDePasse = (string) Config::get('mail.smtp.password', '');
        $delai = (int) Config::get('mail.smtp.timeout', 12);

        // « ssl » : chiffré dès la connexion, en général sur le port 465.
        // « tls » : connexion en clair puis STARTTLS, en général sur le 587.
        $cible = ($chiffrement === 'ssl' ? 'ssl://' : '') . $hote . ':' . $port;
        $flux = @stream_socket_client($cible, $erreur, $message, $delai);

        if ($flux === false) {
            throw new RuntimeException("Connexion à {$cible} impossible : {$message}");
        }

        stream_set_timeout($flux, $delai);

        try {
            self::attendre($flux, 220);

            $nomLocal = (string) (parse_url((string) Config::get('site_url', ''), PHP_URL_HOST) ?: 'localhost');
            self::commande($flux, "EHLO {$nomLocal}", 250);

            if ($chiffrement === 'tls') {
                // Si le chiffrement échoue ici, le message est abandonné.
                // La tentation serait de continuer en clair pour ne pas le
                // perdre : c'est exactement ce qu'il ne faut pas faire.
                // AUTH LOGIN transmet le mot de passe de la boîte en base64,
                // autrement dit en clair pour qui écoute le réseau. Un
                // courriel perdu se renvoie ; un mot de passe de messagerie
                // capté, non.
                try {
                    self::commande($flux, 'STARTTLS', 220);
                } catch (RuntimeException $e) {
                    throw new RuntimeException(
                        'Le serveur SMTP a refusé de chiffrer la connexion (' . $e->getMessage()
                        . "). Vérifiez « mail.smtp.port » et « mail.smtp.encryption » : le port 587 "
                        . "va avec 'tls', le 465 avec 'ssl'."
                    );
                }

                if (!stream_socket_enable_crypto($flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException(
                        'Le certificat du serveur SMTP a été rejeté : vérifiez le nom d\'hôte '
                        . 'dans « mail.smtp.host ».'
                    );
                }

                // La négociation remet la session à zéro : il faut se
                // represénter.
                self::commande($flux, "EHLO {$nomLocal}", 250);
            }

            if ($utilisateur !== '') {
                self::commande($flux, 'AUTH LOGIN', 334);
                self::commande($flux, base64_encode($utilisateur), 334);
                self::commande($flux, base64_encode($motDePasse), 235);
            }

            self::commande($flux, "MAIL FROM:<{$de}>", 250);
            self::commande($flux, "RCPT TO:<{$a}>", [250, 251]);
            self::commande($flux, 'DATA', 354);

            $entetes = ['To' => $a, 'Subject' => '=?UTF-8?B?' . base64_encode($sujet) . '?='] + $entetes;
            $message = '';

            foreach ($entetes as $cle => $valeur) {
                $message .= "{$cle}: {$valeur}\r\n";
            }

            // Un point seul en début de ligne termine le message : il faut le
            // doubler partout ailleurs.
            $message .= "\r\n" . preg_replace('/^\./m', '..', $corps);

            fwrite($flux, $message . "\r\n.\r\n");
            self::attendre($flux, 250);
            self::commande($flux, 'QUIT', [221, 250]);
        } finally {
            fclose($flux);
        }

        return true;
    }

    /**
     * @param resource        $flux
     * @param int|list<int>   $attendu
     */
    private static function commande($flux, string $ligne, int|array $attendu): void
    {
        fwrite($flux, $ligne . "\r\n");
        self::attendre($flux, $attendu);
    }

    /**
     * Lit la réponse du serveur et vérifie son code.
     *
     * @param resource      $flux
     * @param int|list<int> $attendu
     * @throws RuntimeException
     */
    private static function attendre($flux, int|array $attendu): void
    {
        $attendu = (array) $attendu;
        $reponse = '';

        while (($ligne = fgets($flux, 515)) !== false) {
            $reponse .= $ligne;

            // Une réponse sur plusieurs lignes porte un tiret après le code ;
            // la dernière porte une espace.
            if (strlen($ligne) < 4 || $ligne[3] !== '-') {
                break;
            }
        }

        if ($reponse === '') {
            throw new RuntimeException('Le serveur SMTP ne répond plus.');
        }

        $code = (int) substr($reponse, 0, 3);

        if (!in_array($code, $attendu, true)) {
            throw new RuntimeException(
                'Réponse SMTP inattendue : ' . trim($reponse)
                . ' (attendu ' . implode(' ou ', $attendu) . ')'
            );
        }
    }

    // --- Version texte -------------------------------------------------------

    /**
     * Une version texte lisible, tirée du HTML.
     *
     * Elle n'est pas décorative : un message sans partie texte est noté comme
     * suspect par les filtres anti-indésirables, et certains clients de
     * messagerie n'affichent que celle-ci.
     */
    private static function versTexte(string $html): string
    {
        // Le corps seulement : ni style, ni scripts.
        $texte = (string) preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html);

        // Les liens deviennent « libellé (adresse) » : dans un texte brut,
        // l'adresse doit être visible pour être cliquable.
        $texte = (string) preg_replace_callback(
            '#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $libelle = trim(strip_tags($m[2]));
                $lien = $m[1];

                return $libelle === '' || $libelle === $lien ? $lien : "{$libelle} ({$lien})";
            },
            $texte
        );

        $texte = (string) preg_replace('#</(p|div|tr|h[1-6]|li)>#i', "\n\n", $texte);
        $texte = (string) preg_replace('#<br\s*/?>#i', "\n", $texte);
        $texte = html_entity_decode(strip_tags($texte), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Les gabarits sont indentés : sans ce nettoyage, chaque ligne
        // arriverait précédée de douze espaces.
        $texte = (string) preg_replace('/[ \t]+/', ' ', $texte);
        $texte = (string) preg_replace('/ ?\n ?/', "\n", $texte);
        $texte = (string) preg_replace('/\n{3,}/', "\n\n", $texte);

        return trim($texte);
    }
}
