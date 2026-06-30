<?php
/**
 * Règles de prêt : films et jeux physiques uniquement (pas magazines ni démat seul).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LoanEligibility
{
    public static function hasNonPretableColumn(): bool
    {
        return GameSchema::hasColumn('bibliotheque', 'non_pretable');
    }

    /**
     * Domaines autorisés pour une demande de prêt.
     */
    public static function isLoanableMediaDomain(string $mediaDomain): bool
    {
        $mediaDomain = MediaDomain::normalize($mediaDomain);

        return MediaDomain::isFilm($mediaDomain) || MediaDomain::isGame($mediaDomain) || MediaDomain::isBd($mediaDomain);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isRowLoanable(array $row): bool
    {
        $mediaDomain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));
        if (!self::isLoanableMediaDomain($mediaDomain)) {
            return false;
        }

        if (self::hasNonPretableColumn() && !empty($row['non_pretable'])) {
            return false;
        }

        if (MediaDomain::isGame($mediaDomain)) {
            return self::isPhysicalGameRow($row);
        }

        if (MediaDomain::isBd($mediaDomain)) {
            return BdPhysicalSupport::isValid((string) ($row['support_physique'] ?? ''));
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @return true|string true si prêtable, sinon message d'erreur
     */
    public static function validateLoanRequest(array $row): bool|string
    {
        $mediaDomain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));
        if (!self::isLoanableMediaDomain($mediaDomain)) {
            return 'Ce type de média ne peut pas être prêté.';
        }

        if (!self::isRowLoanable($row)) {
            if (MediaDomain::isGame($mediaDomain)) {
                if (self::hasNonPretableColumn() && !empty($row['non_pretable'])) {
                    return 'Le propriétaire ne prête pas cet exemplaire.';
                }

                return 'Ce jeu n’est pas prêtable (exemplaire dématérialisé uniquement).';
            }
            if (MediaDomain::isBd($mediaDomain)) {
                return 'Cet album n’est pas prêtable (support physique non renseigné).';
            }

            return 'Cet exemplaire n’est pas prêtable.';
        }

        return true;
    }

    /**
     * Libellé court pour les messages (« film », « jeu »).
     */
    public static function mediaItemLabel(string $mediaDomain): string
    {
        return match (true) {
            MediaDomain::isGame($mediaDomain) => 'jeu',
            MediaDomain::isBd($mediaDomain) => 'album',
            default => 'film',
        };
    }

    /**
     * Sous-titre optionnel dans les listes de prêts (plateforme jeu).
     *
     * @param array<string, mixed> $row
     */
    public static function listSubtitle(array $row): string
    {
        $mediaDomain = MediaDomain::normalize((string) ($row['media_domain'] ?? ''));
        if (MediaDomain::isGame($mediaDomain)) {
            $platform = GamePlatform::shortLabel((string) ($row['platform'] ?? ''));

            return $platform !== '' ? $platform : 'Jeu';
        }
        if (MediaDomain::isBd($mediaDomain)) {
            $support = BdPhysicalSupport::label((string) ($row['support_physique'] ?? ''));

            return $support !== '' ? $support : 'BD';
        }

        return '';
    }

    /**
     * Case « Ne pas prêter » : jeux avec support physique (ou films).
     *
     * @param array<string, mixed> $row
     */
    public static function canToggleNonPretable(array $row): bool
    {
        if (!self::hasNonPretableColumn()) {
            return false;
        }

        $mediaDomain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::JEU));
        if (MediaDomain::isFilm($mediaDomain)) {
            return true;
        }

        if (MediaDomain::isBd($mediaDomain)) {
            return BdPhysicalSupport::isValid((string) ($row['support_physique'] ?? ''));
        }

        if (!MediaDomain::isGame($mediaDomain)) {
            return false;
        }

        if (!empty($row['non_pretable'])) {
            return true;
        }

        return self::isPhysicalGameRow($row);
    }

    /**
     * Charge une entrée bibliothèque avec les métadonnées nécessaires au prêt.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchBibliothequeRow(PDO $db, int $bibliothequeId): ?array
    {
        if ($bibliothequeId <= 0) {
            return null;
        }

        $joinGame = GameSchema::tableExists()
            ? ' LEFT JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            : '';
        $extra = LoanCatalog::selectLoanMeta();

        $stmt = $db->prepare(
            'SELECT b.id, b.user_id, b.statut, ' . $extra . '
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . $joinGame . '
             WHERE b.id = ? LIMIT 1'
        );
        $stmt->execute([$bibliothequeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function isPhysicalGameRow(array $row): bool
    {
        if (!GameSchema::hasEditionColumns()) {
            return true;
        }

        return GamePhysicalSupport::parseList((string) ($row['physical_supports'] ?? '')) !== [];
    }
}
