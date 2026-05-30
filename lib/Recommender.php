<?php
/**
 * Moteur de suggestion : filtre et note les films selon les réponses du questionnaire.
 */

declare(strict_types=1);

namespace Moncine;

final class Recommender
{
    public function __construct(
        private readonly FilmRepository $films = new FilmRepository(),
        private readonly HistoriqueRepository $historique = new HistoriqueRepository()
    ) {
    }

    /**
     * @param array{
     *   duree_film?: string,
     *   styles?: list<string>,
     *   format_image?: string,
     *   format_son?: string,
     *   vu_policy?: 'jamais'|'ancien_ok'|'peu_importe',
     *   min_days_since_view?: int,
     *   decennie?: string,
     *   nationalites?: list<string>,
     *   content_kind?: string,
     *   exclude_ids?: list<int>
     * } $criteria
     * @return list<array{film: array<string, mixed>, score: int}>
     */
    public function recommend(array $criteria, int $limit = 5, bool $shuffle = false): array
    {
        $exclude = array_map('intval', $criteria['exclude_ids'] ?? []);
        $all = $this->films->findAllRandomOrder();
        $scored = [];

        foreach ($all as $film) {
            if (in_array((int) $film['id'], $exclude, true)) {
                continue;
            }
            $score = $this->scoreFilm($film, $criteria);
            if ($score === null) {
                continue;
            }
            $scored[] = ['film' => $film, 'score' => $score + random_int(0, 8)];
        }

        if ($shuffle) {
            shuffle($scored);
        } else {
            usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        }

        return array_slice($scored, 0, $limit);
    }

    /** Tirage aléatoire parmi tous les films qui correspondent (pas seulement les premiers alphabétiques). */
    public function pickOne(array $criteria): ?array
    {
        $pool = $this->recommend($criteria, 50, true);
        if ($pool === []) {
            return null;
        }

        return $pool[random_int(0, count($pool) - 1)];
    }

    /**
     * @param array<string, mixed> $film
     * @return int|null null = exclu
     */
    private function scoreFilm(array $film, array $criteria): ?int
    {
        $score = 10;
        $filmId = (int) $film['id'];

        $vuPolicy = $criteria['vu_policy'] ?? 'peu_importe';
        $everSeen = $this->historique->wasEverSeen($filmId);
        $daysSince = $this->historique->daysSinceLastView($filmId);

        if ($vuPolicy === 'jamais' && $everSeen) {
            return null;
        }

        if ($vuPolicy === 'ancien_ok' && $everSeen) {
            $minDays = (int) ($criteria['min_days_since_view'] ?? MONCINE_MIN_DAYS_SINCE_REVIEW_OK);
            if ($daysSince !== null && $daysSince < $minDays) {
                return null;
            }
            if ($daysSince !== null && $daysSince >= $minDays) {
                $score += 5;
            }
        }

        if (!$everSeen) {
            $score += 15;
        }

        $contentKind = ContentKindFilter::normalize((string) ($criteria['content_kind'] ?? ''));
        if ($contentKind !== ContentKindFilter::ALL) {
            if (!ContentKindFilter::matchesFilter($film, $contentKind)) {
                return null;
            }
            $score += 12;
        }

        $dureeFilm = (string) ($criteria['duree_film'] ?? '');
        $duree = (int) ($film['duree_min'] ?? 0);
        if (QuizSession::dureeFilmFiltersDuration($dureeFilm) && $duree > 0) {
            if (!self::dureeMatchesCategorie($duree, $dureeFilm)) {
                return null;
            }
            $score += 10;
        }

        $decennie = (string) ($criteria['decennie'] ?? '');
        if ($decennie !== '') {
            $annee = (int) ($film['annee'] ?? 0);
            if ($annee <= 0 || !self::anneeMatchesDecennie($annee, $decennie)) {
                return null;
            }
            $score += 12;
        }

        $wantedStyles = $criteria['styles'] ?? [];
        if ($wantedStyles !== []) {
            $filmStyles = array_map(
                'mb_strtolower',
                FilmRepository::splitStyles((string) ($film['styles'] ?? ''))
            );
            $match = false;
            foreach ($wantedStyles as $wanted) {
                if (in_array(mb_strtolower($wanted, 'UTF-8'), $filmStyles, true)) {
                    $match = true;
                    $score += 20;
                }
            }
            if (!$match) {
                return null;
            }
        }

        $wantedNationalites = $criteria['nationalites'] ?? [];
        if ($wantedNationalites !== []) {
            $filmCountries = array_map(
                static fn (string $c): string => mb_strtolower(TmdbCountries::normalizeCountryLabel($c), 'UTF-8'),
                TmdbCountries::splitNationaliteParts((string) ($film['nationalite'] ?? ''))
            );
            if ($filmCountries === []) {
                return null;
            }
            $countryMatch = false;
            foreach ($wantedNationalites as $wanted) {
                $wantedNorm = mb_strtolower(TmdbCountries::normalizeCountryLabel($wanted), 'UTF-8');
                if (in_array($wantedNorm, $filmCountries, true)) {
                    $countryMatch = true;
                    $score += 18;
                }
            }
            if (!$countryMatch) {
                return null;
            }
        }

        $formatImage = trim((string) ($criteria['format_image'] ?? ''));
        if ($formatImage !== '') {
            if (stripos((string) $film['format_image'], $formatImage) === false) {
                return null;
            }
            $score += 5;
        }

        $formatSon = trim((string) ($criteria['format_son'] ?? ''));
        if ($formatSon !== '') {
            if (stripos((string) $film['format_son'], $formatSon) === false) {
                return null;
            }
            $score += 5;
        }

        return $score;
    }

    public static function anneeMatchesDecennie(int $annee, string $decennie): bool
    {
        if ($decennie === 'avant1960') {
            return $annee < 1960;
        }
        if (preg_match('/^(19|20)\d{2}$/', $decennie) !== 1) {
            return true;
        }
        $start = (int) $decennie;
        return $annee >= $start && $annee <= $start + 9;
    }

    /** 1 h 45 = 105 min, 2 h 30 = 150 min. */
    public static function dureeMatchesCategorie(int $minutes, string $categorie): bool
    {
        return match ($categorie) {
            'court' => $minutes < 105,
            'moyen' => $minutes >= 105 && $minutes <= 150,
            'long' => $minutes > 150,
            default => true,
        };
    }
}
