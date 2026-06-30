<?php
/** @var array<string, mixed>|null $series */
/** @var list<array<string, mixed>> $tomes */
/** @var string $statut */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $searchQuery */
/** @var string $viewMode */
/** @var int $totalCount */
/** @var int $suggestTomeNumero */
/** @var string $kindLabel */
/** @var bool $seriesInLibrary */
?>
<section>
    <?php if ($series === null): ?>
        <h1>Série introuvable</h1>
        <p><a href="/bd.php">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc(trim((string) ($series['poster_url'] ?? '')) ?: null);
        $isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
        $viewMode = Moncine\CollectionViewMode::normalizeBdSeries($viewMode ?? '');
        $isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
        $seriesQuery = array_filter([
            'statut' => $statut,
            'q' => trim($searchQuery) !== '' ? $searchQuery : null,
        ]);
        $seriesViewUrl = static function (string $mode) use ($seriesId, $sortBy, $sortDir, $seriesQuery): string {
            return Moncine\View::bdSeriesUrl($seriesId, $sortBy, $sortDir, $seriesQuery, $mode);
        };
        ?>
        <header class="magazine-series-header">
            <p>
                <a href="<?= $isWishlist ? '/bd-envies.php' : '/bd.php' ?>" class="btn btn-secondary btn-sm">← Retour</a>
            </p>
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
                    </p>
                    <?php if (trim((string) ($series['notes'] ?? '')) !== ''): ?>
                        <p class="hint"><?= nl2br(Moncine\View::escape((string) $series['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <p>
                <a href="<?= Moncine\View::escape(Moncine\View::bdAddTomeUrl($seriesId, $statut)) ?>"
                   class="btn btn-accent">Ajouter un tome</a>
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
            <?php if (!$isGridView): ?>
                <input type="hidden" name="view" value="list">
            <?php endif; ?>
            <label for="serie_bd_q">Rechercher dans cette série</label>
            <input type="search" name="q" id="serie_bd_q"
                   value="<?= Moncine\View::escape($searchQuery) ?>"
                   placeholder="Auteur, genre…">
            <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
        </form>

        <?php if ($totalCount === 0): ?>
            <p class="hint">Aucun tome pour l’instant. <a href="<?= Moncine\View::escape(Moncine\View::bdAddTomeUrl($seriesId, $statut)) ?>">Ajouter le premier tome</a>.</p>
        <?php else: ?>
            <p class="hint">
                <?= (int) $totalCount ?> tome<?= $totalCount > 1 ? 's' : '' ?>.
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
                                    <td><?= Moncine\View::escape((string) ($tome['tome_summary'] ?? '')) ?: '—' ?></td>
                                    <td>
                                        <a href="<?= Moncine\View::escape(Moncine\View::bdUrl($bibId)) ?>">
                                            <?= Moncine\View::escape((string) ($tome['display_titre'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= (int) ($tome['annee'] ?? 0) > 0 ? (int) $tome['annee'] : '—' ?></td>
                                    <td><?= Moncine\View::escape((string) ($tome['scenariste'] ?? '')) ?: '—' ?></td>
                                    <td><?= isset($tome['note_max']) && (int) $tome['note_max'] >= 1
                                        ? (int) $tome['note_max'] . '/10'
                                        : '—' ?></td>
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
    <?php endif; ?>
</section>
