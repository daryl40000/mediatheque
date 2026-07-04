<?php
/**
 * Création de fiches catalogue BD (avec entrée bibliothèque).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdCatalogCreator
{
    public function __construct(
        private readonly PDO $db,
        private readonly BdCatalogWriter $catalogWriter,
        private readonly BdLibraryAttach $libraryAttach
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createWithLibrary(
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!BdRepository::isAvailable()) {
            return 'Module BD non disponible.';
        }

        $resolved = self::resolveTitleAndSeries($data);
        if (is_string($resolved)) {
            return $resolved;
        }
        [$titre, $seriesId] = $resolved;

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        $kind = trim((string) ($data['kind'] ?? '')) !== ''
            ? BdKind::normalize((string) $data['kind'])
            : BdSeriesMetadata::kindFromSeries($series);
        $support = BdPhysicalSupport::normalize((string) ($data['support_physique'] ?? ''));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::BD,
            ]);

            $this->catalogWriter->insertCatalogBdRow($oeuvreId, $data, $seriesId, $kind);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);

            $register = $this->libraryAttach->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
            if ($register !== true) {
                throw new \RuntimeException((string) $register);
            }

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement de l’album.';
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string, 1: int}|string
     */
    public static function resolveTitleAndSeries(array $data): array|string
    {
        $titre = trim((string) ($data['titre'] ?? ''));
        $seriesId = max(0, (int) ($data['series_id'] ?? 0));
        $tomeNum = max(0, (int) ($data['tome_numero'] ?? 0));
        $tomeLabel = trim((string) ($data['tome_label'] ?? ''));

        if ($seriesId <= 0) {
            return 'Choisissez d’abord une série, ou créez-en une nouvelle.';
        }

        if ($tomeNum < 0) {
            return 'Le numéro de tome ne peut pas être négatif.';
        }

        if ($titre === '') {
            $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
            $seriesTitre = trim((string) ($series['titre'] ?? ''));
            if ($seriesTitre === '') {
                return 'Série introuvable.';
            }
            $titre = BdRowMapper::seriesDisplayTitle([
                'series_titre' => $seriesTitre,
                'tome_numero' => $tomeNum,
                'tome_label' => $tomeLabel,
            ]);
        }

        return [$titre, $seriesId];
    }
}
