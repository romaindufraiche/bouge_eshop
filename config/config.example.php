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

    // --- Stripe -------------------------------------------------------------
    // Clés disponibles sur https://dashboard.stripe.com/apikeys
    'stripe' => [
        'secret_key'     => 'sk_test_...',
        'publishable_key' => 'pk_test_...',
        // Donné par le tableau de bord Stripe au moment de créer le webhook,
        // dont l'adresse est <site_url>/webhook/stripe
        'webhook_secret' => 'whsec_...',
    ],

    // --- Courriels ----------------------------------------------------------
    // La boutique n'envoie aucun courriel elle-même : c'est Stripe qui
    // adresse le reçu de paiement au client (à activer dans le tableau de
    // bord Stripe, « Paramètres » → « Reçus par e-mail »). Vous suivez les
    // commandes à préparer depuis l'administration.

    // --- Affichage des erreurs ----------------------------------------------
    // true en développement seulement. En production, laissez false : une
    // erreur détaillée affichée au public révèle la structure du site.
    'debug' => false,
];
