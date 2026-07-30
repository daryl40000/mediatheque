<?php
/**
 * Liens livre catalogue ↔ jeux catalogue (guides, artbooks…).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LivreGameLink
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'livre_game_link' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return list<int> */
    public function listGameOeuvreIdsForBook(int $bookOeuvreId): array
    {
        if (!self::isAvailable() || $bookOeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT game_oeuvre_id FROM livre_game_link WHERE oeuvre_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$bookOeuvreId]);

        return ListOf::ints($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGamesForBook(int $bookOeuvreId): array
    {
        $ids = $this->listGameOeuvreIdsForBook($bookOeuvreId);
        if ($ids === [] || !GameRepository::isAvailable()) {
            return [];
        }

        $repo = new GameRepository();
        $games = [];
        foreach ($ids as $gameOeuvreId) {
            $row = $repo->findCatalogByOeuvreId($gameOeuvreId);
            if ($row !== null) {
                $games[] = $row;
            }
        }

        return $games;
    }

    public function countBooksForGame(int $gameOeuvreId): int
    {
        if (!self::isAvailable() || $gameOeuvreId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM livre_game_link lgl
             INNER JOIN oeuvres o ON o.id = lgl.oeuvre_id AND o.media_domain = ?
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE lgl.game_oeuvre_id = ?'
        );
        $stmt->execute([MediaDomain::LIVRE, $gameOeuvreId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Livres du catalogue qui parlent d’un jeu (pour la fiche jeu).
     *
     * @return list<array<string, mixed>>
     */
    public function listBooksForGame(int $gameOeuvreId): array
    {
        if (!self::isAvailable() || $gameOeuvreId <= 0 || !LivreRepository::isAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.synopsis, o.poster_url,
                    o.saga, o.saga_ordre,
                    ol.auteur, ol.isbn, ol.pages, ol.editeur, ol.categories, ol.langue, ol.collection_label,
                    ol.sous_titre, ol.back_cover_url
             FROM livre_game_link lgl
             INNER JOIN oeuvres o ON o.id = lgl.oeuvre_id AND o.media_domain = ?
             INNER JOIN oeuvre_livre ol ON ol.oeuvre_id = o.id
             WHERE lgl.game_oeuvre_id = ?
             ORDER BY o.titre COLLATE NOCASE ASC'
        );
        $stmt->execute([MediaDomain::LIVRE, $gameOeuvreId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
            $row['annee'] = (int) ($row['annee'] ?? 0);
            $row['pages'] = (int) ($row['pages'] ?? 0);
            $row['category_list'] = LivreCategory::parseList((string) ($row['categories'] ?? ''));
            $row['display_titre'] = (string) ($row['titre'] ?? '');
            $out[] = $row;
        }

        return ListOf::assocRows($out);
    }

    /**
     * Remplace les liens jeux d’un livre (IDs œuvres jeux uniquement).
     *
     * @param list<int> $gameOeuvreIds
     */
    public function replaceLinksForBook(int $bookOeuvreId, array $gameOeuvreIds): void
    {
        if (!self::isAvailable() || $bookOeuvreId <= 0) {
            return;
        }

        $this->db->prepare('DELETE FROM livre_game_link WHERE oeuvre_id = ?')->execute([$bookOeuvreId]);

        $insert = $this->db->prepare(
            'INSERT OR IGNORE INTO livre_game_link (oeuvre_id, game_oeuvre_id) VALUES (?, ?)'
        );
        foreach ($gameOeuvreIds as $gameOeuvreId) {
            $gameOeuvreId = (int) $gameOeuvreId;
            if ($gameOeuvreId <= 0 || $gameOeuvreId === $bookOeuvreId) {
                continue;
            }
            // Vérifie que la cible est bien un jeu catalogue.
            $check = $this->db->prepare(
                'SELECT 1 FROM oeuvres WHERE id = ? AND media_domain = ? LIMIT 1'
            );
            $check->execute([$gameOeuvreId, MediaDomain::JEU]);
            if (!$check->fetchColumn()) {
                continue;
            }
            $insert->execute([$bookOeuvreId, $gameOeuvreId]);
        }
    }
}
