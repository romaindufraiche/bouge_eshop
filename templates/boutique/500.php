<?php
use Bouge\Support\View;

echo View::partial('boutique/message', [
    'heading' => 'Une erreur est survenue',
    'body'    => "Quelque chose s'est mal passé de notre côté. Réessayez dans un instant ; si cela persiste, écrivez-nous.",
]);
