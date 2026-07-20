<?php
/**
 * Gestion des sagas de films : listes, comptages, affectation et renommage.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmCatalogSaga
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<string> */
    public function distinctSagas(): array
    {
        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $stmt = $this->db->prepare(
                'SELECT DISTINCT o.saga FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(o.saga) != ""
                 ORDER BY o.saga COLLATE FRENCH_NOCASE'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT DISTINCT b.saga FROM bibliotheque b
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(b.saga) != ""
                 ORDER BY b.saga COLLATE FRENCH_NOCASE'
            );
        }
        $stmt->execute([$this->foyerId(), LibraryStatut::COLLECTION]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Sagas déjà utilisées dans le catalogue (autocomplétion des formulaires).
     *
     * @return list<string>
     */
    public function listKnownSagas(int $limit = 120): array
    {
        if (!CatalogSchema::hasOeuvreSagaColumns()) {
            return $this->distinctSagas();
        }

        $limit = max(1, min($limit, 300));
        $stmt = $this->db->query(
            'SELECT saga FROM oeuvres WHERE TRIM(saga) != \'\'
             ORDER BY saga COLLATE FRENCH_NOCASE ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $known = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($known[$key])) {
                $known[$key] = $name;
            }
        }

        $names = array_values($known);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($names, 0, $limit);
    }

    /**
     * @return list<array{saga: string, film_count: int}>
     */
    public function listSagasWithCounts(): array
    {
        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $stmt = $this->db->prepare(
                'SELECT o.saga, COUNT(*) AS film_count FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(o.saga) != ""
                 GROUP BY o.saga
                 ORDER BY o.saga COLLATE FRENCH_NOCASE'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT b.saga, COUNT(*) AS film_count FROM bibliotheque b
                 WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(b.saga) != ""
                 GROUP BY b.saga
                 ORDER BY b.saga COLLATE FRENCH_NOCASE'
            );
        }
        $stmt->execute([$this->foyerId(), LibraryStatut::COLLECTION]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['saga'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'saga' => $name,
                'film_count' => (int) ($row['film_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Tous les films catalogue d’une saga (tri saga_ordre, titre), hors œuvre exclue.
     *
     * @return list<array{oeuvre_id: int, titre: string, annee: int, poster_url: string|null, saga_ordre: int}>
     */
    public function listCatalogBySaga(string $saga, int $excludeOeuvreId = 0): array
    {
        $saga = trim($saga);
        if ($saga === '' || !CatalogSchema::hasOeuvreSagaColumns()) {
            return [];
        }

        $params = [
            'saga' => $saga,
            'domain' => MediaDomain::FILM,
        ];
        $sql = 'SELECT o.id AS oeuvre_id, o.titre, o.annee, o.poster_url, o.saga_ordre
                FROM oeuvres o
                WHERE o.media_domain = :domain
                  AND o.saga = :saga';
        if ($excludeOeuvreId > 0) {
            $sql .= ' AND o.id != :exclude_id';
            $params['exclude_id'] = $excludeOeuvreId;
        }
        $sql .= ' ORDER BY
                    CASE WHEN o.saga_ordre > 0 THEN o.saga_ordre ELSE 999999 END ASC,
                    o.titre COLLATE FRENCH_NOCASE ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
                'titre' => (string) ($row['titre'] ?? ''),
                'annee' => (int) ($row['annee'] ?? 0),
                'poster_url' => $row['poster_url'] ?? null,
                'saga_ordre' => (int) ($row['saga_ordre'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $filmIds
     */
    public function assignFilmsToSaga(array $filmIds, string $saga, int $startOrder = 1): int
    {
        $saga = trim($saga);
        if ($saga === '' || $filmIds === []) {
            return 0;
        }

        $startOrder = max(1, $startOrder);
        $lookup = $this->db->prepare(
            'SELECT oeuvre_id FROM bibliotheque
             WHERE id = :id AND foyer_id = :foyer_id AND statut = :statut'
        );
        $updateCatalog = CatalogSchema::hasOeuvreSagaColumns()
            ? $this->db->prepare(
                'UPDATE oeuvres SET saga = :saga, saga_ordre = :saga_ordre, updated_at = datetime(\'now\')
                 WHERE id = :oeuvre_id'
            )
            : null;
        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET saga = :saga, saga_ordre = :saga_ordre
             WHERE id = :id AND foyer_id = :foyer_id AND statut = :statut'
        );

        $updated = 0;
        $ordre = $startOrder;
        foreach ($filmIds as $filmId) {
            $filmId = (int) $filmId;
            if ($filmId <= 0) {
                continue;
            }

            $oeuvreId = 0;
            if ($updateCatalog !== null) {
                $lookup->execute([
                    'id' => $filmId,
                    'foyer_id' => $this->foyerId(),
                    'statut' => LibraryStatut::COLLECTION,
                ]);
                $oeuvreId = (int) $lookup->fetchColumn();
            }

            $stmt->execute([
                'saga' => $saga,
                'saga_ordre' => $ordre,
                'id' => $filmId,
                'foyer_id' => $this->foyerId(),
                'statut' => LibraryStatut::COLLECTION,
            ]);
            if ($stmt->rowCount() > 0) {
                $updated++;
            }

            if ($updateCatalog !== null && $oeuvreId > 0) {
                $updateCatalog->execute([
                    'saga' => $saga,
                    'saga_ordre' => $ordre,
                    'oeuvre_id' => $oeuvreId,
                ]);
            }

            $ordre++;
        }

        return $updated;
    }

    /**
     * @return array{ok: true, updated: int}|array{ok: false, error: string}
     */
    public function renameSaga(string $oldName, string $newName): array
    {
        $oldName = trim($oldName);
        $newName = trim($newName);

        if ($oldName === '') {
            return ['ok' => false, 'error' => 'Saga introuvable.'];
        }
        if ($newName === '') {
            return ['ok' => false, 'error' => 'Le nouveau nom ne peut pas être vide.'];
        }
        if ($oldName === $newName) {
            return ['ok' => true, 'updated' => 0];
        }

        if (CatalogSchema::hasOeuvreSagaColumns()) {
            $countStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = ? AND b.statut = ? AND o.saga = ?'
            );
            $countStmt->execute([$this->foyerId(), LibraryStatut::COLLECTION, $oldName]);
            $filmCount = (int) $countStmt->fetchColumn();
            if ($filmCount === 0) {
                return ['ok' => false, 'error' => 'Aucun film n’utilise cette saga.'];
            }

            $catalogStmt = $this->db->prepare(
                'UPDATE oeuvres SET saga = :new_name, updated_at = datetime(\'now\')
                 WHERE saga = :old_name'
            );
            $catalogStmt->execute([
                'new_name' => $newName,
                'old_name' => $oldName,
            ]);

            $stmt = $this->db->prepare(
                'UPDATE bibliotheque SET saga = :new_name
                 WHERE foyer_id = :foyer_id AND statut = :statut AND saga = :old_name'
            );
            $stmt->execute([
                'new_name' => $newName,
                'foyer_id' => $this->foyerId(),
                'statut' => LibraryStatut::COLLECTION,
                'old_name' => $oldName,
            ]);

            return ['ok' => true, 'updated' => $stmt->rowCount()];
        }

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque
             WHERE foyer_id = ? AND statut = ? AND saga = ?'
        );
        $countStmt->execute([$this->foyerId(), LibraryStatut::COLLECTION, $oldName]);
        $filmCount = (int) $countStmt->fetchColumn();
        if ($filmCount === 0) {
            return ['ok' => false, 'error' => 'Aucun film n’utilise cette saga.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET saga = :new_name
             WHERE foyer_id = :foyer_id AND statut = :statut AND saga = :old_name'
        );
        $stmt->execute([
            'new_name' => $newName,
            'foyer_id' => $this->foyerId(),
            'statut' => LibraryStatut::COLLECTION,
            'old_name' => $oldName,
        ]);

        return ['ok' => true, 'updated' => $stmt->rowCount()];
    }

    /**
     * Détermine la saga/ordre à appliquer à l’exemplaire personnel à partir du formulaire,
     * ou en reprenant celle du catalogue partagé si le formulaire ne la précise pas.
     *
     * @param array<string, mixed> $oeuvre
     * @param array<string, mixed> $data
     * @return array{0: string, 1: int}
     */
    public function resolveLibrarySagaFromOeuvre(array $oeuvre, array $data): array
    {
        $saga = trim((string) ($data['saga'] ?? ''));
        $sagaOrdre = max(0, (int) ($data['saga_ordre'] ?? 0));
        if ($saga === '' && CatalogSchema::hasOeuvreSagaColumns()) {
            $catalogSaga = trim((string) ($oeuvre['saga'] ?? ''));
            if ($catalogSaga !== '') {
                $saga = $catalogSaga;
                $sagaOrdre = max(0, (int) ($oeuvre['saga_ordre'] ?? 0));
            }
        }
        if ($saga === '') {
            $sagaOrdre = 0;
        }

        return [$saga, $sagaOrdre];
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }
}
