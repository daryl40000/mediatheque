<?php
/**
 * Feuilles secondaires ODS du catalogue global — séries, sujets et liens magazines.
 *
 * Objectif : un aller-retour export → import ne perde pas rating_scale, tags,
 * sujets, page PDF ni note de test. Les fichiers PDF (numéro / supplément)
 * restent hors tableur (comme les affiches ZIP).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogMagazineSheets
{
    public const SHEET_SERIES = 'SeriesMagazines';

    public const SHEET_SUBJECTS = 'MagazineSubjects';

    public const SHEET_SUBJECT_LINKS = 'MagazineSubjectLinks';

    public const SHEET_SUPPLEMENTS = 'MagazineSupplements';

    public const SHEET_SUPPLEMENT_LINKS = 'MagazineSupplementLinks';

    /** @var array<string, string> */
    public const SERIES_COLUMNS = [
        'series_id' => 'ID série',
        'titre' => 'Titre',
        'publication_type' => 'Type publication',
        'editeur' => 'Éditeur',
        'issn' => 'ISSN',
        'langue' => 'Langue',
        'pays' => 'Pays',
        'date_debut' => 'Date début',
        'date_fin' => 'Date fin',
        'notes' => 'Notes',
        'tags' => 'Tags',
        'categories' => 'Catégories',
        'rating_scale' => 'Notes sur',
        'poster_url' => 'Logo (URL)',
    ];

    /** @var array<string, list<string>> */
    public const SERIES_COLUMN_ALIASES = [
        'series_id' => ['id serie', 'id série', 'series_id', 'serie id', 'id'],
        'titre' => ['titre', 'title', 'nom'],
        'publication_type' => ['type publication', 'publication_type', 'periodicite', 'périodicité'],
        'editeur' => ['editeur', 'éditeur', 'publisher'],
        'issn' => ['issn'],
        'langue' => ['langue', 'language'],
        'pays' => ['pays', 'country'],
        'date_debut' => ['date debut', 'date début', 'date_debut'],
        'date_fin' => ['date fin', 'date_fin'],
        'notes' => ['notes', 'note'],
        'tags' => ['tags', 'tag', 'plateformes'],
        'categories' => ['categories', 'catégories', 'category'],
        'rating_scale' => ['notes sur', 'rating_scale', 'echelle', 'échelle', 'note max'],
        'poster_url' => ['logo', 'logo url', 'poster_url', 'affiche'],
    ];

    /** @var array<string, string> */
    public const SUBJECT_COLUMNS = [
        'subject_id' => 'ID sujet',
        'category' => 'Catégorie',
        'label' => 'Libellé',
        'detail' => 'Détail',
        'parution_year' => 'Année parution',
        'catalog_oeuvre_id' => 'ID œuvre catalogue',
    ];

    /** @var array<string, list<string>> */
    public const SUBJECT_COLUMN_ALIASES = [
        'subject_id' => ['id sujet', 'subject_id', 'sujet id', 'id'],
        'category' => ['categorie', 'catégorie', 'category'],
        'label' => ['libelle', 'libellé', 'label', 'titre'],
        'detail' => ['detail', 'détail'],
        'parution_year' => ['annee parution', 'année parution', 'parution_year', 'annee'],
        'catalog_oeuvre_id' => [
            'id oeuvre catalogue',
            'id œuvre catalogue',
            'catalog_oeuvre_id',
            'oeuvre_id catalogue',
        ],
    ];

    /** @var array<string, string> */
    public const SUBJECT_LINK_COLUMNS = [
        'oeuvre_id' => 'ID catalogue',
        'subject_id' => 'ID sujet',
        'page' => 'Page PDF',
        'score' => 'Note',
    ];

    /** @var array<string, list<string>> */
    public const SUBJECT_LINK_COLUMN_ALIASES = [
        'oeuvre_id' => ['id catalogue', 'oeuvre_id', 'id oeuvre', 'id numéro', 'id numero'],
        'subject_id' => ['id sujet', 'subject_id', 'sujet id'],
        'page' => ['page pdf', 'page', 'page_pdf'],
        'score' => ['note', 'score', 'note test'],
    ];

    /** @var array<string, string> */
    public const SUPPLEMENT_COLUMNS = [
        'supplement_id' => 'ID supplément',
        'oeuvre_id' => 'ID catalogue',
        'label' => 'Libellé',
        'sort_order' => 'Ordre',
        'pages' => 'Pages',
        'cover_url' => 'Couverture (URL)',
        'original_filename' => 'Nom fichier',
    ];

    /** @var array<string, list<string>> */
    public const SUPPLEMENT_COLUMN_ALIASES = [
        'supplement_id' => ['id supplement', 'id supplément', 'supplement_id', 'id'],
        'oeuvre_id' => ['id catalogue', 'oeuvre_id', 'id oeuvre'],
        'label' => ['libelle', 'libellé', 'label', 'titre'],
        'sort_order' => ['ordre', 'sort_order'],
        'pages' => ['pages', 'page'],
        'cover_url' => ['couverture', 'cover_url', 'cover url'],
        'original_filename' => ['nom fichier', 'original_filename', 'fichier'],
    ];

    /** @var array<string, string> */
    public const SUPPLEMENT_LINK_COLUMNS = [
        'supplement_id' => 'ID supplément',
        'subject_id' => 'ID sujet',
        'page' => 'Page PDF',
        'score' => 'Note',
    ];

    /** @var array<string, list<string>> */
    public const SUPPLEMENT_LINK_COLUMN_ALIASES = [
        'supplement_id' => ['id supplement', 'id supplément', 'supplement_id'],
        'subject_id' => ['id sujet', 'subject_id'],
        'page' => ['page pdf', 'page'],
        'score' => ['note', 'score'],
    ];

    /** @return list<string> */
    public static function seriesHeaders(): array
    {
        return array_values(self::SERIES_COLUMNS);
    }

    /** @return list<string> */
    public static function subjectHeaders(): array
    {
        return array_values(self::SUBJECT_COLUMNS);
    }

    /** @return list<string> */
    public static function subjectLinkHeaders(): array
    {
        return array_values(self::SUBJECT_LINK_COLUMNS);
    }

    /** @return list<string> */
    public static function supplementHeaders(): array
    {
        return array_values(self::SUPPLEMENT_COLUMNS);
    }

    /** @return list<string> */
    public static function supplementLinkHeaders(): array
    {
        return array_values(self::SUPPLEMENT_LINK_COLUMNS);
    }

    /**
     * @return list<list<string>>
     */
    public static function buildSeriesRows(): array
    {
        $rows = [self::seriesHeaders()];
        if (!SeriesRepository::tableExists()) {
            return $rows;
        }

        $db = Database::getInstance();
        $hasCategories = SeriesRepository::categoriesColumnExists();
        $hasRating = SeriesRepository::ratingScaleColumnExists();

        $select = 'id, titre, publication_type, editeur, issn, langue, pays,
                   date_debut, date_fin, notes, tags, poster_url';
        if ($hasCategories) {
            $select .= ', categories';
        }
        if ($hasRating) {
            $select .= ', rating_scale';
        }

        $stmt = $db->prepare(
            'SELECT ' . $select . ' FROM series
             WHERE media_domain = ?
             ORDER BY titre COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([MediaDomain::MAGAZINE]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $series) {
            $rows[] = [
                (string) (int) ($series['id'] ?? 0),
                (string) ($series['titre'] ?? ''),
                (string) ($series['publication_type'] ?? ''),
                (string) ($series['editeur'] ?? ''),
                (string) ($series['issn'] ?? ''),
                (string) ($series['langue'] ?? ''),
                (string) ($series['pays'] ?? ''),
                (string) ($series['date_debut'] ?? ''),
                (string) ($series['date_fin'] ?? ''),
                (string) ($series['notes'] ?? ''),
                (string) ($series['tags'] ?? ''),
                $hasCategories ? (string) ($series['categories'] ?? '') : '',
                $hasRating ? (string) ($series['rating_scale'] ?? '') : '',
                (string) ($series['poster_url'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    public static function buildSubjectRows(): array
    {
        $rows = [self::subjectHeaders()];
        if (!MagazineSubjectRepository::tableExists()) {
            return $rows;
        }

        $db = Database::getInstance();
        $hasCatalogLink = self::subjectHasCatalogOeuvreColumn();
        $hasYear = self::subjectHasParutionYearColumn();

        $select = 'id, category, label, detail';
        $select .= $hasYear ? ', parution_year' : ', 0 AS parution_year';
        $select .= $hasCatalogLink ? ', catalog_oeuvre_id' : ', 0 AS catalog_oeuvre_id';

        $stmt = $db->query(
            'SELECT ' . $select . ' FROM magazine_subject
             ORDER BY category ASC, label COLLATE FRENCH_NOCASE ASC, id ASC'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $subject) {
            $catalogId = (int) ($subject['catalog_oeuvre_id'] ?? 0);
            $rows[] = [
                (string) (int) ($subject['id'] ?? 0),
                (string) ($subject['category'] ?? ''),
                (string) ($subject['label'] ?? ''),
                (string) ($subject['detail'] ?? ''),
                (int) ($subject['parution_year'] ?? 0) > 0
                    ? (string) (int) $subject['parution_year']
                    : '',
                $catalogId > 0 ? (string) $catalogId : '',
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    public static function buildSubjectLinkRows(): array
    {
        $rows = [self::subjectLinkHeaders()];
        if (!MagazineSubjectRepository::tableExists()) {
            return $rows;
        }

        $db = Database::getInstance();
        $hasPage = MagazineSubjectRepository::hasPageColumn();
        $hasScore = MagazineSubjectRepository::hasScoreColumn();

        $select = 'oeuvre_id, subject_id';
        $select .= $hasPage ? ', page' : ', 0 AS page';
        $select .= $hasScore ? ', score' : ', NULL AS score';

        $stmt = $db->query(
            'SELECT ' . $select . ' FROM oeuvre_magazine_subject
             ORDER BY oeuvre_id ASC, subject_id ASC'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $link) {
            $score = $link['score'] ?? null;
            $rows[] = [
                (string) (int) ($link['oeuvre_id'] ?? 0),
                (string) (int) ($link['subject_id'] ?? 0),
                (int) ($link['page'] ?? 0) > 0 ? (string) (int) $link['page'] : '',
                $score !== null && $score !== '' ? (string) $score : '',
            ];
        }

        return $rows;
    }

    /**
     * Métadonnées des suppléments (sans le fichier PDF lui-même).
     *
     * @return list<list<string>>
     */
    public static function buildSupplementRows(): array
    {
        $rows = [self::supplementHeaders()];
        if (!MagazineIssueSupplementRepository::isAvailable()) {
            return $rows;
        }

        $stmt = Database::getInstance()->query(
            'SELECT id, oeuvre_id, label, sort_order, pages, cover_url, original_filename
             FROM magazine_issue_supplement
             ORDER BY oeuvre_id ASC, sort_order ASC, id ASC'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $supp) {
            $rows[] = [
                (string) (int) ($supp['id'] ?? 0),
                (string) (int) ($supp['oeuvre_id'] ?? 0),
                (string) ($supp['label'] ?? ''),
                (string) (int) ($supp['sort_order'] ?? 0),
                (int) ($supp['pages'] ?? 0) > 0 ? (string) (int) $supp['pages'] : '',
                (string) ($supp['cover_url'] ?? ''),
                (string) ($supp['original_filename'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    public static function buildSupplementLinkRows(): array
    {
        $rows = [self::supplementLinkHeaders()];
        if (!MagazineSubjectRepository::hasSupplementSubjectTable()) {
            return $rows;
        }

        $hasScore = MagazineSubjectRepository::hasSupplementScoreColumn();

        $select = 'supplement_id, subject_id, page';
        $select .= $hasScore ? ', score' : ', NULL AS score';

        $stmt = Database::getInstance()->query(
            'SELECT ' . $select . ' FROM magazine_supplement_subject
             ORDER BY supplement_id ASC, subject_id ASC'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $link) {
            $score = $link['score'] ?? null;
            $rows[] = [
                (string) (int) ($link['supplement_id'] ?? 0),
                (string) (int) ($link['subject_id'] ?? 0),
                (int) ($link['page'] ?? 0) > 0
                    ? (string) (int) $link['page']
                    : '',
                $score !== null && $score !== '' ? (string) $score : '',
            ];
        }

        return $rows;
    }

    /**
     * Importe les séries magazines (à faire avant la feuille Catalogue).
     *
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, errors: list<string>}
     */
    public static function importSeriesSheet(array $dataRows, array $header): array
    {
        if (!SeriesRepository::tableExists()) {
            return ['imported' => 0, 'errors' => ['Module séries magazines indisponible.']];
        }

        $map = ImportFilmRows::mapHeaders($header, self::SERIES_COLUMN_ALIASES);
        if (!isset($map['series_id']) || !isset($map['titre'])) {
            return [
                'imported' => 0,
                'errors' => ['Feuille SeriesMagazines : colonnes « ID série » et « Titre » requises.'],
            ];
        }

        $repo = new SeriesRepository();
        $imported = 0;
        $errors = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }

            try {
                $seriesId = self::intFromMap($row, $map, 'series_id');
                $titre = ImportFilmRows::getCell($row, $map, 'titre');
                if ($seriesId <= 0 || $titre === '') {
                    $errors[] = 'SeriesMagazines ligne ' . $line . ' : ID série et titre obligatoires.';
                    continue;
                }

                $payload = [
                    'titre' => $titre,
                    'publication_type' => ImportFilmRows::getCell($row, $map, 'publication_type'),
                    'editeur' => ImportFilmRows::getCell($row, $map, 'editeur'),
                    'issn' => ImportFilmRows::getCell($row, $map, 'issn'),
                    'langue' => ImportFilmRows::getCell($row, $map, 'langue'),
                    'pays' => ImportFilmRows::getCell($row, $map, 'pays'),
                    'date_debut' => ImportFilmRows::getCell($row, $map, 'date_debut'),
                    'date_fin' => ImportFilmRows::getCell($row, $map, 'date_fin'),
                    'notes' => ImportFilmRows::getCell($row, $map, 'notes'),
                    'tags' => ImportFilmRows::getCell($row, $map, 'tags'),
                    'categories' => ImportFilmRows::getCell($row, $map, 'categories'),
                    'rating_scale' => ImportFilmRows::getCell($row, $map, 'rating_scale'),
                    'poster_url' => SecureUrl::sanitizePosterUrl(
                        ImportFilmRows::getCell($row, $map, 'poster_url')
                    ),
                ];

                $existing = $repo->findById($seriesId, MediaDomain::MAGAZINE);
                if ($existing !== null) {
                    $updated = $repo->update($seriesId, $payload);
                    if ($updated !== true) {
                        throw new \RuntimeException((string) $updated);
                    }
                } else {
                    // ID déjà pris par une autre série (ex. BD) : on refuse plutôt que corrompre.
                    $domainStmt = Database::getInstance()->prepare(
                        'SELECT media_domain FROM series WHERE id = ? LIMIT 1'
                    );
                    $domainStmt->execute([$seriesId]);
                    $otherDomain = $domainStmt->fetchColumn();
                    if ($otherDomain !== false) {
                        throw new \RuntimeException(
                            'ID série ' . $seriesId . ' déjà utilisé pour le domaine « ' . $otherDomain . ' ».'
                        );
                    }
                    $created = $repo->createWithId($seriesId, $payload, MediaDomain::MAGAZINE);
                    if (!is_int($created)) {
                        throw new \RuntimeException((string) $created);
                    }
                }
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'SeriesMagazines ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, errors: list<string>}
     */
    public static function importSubjectsSheet(array $dataRows, array $header): array
    {
        if (!MagazineSubjectRepository::tableExists()) {
            return ['imported' => 0, 'errors' => ['Module sujets magazines indisponible.']];
        }

        $map = ImportFilmRows::mapHeaders($header, self::SUBJECT_COLUMN_ALIASES);
        if (!isset($map['subject_id']) || !isset($map['label'])) {
            return [
                'imported' => 0,
                'errors' => ['Feuille MagazineSubjects : colonnes « ID sujet » et « Libellé » requises.'],
            ];
        }

        $imported = 0;
        $errors = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }

            try {
                $subjectId = self::intFromMap($row, $map, 'subject_id');
                $label = ImportFilmRows::getCell($row, $map, 'label');
                if ($subjectId <= 0 || $label === '') {
                    $errors[] = 'MagazineSubjects ligne ' . $line . ' : ID sujet et libellé obligatoires.';
                    continue;
                }

                $category = MagazineSubject::normalizeCategory(
                    ImportFilmRows::getCell($row, $map, 'category')
                );
                $detail = ImportFilmRows::getCell($row, $map, 'detail');
                $parutionYear = self::intFromMap($row, $map, 'parution_year');
                $catalogOeuvreId = self::intFromMap($row, $map, 'catalog_oeuvre_id');

                self::upsertSubject(
                    $subjectId,
                    $category,
                    $label,
                    $detail,
                    $parutionYear,
                    $catalogOeuvreId
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'MagazineSubjects ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, errors: list<string>}
     */
    public static function importSubjectLinksSheet(array $dataRows, array $header): array
    {
        if (!MagazineSubjectRepository::tableExists()) {
            return ['imported' => 0, 'errors' => ['Module sujets magazines indisponible.']];
        }

        $map = ImportFilmRows::mapHeaders($header, self::SUBJECT_LINK_COLUMN_ALIASES);
        if (!isset($map['oeuvre_id']) || !isset($map['subject_id'])) {
            return [
                'imported' => 0,
                'errors' => [
                    'Feuille MagazineSubjectLinks : colonnes « ID catalogue » et « ID sujet » requises.',
                ],
            ];
        }

        $repo = new MagazineSubjectRepository();
        $imported = 0;
        $errors = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }

            try {
                $oeuvreId = self::intFromMap($row, $map, 'oeuvre_id');
                $subjectId = self::intFromMap($row, $map, 'subject_id');
                if ($oeuvreId <= 0 || $subjectId <= 0) {
                    $errors[] = 'MagazineSubjectLinks ligne ' . $line . ' : IDs invalides.';
                    continue;
                }

                $page = self::intFromMap($row, $map, 'page');
                $score = self::parseOptionalScore(ImportFilmRows::getCell($row, $map, 'score'));

                $attached = $repo->attachToOeuvre($oeuvreId, $subjectId, $page);
                if ($attached !== true) {
                    throw new \RuntimeException((string) $attached);
                }

                if ($score !== null && MagazineSubjectRepository::hasScoreColumn()) {
                    $scored = $repo->updateLinkScore($oeuvreId, $subjectId, $score);
                    if ($scored !== true) {
                        throw new \RuntimeException((string) $scored);
                    }
                }

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'MagazineSubjectLinks ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * Importe les métadonnées de suppléments (sans PDF).
     * Crée un objet stocké « placeholder » pour satisfaire la contrainte NOT NULL.
     *
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, errors: list<string>, id_map: array<int, int>}
     */
    public static function importSupplementsSheet(array $dataRows, array $header): array
    {
        $empty = ['imported' => 0, 'errors' => [], 'id_map' => []];
        if (!MagazineIssueSupplementRepository::isAvailable()) {
            return $empty + ['errors' => ['Module suppléments magazines indisponible.']];
        }

        $map = ImportFilmRows::mapHeaders($header, self::SUPPLEMENT_COLUMN_ALIASES);
        if (!isset($map['supplement_id']) || !isset($map['oeuvre_id'])) {
            return $empty + [
                'errors' => [
                    'Feuille MagazineSupplements : colonnes « ID supplément » et « ID catalogue » requises.',
                ],
            ];
        }

        $db = Database::getInstance();
        $imported = 0;
        $errors = [];
        /** @var array<int, int> $idMap ancien_id => nouvel_id */
        $idMap = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }

            try {
                $oldId = self::intFromMap($row, $map, 'supplement_id');
                $oeuvreId = self::intFromMap($row, $map, 'oeuvre_id');
                if ($oldId <= 0 || $oeuvreId <= 0) {
                    $errors[] = 'MagazineSupplements ligne ' . $line . ' : IDs invalides.';
                    continue;
                }

                $oeuvreCheck = $db->prepare('SELECT 1 FROM oeuvres WHERE id = ? LIMIT 1');
                $oeuvreCheck->execute([$oeuvreId]);
                if (!$oeuvreCheck->fetchColumn()) {
                    throw new \RuntimeException('Œuvre catalogue ' . $oeuvreId . ' introuvable.');
                }

                $label = ImportFilmRows::getCell($row, $map, 'label');
                $sortOrder = self::intFromMap($row, $map, 'sort_order');
                $pages = self::intFromMap($row, $map, 'pages');
                $coverUrl = SecureUrl::sanitizePosterUrl(
                    ImportFilmRows::getCell($row, $map, 'cover_url')
                );
                $originalFilename = ImportFilmRows::getCell($row, $map, 'original_filename');

                $existing = $db->prepare(
                    'SELECT id FROM magazine_issue_supplement WHERE id = ? LIMIT 1'
                );
                $existing->execute([$oldId]);
                if ($existing->fetchColumn()) {
                    $db->prepare(
                        'UPDATE magazine_issue_supplement
                         SET oeuvre_id = ?, label = ?, sort_order = ?, pages = ?,
                             cover_url = ?, original_filename = ?
                         WHERE id = ?'
                    )->execute([
                        $oeuvreId, $label, $sortOrder, $pages, $coverUrl, $originalFilename, $oldId,
                    ]);
                    $idMap[$oldId] = $oldId;
                } else {
                    $storedObjectId = self::createPlaceholderStoredObject(
                        $originalFilename !== '' ? $originalFilename : ('supplement-' . $oldId . '.pdf')
                    );
                    $db->prepare(
                        'INSERT INTO magazine_issue_supplement (
                            id, oeuvre_id, stored_object_id, label, sort_order,
                            cover_url, pages, original_filename
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $oldId, $oeuvreId, $storedObjectId, $label, $sortOrder,
                        $coverUrl, $pages, $originalFilename,
                    ]);
                    self::syncSqliteSequence('magazine_issue_supplement');
                    $idMap[$oldId] = $oldId;
                }
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'MagazineSupplements ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors, 'id_map' => $idMap];
    }

    /**
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, errors: list<string>}
     */
    public static function importSupplementLinksSheet(array $dataRows, array $header): array
    {
        if (!MagazineSubjectRepository::hasSupplementSubjectTable()) {
            return ['imported' => 0, 'errors' => ['Liens sujets supplément indisponibles.']];
        }

        $map = ImportFilmRows::mapHeaders($header, self::SUPPLEMENT_LINK_COLUMN_ALIASES);
        if (!isset($map['supplement_id']) || !isset($map['subject_id'])) {
            return [
                'imported' => 0,
                'errors' => [
                    'Feuille MagazineSupplementLinks : « ID supplément » et « ID sujet » requis.',
                ],
            ];
        }

        $repo = new MagazineSubjectRepository();
        $imported = 0;
        $errors = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }

            try {
                $supplementId = self::intFromMap($row, $map, 'supplement_id');
                $subjectId = self::intFromMap($row, $map, 'subject_id');
                if ($supplementId <= 0 || $subjectId <= 0) {
                    $errors[] = 'MagazineSupplementLinks ligne ' . $line . ' : IDs invalides.';
                    continue;
                }

                $page = self::intFromMap($row, $map, 'page');
                $score = self::parseOptionalScore(ImportFilmRows::getCell($row, $map, 'score'));

                $attached = $repo->attachToSupplement($supplementId, $subjectId, $page);
                if ($attached !== true) {
                    throw new \RuntimeException((string) $attached);
                }

                if ($score !== null && MagazineSubjectRepository::hasSupplementScoreColumn()) {
                    $scored = $repo->updateSupplementLinkScore($supplementId, $subjectId, $score);
                    if ($scored !== true) {
                        throw new \RuntimeException((string) $scored);
                    }
                }

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'MagazineSupplementLinks ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     */
    private static function intFromMap(array $row, array $map, string $key): int
    {
        if (!isset($map[$key])) {
            return 0;
        }
        $raw = trim((string) ($row[$map[$key]] ?? ''));
        if ($raw === '' || !preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            return 0;
        }

        return max(0, (int) $raw);
    }

    /** Note brute optionnelle (sans vérifier l’échelle série — déjà validée à la saisie). */
    private static function parseOptionalScore(string $raw): ?float
    {
        $raw = str_replace(',', '.', trim($raw));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        $score = (float) $raw;
        if (!is_finite($score)) {
            return null;
        }

        return $score;
    }

    private static function upsertSubject(
        int $subjectId,
        string $category,
        string $label,
        string $detail,
        int $parutionYear,
        int $catalogOeuvreId
    ): void {
        $db = Database::getInstance();
        $hasYear = self::subjectHasParutionYearColumn();
        $hasCatalog = self::subjectHasCatalogOeuvreColumn();

        $exists = $db->prepare('SELECT 1 FROM magazine_subject WHERE id = ? LIMIT 1');
        $exists->execute([$subjectId]);

        if ($exists->fetchColumn()) {
            $sets = ['category = ?', 'label = ?', 'detail = ?'];
            $params = [$category, $label, $detail];
            if ($hasYear) {
                $sets[] = 'parution_year = ?';
                $params[] = $parutionYear;
            }
            if ($hasCatalog) {
                $sets[] = 'catalog_oeuvre_id = ?';
                $params[] = $catalogOeuvreId > 0 ? $catalogOeuvreId : null;
            }
            $params[] = $subjectId;
            $db->prepare(
                'UPDATE magazine_subject SET ' . implode(', ', $sets) . ' WHERE id = ?'
            )->execute($params);
        } else {
            $cols = ['id', 'category', 'label', 'detail'];
            $placeholders = ['?', '?', '?', '?'];
            $params = [$subjectId, $category, $label, $detail];
            if ($hasYear) {
                $cols[] = 'parution_year';
                $placeholders[] = '?';
                $params[] = $parutionYear;
            }
            if ($hasCatalog) {
                $cols[] = 'catalog_oeuvre_id';
                $placeholders[] = '?';
                $params[] = $catalogOeuvreId > 0 ? $catalogOeuvreId : null;
            }
            $db->prepare(
                'INSERT INTO magazine_subject (' . implode(', ', $cols) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            )->execute($params);
            self::syncSqliteSequence('magazine_subject');
        }

        MagazineSubjectFts::upsert($subjectId);
    }

    private static function createPlaceholderStoredObject(string $filename): int
    {
        $db = Database::getInstance();
        // Objet vide : le PDF n’est pas dans l’ODS ; l’admin pourra le recharger ensuite.
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) ?: 'supplement.pdf';
        $relativePath = 'import-placeholders/' . uniqid('supp_', true) . '_' . $safeName;

        $db->prepare(
            'INSERT INTO stored_objects (backend, relative_path, mime, size_bytes, checksum)
             VALUES (?, ?, ?, 0, ?)'
        )->execute([
            'local',
            $relativePath,
            'application/pdf',
            hash('sha256', 'catalog-import-placeholder:' . $relativePath),
        ]);

        return (int) $db->lastInsertId();
    }

    private static function syncSqliteSequence(string $table): void
    {
        $db = Database::getInstance();
        $max = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM ' . $table)->fetchColumn();
        $db->exec(
            "INSERT OR REPLACE INTO sqlite_sequence (name, seq) VALUES ("
            . $db->quote($table) . ', ' . max(0, $max) . ')'
        );
    }

    private static function subjectHasCatalogOeuvreColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (!MagazineSubjectRepository::tableExists()) {
            return $cache = false;
        }
        foreach (Database::getInstance()->query('PRAGMA table_info(magazine_subject)')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
            if (($col['name'] ?? '') === 'catalog_oeuvre_id') {
                return $cache = true;
            }
        }

        return $cache = false;
    }

    private static function subjectHasParutionYearColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (!MagazineSubjectRepository::tableExists()) {
            return $cache = false;
        }
        foreach (Database::getInstance()->query('PRAGMA table_info(magazine_subject)')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
            if (($col['name'] ?? '') === 'parution_year') {
                return $cache = true;
            }
        }

        return $cache = false;
    }
}
