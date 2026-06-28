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
                return 'Cette catégorie de sujet ne peut pas être reliée à un jeu.';
            }

            $valid = self::validateCatalogOeuvreId($oeuvreId);
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
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ms.id AS subject_id, ms.category, ms.label, ms.detail, ms.parution_year,
                    oms.oeuvre_id AS issue_oeuvre_id,
                    om.numero, om.date_parution,
                    s.titre AS series_titre,
                    b.id AS bib_id
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
             INNER JOIN series s ON s.id = om.series_id
             INNER JOIN bibliotheque b ON b.oeuvre_id = oms.oeuvre_id
             WHERE ms.catalog_oeuvre_id = :oeuvre_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             ORDER BY om.date_parution DESC, s.titre COLLATE FRENCH_NOCASE ASC, om.numero_ordre DESC'
        );
        $stmt->execute([
            'oeuvre_id' => $oeuvreId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['subject_id'] = (int) ($row['subject_id'] ?? 0);
            $row['bib_id'] = (int) ($row['bib_id'] ?? 0);
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
        $oeuvreId = (int) ($subject['catalog_oeuvre_id'] ?? 0);
        $subject['catalog_game'] = null;
        $subject['catalog_game_bib_id'] = 0;
        $subject['catalog_game_url'] = '';

        if ($oeuvreId <= 0 || !GameRepository::isAvailable()) {
            return $subject;
        }

        $game = (new GameRepository())->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return $subject;
        }

        $subject['catalog_game'] = $game;
        $bibId = (new GameRepository())->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
        if ($bibId !== null && $bibId > 0) {
            $subject['catalog_game_bib_id'] = $bibId;
            $subject['catalog_game_url'] = View::gameUrl($bibId);
        }

        return $subject;
    }
}
