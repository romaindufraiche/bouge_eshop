<?php

/**
 * Réglages commerciaux.
 *
 * Tout ce qui se règle sans toucher au code métier : frais de port, franco,
 * coordonnées. Modifier une valeur ici suffit.
 */

return [
    // BOUGE est le concept store ; BOUGE Club est sa boutique en ligne.
    // Le mot « Club » est composé dans une autre typographie que le logo :
    // voir la règle .club dans public/assets/css/site.css.
    'name'      => 'BOUGE Club',
    'name_mark' => 'BOUGE',
    'name_suffix' => 'Club',
    // Baseline officielle de la marque, présente dans le logo.
    'baseline' => 'Sport et bien plus.',
    // Ce que vend la boutique, pour les titres de page et le référencement.
    'tagline'  => 'Matériel de natation',
    'email'    => 'contact@bouge.fr',
    // Laisser vide pour masquer la ligne dans le pied de page.
    'phone'    => '',

    // --- La salle ----------------------------------------------------------
    // BOUGE est d'abord un lieu : une salle de sport doublée d'un concept
    // store. Ce site en est le rayon matériel, ouvert à toute heure — pas
    // l'inverse. Le bloc de rappel disparaît si 'name' est vide ; le bouton
    // n'est affiché que si 'url' est renseignée — un lien mort vaut moins que
    // pas de lien du tout.
    'store' => [
        'name'    => 'BOUGE',
        // Le texte du bloc « le lieu », en paragraphes. Il explique ce que la
        // boutique est par rapport à la salle : un prolongement, pas une
        // enseigne de plus. Une chaîne simple fonctionne aussi, pour qui veut
        // un seul paragraphe.
        'pitch'   => [
            "BOUGE est un concept store dans lequel on peut faire du sport : on y pousse de la fonte, on y boit un café, on peut récupérer avec un ostéo. C'est un lieu avant d'être une marque, et c'est là que tout a commencé.",
            "BOUGE Club en est le rayon matériel : de quoi s'équiper sans courir les magasins la veille d'une séance. Ce qu'on vend, c'est ce qu'on utilise au bassin.",
            "Et une commande passée ici ne part pas chez une enseigne : elle fait vivre le lieu.",
            "Le reste se vit sur place. Poussez la porte, on vous fera visiter.",
        ],
        'url'     => '',
        'address' => '8 rue Albert Simonin, 92400 Courbevoie',
        'hours'   => 'Du mardi au samedi, 10h – 19h',

        // Le plan affiché sous la mascotte. Il vient d'OpenStreetMap : pas de
        // JavaScript à charger, pas de compte à ouvrir, et aucun mouchard —
        // contrairement à l'équivalent chez Google, qui imposerait une
        // bannière de consentement.
        //
        // Coordonnées du 8 rue Albert Simonin, relevées au numéro près auprès
        // du géocodeur de l'IGN (data.geopf.fr) plutôt qu'au centre de la rue.
        //
        // Pour une autre adresse : https://data.geopf.fr/geocodage/search?q=...
        // renvoie la latitude et la longitude ; sur openstreetmap.org, un clic
        // droit sur le point puis « Afficher l'adresse » donne la même chose.
        //
        // Laisser 'lat' ou 'lon' vide masque le plan.
        'map' => [
            'lat'  => '48.8958110',
            'lon'  => '2.2554090',
            // Plus le nombre est grand, plus on est près : 15 montre le
            // quartier, 17 la rue, 18 le pâté de maisons.
            'zoom' => 17,
        ],
    ],

    'shipping' => [
        // Frais de port forfaitaires pour la France métropolitaine, en centimes.
        // Ce sont les tarifs facturés au client, pas ceux que le transporteur
        // nous facture : la marge — ou la perte — est la différence.
        'flat_rate_cents' => 490,
        // Le point relais coûte moins cher au transporteur qu'un passage à
        // domicile : le client doit y voir son intérêt, sinon il ne le choisit
        // pas. Mettre null pour ne pas proposer le point relais du tout.
        'relay_cents' => 390,
        // Montant de panier à partir duquel la livraison est offerte.
        // Mettre null pour désactiver le franco de port.
        'free_above_cents' => 6000,
        // Le retrait sur place est toujours gratuit.
        'pickup_cents' => 0,
        // Poids retenu pour un produit dont la fiche ne dit rien. Le
        // transporteur facture au poids : mieux vaut une estimation haute
        // qu'un colis refusé au dépôt.
        'default_weight_grams' => 300,
        // Poids de l'emballage, ajouté une fois par colis.
        'packaging_grams' => 120,
    ],

    // --- Le transporteur -----------------------------------------------------
    // Stripe encaisse ; il ne fabrique pas d'étiquette. Pour que l'acheteur
    // puisse choisir un point relais et que le vendeur n'ait plus qu'à
    // imprimer, il faut un second prestataire, branché ici.
    //
    // Tant que 'driver' est vide, le site fonctionne exactement comme avant :
    // livraison à domicile au forfait et retrait sur place. L'option « point
    // relais » n'est pas proposée, plutôt que de l'être sans pouvoir tenir la
    // promesse.
    'carrier' => [
        // '' pour aucun, 'boxtal' une fois le compte ouvert.
        //
        // La variable d'environnement permet de changer de pilote sans
        // toucher au fichier : c'est ainsi que la démonstration sur Render
        // active les points relais inventés. En local et sur un mutualisé,
        // elle n'existe pas et la valeur ci-dessous s'applique.
        'driver' => getenv('BOUGE_CARRIER_DRIVER') ?: '',

        // Adresse d'expédition, imprimée sur l'étiquette et point de départ
        // du calcul de tarif. C'est celle de la salle.
        'from' => [
            'company'     => 'BOUGE',
            'address'     => '8 rue Albert Simonin',
            'postal_code' => '92400',
            'city'        => 'Courbevoie',
            'country'     => 'FR',
            'phone'       => '',
            'email'       => 'contact@bouge.fr',
        ],
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
