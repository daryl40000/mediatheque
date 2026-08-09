<?php
/**
 * Échelle de notation d’une série magazine (plafond libre : 5, 6, 10, 50…).
 *
 * Stockage : entier positif en texte dans series.rating_scale (NULL = pas de notation).
 * toPercent() uniformise sur 100 : règle de trois, ou table manuelle pour les étoiles entières.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineRatingScale
{
    /** Plafond max accepté pour une échelle (évite les saisies aberrantes). */
    public const MAX_ALLOWED = 1000;

    /** Plafond max pour une table d’équivalence étoiles (échelles &lt; 10). */
    public const STAR_MAP_MAX = 9;

    /**
     * Normalise une valeur formulaire / BDD → chaîne du plafond (« 5 », « 50 ») ou null.
     *
     * Accepte encore d’anciennes valeurs (« percent », « % ») → 100.
     */
    public static function normalize(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            $n = (int) round((float) $raw);

            return self::clampMaxOrNull($n);
        }

        $raw = strtolower(trim((string) $raw));
        if ($raw === '' || $raw === 'none' || $raw === 'aucune') {
            return null;
        }

        // Ancienne clé « en % ».
        if ($raw === 'percent' || $raw === '%' || $raw === 'pct') {
            return '100';
        }

        $raw = str_replace(',', '.', $raw);
        // « sur 20 », « /20 », « 20 pts »
        if (preg_match('/(\d+(?:\.\d+)?)/', $raw, $m) === 1) {
            $n = (int) round((float) $m[1]);

            return self::clampMaxOrNull($n);
        }

        return null;
    }

    private static function clampMaxOrNull(int $n): ?string
    {
        if ($n < 1 || $n > self::MAX_ALLOWED) {
            return null;
        }

        return (string) $n;
    }

    /** Libellé affiché (ex. « Sur 20 »). */
    public static function label(?string $scale): string
    {
        $scale = self::normalize($scale);
        if ($scale === null) {
            return 'Aucune';
        }

        return 'Sur ' . $scale;
    }

    /** Maximum de l’échelle (0 si aucune). */
    public static function maxValue(?string $scale): float
    {
        $scale = self::normalize($scale);

        return $scale === null ? 0.0 : (float) $scale;
    }

    /** Affichage en étoiles si le plafond est strictement inférieur à 10. */
    public static function usesStars(?string $scale): bool
    {
        $max = self::maxValue($scale);

        return $max > 0.0 && $max < 10.0;
    }

    /**
     * Parse une note saisie. Chaîne vide → null (effacer).
     * Hors bornes → message d’erreur string.
     *
     * @return float|null|string
     */
    public static function parseScore(mixed $raw, ?string $scale): float|null|string
    {
        $scale = self::normalize($scale);
        if ($scale === null) {
            return 'Ce numéro n’a pas d’échelle de notation.';
        }

        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        if (is_string($raw)) {
            $raw = str_replace(',', '.', trim($raw));
        }
        if (!is_numeric($raw)) {
            return 'Note invalide.';
        }

        $score = (float) $raw;
        if (!is_finite($score)) {
            return 'Note invalide.';
        }

        $max = self::maxValue($scale);
        if ($score < 0.0 || $score > $max) {
            return 'La note doit être entre 0 et ' . self::formatNumber($max) . '.';
        }

        // Arrondi au demi-point le plus proche pour rester cohérent avec l’UI.
        $score = round($score * 2) / 2;

        if ($score < 0.0) {
            $score = 0.0;
        }
        if ($score > $max) {
            $score = $max;
        }

        return $score;
    }

    /**
     * Uniformise la note sur 100.
     *
     * - Échelles ≥ 10 : toujours règle de trois.
     * - Étoiles (&lt; 10) + table d’équivalence : notes entières via la table ;
     *   demi-étoiles ou note absente de la table → règle de trois.
     * - Table vide / absente → règle de trois.
     *
     * @param array<int, float>|null $starPercentMap ex. [0 => 15.0, 1 => 40.0, …]
     */
    public static function toPercent(?float $score, ?string $scale, ?array $starPercentMap = null): ?float
    {
        if ($score === null) {
            return null;
        }
        $max = self::maxValue($scale);
        if ($max <= 0.0) {
            return null;
        }

        if (
            self::usesStars($scale)
            && $starPercentMap !== null
            && $starPercentMap !== []
            && self::isWholeStarScore($score)
        ) {
            $starKey = (int) round($score);
            if (array_key_exists($starKey, $starPercentMap)) {
                return round((float) $starPercentMap[$starKey], 1);
            }
        }

        return round(($score / $max) * 1000) / 10;
    }

    /** True si la note est un nombre entier d’étoiles (pas 3,5). */
    public static function isWholeStarScore(float $score): bool
    {
        return abs($score - round($score)) < 0.001;
    }

    /**
     * Lit une table JSON / tableau PHP → map int => float, ou null si vide.
     *
     * @return array<int, float>|null
     */
    public static function parseStarPercentMap(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return null;
        }

        $map = [];
        foreach ($raw as $key => $value) {
            if (!is_numeric($key) || !is_numeric($value)) {
                continue;
            }
            $star = (int) $key;
            if ($star < 0 || $star > self::STAR_MAP_MAX) {
                continue;
            }
            $percent = (float) $value;
            if (!is_finite($percent) || $percent < 0.0 || $percent > 100.0) {
                continue;
            }
            $map[$star] = round($percent, 1);
        }

        if ($map === []) {
            return null;
        }

        ksort($map, SORT_NUMERIC);

        return $map;
    }

    /**
     * Encode la map pour la BDD (JSON) ou null.
     *
     * @param array<int, float>|null $map
     */
    public static function serializeStarPercentMap(?array $map): ?string
    {
        $map = self::parseStarPercentMap($map);
        if ($map === null) {
            return null;
        }

        // Clés en chaînes pour un JSON stable {"0":15,"1":40}.
        $payload = [];
        foreach ($map as $star => $percent) {
            $payload[(string) $star] = $percent;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * Construit la map depuis le formulaire (star_percent[0], star_percent[1]…).
     * Toutes les cases vides → null (règle de trois).
     *
     * @param array<string, mixed> $post
     * @return array<int, float>|null
     */
    public static function normalizeStarPercentMapFromPost(array $post, int $maxStars = 5): ?array
    {
        $maxStars = max(1, min(self::STAR_MAP_MAX, $maxStars));
        $raw = $post['star_percent'] ?? null;
        if (!is_array($raw)) {
            return null;
        }

        $map = [];
        for ($star = 0; $star <= $maxStars; $star++) {
            if (!array_key_exists((string) $star, $raw) && !array_key_exists($star, $raw)) {
                continue;
            }
            $value = $raw[(string) $star] ?? $raw[$star] ?? '';
            if (is_string($value)) {
                $value = str_replace(',', '.', trim($value));
                if ($value === '') {
                    continue;
                }
            }
            if (!is_numeric($value)) {
                continue;
            }
            $percent = (float) $value;
            if (!is_finite($percent) || $percent < 0.0 || $percent > 100.0) {
                continue;
            }
            $map[$star] = round($percent, 1);
        }

        return $map === [] ? null : $map;
    }

    /**
     * Extrait la map depuis une ligne série (colonne star_percent_map).
     *
     * @param array<string, mixed>|null $series
     * @return array<int, float>|null
     */
    public static function starPercentMapFromSeries(?array $series): ?array
    {
        if ($series === null) {
            return null;
        }

        return self::parseStarPercentMap($series['star_percent_map'] ?? null);
    }

    /** Libellé texte (ex. « 8/10 », « 75/100 », « 3,5/5 »). */
    public static function formatDisplay(?float $score, ?string $scale): string
    {
        if ($score === null) {
            return '';
        }
        $scale = self::normalize($scale);
        if ($scale === null) {
            return self::formatNumber($score);
        }

        // Sur 100 : affichage en pourcentage plus naturel.
        if ((int) $scale === 100) {
            return self::formatNumber($score) . ' %';
        }

        return self::formatNumber($score) . '/' . self::formatNumber(self::maxValue($scale));
    }

    /**
     * Découpe une note en étoiles plein / demi / vide (lecture seule).
     *
     * @return list<'full'|'half'|'empty'>
     */
    public static function starParts(?float $score, ?string $scale): array
    {
        if ($score === null || !self::usesStars($scale)) {
            return [];
        }

        $max = (int) self::maxValue($scale);
        $parts = [];
        for ($i = 1; $i <= $max; $i++) {
            if ($score >= $i) {
                $parts[] = 'full';
            } elseif ($score >= ($i - 0.5)) {
                $parts[] = 'half';
            } else {
                $parts[] = 'empty';
            }
        }

        return $parts;
    }

    public static function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    }
}
