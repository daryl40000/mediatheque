<?php
/**
 * Correspondance thèmes IGDB (anglais) → libellés français pour oeuvre_jeu.theme.
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbThemeMap
{
    /** @var array<string, string> */
    private const MAP = [
        'non-fiction' => 'Non-fiction',
        'fantasy' => 'Fantasy',
        'science fiction' => 'Science-fiction',
        'horror' => 'Horreur',
        'thriller' => 'Thriller',
        'survival' => 'Survie',
        'comedy' => 'Comédie',
        'romance' => 'Romance',
        'open world' => 'Monde ouvert',
        'mystery' => 'Mystère',
        'sandbox' => 'Bac à sable',
        'party' => 'Party game',
        'warfare' => 'Guerre',
        'stealth' => 'Infiltration',
        'historical' => 'Historique',
        'kids' => 'Jeunesse',
        '4x (explore, expand, exploit, and exterminate)' => '4X',
        '4x' => '4X',
        'education' => 'Éducation',
        'fitness' => 'Fitness',
    ];

    /**
     * @param list<string> $igdbThemes
     */
    public static function translateList(array $igdbThemes): string
    {
        $translated = [];
        foreach ($igdbThemes as $name) {
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
