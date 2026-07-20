<?php
/** @var array<string, mixed>|null $series */
/** @var list<array<string, mixed>> $tomes */
/** @var string $statut */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $searchQuery */
/** @var string $viewMode */
/** @var int $totalCount */
/** @var int $totalAllTomes */
/** @var string $possessionFilter */
/** @var int $suggestTomeNumero */
/** @var string $kindLabel */
/** @var int $possessedCount */
/** @var int $catalogTomeCount */
/** @var bool $seriesInLibrary */
?>
<section>
    <?php if ($series === null): ?>
        <h1>Série introuvable</h1>
        <p><a href="/bd.php">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        $isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
        $possessionFilter = Moncine\BdRepository::normalizePossessionFilter($possessionFilter ?? '');
        $hasSearch = trim($searchQuery) !== '';
        $viewMode = Moncine\CollectionViewMode::normalizeBdSeries($viewMode ?? '');
        $isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
        $seriesQuery = array_filter([
            'statut' => $statut,
            'q' => $hasSearch ? $searchQuery : null,
            'possession' => (!$isWishlist && $possessionFilter !== Moncine\BdRepository::POSSESSION_ALL)
                ? $possessionFilter
                : null,
        ]);
        $seriesViewUrl = static function (string $mode) use ($seriesId, $sortBy, $sortDir, $seriesQuery): string {
            return Moncine\View::bdSeriesUrl($seriesId, $sortBy, $sortDir, $seriesQuery, $mode);
        };
        ?>
        <header class="magazine-series-header">
            <p>
                <a href="<?= $isWishlist ? '/bd-envies.php' : '/bd.php' ?>" class="btn btn-secondary btn-sm">← Retour</a>
            </p>
            <?php if (isset($_GET['error']) && trim((string) $_GET['error']) !== ''): ?>
                <div class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['error']) ?></div>
            <?php endif; ?>
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        <?= Moncine\View::escape($kindLabel) ?>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            · <?= Moncine\View::escape((string) $series['editeur']) ?>
                        <?php endif; ?>
                        <?php if (!$isWishlist): ?>
                            · <?= (int) ($possessedCount ?? 0) ?> possédé<?= (int) ($possessedCount ?? 0) > 1 ? 's' : '' ?>
                            sur <?= (int) ($catalogTomeCount ?? 0) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (trim((string) ($series['notes'] ?? '')) !== ''): ?>
                        <p class="hint"><?= nl2br(Moncine\View::escape((string) $series['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <p>
                <a href="<?= Moncine\View::escape(Moncine\View::bdAddTomeUrl($seriesId, $statut)) ?>"
                   class="btn btn-accent">Ajouter un tome</a>
                <a href="/modifier-serie-bd.php?series_id=<?= $seriesId ?>"
                   class="btn btn-secondary">Modifier la série</a>
                <?php if ($totalCount > 0): ?>
                    <a href="<?= Moncine\View::escape(Moncine\View::bdSeriesPrintUrl(
                        $seriesId,
                        $sortBy ?? 'tome',
                        $sortDir ?? 'asc',
                        array_filter([
                            'statut' => $statut,
                            'q' => trim($searchQuery) !== '' ? $searchQuery : null,
                        ])
                    )) ?>"
                       class="btn btn-secondary">Exporter en PDF</a>
                <?php endif; ?>
            </p>
        </header>

        <?php if (isset($_GET['added_series'])): ?>
            <div class="alert alert-success">Série ajoutée à votre bibliothèque.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Tome retiré.</div>
        <?php endif; ?>

        <form method="get" action="/serie-bd.php" class="collection-search import-form">
            <input type="hidden" name="series_id" value="<?= $seriesId ?>">
            <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">
            <?php if (!$isWishlist && $possessionFilter !== Moncine\BdRepository::POSSESSION_ALL): ?>
                <input type="hidden" name="possession" value="<?= Moncine\View::escape($possessionFilter) ?>">
            <?php endif; ?>
            <?php if (!$isGridView): ?>
                <input type="hidden" name="view" value="list">
            <?php endif; ?>
            <label for="serie_bd_q">Rechercher dans cette série</label>
            <input type="search" name="q" id="serie_bd_q"
                   value="<?= Moncine\View::escape($searchQuery) ?>"
                   placeholder="Auteur, genre…">
            <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
        </form>

        <?php if (!$isWishlist && ($totalAllTomes ?? $totalCount) > 0): ?>
            <?php
            $possessionBaseQuery = ['statut' => $statut];
            if ($hasSearch) {
                $possessionBaseQuery['q'] = $searchQuery;
            }
            $possessionLink = static function (string $filter) use ($seriesId, $sortBy, $sortDir, $possessionBaseQuery, $viewMode): string {
                $params = $possessionBaseQuery;
                if ($filter !== Moncine\BdRepository::POSSESSION_ALL) {
                    $params['possession'] = $filter;
                }

                return Moncine\View::bdSeriesUrl($seriesId, $sortBy, $sortDir, $params, $viewMode);
            };
            ?>
            <nav class="magazine-possession-filter" aria-label="Filtrer les tomes affichés">
                <span class="magazine-possession-filter__label">Afficher :</span>
                <a href="<?= Moncine\View::escape($possessionLink(Moncine\BdRepository::POSSESSION_ALL)) ?>"
                   class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\BdRepository::POSSESSION_ALL ? ' is-active' : '' ?>">Tous</a>
                <a href="<?= Moncine\View::escape($possessionLink(Moncine\BdRepository::POSSESSION_OWNED)) ?>"
                   class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\BdRepository::POSSESSION_OWNED ? ' is-active' : '' ?>">Possédés</a>
                <a href="<?= Moncine\View::escape($possessionLink(Moncine\BdRepository::POSSESSION_UNOWNED)) ?>"
                   class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\BdRepository::POSSESSION_UNOWNED ? ' is-active' : '' ?>">Non possédés</a>
                <a href="<?= Moncine\View::escape($possessionLink(Moncine\BdRepository::FILTER_HORS_SERIE)) ?>"
                   class="btn btn-secondary btn-sm<?= $possessionFilter === Moncine\BdRepository::FILTER_HORS_SERIE ? ' is-active' : '' ?>">Hors-série</a>
            </nav>
        <?php endif; ?>

        <?php if ($totalCount === 0): ?>
            <p class="hint">
                <?php if ($hasSearch): ?>
                    Aucun tome ne correspond à votre recherche.
                <?php elseif ($possessionFilter === Moncine\BdRepository::FILTER_HORS_SERIE): ?>
                    Aucun tome hors-série dans cette série.
                <?php elseif ($possessionFilter !== Moncine\BdRepository::POSSESSION_ALL): ?>
                    Aucun tome avec ce filtre.
                <?php else: ?>
                    Aucun tome pour l’instant. <a href="<?= Moncine\View::escape(Moncine\View::bdAddTomeUrl($seriesId, $statut)) ?>">Ajouter le premier tome</a>.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="hint">
                <?php if ($hasSearch): ?>
                    <?= (int) $totalCount ?> tome<?= $totalCount > 1 ? 's' : '' ?> trouvé<?= $totalCount > 1 ? 's' : '' ?>
                    sur <?= (int) ($totalAllTomes ?? $totalCount) ?>.
                <?php elseif ($possessionFilter !== Moncine\BdRepository::POSSESSION_ALL): ?>
                    <?= (int) $totalCount ?> tome<?= $totalCount > 1 ? 's' : '' ?>
                    <?php if ($possessionFilter === Moncine\BdRepository::FILTER_HORS_SERIE): ?>
                        hors-série
                    <?php elseif ($possessionFilter === Moncine\BdRepository::POSSESSION_UNOWNED): ?>
                        non possédé<?= $totalCount > 1 ? 's' : '' ?>
                    <?php else: ?>
                        possédé<?= $totalCount > 1 ? 's' : '' ?>
                    <?php endif; ?>
                    sur <?= (int) ($totalAllTomes ?? $totalCount) ?> au total.
                <?php else: ?>
                    <?= (int) $totalCount ?> tome<?= $totalCount > 1 ? 's' : '' ?>.
                <?php endif; ?>
                <?php if ($isGridView): ?>
                    Cliquez sur une vignette pour ouvrir la fiche.
                <?php else: ?>
                    Cliquez sur un titre pour ouvrir la fiche.
                <?php endif; ?>
            </p>

            <nav class="ui-pill-bar" aria-label="Mode d’affichage">
                <?php foreach (Moncine\CollectionViewMode::listGridChoices() as $modeKey => $modeLabel): ?>
                    <?php
                    $modeActive = $viewMode === $modeKey;
                    $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
                    ?>
                    <a href="<?= Moncine\View::escape($seriesViewUrl($modeKey)) ?>"
                       class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                        <?= Moncine\View::escape($modeLabel) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($isGridView): ?>
                <?php require MONCINE_ROOT . '/templates/_bd_tomes_grid.php'; ?>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="films-table films-table--wide">
                        <thead>
                            <tr>
                                <th scope="col">Tome</th>
                                <th scope="col">Titre</th>
                                <th scope="col">Année</th>
                                <th scope="col">Scénariste</th>
                                <th scope="col">Note</th>
                                <th scope="col">Lu le</th>
                                <th scope="col">Exemplaire</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tomes as $tome): ?>
                                <?php
                                $bibId = (int) ($tome['id'] ?? 0);
                                $isPossessed = !empty($tome['is_possessed']);
                                $rowClass = (!$isWishlist && !$isPossessed) ? ' films-table__row--unowned' : '';
                                ?>
                                <tr class="<?= trim($rowClass) ?>">
                                    <td>
                                        <?php if (!empty($tome['est_hors_serie'])): ?>
                                            <span class="badge">HS</span>
                                        <?php endif; ?>
                                        <?= Moncine\View::escape((string) ($tome['tome_summary'] ?? '')) ?: '—' ?>
                                    </td>
                                    <td>
                                        <a href="<?= Moncine\View::escape(Moncine\View::bdUrl($bibId)) ?>">
                                            <?= Moncine\View::escape((string) ($tome['display_titre'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= (int) ($tome['annee'] ?? 0) > 0 ? (int) $tome['annee'] : '—' ?></td>
                                    <td><?= Moncine\View::escape((string) ($tome['scenariste'] ?? '')) ?: '—' ?></td>
                                    <td><?php if (isset($tome['note_max']) && (int) $tome['note_max'] >= 1):
                                        $score = (int) $tome['note_max'];
                                        $showLabel = false;
                                        $size = 'small';
                                        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                                    else:
                                        echo '—';
                                    endif; ?></td>
                                    <td><?= (string) ($tome['read_at_label'] ?? '') !== ''
                                        ? Moncine\View::escape((string) $tome['read_at_label'])
                                        : '—' ?></td>
                                    <td>
                                        <?php if (!$isWishlist && !$isPossessed): ?>
                                            <span class="magazine-tag magazine-tag--none">Non possédé</span>
                                        <?php else: ?>
                                            <?= Moncine\View::escape((string) ($tome['possession_label'] ?? '')) ?: '—' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= Moncine\View::escape(Moncine\View::bdUrl($bibId)) ?>"
                                           class="btn btn-secondary btn-sm">Modifier</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($seriesInLibrary)): ?>
            <?php
            $pageStatut = $statut;
            $tomeCount = (int) ($totalAllTomes ?? 0);
            require MONCINE_ROOT . '/templates/_bd_series_remove_button.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
