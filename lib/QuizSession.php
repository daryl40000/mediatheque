<?php
/**
 * Mémorise les réponses du questionnaire entre les tirages.
 */

declare(strict_types=1);

namespace Moncine;

final class QuizSession
{
    private const KEY_CRITERIA = 'moncine_quiz_criteria';
    private const KEY_EXCLUDED = 'moncine_quiz_excluded';
    private const KEY_PROPOSED = 'moncine_quiz_proposed';
    private const KEY_RATINGS = 'moncine_quiz_ratings';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // En local, le chemin système par défaut (ex. /var/lib/php/sessions) peut ne pas être
        // accessible en écriture pour l'utilisateur qui lance `php -S ...`.
        // On bascule donc vers un répertoire de sessions sous MONCINE_DATA si nécessaire.
        $savePathRaw = (string) ini_get('session.save_path');
        // Peut être de la forme "5;/var/lib/php/sessions" (préfixe = profondeur).
        $savePath = $savePathRaw;
        if (str_contains($savePath, ';')) {
            $savePath = (string) substr($savePath, strrpos($savePath, ';') + 1);
        }
        $savePath = trim($savePath);

        $savePathWritable = false;
        if ($savePath !== '' && is_dir($savePath)) {
            // is_writable() peut être trompeur selon l'environnement ; on tente un vrai write.
            $probe = rtrim($savePath, '/') . '/.moncine_session_probe_' . bin2hex(random_bytes(6));
            $savePathWritable = @file_put_contents($probe, '1') !== false;
            if ($savePathWritable) {
                @unlink($probe);
            }
        }

        if ($savePath === '' || !is_dir($savePath) || !$savePathWritable) {
            $localPath = rtrim((string) MONCINE_DATA, '/') . '/sessions';
            if (!is_dir($localPath)) {
                @mkdir($localPath, 0750, true);
            }
            if (is_dir($localPath) && is_writable($localPath)) {
                ini_set('session.save_path', $localPath);
            }
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_lifetime', '0');

        session_name('moncine_session');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => self::isHttpsRequest(),
        ]);
    }

    private static function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            return true;
        }

        return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
    }

    /**
     * @param array<string, mixed> $post
     * @return array{
     *   duree_film: string,
     *   styles: list<string>,
     *   format_image: string,
     *   format_son: string,
     *   vu_policy: string,
     *   min_days_since_view: int,
     *   decennie: string,
     *   nationalites: list<string>,
     *   content_kind: string
     * }
     */
    public static function parseFromPost(array $post): array
    {
        $vuPolicy = (string) ($post['vu_policy'] ?? 'peu_importe');
        $allowed = ['jamais', 'ancien_ok', 'peu_importe'];
        if (!in_array($vuPolicy, $allowed, true)) {
            $vuPolicy = 'peu_importe';
        }

        $decennie = (string) ($post['decennie'] ?? '');
        if (!in_array($decennie, self::decennieChoices(), true)) {
            $decennie = '';
        }

        $styles = [];
        if (isset($post['styles']) && is_array($post['styles'])) {
            $styles = array_values(array_map('strval', $post['styles']));
        }

        $nationalites = [];
        if (isset($post['nationalites']) && is_array($post['nationalites'])) {
            foreach ($post['nationalites'] as $country) {
                $label = TmdbCountries::normalizeCountryLabel((string) $country);
                if ($label !== '') {
                    $nationalites[] = $label;
                }
            }
            $nationalites = array_values(array_unique($nationalites));
        }

        $dureeFilm = (string) ($post['duree_film'] ?? 'moyen');
        if (!in_array($dureeFilm, self::dureeFilmChoices(), true)) {
            $dureeFilm = 'moyen';
        }

        return [
            'duree_film' => $dureeFilm,
            'content_kind' => ContentKindFilter::normalize((string) ($post['content_kind'] ?? '')),
            'styles' => $styles,
            'format_image' => trim((string) ($post['format_image'] ?? '')),
            'format_son' => trim((string) ($post['format_son'] ?? '')),
            'vu_policy' => $vuPolicy,
            'min_days_since_view' => MONCINE_MIN_DAYS_SINCE_REVIEW_OK,
            'decennie' => $decennie,
            'nationalites' => $nationalites,
        ];
    }

    /** Libellé du type pour le récapitulatif (vide si « peu importe »). */
    public static function contentKindSummary(array $criteria): string
    {
        $kind = ContentKindFilter::normalize((string) ($criteria['content_kind'] ?? ''));
        if ($kind === ContentKindFilter::ALL) {
            return '';
        }

        return ContentKindFilter::quizLabel($kind);
    }

    /** @param array<string, mixed> $criteria */
    public static function nationalitesSummary(array $criteria): string
    {
        $list = $criteria['nationalites'] ?? [];
        if (!is_array($list) || $list === []) {
            return '';
        }

        return implode(', ', array_map('strval', $list));
    }

    /** @return array<string, string> */
    public static function dureeFilmChoiceLabels(): array
    {
        return [
            'peu_importe' => 'Peu importe',
            'court' => 'Court (moins de 1 h 45)',
            'moyen' => 'Moyen (1 h 45 – 2 h 30)',
            'long' => 'Long (plus de 2 h 30)',
        ];
    }

    /** @return list<string> */
    public static function dureeFilmChoices(): array
    {
        return array_keys(self::dureeFilmChoiceLabels());
    }

    public static function dureeFilmLabel(string $value): string
    {
        return self::dureeFilmChoiceLabels()[$value] ?? 'Peu importe';
    }

    public static function dureeFilmFiltersDuration(string $value): bool
    {
        return $value !== '' && $value !== 'peu_importe';
    }

    /** @return list<string> Valeurs autorisées pour le filtre décennie (chaîne vide = toutes). */
    public static function decennieChoices(): array
    {
        return ['', '2020', '2010', '2000', '1990', '1980', '1970', '1960', 'avant1960'];
    }

    /** Libellé affiché pour une valeur décennie. */
    public static function decennieLabel(string $value): string
    {
        return match ($value) {
            '2020' => 'Années 2020',
            '2010' => 'Années 2010',
            '2000' => 'Années 2000',
            '1990' => 'Années 1990',
            '1980' => 'Années 1980',
            '1970' => 'Années 1970',
            '1960' => 'Années 1960',
            'avant1960' => 'Avant 1960',
            default => 'Toutes les décennies',
        };
    }

    /** @param array<string, mixed> $criteria */
    public static function save(array $criteria): void
    {
        $_SESSION[self::KEY_CRITERIA] = $criteria;
    }

    /** @return array<string, mixed>|null */
    public static function load(): ?array
    {
        $data = $_SESSION[self::KEY_CRITERIA] ?? null;
        if (!is_array($data)) {
            return null;
        }
        if (($data['vu_policy'] ?? '') === 'ancien_ok') {
            $data['min_days_since_view'] = MONCINE_MIN_DAYS_SINCE_REVIEW_OK;
        }

        return $data;
    }

    public static function hasCriteria(): bool
    {
        return self::load() !== null;
    }

    public static function clearCriteria(): void
    {
        unset($_SESSION[self::KEY_CRITERIA], $_SESSION[self::KEY_EXCLUDED], $_SESSION[self::KEY_PROPOSED]);
    }

    public static function setRating(int $filmId, string $noteKey): void
    {
        if ($filmId <= 0 || !ChoixNote::isValid($noteKey)) {
            return;
        }
        $ratings = self::getRatingsMap();
        $ratings[$filmId] = $noteKey;
        $_SESSION[self::KEY_RATINGS] = $ratings;
        self::addProposed($filmId);
    }

    public static function getRating(int $filmId): ?string
    {
        $ratings = self::getRatingsMap();
        $key = $ratings[$filmId] ?? null;
        return is_string($key) && ChoixNote::isValid($key) ? $key : null;
    }

    public static function hasRatings(): bool
    {
        return self::getRatingsMap() !== [];
    }

    /** @return array<int, string> */
    public static function getRatingsMap(): array
    {
        $raw = $_SESSION[self::KEY_RATINGS] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id => $key) {
            $filmId = (int) $id;
            if ($filmId > 0 && is_string($key) && ChoixNote::isValid($key)) {
                $out[$filmId] = $key;
            }
        }
        return $out;
    }

    /**
     * @return list<array{film_id: int, note_key: string, score: int, label: string}>
     */
    public static function getRatingsSorted(): array
    {
        $list = [];
        foreach (self::getRatingsMap() as $filmId => $noteKey) {
            $list[] = [
                'film_id' => $filmId,
                'note_key' => $noteKey,
                'score' => ChoixNote::score($noteKey),
                'label' => ChoixNote::label($noteKey),
            ];
        }
        usort($list, static function (array $a, array $b): int {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $a['film_id'] <=> $b['film_id'];
        });
        return $list;
    }

    public static function getTopScore(): ?int
    {
        $sorted = self::getRatingsSorted();
        return $sorted === [] ? null : $sorted[0]['score'];
    }

    public static function clearRatings(): void
    {
        unset($_SESSION[self::KEY_RATINGS]);
    }

    public static function clearExcluded(): void
    {
        $_SESSION[self::KEY_EXCLUDED] = [];
        $_SESSION[self::KEY_PROPOSED] = [];
    }

    public static function addExcluded(int $filmId): void
    {
        self::addProposed($filmId);
    }

    public static function addProposed(int $filmId): void
    {
        if ($filmId <= 0) {
            return;
        }
        $list = self::getProposed();
        if (!in_array($filmId, $list, true)) {
            $list[] = $filmId;
        }
        $_SESSION[self::KEY_PROPOSED] = $list;
    }

    /** @return list<int> */
    public static function getProposed(): array
    {
        $list = $_SESSION[self::KEY_PROPOSED] ?? [];
        if (!is_array($list)) {
            return [];
        }
        return array_values(array_map('intval', $list));
    }

    /**
     * Films à ne plus proposer au tirage : déjà proposés + déjà notés.
     *
     * @return list<int>
     */
    public static function getExcludeIdsForDraw(): array
    {
        $ids = array_merge(
            self::getProposed(),
            array_keys(self::getRatingsMap())
        );
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return list<int> */
    public static function getExcluded(): array
    {
        return self::getExcludeIdsForDraw();
    }
}
