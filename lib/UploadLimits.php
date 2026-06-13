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

    public static function maxPdfBytes(): int
    {
        return defined('MONCINE_PDF_MAX_BYTES') ? (int) MONCINE_PDF_MAX_BYTES : 350 * 1024 * 1024;
    }

    /** Fichiers joints jeux (même plafond que les PDF magazines). */
    public static function maxAttachmentBytes(): int
    {
        return self::maxPdfBytes();
    }

    public static function maxAttachmentBytesLabel(): string
    {
        return self::formatBytesLabel(self::maxAttachmentBytes());
    }

    public static function attachmentTooLargeApplicationMessage(): string
    {
        return 'Fichier trop volumineux (maximum ' . self::maxAttachmentBytesLabel() . ' autorisé par l’application).';
    }

    public static function phpAllowsAttachmentUpload(): bool
    {
        return self::phpAllowsUploadOfSize(self::maxAttachmentBytes(), 512 * 1024);
    }

    public static function maxPosterBytes(): int
    {
        return defined('MONCINE_POSTER_MAX_BYTES') ? (int) MONCINE_POSTER_MAX_BYTES : 10 * 1024 * 1024;
    }

    public static function maxPostersZipBytes(): int
    {
        return defined('MONCINE_POSTERS_ZIP_MAX_BYTES') ? (int) MONCINE_POSTERS_ZIP_MAX_BYTES : 200 * 1024 * 1024;
    }

    public static function maxPdfBytesLabel(): string
    {
        return self::formatBytesLabel(self::maxPdfBytes());
    }

    public static function maxPosterBytesLabel(): string
    {
        return self::formatBytesLabel(self::maxPosterBytes());
    }

    public static function maxPostersZipBytesLabel(): string
    {
        return self::formatBytesLabel(self::maxPostersZipBytes());
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

    public static function currentUploadMaxBytes(): int
    {
        return self::parseIniSize(self::uploadMaxFilesizeLabel());
    }

    public static function currentPostMaxBytes(): int
    {
        return self::parseIniSize(self::postMaxSizeLabel());
    }

    /**
     * Les limites PHP actuelles permettent-elles d’envoyer un PDF magazine ?
     */
    public static function phpAllowsPdfUpload(): bool
    {
        return self::phpAllowsUploadOfSize(self::maxPdfBytes(), 512 * 1024);
    }

    /** Affiche / couverture (fichier image unique). */
    public static function phpAllowsPosterUpload(): bool
    {
        return self::phpAllowsUploadOfSize(self::maxPosterBytes(), 256 * 1024);
    }

    /** Archive ZIP d’affiches (import admin). */
    public static function phpAllowsPostersZipUpload(): bool
    {
        return self::phpAllowsUploadOfSize(self::maxPostersZipBytes(), 1024 * 1024);
    }

    /** Message d’avertissement si les limites PHP bloquent les gros PDF. */
    public static function phpLimitsWarning(): string
    {
        if (self::phpAllowsPdfUpload()) {
            return '';
        }

        return 'Les limites PHP du serveur sont trop basses pour importer un PDF magazine '
            . '(upload_max_filesize = ' . self::uploadMaxFilesizeLabel()
            . ', post_max_size = ' . self::postMaxSizeLabel()
            . ', maximum application = ' . self::maxPdfBytesLabel() . '). '
            . self::phpLimitsHintHtml();
    }

    /** Avertissement si PHP bloque les affiches ou le ZIP d’affiches. */
    public static function posterLimitsWarning(): string
    {
        $parts = [];
        if (!self::phpAllowsPosterUpload()) {
            $parts[] = 'affiche fichier (max application ' . self::maxPosterBytesLabel() . ')';
        }
        if (!self::phpAllowsPostersZipUpload()) {
            $parts[] = 'ZIP d’affiches (max application ' . self::maxPostersZipBytesLabel() . ')';
        }

        if ($parts === []) {
            return '';
        }

        return 'Les limites PHP du serveur sont trop basses pour : '
            . implode(', ', $parts)
            . ' (upload_max_filesize = ' . self::uploadMaxFilesizeLabel()
            . ', post_max_size = ' . self::postMaxSizeLabel() . '). '
            . self::phpLimitsHintHtml();
    }

    /**
     * @return list<string> messages HTML (alertes) non vides
     */
    public static function phpLimitsWarnings(): array
    {
        $warnings = [];
        foreach ([self::phpLimitsWarning(), self::posterLimitsWarning()] as $message) {
            if ($message !== '') {
                $warnings[] = $message;
            }
        }

        return $warnings;
    }

    public static function postTooLargeMessage(): string
    {
        return 'Fichier trop volumineux pour PHP (post_max_size = '
            . self::postMaxSizeLabel()
            . ', upload_max_filesize = '
            . self::uploadMaxFilesizeLabel()
            . '). Le fichier dépasse la limite du serveur : augmentez ces valeurs dans la configuration PHP '
            . '(voir www/.user.ini) ou contactez l’administrateur.';
    }

    public static function pdfTooLargeApplicationMessage(): string
    {
        return 'PDF trop volumineux (maximum ' . self::maxPdfBytesLabel() . ' autorisé par l’application).';
    }

    public static function fileUploadErrorMessage(int $error, string $fieldLabel = 'Fichier'): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $fieldLabel . ' : ' . self::postTooLargeMessage(),
            UPLOAD_ERR_PARTIAL => $fieldLabel . ' partiellement reçu — réessayez l’envoi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire serveur manquant — contactez l’administrateur.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d’écrire le fichier sur le serveur.',
            UPLOAD_ERR_EXTENSION => 'Type de fichier refusé par le serveur.',
            default => $fieldLabel . ' : erreur d’envoi (code ' . $error . ').',
        };
    }

    /**
     * Vérifie limites PHP + jeton CSRF avant traitement d’un formulaire multipart.
     *
     * @param array<string, string> $fileFields clé $_FILES => libellé affiché (ex. pdf_file => PDF)
     */
    public static function guardPostWithFiles(array $post, string $redirectUrl, array $fileFields = []): void
    {
        if (self::postBodyWasDiscarded()) {
            self::redirectWithError($redirectUrl, self::postTooLargeMessage());
        }

        foreach ($fileFields as $field => $label) {
            if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
                continue;
            }

            $error = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                self::redirectWithError($redirectUrl, self::fileUploadErrorMessage($error, $label));
            }
        }

        if (!Csrf::validateFromPost($post)) {
            $message = Csrf::REJECT_MESSAGE;
            if (self::postBodyWasDiscarded() || self::multipartLikelyTooLarge()) {
                $message = self::postTooLargeMessage();
            }

            self::redirectWithError($redirectUrl, $message);
        }
    }

    /** POST quasi vide mais requête multipart : limite PHP souvent en cause. */
    public static function multipartLikelyTooLarge(): bool
    {
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

        return str_contains(strtolower($type), 'multipart/form-data')
            && ($_POST === [] || !isset($_POST[Csrf::FIELD_NAME]));
    }

    private static function redirectWithError(string $url, string $message): void
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        header('Location: ' . $url . $separator . 'error=' . rawurlencode($message));
        exit;
    }

    private static function phpAllowsUploadOfSize(int $maxBytes, int $postMarginBytes): bool
    {
        $needPost = $maxBytes + $postMarginBytes;

        return self::currentUploadMaxBytes() >= $maxBytes
            && self::currentPostMaxBytes() >= $needPost;
    }

    private static function phpLimitsHintHtml(): string
    {
        return 'En local, lancez le site avec <code>./start-dev.sh</code>. '
            . 'Sur un hébergement, augmentez <code>upload_max_filesize</code> et '
            . '<code>post_max_size</code> (<code>www/.user.ini</code> ou panneau). '
            . 'Avec Nginx, vérifiez aussi <code>client_max_body_size</code>.';
    }

    public static function formatBytesLabel(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return (string) (int) round($bytes / (1024 * 1024)) . ' Mo';
        }

        if ($bytes >= 1024) {
            return (string) (int) round($bytes / 1024) . ' Ko';
        }

        return (string) $bytes . ' o';
    }

    /** Convertit une taille PHP (ex. 350M, 8M) en octets. */
    private static function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($value, -1));
        if (in_array($unit, ['g', 'm', 'k'], true)) {
            $num = (float) substr($value, 0, -1);

            return match ($unit) {
                'g' => (int) round($num * 1024 * 1024 * 1024),
                'm' => (int) round($num * 1024 * 1024),
                'k' => (int) round($num * 1024),
            };
        }

        return (int) $value;
    }
}
