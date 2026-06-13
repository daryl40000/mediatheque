<?php
/** @var list<array<string, mixed>> $games */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $viewMode */
/** @var int $totalCount */
/** @var string $moduleError */

$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';
$viewMode = Moncine\CollectionViewMode::normalize($viewMode ?? '');
$isGridView = Moncine\CollectionViewMode::isGrid($viewMode);

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::gamesSortUrl($column, $sortBy, $sortDir, $query, $viewMode)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page">
    <header class="collection-page__header">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <p class="lead">
            Vos jeux vidéo (physiques ou dématérialisés). Les fiches peuvent être reliées aux
            <strong>tests et previews</strong> de vos magazines (PC Jeux, Joystick…).
        </p>
        <div class="collection-page__actions">
            <a href="/ajouter-jeu.php" class="btn btn-accent">Ajouter un jeu</a>
            <a href="/jeux-envies.php" class="btn btn-secondary">Mes envies jeux</a>
        </div>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success">
            Jeu retiré<?= !empty($_GET['deleted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['deleted_title']) . ' »'
                : '' ?>.
        </div>
    <?php endif; ?>

    <form method="get" action="/jeux.php" class="collection-search">
        <label for="jeux_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="jeux_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, studio, genre…">
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
            <?php if ($isGridView): ?>
                <input type="hidden" name="view" value="grid">
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
            <?php if ($query !== ''): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::gamesCollectionUrl('', $sortBy, $sortDir, $viewMode)) ?>"
                   class="btn btn-secondary btn-sm">Effacer la recherche</a>
            <?php endif; ?>
        </div>
    </form>

    <nav class="ui-pill-bar" aria-label="Mode d’affichage">
        <?php foreach (Moncine\CollectionViewMode::choices() as $modeKey => $modeLabel): ?>
            <?php
            $modeActive = $viewMode === $modeKey;
            $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
            ?>
            <a href="<?= Moncine\View::escape(Moncine\View::gamesCollectionUrl($query, $sortBy, $sortDir, $modeKey)) ?>"
               class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                <?= Moncine\View::escape($modeLabel) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucun jeu dans votre collection pour l’instant.</p>
    <?php else: ?>
        <p class="hint">
            <?= (int) $totalCount ?> jeu<?= $totalCount > 1 ? 'x' : '' ?> trouvé<?= $totalCount > 1 ? 's' : '' ?>.
            <?php if ($isGridView): ?>
                Cliquez sur une vignette pour ouvrir la fiche.
            <?php else: ?>
                Cliquez sur un en-tête de colonne pour trier.
            <?php endif; ?>
        </p>
        <?php if ($isGridView): ?>
            <?php require MONCINE_ROOT . '/templates/_games_collection_grid.php'; ?>
        <?php else: ?>
            <?php require MONCINE_ROOT . '/templates/_games_collection_list.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
