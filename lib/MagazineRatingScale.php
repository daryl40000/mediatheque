<?php
/**
 * Échelle de notation d’une série magazine (plafond libre : 5, 6, 10, 50…).
 *
 * Stockage : entier positif en texte dans series.rating_scale (NULL = pas de notation).
 * toPercent() uniformise sur 100 (règle de trois) pour de futures moyennes / stats.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineRatingScale
{
    /** Plafond max accepté pour une échelle (évite les saisies aberrantes). */
    public const MAX_ALLOWED = 1000;

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
     * Uniformise la note sur 100 (règle de trois) pour moyennes / stats.
     */
    public static function toPercent(?float $score, ?string $scale): ?float
    {
        if ($score === null) {
            return null;
        }
        $max = self::maxValue($scale);
        if ($max <= 0.0) {
            return null;
        }

        return round(($score / $max) * 1000) / 10;
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
