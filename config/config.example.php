<?php

/**
 * Configuration de la boutique BOUGE.
 *
 * Copiez ce fichier en `config/config.php` et renseignez vos valeurs.
 * `config.php` n'est jamais versionné : il contient vos mots de passe.
 */

return [
    // --- Base de données ----------------------------------------------------
    // Ces quatre valeurs vous sont données par votre hébergeur, dans la
    // rubrique « Bases de données » de votre espace client.
    'db' => [
        'host'     => 'localhost',
        'name'     => 'bouge',
        'user'     => 'bouge',
        'password' => 'a-remplacer',
        // Laissez vide sauf si votre hébergeur impose un port ou une socket.
        'port'   => '',
        'socket' => '',
    ],

    // --- Adresse publique du site -------------------------------------------
    // Sans slash final. Sert aux URL de retour Stripe et aux balises SEO.
    'site_url' => 'https://www.bouge.fr',

    // --- Sécurité -----------------------------------------------------------
    // Clé secrète utilisée pour signer les sessions. Générez-en une avec :
    //   php -r "echo bin2hex(random_bytes(32));"
    'app_key' => 'a-remplacer-par-une-cle-aleatoire-de-64-caracteres',

    // --- Stripe -------------------------------------------------------------
    // Clés disponibles sur https://dashboard.stripe.com/apikeys
    'stripe' => [
        'secret_key'     => 'sk_test_...',
        'publishable_key' => 'pk_test_...',
        // Donné par le tableau de bord Stripe au moment de créer le webhook,
        // dont l'adresse est <site_url>/webhook/stripe
        'webhook_secret' => 'whsec_...',
    ],

    // --- Envoi des courriels ------------------------------------------------
    // Adresse expéditrice des confirmations de commande. Sur la plupart des
    // hébergements mutualisés, elle doit appartenir à votre domaine.
    'mail' => [
        'from_address' => 'contact@bouge.fr',
        'from_name'    => 'BOUGE.',
        // Mettre à false pour désactiver complètement l'envoi de courriels.
        'enabled'      => true,
    ],

    // --- Affichage des erreurs ----------------------------------------------
    // true en développement seulement. En production, laissez false : une
    // erreur détaillée affichée au public révèle la structure du site.
    'debug' => false,
];
