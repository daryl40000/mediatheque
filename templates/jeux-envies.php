<?php
/** @var list<array<string, mixed>> $games */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */

$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::gamesWishlistSortUrl($column, $sortBy, $sortDir, $query)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page wishlist-page">
    <div class="collection-page__head">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></h1>
        <div class="collection-page__head-actions">
            <?php
            $printUrl = Moncine\View::gamesWishlistPrintUrl($query, $sortBy, $sortDir);
            require MONCINE_ROOT . '/templates/_print_button.php';
            ?>
            <a class="btn btn-secondary" href="/gerer-partages.php?domain=<?= Moncine\MediaDomain::JEU ?>&scope=<?= Moncine\ShareLinkScope::WISHLIST ?>">
                Partager mes envies
            </a>
        </div>
    </div>

    <p class="lead">Jeux que vous souhaitez acquérir.</p>

    <nav class="ui-pill-nav" aria-label="Navigation envies jeux">
        <a href="/jeux.php" class="ui-pill">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a>
    </nav>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
        <div class="alert alert-success">Jeu ajouté à votre collection.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['promote_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success">
            Envie retirée<?= !empty($_GET['deleted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['deleted_title']) . ' »'
                : '' ?>.
        </div>
    <?php endif; ?>

    <form method="get" action="/jeux-envies.php" class="collection-search import-form">
        <label for="jeux_w_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="jeux_w_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, studio…"
                   autocomplete="off">
            <?php if ($sortBy !== 'titre'): ?>
                <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <?php endif; ?>
            <?php if (strtolower($sortDir) === 'desc'): ?>
                <input type="hidden" name="dir" value="desc">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </div>
        <div class="collection-search__add">
            <a href="/ajouter-jeu.php?statut=wishlist" class="btn btn-primary">Ajouter une envie</a>
        </div>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune envie jeu pour l’instant.</p>
    <?php else: ?>
        <p class="hint"><?= (int) $totalCount ?> envie<?= $totalCount > 1 ? 's' : '' ?> trouvée<?= $totalCount > 1 ? 's' : '' ?>.</p>
        <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
        <div class="table-scroll">
            <table class="films-table films-table--sortable films-table--wide">
                <thead>
                    <tr>
                        <?php $sortHeader('Titre', 'titre'); ?>
                        <th scope="col">Plateforme</th>
                        <?php $sortHeader('Année', 'annee'); ?>
                        <?php $sortHeader('Studio', 'studio'); ?>
                        <?php $sortHeader('Ajouté le', 'added_at'); ?>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                        <?php $gameId = (int) ($game['id'] ?? 0); ?>
                        <tr>
                            <td>
                                <?php require MONCINE_ROOT . '/templates/_game_list_title.php'; ?>
                            </td>
                            <td><?= Moncine\View::escape((string) ($game['platform_short'] ?? '')) ?></td>
                            <td><?= (int) ($game['annee'] ?? 0) > 0 ? (int) $game['annee'] : '—' ?></td>
                            <td><?= Moncine\View::escape((string) ($game['studio'] ?? '')) ?></td>
                            <td><?= (string) ($game['added_at_label'] ?? '') !== ''
                                ? Moncine\View::escape((string) $game['added_at_label'])
                                : '—' ?></td>
                            <td class="wishlist-actions">
                                <?php
                                $return = 'envies';
                                $extraHiddenFields = [
                                    ['q', $query],
                                    ['sort', $sortBy],
                                    ['dir', $sortDir],
                                ];
                                require MONCINE_ROOT . '/templates/_game_promote_form.php';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
