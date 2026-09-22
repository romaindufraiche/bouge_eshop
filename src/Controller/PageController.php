<?php

declare(strict_types=1);

namespace Bouge\Controller;

use Bouge\Repository\PickupPointRepository;
use Bouge\Support\View;

/** Pages éditoriales : mentions légales, CGV, livraison. */
final class PageController
{
    public function legal(): string
    {
        return View::render('boutique/mentions-legales', [
            'title'     => 'Mentions légales',
            'canonical' => '/mentions-legales',
            'noindex'   => true,
            'shop'      => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }

    public function terms(): string
    {
        return View::render('boutique/cgv', [
            'title'     => 'Conditions générales de vente',
            'canonical' => '/cgv',
            'noindex'   => true,
            'shop'      => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }

    public function shipping(): string
    {
        return View::render('boutique/livraison', [
            'title'       => 'Livraison et retrait',
            'description' => 'Modes de livraison en France, frais de port, seuil de '
                . 'livraison offerte et points de retrait.',
            'canonical'   => '/livraison',
            'points'      => (new PickupPointRepository())->active(),
            'shop'        => require dirname(__DIR__, 2) . '/config/shop.php',
        ]);
    }
}
