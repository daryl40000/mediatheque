<?php
/**
 * Parse les réponses texte de l’API « développement » abandonware-magazines.org.
 *
 * Format : champs séparés par « ; », enregistrements souvent séparés par des balises <br>.
 */

declare(strict_types=1);

namespace Moncine;

final class AbmApiParser
{
    private const ISSUES_HEADER_MARKER = 'identifiant du numéro';

    private const LOGO_BASE_URL = 'https://www.abandonware-magazines.org/images_logomags/';

    /**
     * @return list<array{abm_magazine_id: int, titre: string, logo_filename: string}>
     */
    public static function parseMagazinesList(string $raw): array
    {
        $raw = self::normalizeRaw($raw);
        if ($raw === '') {
            return [];
        }

        /** @var array<int, array{abm_magazine_id: int, titre: string, logo_filename: string}> $magazines */
        $magazines = [];

        foreach (self::splitLines($raw) as $line) {
            if ($line === '' || str_contains(mb_strtolower($line), 'identifiant du magazine')) {
                continue;
            }

            $parts = array_map(trim(...), explode(';', $line));
            if (count($parts) < 3) {
                continue;
            }

            $id = (int) $parts[0];
            $titre = $parts[1];
            $logo = $parts[2];
            if ($id <= 0 || $titre === '' || $logo === '') {
                continue;
            }

            $magazines[$id] = [
                'abm_magazine_id' => $id,
                'titre' => $titre,
                'logo_filename' => $logo,
            ];
        }

        return array_values($magazines);
    }

    /**
     * @return list<array{
     *   abm_issue_id: int,
     *   abm_magazine_id: int,
     *   magazine_titre: string,
     *   is_cd: bool,
     *   hors_serie: bool,
     *   numero: string,
     *   cover_filename: string,
     *   date_label: string,
     *   cover_url: string
     * }>
     */
    public static function parseIssuesDump(string $raw): array
    {
        $raw = self::normalizeRaw($raw);
        if ($raw === '') {
            return [];
        }

        $issues = [];
        foreach (self::splitLines($raw) as $line) {
            if ($line === '' || str_contains(mb_strtolower($line), self::ISSUES_HEADER_MARKER)) {
                continue;
            }

            $parts = array_map(trim(...), explode(';', $line));
            if (count($parts) < 9) {
                continue;
            }

            $rawCoverUrl = (string) array_pop($parts);
            $dateLabel = (string) array_pop($parts);
            $filename = (string) array_pop($parts);
            $numero = (string) array_pop($parts);
            $hsFlag = (string) array_pop($parts);
            $cdFlag = (string) array_pop($parts);
            $magazineId = (int) array_pop($parts);
            $magazineTitre = (string) array_pop($parts);
            $issueId = (int) array_pop($parts);

            if ($issueId <= 0 || $magazineId <= 0 || $magazineTitre === '' || $rawCoverUrl === '') {
                continue;
            }

            $issues[] = [
                'abm_issue_id' => $issueId,
                'abm_magazine_id' => $magazineId,
                'magazine_titre' => $magazineTitre,
                'is_cd' => self::flagIsSet($cdFlag),
                'hors_serie' => self::flagIsSet($hsFlag) || stripos($hsFlag, 'HS') !== false,
                'numero' => $numero,
                'cover_filename' => $filename,
                'date_label' => $dateLabel,
                'cover_url' => self::normalizeCoverUrl($rawCoverUrl),
            ];
        }

        return $issues;
    }

    /**
     * Regroupe les numéros par revue et fusionne avec la liste des magazines (choixapi=12).
     *
     * @param list<array{abm_magazine_id: int, titre: string, logo_filename: string}> $magazines
     * @param list<array<string, mixed>> $issues
     * @return list<array<string, mixed>>
     */
    public static function buildCatalogExport(array $magazines, array $issues): array
    {
        /** @var array<int, array<string, mixed>> $byMagId */
        $byMagId = [];

        foreach ($magazines as $mag) {
            $id = (int) ($mag['abm_magazine_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byMagId[$id] = [
                'abm_magazine_id' => $id,
                'titre' => (string) ($mag['titre'] ?? ''),
                'logo_filename' => (string) ($mag['logo_filename'] ?? ''),
                'logo_url' => self::logoUrlFromFilename((string) ($mag['logo_filename'] ?? '')),
                'issues' => [],
            ];
        }

        foreach ($issues as $issue) {
            $magId = (int) ($issue['abm_magazine_id'] ?? 0);
            if ($magId <= 0) {
                continue;
            }

            if (!isset($byMagId[$magId])) {
                $byMagId[$magId] = [
                    'abm_magazine_id' => $magId,
                    'titre' => (string) ($issue['magazine_titre'] ?? ''),
                    'logo_filename' => '',
                    'logo_url' => '',
                    'issues' => [],
                ];
            }

            $numero = (string) ($issue['numero'] ?? '');
            $horsSerie = !empty($issue['hors_serie']);
            $byMagId[$magId]['issues'][] = [
                'abm_issue_id' => (int) ($issue['abm_issue_id'] ?? 0),
                'numero' => $numero,
                'numero_ordre' => self::guessNumeroOrdre($numero, $horsSerie),
                'hors_serie' => $horsSerie,
                'is_cd' => !empty($issue['is_cd']),
                'date_label' => (string) ($issue['date_label'] ?? ''),
                'date_parution' => PublicationType::parseParutionDateLabel((string) ($issue['date_label'] ?? '')) ?? '',
                'annee' => self::extractYear((string) ($issue['date_label'] ?? '')),
                'cover_filename' => (string) ($issue['cover_filename'] ?? ''),
                'cover_url' => self::normalizeCoverUrl((string) ($issue['cover_url'] ?? '')),
            ];
        }

        $series = array_values($byMagId);
        usort($series, static fn (array $a, array $b): int => strcasecmp(
            (string) ($a['titre'] ?? ''),
            (string) ($b['titre'] ?? '')
        ));

        foreach ($series as &$serie) {
            usort(
                $serie['issues'],
                static fn (array $a, array $b): int => ((float) ($a['numero_ordre'] ?? 0)) <=> ((float) ($b['numero_ordre'] ?? 0))
                    ?: strcasecmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''))
            );
        }
        unset($serie);

        return $series;
    }

    public static function extractYear(string $dateLabel): ?int
    {
        if (preg_match('/\b((?:19|20)\d{2})\b/', $dateLabel, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function guessNumeroOrdre(string $numero, bool $horsSerie): float
    {
        $numero = trim($numero);
        if ($numero === '') {
            return $horsSerie ? 0.5 : 0.0;
        }

        if (preg_match('/^(\d+)(?:[.,](\d+))?/', $numero, $m)) {
            $base = (float) $m[1];
            if (isset($m[2]) && $m[2] !== '') {
                $base += ((float) ('0.' . $m[2]));
            } elseif ($horsSerie) {
                $base += 0.5;
            }

            return $base;
        }

        return $horsSerie ? 0.5 : 0.0;
    }

    /**
     * Corrige les URLs de couverture ABM pour l’export JSON.
     * L’API renvoie parfois des espaces littéraux dans le chemin (ex. « PC Team ») :
     * ils sont encodés en %20 pour que l’URL reste valide en JSON et à l’import,
     * tout en pointant vers le bon fichier sur le serveur ABM.
     */
    public static function normalizeCoverUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        if (str_contains(strtolower($url), 'abandonware-magazines.org')) {
            $parts = parse_url($url);
            if (is_array($parts) && isset($parts['path'])) {
                $parts['path'] = self::encodeSpacesInUrlPath((string) $parts['path']);
                $url = self::buildHttpUrl($parts);
            }
        }

        return SecureUrl::sanitizePosterUrl($url);
    }

    /** Encode les espaces du chemin URL (%20 = espace ASCII en notation URL). */
    private static function encodeSpacesInUrlPath(string $path): string
    {
        if ($path === '' || !str_contains($path, ' ')) {
            return $path;
        }

        $segments = explode('/', $path);
        foreach ($segments as $i => $segment) {
            if ($segment === '' || !str_contains($segment, ' ')) {
                continue;
            }
            $segments[$i] = str_replace(' ', '%20', $segment);
        }

        return implode('/', $segments);
    }

    private static function normalizeRaw(string $raw): string
    {
        $raw = preg_replace('/<br\s*\/?>/iu', "\n", $raw) ?? $raw;
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($raw);
    }

    /** @return list<string> */
    private static function splitLines(string $raw): array
    {
        $lines = preg_split('/\R/u', $raw) ?: [];

        return array_map(trim(...), $lines);
    }

    private static function flagIsSet(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && $value !== '0';
    }

    private static function logoUrlFromFilename(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '';
        }
        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            return self::normalizeCoverUrl($filename);
        }

        return self::LOGO_BASE_URL . rawurlencode($filename);
    }

    /** @param array<string, mixed> $parts */
    private static function buildHttpUrl(array $parts): string
    {
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . (string) $parts['fragment'] : '';

        return $scheme . '://' . $host . $path . $query . $fragment;
    }
}
