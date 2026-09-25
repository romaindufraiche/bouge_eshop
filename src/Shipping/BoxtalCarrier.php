<?php

declare(strict_types=1);

namespace Bouge\Shipping;

use DOMDocument;
use DOMXPath;

/**
 * Boxtal, anciennement Envoimoinscher.
 *
 * Un courtier plutôt qu'un transporteur : un seul compte donne accès à
 * Mondial Relay, Colissimo et Chronopost, à domicile comme en point relais,
 * sans abonnement ni engagement de volume. C'est ce qui convient à une
 * boutique qui démarre.
 *
 * Dialogue avec leur API v1, qui parle XML et s'authentifie par un en-tête
 * maison. Écrit à la main plutôt qu'avec leur bibliothèque : celle-ci
 * réclame des constantes globales et date de PHP 5, alors que trois requêtes
 * suffisent ici.
 *
 * Documentation : https://developer.boxtal.com/fr/fr/apiv1/guide/
 */
final class BoxtalCarrier implements Carrier
{
    /** Version d'API annoncée à chaque requête ; Boxtal la réclame. */
    private const API_VERSION = '1.3.7';

    private const SERVEUR_TEST = 'https://test.envoimoinscher.com/';
    private const SERVEUR_PROD = 'https://www.envoimoinscher.com/';

    /**
     * Le paramètre d'offre qui porte la liste des points relais. Boxtal le
     * renvoie sous forme d'énumération : un identifiant, un libellé.
     */
    private const PARAM_POINT_RELAIS = 'retrait.pointrelais';

    /**
     * Nature de la marchandise, dans la nomenclature Boxtal. 40110 correspond
     * au textile et aux vêtements neufs, ce que vend la boutique.
     */
    private const CODE_CONTENU = 40110;

    /**
     * @param array<string, string> $expediteur Adresse de départ, imprimée sur
     *                                          l'étiquette.
     */
    public function __construct(
        private readonly string $utilisateur,
        private readonly string $motDePasse,
        private readonly bool $test,
        private readonly array $expediteur,
        /** Colis type servant au calcul de tarif, en centimètres. */
        private readonly array $dimensions = ['longueur' => 25, 'largeur' => 20, 'hauteur' => 10],
        private readonly int $delaiSecondes = 8,
    ) {
    }

    public function name(): string
    {
        return $this->test ? 'Boxtal (mode test)' : 'Boxtal';
    }

    public function relayPointsNear(string $postalCode, string $city, int $limit = 8): array
    {
        // Un colis type suffit : les points relais proposés dépendent de
        // l'adresse, pas du poids.
        $xpath = $this->requete('api/v1/cotation', $this->parametresDevis($postalCode, $city, 500));

        $points = [];

        foreach ($xpath->query('//offer') as $offre) {
            $transporteur = $this->texte($xpath, './operator/label', $offre) ?: 'Transporteur';

            $chemin = './options/option/parameter[code="' . self::PARAM_POINT_RELAIS . '"]/type/enum/value';

            foreach ($xpath->query($chemin, $offre) as $valeur) {
                $code = trim($this->texte($xpath, './id', $valeur));
                $libelle = trim($this->texte($xpath, './label', $valeur));

                // Le même commerce revient dans plusieurs offres : on garde la
                // première, la liste doit rester lisible.
                if ($code === '' || isset($points[$code])) {
                    continue;
                }

                $points[$code] = $this->depuisLibelle($code, $transporteur, $libelle, $postalCode, $city);
            }
        }

        return array_slice(array_values($points), 0, $limit);
    }

    public function relayPoint(string $code): ?RelayPoint
    {
        $pays = $this->expediteur['country'] ?? 'FR';

        try {
            $xpath = $this->requete('api/v1/pickup_point/' . rawurlencode($code) . '/' . $pays . '/informations');
        } catch (CarrierException) {
            // Un point inconnu se traduit par une erreur côté Boxtal : c'est
            // une absence, pas une panne.
            return null;
        }

        $noeud = $xpath->query('/pickup_point')->item(0);

        if ($noeud === null) {
            return null;
        }

        $horaires = [];
        foreach ($xpath->query('./schedule/day', $noeud) as $jour) {
            $ligne = trim(preg_replace('/\s+/', ' ', (string) $jour->textContent) ?? '');
            if ($ligne !== '') {
                $horaires[] = $ligne;
            }
        }

        return new RelayPoint(
            code: $this->texte($xpath, './code', $noeud) ?: $code,
            operator: $this->operateurDepuisCode($code),
            name: $this->texte($xpath, './name', $noeud),
            address: $this->texte($xpath, './address', $noeud),
            postalCode: $this->texte($xpath, './zipcode', $noeud),
            city: $this->texte($xpath, './city', $noeud),
            schedule: array_slice($horaires, 0, 3),
        );
    }

    public function buyLabel(array $order, int $weightGrams): ShippingLabel
    {
        $enRelais = ($order['fulfilment'] ?? '') === \Bouge\Support\Status::RELAY;

        $codePostal = (string) ($enRelais ? $order['relay_postal_code'] : $order['shipping_postal_code']);
        $ville = (string) ($enRelais ? $order['relay_city'] : $order['shipping_city']);

        // Le tarif est redemandé au moment d'acheter : les offres changent, et
        // acheter sur un devis d'hier ferait échouer la commande.
        $offre = $this->meilleureOffre($codePostal, $ville, $weightGrams, $enRelais);

        $parametres = $this->parametresDevis($codePostal, $ville, $weightGrams);
        $parametres += $this->personne('expediteur', $this->expediteurComplet());
        $parametres += $this->personne('destinataire', $this->destinataire($order, $enRelais));
        $parametres += [
            'operator'             => $offre['operateur'],
            'service'              => $offre['service'],
            'collecte'             => date('Y-m-d'),
            'delai'                => 'aucun',
            'assurance.selection'  => 'false',
            'content_code'         => self::CODE_CONTENU,
            'colis.description'    => 'Matériel de natation',
            'valeur'               => number_format(((int) $order['subtotal_cents']) / 100, 2, '.', ''),
        ];

        if ($enRelais) {
            $parametres[self::PARAM_POINT_RELAIS] = (string) $order['relay_code'];
        }

        $xpath = $this->requete('api/v1/order', $parametres);

        $envoi = $xpath->query('/order/shipment')->item(0);

        if ($envoi === null) {
            throw new CarrierException("Boxtal n'a pas confirmé la commande d'expédition.");
        }

        $reference = $this->texte($xpath, './reference', $envoi);
        $etiquette = $this->texte($xpath, './labels/label', $envoi);

        if ($reference === '' || $etiquette === '') {
            throw new CarrierException(
                "Boxtal a accepté la commande mais n'a pas renvoyé d'étiquette. "
                . 'Vérifiez le détail de l\'envoi sur votre compte Boxtal.'
            );
        }

        return new ShippingLabel(
            url: $etiquette,
            reference: $reference,
            carrier: $this->texte($xpath, './offer/operator/label', $envoi) ?: 'Boxtal',
            trackingNumber: $this->texte($xpath, './offer/tracking', $envoi) ?: $reference,
        );
    }

    // --- Devis ---------------------------------------------------------------

    /**
     * L'offre la moins chère qui corresponde au mode de livraison voulu.
     *
     * @return array{operateur: string, service: string}
     */
    private function meilleureOffre(string $postalCode, string $city, int $grammes, bool $enRelais): array
    {
        $xpath = $this->requete('api/v1/cotation', $this->parametresDevis($postalCode, $city, $grammes));

        $meilleure = null;
        $prix = null;

        foreach ($xpath->query('//offer') as $offre) {
            $aDesPointsRelais = $xpath->query(
                './options/option/parameter[code="' . self::PARAM_POINT_RELAIS . '"]',
                $offre
            )->length > 0;

            // Une offre en point relais ne sait pas livrer à domicile, et
            // réciproquement : proposer la mauvaise ferait échouer l'achat.
            if ($aDesPointsRelais !== $enRelais) {
                continue;
            }

            $montant = (float) $this->texte($xpath, './price/tax-inclusive', $offre);

            if ($montant <= 0.0 || ($prix !== null && $montant >= $prix)) {
                continue;
            }

            $prix = $montant;
            $meilleure = [
                'operateur' => $this->texte($xpath, './operator/code', $offre),
                'service'   => $this->texte($xpath, './service/code', $offre),
            ];
        }

        if ($meilleure === null) {
            throw new CarrierException(
                $enRelais
                    ? "Aucune offre en point relais pour cette adresse."
                    : "Aucune offre de livraison à domicile pour cette adresse."
            );
        }

        return $meilleure;
    }

    /** @return array<string, string|int> */
    private function parametresDevis(string $postalCode, string $city, int $grammes): array
    {
        $parametres = [
            'colis_1.poids'     => max(0.1, round($grammes / 1000, 2)),
            'colis_1.longueur'  => $this->dimensions['longueur'],
            'colis_1.largeur'   => $this->dimensions['largeur'],
            'colis_1.hauteur'   => $this->dimensions['hauteur'],
            'code_contenu'      => self::CODE_CONTENU,
            'collecte'          => date('Y-m-d'),
        ];

        $parametres += $this->personne('expediteur', [
            'pays'        => $this->expediteur['country'] ?? 'FR',
            'code_postal' => $this->expediteur['postal_code'] ?? '',
            'ville'       => $this->expediteur['city'] ?? '',
            'adresse'     => $this->expediteur['address'] ?? '',
            'type'        => 'entreprise',
        ]);

        $parametres += $this->personne('destinataire', [
            'pays'        => 'FR',
            'code_postal' => $postalCode,
            'ville'       => $city !== '' ? $city : $postalCode,
            'type'        => 'particulier',
        ]);

        return $parametres;
    }

    /**
     * Préfixe chaque champ du nom de la personne : Boxtal attend
     * `expediteur.ville`, `destinataire.nom`, et ainsi de suite.
     *
     * @param array<string, string> $champs
     * @return array<string, string>
     */
    private function personne(string $role, array $champs): array
    {
        $parametres = [];

        foreach ($champs as $cle => $valeur) {
            if ($valeur !== '') {
                $parametres[$role . '.' . $cle] = $valeur;
            }
        }

        return $parametres;
    }

    /** @return array<string, string> */
    private function expediteurComplet(): array
    {
        return [
            'pays'        => $this->expediteur['country'] ?? 'FR',
            'code_postal' => $this->expediteur['postal_code'] ?? '',
            'ville'       => $this->expediteur['city'] ?? '',
            'adresse'     => $this->expediteur['address'] ?? '',
            'type'        => 'entreprise',
            'societe'     => $this->expediteur['company'] ?? '',
            'civilite'    => 'M',
            'prenom'      => $this->expediteur['company'] ?? 'Service',
            'nom'         => 'Expedition',
            'email'       => $this->expediteur['email'] ?? '',
            'tel'         => $this->expediteur['phone'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, string>
     */
    private function destinataire(array $order, bool $enRelais): array
    {
        // En point relais, le colis va au commerce : c'est son adresse qui est
        // imprimée, le nom du client servant seulement à le retrouver au
        // comptoir.
        $nomComplet = trim((string) $order['customer_name']);
        $espace = mb_strrpos($nomComplet, ' ');

        return [
            'pays'        => 'FR',
            'code_postal' => (string) ($enRelais ? $order['relay_postal_code'] : $order['shipping_postal_code']),
            'ville'       => (string) ($enRelais ? $order['relay_city'] : $order['shipping_city']),
            'adresse'     => (string) ($enRelais ? $order['relay_address'] : $order['shipping_address_line1']),
            'type'        => 'particulier',
            'civilite'    => 'M',
            'prenom'      => $espace === false ? $nomComplet : mb_substr($nomComplet, 0, $espace),
            'nom'         => $espace === false ? $nomComplet : mb_substr($nomComplet, $espace + 1),
            'email'       => (string) $order['email'],
            'tel'         => (string) ($order['phone'] ?? ''),
        ];
    }

    // --- Lecture des réponses ------------------------------------------------

    /**
     * Découpe le libellé d'un point relais.
     *
     * Boxtal renvoie une chaîne, dont la mise en forme dépend du
     * transporteur. On tente la découpe la plus courante — nom, adresse, code
     * postal et ville — et à défaut le libellé entier sert de nom : mieux vaut
     * un intitulé un peu long qu'un point relais illisible. L'adresse exacte
     * est de toute façon redemandée par relayPoint() pour celui que le client
     * retient.
     */
    private function depuisLibelle(
        string $code,
        string $transporteur,
        string $libelle,
        string $postalCodeCherche,
        string $villeCherchee,
    ): RelayPoint {
        $morceaux = array_values(array_filter(array_map('trim', preg_split('/\s*[-,]\s*/', $libelle) ?: [])));

        $codePostal = $postalCodeCherche;
        $ville = $villeCherchee;
        $adresse = '';
        $nom = $libelle;

        if (count($morceaux) >= 2) {
            $nom = array_shift($morceaux);

            // Le dernier morceau qui commence par cinq chiffres porte le code
            // postal et la ville ; ce qui reste au milieu est la rue.
            $dernier = end($morceaux);
            if (is_string($dernier) && preg_match('/^(\d{5})\s+(.+)$/', $dernier, $trouve) === 1) {
                $codePostal = $trouve[1];
                $ville = $trouve[2];
                array_pop($morceaux);
            }

            $adresse = implode(', ', $morceaux);
        }

        return new RelayPoint(
            code: $code,
            operator: $transporteur,
            name: $nom,
            address: $adresse,
            postalCode: $codePostal,
            city: $ville,
        );
    }

    /** Le code d'un point relais commence par celui de son transporteur. */
    private function operateurDepuisCode(string $code): string
    {
        return match (strtoupper(substr($code, 0, 4))) {
            'MONR' => 'Mondial Relay',
            'RELC' => 'Relais Colis',
            'CHRP' => 'Chronopost',
            'SOGP' => 'Relais Colis',
            'UPSE' => 'UPS',
            default => 'Transporteur',
        };
    }

    private function texte(DOMXPath $xpath, string $chemin, ?object $contexte = null): string
    {
        $noeud = $contexte === null
            ? $xpath->query($chemin)->item(0)
            : $xpath->query($chemin, $contexte)->item(0);

        return $noeud === null ? '' : trim((string) $noeud->nodeValue);
    }

    // --- Transport HTTP ------------------------------------------------------

    /**
     * Une requête à l'API, en GET sans paramètres et en POST avec.
     *
     * @param array<string, mixed> $parametres
     * @throws CarrierException
     */
    private function requete(string $action, array $parametres = []): DOMXPath
    {
        $serveur = $this->test ? self::SERVEUR_TEST : self::SERVEUR_PROD;
        $curl = curl_init($serveur . $action);

        if ($curl === false) {
            throw new CarrierException('Impossible de contacter Boxtal.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->delaiSecondes,
            CURLOPT_HTTPHEADER     => [
                'Accept-Language: fr_FR',
                'Api-Version: ' . self::API_VERSION,
                // Boxtal attend le couple encodé sans le mot « Basic » : c'est
                // leur convention, pas une authentification HTTP standard.
                'Authorization: ' . base64_encode($this->utilisateur . ':' . $this->motDePasse),
            ],
        ]);

        if ($parametres !== []) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($parametres));
        }

        $reponse = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $erreur = curl_error($curl);
        curl_close($curl);

        if ($reponse === false || $erreur !== '') {
            throw new CarrierException('Boxtal ne répond pas : ' . $erreur);
        }

        return $this->analyser((string) $reponse, $code);
    }

    /** @throws CarrierException */
    private function analyser(string $xml, int $codeHttp): DOMXPath
    {
        $document = new DOMDocument();
        // Les avertissements de libxml ne nous apprennent rien d'utile ; le
        // retour de load() suffit à savoir si le document tient debout.
        $precedent = libxml_use_internal_errors(true);
        $valide = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        if (!$valide) {
            throw new CarrierException("Boxtal a renvoyé une réponse illisible (HTTP {$codeHttp}).");
        }

        $xpath = new DOMXPath($document);

        // Les erreurs métier arrivent avec un corps XML, pas seulement un code
        // HTTP : c'est là qu'il faut les lire.
        $messages = [];
        foreach ($xpath->query('//error/message | //error/code') as $noeud) {
            $message = trim((string) $noeud->nodeValue);
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        if ($messages !== []) {
            throw new CarrierException('Boxtal : ' . implode(' — ', array_unique($messages)));
        }

        if ($codeHttp >= 400) {
            throw new CarrierException("Boxtal a refusé la requête (HTTP {$codeHttp}).");
        }

        return $xpath;
    }
}
