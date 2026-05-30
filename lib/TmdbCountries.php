<?php
/**
 * Pays de production TMDB → libellés affichés (ex. USA au lieu de « United States of America »).
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbCountries
{
    /** @var array<string, string> Code ISO 3166-1 alpha-2 → libellé affiché */
    private const LABELS_FR = [
        'AD' => 'Andorre',
        'AE' => 'Émirats arabes unis',
        'AR' => 'Argentine',
        'AT' => 'Autriche',
        'AU' => 'Australie',
        'BE' => 'Belgique',
        'BG' => 'Bulgarie',
        'BR' => 'Brésil',
        'CA' => 'Canada',
        'CH' => 'Suisse',
        'CL' => 'Chili',
        'CN' => 'Chine',
        'CO' => 'Colombie',
        'CU' => 'Cuba',
        'CZ' => 'Tchéquie',
        'DE' => 'Allemagne',
        'DK' => 'Danemark',
        'EE' => 'Estonie',
        'EG' => 'Égypte',
        'ES' => 'Espagne',
        'FI' => 'Finlande',
        'FR' => 'France',
        'GB' => 'Royaume-Uni',
        'GR' => 'Grèce',
        'HK' => 'Hong Kong',
        'HR' => 'Croatie',
        'HU' => 'Hongrie',
        'ID' => 'Indonésie',
        'IE' => 'Irlande',
        'IL' => 'Israël',
        'IN' => 'Inde',
        'IR' => 'Iran',
        'IS' => 'Islande',
        'IT' => 'Italie',
        'JP' => 'Japon',
        'KR' => 'Corée du Sud',
        'LB' => 'Liban',
        'LT' => 'Lituanie',
        'LU' => 'Luxembourg',
        'LV' => 'Lettonie',
        'MA' => 'Maroc',
        'MC' => 'Monaco',
        'MX' => 'Mexique',
        'MY' => 'Malaisie',
        'NL' => 'Pays-Bas',
        'NO' => 'Norvège',
        'NZ' => 'Nouvelle-Zélande',
        'PE' => 'Pérou',
        'PH' => 'Philippines',
        'PL' => 'Pologne',
        'PT' => 'Portugal',
        'RO' => 'Roumanie',
        'RS' => 'Serbie',
        'RU' => 'Russie',
        'SE' => 'Suède',
        'SG' => 'Singapour',
        'SI' => 'Slovénie',
        'SK' => 'Slovaquie',
        'TH' => 'Thaïlande',
        'TR' => 'Turquie',
        'TW' => 'Taïwan',
        'UA' => 'Ukraine',
        'US' => 'USA',
        'UY' => 'Uruguay',
        'VE' => 'Venezuela',
        'VN' => 'Viêt Nam',
        'ZA' => 'Afrique du Sud',
    ];

    public static function labelFromIso(string $iso): string
    {
        $iso = strtoupper(trim($iso));
        if ($iso === '') {
            return '';
        }

        return self::normalizeCountryLabel(self::LABELS_FR[$iso] ?? $iso);
    }

    /**
     * Unifie les libellés TMDB / saisis (États-Unis, United States… → USA).
     */
    public static function normalizeCountryLabel(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        if (self::isUsaLabel($name)) {
            return 'USA';
        }

        return $name;
    }

    /**
     * Un seul pays en base : le principal (le premier de la liste TMDB ou de la saisie).
     */
    public static function formatNationaliteList(string $nationalite): string
    {
        $parts = self::splitNationaliteParts($nationalite);
        if ($parts === []) {
            return '';
        }

        return self::normalizeCountryLabel($parts[0]);
    }

    /** @return list<string> */
    public static function splitNationaliteParts(string $nationalite): array
    {
        $nationalite = trim($nationalite);
        if ($nationalite === '') {
            return [];
        }

        $parts = preg_split('/[,;|\/]+/', $nationalite) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $s = trim($part);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * Pays principal TMDB : le premier pays de production (film) ou d’origine (série).
     *
     * @param array<string, mixed> $data Réponse movie/{id} ou tv/{id}
     */
    public static function nationaliteFromDetail(array $data): string
    {
        $production = $data['production_countries'] ?? null;
        if (is_array($production)) {
            foreach ($production as $country) {
                if (!is_array($country)) {
                    continue;
                }
                $name = self::normalizeCountryLabel(trim((string) ($country['name'] ?? '')));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        $origin = $data['origin_country'] ?? null;
        if (is_array($origin)) {
            foreach ($origin as $iso) {
                if (!is_string($iso)) {
                    continue;
                }
                $label = self::labelFromIso($iso);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return '';
    }

    private static function isUsaLabel(string $name): bool
    {
        $norm = mb_strtolower(trim($name), 'UTF-8');
        $norm = str_replace(['é', 'è', 'ê', 'ë'], ['e', 'e', 'e', 'e'], $norm);
        $norm = preg_replace('/\s+/u', ' ', $norm) ?? $norm;

        if (in_array($norm, ['us', 'usa', 'u.s.a.', 'u.s.a', 'u.s.', 'etats-unis', 'etats unis'], true)) {
            return true;
        }

        return str_contains($norm, 'united state')
            || str_contains($norm, 'united states')
            || $norm === 'america'
            || str_starts_with($norm, 'amerique');
    }
}
