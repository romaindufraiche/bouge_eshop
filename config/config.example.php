<?php

/**
 * Configuration de la boutique BOUGE Club.
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
        'secret_key'      => 'sk_test_...',
        // Inutilisée aujourd'hui : le paiement passe par la page hébergée par
        // Stripe, et la boutique n'exécute aucun code Stripe dans le
        // navigateur. Le champ attend le jour où l'on intégrerait le
        // formulaire de carte dans la page.
        'publishable_key' => 'pk_test_...',
        // Donné par le tableau de bord Stripe au moment de créer le webhook,
        // dont l'adresse est <site_url>/webhook/stripe
        'webhook_secret'  => 'whsec_...',
    ],

    // --- Transporteur (Boxtal) ----------------------------------------------
    // Stripe encaisse ; Boxtal achemine. Identifiants du compte ouvert sur
    // https://www.boxtal.com — les mêmes que pour se connecter à leur site.
    //
    // Ces clés ne servent que si 'carrier.driver' vaut 'boxtal' dans
    // config/shop.php. Laissées vides, le point relais n'est pas proposé et
    // les étiquettes restent à acheter à la main.
    'boxtal' => [
        'user'     => '',
        'password' => '',
        // true tant que vous n'avez pas vérifié le parcours de bout en bout :
        // en mode test, aucun colis ne part et rien n'est facturé. Les
        // identifiants de test sont distincts de ceux de production.
        'test' => true,
    ],

    // --- Courriels ----------------------------------------------------------
    // La boutique envoie trois courriels : l'accusé de commande, la bienvenue
    // à l'ouverture d'un compte, et le lien de réinitialisation du mot de
    // passe.
    'mail' => [
        // Obligatoire. Sans cette adresse, aucun courriel ne part.
        // Elle doit appartenir à votre domaine : un expéditeur en @gmail.com
        // envoyé depuis votre serveur est refusé par la plupart des messageries.
        'from'      => 'contact@votre-domaine.fr',
        'from_name' => 'BOUGE Club',
        // Où arrivent les réponses des clients. Vide : la même que ci-dessus.
        'reply_to'  => '',

        // SMTP : fortement recommandé. Sans lui, PHP poste le courriel depuis
        // le serveur web sans authentification, et Gmail comme Outlook le
        // rangent souvent en indésirable.
        //
        // Chez OVH, ce sont les réglages de votre boîte : ssl0.ovh.net,
        // port 587, chiffrement « tls », et l'adresse complète en identifiant.
        // Laisser 'host' vide retombe sur la fonction mail() de PHP.
        'smtp' => [
            'host'       => '',
            'port'       => 587,
            // 'tls' pour le port 587, 'ssl' pour le 465, '' pour aucun.
            'encryption' => 'tls',
            'user'       => '',
            'password'   => '',
            'timeout'    => 12,
        ],
    ],

    // Le reçu de paiement, lui, reste l'affaire de Stripe : activez-le dans
    // le tableau de bord, « Paramètres » → « Reçus par e-mail ». Le client
    // reçoit donc deux messages, qui ne disent pas la même chose — la
    // boutique confirme la commande et le point de retrait, Stripe atteste
    // du paiement.

    // --- Affichage des erreurs ----------------------------------------------
    // true en développement seulement. En production, laissez false : une
    // erreur détaillée affichée au public révèle la structure du site.
    'debug' => false,
];
