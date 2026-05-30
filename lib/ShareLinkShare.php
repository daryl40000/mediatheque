<?php
/**
 * Partage d’un lien visiteur par e-mail ou Bluesky (intent public).
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkShare
{
    public static function blueskyIntentUrl(string $absoluteUrl, string $scopeLabel): string
    {
        $text = self::shareMessage($absoluteUrl, $scopeLabel);

        return 'https://bsky.app/intent/compose?text=' . rawurlencode($text);
    }

    public static function mailtoUrl(string $absoluteUrl, string $scopeLabel, string $toEmail = ''): string
    {
        $subject = MONCINE_APP_NAME . ' — ' . $scopeLabel;
        $body = self::shareMessage($absoluteUrl, $scopeLabel);
        $query = http_build_query(['subject' => $subject, 'body' => $body], '', '&', PHP_QUERY_RFC3986);
        $to = trim($toEmail);
        if ($to !== '') {
            return 'mailto:' . rawurlencode($to) . '?' . $query;
        }

        return 'mailto:?' . $query;
    }

    /**
     * Envoie le lien par e-mail via le serveur (mail()).
     */
    public static function sendByEmail(
        string $toEmail,
        string $senderName,
        string $absoluteUrl,
        string $scopeLabel,
        string $personalMessage = ''
    ): bool {
        $subject = 'Partage de ma liste — ' . $scopeLabel;
        $body = 'Bonjour,' . "\n\n"
            . $senderName . ' souhaite partager avec vous : ' . $scopeLabel . ".\n\n";
        if (trim($personalMessage) !== '') {
            $body .= trim($personalMessage) . "\n\n";
        }
        $body .= 'Lien (lecture seule, sans compte Moncine) :' . "\n"
            . $absoluteUrl . "\n\n"
            . '— ' . MONCINE_APP_NAME;

        return MailService::send($toEmail, $subject, $body);
    }

    private static function shareMessage(string $absoluteUrl, string $scopeLabel): string
    {
        return 'Voici ma liste « ' . $scopeLabel . ' » sur ' . MONCINE_APP_NAME . ' : ' . $absoluteUrl;
    }
}
