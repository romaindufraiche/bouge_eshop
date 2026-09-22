<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;

/**
 * Stockage des photos produits.
 *
 * Les fichiers sont écrits dans public/uploads, servis directement par le
 * serveur web. Ce dossier refuse d'exécuter du code (voir son .htaccess) :
 * c'est la dernière barrière si un fichier indésirable franchissait les
 * contrôles ci-dessous.
 */
final class Uploads
{
    /**
     * Enregistre une photo et renvoie l'adresse à stocker en base.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     * @throws RuntimeException si le fichier est refusé
     */
    public static function store(array $file): string
    {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException(
                "« {$file['name']} » dépasse la taille autorisée par le serveur."
            );
        }

        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
            throw new RuntimeException("« {$file['name']} » n'a pas pu être envoyé.");
        }

        $maxBytes = (int) Config::shop('uploads.max_bytes', 5 * 1024 * 1024);
        if ($file['size'] > $maxBytes) {
            $mo = (int) round($maxBytes / 1024 / 1024);
            throw new RuntimeException(
                "« {$file['name']} » dépasse {$mo} Mo. Réduisez la photo avant de l'envoyer."
            );
        }

        // Le type est lu dans le CONTENU du fichier, pas dans l'en-tête envoyé
        // par le navigateur, qu'un client peut annoncer comme il veut.
        $detected = self::detectMimeType($file['tmp_name']);

        /** @var array<string, string> $allowed */
        $allowed = Config::shop('uploads.allowed_types', []);

        if (!isset($allowed[$detected])) {
            throw new RuntimeException(
                "« {$file['name']} » n'est pas dans un format accepté. Utilisez du JPEG, PNG ou WebP."
            );
        }

        $directory = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Le dossier d'envoi n'a pas pu être créé.");
        }

        // Nom aléatoire : deux photos du même nom ne s'écrasent pas, et le nom
        // d'origine — non maîtrisé — ne se retrouve pas dans une adresse.
        $filename = bin2hex(random_bytes(16)) . $allowed[$detected];
        $destination = $directory . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            // En test, le fichier n'a pas été envoyé par HTTP : on retombe sur
            // une copie simple.
            if (!copy($file['tmp_name'], $destination)) {
                throw new RuntimeException("« {$file['name']} » n'a pas pu être enregistré.");
            }
        }

        chmod($destination, 0644);

        return '/uploads/' . $filename;
    }

    /**
     * Supprime une photo du stockage.
     * Ne lève jamais : un fichier déjà absent ne doit pas empêcher la
     * suppression du produit correspondant.
     */
    public static function delete(string $url): void
    {
        if (!str_starts_with($url, '/uploads/')) {
            return;
        }

        // On ne garde que le nom du fichier : un chemin contenant « ../ » ne
        // peut pas faire sortir du dossier d'envoi.
        $filename = basename($url);
        $path = dirname(__DIR__, 2) . '/public/uploads/' . $filename;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function detectMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $type = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($type)) {
                    return $type;
                }
            }
        }

        // Repli : getimagesize ne reconnaît qu'une véritable image.
        $info = @getimagesize($path);

        return is_array($info) && isset($info['mime']) ? (string) $info['mime'] : 'application/octet-stream';
    }
}
