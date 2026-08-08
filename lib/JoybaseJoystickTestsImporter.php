<?php
/**
 * Import ponctuel Joybase → tests Joystick (feuille « Tests » uniquement).
 *
 * Usage prévu une seule fois ; spécifique au format ODS Joybase v1212.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;
use ZipArchive;

/**
 * Forme du rapport renvoyé par l’import (compteurs + listes de messages).
 *
 * @phpstan-type JoybaseImportResult array{
 *   dry_run: bool,
 *   series_id: int,
 *   series_titre: string,
 *   rows_read: int,
 *   linked: int,
 *   links_updated: int,
 *   games_created: int,
 *   games_reused: int,
 *   subjects_created: int,
 *   subjects_reused: int,
 *   scores_set: int,
 *   scores_absent: int,
 *   scores_skipped: int,
 *   issues_missing: int,
 *   errors: list<string>,
 *   warnings: list<string>
 * }
 */
final class JoybaseJoystickTestsImporter
{
    public const DEFAULT_SERIES_TITLE = 'Joystick';

    /** @var array{dryRun: bool, seriesTitle: string, tag: string, setRatingPeriods: bool, limit: int, fillGameMeta: bool} */
    private array $options;

    private PDO $db;

    /** @var array<string, int>|null clé normalisée → oeuvre_id */
    private ?array $gameTitleIndex = null;

    /**
     * @param array{dryRun?: bool, seriesTitle?: string, tag?: string, setRatingPeriods?: bool, limit?: int, fillGameMeta?: bool} $options
     */
    public function __construct(array $options = [])
    {
        $this->options = [
            'dryRun' => !empty($options['dryRun']),
            'seriesTitle' => trim((string) ($options['seriesTitle'] ?? self::DEFAULT_SERIES_TITLE)),
            'tag' => trim((string) ($options['tag'] ?? '')),
            'setRatingPeriods' => !empty($options['setRatingPeriods']),
            'limit' => max(0, (int) ($options['limit'] ?? 0)),
            'fillGameMeta' => array_key_exists('fillGameMeta', $options)
                ? (bool) $options['fillGameMeta']
                : true,
        ];
        $this->db = Database::getInstance();
    }

    /**
     * @return JoybaseImportResult
     */
    public function importFromOds(string $odsPath): array
    {
        /** @var JoybaseImportResult $result */
        $result = [
            'dry_run' => $this->options['dryRun'],
            'series_id' => 0,
            'series_titre' => '',
            'rows_read' => 0,
            'linked' => 0,
            'links_updated' => 0,
            'games_created' => 0,
            'games_reused' => 0,
            'subjects_created' => 0,
            'subjects_reused' => 0,
            'scores_set' => 0,
            'scores_absent' => 0,
            'scores_skipped' => 0,
            'issues_missing' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        if (!is_file($odsPath)) {
            $result['errors'][] = 'Fichier introuvable : ' . $odsPath;

            return $result;
        }

        $series = $this->findJoystickSeries($this->options['seriesTitle']);
        if ($series === null) {
            $result['errors'][] = 'Série magazine « '
                . $this->options['seriesTitle']
                . ' » introuvable. Importez Joystick (ABM) avant ce script.';

            return $result;
        }

        $seriesId = (int) ($series['id'] ?? 0);
        $result['series_id'] = $seriesId;
        $result['series_titre'] = (string) ($series['titre'] ?? '');

        // resolveDetailTag() renvoie toujours une chaîne ; préfixe ERR: = erreur à remonter.
        $detailTag = $this->resolveDetailTag($series);
        if (str_starts_with($detailTag, 'ERR:')) {
            $result['errors'][] = substr($detailTag, 4);

            return $result;
        }

        if ($this->options['setRatingPeriods'] && !$this->options['dryRun']) {
            $periodsResult = $this->ensureJoystickRatingPeriods($seriesId);
            if ($periodsResult !== true) {
                $result['errors'][] = (string) $periodsResult;

                return $result;
            }
            $result['warnings'][] = 'Périodes d’échelle Joystick enregistrées en base (1–139 sur 100, 140→… sur 10).';
        } elseif ($this->options['setRatingPeriods'] && $this->options['dryRun']) {
            $result['warnings'][] = 'Dry-run : périodes d’échelle non écrites en base (seraient 1–139 /100, 140→… /10).';
        }

        // Toujours utiliser les barèmes Joybase pour valider les notes de CET import
        // (même en dry-run, et même si la série n’a pas encore de périodes en base).
        $periods = self::joystickRatingPeriods();
        $defaultScale = '10';
        $result['warnings'][] = 'Barèmes appliqués aux notes : '
            . implode(' ; ', array_map(
                static fn (array $p): string => MagazineRatingPeriod::formatLabel($p),
                $periods
            ))
            . ' (défaut hors plage : Sur 10).';

        $issueMap = $this->buildIssueMap($seriesId);
        if ($issueMap === []) {
            $result['errors'][] = 'Aucun numéro trouvé pour la série « ' . $result['series_titre'] . ' ».';

            return $result;
        }

        $rows = $this->readTestsSheet($odsPath);
        if ($rows === []) {
            $result['errors'][] = 'Feuille « Tests » vide ou illisible dans le ODS.';

            return $result;
        }

        $subjectRepo = new MagazineSubjectRepository();
        $gameLink = MagazineGameLink::isAvailable() ? new MagazineGameLink() : null;

        $limit = $this->options['limit'];
        foreach ($rows as $index => $row) {
            if ($limit > 0 && $result['rows_read'] >= $limit) {
                break;
            }
            $result['rows_read']++;
            $line = $index + 2; // +1 header, +1 human

            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $result['warnings'][] = 'Ligne ' . $line . ' : titre vide, ignorée.';
                continue;
            }

            $issueNum = (int) ($row['issue_number'] ?? 0);
            if ($issueNum <= 0) {
                $result['errors'][] = 'Ligne ' . $line . ' (« ' . $title . ' ») : numéro magazine invalide.';
                continue;
            }

            $issue = $issueMap[$issueNum] ?? null;
            if ($issue === null) {
                $result['issues_missing']++;
                $result['errors'][] = 'Ligne ' . $line . ' : numéro Joystick '
                    . $issueNum . ' introuvable en catalogue.';
                continue;
            }

            $issueOeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
            $year = (int) ($row['year'] ?? 0);
            if ($year <= 0) {
                $year = MagazineSubject::parutionYearFromIssue($issue);
            }
            if ($year <= 0) {
                $result['errors'][] = 'Ligne ' . $line . ' (« ' . $title . ' ») : année introuvable.';
                continue;
            }

            $studio = trim((string) ($row['developer'] ?? ''));
            $editeur = trim((string) ($row['publisher'] ?? ''));
            $page = (int) ($row['page'] ?? 0);
            $titleRaw = trim((string) ($row['title_raw'] ?? $title));

            $ratingScale = MagazineRatingPeriod::resolve(
                $defaultScale,
                $periods,
                (float) ($issue['numero_ordre'] ?? $issueNum)
            );

            $scoreRaw = (string) ($row['note_raw'] ?? '');
            $parsedScore = self::parseJoybaseScore($scoreRaw);
            $scoreToStore = null;
            if ($parsedScore === false) {
                // Note présente mais illisible : on lie quand même page/sujet, sans note.
                $result['scores_skipped']++;
                $result['warnings'][] = 'Ligne ' . $line . ' (« ' . $title . ' ») : note non interprétable « '
                    . self::short($scoreRaw) . ' » — sujet lié sans note.';
            } elseif ($parsedScore === null) {
                // Champ vide / « non noté » : traitement normal, aucune note enregistrée.
                $result['scores_absent']++;
            } elseif ($ratingScale === null) {
                $result['scores_skipped']++;
                $result['warnings'][] = 'Ligne ' . $line . ' : note ignorée (pas d’échelle pour ce numéro). '
                    . 'Astuce : --set-rating-periods';
            } else {
                $validated = MagazineRatingScale::parseScore($parsedScore, $ratingScale);
                if (is_string($validated)) {
                    // Ex. note 10,00 % lue comme 10 sur une échelle /10 : OK ; sinon tenter conversion % → échelle.
                    $asPercent = self::parseJoybaseScorePreferPercent($scoreRaw);
                    if ($asPercent !== null && (int) $ratingScale === 100) {
                        $validated = MagazineRatingScale::parseScore($asPercent, $ratingScale);
                    }
                }
                if (is_string($validated)) {
                    $result['scores_skipped']++;
                    $result['warnings'][] = 'Ligne ' . $line . ' (« ' . $title . ' ») : '
                        . $validated . ' (brut « ' . self::short($scoreRaw) . ' », échelle '
                        . MagazineRatingScale::label($ratingScale) . ') — sujet lié sans note.';
                } else {
                    $scoreToStore = $validated;
                }
            }

            if ($this->options['dryRun']) {
                // Estimation anti-doublons sans écriture.
                $gameExists = $this->findExistingGameOeuvreId($title, $titleRaw) !== null;
                if ($gameExists) {
                    $result['games_reused']++;
                } else {
                    $result['games_created']++;
                }
                if ($this->findExistingSubjectId(MagazineSubject::TEST, $title, $detailTag, $year) !== null) {
                    $result['subjects_reused']++;
                } else {
                    $result['subjects_created']++;
                }
                $result['linked']++;
                if ($scoreToStore !== null) {
                    $result['scores_set']++;
                }
                continue;
            }

            // --- écriture réelle ---
            $gameOeuvreId = $this->findOrCreateGame($title, $titleRaw, $year, $studio, $editeur, $result);
            if ($gameOeuvreId <= 0) {
                $result['errors'][] = 'Ligne ' . $line . ' (« ' . $title . ' ») : impossible de créer/trouver le jeu.';
                continue;
            }

            $beforeSubjectId = $this->findExistingSubjectId(MagazineSubject::TEST, $title, $detailTag, $year);
            $subject = $subjectRepo->findOrCreate(MagazineSubject::TEST, $title, $detailTag, $year);
            if ($subject === null) {
                $result['errors'][] = 'Ligne ' . $line . ' : impossible de créer le sujet.';
                continue;
            }
            $subjectId = (int) ($subject['id'] ?? 0);
            if ($beforeSubjectId === null) {
                $result['subjects_created']++;
            } else {
                $result['subjects_reused']++;
            }

            if ($gameLink !== null) {
                $linkCatalog = $gameLink->setSubjectCatalogLink($subjectId, $gameOeuvreId);
                if ($linkCatalog !== true) {
                    $result['warnings'][] = 'Ligne ' . $line . ' : lien catalogue jeu — ' . (string) $linkCatalog;
                }
            }

            $existsLink = $this->linkExists($issueOeuvreId, $subjectId);
            $attach = $subjectRepo->attachToOeuvre($issueOeuvreId, $subjectId, $page);
            if ($attach !== true) {
                $result['errors'][] = 'Ligne ' . $line . ' : rattachement — ' . (string) $attach;
                continue;
            }
            if ($existsLink) {
                $result['links_updated']++;
                if ($page > 0) {
                    $subjectRepo->updateLinkPage($issueOeuvreId, $subjectId, $page);
                }
            } else {
                $result['linked']++;
            }

            if ($scoreToStore !== null) {
                $scoreResult = $subjectRepo->updateLinkScore($issueOeuvreId, $subjectId, $scoreToStore);
                if ($scoreResult !== true) {
                    $result['warnings'][] = 'Ligne ' . $line . ' : note — ' . (string) $scoreResult;
                } else {
                    $result['scores_set']++;
                }
            }
        }

        return $result;
    }

    /**
     * Lit la feuille Tests du ODS.
     *
     * @return list<array{title: string, title_raw: string, developer: string, publisher: string, genre: string, note_raw: string, issue_number: int, page: int, year: int}>
     */
    public function readTestsSheet(string $odsPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($odsPath) !== true) {
            return [];
        }
        $xml = $zip->getFromName('content.xml');
        $zip->close();
        if ($xml === false || trim($xml) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        if (@$dom->loadXML($xml) === false) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

        $tables = $xpath->query('//table:table');
        if ($tables === false) {
            return [];
        }

        $testsTable = null;
        foreach ($tables as $table) {
            if (!$table instanceof \DOMElement) {
                continue;
            }
            $name = trim($table->getAttribute('table:name'));
            if (mb_strtolower($name) === 'tests') {
                $testsTable = $table;
                break;
            }
        }
        if ($testsTable === null) {
            return [];
        }

        $rowNodes = $xpath->query('table:table-row', $testsTable);
        if ($rowNodes === false) {
            return [];
        }

        $out = [];
        $first = true;
        foreach ($rowNodes as $rowNode) {
            if (!$rowNode instanceof \DOMElement) {
                continue;
            }
            $cells = $this->expandOdsRow($xpath, $rowNode, 8);
            if ($first) {
                $first = false;
                continue;
            }
            if ($cells === [] || trim((string) ($cells[0] ?? '')) === '') {
                continue;
            }

            $rawTitle = $this->cleanTitle((string) $cells[0]);
            $title = self::canonicalizeJoybaseTitle($rawTitle);
            $issueNumber = self::parseIssueNumber((string) ($cells[5] ?? ''));
            $page = self::parsePage((string) ($cells[6] ?? ''));
            $year = self::parseMagazineYear((string) ($cells[7] ?? ''));

            $out[] = [
                'title' => $title,
                'title_raw' => $rawTitle,
                'developer' => trim((string) ($cells[1] ?? '')),
                'publisher' => trim((string) ($cells[2] ?? '')),
                'genre' => trim((string) ($cells[3] ?? '')),
                'note_raw' => trim((string) ($cells[4] ?? '')),
                'issue_number' => $issueNumber,
                'page' => $page,
                'year' => $year,
            ];
        }

        return $out;
    }

    /**
     * Remet l’article Joybase en tête (ex. « Dig - The… » → « The Dig »).
     */
    public static function canonicalizeJoybaseTitle(string $title): string
    {
        $title = trim(str_replace("\xc2\xa0", ' ', $title));
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $article = '(The|A|An|L[\'’]|Le|La|Les|Un|Une)';

        // « Ball (The...) », « Ecole des bébés (L'...) », « Aventures… (Les...) - suite »
        if (preg_match(
            '/^(.+?)\s*\(\s*' . $article . '\.{0,3}\s*\)\s*(.*)$/iu',
            $title,
            $m
        ) === 1) {
            return self::prependArticle(trim((string) $m[2]), trim((string) $m[1]), trim((string) $m[3]));
        }

        // « Dig - The… », « Enigme de maitre Lu - L’… »
        if (preg_match(
            '/^(.+?)\s*[-–—]\s*' . $article . '\s*[.…]*\s*$/iu',
            $title,
            $m
        ) === 1) {
            return self::prependArticle(trim((string) $m[2]), trim((string) $m[1]), '');
        }

        // « Entente, The - WWI… », « Simpsons, The… - Hit and Run »
        if (preg_match(
            '/^(.+?),\s*' . $article . '\s*[.…]*\s*(.*)$/iu',
            $title,
            $m
        ) === 1) {
            $rest = trim((string) $m[3]);
            if ($rest !== '' && !str_starts_with($rest, '-')) {
                // Virgule dans un titre normal (pas un article) → ne pas toucher.
                return $title;
            }

            return self::prependArticle(trim((string) $m[2]), trim((string) $m[1]), $rest);
        }

        return $title;
    }

    /**
     * Clé de rapprochement (article remis en tête, casse / accents / ponctuation ignorés).
     */
    public static function matchKey(string $title): string
    {
        $title = self::canonicalizeJoybaseTitle($title);
        if ($title === '') {
            return '';
        }

        return MagazineSubject::normalizeLabelKey(SearchMatch::fold($title));
    }

    /**
     * Variantes de titre à tester pour retrouver une fiche existante.
     *
     * @return list<string>
     */
    public static function titleVariants(string $title, string $rawTitle = ''): array
    {
        $variants = [];
        foreach ([$title, $rawTitle, self::canonicalizeJoybaseTitle($title), self::canonicalizeJoybaseTitle($rawTitle)] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $variants[$candidate] = true;
        }

        return array_keys($variants);
    }

    private static function prependArticle(string $article, string $body, string $rest): string
    {
        $article = trim($article);
        $body = trim($body);
        if ($body === '' || $article === '') {
            return trim($body . ' ' . $rest);
        }

        // Uniformiser l’apostrophe.
        $article = str_replace('’', "'", $article);
        $isElided = str_ends_with(mb_strtolower($article), "'");

        $natural = $isElided
            ? $article . $body
            : $article . ' ' . $body;

        $rest = trim($rest);
        if ($rest !== '') {
            if (str_starts_with($rest, '-')) {
                $natural .= ' ' . $rest;
            } else {
                $natural .= ' ' . $rest;
            }
        }

        return preg_replace('/\s+/u', ' ', trim($natural)) ?? trim($natural);
    }

    /**
     * Parse une note Joybase.
     *
     * @return float|null|false null = non noté, false = illisible, float = valeur brute
     */
    public static function parseJoybaseScore(string $raw): float|null|false
    {
        $raw = trim(str_replace("\xc2\xa0", ' ', $raw));
        if ($raw === '') {
            return null;
        }

        $lower = mb_strtolower($raw);
        if (
            $lower === 'non noté'
            || $lower === 'non note'
            || $lower === '-'
            || $lower === 'aucun'
            || $lower === 'assurément'
        ) {
            return null;
        }

        // Prendre le bloc après ====== (note « officielle » Joybase).
        if (str_contains($raw, '======')) {
            $parts = preg_split('/={3,}/', $raw) ?: [];
            $tail = trim((string) end($parts));
            if ($tail !== '') {
                $raw = $tail;
            }
        }

        // Pourcentage prioritaire.
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $raw, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        // Nombre simple (échelle /10).
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*$/u', trim($raw), $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        // Dernier recours : premier nombre raisonnable ≤ 100.
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $raw, $m) === 1) {
            $value = (float) str_replace(',', '.', $m[1]);
            if ($value > 100) {
                return false;
            }

            return $value;
        }

        return false;
    }

    public static function parseJoybaseScorePreferPercent(string $raw): ?float
    {
        $parsed = self::parseJoybaseScore($raw);
        if (!is_float($parsed)) {
            return null;
        }

        return $parsed;
    }

    public static function parseIssueNumber(string $raw): int
    {
        $raw = trim(str_replace("\xc2\xa0", ' ', $raw));
        if ($raw === '') {
            return 0;
        }
        // « 084 (JS-01) » → 84
        if (preg_match('/(\d{1,4})/', $raw, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }

    public static function parsePage(string $raw): int
    {
        $raw = trim(str_replace("\xc2\xa0", ' ', $raw));
        if ($raw === '' || !preg_match('/(\d{1,4})/', $raw, $m)) {
            return 0;
        }

        return max(0, (int) $m[1]);
    }

    /** MM/YY → année (50–99 → 19xx, 00–49 → 20xx). */
    public static function parseMagazineYear(string $raw): int
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{1,2})\/(\d{2})$/', $raw, $m) !== 1) {
            return 0;
        }
        $yy = (int) $m[2];

        return $yy >= 50 ? 1900 + $yy : 2000 + $yy;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findJoystickSeries(string $title): ?array
    {
        $repo = new SeriesRepository();
        $exact = $repo->findByTitre($title, MediaDomain::MAGAZINE);
        if ($exact !== null) {
            return $exact;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM series
             WHERE media_domain = ?
               AND titre LIKE ? ESCAPE \'\\\'
             ORDER BY LENGTH(titre) ASC, id ASC
             LIMIT 5'
        );
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $title) . '%';
        $stmt->execute([MediaDomain::MAGAZINE, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return null;
        }

        // Préférer un titre égal hors casse.
        foreach ($rows as $row) {
            if (mb_strtolower(trim((string) ($row['titre'] ?? ''))) === mb_strtolower($title)) {
                return $row;
            }
        }

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $series
     */
    private function resolveDetailTag(array $series): string
    {
        if ($this->options['tag'] !== '') {
            return $this->options['tag'];
        }

        $single = MagazineSeriesTag::singleTag($series);
        if ($single !== null) {
            return $single;
        }

        if (MagazineSeriesTag::requiresTagChoice($series)) {
            return 'ERR:La série a plusieurs tags — précisez --tag=… (ex. --tag=PC).';
        }

        return '';
    }

    /**
     * Barèmes Joybase (données réelles des notes, pas le tableau « Infos » obsolète).
     *
     * @return list<array{from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string}>
     */
    public static function joystickRatingPeriods(): array
    {
        return [
            [
                'from_numero_ordre' => 1.0,
                'to_numero_ordre' => 139.0,
                'rating_scale' => '100',
            ],
            [
                'from_numero_ordre' => 140.0,
                'to_numero_ordre' => null,
                'rating_scale' => '10',
            ],
        ];
    }

    /** @return true|string */
    private function ensureJoystickRatingPeriods(int $seriesId): true|string
    {
        if (!MagazineRatingPeriod::tableExists()) {
            return 'Table des périodes d’échelle indisponible (migration 075).';
        }

        $replaced = MagazineRatingPeriod::replaceForSeries($seriesId, self::joystickRatingPeriods());
        if ($replaced !== true) {
            return $replaced;
        }

        // Défaut hors période = sur 10.
        (new SeriesRepository())->update($seriesId, ['rating_scale' => '10']);

        return true;
    }

    /**
     * @return array<int, array{oeuvre_id: int, numero: string, numero_ordre: float, date_parution: string}>
     */
    private function buildIssueMap(int $seriesId): array
    {
        $stmt = $this->db->prepare(
            'SELECT oeuvre_id, numero, numero_ordre, date_parution, est_hors_serie
             FROM oeuvre_magazine
             WHERE series_id = ?
             ORDER BY numero_ordre ASC'
        );
        $stmt->execute([$seriesId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ((int) ($row['est_hors_serie'] ?? 0) === 1) {
                continue;
            }
            $ordre = (float) ($row['numero_ordre'] ?? 0);
            // Hors-série encodés en n+0.5 : on ignore pour Joybase (tests = numéros entiers).
            if ($ordre <= 0 || abs($ordre - round($ordre)) > 0.01) {
                // tenter via numero texte
                $fromText = self::parseIssueNumber((string) ($row['numero'] ?? ''));
                if ($fromText <= 0) {
                    continue;
                }
                $key = $fromText;
            } else {
                $key = (int) round($ordre);
            }

            if (!isset($map[$key])) {
                $map[$key] = [
                    'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
                    'numero' => (string) ($row['numero'] ?? ''),
                    'numero_ordre' => $ordre > 0 ? $ordre : (float) $key,
                    'date_parution' => (string) ($row['date_parution'] ?? ''),
                ];
            }
        }

        return $map;
    }

    /**
     * Cherche un jeu catalogue existant ou en crée un ; met à jour les compteurs du rapport.
     *
     * @param JoybaseImportResult $result
     * @param-out JoybaseImportResult $result
     */
    private function findOrCreateGame(
        string $title,
        string $titleRaw,
        int $year,
        string $studio,
        string $editeur,
        array &$result
    ): int {
        $existingId = $this->findExistingGameOeuvreId($title, $titleRaw);
        if ($existingId !== null) {
            $result['games_reused']++;
            if ($this->options['fillGameMeta']) {
                $this->fillEmptyGameMeta($existingId, $studio, $editeur);
            }

            return $existingId;
        }

        $created = (new GameRepository())->createCatalogOnly([
            'titre' => $title,
            'annee' => $year,
            'studio' => $studio,
            'editeur' => $editeur,
            'platform' => GamePlatform::PC,
        ]);

        if (is_int($created) && $created > 0) {
            $result['games_created']++;
            $this->rememberGameTitle($title, $titleRaw, $created);

            return $created;
        }

        // Course / doublon concurrent : retenter la recherche.
        $this->gameTitleIndex = null;
        $again = $this->findExistingGameOeuvreId($title, $titleRaw);
        if ($again !== null) {
            $result['games_reused']++;

            return $again;
        }

        return 0;
    }

    private function findExistingGameOeuvreId(string $title, string $titleRaw = ''): ?int
    {
        foreach (self::titleVariants($title, $titleRaw) as $variant) {
            $oeuvre = (new OeuvreRepository())->findByTitreRealisateurAndDomain(
                $variant,
                '',
                MediaDomain::JEU
            );
            if ($oeuvre !== null) {
                return (int) ($oeuvre['id'] ?? 0);
            }
        }

        $index = $this->gameTitleIndex();
        foreach (self::titleVariants($title, $titleRaw) as $variant) {
            $want = self::matchKey($variant);
            if ($want !== '' && isset($index[$want])) {
                return $index[$want];
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function gameTitleIndex(): array
    {
        if ($this->gameTitleIndex !== null) {
            return $this->gameTitleIndex;
        }

        $this->gameTitleIndex = [];
        $stmt = $this->db->prepare(
            'SELECT id, titre FROM oeuvres WHERE media_domain = ? ORDER BY id ASC'
        );
        $stmt->execute([MediaDomain::JEU]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $oeuvreId = (int) ($row['id'] ?? 0);
            $titre = (string) ($row['titre'] ?? '');
            foreach (self::titleVariants($titre) as $variant) {
                $key = self::matchKey($variant);
                if ($key === '' || isset($this->gameTitleIndex[$key])) {
                    continue;
                }
                $this->gameTitleIndex[$key] = $oeuvreId;
            }
        }

        return $this->gameTitleIndex;
    }

    private function rememberGameTitle(string $title, string $titleRaw, int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }
        $index = $this->gameTitleIndex();
        foreach (self::titleVariants($title, $titleRaw) as $variant) {
            $key = self::matchKey($variant);
            if ($key === '' || isset($index[$key])) {
                continue;
            }
            $this->gameTitleIndex[$key] = $oeuvreId;
        }
    }

    private function fillEmptyGameMeta(int $oeuvreId, string $studio, string $editeur): void
    {
        if ($oeuvreId <= 0 || ($studio === '' && $editeur === '')) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT studio, editeur FROM oeuvre_jeu WHERE oeuvre_id = ? LIMIT 1'
        );
        $stmt->execute([$oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return;
        }

        $newStudio = trim((string) ($row['studio'] ?? '')) === '' ? $studio : (string) $row['studio'];
        $newEditeur = trim((string) ($row['editeur'] ?? '')) === '' ? $editeur : (string) $row['editeur'];
        if ($newStudio === (string) ($row['studio'] ?? '') && $newEditeur === (string) ($row['editeur'] ?? '')) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_jeu SET studio = ?, editeur = ? WHERE oeuvre_id = ?'
        )->execute([$newStudio, $newEditeur, $oeuvreId]);
    }

    private function findExistingSubjectId(string $category, string $label, string $detail, int $year): ?int
    {
        $category = MagazineSubject::normalizeCategory($category);
        $label = trim($label);
        $detail = trim($detail);
        $year = max(0, $year);
        if ($label === '' || $year <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT id, label FROM magazine_subject
             WHERE category = ? AND detail = ? AND parution_year = ?
             ORDER BY id ASC'
        );
        $stmt->execute([$category, $detail, $year]);
        $want = self::matchKey($label);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $candidate = self::matchKey((string) ($row['label'] ?? ''));
            if ($candidate !== '' && $candidate === $want) {
                return (int) ($row['id'] ?? 0);
            }
        }

        return null;
    }

    private function linkExists(int $issueOeuvreId, int $subjectId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM oeuvre_magazine_subject WHERE oeuvre_id = ? AND subject_id = ? LIMIT 1'
        );
        $stmt->execute([$issueOeuvreId, $subjectId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return list<string>
     */
    private function expandOdsRow(\DOMXPath $xpath, \DOMElement $rowNode, int $maxCols): array
    {
        $cells = [];
        $cellNodes = $xpath->query('table:table-cell', $rowNode);
        if ($cellNodes === false) {
            return [];
        }

        foreach ($cellNodes as $cellNode) {
            if (!$cellNode instanceof \DOMElement) {
                continue;
            }
            $repeat = (int) ($cellNode->getAttribute('table:number-columns-repeated') ?: '1');
            $repeat = max(1, min($repeat, $maxCols));
            $texts = [];
            $pNodes = $xpath->query('.//text:p', $cellNode);
            if ($pNodes !== false) {
                foreach ($pNodes as $p) {
                    if ($p instanceof \DOMElement) {
                        $texts[] = $p->textContent;
                    }
                }
            }
            $value = trim(implode("\n", $texts));
            for ($i = 0; $i < $repeat; $i++) {
                $cells[] = $value;
                if (count($cells) >= $maxCols) {
                    return $cells;
                }
            }
        }

        while (count($cells) < $maxCols) {
            $cells[] = '';
        }

        return array_slice($cells, 0, $maxCols);
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(str_replace("\xc2\xa0", ' ', $title));
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return trim($title);
    }

    private static function short(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? $text;
        if (mb_strlen($text) > 60) {
            return mb_substr($text, 0, 57) . '…';
        }

        return $text;
    }
}
