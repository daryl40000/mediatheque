<?php
/**
 * Bibliothèque : collection partagée (foyer) ou envies personnelles (utilisateur).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BibliothequeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $userId, int $foyerId): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $params = [
            'bib_id' => $id,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ];
        $domainSql = CatalogSchema::sqlMediaDomainAnd('o', $params);
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE b.id = :bib_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )' . $domainSql
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->normalizeAndRepairFilmRowEan($row);
    }

    public function findByOeuvreId(int $oeuvreId, int $userId, int $foyerId, ?string $statut = null): ?array
    {
        if ($statut === LibraryStatut::WISHLIST) {
            $stmt = $this->db->prepare(
                'SELECT * FROM bibliotheque
                 WHERE oeuvre_id = ? AND user_id = ? AND statut = ?
                 LIMIT 1'
            );
            $stmt->execute([$oeuvreId, $userId, LibraryStatut::WISHLIST]);
            $row = $stmt->fetch();

            return $row ?: null;
        }

        if ($statut === LibraryStatut::COLLECTION) {
            $stmt = $this->db->prepare(
                'SELECT * FROM bibliotheque
                 WHERE oeuvre_id = ? AND foyer_id = ? AND statut = ?
                 LIMIT 1'
            );
            $stmt->execute([$oeuvreId, $foyerId, LibraryStatut::COLLECTION]);
            $row = $stmt->fetch();

            return $row ?: null;
        }

        $collection = $this->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::COLLECTION);
        if ($collection !== null) {
            return $collection;
        }

        return $this->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::WISHLIST);
    }

    /**
     * @param array<string, mixed> $libraryData support_physique, format_*, saga, saga_ordre, statut
     */
    public function insert(int $userId, int $foyerId, int $oeuvreId, array $libraryData): int
    {
        $statut = LibraryStatut::normalize((string) ($libraryData['statut'] ?? LibraryStatut::COLLECTION));
        $rowFoyerId = $statut === LibraryStatut::COLLECTION ? max(0, $foyerId) : null;
        if ($statut === LibraryStatut::COLLECTION && $rowFoyerId <= 0) {
            throw new \RuntimeException('Aucun foyer associé à votre compte.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO bibliotheque (
                user_id, foyer_id, oeuvre_id, statut, support_physique, format_image, format_son,
                saga, saga_ordre, saison_numero, saison_label, ean
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $rowFoyerId,
            $oeuvreId,
            $statut,
            SupportPhysique::normalize((string) ($libraryData['support_physique'] ?? '')),
            trim((string) ($libraryData['format_image'] ?? '')),
            trim((string) ($libraryData['format_son'] ?? '')),
            trim((string) ($libraryData['saga'] ?? '')),
            trim((string) ($libraryData['saga'] ?? '')) === ''
                ? 0
                : max(0, (int) ($libraryData['saga_ordre'] ?? 0)),
            max(0, (int) ($libraryData['saison_numero'] ?? 0)),
            trim((string) ($libraryData['saison_label'] ?? '')),
            OeuvreEanRepository::normalizeEan((string) ($libraryData['ean'] ?? '')),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $libraryData
     */
    public function update(int $id, array $libraryData): void
    {
        if ($id <= 0) {
            return;
        }
        $sets = [];
        $params = ['id' => $id];
        foreach ([
            'support_physique',
            'format_image',
            'format_son',
            'saga',
            'saga_ordre',
            'statut',
            'saison_numero',
            'saison_label',
            'ean',
            'foyer_id',
        ] as $field) {
            if (array_key_exists($field, $libraryData)) {
                $sets[] = $field . ' = :' . $field;
                $value = $libraryData[$field];
                if ($field === 'ean') {
                    $value = OeuvreEanRepository::normalizeEan((string) $value);
                }
                $params[$field] = $value;
            }
        }
        if ($sets === []) {
            return;
        }
        $stmt = $this->db->prepare(
            'UPDATE bibliotheque SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
    }

    public function promoteToCollection(
        int $id,
        int $userId,
        int $foyerId,
        string $supportKey = '',
        string $ean = '',
        ?int $wishlistTargetId = null
    ): bool {
        $item = $this->findById($id, $userId, $foyerId);
        if ($item === null || ($item['statut'] ?? '') === LibraryStatut::COLLECTION) {
            return false;
        }
        if ($foyerId <= 0) {
            return false;
        }

        if ($wishlistTargetId !== null && $wishlistTargetId > 0 && WishlistTargetRepository::tableExists()) {
            $target = (new WishlistTargetRepository())->findByIdForBibliotheque($wishlistTargetId, $id);
            if ($target === null) {
                return false;
            }
            $supportKey = (string) ($target['support_physique'] ?? '');
            $ean = (string) ($target['ean'] ?? '');
        }

        $oeuvreId = (int) ($item['oeuvre_id'] ?? 0);
        $existingCollection = $this->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::COLLECTION);
        if ($existingCollection !== null) {
            $this->deleteById($id, $userId, $foyerId);

            return true;
        }

        $data = [
            'statut' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
        ];
        if ($supportKey !== '') {
            $data['support_physique'] = SupportPhysique::normalize($supportKey);
        }
        $ean = OeuvreEanRepository::normalizeEan($ean);
        if ($ean !== '') {
            $data['ean'] = $ean;
        }
        $this->update($id, $data);
        if (WishlistTargetRepository::tableExists()) {
            (new WishlistTargetRepository())->deleteAllForBibliotheque($id);
        }

        return true;
    }

    public function deleteById(int $id, int $userId, int $foyerId): bool
    {
        $item = $this->findById($id, $userId, $foyerId);
        if ($item === null) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Corrige un EAN mal stocké (espaces, tirets) à la lecture de la fiche.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeAndRepairFilmRowEan(array $row): array
    {
        $libraryId = (int) ($row['id'] ?? 0);
        $raw = (string) ($row['ean'] ?? '');
        $clean = OeuvreEanRepository::normalizeEan($raw);
        if ($clean !== $raw && $libraryId > 0) {
            $this->update($libraryId, ['ean' => $clean]);
        }
        $row['ean'] = $clean;

        return CatalogSchema::normalizeFilmRow($row);
    }

    public function countByStatut(int $userId, int $foyerId, string $statut): int
    {
        $domainJoin = CatalogSchema::hasMediaDomainColumn()
            ? ' INNER JOIN oeuvres o ON o.id = bibliotheque.oeuvre_id'
            : '';
        $domainSql = CatalogSchema::hasMediaDomainColumn()
            ? ' AND o.media_domain = ?'
            : '';

        if ($statut === LibraryStatut::WISHLIST) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM bibliotheque' . $domainJoin . '
                 WHERE user_id = ? AND statut = ?' . $domainSql
            );
            $params = [$userId, $statut];
            if ($domainSql !== '') {
                $params[] = MediaContext::current();
            }
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque' . $domainJoin . '
             WHERE foyer_id = ? AND statut = ?' . $domainSql
        );
        $params = [$foyerId, $statut];
        if ($domainSql !== '') {
            $params[] = MediaContext::current();
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
