<?php
use Bouge\Support\View;

echo View::partial('boutique/message', [
    'heading' => 'Page introuvable',
    'body'    => "Cette page n'existe pas ou plus. Le produit a peut-être été retiré du catalogue.",
]);
