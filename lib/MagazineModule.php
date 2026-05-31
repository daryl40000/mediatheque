<?php
/**
 * Initialisation automatique du module magazines (schéma + données de base).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineModule
{
    private const REPAIR_META = 'magazine_series_library_repair_v1';

    /** Appelé à chaque requête web/CLI après Database::getInstance() (migrations déjà lancées). */
    public static function bootstrap(): void
    {
        if (!SeriesRepository::tableExists()) {
            return;
        }

        $db = Database::getInstance();

        // Sécurité : si une migration magazines est en attente, la relancer ici.
        if (!MagazineRepository::seriesLibraryTableExists()) {
            try {
                (new SchemaMigrator($db))->runPendingMigrations();
            } catch (\Throwable $e) {
                error_log('MagazineModule::bootstrap migration: ' . $e->getMessage());

                return;
            }
        }

        if (!MagazineRepository::isAvailable()) {
            return;
        }

        self::repairOrphanSeriesOnce();
    }

    /**
     * Sur une installation solo, rattache les séries magazines sans entrée bibliothèque
     * (séries créées avant la table series_bibliotheque).
     */
    private static function repairOrphanSeriesOnce(): void
    {
        $migrator = new SchemaMigrator(Database::getInstance());
        if ($migrator->getMetadata(self::REPAIR_META) !== '') {
            return;
        }

        $userRepo = new UtilisateurRepository();
        if ($userRepo->countWithPassword() !== 1) {
            $migrator->setMetadata(self::REPAIR_META, 'skipped_multi_user');

            return;
        }

        $stmt = Database::getInstance()->query(
            "SELECT id, foyer_id FROM utilisateurs
             WHERE password_hash IS NOT NULL AND TRIM(password_hash) != ''
             LIMIT 1"
        );
        $user = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($user === false) {
            $migrator->setMetadata(self::REPAIR_META, 'skipped_no_user');

            return;
        }

        $userId = (int) ($user['id'] ?? 0);
        $foyerId = (int) ($user['foyer_id'] ?? 0);
        if ($foyerId <= 0) {
            $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);
        }
        if ($userId <= 0 || $foyerId <= 0) {
            $migrator->setMetadata(self::REPAIR_META, 'skipped_no_foyer');

            return;
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT id FROM series
             WHERE media_domain = ?
               AND id NOT IN (SELECT series_id FROM series_bibliotheque)'
        );
        $stmt->execute([MediaDomain::MAGAZINE]);
        $orphanIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $magRepo = new MagazineRepository();
        foreach ($orphanIds as $seriesId) {
            $magRepo->registerSeriesInLibrary(
                (int) $seriesId,
                LibraryStatut::COLLECTION,
                $userId,
                $foyerId
            );
        }

        $migrator->setMetadata(self::REPAIR_META, 'done');
    }
}
