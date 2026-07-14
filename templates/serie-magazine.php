<?php
/** @var array<string, mixed>|null $series */
/** @var list<array<string, mixed>> $issues */
/** @var string $statut */
/** @var string $publicationTypeLabel */
/** @var float $suggestNumeroOrdre */
/** @var string $searchQuery */
/** @var bool $hasSearch */
/** @var int $totalAllIssues */
/** @var int $filteredCount */
/** @var bool $pdfTextSearchEnabled */
/** @var bool $pdftotextAvailable */
/** @var string $reindexMessage */
/** @var string $possessionFilter */
/** @var int $totalWithPossessionFilter */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var int $listTotal */
/** @var int $possessedCount */
/** @var int $catalogIssueCount */
/** @var string|null $sortBy */
/** @var string|null $sortDir */
?>
<section>
    <?php if ($series === null): ?>
        <h1>Série introuvable</h1>
        <p><a href="/magazines.php">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        $isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
        $seriesQuery = ['statut' => $statut];
        if (($possessionFilter ?? Moncine\MagazineRepository::POSSESSION_ALL) !== Moncine\MagazineRepository::POSSESSION_ALL) {
            $seriesQuery['possession'] = $possessionFilter;
        }
        $possessionFilter = $possessionFilter ?? Moncine\MagazineRepository::POSSESSION_ALL;
        ?>
        <header class="magazine-series-header">
            <p>
                <a href="<?= $isWishlist ? '/magazines-envies.php' : '/magazines.php' ?>" class="btn btn-secondary btn-sm">← Retour</a>
            </p>
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        <?= Moncine\View::escape($publicationTypeLabel) ?>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            · <?= Moncine\View::escape((string) $series['editeur']) ?>
                        <?php endif; ?>
                        <?php if (trim((string) ($series['issn'] ?? '')) !== ''): ?>
                            · ISSN <?= Moncine\View::escape((string) $series['issn']) ?>
                        <?php endif; ?>
                        <?php if (!$isWishlist): ?>
                            · <?= (int) ($possessedCount ?? 0) ?> possédé<?= (int) ($possessedCount ?? 0) > 1 ? 's' : '' ?>
                            sur <?= (int) ($catalogIssueCount ?? 0) ?>
                        <?php endif; ?>
                    </p>
                    <?php
                    $seriesTagList = Moncine\MagazineSeriesTag::parseList((string) ($series['tags'] ?? ''));
                    if ($seriesTagList !== []):
                    ?>
                        <p class="hint">
                            Tags :
                            <?php foreach ($seriesTagList as $tagIndex => $tagLabel): ?>
                                <?php if ($tagIndex > 0): ?>, <?php endif; ?>
                                <span class="magazine-tag magazine-tag--series"><?= Moncine\View::escape($tagLabel) ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <?php require MONCINE_ROOT . '/templates/_magazine_series_categories_display.php'; ?>
                    <?php if (trim((string) ($series['notes'] ?? '')) !== ''): ?>
                        <p class="hint"><?= nl2br(Moncine\View::escape((string) $series['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <p>
                <a href="/ajouter-numero-magazine.php?series_id=<?= $seriesId ?>&statut=<?= Moncine\View::escape($statut) ?>"
                   class="btn btn-accent">Ajouter un numéro</a>
                <a href="/modifier-serie-magazine.php?series_id=<?= $seriesId ?>"
                   class="btn btn-secondary">Modifier la série</a>
                <?php if ($totalAllIssues > 0 || $hasSearch): ?>
                    <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesPrintUrl(
                        $seriesId,
                        $sortBy ?? 'numero_ordre',
                        $sortDir ?? 'desc',
                        array_filter([
                            'statut' => $statut,
                            'q' => $hasSearch ? $searchQuery : null,
                            'possession' => $possessionFilter !== Moncine\MagazineRepository::POSSESSION_ALL
                                ? $possessionFilter
                                : null,
                        ])
                    )) ?>"
                       class="btn btn-secondary">Exporter en PDF</a>
                <?php endif; ?>
            </p>
        </header>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Numéro retiré de votre liste.</div>
        <?php endif; ?>
        <?php if (isset($_GET['added_series'])): ?>
            <div class="alert alert-success">
                Revue ajoutée à vos magazines.
                <?php if (isset($_GET['linked_issues']) && (int) $_GET['linked_issues'] > 0): ?>
                    <?= (int) $_GET['linked_issues'] ?> numéro(s) du catalogue sont listés comme <strong>non possédés</strong>.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['error']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['wishlist'])): ?>
            <div class="alert alert-success">Numéro ajouté à vos envies (il reste visible ici avec le badge « Non possédé »).</div>
        <?php endif; ?>

        <?php if ($reindexMessage !== ''): ?>
            <div class="alert alert-success"><?= Moncine\View::escape($reindexMessage) ?></div>
        <?php endif; ?>

        <?php if ($totalAllIssues > 0 || $hasSearch): ?>
            <form method="get" action="/serie-magazine.php" class="collection-search magazine-issues-search">
                <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">
                <?php if ($possessionFilter !== Moncine\MagazineRepository::POSSESSION_ALL): ?>
                    <input type="hidden" name="possession" value="<?= Moncine\View::escape($possessionFilter) ?>">
                <?php endif; ?>
                <div class="magazine-issues-search__bar">
                    <label for="q" class="magazine-issues-search__label">Rechercher dans cette série</label>
                    <div class="magazine-issues-search__bar-row">
                        <input type="search" name="q" id="q" class="magazine-issues-search__input"
                               value="<?= Moncine\View::escape($searchQuery) ?>"
                               placeholder="Numéro, date (06/2024), mot du sommaire ou du PDF…">
                        <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                        <?php if ($hasSearch): ?>
                            <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', $seriesQuery)) ?>"
                               class="btn btn-secondary btn-sm">Effacer</a>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="hint magazine-issues-search__hint">
                    Cherche parmi tous les numéros de
                    <strong><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></strong>
                    (n°, date, sommaire saisi<?php if ($pdfTextSearchEnabled): ?>, texte des 6 premières pages du PDF<?php endif; ?>).
                </p>
            </form>

            <?php if (!$isWishlist && $totalAllIssues > 0): ?>
                <?php
                $possessionBaseQuery = ['statut' => $statut];
                if ($hasSearch) {
                    $possessionBaseQuery['q'] = $searchQuery;
                }
                $possessionLink = static function (string $filter) use ($seriesId, $possessionBaseQuery): string {
                    $params = $possessionBaseQuery;
                    if ($filter !== Moncine\MagazineRepository::POSSESSION_ALL) {
                        $params['possession'] = $filter;
                    }

                    return Moncine\View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', $params);
                };
                ?>
                <nav class="magazine-possession-filter" aria-label="Filtrer les numéros affichés">
                    <span class="magazine-possession-filter__label">Afficher :</span>
                    <a href="<?= Moncine\View::escape($possessionLink(Moncine\MagazineRepository::POSSESSION_ALL)) ?>"
                       class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\MagazineRepository::POSSESSION_ALL ? ' is-active' : '' ?>">Tous</a>
                    <a href="<?= Moncine\View::escape($possessionLink(Moncine\MagazineRepository::POSSESSION_OWNED)) ?>"
                       class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\MagazineRepository::POSSESSION_OWNED ? ' is-active' : '' ?>">Possédés</a>
                    <a href="<?= Moncine\View::escape($possessionLink(Moncine\MagazineRepository::POSSESSION_UNOWNED)) ?>"
                       class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\MagazineRepository::POSSESSION_UNOWNED ? ' is-active' : '' ?>">Non possédés</a>
                    <a href="<?= Moncine\View::escape($possessionLink(Moncine\MagazineRepository::FILTER_HORS_SERIE)) ?>"
                       class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\MagazineRepository::FILTER_HORS_SERIE ? ' is-active' : '' ?>">Hors-série</a>
                </nav>
            <?php endif; ?>

            <?php if ($pdfTextSearchEnabled && $pdftotextAvailable && $totalAllIssues > 0): ?>
                <form method="post" action="/serie-magazine.php?series_id=<?= $seriesId ?>&statut=<?= Moncine\View::escape($statut) ?>" class="magazine-issues-reindex">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="reindex_pdf_text">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        Indexer le texte des PDF déjà importés
                    </button>
                    <span class="hint">Utile pour les numéros ajoutés avant cette fonctionnalité.</span>
                </form>
            <?php elseif ($pdfTextSearchEnabled && !$pdftotextAvailable): ?>
                <p class="hint">Pour indexer le sommaire depuis les PDF, installez <code>pdftotext</code> (paquet Poppler) sur le serveur.</p>
            <?php endif; ?>

            <p class="stats">
                <?php if ($hasSearch): ?>
                    <?= (int) $filteredCount ?> numéro(s) trouvé(s) sur <?= (int) $totalAllIssues ?>.
                <?php elseif ($possessionFilter !== Moncine\MagazineRepository::POSSESSION_ALL): ?>
                    <?= (int) $filteredCount ?> numéro(s)
                    <?php if ($possessionFilter === Moncine\MagazineRepository::FILTER_HORS_SERIE): ?>
                        hors-série
                    <?php elseif ($possessionFilter === Moncine\MagazineRepository::POSSESSION_UNOWNED): ?>
                        non possédé(s)
                    <?php else: ?>
                        possédé(s)
                    <?php endif; ?>
                    sur <?= (int) $totalAllIssues ?> au total.
                <?php else: ?>
                    <?= (int) $totalAllIssues ?> numéro(s) dans cette liste.
                <?php endif; ?>
                <?php if ($totalPages > 1): ?>
                    <span class="stats__page-range">
                        — page <?= (int) $page ?> / <?= (int) $totalPages ?>
                        (<?= (int) $perPage ?> par page)
                    </span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($issues === []): ?>
            <p class="hint">
                <?php if ($hasSearch): ?>
                    Aucun numéro ne correspond à votre recherche.
                <?php elseif ($possessionFilter === Moncine\MagazineRepository::FILTER_HORS_SERIE): ?>
                    Aucun numéro hors-série dans cette série.
                <?php else: ?>
                    Aucun numéro dans cette liste. Ajoutez le premier numéro.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <?php
            $paginationIdSuffix = '-top';
            require MONCINE_ROOT . '/templates/_magazine_issues_pagination.php';
            ?>
            <div class="magazine-issues-grid" id="magazine-issues-grid">
                <?php foreach ($issues as $row): ?>
                    <?php
                    $bibId = (int) ($row['bib_id'] ?? 0);
                    $storedObjectId = (int) ($row['stored_object_id'] ?? 0);
                    $issueUrl = Moncine\View::magazineIssueUrl($bibId);
                    $pdfUrl = $storedObjectId > 0 ? '/media-object.php?id=' . $storedObjectId : '';
                    $cover = Moncine\View::posterSrc(trim((string) ($row['poster_url'] ?? '')) ?: null);
                    $dateLabel = Moncine\PublicationType::formatParutionDate(
                        (string) ($row['date_parution'] ?? ''),
                        (string) ($row['publication_type'] ?? $series['publication_type'] ?? '')
                    );
                    $pages = (int) ($row['pages'] ?? 0);
                    $isPossessed = Moncine\MagazineSupport::isPossessed($row);
                    $cardClass = 'magazine-issue-card';
                    if (!$isWishlist && !$isPossessed) {
                        $cardClass .= ' magazine-issue-card--unowned';
                    }
                    ?>
                    <article class="<?= Moncine\View::escape($cardClass) ?>">
                        <a href="<?= Moncine\View::escape($issueUrl) ?>" class="magazine-issue-card__cover-link">
                            <?php if ($cover !== ''): ?>
                                <img src="<?= $cover ?>" alt="" class="magazine-cover magazine-cover--card" loading="lazy">
                            <?php else: ?>
                                <span class="magazine-cover magazine-cover--card magazine-cover--empty" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <div class="magazine-issue-card__body">
                            <h2 class="magazine-issue-card__title">
                                <?php if (!empty($row['est_hors_serie'])): ?>
                                    <span class="badge">HS</span>
                                <?php endif; ?>
                                N° <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
                            </h2>
                            <p class="magazine-issue-card__meta hint">
                                <?= Moncine\View::escape($dateLabel) ?>
                                <?php if ($pages > 0): ?>
                                    · <?= $pages ?> p.
                                <?php endif; ?>
                                <?php $issue = $row; require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
                                <?php if (!$isWishlist && !Moncine\MagazineSupport::isPossessed($row)): ?>
                                    <span class="magazine-tag magazine-tag--none">Non possédé</span>
                                <?php endif; ?>
                            </p>
                            <?php require MONCINE_ROOT . '/templates/_magazine_series_categories_display.php'; ?>
                            <div class="magazine-issue-card__actions">
                                <a href="<?= Moncine\View::escape($issueUrl) ?>" class="btn btn-secondary btn-sm">Fiche</a>
                                <?php if ($pdfUrl !== ''): ?>
                                    <a href="<?= Moncine\View::escape($pdfUrl) ?>"
                                       class="btn btn-accent btn-sm"
                                       target="_blank"
                                       rel="noopener">PDF</a>
                                <?php endif; ?>
                                <?php if (!$isWishlist): ?>
                                    <?php
                                    $issue = $row;
                                    require MONCINE_ROOT . '/templates/_magazine_wishlist_button.php';
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php
            $paginationIdSuffix = '-bottom';
            require MONCINE_ROOT . '/templates/_magazine_issues_pagination.php';
            ?>
        <?php endif; ?>

        <?php if (!empty($seriesInLibrary)): ?>
            <?php
            $pageStatut = $statut;
            $issueCount = $totalAllIssues;
            require MONCINE_ROOT . '/templates/_magazine_series_remove_button.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
