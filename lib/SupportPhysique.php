<?php
/**
 * Type de support physique : DVD, Blu-ray, Blu-ray 4K.
 */

declare(strict_types=1);

namespace Moncine;

final class SupportPhysique
{
    public const DVD = 'dvd';
    public const BLURAY = 'bluray';
    public const BLURAY_4K = 'bluray_4k';

    /** @return array<string, string> clé interne => libellé affiché */
    public static function choices(): array
    {
        return [
            self::DVD => 'DVD',
            self::BLURAY => 'Blu-ray',
            self::BLURAY_4K => 'Blu-ray 4K',
        ];
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && $key !== '' && isset(self::choices()[$key]);
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return self::choices()[$key] ?? '';
    }

    /** Normalise une saisie import ou formulaire vers une clé interne (ou vide). */
    public static function normalize(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $norm = mb_strtolower($raw, 'UTF-8');
        $norm = str_replace(['é', 'è', 'ê'], 'e', $norm);
        $norm = preg_replace('/\s+/', ' ', $norm) ?? $norm;

        if ($norm === 'dvd') {
            return self::DVD;
        }
        if (in_array($norm, ['blu-ray', 'bluray', 'blu ray', 'bd'], true)) {
            return self::BLURAY;
        }
        if (in_array($norm, ['blu-ray 4k', 'bluray 4k', 'blu ray 4k', '4k', 'uhd', 'blu-ray 4k uhd'], true)) {
            return self::BLURAY_4K;
        }
        if (str_contains($norm, '4k') && str_contains($norm, 'blu')) {
            return self::BLURAY_4K;
        }

        foreach (self::choices() as $key => $label) {
            $labelNorm = mb_strtolower($label, 'UTF-8');
            $labelNorm = str_replace(['é', 'è', 'ê'], 'e', $labelNorm);
            if ($norm === $labelNorm) {
                return $key;
            }
        }

        return '';
    }
}
