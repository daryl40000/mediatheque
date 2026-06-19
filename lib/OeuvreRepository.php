<?php
/**
 * Catalogue d’œuvres Moncine (métadonnées partagées, indépendantes de TMDB à terme).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class OeuvreRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM oeuvres WHERE id = ?';
        if (CatalogSchema::hasMediaDomainColumn()) {
            $sql .= ' AND media_domain = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, MediaContext::current()]);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
        }

        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Fiche catalogue admin : tous domaines média (sans filtre d’onglet actif). */
    public function findByIdForAdmin(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM oeuvres WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByTitreRealisateurAndDomain(
        string $titre,
        string $realisateur,
        string $mediaDomain
    ): ?array {
        if (CatalogSchema::hasMediaDomainColumn()) {
            $stmt = $this->db->prepare(
                'SELECT * FROM oeuvres WHERE titre = ? AND realisateur = ? AND media_domain = ?'
            );
            $stmt->execute([$titre, $realisateur, MediaDomain::normalize($mediaDomain)]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM oeuvres WHERE titre = ? AND realisateur = ?'
            );
            $stmt->execute([$titre, $realisateur]);
        }
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateMediaDomain(int $id, string $mediaDomain): void
    {
        if ($id <= 0 || !CatalogSchema::hasMediaDomainColumn()) {
            return;
        }
        $stmt = $this->db->prepare(
            'UPDATE oeuvres SET media_domain = ?, updated_at = datetime(\'now\') WHERE id = ?'
        );
        $stmt->execute([MediaDomain::normalize($mediaDomain), $id]);
    }

    public function findByTitreAndRealisateur(string $titre, string $realisateur): ?array
    {
        if (CatalogSchema::hasMediaDomainColumn()) {
            $stmt = $this->db->prepare(
                'SELECT * FROM oeuvres WHERE titre = ? AND realisateur = ? AND media_domain = ?'
            );
            $stmt->execute([$titre, $realisateur, MediaContext::current()]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM oeuvres WHERE titre = ? AND realisateur = ?'
            );
            $stmt->execute([$titre, $realisateur]);
        }
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Recherche dans le catalogue par début de titre (autocomplétion).
     *
     * @return list<array<string, mixed>>
     */
    public function searchByTitrePrefix(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $prefetchLimit = min(max($limit * 8, 60), 200);
        $foldedPattern = SearchMatch::foldedContainsPattern($query);
        $prefixPattern = SearchMatch::foldedPrefixPattern($query, 2);

        $conditions = ['fold_search(titre) LIKE ? ESCAPE \'\\\''];
        $bind = [$foldedPattern];
        if ($prefixPattern !== '') {
            $conditions[] = 'fold_search(titre) LIKE ? ESCAPE \'\\\'';
            $bind[] = $prefixPattern;
        }

        $domainSql = CatalogSchema::hasMediaDomainColumn()
            ? ' AND media_domain = ?'
            : '';
        $sqlLimit = $prefetchLimit;
        $stmt = $this->db->prepare(
            'SELECT * FROM oeuvres
             WHERE (' . implode(' OR ', $conditions) . ')' . $domainSql . '
             ORDER BY titre COLLATE FRENCH_NOCASE, realisateur COLLATE FRENCH_NOCASE
             LIMIT ' . $sqlLimit
        );
        if ($domainSql !== '') {
            $bind[] = MediaContext::current();
        }
        $stmt->execute($bind);
        $rows = $stmt->fetchAll() ?: [];

        return SearchMatch::filterRankLimit(
            $rows,
            $query,
            static fn (array $row): string => (string) ($row['titre'] ?? '')
                . ' '
                . (string) ($row['realisateur'] ?? ''),
            $limit
        );
    }

    public function deleteById(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM oeuvres WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function countBibliothequeLinks(int $oeuvreId): int
    {
        if ($oeuvreId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM bibliotheque WHERE oeuvre_id = ?');
        $stmt->execute([$oeuvreId]);

        return (int) $stmt->fetchColumn();
    }

    /** Vide le catalogue (supprime aussi les entrées bibliothèque liées — CASCADE). */
    public function deleteAll(): void
    {
        $this->db->exec('DELETE FROM oeuvres');
        $this->syncAutoincrementSequence();
    }

    public function findByTmdbId(int $tmdbId): ?array
    {
        if ($tmdbId <= 0) {
            return null;
        }
        if (CatalogSchema::hasMediaDomainColumn()) {
            $stmt = $this->db->prepare(
                'SELECT * FROM oeuvres WHERE tmdb_id = ? AND media_domain = ? LIMIT 1'
            );
            $stmt->execute([$tmdbId, MediaContext::current()]);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM oeuvres WHERE tmdb_id = ? LIMIT 1');
            $stmt->execute([$tmdbId]);
        }
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $payload champs oeuvres
     */
    public function insert(array $payload): int
    {
        $fields = CatalogSchema::OEUVRE_FIELDS;
        $params = $this->filterPayload($payload, $fields);
        if (CatalogSchema::hasMediaDomainColumn()) {
            $domain = MediaDomain::normalize((string) ($payload['media_domain'] ?? MediaContext::current()));
            $columns = 'media_domain, ' . implode(', ', $fields);
            $placeholders = ':media_domain, ' . implode(', ', array_map(static fn (string $f): string => ':' . $f, $fields));
            $params['media_domain'] = $domain;
        } else {
            $columns = implode(', ', $fields);
            $placeholders = implode(', ', array_map(static fn (string $f): string => ':' . $f, $fields));
        }
        $stmt = $this->db->prepare("INSERT INTO oeuvres ($columns) VALUES ($placeholders)");
        $stmt->execute($params);
        $id = (int) $this->db->lastInsertId();
        $this->touchUpdated($id);

        return $id;
    }

    /**
     * Crée une œuvre avec un ID catalogue imposé (import / migration depuis une autre instance).
     *
     * @param array<string, mixed> $payload
     */
    public function insertWithId(int $id, array $payload): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID catalogue invalide.');
        }

        if ($this->findById($id) !== null) {
            throw new \RuntimeException('ID catalogue ' . $id . ' déjà utilisé.');
        }

        $fields = array_merge(['id'], CatalogSchema::OEUVRE_FIELDS);
        $params = $this->filterPayload($payload, CatalogSchema::OEUVRE_FIELDS);
        $params['id'] = $id;
        if (CatalogSchema::hasMediaDomainColumn()) {
            $fields = array_merge(['id', 'media_domain'], CatalogSchema::OEUVRE_FIELDS);
            $params['media_domain'] = MediaDomain::normalize(
                (string) ($payload['media_domain'] ?? MediaContext::current())
            );
        }
        $columns = implode(', ', $fields);
        $placeholders = implode(', ', array_map(static fn (string $f): string => ':' . $f, $fields));

        $stmt = $this->db->prepare("INSERT INTO oeuvres ($columns) VALUES ($placeholders)");
        $stmt->execute($params);
        $this->touchUpdated($id);
    }

    /** Réaligne le compteur AUTOINCREMENT SQLite après des insertions avec ID explicite. */
    public function syncAutoincrementSequence(): void
    {
        $max = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) FROM oeuvres')->fetchColumn();
        $this->db->exec(
            "INSERT OR REPLACE INTO sqlite_sequence (name, seq) VALUES ('oeuvres', " . max(0, $max) . ')'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $onlyFields
     */
    public function update(int $id, array $payload, array $onlyFields = []): void
    {
        if ($id <= 0) {
            return;
        }
        $fields = $onlyFields !== [] ? $onlyFields : CatalogSchema::OEUVRE_FIELDS;
        $sets = [];
        foreach ($fields as $field) {
            if (in_array($field, CatalogSchema::OEUVRE_FIELDS, true)) {
                $sets[] = $field . ' = :' . $field;
            }
        }
        if ($sets === []) {
            return;
        }
        $params = $this->filterPayload($payload, $fields);
        $params['id'] = $id;
        $stmt = $this->db->prepare(
            'UPDATE oeuvres SET ' . implode(', ', $sets) . ', updated_at = datetime(\'now\') WHERE id = :id'
        );
        $stmt->execute($params);
    }

    private function touchUpdated(int $id): void
    {
        $this->db->prepare('UPDATE oeuvres SET updated_at = datetime(\'now\') WHERE id = ?')->execute([$id]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function filterPayload(array $payload, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $out[$field] = $payload[$field];
            }
        }

        return $out;
    }

    /**
     * Toutes les œuvres du catalogue (export admin), avec extensions jeu / magazine.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForExport(): array
    {
        $joins = '';
        $gameTable = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_jeu' LIMIT 1"
        )->fetchColumn();
        $magTable = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_magazine' LIMIT 1"
        )->fetchColumn();

        if ($gameTable) {
            $joins .= ' LEFT JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id';
        }
        if ($magTable) {
            $joins .= ' LEFT JOIN oeuvre_magazine om ON om.oeuvre_id = o.id';
        }

        $select = 'o.*';
        if ($gameTable) {
            $select .= ', oj.studio AS jeu_studio, oj.editeur AS jeu_editeur, oj.genre AS jeu_genre,'
                . ' oj.platform AS jeu_platform, oj.is_digital AS jeu_is_digital,'
                . ' oj.physical_supports AS jeu_physical_supports, oj.digital_stores AS jeu_digital_stores,'
                . ' oj.is_extension AS jeu_is_extension, oj.base_game_oeuvre_id AS jeu_base_game_oeuvre_id';
            if (GameRepository::hasRemakeColumns()) {
                $select .= ', oj.is_remake AS jeu_is_remake, oj.original_game_oeuvre_id AS jeu_original_game_oeuvre_id';
            }
        }
        if ($magTable) {
            $select .= ', om.series_id AS mag_series_id, om.numero AS mag_numero,'
                . ' om.numero_ordre AS mag_numero_ordre, om.date_parution AS mag_date_parution,'
                . ' om.sommaire AS mag_sommaire, om.pages AS mag_pages,'
                . ' om.est_hors_serie AS mag_est_hors_serie';
        }

        $stmt = $this->db->query(
            'SELECT ' . $select . ' FROM oeuvres o' . $joins
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE, o.realisateur COLLATE FRENCH_NOCASE'
        );

        return $stmt->fetchAll() ?: [];
    }

}
