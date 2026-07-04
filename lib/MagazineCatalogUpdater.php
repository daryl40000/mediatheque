<?php
declare(strict_types=1);
namespace Moncine;
use PDO;
final class MagazineCatalogUpdater { public function __construct(private readonly PDO $db, private readonly MagazineCatalogValidator $validator, private readonly MagazineLibraryQuery $libraryQuery, private readonly MagazineLibraryMutations $libraryMutations) {}
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data): bool|string
    {
        $issue = $this->libraryQuery->findCatalogIssueByOeuvreId($oeuvreId);
        if ($issue === null) {
            return 'Numéro introuvable dans le catalogue.';
        }

        $seriesId = (int) ($issue['series_id'] ?? 0);
        $numero = trim((string) ($data['numero'] ?? $issue['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = $this->validator->horsSerieFromData($data, $issue);
        $wasHorsSerie = !empty($issue['est_hors_serie']);

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $seriesTitre = trim((string) ($series['titre'] ?? $issue['series_titre'] ?? ''));
        $titre = MagazineRepository::buildCatalogIssueTitle($seriesTitre, $numero, $horsSerie);

        $numeroOrdre = (float) ($data['numero_ordre'] ?? $issue['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero)
                ? (float) $numero
                : $this->libraryQuery->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        $numeroError = $this->validator->validateNumeroForSeries($seriesId, $numero, $horsSerie, $oeuvreId);
        if ($numeroError !== null) {
            if ($wasHorsSerie && !$horsSerie) {
                return 'Impossible de retirer le hors-série : un numéro classique « '
                    . $numero . ' » existe déjà pour cette revue. '
                    . 'Fusionnez les doublons depuis Maintenance catalogue → Doublons magazines.';
            }

            return $numeroError;
        }

        $numeroOrdre = MagazineNumeroOrdre::adjustForHorsSerie($numeroOrdre, $horsSerie);

        $dateParution = trim((string) ($data['date_parution'] ?? $issue['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? $issue['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? $issue['pages'] ?? 0));
        $posterUrl = SecureUrl::sanitizePosterUrl(trim((string) ($data['poster_url'] ?? $issue['poster_url'] ?? '')));

        $titleError = $this->validator->validateCatalogIssueTitleUnique($titre, $oeuvreId);
        if ($titleError !== null) {
            return $titleError;
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'poster_url' => $posterUrl,
            ], ['titre', 'poster_url']);

            $this->db->prepare(
                'UPDATE oeuvre_magazine SET
                    numero = ?, numero_ordre = ?, date_parution = ?, sommaire = ?,
                    pages = ?, est_hors_serie = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                $oeuvreId,
            ]);

            $this->db->commit();
            MagazineIssueFts::upsert($oeuvreId);

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Impossible de mettre à jour le numéro.';
        }
    }
    public function updateIssue(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        $issue = $this->libraryQuery->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $numero = trim((string) ($data['numero'] ?? $issue['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = $this->validator->horsSerieFromData($data, $issue);
        $wasHorsSerie = !empty($issue['est_hors_serie']);

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $seriesTitre = trim((string) ($series['titre'] ?? $issue['series_titre'] ?? ''));
        $titre = MagazineRepository::buildCatalogIssueTitle($seriesTitre, $numero, $horsSerie);

        $numeroOrdre = (float) ($data['numero_ordre'] ?? $issue['numero_ordre'] ?? 0);
        $numeroError = $this->validator->validateNumeroForSeries($seriesId, $numero, $horsSerie, $oeuvreId);
        if ($numeroError !== null) {
            if ($wasHorsSerie && !$horsSerie) {
                return 'Impossible de retirer le hors-série : un numéro classique « '
                    . $numero . ' » existe déjà pour cette revue. '
                    . 'Fusionnez les doublons depuis Maintenance catalogue → Doublons magazines.';
            }

            return $numeroError;
        }

        $numeroOrdre = MagazineNumeroOrdre::adjustForHorsSerie($numeroOrdre, $horsSerie);

        $dateParution = trim((string) ($data['date_parution'] ?? $issue['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? $issue['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? $issue['pages'] ?? 0));
        $posterUrl = trim((string) ($data['poster_url'] ?? $issue['poster_url'] ?? ''));

        $titleError = $this->validator->validateCatalogIssueTitleUnique($titre, $oeuvreId);
        if ($titleError !== null) {
            return $titleError;
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'poster_url' => $posterUrl,
            ], ['titre', 'poster_url']);

            $storedObjectId = null;
            if (array_key_exists('stored_object_id', $data)) {
                $storedObjectId = $data['stored_object_id'] !== null ? (int) $data['stored_object_id'] : null;
            } elseif ((int) ($issue['stored_object_id'] ?? 0) > 0) {
                $storedObjectId = (int) $issue['stored_object_id'];
            }

            $this->db->prepare(
                'UPDATE oeuvre_magazine SET
                    numero = ?, numero_ordre = ?, date_parution = ?, sommaire = ?,
                    pages = ?, est_hors_serie = ?, stored_object_id = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                $storedObjectId,
                $oeuvreId,
            ]);

            if (array_key_exists('support_papier', $data) || array_key_exists('support_physique', $data)) {
                $hasPaper = array_key_exists('support_papier', $data)
                    ? !empty($data['support_papier'])
                    : MagazineSupport::hasPaper((string) ($issue['support_physique'] ?? ''));
                $effectiveStoredId = $storedObjectId !== null
                    ? (int) $storedObjectId
                    : (int) ($issue['stored_object_id'] ?? 0);
                $hasPdf = $effectiveStoredId > 0;
                $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
                    ->execute([MagazineSupport::formatTagsForStorage($hasPaper, $hasPdf), $bibId]);
            }

            $this->db->commit();

            MagazineIssueFts::upsert($oeuvreId);

            $this->libraryMutations->clearWishlistEntriesWhenPossessed($oeuvreId);

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Mise à jour impossible.';
        }
    }
}
