<?php
/**
 * Correspondance genres IGDB (anglais) → libellés français pour oeuvre_jeu.genre.
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbGenreMap
{
    /** @var array<string, string> clé = nom IGDB en minuscules */
    private const MAP = [
        'point-and-click' => 'Point-and-click',
        'fighting' => 'Combat',
        'shooter' => 'FPS',
        'music' => 'Musique',
        'platform' => 'Plateforme',
        'puzzle' => 'Réflexion',
        'racing' => 'Course',
        'real time strategy (rts)' => 'RTS',
        'role-playing (rpg)' => 'RPG',
        'simulator' => 'Simulation',
        'sport' => 'Sport',
        'strategy' => 'Stratégie',
        'turn-based strategy (tbs)' => 'TBS',
        'tactical' => 'Tactique',
        "hack and slash/beat 'em up" => "Beat'em up",
        'quiz/trivia' => 'Quiz',
        'pinball' => 'Flipper',
        'adventure' => 'Aventure',
        'indie' => 'Indé',
        'arcade' => 'Arcade',
        'visual novel' => 'Visual novel',
        'card & board game' => 'Jeu de cartes / plateau',
        'moba' => 'MOBA',
    ];

    /**
     * Traduit une liste de noms IGDB en tags français (séparés par des virgules).
     *
     * @param list<string> $igdbGenres
     */
    public static function translateList(array $igdbGenres): string
    {
        $translated = [];
        foreach ($igdbGenres as $name) {
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
