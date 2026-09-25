<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\CustomerRepository;
use Bouge\Shipping\CarrierException;
use Bouge\Shipping\Carriers;
use Bouge\Shipping\RelayPoint;
use Bouge\Repository\OrderRepository;
use Bouge\Repository\PickupPointRepository;
use Bouge\Support\Cart;
use Bouge\Support\Csrf;
use Bouge\Support\CustomerAuth;
use Bouge\Support\Session;
use Bouge\Support\Shipping;
use Bouge\Support\Status;
use Bouge\Support\StripeGateway;
use Bouge\Support\Validator;
use Bouge\Support\View;
use Throwable;

final class CheckoutController
{
    public function form(): string
    {
        return $this->renderForm([], []);
    }

    /**
     * Crée la commande puis la session de paiement.
     *
     * Rien de ce qui touche à l'argent ne vient du formulaire : le panier est
     * relu en base, les stocks revérifiés, les frais de port recalculés.
     */
    public function submit(): string
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            Session::flash('shop', 'Votre session a expiré. Vérifiez votre panier et réessayez.');
            redirect('/commande');
        }

        $points = (new PickupPointRepository())->active();
        $fulfilment = (string) ($_POST['fulfilment'] ?? Status::DELIVERY);

        // Le bouton « Chercher les points relais » renvoie le formulaire sans
        // rien valider : le client n'a pas fini de le remplir, lui reprocher
        // ses champs vides à ce moment-là serait absurde. C'est la seule
        // manière d'interroger le transporteur sans JavaScript.
        if (isset($_POST['chercher_relais'])) {
            return $this->renderForm([], $_POST);
        }

        $validator = new Validator($_POST);
        $validator
            ->required('customer_name', 'Indiquez votre nom.')
            ->maxLength('customer_name', 120, 'Nom trop long.')
            ->required('email', 'Indiquez votre adresse électronique.')
            ->email('email', 'Adresse électronique invalide.')
            ->maxLength('phone', 30, 'Numéro trop long.')
            ->inList(
                'fulfilment',
                Shipping::availableFulfilments($points !== []),
                'Choisissez un mode de livraison.'
            )
            ->when($fulfilment === Status::DELIVERY, static function (Validator $v): void {
                $v->required('shipping_address_line1', 'Indiquez votre adresse.')
                  ->required('shipping_city', 'Indiquez votre ville.')
                  ->required('shipping_postal_code', 'Indiquez votre code postal.')
                  ->pattern('shipping_postal_code', '/^\d{5}$/', 'Code postal à 5 chiffres.');
            })
            // En point relais, c'est le commerce qui reçoit le colis : l'adresse
            // du client ne sert à rien et ne lui est donc pas demandée.
            ->when($fulfilment === Status::RELAY, static function (Validator $v): void {
                $v->required('relay_code', 'Choisissez un point relais dans la liste.');
            })
            ->when($fulfilment === Status::PICKUP, static function (Validator $v): void {
                $v->required('pickup_point_id', 'Choisissez un point de retrait.');
            });

        if (!$validator->passes()) {
            return $this->renderForm($validator->errors(), $validator->values());
        }

        // --- Relecture du panier en base -------------------------------------
        $cart = Cart::resolve();

        if ($cart['empty']) {
            return $this->renderForm(
                [],
                $validator->values(),
                'Votre panier est vide.'
            );
        }

        if ($cart['issues'] !== []) {
            return $this->renderForm(
                [],
                $validator->values(),
                'Votre panier a changé depuis votre dernière visite. Vérifiez le récapitulatif ci-dessous, puis relancez le paiement.'
            );
        }

        // --- Point de retrait --------------------------------------------------
        $pickupPointId = null;
        if ($fulfilment === Status::PICKUP) {
            $point = (new PickupPointRepository())->findActive((int) $validator->value('pickup_point_id'));

            if ($point === null) {
                return $this->renderForm(
                    ['pickup_point_id' => 'Choisissez un autre point de retrait.'],
                    $validator->values(),
                    "Ce point de retrait n'est plus disponible."
                );
            }

            $pickupPointId = (int) $point['id'];
        }

        // --- Point relais ------------------------------------------------------
        // Le code arrive du formulaire, mais jamais l'adresse : elle est
        // redemandée au transporteur. Sans quoi un client pourrait faire
        // imprimer l'étiquette pour l'adresse de son choix.
        $relay = null;
        if ($fulfilment === Status::RELAY) {
            try {
                $relay = $this->findRelayByCode(
                    (string) $validator->value('relay_code'),
                    (string) ($_POST['relay_search_postal_code'] ?? ''),
                    (string) ($_POST['relay_search_city'] ?? '')
                );
            } catch (CarrierException $e) {
                error_log('Transporteur injoignable au moment de la commande : ' . $e);

                return $this->renderForm(
                    [],
                    $validator->values(),
                    "Le transporteur ne répond pas pour l'instant. Réessayez dans un moment, "
                    . 'ou choisissez la livraison à domicile.'
                );
            }

            if ($relay === null) {
                return $this->renderForm(
                    ['relay_code' => 'Choisissez un autre point relais.'],
                    $validator->values(),
                    "Ce point relais n'est plus proposé. Relancez la recherche et choisissez-en un autre."
                );
            }
        }

        $shippingCents = Shipping::cents($cart['subtotal_cents'], $fulfilment);
        $totalCents = $cart['subtotal_cents'] + $shippingCents;
        $reference = OrderRepository::generateReference();

        // --- Création de la commande, au statut « en attente de paiement » -----
        $orders = new OrderRepository();
        $orderId = $orders->create(
            [
                'reference'     => $reference,
                'email'         => mb_strtolower($validator->value('email')),
                'customer_name' => $validator->value('customer_name'),
                'phone'         => $validator->value('phone') ?: null,
                'status'        => Status::ORDER_PENDING,
                'fulfilment'    => $fulfilment,

                'shipping_address_line1' => $fulfilment === Status::DELIVERY ? $validator->value('shipping_address_line1') : null,
                'shipping_address_line2' => $fulfilment === Status::DELIVERY ? ($validator->value('shipping_address_line2') ?: null) : null,
                'shipping_postal_code'   => $fulfilment === Status::DELIVERY ? $validator->value('shipping_postal_code') : null,
                'shipping_city'          => $fulfilment === Status::DELIVERY ? $validator->value('shipping_city') : null,
                'shipping_country'       => $fulfilment === Status::DELIVERY ? 'FR' : null,

                'pickup_point_id' => $pickupPointId,
                // Le point relais est recopié sur la commande : il appartient au
                // transporteur, il changera, et la commande doit rester lisible
                // dans dix ans même si le commerce a fermé.
                ...($relay?->toOrderColumns() ?? []),
                // Rattachement au compte s'il y en a un. NULL sinon : commander
                // sans compte reste possible, et c'est le cas par défaut.
                'customer_id'     => CustomerAuth::id(),
                'subtotal_cents'  => $cart['subtotal_cents'],
                'shipping_cents'  => $shippingCents,
                'total_cents'     => $totalCents,
            ],
            array_map(
                static fn (array $line): array => [
                    'product_id'       => $line['product_id'],
                    'variant_id'       => $line['variant_id'],
                    'product_name'     => $line['name'],
                    'variant_label'    => $line['variant_label'],
                    'image_url'        => $line['image_url'],
                    'unit_price_cents' => $line['unit_price_cents'],
                    'weight_grams'     => $line['weight_grams'],
                    'quantity'         => $line['quantity'],
                    'line_total_cents' => $line['line_total_cents'],
                ],
                $cart['lines']
            )
        );

        // Le client connecté retrouvera ses coordonnées préremplies la fois
        // suivante — sans avoir eu à les enregistrer explicitement.
        $customerId = CustomerAuth::id();
        if ($customerId !== null && $fulfilment === Status::DELIVERY) {
            (new CustomerRepository())->updateProfile($customerId, [
                'name'          => $validator->value('customer_name'),
                'phone'         => $validator->value('phone') ?: null,
                'address_line1' => $validator->value('shipping_address_line1'),
                'address_line2' => $validator->value('shipping_address_line2') ?: null,
                'postal_code'   => $validator->value('shipping_postal_code'),
                'city'          => $validator->value('shipping_city'),
            ]);
        }

        // --- Session de paiement ------------------------------------------------
        try {
            $session = StripeGateway::createCheckoutSession(
                $cart['lines'],
                $shippingCents,
                mb_strtolower($validator->value('email')),
                $reference,
                $orderId
            );

            $orders->attachStripeSession($orderId, $session['id']);

            redirect($session['url'], 303);
        } catch (Throwable $e) {
            // La commande reste en base au statut « en attente de paiement » :
            // elle sert de trace si le client rappelle, et ne décompte rien.
            error_log('Création de la session Stripe impossible : ' . $e);

            return $this->renderForm(
                [],
                $validator->values(),
                "Le paiement n'a pas pu être lancé. Réessayez dans un instant ; si le problème persiste, contactez-nous."
            );
        }

        return '';
    }

    /** Retour du client après paiement. */
    public function confirmation(): string
    {
        $sessionId = (string) ($_GET['session_id'] ?? '');

        if ($sessionId === '') {
            return $this->message(
                'Commande introuvable',
                "Le lien de confirmation est incomplet. Si vous avez été débité, écrivez-nous : nous retrouverons votre commande."
            );
        }

        // On interroge Stripe directement : la page peut être atteinte avant
        // que le webhook n'ait fini son travail, et c'est Stripe qui sait si
        // le paiement a abouti.
        $paid = false;
        try {
            $session = StripeGateway::client()->checkout->sessions->retrieve($sessionId);
            $paid = $session->payment_status === 'paid';
        } catch (Throwable $e) {
            error_log('Session Stripe illisible : ' . $e);
        }

        $order = (new OrderRepository())->findByStripeSession($sessionId);

        if ($order === null) {
            return $this->message(
                'Commande introuvable',
                "Nous n'avons pas retrouvé cette commande. Si vous avez été débité, écrivez-nous avec la date et le montant."
            );
        }

        if (!$paid && $order['status'] === Status::ORDER_PENDING) {
            return $this->message(
                'Paiement en cours de vérification',
                "Votre commande {$order['reference']} est enregistrée, mais le paiement n'est pas encore confirmé. "
                . 'Rechargez cette page dans quelques instants ; vous recevrez un courriel dès la confirmation.'
            );
        }

        // Le panier n'a plus lieu d'être une fois la commande confirmée.
        Cart::clear();

        $full = (new OrderRepository())->find((int) $order['id']);

        return View::render('boutique/confirmation', [
            'title'     => 'Commande confirmée',
            'canonical' => '/commande/confirmation',
            'noindex'   => true,
            'order'     => $full,
            'shop'      => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function renderForm(array $errors, array $values, ?string $message = null): string
    {
        $cart = Cart::resolve();
        $fulfilment = $values['fulfilment'] ?? Status::DELIVERY;

        // Préremplissage depuis le compte, au premier affichage seulement :
        // après une erreur de validation, c'est la saisie du client qui prime.
        $client = CustomerAuth::user();
        if ($client !== null && $values === []) {
            $values = array_filter([
                'customer_name'          => $client['name'],
                'email'                  => $client['email'],
                'phone'                  => $client['phone'],
                'shipping_address_line1' => $client['address_line1'],
                'shipping_address_line2' => $client['address_line2'],
                'shipping_postal_code'   => $client['postal_code'],
                'shipping_city'          => $client['city'],
                // Le client cherchera presque toujours un point relais près de
                // chez lui : autant lui épargner la saisie.
                'relay_search_postal_code' => $client['postal_code'],
                'relay_search_city'        => $client['city'],
            ], static fn (mixed $valeur): bool => $valeur !== null && $valeur !== '');
        }

        $relais = $this->relaySearch($values);

        return View::render('boutique/commande', [
            'title'          => 'Votre commande',
            'canonical'      => '/commande',
            'noindex'        => true,
            'cart'           => $cart,
            'points'         => (new PickupPointRepository())->active(),
            'relayPoints'    => $relais['points'],
            'relayError'     => $relais['error'],
            'relayAvailable' => Shipping::relayAvailable(),
            'errors'         => $errors,
            'values'         => $values,
            'message'        => $message,
            'fulfilment'     => $fulfilment,
            'shippingCents'  => Shipping::cents($cart['subtotal_cents'], $fulfilment),
            'stripeReady'    => StripeGateway::isConfigured(),
            'client'         => $client,
            'shop'           => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }

    /**
     * Retrouve un point relais par son code, en relançant la recherche autour
     * de la même adresse. Le transporteur est la seule source de vérité sur
     * l'adresse du point : elle n'est jamais reprise du formulaire.
     *
     * @throws CarrierException
     */
    private function findRelayByCode(string $code, string $postalCode, string $city): ?RelayPoint
    {
        $carrier = Carriers::get();

        // Le détail d'un point donné, quand le transporteur sait le fournir :
        // c'est l'adresse la plus sûre, et un appel plutôt qu'une liste.
        $point = $carrier->relayPoint($code);

        if ($point !== null) {
            return $point;
        }

        // Sinon on relance la recherche et on retrouve le point dedans : cela
        // vérifie au passage qu'il était bien dans la liste proposée.
        foreach ($carrier->relayPointsNear($postalCode, $city) as $candidat) {
            if ($candidat->code === $code) {
                return $candidat;
            }
        }

        return null;
    }

    /**
     * Les points relais à montrer, s'il y a de quoi chercher.
     *
     * Un transporteur en panne ne bloque pas la commande : le message explique
     * la situation et les autres modes de livraison restent ouverts.
     *
     * @param array<string, mixed> $values
     * @return array{points: list<RelayPoint>, error: string|null}
     */
    private function relaySearch(array $values): array
    {
        $postalCode = trim((string) ($values['relay_search_postal_code'] ?? ''));

        if (!Shipping::relayAvailable() || !preg_match('/^\d{5}$/', $postalCode)) {
            return ['points' => [], 'error' => null];
        }

        try {
            return [
                'points' => Carriers::get()->relayPointsNear(
                    $postalCode,
                    trim((string) ($values['relay_search_city'] ?? ''))
                ),
                'error' => null,
            ];
        } catch (CarrierException $e) {
            error_log('Recherche de points relais impossible : ' . $e);

            return [
                'points' => [],
                'error'  => 'La liste des points relais est indisponible pour le moment. '
                    . 'Réessayez dans un moment, ou choisissez un autre mode de livraison.',
            ];
        }
    }

    private function message(string $heading, string $body): string
    {
        return View::render('boutique/message', [
            'title'   => $heading,
            'noindex' => true,
            'heading' => $heading,
            'body'    => $body,
        ]);
    }
}
