<?php
/**
 * Import bibliothèque multi-médias optimisé (gros CSV).
 *
 * Précharge le catalogue et la bibliothèque, traite en transaction,
 * n’interroge l’historique que si une date « Vu » est présente.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LibraryBulkImporter
{
    private const MAX_ERRORS_KEPT = 80;

    private readonly PDO $db;

    private readonly BibliothequeRepository $bibliotheque;

    private readonly HistoriqueRepository $historique;

    public function __construct(
        ?PDO $db = null,
        ?BibliothequeRepository $bibliotheque = null,
        ?HistoriqueRepository $historique = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->bibliotheque = $bibliotheque ?? new BibliothequeRepository();
        $this->historique = $historique ?? new HistoriqueRepository();
    }

    /**
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{
     *   imported: int,
     *   added: int,
     *   updated: int,
     *   vues: int,
     *   errors: list<string>,
     *   error_total: int
     * }
     */
    public function importSheet(array $dataRows, array $header): array
    {
        @set_time_limit(600);

        $map = ImportFilmRows::mapHeaders($header, LibraryExportSchema::COLUMN_ALIASES);
        if (!isset($map['oeuvre_id']) && !isset($map['titre'])) {
            return [
                'imported' => 0,
                'added' => 0,
                'updated' => 0,
                'vues' => 0,
                'errors' => ['Colonne « ID catalogue » ou « Titre » requise.'],
                'error_total' => 1,
            ];
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        if ($userId <= 0) {
            return [
                'imported' => 0,
                'added' => 0,
                'updated' => 0,
                'vues' => 0,
                'errors' => ['Utilisateur non connecté.'],
                'error_total' => 1,
            ];
        }

        $oeuvresById = $this->preloadOeuvresById();
        $oeuvresByTitre = $this->indexOeuvresByTitre($oeuvresById);
        $libraryByOeuvre = $this->preloadLibraryByOeuvre($userId, $foyerId);
        $bdSeriesByOeuvre = $this->preloadBdSeriesByOeuvre();
        $magSeriesByOeuvre = $this->preloadMagazineSeriesByOeuvre();

        $added = 0;
        $updated = 0;
        $vues = 0;
        $errors = [];
        $errorTotal = 0;
        $seriesToRegister = []; // series_id => statut
        $pendingVues = []; // list of [bibId, date, note]

        $startedTransaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $line = 1;
            foreach ($dataRows as $row) {
                $line++;
                if (ImportFilmRows::isEmptyRow($row)) {
                    continue;
                }

                try {
                    $parsed = ImportLibraryRows::rowToLibrary($row, $map);
                    $oeuvreId = (int) ($parsed['oeuvre_id'] ?? 0);
                    $titre = trim((string) ($parsed['titre'] ?? ''));
                    if ($oeuvreId <= 0 && $titre === '') {
                        continue;
                    }

                    $statut = LibraryStatut::normalize(
                        (string) ($parsed['statut'] ?? LibraryStatut::COLLECTION)
                    );
                    $importColumns = (array) ($parsed['_import_columns'] ?? array_keys($map));
                    $vuRaw = (string) ($parsed['_vu'] ?? '');
                    $noteRaw = (string) ($parsed['_note'] ?? '');
                    unset($parsed['_vu'], $parsed['_note'], $parsed['_import_columns']);

                    $oeuvre = $this->resolveOeuvre(
                        $oeuvreId,
                        $titre,
                        trim((string) ($parsed['realisateur'] ?? '')),
                        trim((string) ($parsed['media_domain'] ?? '')),
                        $oeuvresById,
                        $oeuvresByTitre
                    );
                    if ($oeuvre === null) {
                        throw new \RuntimeException(
                            $oeuvreId > 0
                                ? 'ID catalogue ' . $oeuvreId . ' introuvable (titre « ' . $titre . ' »).'
                                : 'Aucune œuvre « ' . $titre . ' » au catalogue.'
                        );
                    }

                    $resolvedId = (int) $oeuvre['id'];
                    $domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
                    $details = $this->libraryPayload($parsed, $statut);
                    // Recalcule le support avec le vrai domaine (la colonne CSV peut manquer).
                    $details['support_physique'] = $this->normalizeSupportForDomain(
                        $domain,
                        (string) ($parsed['support_physique'] ?? $details['support_physique'] ?? '')
                    );

                    if ($domain === MediaDomain::BD && !isset($bdSeriesByOeuvre[$resolvedId])) {
                        $this->ensureBdStub($resolvedId, $oeuvre);
                        $bdSeriesByOeuvre[$resolvedId] = 0;
                    }
                    if ($domain === MediaDomain::MAGAZINE && !isset($magSeriesByOeuvre[$resolvedId])) {
                        // Sans ligne oeuvre_magazine, la série ne peut pas s’afficher.
                        throw new \RuntimeException(
                            'Magazine ID ' . $resolvedId
                            . ' sans fiche catalogue (oeuvre_magazine). '
                            . 'Réimportez d’abord le catalogue magazines / CSV admin.'
                        );
                    }

                    // Toujours vérifier en base (pas seulement le cache) pour éviter les UNIQUE.
                    if (!isset($libraryByOeuvre[$resolvedId])) {
                        $existingId = $this->findExistingBibliothequeId($userId, $foyerId, $resolvedId);
                        if ($existingId > 0) {
                            $libraryByOeuvre[$resolvedId] = [
                                'id' => $existingId,
                                'statut' => LibraryStatut::COLLECTION,
                            ];
                        }
                    }

                    if (isset($libraryByOeuvre[$resolvedId])) {
                        $bibId = (int) $libraryByOeuvre[$resolvedId]['id'];
                        $this->applyUpdate($bibId, $details, $importColumns, $statut);
                        $libraryByOeuvre[$resolvedId]['statut'] = $statut;
                        $updated++;
                    } else {
                        $upsert = $this->insertOrUpdateExisting(
                            $userId,
                            $foyerId,
                            $resolvedId,
                            $details,
                            $importColumns,
                            $statut
                        );
                        $bibId = $upsert['id'];
                        $libraryByOeuvre[$resolvedId] = ['id' => $bibId, 'statut' => $statut];
                        if ($upsert['created']) {
                            $added++;
                        } else {
                            $updated++;
                        }
                    }

                    if ($domain === MediaDomain::BD) {
                        $seriesId = (int) ($bdSeriesByOeuvre[$resolvedId] ?? 0);
                        if ($seriesId > 0) {
                            $seriesToRegister[$seriesId] = $statut;
                        }
                    }
                    if ($domain === MediaDomain::MAGAZINE) {
                        $seriesId = (int) ($magSeriesByOeuvre[$resolvedId] ?? 0);
                        if ($seriesId > 0) {
                            $seriesToRegister[$seriesId] = $statut;
                        }
                    }

                    $dateVue = ImportCsv::parseVueDate($vuRaw);
                    if ($dateVue !== null && $bibId > 0) {
                        $pendingVues[] = [$bibId, $dateVue, ImportCsv::parseNote($noteRaw)];
                    }
                } catch (\Throwable $e) {
                    $errorTotal++;
                    if (count($errors) < self::MAX_ERRORS_KEPT) {
                        $errors[] = 'Ligne ' . $line . ' : ' . $e->getMessage();
                    }
                }
            }

            $this->registerSeriesLibrary($seriesToRegister, $userId, $foyerId);

            foreach ($pendingVues as [$bibId, $dateVue, $note]) {
                if ($this->historique->recordViewing($bibId, $dateVue, $note)) {
                    $vues++;
                }
            }

            if ($startedTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $imported = $added + $updated;
        if ($imported === 0 && $errorTotal >= 3) {
            array_unshift(
                $errors,
                'Aucun rattachement : les ID catalogue du fichier ne correspondent probablement pas '
                . 'au catalogue actuel. Importez d’abord le CSV catalogue admin '
                . '(« Réinitialiser le catalogue »), puis réessayez.'
            );
        }
        if ($errorTotal > self::MAX_ERRORS_KEPT) {
            $errors[] = '… et ' . ($errorTotal - self::MAX_ERRORS_KEPT)
                . ' autre(s) erreur(s) non affichée(s).';
        }

        return [
            'imported' => $imported,
            'added' => $added,
            'updated' => $updated,
            'vues' => $vues,
            'errors' => $errors,
            'error_total' => $errorTotal,
        ];
    }

    /**
     * @return array<int, array{id: int, titre: string, realisateur: string, media_domain: string}>
     */
    private function preloadOeuvresById(): array
    {
        $hasDomain = CatalogSchema::hasMediaDomainColumn();
        $sql = $hasDomain
            ? 'SELECT id, titre, realisateur, media_domain FROM oeuvres'
            : 'SELECT id, titre, realisateur FROM oeuvres';
        $stmt = $this->db->query($sql);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $map[$id] = [
                'id' => $id,
                'titre' => (string) ($row['titre'] ?? ''),
                'realisateur' => (string) ($row['realisateur'] ?? ''),
                'media_domain' => $hasDomain
                    ? MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM))
                    : MediaDomain::FILM,
            ];
        }

        return $map;
    }

    /**
     * @param array<int, array{id: int, titre: string, realisateur: string, media_domain: string}> $oeuvresById
     * @return array<string, int>
     */
    private function indexOeuvresByTitre(array $oeuvresById): array
    {
        $index = [];
        foreach ($oeuvresById as $oeuvre) {
            $titre = $this->foldKey($oeuvre['titre']);
            $real = $this->foldKey($oeuvre['realisateur']);
            $domain = $oeuvre['media_domain'];
            $index[$titre . "\0" . $real . "\0" . $domain] = $oeuvre['id'];
            // Clé sans domaine : premier trouvé conserve la priorité.
            $anyKey = $titre . "\0" . $real;
            if (!isset($index[$anyKey])) {
                $index[$anyKey] = $oeuvre['id'];
            }
        }

        return $index;
    }

    /**
     * @return array<int, array{id: int, statut: string}>
     */
    private function preloadLibraryByOeuvre(int $userId, int $foyerId): array
    {
        // Toutes les lignes du foyer (collection) + toutes les lignes de l’utilisateur
        // (envies ou collection orpheline) — évite les doublons UNIQUE user_id/oeuvre_id.
        $stmt = $this->db->prepare(
            'SELECT id, oeuvre_id, statut FROM bibliotheque
             WHERE user_id = ?
                OR (foyer_id = ? AND statut = ?)'
        );
        $stmt->execute([
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
        ]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }
            $statut = (string) ($row['statut'] ?? LibraryStatut::COLLECTION);
            // Collection prime sur envies si les deux existent.
            if (
                isset($map[$oeuvreId])
                && ($map[$oeuvreId]['statut'] ?? '') === LibraryStatut::COLLECTION
                && $statut !== LibraryStatut::COLLECTION
            ) {
                continue;
            }
            $map[$oeuvreId] = [
                'id' => (int) $row['id'],
                'statut' => $statut,
            ];
        }

        return $map;
    }

    /**
     * Insert, ou mise à jour si une contrainte UNIQUE indique que la ligne existe déjà.
     *
     * SQLite : en cas d’échec UNIQUE dans une transaction, il faut un SAVEPOINT
     * sinon les requêtes suivantes restent bloquées.
     *
     * @param array<string, mixed> $details
     * @param list<string> $importedColumns
     * @return array{id: int, created: bool}
     */
    private function insertOrUpdateExisting(
        int $userId,
        int $foyerId,
        int $oeuvreId,
        array $details,
        array $importedColumns,
        string $statut
    ): array {
        // Filet avant INSERT (évite la plupart des UNIQUE).
        $existingId = $this->findExistingBibliothequeId($userId, $foyerId, $oeuvreId);
        if ($existingId > 0) {
            $this->applyUpdate($existingId, $details, $importedColumns, $statut);

            return ['id' => $existingId, 'created' => false];
        }

        // SAVEPOINT : un UNIQUE SQLite « empoisonne » toute la transaction
        // sans point de reprise. Après ROLLBACK TO, il faut RELEASE
        // sinon le prochain SAVEPOINT du même nom bloque les lignes suivantes.
        $useSavepoint = $this->db->inTransaction();
        $savepointName = 'moncine_lib_insert_' . str_replace('.', '_', uniqid('', true));
        if ($useSavepoint) {
            $this->db->exec('SAVEPOINT ' . $savepointName);
        }

        try {
            $bibId = $this->bibliotheque->insert($userId, $foyerId, $oeuvreId, $details);
            if ($useSavepoint) {
                $this->db->exec('RELEASE SAVEPOINT ' . $savepointName);
            }

            return ['id' => $bibId, 'created' => true];
        } catch (\Throwable $e) {
            if ($useSavepoint) {
                try {
                    $this->db->exec('ROLLBACK TO SAVEPOINT ' . $savepointName);
                    $this->db->exec('RELEASE SAVEPOINT ' . $savepointName);
                } catch (\Throwable) {
                    // Le SAVEPOINT peut déjà être invalidé : on continue la récupération.
                }
            }
            if (!$this->isUniqueConstraintFailure($e)) {
                throw $e;
            }
        }

        $existingId = $this->findExistingBibliothequeId($userId, $foyerId, $oeuvreId);
        if ($existingId <= 0) {
            // Dernier recours : n’importe quelle ligne pour ce couple user/œuvre.
            $stmt = $this->db->prepare(
                'SELECT id FROM bibliotheque WHERE user_id = ? AND oeuvre_id = ? ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute([$userId, $oeuvreId]);
            $existingId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($existingId <= 0 && $foyerId > 0) {
            $stmt = $this->db->prepare(
                'SELECT id FROM bibliotheque
                 WHERE foyer_id = ? AND oeuvre_id = ? AND statut = ?
                 ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute([$foyerId, $oeuvreId, LibraryStatut::COLLECTION]);
            $existingId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($existingId <= 0) {
            throw new \RuntimeException(
                'Doublon bibliothèque pour l’œuvre #' . $oeuvreId
                . ' mais aucune entrée existante récupérable.'
            );
        }
        $this->applyUpdate($existingId, $details, $importedColumns, $statut);

        return ['id' => $existingId, 'created' => false];
    }

    private function isUniqueConstraintFailure(\Throwable $e): bool
    {
        $message = $e->getMessage();
        if (str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'bibliotheque.user_id')
            || str_contains($message, 'bibliotheque.foyer_id')) {
            return true;
        }

        if ($e instanceof \PDOException) {
            $info = $e->errorInfo ?? [];
            if (($info[0] ?? '') === '23000' || (int) ($info[1] ?? 0) === 19) {
                return true;
            }
            if ((string) $e->getCode() === '23000') {
                return true;
            }
        }

        return false;
    }

    private function findExistingBibliothequeId(int $userId, int $foyerId, int $oeuvreId): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM bibliotheque
             WHERE oeuvre_id = ?
               AND (
                    user_id = ?
                    OR (foyer_id = ? AND statut = ?)
               )
             ORDER BY CASE WHEN statut = ? THEN 0 ELSE 1 END, id ASC
             LIMIT 1'
        );
        $stmt->execute([
            $oeuvreId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            LibraryStatut::COLLECTION,
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    /** @return array<int, int> oeuvre_id => series_id */
    private function preloadBdSeriesByOeuvre(): array
    {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        $stmt = $this->db->query('SELECT oeuvre_id, COALESCE(series_id, 0) AS series_id FROM oeuvre_bd');
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) $row['oeuvre_id']] = (int) $row['series_id'];
        }

        return $map;
    }

    /** @return array<int, int> oeuvre_id => series_id */
    private function preloadMagazineSeriesByOeuvre(): array
    {
        if (!MagazineRepository::isAvailable()) {
            return [];
        }

        $stmt = $this->db->query(
            'SELECT oeuvre_id, COALESCE(series_id, 0) AS series_id FROM oeuvre_magazine'
        );
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) $row['oeuvre_id']] = (int) $row['series_id'];
        }

        return $map;
    }

    /**
     * @param array<int, array{id: int, titre: string, realisateur: string, media_domain: string}> $oeuvresById
     * @param array<string, int> $oeuvresByTitre
     * @return array{id: int, titre: string, realisateur: string, media_domain: string}|null
     */
    private function resolveOeuvre(
        int $oeuvreId,
        string $titre,
        string $realisateur,
        string $mediaDomain,
        array $oeuvresById,
        array $oeuvresByTitre
    ): ?array {
        if ($oeuvreId > 0 && isset($oeuvresById[$oeuvreId])) {
            return $oeuvresById[$oeuvreId];
        }

        if ($titre === '') {
            return null;
        }

        $titreKey = $this->foldKey($titre);
        $realKey = $this->foldKey($realisateur);
        if ($mediaDomain !== '') {
            $domain = MediaDomain::normalize($mediaDomain);
            $key = $titreKey . "\0" . $realKey . "\0" . $domain;
            if (isset($oeuvresByTitre[$key])) {
                return $oeuvresById[$oeuvresByTitre[$key]] ?? null;
            }
        }

        $anyKey = $titreKey . "\0" . $realKey;
        if (isset($oeuvresByTitre[$anyKey])) {
            return $oeuvresById[$oeuvresByTitre[$anyKey]] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function libraryPayload(array $data, string $statut): array
    {
        return [
            'statut' => $statut,
            'support_physique' => (string) ($data['support_physique'] ?? ''),
            'format_image' => (string) ($data['format_image'] ?? ''),
            'format_son' => (string) ($data['format_son'] ?? ''),
            'saga' => (string) ($data['saga'] ?? ''),
            'saga_ordre' => max(0, (int) ($data['saga_ordre'] ?? 0)),
            'saison_numero' => max(0, (int) ($data['saison_numero'] ?? 0)),
            'saison_label' => (string) ($data['saison_label'] ?? ''),
            'ean' => (string) ($data['ean'] ?? ''),
        ];
    }

    /**
     * Support selon le domaine. Magazine : vide = non possédé (ne pas inventer « papier »).
     */
    private function normalizeSupportForDomain(string $domain, string $support): string
    {
        $support = trim($support);
        if ($domain === MediaDomain::MAGAZINE) {
            if ($support === '') {
                return '';
            }
            $tags = MagazineSupport::parseTags($support);
            if ($tags === []) {
                return '';
            }

            return MagazineSupport::formatTagsForStorage(
                in_array(MagazineSupport::TAG_PAPIER, $tags, true),
                in_array(MagazineSupport::TAG_PDF, $tags, true)
            );
        }

        if ($domain === MediaDomain::BD) {
            return BdPhysicalSupport::normalize($support);
        }

        $film = SupportPhysique::normalize($support);
        if ($film !== '') {
            return $film;
        }

        return BdPhysicalSupport::normalize($support);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    private function applyUpdate(int $libraryId, array $data, array $importedColumns, string $statut): void
    {
        $importSet = $importedColumns !== [] ? array_flip($importedColumns) : null;
        $update = [];
        foreach (LibraryExportSchema::libraryDatabaseFields() as $field) {
            if ($importSet !== null && !isset($importSet[$field])) {
                continue;
            }
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if ($importSet === null || isset($importSet['statut'])) {
            $update['statut'] = $statut;
        }
        // Passage envies → collection : rattacher au foyer courant.
        if (($update['statut'] ?? '') === LibraryStatut::COLLECTION) {
            $foyerId = UserContext::currentFoyerId();
            if ($foyerId > 0) {
                $update['foyer_id'] = $foyerId;
            }
        } elseif (($update['statut'] ?? '') === LibraryStatut::WISHLIST) {
            $update['foyer_id'] = null;
        }
        if ($update !== []) {
            $this->bibliotheque->update($libraryId, $update);
        }
    }

    /**
     * @param array{id: int, titre: string, realisateur: string, media_domain: string} $oeuvre
     */
    private function ensureBdStub(int $oeuvreId, array $oeuvre): void
    {
        $this->db->prepare(
            'INSERT OR IGNORE INTO oeuvre_bd (
                oeuvre_id, series_id, kind, tome_numero, tome_ordre, tome_label,
                est_hors_serie, scenariste, dessinateur, editeur, genre
             ) VALUES (?, NULL, ?, 0, 0, ?, 0, ?, ?, ?, ?)'
        )->execute([
            $oeuvreId,
            BdKind::BD,
            trim($oeuvre['titre']),
            trim($oeuvre['realisateur']),
            '',
            '',
            '',
        ]);
    }

    /**
     * Enregistre les séries BD / magazines dans series_bibliotheque.
     *
     * @param array<int, string> $seriesToRegister series_id => statut
     */
    private function registerSeriesLibrary(array $seriesToRegister, int $userId, int $foyerId): void
    {
        if ($seriesToRegister === []) {
            return;
        }
        if (!BdRepository::seriesLibraryTableExists() && !MagazineRepository::seriesLibraryTableExists()) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($seriesToRegister as $seriesId => $statut) {
            $seriesId = (int) $seriesId;
            if ($seriesId <= 0) {
                continue;
            }
            $stmt->execute([
                $seriesId,
                $userId,
                $foyerId,
                LibraryStatut::normalize($statut),
            ]);
        }
    }

    private function foldKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'ù', 'û', 'ô', 'î', 'ï', 'ç'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'u', 'u', 'o', 'i', 'i', 'c'],
            $value
        );
    }
}
