<?php

/**
 * Réglages commerciaux.
 *
 * Tout ce qui se règle sans toucher au code métier : frais de port, franco,
 * coordonnées. Modifier une valeur ici suffit.
 */

return [
    'name'     => 'BOUGE.',
    // Baseline officielle de la marque, présente dans le logo.
    'baseline' => 'Sport et bien plus.',
    // Ce que vend la boutique, pour les titres de page et le référencement.
    'tagline'  => 'Matériel de natation',
    'email'    => 'contact@bouge.fr',
    // Laisser vide pour masquer la ligne dans le pied de page.
    'phone'    => '',

    'shipping' => [
        // Frais de port forfaitaires pour la France métropolitaine, en centimes.
        'flat_rate_cents' => 490,
        // Montant de panier à partir duquel la livraison est offerte.
        // Mettre null pour désactiver le franco de port.
        'free_above_cents' => 6000,
        // Le retrait sur place est toujours gratuit.
        'pickup_cents' => 0,
    ],

    'cart' => [
        // Garde-fou : quantité maximale par ligne.
        'max_quantity_per_line' => 20,
        // Nombre maximal de lignes distinctes dans un panier.
        'max_lines' => 50,
    ],

    'uploads' => [
        // Taille maximale par photo, en octets.
        'max_bytes' => 5 * 1024 * 1024,
        // Types acceptés, et extension utilisée à l'enregistrement.
        'allowed_types' => [
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'image/webp' => '.webp',
        ],
    ],
];
