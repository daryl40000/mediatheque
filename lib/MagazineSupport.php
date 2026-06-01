<?php
/**
 * Supports magazine sous forme de tags (papier, pdf).
 * Stockage en base : chaîne « papier,pdf » dans bibliotheque.support_physique.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineSupport
{
    public const TAG_PDF = 'pdf';
    public const TAG_PAPIER = 'papier';

    /** @return array<string, string> clé tag => libellé */
    public static function labelMap(): array
    {
        return [
            self::TAG_PAPIER => 'Papier',
            self::TAG_PDF => 'PDF',
        ];
    }

    public static function label(string $tag): string
    {
        $tag = self::normalizeTag($tag);

        return self::labelMap()[$tag] ?? $tag;
    }

    /**
     * @return list<string> tags normalisés (papier, pdf)
     */
    public static function parseTags(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (str_contains($raw, ',')) {
            $tags = [];
            foreach (explode(',', $raw) as $part) {
                $tag = self::normalizeTag($part);
                if ($tag !== '' && isset(self::labelMap()[$tag])) {
                    $tags[$tag] = $tag;
                }
            }

            return array_values($tags);
        }

        return self::parseLegacyFreeText($raw);
    }

    /**
     * @return list<string>
     */
    public static function formatTags(bool $hasPaper, bool $hasPdf): array
    {
        $tags = [];
        if ($hasPaper) {
            $tags[] = self::TAG_PAPIER;
        }
        if ($hasPdf) {
            $tags[] = self::TAG_PDF;
        }

        return $tags;
    }

    public static function formatTagsForStorage(bool $hasPaper, bool $hasPdf): string
    {
        return implode(',', self::formatTags($hasPaper, $hasPdf));
    }

    public static function hasPaper(string $raw): bool
    {
        return in_array(self::TAG_PAPIER, self::parseTags($raw), true);
    }

    public static function hasPdf(string $raw): bool
    {
        return in_array(self::TAG_PDF, self::parseTags($raw), true);
    }

    /**
     * Tags à afficher (base + PDF si fichier rattaché, pour anciennes fiches non resynchronisées).
     *
     * @param array<string, mixed> $issue
     *
     * @return list<string>
     */
    public static function tagsForIssue(array $issue): array
    {
        $tags = self::parseTags((string) ($issue['support_physique'] ?? ''));
        if ((int) ($issue['stored_object_id'] ?? 0) > 0 && !in_array(self::TAG_PDF, $tags, true)) {
            $tags[] = self::TAG_PDF;
        }

        return $tags;
    }

    private static function normalizeTag(string $tag): string
    {
        $tag = mb_strtolower(trim($tag));

        return match ($tag) {
            'pdf', 'numérique', 'numerique', 'demat', 'démat', 'dematerialise', 'dématérialisé' => self::TAG_PDF,
            'papier', 'paper', 'physique', 'print' => self::TAG_PAPIER,
            default => preg_replace('/[^a-z0-9_-]+/', '', $tag) ?? '',
        };
    }

    /** Anciennes saisies libres (« Papier + PDF », etc.). */
    private static function parseLegacyFreeText(string $raw): array
    {
        $lower = mb_strtolower($raw);
        $tags = [];

        if (str_contains($lower, 'pdf') || str_contains($lower, 'démat') || str_contains($lower, 'demat')) {
            $tags[self::TAG_PDF] = self::TAG_PDF;
        }
        if (str_contains($lower, 'papier') || str_contains($lower, 'physique') || str_contains($lower, 'paper')) {
            $tags[self::TAG_PAPIER] = self::TAG_PAPIER;
        }

        return array_values($tags);
    }
}
