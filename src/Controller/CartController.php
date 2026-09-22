<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\ProductRepository;
use Bouge\Support\Cart;
use Bouge\Support\Config;
use Bouge\Support\Csrf;
use Bouge\Support\Session;
use Bouge\Support\Shipping;
use Bouge\Support\Status;
use Bouge\Support\View;

final class CartController
{
    public function show(): string
    {
        $cart = Cart::resolve();

        return View::render('boutique/panier', [
            'title'     => 'Votre panier',
            'canonical' => '/panier',
            'noindex'   => true,
            'cart'      => $cart,
            'shop'      => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }

    /** Ajout depuis la fiche produit. */
    public function add(): string
    {
        $this->requireToken();

        $productId = (int) ($_POST['product_id'] ?? 0);
        $variantId = isset($_POST['variant_id']) && $_POST['variant_id'] !== ''
            ? (int) $_POST['variant_id']
            : null;
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $slug = (string) ($_POST['slug'] ?? '');

        $product = (new ProductRepository())->find($productId);

        // Tous les refus sont revérifiés ici : le formulaire de la page ne
        // suffit pas, une requête peut être envoyée directement.
        if ($product === null || $product['status'] !== Status::PRODUCT_PUBLISHED) {
            Session::flash('product_error', "Ce produit n'est plus disponible.");
            redirect('/boutique');
        }

        if (($product['external_url'] ?? null) !== null && $product['external_url'] !== '') {
            Session::flash('product_error', 'Ce produit est vendu par un revendeur : il ne peut pas être commandé ici.');
            redirect('/produit/' . $product['slug']);
        }

        // Un produit à déclinaisons exige d'en choisir une.
        if ($product['variants'] !== [] && $variantId === null) {
            Session::flash('product_error', 'Choisissez un modèle avant d\'ajouter au panier.');
            redirect('/produit/' . $product['slug']);
        }

        if ($variantId !== null) {
            $found = false;
            foreach ($product['variants'] as $variant) {
                if ((int) $variant['id'] === $variantId) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                Session::flash('product_error', "Ce modèle n'existe plus.");
                redirect('/produit/' . $product['slug']);
            }
        }

        Cart::add((int) $product['id'], $variantId, $quantity);
        Session::flash('shop', 'Ajouté au panier.');

        redirect('/panier');
        // @phpstan-ignore-next-line redirect() ne revient jamais
        return '';
    }

    /** Changement de quantité depuis le panier. */
    public function update(): string
    {
        $this->requireToken();

        Cart::setQuantity(
            (int) ($_POST['product_id'] ?? 0),
            isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int) $_POST['variant_id'] : null,
            (int) ($_POST['quantity'] ?? 0)
        );

        redirect('/panier');
        return '';
    }

    public function remove(): string
    {
        $this->requireToken();

        Cart::remove(
            (int) ($_POST['product_id'] ?? 0),
            isset($_POST['variant_id']) && $_POST['variant_id'] !== '' ? (int) $_POST['variant_id'] : null
        );

        redirect('/panier');
        return '';
    }

    private function requireToken(): void
    {
        if (!Csrf::isValid($_POST['_token'] ?? null)) {
            http_response_code(419);
            Session::flash('shop', 'Votre session a expiré. Réessayez.');
            redirect('/panier');
        }
    }
}
