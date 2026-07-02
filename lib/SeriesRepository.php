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

        $this->db->prepare(
            'INSERT INTO series (
                media_domain, titre, publication_type, poster_url, editeur, issn,
                langue, pays, date_debut, date_fin, notes, tags, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
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
        ]);

        return (int) $this->db->lastInsertId();
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
