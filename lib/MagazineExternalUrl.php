<?php
/**
 * Liens « consulter en ligne » pour séries et numéros de magazines.
 *
 * URL libre (HTTPS) ; construction automatique pour Abandonware Magazines.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineExternalUrl
{
    public const ABM_BASE = 'https://www.abandonware-magazines.org/affiche_mag.php';

    /** Nettoie une URL saisie : vide ou HTTPS uniquement. */
    public static function sanitize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Accepte http:// en le forçant en https (comme les couvertures ABM).
        if (str_starts_with(strtolower($url), 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return SecureUrl::isHttpsUrl($url) ? $url : '';
    }

    public static function abmSeriesUrl(int $abmMagazineId): string
    {
        if ($abmMagazineId <= 0) {
            return '';
        }

        return self::ABM_BASE . '?mag=' . $abmMagazineId;
    }

    public static function abmIssueUrl(int $abmMagazineId, int $abmIssueId): string
    {
        if ($abmMagazineId <= 0 || $abmIssueId <= 0) {
            return '';
        }

        return self::ABM_BASE . '?mag=' . $abmMagazineId . '&num=' . $abmIssueId;
    }

    /**
     * URL affichable : priorité au champ explicite, sinon reconstruction depuis notes ABM.
     *
     * @param array<string, mixed> $seriesRow
     */
    public static function resolveSeriesUrl(array $seriesRow): string
    {
        $explicit = self::sanitize((string) ($seriesRow['external_url'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $abmId = MagazineCatalogImporter::parseAbmMagazineIdFromNotes(
            (string) ($seriesRow['notes'] ?? '')
        );

        return self::abmSeriesUrl($abmId);
    }

    /**
     * @param array<string, mixed> $issueRow
     * @param array<string, mixed>|null $seriesRow
     */
    public static function resolveIssueUrl(array $issueRow, ?array $seriesRow = null): string
    {
        $explicit = self::sanitize((string) ($issueRow['external_url'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return '';
    }
}
