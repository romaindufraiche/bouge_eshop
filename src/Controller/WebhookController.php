<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\OrderRepository;
use Bouge\Support\Config;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Webhook Stripe.
 *
 * C'est ici, et nulle part ailleurs, qu'une commande devient « payée ».
 * Le retour du navigateur sur la page de confirmation ne prouve rien : il
 * peut être rejoué, interrompu ou fabriqué. Seul cet appel signé par Stripe
 * fait foi, et c'est donc lui qui décompte le stock.
 *
 * Mise en place :
 *   - en local : stripe listen --forward-to localhost:8000/webhook/stripe
 *   - en ligne : tableau de bord Stripe > Développeurs > Webhooks,
 *     adresse <site>/webhook/stripe, événement checkout.session.completed
 */
final class WebhookController
{
    public function stripe(): string
    {
        header('Content-Type: application/json');

        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        // Le corps doit rester brut : la signature porte sur les octets exacts.
        $payload = file_get_contents('php://input') ?: '';
        $secret = (string) Config::get('stripe.webhook_secret', '');

        if ($signature === '' || $secret === '') {
            http_response_code(400);

            return json_encode(['error' => 'Signature absente.']) ?: '';
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            // La requête ne vient pas de Stripe : on l'ignore.
            error_log('Signature de webhook Stripe invalide : ' . $e->getMessage());
            http_response_code(400);

            return json_encode(['error' => 'Signature invalide.']) ?: '';
        }

        try {
            $session = $event->data->object;
            $orderId = (int) ($session->metadata->order_id ?? 0);

            if ($orderId === 0) {
                error_log('Événement Stripe sans order_id : ' . $event->id);

                return json_encode(['received' => true]) ?: '';
            }

            $orders = new OrderRepository();

            switch ($event->type) {
                case 'checkout.session.completed':
                case 'checkout.session.async_payment_succeeded':
                    if (($session->payment_status ?? '') === 'paid') {
                        $paymentIntent = $session->payment_intent ?? null;
                        $orders->markPaid(
                            $orderId,
                            is_string($paymentIntent) ? $paymentIntent : ($paymentIntent->id ?? null)
                        );
                    }
                    break;

                case 'checkout.session.expired':
                case 'checkout.session.async_payment_failed':
                    $orders->cancelIfPending($orderId);
                    break;

                default:
                    // Les autres événements ne nous concernent pas.
                    break;
            }
        } catch (Throwable $e) {
            // On renvoie une erreur pour que Stripe rejoue l'événement plus
            // tard, plutôt que de perdre définitivement un paiement encaissé.
            error_log("Traitement du webhook {$event->type} en échec : " . $e);
            http_response_code(500);

            return json_encode(['error' => 'Traitement en échec.']) ?: '';
        }

        return json_encode(['received' => true]) ?: '';
    }
}
