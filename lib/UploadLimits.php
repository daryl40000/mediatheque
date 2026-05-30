<?php
/**
 * Détecte les dépassements de limites d’envoi PHP (post_max_size, upload_max_filesize).
 */

declare(strict_types=1);

namespace Moncine;

final class UploadLimits
{
    /**
     * Corps POST vide alors que le navigateur a envoyé des données : PHP a probablement tout rejeté.
     */
    public static function postBodyWasDiscarded(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        if ($_POST !== [] || $_FILES !== []) {
            return false;
        }

        return (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    public static function postMaxSizeLabel(): string
    {
        $raw = ini_get('post_max_size');

        return is_string($raw) && $raw !== '' ? $raw : '?';
    }

    public static function uploadMaxFilesizeLabel(): string
    {
        $raw = ini_get('upload_max_filesize');

        return is_string($raw) && $raw !== '' ? $raw : '?';
    }

    public static function postTooLargeMessage(): string
    {
        return 'Fichier trop volumineux pour PHP (post_max_size = '
            . self::postMaxSizeLabel()
            . ', upload_max_filesize = '
            . self::uploadMaxFilesizeLabel()
            . '). Après mise à jour du paquet Moncine, relancez : '
            . 'yunohost app upgrade moncine -u … puis systemctl restart php8.4-fpm. '
            . 'Sinon copiez le dossier posters/ en SSH vers www/posters/.';
    }
}
