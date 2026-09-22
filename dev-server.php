<?php

/**
 * Routeur du serveur de développement intégré à PHP.
 *
 * UNIQUEMENT pour travailler en local :
 *
 *   php -S localhost:8000 -t public dev-server.php
 *
 * Le `-t public` est indispensable : il place la racine du serveur sur le
 * dossier public, comme le fera votre hébergeur. Sans lui, `return false`
 * ferait chercher les fichiers statiques à la racine du projet.
 *
 * Ce fichier reproduit ce que fait le .htaccess en production — servir les
 * fichiers existants, envoyer tout le reste au contrôleur frontal. Le serveur
 * intégré n'est pas prévu pour la production.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path !== '/' && is_file(__DIR__ . '/public' . $path)) {
    return false; // le serveur intégré sert le fichier tel quel
}

require __DIR__ . '/public/index.php';
