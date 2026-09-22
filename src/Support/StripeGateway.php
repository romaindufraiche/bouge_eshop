<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;
use Stripe\StripeClient;

/**
 * Accès à Stripe.
 *
 * Le client est instancié à la première utilisation : le site s'affiche et se
 * parcourt même sans clé configurée, seul le paiement en a besoin.
 */
final class StripeGateway
{
    private static ?StripeClient $client = null;

    public static function isConfigured(): bool
    {
        $key = (string) Config::get('stripe.secret_key', '');

        return $key !== '' && !str_contains($key, '...');
    }

    public static function client(): StripeClient
    {
        if (self::$client instanceof StripeClient) {
            return self::$client;
        }

        if (!self::isConfigured()) {
            throw new RuntimeException(
                'Clé Stripe absente. Renseignez stripe.secret_key dans config/config.php.'
            );
        }

        self::$client = new StripeClient((string) Config::get('stripe.secret_key'));

        return self::$client;
    }

    /**
     * Crée la session de paiement et renvoie l'URL vers laquelle envoyer le
     * client.
     *
     * Les montants proviennent exclusivement du panier relu en base : rien de
     * ce qui touche à l'argent ne vient du formulaire.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array{id: string, url: string}
     */
    public static function createCheckoutSession(
        array $lines,
        int $shippingCents,
        string $email,
        string $reference,
        int $orderId,
    ): array {
        // Stripe télécharge les images depuis Internet : inutile de lui passer
        // des adresses locales, qu'il ne pourra pas atteindre.
        $siteUrl = rtrim((string) Config::get('site_url', ''), '/');
        $imagesReachable = str_starts_with($siteUrl, 'https://');

        $lineItems = [];
        foreach ($lines as $line) {
            $name = $line['variant_label'] === null
                ? $line['name']
                : $line['name'] . ' — ' . $line['variant_label'];

            $productData = ['name' => $name];
            if ($imagesReachable && $line['image_url'] !== null) {
                $productData['images'] = [$siteUrl . $line['image_url']];
            }

            $lineItems[] = [
                'quantity'   => $line['quantity'],
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => $line['unit_price_cents'],
                    'product_data' => $productData,
                ],
            ];
        }

        $payload = [
            'mode'                => 'payment',
            'locale'              => 'fr',
            'customer_email'      => $email,
            'client_reference_id' => $reference,
            'metadata'            => ['order_id' => (string) $orderId, 'reference' => $reference],
            'line_items'          => $lineItems,
            'success_url'         => $siteUrl . '/commande/confirmation?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'          => $siteUrl . '/panier',
        ];

        // Les frais de port passent par une option de livraison plutôt que par
        // une ligne d'article : ils apparaissent ainsi correctement dans les
        // rapports du tableau de bord Stripe.
        if ($shippingCents > 0) {
            $payload['shipping_options'] = [[
                'shipping_rate_data' => [
                    'type'         => 'fixed_amount',
                    'display_name' => 'Livraison France',
                    'fixed_amount' => ['amount' => $shippingCents, 'currency' => 'eur'],
                ],
            ]];
        }

        $session = self::client()->checkout->sessions->create($payload);

        if ($session->url === null) {
            throw new RuntimeException("Stripe n'a pas renvoyé d'URL de paiement.");
        }

        return ['id' => $session->id, 'url' => $session->url];
    }
}
