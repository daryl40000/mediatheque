<?php
declare(strict_types=1);
namespace Moncine;
use PDO;
final class MagazineCatalogCreator { public function __construct(private readonly PDO $db, private readonly MagazineCatalogWriter $catalogWriter, private readonly MagazineCatalogValidator $validator, private readonly MagazineLibraryQuery $libraryQuery, private readonly MagazineLibraryAttach $libraryAttach, private readonly MagazineLibraryMutations $libraryMutations) {}
    public function createCatalogIssue(int $seriesId, array $data): int|string
    {
        if (!MagazineRepository::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $numero = trim((string) ($data['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = !empty($data['est_hors_serie']);
        $numeroError = $this->validator->validateNumeroForSeries($seriesId, $numero, $horsSerie);
        if ($numeroError !== null) {
            return $numeroError;
        }

        $numeroOrdre = (float) ($data['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero)
                ? (float) $numero
                : $this->libraryQuery->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        if ($horsSerie && $numeroOrdre === (float) (int) $numeroOrdre) {
            $numeroOrdre += 0.5;
        }

        $seriesTitre = trim((string) ($data['series_titre'] ?? $series['titre'] ?? ''));
        $titre = MagazineRepository::buildCatalogIssueTitle($seriesTitre, $numero, $horsSerie);
        $dateParution = trim((string) ($data['date_parution'] ?? ''));
        $annee = max(0, (int) ($data['annee'] ?? 0));
        $posterUrl = SecureUrl::sanitizePosterUrl((string) ($data['poster_url'] ?? ''));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'annee' => $annee,
                'synopsis' => '',
                'poster_url' => $posterUrl,
                'media_domain' => MediaDomain::MAGAZINE,
            ]);

            $this->db->prepare(
                'INSERT INTO oeuvre_magazine (
                    oeuvre_id, series_id, numero, numero_ordre, date_parution,
                    sommaire, pages, est_hors_serie
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $seriesId,
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                trim((string) ($data['sommaire'] ?? '')),
                max(0, (int) ($data['pages'] ?? 0)),
                $horsSerie ? 1 : 0,
            ]);

            $this->db->commit();
            MagazineIssueFts::upsert($oeuvreId);

            return $oeuvreId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineCatalogCreator::createCatalogIssue: ' . $e->getMessage());

            return 'Impossible d’enregistrer le numéro catalogue.';
        }
    }
    public function createIssueWithLibrary(
        int $seriesId,
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!MagazineRepository::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $numero = trim((string) ($data['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = !empty($data['est_hors_serie']);
        $numeroError = $this->validator->validateNumeroForSeries($seriesId, $numero, $horsSerie);
        if ($numeroError !== null) {
            return $numeroError;
        }

        $numeroOrdre = (float) ($data['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero) ? (float) $numero : $this->libraryQuery->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        if ($horsSerie && $numeroOrdre === (float) (int) $numeroOrdre) {
            $numeroOrdre += 0.5;
        }

        $titre = MagazineRepository::buildCatalogIssueTitle(trim((string) ($series['titre'] ?? '')), $numero, $horsSerie);
        $dateParution = trim((string) ($data['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? 0));
        $hasPaper = !empty($data['support_papier']);
        $hasPdf = isset($data['stored_object_id']) && (int) $data['stored_object_id'] > 0;
        $support = MagazineSupport::formatTagsForStorage($hasPaper, $hasPdf);

        $statut = LibraryStatut::normalize($statut);

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'synopsis' => '',
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::MAGAZINE,
            ]);

            $this->db->prepare(
                'INSERT INTO oeuvre_magazine (
                    oeuvre_id, series_id, numero, numero_ordre, date_parution,
                    sommaire, pages, est_hors_serie, stored_object_id
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $seriesId,
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                isset($data['stored_object_id']) ? (int) $data['stored_object_id'] : null,
            ]);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);

            $seriesResult = $this->libraryAttach->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
            if ($seriesResult !== true) {
                throw new \RuntimeException((string) $seriesResult);
            }

            $this->db->commit();

            MagazineIssueFts::upsert($oeuvreId);

            if (MagazineSupport::isPossessed([
                'support_physique' => $support,
                'stored_object_id' => (int) ($data['stored_object_id'] ?? 0),
            ])) {
                $this->libraryMutations->clearWishlistEntriesWhenPossessed($oeuvreId);
            }

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineCatalogCreator::createIssueWithLibrary: ' . $e->getMessage());

            return 'Impossible d’enregistrer le numéro.';
        }
    }
}
