<?php
/**
 * Historique des films déjà vus (personnel à chaque utilisateur).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;
use PDOException;

final class HistoriqueRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function markVu(int $filmId, ?int $note = null): void
    {
        $this->recordViewing($filmId, date('Y-m-d'), $note);
    }

    /** Enregistre ou met à jour la note personnelle sur un jeu (sans saisie de date). */
    public function setPersonalNote(int $libraryId, ?int $note): void
    {
        if ($note !== null) {
            $note = self::normalizeStoredNote($note);
            if ($note === null) {
                throw new \InvalidArgumentException('Ressenti invalide.');
            }
        }

        $userId = UserContext::currentUserId();
        if (!$this->libraryEntryExists($libraryId, $userId)) {
            throw new \RuntimeException('Cette fiche est introuvable dans votre bibliothèque.');
        }

        if ($note === null) {
            $stmt = $this->db->prepare(
                'DELETE FROM historique WHERE film_id = ? AND user_id = ?'
            );
            $stmt->execute([$libraryId, $userId]);

            return;
        }

        $today = date('Y-m-d');
        $check = $this->db->prepare(
            'SELECT id FROM historique WHERE film_id = ? AND user_id = ? ORDER BY date_vue DESC, id DESC LIMIT 1'
        );
        $check->execute([$libraryId, $userId]);
        $existingId = $check->fetchColumn();

        if ($existingId !== false) {
            $upd = $this->db->prepare(
                'UPDATE historique SET note = ?, date_vue = ? WHERE id = ? AND user_id = ?'
            );
            $upd->execute([$note, $today, $existingId, $userId]);

            return;
        }

        $this->recordViewing($libraryId, $today, $note);
    }

    public static function todayForInput(): string
    {
        return date('d/m/Y');
    }

    public static function todayForInputIso(): string
    {
        return date('Y-m-d');
    }

    public static function parseVueDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd/m/y', 'd-m-Y', 'd-m-y', 'd.m.Y', 'd.m.y'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($dt === false) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return $dt->format('Y-m-d');
        }

        return null;
    }

    /** @return array{ok: true, date: string}|array{ok: false, error: string} */
    public static function parseDateVueInput(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => true, 'date' => date('Y-m-d')];
        }

        $iso = self::parseVueDate($raw);
        if ($iso === null) {
            return [
                'ok' => false,
                'error' => 'Date invalide. Utilisez le calendrier ou le format jj/mm/aaaa (ex. 16/05/2024).',
            ];
        }

        if ($iso > date('Y-m-d')) {
            return [
                'ok' => false,
                'error' => 'La date de vision ne peut pas être dans le futur.',
            ];
        }

        return ['ok' => true, 'date' => $iso];
    }

    /** @return array{ok: true, note: ?int}|array{ok: false, error: string} */
    public static function parseNoteInput(string $raw): array
    {
        $parsed = RessentiNote::parseInput($raw);
        if (!$parsed['ok']) {
            return $parsed;
        }

        return ['ok' => true, 'note' => $parsed['score']];
    }

    /** @deprecated Utiliser parseNoteInput() */
    public static function parseRessentiInput(string $raw): array
    {
        return self::parseNoteInput($raw);
    }

    private static function ressentiWhereSql(string $alias = 'h'): string
    {
        return RessentiNote::sqlValidNote($alias);
    }

    private static function normalizeStoredNote(?int $note): ?int
    {
        if ($note === null) {
            return null;
        }

        $score = RessentiNote::normalizeScore($note);
        if ($score !== null) {
            return $score;
        }

        return RessentiNote::scoreFromLegacyTen($note);
    }

    public function recordViewing(int $filmId, string $dateVue, ?int $note = null): bool
    {
        $note = self::normalizeStoredNote($note);
        $userId = UserContext::currentUserId();
        if (!$this->libraryEntryExists($filmId, $userId)) {
            throw new \RuntimeException('Cette fiche est introuvable dans votre bibliothèque.');
        }

        $check = $this->db->prepare(
            'SELECT id FROM historique WHERE film_id = ? AND user_id = ? AND date_vue = ?'
        );
        $check->execute([$filmId, $userId, $dateVue]);
        $existingId = $check->fetchColumn();

        if ($existingId !== false) {
            if ($note !== null) {
                $upd = $this->db->prepare('UPDATE historique SET note = ? WHERE id = ?');
                $upd->execute([$note, $existingId]);
            }

            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO historique (film_id, user_id, date_vue, note) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$filmId, $userId, $dateVue, $note]);
        } catch (PDOException $e) {
            if ($this->isForeignKeyFailure($e)) {
                HistoriqueSchema::repairForeignKeyIfNeeded($this->db);
                $stmt = $this->db->prepare(
                    'INSERT INTO historique (film_id, user_id, date_vue, note) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$filmId, $userId, $dateVue, $note]);
            } else {
                throw $e;
            }
        }

        return true;
    }

    private function libraryEntryExists(int $filmId, int $userId): bool
    {
        if ($filmId <= 0) {
            return false;
        }

        if (CatalogSchema::usesCatalogTables($this->db)) {
            $foyerId = UserContext::currentFoyerId();
            $stmt = $this->db->prepare(
                'SELECT 1 FROM bibliotheque WHERE id = ?
                 AND (
                    (statut = ? AND foyer_id = ?)
                    OR (statut = ? AND user_id = ?)
                 )
                 LIMIT 1'
            );
            $stmt->execute([
                $filmId,
                LibraryStatut::COLLECTION,
                $foyerId,
                LibraryStatut::WISHLIST,
                $userId,
            ]);

            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare('SELECT 1 FROM films WHERE id = ? LIMIT 1');
        $stmt->execute([$filmId]);

        return (bool) $stmt->fetchColumn();
    }

    private function isForeignKeyFailure(PDOException $e): bool
    {
        return str_contains($e->getMessage(), 'FOREIGN KEY')
            || str_contains($e->getMessage(), 'foreign key');
    }

    public function wasEverSeen(int $filmId): bool
    {
        $userId = UserContext::currentUserId();
        $stmt = $this->db->prepare(
            'SELECT 1 FROM historique WHERE film_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$filmId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array{id: int, date_vue: string, note: ?int}> */
    public function findViewingsByFilm(int $filmId): array
    {
        $userId = UserContext::currentUserId();
        $stmt = $this->db->prepare(
            'SELECT id, date_vue, note FROM historique
             WHERE film_id = ? AND user_id = ?
             ORDER BY date_vue DESC, id DESC'
        );
        $stmt->execute([$filmId, $userId]);

        return $stmt->fetchAll();
    }

    public function deleteViewing(int $historiqueId, int $filmId): bool
    {
        if ($historiqueId <= 0 || $filmId <= 0) {
            return false;
        }

        $userId = UserContext::currentUserId();
        $stmt = $this->db->prepare(
            'DELETE FROM historique WHERE id = ? AND film_id = ? AND user_id = ?'
        );
        $stmt->execute([$historiqueId, $filmId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function getLastViewing(int $filmId): ?array
    {
        $userId = UserContext::currentUserId();
        $stmt = $this->db->prepare(
            'SELECT date_vue, note FROM historique
             WHERE film_id = ? AND user_id = ?
             ORDER BY date_vue DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$filmId, $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getBestRessentiScore(int $filmId): ?int
    {
        $userId = UserContext::currentUserId();
        $stmt = $this->db->prepare(
            'SELECT MAX(h.note) FROM historique h
             WHERE h.film_id = ? AND h.user_id = ? AND ' . self::ressentiWhereSql('h')
        );
        $stmt->execute([$filmId, $userId]);
        $note = $stmt->fetchColumn();
        if ($note === false || $note === null) {
            return null;
        }

        return RessentiNote::normalizeScore((int) $note);
    }

    /** @deprecated Utiliser getBestRessentiScore() */
    public function getNoteSur10(int $filmId): ?int
    {
        return $this->getBestRessentiScore($filmId);
    }

    public static function formatRessentiScore(?int $score): string
    {
        return RessentiNote::labelFromScore(RessentiNote::normalizeScore($score));
    }

    /** @deprecated Utiliser formatRessentiScore() */
    public static function formatNoteSur10(?int $note): string
    {
        return self::formatRessentiScore($note);
    }

    /** @deprecated Notes foyer supprimées */
    public function getFoyerAverageNote(int $filmId): ?float
    {
        return null;
    }

    /** @deprecated Notes foyer supprimées */
    public static function formatAverageNote(?float $note): string
    {
        return '';
    }

    public static function formatDateVue(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return '';
        }
        $date = trim($date);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $date, $m)) {
            return sprintf('%02d-%02d-%04d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})/', $date, $m)) {
            return sprintf('%02d-%02d-%04d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return $date;
    }

    /** @return list<array<string, mixed>> */
    public function findAllWithFilmTitles(): array
    {
        $userId = UserContext::currentUserId();
        if (CatalogSchema::usesCatalogTables($this->db)) {
            $stmt = $this->db->prepare(
                'SELECT h.id, h.film_id, b.oeuvre_id, o.titre, o.realisateur, h.date_vue, h.note
                 FROM historique h
                 INNER JOIN bibliotheque b ON b.id = h.film_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE h.user_id = ?
                 ORDER BY h.date_vue DESC, o.titre COLLATE FRENCH_NOCASE'
            );
            $stmt->execute([$userId]);

            return $stmt->fetchAll();
        }

        $stmt = $this->db->query(
            'SELECT h.id, h.film_id, f.titre, f.realisateur, h.date_vue, h.note
             FROM historique h
             INNER JOIN films f ON f.id = h.film_id
             ORDER BY h.date_vue DESC, f.titre COLLATE FRENCH_NOCASE'
        );

        return $stmt->fetchAll();
    }

    public function daysSinceLastView(int $filmId): ?int
    {
        $userId = UserContext::currentUserId();
        $stmt = $this->db->prepare(
            'SELECT CAST(julianday("now") - julianday(MAX(date_vue)) AS INTEGER)
             FROM historique WHERE film_id = ? AND user_id = ?'
        );
        $stmt->execute([$filmId, $userId]);
        $days = $stmt->fetchColumn();

        return $days !== false ? (int) $days : null;
    }
}
