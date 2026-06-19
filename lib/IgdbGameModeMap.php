<?php
/**
 * Correspondance modes de jeu IGDB (anglais) → libellés français pour oeuvre_jeu.game_mode.
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbGameModeMap
{
    /** @var array<string, string> */
    private const MAP = [
        'single player' => 'Solo',
        'multiplayer' => 'Multijoueur',
        'co-operative' => 'Coopératif',
        'cooperative' => 'Coopératif',
        'split screen' => 'Écran partagé',
        'massively multiplayer online (mmo)' => 'MMO',
        'mmo' => 'MMO',
        'battle royale' => 'Battle royale',
    ];

    /**
     * @param list<string> $igdbModes
     */
    public static function translateList(array $igdbModes): string
    {
        $translated = [];
        foreach ($igdbModes as $name) {
            $label = self::translateOne($name);
            if ($label !== '') {
                $translated[] = $label;
            }
        }

        return GameGenre::serializeList($translated);
    }

    public static function translateOne(string $igdbName): string
    {
        $igdbName = trim($igdbName);
        if ($igdbName === '') {
            return '';
        }

        $key = mb_strtolower($igdbName);
        if (isset(self::MAP[$key])) {
            return self::MAP[$key];
        }

        return $igdbName;
    }
}
