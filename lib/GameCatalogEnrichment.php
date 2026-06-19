<?php
/**
 * Enrichissement catalogue jeux (comptage, sélection, mise à jour IGDB).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameCatalogEnrichment
{
    private readonly PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function countNeedingEnrichment(bool $includeAttempted = false): int
    {
        if (!GameRepository::hasIgdbColumns() || !GameRepository::isAvailable()) {
            return 0;
        }

        $params = [
            'foyer_id' => UserContext::currentFoyerId(),
            'statut' => LibraryStatut::COLLECTION,
        ];

        $pendingSql = $includeAttempted
            ? '1 = 1'
            : self::enrichmentPendingSql('o', 'oj');

        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT o.id)
             FROM oeuvres o
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
             WHERE b.foyer_id = :foyer_id
               AND b.statut = :statut
               AND o.media_domain = :media_domain
               AND ' . $pendingSql
        );
        $params['media_domain'] = MediaDomain::JEU;
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichment(int $limit = 10, bool $force = false): array
    {
        if (!GameRepository::hasIgdbColumns() || !GameRepository::isAvailable()) {
            return [];
        }

        $limit = max(1, $limit);
        $params = [
            'foyer_id' => UserContext::currentFoyerId(),
            'statut' => LibraryStatut::COLLECTION,
            'media_domain' => MediaDomain::JEU,
            'lim' => $limit,
        ];

        $pendingSql = $force ? '1 = 1' : self::enrichmentPendingSql('o', 'oj');
        $igdbCols = GameRepository::hasIgdbColumns() ? ', oj.igdb_id, oj.igdb_enriched_at' : '';

        $stmt = $this->db->prepare(
            'SELECT DISTINCT o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital' . $igdbCols . '
             FROM oeuvres o
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
             WHERE b.foyer_id = :foyer_id
               AND b.statut = :statut
               AND o.media_domain = :media_domain
               AND ' . $pendingSql . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE
             LIMIT :lim'
        );
        $stmt->bindValue('foyer_id', $params['foyer_id'], PDO::PARAM_INT);
        $stmt->bindValue('statut', $params['statut']);
        $stmt->bindValue('media_domain', $params['media_domain']);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static fn (array $row): array => GameRowMapper::hydrateCatalogRow($row),
            $rows
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function updateEnrichmentMetadata(int $oeuvreId, array $meta, bool $forceReplace = false): void
    {
        if (!GameRepository::hasIgdbColumns() || $oeuvreId <= 0) {
            return;
        }

        $repo = new GameRepository();
        $game = $repo->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return;
        }

        $newPoster = trim((string) ($meta['poster_url'] ?? ''));
        if ($forceReplace && $newPoster !== '') {
            $poster = $newPoster;
        } else {
            $poster = $newPoster !== '' ? $newPoster : (string) ($game['poster_url'] ?? '');
        }
        $poster = $this->resolvePosterForOeuvre($oeuvreId, $poster);

        $newAnnee = (int) ($meta['annee'] ?? 0);
        if ($forceReplace && $newAnnee > 0) {
            $annee = $newAnnee;
        } else {
            $annee = (int) ($game['annee'] ?? 0);
            if ($annee <= 0) {
                $annee = $newAnnee;
            }
        }

        $titre = trim((string) ($game['titre'] ?? ''));
        if (array_key_exists('titre', $meta)) {
            $newTitre = trim((string) ($meta['titre'] ?? ''));
            if ($forceReplace && $newTitre !== '') {
                $titre = $newTitre;
            } elseif ($newTitre !== '') {
                $titre = $titre !== '' ? $titre : $newTitre;
            }
        }

        $titreOriginal = trim((string) ($game['titre_original'] ?? ''));
        if (array_key_exists('titre_original', $meta)) {
            $newTitreOriginal = trim((string) ($meta['titre_original'] ?? ''));
            if ($forceReplace && $newTitreOriginal !== '') {
                $titreOriginal = $newTitreOriginal;
            } elseif ($newTitreOriginal !== '') {
                $titreOriginal = $newTitreOriginal;
            }
        }

        $studio = $this->resolveTextField($game, $meta, 'studio', $forceReplace);
        $editeur = $this->resolveTextField($game, $meta, 'editeur', $forceReplace);
        $genre = $this->resolveTagField($game, $meta, 'genre', $forceReplace);
        $franchise = $this->resolveTextField($game, $meta, 'franchise', $forceReplace);
        $gameMode = $this->resolveTagField($game, $meta, 'game_mode', $forceReplace);
        $theme = $this->resolveTagField($game, $meta, 'theme', $forceReplace);
        $alternativeNames = $this->resolveTagField($game, $meta, 'alternative_names', $forceReplace);

        $incomingIgdbId = (int) ($meta['igdb_id'] ?? 0);
        $igdbId = $incomingIgdbId > 0 ? $incomingIgdbId : (int) ($game['igdb_id'] ?? 0);

        $igdbMetaSet = GameRepository::hasIgdbMetadataColumns()
            ? ', franchise = :franchise, game_mode = :game_mode, theme = :theme, alternative_names = :alternative_names'
            : '';

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'titre_original' => $titreOriginal,
                'annee' => $annee,
                'poster_url' => $poster,
            ], ['titre', 'titre_original', 'annee', 'poster_url']);

            $params = [
                'studio' => $studio,
                'editeur' => $editeur,
                'genre' => $genre,
                'igdb_id' => $igdbId,
                'oeuvre_id' => $oeuvreId,
            ];
            if (GameRepository::hasIgdbMetadataColumns()) {
                $params['franchise'] = $franchise;
                $params['game_mode'] = $gameMode;
                $params['theme'] = $theme;
                $params['alternative_names'] = $alternativeNames;
            }

            $this->db->prepare(
                'UPDATE oeuvre_jeu SET
                    studio = :studio,
                    editeur = :editeur,
                    genre = :genre,
                    igdb_id = :igdb_id,
                    igdb_enriched_at = datetime(\'now\')'
                . $igdbMetaSet . '
                 WHERE oeuvre_id = :oeuvre_id'
            )->execute($params);

            $this->db->commit();
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    public function markEnrichmentAttempt(int $oeuvreId): void
    {
        if (!GameRepository::hasIgdbColumns() || $oeuvreId <= 0) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_jeu SET igdb_enriched_at = datetime(\'now\') WHERE oeuvre_id = ?'
        )->execute([$oeuvreId]);
    }

    private static function enrichmentPendingSql(string $oeuvreAlias, string $jeuAlias): string
    {
        $o = $oeuvreAlias . '.';
        $j = $jeuAlias . '.';

        return $j . 'igdb_enriched_at IS NULL
            OR (
                ' . $j . 'igdb_enriched_at IS NOT NULL
                AND (' . $o . 'poster_url IS NULL OR ' . $o . 'poster_url = "")
                AND (' . $j . 'studio IS NULL OR ' . $j . 'studio = "")
                AND (' . $j . 'editeur IS NULL OR ' . $j . 'editeur = "")
                AND (' . $j . 'genre IS NULL OR ' . $j . 'genre = "")
            )';
    }

    /**
     * @param array<string, mixed> $game
     * @param array<string, mixed> $meta
     */
    private function resolveTextField(array $game, array $meta, string $field, bool $forceReplace): string
    {
        $newValue = trim((string) ($meta[$field] ?? ''));
        if ($forceReplace && $newValue !== '') {
            return $newValue;
        }
        if ($newValue !== '') {
            $existing = trim((string) ($game[$field] ?? ''));

            return $existing !== '' ? $existing : $newValue;
        }

        return trim((string) ($game[$field] ?? ''));
    }

    /**
     * @param array<string, mixed> $game
     * @param array<string, mixed> $meta
     */
    private function resolveTagField(array $game, array $meta, string $field, bool $forceReplace): string
    {
        $newValue = GameGenre::normalizeInput((string) ($meta[$field] ?? ''));
        if ($forceReplace && $newValue !== '') {
            return $newValue;
        }
        if ($newValue !== '') {
            $existing = GameGenre::normalizeInput((string) ($game[$field] ?? ''));

            return $existing !== '' ? $existing : $newValue;
        }

        return GameGenre::normalizeInput((string) ($game[$field] ?? ''));
    }

    private function resolvePosterForOeuvre(int $oeuvreId, string $posterUrl): string
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return '';
        }

        if ($oeuvreId > 0) {
            $local = (new PosterStorage())->ensureLocalForOeuvre($oeuvreId, $posterUrl);
            if ($local !== '') {
                return $local;
            }
        }

        return SecureUrl::sanitizePosterUrl($posterUrl);
    }
}
