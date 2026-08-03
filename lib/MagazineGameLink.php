<?php
/**
 * Pont magazines ↔ jeux : lien sujet magazine → fiche catalogue jeu.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineGameLink
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return MagazineSubjectRepository::isAvailable()
            && GameRepository::isAvailable()
            && self::catalogColumnExists();
    }

    public static function catalogColumnExists(): bool
    {
        $stmt = Database::getInstance()->query('PRAGMA table_info(magazine_subject)');
        if ($stmt === false) {
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === 'catalog_oeuvre_id') {
                return true;
            }
        }

        return false;
    }

    /**
     * Catégories de sujet pouvant être reliées à une fiche jeu.
     */
    public static function supportsSubjectCategory(string $category): bool
    {
        return MagazineSubject::supportsCatalogGameLink($category);
    }

    /**
     * Vérifie qu’une œuvre catalogue est bien un jeu.
     *
     * @return true|string true si valide, sinon message d’erreur
     */
    public static function validateCatalogOeuvreId(int $oeuvreId): bool|string
    {
        if ($oeuvreId <= 0) {
            return true;
        }

        if (!GameRepository::isAvailable()) {
            return 'Le catalogue jeux n’est pas disponible.';
        }

        $game = (new GameRepository())->findCatalogByOeuvreId($oeuvreId);

        return $game !== null ? true : 'La fiche jeu sélectionnée est introuvable.';
    }

    /**
     * Associe ou retire le lien catalogue d’un sujet magazine.
     *
     * @return true|string
     */
    public function setSubjectCatalogLink(int $subjectId, ?int $oeuvreId): bool|string
    {
        if (!self::isAvailable() || $subjectId <= 0) {
            return 'Lien magazine ↔ jeu non disponible.';
        }

        $subjectRepo = new MagazineSubjectRepository();
        $subject = $subjectRepo->findById($subjectId);
        if ($subject === null) {
            return 'Sujet introuvable.';
        }

        if ($oeuvreId !== null && $oeuvreId > 0) {
            if (!self::supportsSubjectCategory((string) ($subject['category'] ?? ''))) {
                return 'Cette catégorie de sujet ne peut pas être reliée à un média du catalogue.';
            }

            $valid = MagazineSubjectCatalogLink::validateCatalogOeuvreId($oeuvreId);
            if ($valid !== true) {
                return $valid;
            }
        } else {
            $oeuvreId = null;
        }

        $stmt = $this->db->prepare(
            'UPDATE magazine_subject SET catalog_oeuvre_id = :oeuvre_id WHERE id = :subject_id'
        );
        $stmt->execute([
            'oeuvre_id' => $oeuvreId,
            'subject_id' => $subjectId,
        ]);

        return true;
    }

    /**
     * Revues liées à un jeu dans la bibliothèque du foyer (préparation fiche jeu).
     *
     * @return list<array<string, mixed>>
     */
    public function listMagazineCoverageForGame(int $oeuvreId, int $userId, int $foyerId): array
    {
        return array_values(array_filter(
            $this->listIssueCoverageForGame($oeuvreId, $userId, $foyerId),
            static fn (array $row): bool => !empty($row['in_library']),
        ));
    }

    /**
     * Tous les numéros magazine du catalogue qui traitent ce jeu (bibliothèque ou non).
     *
     * @return list<array<string, mixed>>
     */
    public function listIssueCoverageForGame(int $oeuvreId, int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ms.id AS subject_id, ms.category, ms.label, ms.detail, ms.parution_year,
                    oms.oeuvre_id AS issue_oeuvre_id,'
            . (MagazineSubjectRepository::hasPageColumn() ? ' oms.page AS article_page,' : ' 0 AS article_page,')
            . (MagazineSubjectRepository::hasScoreColumn() ? ' oms.score AS test_score,' : ' NULL AS test_score,')
            . ' om.numero, om.numero_ordre, om.date_parution, om.stored_object_id,
                    s.titre AS series_titre, s.publication_type,
                    s.poster_url AS series_poster_url,'
            . (SeriesRepository::ratingScaleColumnExists() ? ' s.rating_scale,' : ' NULL AS rating_scale,')
            . ' o_issue.poster_url,
                    b.id AS bib_id
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
             INNER JOIN oeuvres o_issue ON o_issue.id = om.oeuvre_id AND o_issue.media_domain = :magazine_domain
             INNER JOIN series s ON s.id = om.series_id
             LEFT JOIN bibliotheque b ON b.oeuvre_id = oms.oeuvre_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             WHERE ms.catalog_oeuvre_id = :oeuvre_id
             ORDER BY om.date_parution DESC, s.titre COLLATE FRENCH_NOCASE ASC, om.numero_ordre DESC'
        );
        $stmt->execute([
            'oeuvre_id' => $oeuvreId,
            'magazine_domain' => MediaDomain::MAGAZINE,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ]);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $issueOeuvreId = (int) ($row['issue_oeuvre_id'] ?? 0);
            if ($issueOeuvreId <= 0) {
                continue;
            }

            $category = MagazineSubject::normalizeCategory((string) ($row['category'] ?? ''));
            $categoryLabel = MagazineSubject::label($category);
            if (!isset($grouped[$issueOeuvreId])) {
                $row['subject_id'] = (int) ($row['subject_id'] ?? 0);
                $row['bib_id'] = (int) ($row['bib_id'] ?? 0);
                $row['parution_year'] = (int) ($row['parution_year'] ?? 0);
                $row['stored_object_id'] = (int) ($row['stored_object_id'] ?? 0);
                $row['article_page'] = MagazineSubjectRepository::normalizePage($row['article_page'] ?? 0);
                $row['category'] = $category;
                $row['categories'] = $category !== '' ? [$category] : [];
                $row['category_label'] = $categoryLabel;
                $row['category_labels'] = $categoryLabel !== '' ? [$categoryLabel] : [];
                $row['display_label'] = MagazineSubject::displayLabel(
                    (string) ($row['label'] ?? ''),
                    (string) ($row['detail'] ?? ''),
                    (int) ($row['parution_year'] ?? 0)
                );
                $row['rating_scale'] = MagazineRatingScale::normalize($row['rating_scale'] ?? null);
                $row['test_score'] = self::normalizeCoverageScore($row['test_score'] ?? null, $category);
                $grouped[$issueOeuvreId] = $row;
                continue;
            }

            if ($category !== '' && !in_array($category, $grouped[$issueOeuvreId]['categories'], true)) {
                $grouped[$issueOeuvreId]['categories'][] = $category;
            }
            if ($categoryLabel !== '' && !in_array($categoryLabel, $grouped[$issueOeuvreId]['category_labels'], true)) {
                $grouped[$issueOeuvreId]['category_labels'][] = $categoryLabel;
            }

            if ((int) ($grouped[$issueOeuvreId]['bib_id'] ?? 0) <= 0 && (int) ($row['bib_id'] ?? 0) > 0) {
                $grouped[$issueOeuvreId]['bib_id'] = (int) $row['bib_id'];
            }
            if ((int) ($grouped[$issueOeuvreId]['stored_object_id'] ?? 0) <= 0 && (int) ($row['stored_object_id'] ?? 0) > 0) {
                $grouped[$issueOeuvreId]['stored_object_id'] = (int) $row['stored_object_id'];
            }

            // Garder la plus petite page positive (début d’article le plus tôt).
            $candidatePage = MagazineSubjectRepository::normalizePage($row['article_page'] ?? 0);
            $currentPage = MagazineSubjectRepository::normalizePage(
                $grouped[$issueOeuvreId]['article_page'] ?? 0
            );
            if ($candidatePage > 0 && ($currentPage <= 0 || $candidatePage < $currentPage)) {
                $grouped[$issueOeuvreId]['article_page'] = $candidatePage;
            }

            // Préférer la note d’un sujet « Test » (échelle de la série).
            if (($grouped[$issueOeuvreId]['rating_scale'] ?? null) === null) {
                $grouped[$issueOeuvreId]['rating_scale'] = MagazineRatingScale::normalize(
                    $row['rating_scale'] ?? null
                );
            }
            $candidateScore = self::normalizeCoverageScore($row['test_score'] ?? null, $category);
            if ($candidateScore !== null) {
                $existingScore = $grouped[$issueOeuvreId]['test_score'] ?? null;
                $existingIsTest = in_array(
                    MagazineSubject::TEST,
                    is_array($grouped[$issueOeuvreId]['categories'] ?? null)
                        ? $grouped[$issueOeuvreId]['categories']
                        : [],
                    true
                );
                if ($existingScore === null || ($category === MagazineSubject::TEST && !$existingIsTest)) {
                    $grouped[$issueOeuvreId]['test_score'] = $candidateScore;
                    $grouped[$issueOeuvreId]['rating_scale'] = MagazineRatingScale::normalize(
                        $row['rating_scale'] ?? ($grouped[$issueOeuvreId]['rating_scale'] ?? null)
                    );
                }
            }
        }

        $rows = [];
        foreach ($grouped as $row) {
            $rows[] = $this->enrichCoverageRow($row);
        }

        return $rows;
    }

    /**
     * Sépare les numéros « jeux offerts » des autres couvertures (tests, dossiers…).
     *
     * @param list<array<string, mixed>> $rows
     * @return array{offered: list<array<string, mixed>>, coverage: list<array<string, mixed>>}
     */
    public function partitionIssueCoverageByOffer(array $rows): array
    {
        $offered = [];
        $coverage = [];
        foreach ($rows as $row) {
            $categories = is_array($row['categories'] ?? null)
                ? array_values(array_map(
                    static fn (mixed $cat): string => MagazineSubject::normalizeCategory((string) $cat),
                    $row['categories']
                ))
                : [];
            if ($categories === []) {
                $single = MagazineSubject::normalizeCategory((string) ($row['category'] ?? ''));
                if ($single !== '') {
                    $categories = [$single];
                }
            }

            $hasOffer = in_array(MagazineSubject::JEUX_OFFERTS, $categories, true);
            $otherCategories = array_values(array_filter(
                $categories,
                static fn (string $cat): bool => $cat !== MagazineSubject::JEUX_OFFERTS
            ));

            if ($hasOffer) {
                $offerRow = $row;
                $offerRow['categories'] = [MagazineSubject::JEUX_OFFERTS];
                $offerRow['category'] = MagazineSubject::JEUX_OFFERTS;
                $offerRow['category_label'] = MagazineSubject::label(MagazineSubject::JEUX_OFFERTS);
                $offerRow['category_labels'] = [$offerRow['category_label']];
                $offerRow['test_score'] = null;
                $offerRow['score_display'] = '';
                $offerRow['score_percent'] = null;
                $offerRow['score_stars'] = [];
                $offered[] = $offerRow;
            }

            if ($otherCategories !== []) {
                $coverageRow = $row;
                $coverageRow['categories'] = $otherCategories;
                $coverageRow['category'] = $otherCategories[0];
                $coverageRow['category_labels'] = [];
                foreach ($otherCategories as $cat) {
                    $label = MagazineSubject::label($cat);
                    if ($label !== '' && !in_array($label, $coverageRow['category_labels'], true)) {
                        $coverageRow['category_labels'][] = $label;
                    }
                }
                $coverageRow['category_label'] = (string) ($coverageRow['category_labels'][0] ?? '');
                $coverage[] = $coverageRow;
            }
        }

        return ['offered' => $offered, 'coverage' => $coverage];
    }

    public function countIssueCoverageForGame(int $oeuvreId, int $userId, int $foyerId): int
    {
        return count($this->listIssueCoverageForGame($oeuvreId, $userId, $foyerId));
    }

    /**
     * Compte les numéros magazine (tous types de sujets) par jeu catalogue.
     * Une seule requête pour toute une liste (Mes jeux).
     *
     * @param list<int> $oeuvreIds
     * @return array<int, int> oeuvre_id => nombre de numéros distincts
     */
    public function countIssueCoverageByOeuvreIds(array $oeuvreIds): array
    {
        if (!self::isAvailable() || $oeuvreIds === []) {
            return [];
        }

        $ids = [];
        foreach ($oeuvreIds as $oeuvreId) {
            $oeuvreId = (int) $oeuvreId;
            if ($oeuvreId > 0) {
                $ids[$oeuvreId] = $oeuvreId;
            }
        }
        if ($ids === []) {
            return [];
        }

        $idList = array_values($ids);
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $this->db->prepare(
            'SELECT ms.catalog_oeuvre_id AS oeuvre_id,
                    COUNT(DISTINCT oms.oeuvre_id) AS issue_count
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
             INNER JOIN oeuvres o_issue
                ON o_issue.id = om.oeuvre_id AND o_issue.media_domain = ?
             WHERE ms.catalog_oeuvre_id IN (' . $placeholders . ')
             GROUP BY ms.catalog_oeuvre_id'
        );
        $stmt->execute(array_merge([MediaDomain::MAGAZINE], $idList));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId > 0) {
                $map[$oeuvreId] = (int) ($row['issue_count'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichCoverageRow(array $row): array
    {
        $poster = trim((string) ($row['poster_url'] ?? ''));
        if ($poster === '') {
            $poster = trim((string) ($row['series_poster_url'] ?? ''));
        }
        $row['poster_src'] = View::posterSrc($poster !== '' ? $poster : null);
        $bibId = (int) ($row['bib_id'] ?? 0);
        $issueOeuvreId = (int) ($row['issue_oeuvre_id'] ?? 0);
        if ($bibId > 0) {
            $row['in_library'] = true;
            $row['issue_nav_url'] = View::magazineIssueNavUrl($bibId);
        } elseif ($issueOeuvreId > 0) {
            $row['in_library'] = false;
            $row['issue_nav_url'] = View::oeuvreMagazineNavUrl($issueOeuvreId);
        } else {
            $row['in_library'] = false;
            $row['issue_nav_url'] = '';
        }
        $row['date_label'] = PublicationType::formatParutionDate(
            (string) ($row['date_parution'] ?? ''),
            (string) ($row['publication_type'] ?? PublicationType::MENSUEL)
        );
        if (!isset($row['category_labels']) || !is_array($row['category_labels'])) {
            $label = (string) ($row['category_label'] ?? '');
            $row['category_labels'] = $label !== '' ? [$label] : [];
        }

        $storedObjectId = (int) ($row['stored_object_id'] ?? 0);
        $articlePage = MagazineSubjectRepository::normalizePage($row['article_page'] ?? 0);
        $row['stored_object_id'] = $storedObjectId;
        $row['article_page'] = $articlePage;
        $row['pdf_url'] = $storedObjectId > 0
            ? View::mediaObjectUrl($storedObjectId, $articlePage)
            : '';

        $ratingScale = MagazineRatingScale::normalize($row['rating_scale'] ?? null);
        $testScore = array_key_exists('test_score', $row) && $row['test_score'] !== null && $row['test_score'] !== ''
            ? (float) $row['test_score']
            : null;
        // Afficher la note seulement si un test est bien présent sur ce numéro.
        $categories = is_array($row['categories'] ?? null) ? $row['categories'] : [];
        $hasTest = in_array(MagazineSubject::TEST, $categories, true)
            || MagazineSubject::normalizeCategory((string) ($row['category'] ?? '')) === MagazineSubject::TEST;
        if (!$hasTest) {
            $testScore = null;
        }

        $row['rating_scale'] = $ratingScale;
        $row['test_score'] = $testScore;
        $row['score_display'] = $testScore !== null
            ? MagazineRatingScale::formatDisplay($testScore, $ratingScale)
            : '';
        $row['score_percent'] = $testScore !== null
            ? MagazineRatingScale::toPercent($testScore, $ratingScale)
            : null;
        $row['score_stars'] = $testScore !== null
            ? MagazineRatingScale::starParts($testScore, $ratingScale)
            : [];

        return $row;
    }

    /**
     * Note d’un test uniquement (les autres catégories n’ont pas de score utile).
     */
    private static function normalizeCoverageScore(mixed $raw, string $category): ?float
    {
        if (MagazineSubject::normalizeCategory($category) !== MagazineSubject::TEST) {
            return null;
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * Moyenne des notes presse (déjà converties sur 100) pour une liste de couvertures.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{average: float|null, count: int}
     */
    public static function averageScorePercent(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $percent = $row['score_percent'] ?? null;
            if ($percent === null || $percent === '') {
                continue;
            }
            if (!is_numeric($percent)) {
                continue;
            }
            $values[] = (float) $percent;
        }

        $count = count($values);
        if ($count === 0) {
            return ['average' => null, 'count' => 0];
        }

        return [
            'average' => round(array_sum($values) / $count, 1),
            'count' => $count,
        ];
    }

    /**
     * Sujets magazine reliés à un jeu catalogue (vue admin, tous numéros du catalogue).
     *
     * @return list<array<string, mixed>>
     */
    public function listCatalogSubjectCoverageForGame(int $oeuvreId): array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ms.id AS subject_id, ms.category, ms.label, ms.detail, ms.parution_year,
                    COUNT(DISTINCT oms.oeuvre_id) AS issue_count
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             WHERE ms.catalog_oeuvre_id = :oeuvre_id
             GROUP BY ms.id
             ORDER BY ms.parution_year DESC, ms.label COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute(['oeuvre_id' => $oeuvreId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['subject_id'] = (int) ($row['subject_id'] ?? 0);
            $row['issue_count'] = (int) ($row['issue_count'] ?? 0);
            $row['parution_year'] = (int) ($row['parution_year'] ?? 0);
            $row['category_label'] = MagazineSubject::label((string) ($row['category'] ?? ''));
            $row['display_label'] = MagazineSubject::displayLabel(
                (string) ($row['label'] ?? ''),
                (string) ($row['detail'] ?? ''),
                (int) ($row['parution_year'] ?? 0)
            );
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Enrichit un sujet magazine avec les infos catalogue jeu + lien bibliothèque utilisateur.
     *
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    public function enrichSubjectRow(array $subject, int $userId, int $foyerId): array
    {
        if (!MagazineSubjectCatalogLink::isAvailable()) {
            return $subject;
        }

        return (new MagazineSubjectCatalogLink())->enrichSubjectRow($subject, $userId, $foyerId);
    }
}
