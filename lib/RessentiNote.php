<?php
/**
 * Ressenti subjectif sur une œuvre (1–5), distinct du questionnaire « ce soir ».
 */

declare(strict_types=1);

namespace Moncine;

final class RessentiNote
{
    public const MIN_SCORE = 1;

    public const MAX_SCORE = 5;

    /** @var array<string, array{label: string, score: int, css: string}> */
    public const LEVELS = [
        'deteste' => ['label' => 'Je déteste', 'score' => 1, 'css' => 'ressenti--deteste'],
        'aime_pas_trop' => ['label' => 'J\'aime pas trop', 'score' => 2, 'css' => 'ressenti--aime-pas-trop'],
        'bof' => ['label' => 'Bof', 'score' => 3, 'css' => 'ressenti--bof'],
        'aime_bien' => ['label' => 'J\'aime bien', 'score' => 4, 'css' => 'ressenti--aime-bien'],
        'adore' => ['label' => 'J\'adore', 'score' => 5, 'css' => 'ressenti--adore'],
    ];

    /** @var array<int, string> */
    private const SCORE_TO_KEY = [
        1 => 'deteste',
        2 => 'aime_pas_trop',
        3 => 'bof',
        4 => 'aime_bien',
        5 => 'adore',
    ];

    public static function isValidKey(string $key): bool
    {
        return isset(self::LEVELS[$key]);
    }

    public static function label(string $key): string
    {
        return self::LEVELS[$key]['label'] ?? $key;
    }

    public static function labelFromScore(?int $score): string
    {
        $key = self::keyFromScore($score);

        return $key !== null ? self::label($key) : '';
    }

    public static function score(string $key): int
    {
        return self::LEVELS[$key]['score'] ?? 0;
    }

    public static function cssClass(string $key): string
    {
        return self::LEVELS[$key]['css'] ?? 'ressenti--unknown';
    }

    public static function cssClassFromScore(?int $score): string
    {
        $key = self::keyFromScore($score);

        return $key !== null ? self::cssClass($key) : '';
    }

    public static function keyFromScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $score = (int) $score;

        return self::SCORE_TO_KEY[$score] ?? null;
    }

    public static function normalizeScore(?int $score): ?int
    {
        if ($score === null) {
            return null;
        }

        $score = (int) $score;
        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            return null;
        }

        return $score;
    }

    /** Convertit une ancienne note 1–10 (import) vers le score ressenti 1–5. */
    public static function scoreFromLegacyTen(?int $noteSur10): ?int
    {
        if ($noteSur10 === null) {
            return null;
        }

        $noteSur10 = (int) $noteSur10;
        if ($noteSur10 <= 0) {
            return null;
        }

        return match (true) {
            $noteSur10 <= 2 => 1,
            $noteSur10 <= 4 => 2,
            $noteSur10 <= 6 => 3,
            $noteSur10 <= 8 => 4,
            default => 5,
        };
    }

    /**
     * @return array{ok: true, score: ?int}|array{ok: false, error: string}
     */
    public static function parseInput(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => true, 'score' => null];
        }

        if (self::isValidKey($raw)) {
            return ['ok' => true, 'score' => self::score($raw)];
        }

        if (!is_numeric($raw)) {
            return [
                'ok' => false,
                'error' => 'Ressenti invalide. Choisissez l’un des cinq paliers proposés.',
            ];
        }

        $legacy = ImportCsv::parseNote($raw);
        if ($legacy !== null && $legacy > self::MAX_SCORE) {
            $legacy = self::scoreFromLegacyTen($legacy);
        }

        $score = self::normalizeScore($legacy);
        if ($score === null) {
            return [
                'ok' => false,
                'error' => 'Ressenti invalide. Choisissez l’un des cinq paliers proposés.',
            ];
        }

        return ['ok' => true, 'score' => $score];
    }

    /** @return list<string> */
    public static function orderedKeys(): array
    {
        return array_keys(self::LEVELS);
    }

    /** Condition SQL pour une note ressenti valide (1–5). */
    public static function sqlValidNote(string $alias = 'h'): string
    {
        return $alias . '.note IS NOT NULL AND ' . $alias . '.note >= ' . self::MIN_SCORE
            . ' AND ' . $alias . '.note <= ' . self::MAX_SCORE;
    }

    /**
     * Marque HTML de l’icône (PNG si disponible, sinon SVG).
     */
    public static function iconSvg(string $key): string
    {
        if (self::hasRasterIcon($key)) {
            return self::rasterIconHtml($key);
        }

        return self::vectorIconSvg($key);
    }

    public static function iconUrl(string $key): ?string
    {
        if (!self::hasRasterIcon($key)) {
            return null;
        }

        return '/assets/icons/ressenti/' . rawurlencode($key) . '.png';
    }

    public static function hasRasterIcon(string $key): bool
    {
        if (!isset(self::LEVELS[$key])) {
            return false;
        }

        return is_file(self::rasterIconPath($key));
    }

    private static function rasterIconPath(string $key): string
    {
        return MONCINE_ROOT . '/www/assets/icons/ressenti/' . $key . '.png';
    }

    private static function rasterIconHtml(string $key): string
    {
        $url = self::iconUrl($key) ?? '/assets/icons/ressenti/' . rawurlencode($key) . '.png';

        return '<img class="ressenti-icon-img" src="' . $url . '" alt="" width="24" height="24"'
            . ' aria-hidden="true" decoding="async" loading="lazy">';
    }

    private static function vectorIconSvg(string $key): string
    {
        return match ($key) {
            'aime_bien' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 9V5a3 3 0 0 0-6 0v4H5v10h14V9h-5zm-4-4a1 1 0 0 1 2 0v4h-2V5z"/></svg>',
            'bof' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="10" r="1.2" fill="currentColor"/><circle cx="15" cy="10" r="1.2" fill="currentColor"/><path d="M9 15h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            'aime_pas_trop' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 9V5a3 3 0 0 0-6 0v4H1v10h14V9H10zm-4-4a1 1 0 0 1 2 0v4H6V5zm12 13H8v-2h10v2z"/></svg>',
            'deteste' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 21s-6.7-4.4-9.2-8.3C1.1 9.8 2.6 6 6.2 6c2 0 3.2 1.2 3.8 2.2.6-1 1.8-2.2 3.8-2.2 3.6 0 5.1 3.8 3.4 6.7C18.7 16.6 12 21 12 21z"/><path d="M5 5l14 14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
            default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
        };
    }
}
