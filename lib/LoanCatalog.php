<?php
/**
 * Requêtes SQL communes pour les listes de prêts (films + jeux).
 */

declare(strict_types=1);

namespace Moncine;

final class LoanCatalog
{
    public static function joinExtras(): string
    {
        if (!GameSchema::tableExists()) {
            return '';
        }

        return ' LEFT JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id';
    }

    public static function selectLoanMeta(): string
    {
        $parts = [];
        if (CatalogSchema::hasMediaDomainColumn()) {
            $parts[] = 'o.media_domain';
        } else {
            $parts[] = '\'' . MediaDomain::FILM . '\' AS media_domain';
        }

        if (GameSchema::tableExists()) {
            $parts[] = 'oj.platform';
            if (GameSchema::hasEditionColumns()) {
                $parts[] = 'oj.physical_supports';
                $parts[] = 'oj.digital_stores';
                $parts[] = 'oj.is_digital';
            }
        }

        if (LoanEligibility::hasNonPretableColumn()) {
            $parts[] = 'b.non_pretable';
        }

        return implode(', ', $parts);
    }

    public static function selectLoanRow(): string
    {
        return CatalogSchema::selectFilmRow() . ', ' . self::selectLoanMeta();
    }

    /** Colonnes titre + domaine pour les notifications de prêt. */
    public static function notificationSelect(): string
    {
        $domain = CatalogSchema::hasMediaDomainColumn()
            ? 'o.media_domain'
            : '\'' . MediaDomain::FILM . '\' AS media_domain';

        return 'o.titre, ' . $domain;
    }
}
