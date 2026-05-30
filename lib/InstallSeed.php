<?php
/**
 * Import automatique catalogue + affiches à l’installation neuve uniquement.
 *
 * Déposez dans install_seed/ (paquet ou data/install_seed/) :
 *   - un CSV catalogue (export admin)
 *   - une archive ZIP des affiches (posters/1.jpg, …)
 */

declare(strict_types=1);

namespace Moncine;

final class InstallSeed
{
    /** @var list<string> */
    private const CATALOG_CANDIDATES = [
        'catalogue.csv',
        'moncine-catalogue.csv',
    ];

    /** @var list<string> */
    private const POSTERS_CANDIDATES = [
        'affiches.zip',
        'posters.zip',
        'moncine-affiches.zip',
    ];

    public function __construct(
        private readonly SchemaMigrator $migrator,
        private readonly ImportPostersZip $postersZip = new ImportPostersZip()
    ) {
    }

    /**
     * @return array{status: string, message: string, catalog_imported?: int, posters_imported?: int, errors: list<string>}
     */
    public function applyIfEligible(): array
    {
        $check = $this->eligibility();
        if ($check !== null) {
            return [
                'status' => 'skipped',
                'message' => $check,
                'errors' => [],
            ];
        }

        $dirs = $this->seedDirectories();
        $catalogPath = $this->findCatalogCsv($dirs);
        $zipPath = $this->findPostersZip($dirs);

        if ($catalogPath === null && $zipPath === null) {
            return [
                'status' => 'skipped',
                'message' => 'Aucun fichier dans install_seed/ (CSV catalogue ou ZIP affiches).',
                'errors' => [],
            ];
        }

        $errors = [];
        $catalogImported = 0;

        if ($catalogPath !== null) {
            $catalogResult = (new ImportCsv())->importFromPath($catalogPath, MONCINE_CSV_DELIMITER, true, true);
            $catalogImported = (int) ($catalogResult['imported'] ?? 0);
            $errors = array_merge($errors, $catalogResult['errors'] ?? []);
            if ($catalogImported === 0 && ($catalogResult['errors'] ?? []) === []) {
                $errors[] = 'Import catalogue : aucune ligne traitée (' . basename($catalogPath) . ').';
            }
        }

        $postersImported = 0;
        if ($zipPath !== null) {
            $posterResult = $this->postersZip->importFromPath($zipPath);
            $postersImported = (int) ($posterResult['imported'] ?? 0);
            $errors = array_merge($errors, $posterResult['errors'] ?? []);
        }

        if ($catalogPath !== null && $catalogImported === 0) {
            return [
                'status' => 'error',
                'message' => 'Échec de l’import catalogue depuis install_seed/.',
                'catalog_imported' => 0,
                'posters_imported' => $postersImported,
                'errors' => $errors,
            ];
        }

        $this->migrator->setMetadata(
            SchemaMigrator::META_INSTALL_SEED_APPLIED,
            gmdate('c') . '|catalog=' . $catalogImported . '|posters=' . $postersImported
        );

        return [
            'status' => 'applied',
            'message' => sprintf(
                'Graine d’installation appliquée : %d œuvre(s) catalogue, %d affiche(s).',
                $catalogImported,
                $postersImported
            ),
            'catalog_imported' => $catalogImported,
            'posters_imported' => $postersImported,
            'errors' => $errors,
        ];
    }

    private function eligibility(): ?string
    {
        if ($this->migrator->getMetadata(SchemaMigrator::META_INSTALL_SEED_APPLIED) !== '') {
            return 'Graine déjà appliquée sur cette instance (install_seed_applied).';
        }

        if (!CatalogSchema::usesCatalogTables(Database::getInstance())) {
            return 'Schéma catalogue absent.';
        }

        if ((new ExportCatalog())->catalogEntryCount() > 0) {
            return 'Catalogue non vide : import automatique ignoré pour protéger les données existantes.';
        }

        return null;
    }

    /**
     * @return list<string> Dossiers existants à parcourir (data prioritaire).
     */
    private function seedDirectories(): array
    {
        $dirs = [];
        foreach ([MONCINE_INSTALL_SEED_DATA_DIR, MONCINE_INSTALL_SEED_PACKAGE_DIR] as $dir) {
            if (is_dir($dir)) {
                $dirs[] = $dir;
            }
        }

        return $dirs;
    }

    /**
     * @param list<string> $dirs
     */
    private function findCatalogCsv(array $dirs): ?string
    {
        foreach ($dirs as $dir) {
            foreach (self::CATALOG_CANDIDATES as $name) {
                $path = $dir . '/' . $name;
                if (is_file($path) && is_readable($path)) {
                    return $path;
                }
            }

            foreach (glob($dir . '/*catalogue*.csv') ?: [] as $path) {
                if (is_file($path) && is_readable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $dirs
     */
    private function findPostersZip(array $dirs): ?string
    {
        foreach ($dirs as $dir) {
            foreach (self::POSTERS_CANDIDATES as $name) {
                $path = $dir . '/' . $name;
                if (is_file($path) && is_readable($path)) {
                    return $path;
                }
            }

            foreach (glob($dir . '/*.zip') ?: [] as $path) {
                if (is_file($path) && is_readable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
