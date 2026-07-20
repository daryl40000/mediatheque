<?php
/**
 * Séries (magazines, futures BD…) — niveau catalogue partagé.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SeriesRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'series' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function categoriesColumnExists(): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(series)');
        if ($stmt === false) {
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === 'categories') {
                return true;
            }
        }

        return false;
    }

    /**
     * Catégories déjà utilisées sur des séries magazine (pour autocomplétion).
     *
     * @return list<string>
     */
    public function listKnownCategoryLabels(int $limit = 40): array
    {
        if (!self::tableExists() || !self::categoriesColumnExists()) {
            return [];
        }

        $limit = max(1, min(80, $limit));
        $stmt = $this->db->prepare(
            'SELECT categories FROM series
             WHERE media_domain = ?
               AND TRIM(categories) != \'\'
             ORDER BY titre COLLATE FRENCH_NOCASE
             LIMIT ' . $limit
        );
        $stmt->execute([MediaDomain::MAGAZINE]);

        $labels = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            foreach (MagazineSeriesCategory::parseList((string) ($row['categories'] ?? '')) as $label) {
                $key = mb_strtolower($label);
                if (!isset($labels[$key])) {
                    $labels[$key] = $label;
                }
            }
        }

        return array_values($labels);
    }

    /**
     * Catégories brutes par identifiant série (contourne les limites de SELECT s.* + GROUP BY).
     *
     * @param list<int> $seriesIds
     * @return array<int, string>
     */
    public function categoriesBySeriesIds(array $seriesIds): array
    {
        if (!self::categoriesColumnExists()) {
            return [];
        }

        $seriesIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $seriesIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($seriesIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($seriesIds), '?'));
        $stmt = $this->db->prepare(
            'SELECT id, categories FROM series WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($seriesIds);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = (string) ($row['categories'] ?? '');
            }
        }

        return $map;
    }

    public function findById(int $id, ?string $mediaDomain = null): ?array
    {
        if ($id <= 0 || !self::tableExists()) {
            return null;
        }

        if ($mediaDomain === null && $this->shouldScopeFindByIdToCurrentDomain()) {
            $mediaDomain = MediaContext::current();
        }

        if ($mediaDomain === null) {
            $stmt = $this->db->prepare('SELECT * FROM series WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM series WHERE id = ? AND media_domain = ? LIMIT 1'
            );
            $stmt->execute([$id, MediaDomain::normalize($mediaDomain)]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** En HTTP, findById sans domaine explicite reste limité au domaine actif. */
    private function shouldScopeFindByIdToCurrentDomain(): bool
    {
        return PHP_SAPI !== 'cli';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchByTitre(string $query, ?string $mediaDomain = null, int $limit = 30): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $query = trim($query);
        if (mb_strlen($query) < 1) {
            return [];
        }

        $domain = MediaDomain::normalize($mediaDomain ?? MediaContext::current());
        $limit = max(1, min(50, $limit));
        $pattern = LikePattern::containsFragment($query);
        $stmt = $this->db->prepare(
            'SELECT * FROM series
             WHERE media_domain = ?
               AND LOWER(titre) LIKE LOWER(?) ESCAPE \'\\\'
             ORDER BY titre COLLATE FRENCH_NOCASE
             LIMIT ' . $limit
        );
        $stmt->execute([$domain, $pattern]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string ID ou message d’erreur
     */
    public function create(array $data, ?string $mediaDomain = null): int|string
    {
        if (!self::tableExists()) {
            return 'Module séries non disponible (migration 031 manquante).';
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre de la série est obligatoire.';
        }

        $domain = MediaDomain::normalize($mediaDomain ?? MediaContext::current());
        $existing = $this->findByTitre($titre, $domain);
        if ($existing !== null) {
            return 'Une série avec ce titre existe déjà.';
        }

        $publicationType = PublicationType::normalize((string) ($data['publication_type'] ?? ''));
        $categoriesSql = self::categoriesColumnExists() ? ', categories' : '';
        $categoriesValue = self::categoriesColumnExists() ? ', ?' : '';
        $categoriesParam = self::categoriesColumnExists()
            ? [MagazineSeriesCategory::normalizeInput((string) ($data['categories'] ?? ''))]
            : [];

        $this->db->prepare(
            'INSERT INTO series (
                media_domain, titre, publication_type, poster_url, editeur, issn,
                langue, pays, date_debut, date_fin, notes, tags' . $categoriesSql . ', created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?' . $categoriesValue . ', datetime(\'now\'))'
        )->execute([
            $domain,
            $titre,
            $publicationType,
            trim((string) ($data['poster_url'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            trim((string) ($data['issn'] ?? '')),
            trim((string) ($data['langue'] ?? '')),
            trim((string) ($data['pays'] ?? '')),
            self::nullableDate((string) ($data['date_debut'] ?? '')),
            self::nullableDate((string) ($data['date_fin'] ?? '')),
            trim((string) ($data['notes'] ?? '')),
            MagazineSeriesTag::normalizeInput((string) ($data['tags'] ?? '')),
            ...$categoriesParam,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Création avec ID explicite (migration catalogue / conservation des numéros).
     *
     * @param array<string, mixed> $data
     * @return int|string ID ou message d’erreur
     */
    public function createWithId(int $id, array $data, ?string $mediaDomain = null): int|string
    {
        if ($id <= 0) {
            return 'ID série invalide.';
        }
        if (!self::tableExists()) {
            return 'Module séries non disponible (migration 031 manquante).';
        }

        $exists = $this->db->prepare('SELECT 1 FROM series WHERE id = ? LIMIT 1');
        $exists->execute([$id]);
        if ($exists->fetchColumn()) {
            return $id;
        }

        $titre = trim((string) ($data['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre de la série est obligatoire.';
        }

        $domain = MediaDomain::normalize($mediaDomain ?? MediaContext::current());
        $publicationType = PublicationType::normalize((string) ($data['publication_type'] ?? ''));
        $categoriesSql = self::categoriesColumnExists() ? ', categories' : '';
        $categoriesValue = self::categoriesColumnExists() ? ', ?' : '';
        $categoriesParam = self::categoriesColumnExists()
            ? [MagazineSeriesCategory::normalizeInput((string) ($data['categories'] ?? ''))]
            : [];

        $this->db->prepare(
            'INSERT INTO series (
                id, media_domain, titre, publication_type, poster_url, editeur, issn,
                langue, pays, date_debut, date_fin, notes, tags' . $categoriesSql . ', created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?' . $categoriesValue . ', datetime(\'now\'))'
        )->execute([
            $id,
            $domain,
            $titre,
            $publicationType,
            trim((string) ($data['poster_url'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            trim((string) ($data['issn'] ?? '')),
            trim((string) ($data['langue'] ?? '')),
            trim((string) ($data['pays'] ?? '')),
            self::nullableDate((string) ($data['date_debut'] ?? '')),
            self::nullableDate((string) ($data['date_fin'] ?? '')),
            trim((string) ($data['notes'] ?? '')),
            MagazineSeriesTag::normalizeInput((string) ($data['tags'] ?? '')),
            ...$categoriesParam,
        ]);

        $max = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) FROM series')->fetchColumn();
        $this->db->exec(
            "INSERT OR REPLACE INTO sqlite_sequence (name, seq) VALUES ('series', " . max(0, $max) . ')'
        );

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function update(int $id, array $data): bool|string
    {
        $series = $this->findById($id);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $titre = trim((string) ($data['titre'] ?? $series['titre'] ?? ''));
        if ($titre === '') {
            return 'Le titre de la série est obligatoire.';
        }

        $domain = (string) ($series['media_domain'] ?? MediaContext::current());
        $duplicate = $this->findByTitre($titre, $domain);
        if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== $id) {
            return 'Une autre série porte déjà ce titre.';
        }

        $categoriesValue = array_key_exists('categories', $data)
            ? MagazineSeriesCategory::normalizeInput((string) $data['categories'])
            : (string) ($series['categories'] ?? '');

        if (self::categoriesColumnExists()) {
            $this->db->prepare(
                'UPDATE series SET
                    titre = ?, publication_type = ?, poster_url = ?, editeur = ?, issn = ?,
                    langue = ?, pays = ?, date_debut = ?, date_fin = ?, notes = ?, tags = ?, categories = ?,
                    updated_at = datetime(\'now\')
                 WHERE id = ?'
            )->execute([
                $titre,
                PublicationType::normalize((string) ($data['publication_type'] ?? $series['publication_type'] ?? '')),
                trim((string) ($data['poster_url'] ?? $series['poster_url'] ?? '')),
                trim((string) ($data['editeur'] ?? $series['editeur'] ?? '')),
                trim((string) ($data['issn'] ?? $series['issn'] ?? '')),
                trim((string) ($data['langue'] ?? $series['langue'] ?? '')),
                trim((string) ($data['pays'] ?? $series['pays'] ?? '')),
                self::nullableDate((string) ($data['date_debut'] ?? $series['date_debut'] ?? '')),
                self::nullableDate((string) ($data['date_fin'] ?? $series['date_fin'] ?? '')),
                trim((string) ($data['notes'] ?? $series['notes'] ?? '')),
                array_key_exists('tags', $data)
                    ? MagazineSeriesTag::normalizeInput((string) $data['tags'])
                    : (string) ($series['tags'] ?? ''),
                $categoriesValue,
                $id,
            ]);

            return true;
        }

        $this->db->prepare(
            'UPDATE series SET
                titre = ?, publication_type = ?, poster_url = ?, editeur = ?, issn = ?,
                langue = ?, pays = ?, date_debut = ?, date_fin = ?, notes = ?, tags = ?,
                updated_at = datetime(\'now\')
             WHERE id = ?'
        )->execute([
            $titre,
            PublicationType::normalize((string) ($data['publication_type'] ?? $series['publication_type'] ?? '')),
            trim((string) ($data['poster_url'] ?? $series['poster_url'] ?? '')),
            trim((string) ($data['editeur'] ?? $series['editeur'] ?? '')),
            trim((string) ($data['issn'] ?? $series['issn'] ?? '')),
            trim((string) ($data['langue'] ?? $series['langue'] ?? '')),
            trim((string) ($data['pays'] ?? $series['pays'] ?? '')),
            self::nullableDate((string) ($data['date_debut'] ?? $series['date_debut'] ?? '')),
            self::nullableDate((string) ($data['date_fin'] ?? $series['date_fin'] ?? '')),
            trim((string) ($data['notes'] ?? $series['notes'] ?? '')),
            array_key_exists('tags', $data)
                ? MagazineSeriesTag::normalizeInput((string) $data['tags'])
                : (string) ($series['tags'] ?? ''),
            $id,
        ]);

        return true;
    }

    public function findByTitre(string $titre, ?string $mediaDomain = null): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $titre = trim($titre);
        if ($titre === '') {
            return null;
        }

        $domain = MediaDomain::normalize($mediaDomain ?? MediaContext::current());
        $stmt = $this->db->prepare(
            'SELECT * FROM series WHERE media_domain = ? AND LOWER(TRIM(titre)) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([$domain, $titre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private static function nullableDate(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
