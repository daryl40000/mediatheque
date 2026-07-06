<?php
/**
 * Temps de jeu : formatage, total Steam + manuel, saisie formulaire.
 */

declare(strict_types=1);

namespace Moncine;

final class GamePlaytime
{
    public static function isAvailable(): bool
    {
        return GameSteamStatsRepository::isAvailable()
            || GameSchema::hasManualPlaytimeColumn();
    }

    /** Expression SQL pour trier / sommer le temps total (Steam + manuel). */
    public static function totalMinutesSql(string $bibAlias = 'b'): string
    {
        $steam = GameSteamStatsRepository::isAvailable()
            ? 'COALESCE(gss.playtime_minutes, 0)'
            : '0';
        $manual = GameSchema::hasManualPlaytimeColumn()
            ? 'COALESCE(' . $bibAlias . '.manual_playtime_minutes, 0)'
            : '0';

        return '(' . $steam . ' + ' . $manual . ')';
    }

    public static function totalMinutes(int $steamMinutes, int $manualMinutes): int
    {
        return max(0, $steamMinutes) + max(0, $manualMinutes);
    }

    public static function format(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'Jamais joué';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($hours <= 0) {
            return $mins . ' min';
        }
        if ($mins === 0) {
            return $hours . ' h';
        }

        return $hours . ' h ' . $mins . ' min';
    }

    /**
     * @return array{hours: int, minutes: int}
     */
    public static function splitMinutes(int $totalMinutes): array
    {
        $totalMinutes = max(0, $totalMinutes);

        return [
            'hours' => intdiv($totalMinutes, 60),
            'minutes' => $totalMinutes % 60,
        ];
    }

    /** @param array<string, mixed> $post */
    public static function manualMinutesFromPost(array $post): int
    {
        if (!GameSchema::hasManualPlaytimeColumn()) {
            return 0;
        }

        if (array_key_exists('manual_playtime_minutes_total', $post)) {
            return max(0, (int) $post['manual_playtime_minutes_total']);
        }

        $hours = max(0, (int) ($post['manual_playtime_hours'] ?? 0));
        $minutes = max(0, min(59, (int) ($post['manual_playtime_minutes_part'] ?? 0)));

        return min(9_999_999, ($hours * 60) + $minutes);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateRow(array $row): array
    {
        $steamMinutes = (int) ($row['steam_playtime_minutes'] ?? 0);
        $manualMinutes = GameSchema::hasManualPlaytimeColumn()
            ? (int) ($row['manual_playtime_minutes'] ?? 0)
            : 0;
        $totalMinutes = self::totalMinutes($steamMinutes, $manualMinutes);

        $row['manual_playtime_minutes'] = $manualMinutes;
        $row['manual_playtime_label'] = self::format($manualMinutes);
        $row['steam_playtime_minutes'] = $steamMinutes;
        $row['steam_playtime_label'] = self::format($steamMinutes);
        $row['playtime_minutes'] = $totalMinutes;
        $row['playtime_label'] = self::format($totalMinutes);
        $row['steam_never_played'] = $steamMinutes === 0;
        $row['never_played'] = $totalMinutes === 0;
        $row['has_manual_playtime'] = $manualMinutes > 0;
        $row['has_steam_playtime'] = $steamMinutes > 0;

        return $row;
    }
}
