<?php
/**
 * Pont magazines ↔ catalogue multi-médias (jeu, film…).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineSubjectCatalogLink
{
    /** @return array<string, string> domaine => libellé */
    public static function linkableMediaDomainChoices(): array
    {
        $choices = [
            '' => 'Aucun (sujet sans lien catalogue)',
        ];

        if (GameRepository::isAvailable()) {
            $choices[MediaDomain::JEU] = 'Jeu vidéo';
        }

        $choices[MediaDomain::FILM] = 'Film';

        return $choices;
    }

    public static function isAvailable(): bool
    {
        return MagazineSubjectRepository::isAvailable()
            && MagazineGameLink::catalogColumnExists();
    }

    public static function isLinkableDomain(string $domain): bool
    {
        $domain = MediaDomain::normalize($domain);

        return $domain === MediaDomain::FILM
            || ($domain === MediaDomain::JEU && GameRepository::isAvailable());
    }

    /**
     * @return true|string
     */
    public static function validateCatalogOeuvreId(int $oeuvreId, string $expectedDomain = ''): bool|string
    {
        if ($oeuvreId <= 0) {
            return true;
        }

        $row = (new OeuvreRepository())->findByIdForAdmin($oeuvreId);
        if ($row === null) {
            return 'La fiche catalogue sélectionnée est introuvable.';
        }

        $domain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));
        $expectedDomain = MediaDomain::normalize($expectedDomain);
        if ($expectedDomain !== '' && $expectedDomain !== MediaDomain::FILM && $domain !== $expectedDomain) {
            return 'Le type de média ne correspond pas à la fiche catalogue choisie.';
        }

        if ($domain === MediaDomain::JEU && (new GameRepository())->findCatalogByOeuvreId($oeuvreId) === null) {
            return 'La fiche jeu sélectionnée est introuvable.';
        }

        if (!self::isLinkableDomain($domain)) {
            return 'Ce type de média ne peut pas être relié à un sujet magazine.';
        }

        return true;
    }

    /**
     * Recherche dans le catalogue du domaine demandé.
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $domain, string $query, int $limit = 20): array
    {
        $domain = MediaDomain::normalize($domain);
        if (!self::isLinkableDomain($domain) || trim($query) === '') {
            return [];
        }

        $limit = max(1, min(30, $limit));

        return match ($domain) {
            MediaDomain::JEU => (new GameRepository())->searchCatalog($query, $limit),
            MediaDomain::FILM => (new FilmRepository())->searchCatalogOeuvres($query, $limit),
            default => [],
        };
    }

    /**
     * Retrouve une fiche catalogue existante ou en crée une minimale.
     *
     * @return int|string ID œuvre ou message d’erreur
     */
    public function findOrCreateCatalogOeuvre(string $domain, string $title, int $year = 0): int|string
    {
        $domain = MediaDomain::normalize($domain);
        if (!self::isLinkableDomain($domain)) {
            return 'Choisissez un type de média valide.';
        }

        $title = trim($title);
        if ($title === '') {
            return 'Indiquez un titre pour le média.';
        }

        $existing = $this->findBestCatalogMatch($domain, $title);
        if ($existing !== null) {
            return (int) ($existing['oeuvre_id'] ?? 0);
        }

        return $this->createMinimalCatalogEntry($domain, $title, $year);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveCatalogRow(int $oeuvreId): ?array
    {
        if ($oeuvreId <= 0) {
            return null;
        }

        $oeuvre = (new OeuvreRepository())->findByIdForAdmin($oeuvreId);
        if ($oeuvre === null) {
            return null;
        }

        $domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));

        if ($domain === MediaDomain::JEU) {
            $game = (new GameRepository())->findCatalogByOeuvreId($oeuvreId);
            if ($game === null) {
                return null;
            }

            return [
                'oeuvre_id' => $oeuvreId,
                'media_domain' => $domain,
                'title' => trim((string) ($game['titre'] ?? '')),
                'display_label' => (string) ($game['display_label'] ?? $game['display_titre'] ?? $game['titre'] ?? ''),
                'annee' => (int) ($game['annee'] ?? 0),
                'poster_url' => $game['poster_url'] ?? null,
                'detail_hint' => (string) ($game['platform_short'] ?? ''),
            ];
        }

        if ($domain === MediaDomain::FILM) {
            $title = trim((string) ($oeuvre['titre'] ?? ''));
            if ($title === '') {
                return null;
            }

            $annee = (int) ($oeuvre['annee'] ?? 0);
            $realisateur = trim((string) ($oeuvre['realisateur'] ?? ''));
            $displayLabel = $title;
            if ($annee > 0) {
                $displayLabel .= ' (' . $annee . ')';
            }

            return [
                'oeuvre_id' => $oeuvreId,
                'media_domain' => $domain,
                'title' => $title,
                'display_label' => $displayLabel,
                'annee' => $annee,
                'poster_url' => $oeuvre['poster_url'] ?? null,
                'detail_hint' => $realisateur,
            ];
        }

        return null;
    }

    /**
     * Enrichit un sujet magazine avec les infos catalogue + lien bibliothèque.
     *
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    public function enrichSubjectRow(array $subject, int $userId, int $foyerId): array
    {
        $subjectId = (int) ($subject['id'] ?? 0);
        $oeuvreId = (int) ($subject['catalog_oeuvre_id'] ?? 0);
        $subject['catalog_game'] = null;
        $subject['catalog_game_bib_id'] = 0;
        $subject['catalog_game_url'] = '';
        $subject['media_nav_url'] = View::magazineSubjectUrl($subjectId);
        $subject['media_poster_src'] = '';
        $subject['media_subtitle'] = '';
        $subject['media_in_library'] = false;
        $subject['media_has_catalog'] = false;

        if ($oeuvreId <= 0 || !self::isAvailable()) {
            return $subject;
        }

        $catalog = $this->resolveCatalogRow($oeuvreId);
        if ($catalog === null) {
            return $subject;
        }

        $domain = (string) ($catalog['media_domain'] ?? '');
        $subject['media_has_catalog'] = true;
        $subject['media_poster_src'] = View::posterSrc($catalog['poster_url'] ?? null);

        $subtitleParts = array_filter([
            trim((string) ($catalog['detail_hint'] ?? '')),
            (int) ($catalog['annee'] ?? 0) > 0 ? (string) (int) $catalog['annee'] : '',
        ], static fn (string $part): bool => $part !== '');
        $subject['media_subtitle'] = implode(' · ', $subtitleParts);

        if ($domain === MediaDomain::JEU) {
            $game = (new GameRepository())->findCatalogByOeuvreId($oeuvreId);
            if ($game !== null) {
                $subject['catalog_game'] = $game;
            }
            $bibId = (new GameRepository())->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
            if ($bibId !== null && $bibId > 0) {
                $subject['catalog_game_bib_id'] = $bibId;
                $subject['catalog_game_url'] = View::gameNavUrl($bibId);
                $subject['media_in_library'] = true;
                $subject['media_nav_url'] = View::gameNavUrl($bibId);
            } else {
                $subject['media_nav_url'] = View::oeuvreJeuUrl($oeuvreId);
            }

            return $subject;
        }

        if ($domain === MediaDomain::FILM) {
            $library = (new BibliothequeRepository())->findByOeuvreId($oeuvreId, $userId, $foyerId);
            $bibId = (int) ($library['id'] ?? 0);
            if ($bibId > 0) {
                $subject['media_in_library'] = true;
                $subject['media_nav_url'] = View::filmLibraryNavUrl($bibId);
            } else {
                $subject['media_nav_url'] = View::catalogOeuvreDetailUrl($oeuvreId, MediaDomain::FILM);
            }
        }

        return $subject;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBestCatalogMatch(string $domain, string $title): ?array
    {
        $titleKey = MagazineSubject::normalizeLabelKey($title);
        if ($titleKey === '') {
            return null;
        }

        foreach ($this->searchCatalog($domain, $title, 15) as $row) {
            $candidateTitle = (string) ($row['display_titre'] ?? $row['titre'] ?? $row['title'] ?? '');
            if (MagazineSubject::normalizeLabelKey($candidateTitle) === $titleKey) {
                return [
                    'oeuvre_id' => (int) ($row['oeuvre_id'] ?? $row['id'] ?? 0),
                ];
            }
        }

        $oeuvres = new OeuvreRepository();
        if ($domain === MediaDomain::JEU) {
            $oeuvre = $oeuvres->findByTitreRealisateurAndDomain($title, '', MediaDomain::JEU);
        } else {
            $oeuvre = $oeuvres->findByTitreRealisateurAndDomain($title, '', MediaDomain::FILM);
        }

        if ($oeuvre === null) {
            return null;
        }

        return ['oeuvre_id' => (int) ($oeuvre['id'] ?? 0)];
    }

    /**
     * @return int|string
     */
    private function createMinimalCatalogEntry(string $domain, string $title, int $year): int|string
    {
        $year = MagazineSubject::normalizeParutionYear($year);

        if ($domain === MediaDomain::JEU) {
            $created = (new GameRepository())->createCatalogOnly([
                'titre' => $title,
                'annee' => $year,
                'platform' => GamePlatform::PC,
            ]);
            if (!is_int($created)) {
                $existing = (new OeuvreRepository())->findByTitreRealisateurAndDomain($title, '', MediaDomain::JEU);
                if ($existing !== null) {
                    return (int) ($existing['id'] ?? 0);
                }

                return $created;
            }

            return $created;
        }

        $oeuvres = new OeuvreRepository();
        $existing = $oeuvres->findByTitreRealisateurAndDomain($title, '', MediaDomain::FILM);
        if ($existing !== null) {
            return (int) ($existing['id'] ?? 0);
        }

        return $oeuvres->insert([
            'titre' => $title,
            'titre_original' => '',
            'realisateur' => '',
            'duree_min' => 0,
            'styles' => '',
            'annee' => $year,
            'nationalite' => '',
            'tmdb_id' => 0,
            'tmdb_media_type' => '',
            'tmdb_tv_kind' => '',
            'realisateur_tmdb_id' => 0,
            'acteur_1' => '',
            'acteur_1_tmdb_id' => 0,
            'acteur_2' => '',
            'acteur_2_tmdb_id' => 0,
            'acteur_3' => '',
            'acteur_3_tmdb_id' => 0,
            'poster_url' => '',
            'synopsis' => '',
            'moncine_kind' => '',
            'omdb_imdb_id' => '',
            'omdb_enriched_at' => null,
            'media_domain' => MediaDomain::FILM,
        ]);
    }
}
