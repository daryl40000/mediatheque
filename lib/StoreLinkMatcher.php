<?php
/**
 * Score de correspondance titre catalogue ↔ résultat magasin.
 */

declare(strict_types=1);

namespace Moncine;

final class StoreLinkMatcher
{
    public const AUTO_VERIFY_THRESHOLD = 0.85;

    public const MIN_STORE_THRESHOLD = 0.60;

    /**
     * @param list<array{title: string, slug: string, product_id?: int}> $candidates
     * @return array{best: ?array{title: string, slug: string, product_id?: int}, confidence: float}
     */
    public static function bestMatch(string $catalogTitle, array $candidates, ?int $catalogYear = null): array
    {
        $catalogTitle = trim($catalogTitle);
        if ($catalogTitle === '' || $candidates === []) {
            return ['best' => null, 'confidence' => 0.0];
        }

        $best = null;
        $bestConfidence = 0.0;

        foreach ($candidates as $candidate) {
            $candidateTitle = trim((string) ($candidate['title'] ?? ''));
            $slug = trim((string) ($candidate['slug'] ?? ''));
            if ($candidateTitle === '' || $slug === '') {
                continue;
            }

            $confidence = self::confidence($catalogTitle, $candidateTitle, $catalogYear);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $best = $candidate;
            }
        }

        return [
            'best' => $best,
            'confidence' => round($bestConfidence, 4),
        ];
    }

    public static function confidence(string $catalogTitle, string $candidateTitle, ?int $catalogYear = null): float
    {
        $catalogTitle = trim($catalogTitle);
        $candidateTitle = trim($candidateTitle);
        if ($catalogTitle === '' || $candidateTitle === '') {
            return 0.0;
        }

        $catalogFold = SearchMatch::fold($catalogTitle);
        $candidateFold = SearchMatch::fold($candidateTitle);

        if ($catalogFold === $candidateFold) {
            return 1.0;
        }

        if (!SearchMatch::matches($candidateTitle, $catalogTitle, 1)) {
            return 0.0;
        }

        $score = SearchMatch::score($candidateTitle, $catalogTitle);
        $confidence = match (true) {
            $score <= 0 => 0.92,
            $score <= 10 => 0.82,
            $score <= 30 => 0.72,
            default => max(0.0, 0.65 - ($score / 500.0)),
        };

        if (str_contains($candidateFold, $catalogFold) || str_contains($catalogFold, $candidateFold)) {
            $confidence = min(1.0, $confidence + 0.05);
        }

        if (self::editionPenalty($catalogTitle, $candidateTitle) > 0) {
            $confidence = max(0.0, $confidence - 0.15);
        }

        if ($catalogYear !== null && $catalogYear > 0) {
            // Pas d’année côté API magasin en v1 — léger bonus si titres très proches déjà.
            unset($catalogYear);
        }

        return min(1.0, max(0.0, $confidence));
    }

    private static function editionPenalty(string $catalogTitle, string $candidateTitle): int
    {
        $catalogEdition = StoreTitleNormalizer::stripEditionWords($catalogTitle);
        $candidateEdition = StoreTitleNormalizer::stripEditionWords($candidateTitle);
        $catalogBase = SearchMatch::fold($catalogEdition);
        $candidateBase = SearchMatch::fold($candidateEdition);

        if ($catalogBase === $candidateBase) {
            return 0;
        }

        if ($catalogEdition !== $catalogTitle || $candidateEdition !== $candidateTitle) {
            return 1;
        }

        return 0;
    }
}
